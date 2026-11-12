/* ============================================================
   Гарант — корзина (localStorage + модалка оформления).
   Зависит от глобалов: products, fmt (из products.js).
   ============================================================ */

const CART_KEY = 'garant_cart_v1';
const cartBtn = document.getElementById('cartBtn');
const cartCount = document.getElementById('cartCount');

/* ---------- Хранилище ---------- */
function cartLoad() {
  try { return JSON.parse(localStorage.getItem(CART_KEY) || '[]'); }
  catch (e) { return []; }
}
function cartSave(items) {
  localStorage.setItem(CART_KEY, JSON.stringify(items));
  cartUpdateCount();
}
function cartUpdateCount() {
  const items = cartLoad();
  const qty = items.reduce((s, i) => s + i.qty, 0);
  if (cartCount) {
    cartCount.textContent = qty;
    cartCount.classList.toggle('pop', qty > 0);
    setTimeout(() => cartCount.classList.remove('pop'), 250);
  }
}
function cartAdd(product) {
  const items = cartLoad();
  const found = items.find(i => i.id === product.id);
  if (found) found.qty += 1;
  else items.push({ ...product, qty: 1 });
  cartSave(items);
}
function cartRemove(id) {
  cartSave(cartLoad().filter(i => i.id !== id));
}
function cartSetQty(id, qty) {
  const items = cartLoad();
  const it = items.find(i => i.id === id);
  if (!it) return;
  it.qty = Math.max(1, qty | 0);
  cartSave(items);
}
function cartTotal() {
  return cartLoad().reduce((s, i) => s + i.price * i.qty, 0);
}

cartUpdateCount();

/* ---------- Модалка корзины ---------- */
const cartModal       = document.getElementById('cartModal');
const cartModalClose  = document.getElementById('cartModalClose');
const cartItemsEl     = document.getElementById('cartItems');
const cartTotalRow    = document.getElementById('cartTotalRow');
const cartTotalValue  = document.getElementById('cartTotalValue');
const cartFormState   = document.getElementById('cartFormState');
const cartSuccess     = document.getElementById('cartSuccess');
const cartSuccessClose= document.getElementById('cartSuccessClose');
const cartFormError   = document.getElementById('cartFormError');
const cartConsentGroup= document.getElementById('cartConsentGroup');
const cartSubmitBtn   = document.getElementById('cartSubmitBtn');

function cartRender() {
  const items = cartLoad();
  if (!items.length) {
    cartItemsEl.innerHTML = '<div class="cart-empty">Корзина пуста</div>';
    cartTotalRow.style.display = 'none';
    return;
  }
  cartTotalRow.style.display = '';
  cartTotalValue.textContent = fmt(cartTotal());
  cartItemsEl.innerHTML = items.map(i => `
    <div class="cart-row">
      <div class="cart-row-img"><img src="${i.image}" alt=""></div>
      <div class="cart-row-info">
        <div class="cart-row-title">${i.model}</div>
        <div class="cart-row-meta">${i.memory} · ${i.color}</div>
        <div class="cart-qty">
          <button type="button" data-act="dec" data-id="${i.id}">−</button>
          <span>${i.qty}</span>
          <button type="button" data-act="inc" data-id="${i.id}">+</button>
        </div>
      </div>
      <div class="cart-row-price">${fmt(i.price * i.qty)}</div>
      <button type="button" class="cart-remove" data-act="del" data-id="${i.id}" title="Удалить">×</button>
    </div>
  `).join('');
}

cartItemsEl.addEventListener('click', e => {
  const btn = e.target.closest('[data-act]');
  if (!btn) return;
  const id = +btn.dataset.id;
  const act = btn.dataset.act;
  if (act === 'inc') cartSetQty(id, (cartLoad().find(i => i.id === id)?.qty || 1) + 1);
  if (act === 'dec') cartSetQty(id, (cartLoad().find(i => i.id === id)?.qty || 1) - 1);
  if (act === 'del') cartRemove(id);
  cartRender();
});

function openCart() {
  cartFormState.style.display = '';
  cartSuccess.style.display = 'none';
  document.getElementById('cartOrderForm').reset();
  cartFormError.classList.remove('show');
  cartConsentGroup.classList.remove('error');
  cartRender();
  cartModal.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeCart() {
  cartModal.classList.remove('open');
  document.body.style.overflow = '';
}

cartBtn.addEventListener('click', openCart);
cartModalClose.addEventListener('click', closeCart);
cartModal.addEventListener('click', e => { if (e.target === cartModal) closeCart(); });
cartSuccessClose.addEventListener('click', closeCart);
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && cartModal.classList.contains('open')) closeCart();
});

/* ---------- Оформление корзины ---------- */
document.getElementById('cartOrderForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  cartFormError.classList.remove('show');
  cartConsentGroup.classList.remove('error');

  const lastName  = document.getElementById('cartLastName').value.trim();
  const firstName = document.getElementById('cartFirstName').value.trim();
  const phone     = document.getElementById('cartPhone').value.trim();
  const email     = document.getElementById('cartEmail').value.trim();
  const consent   = document.getElementById('cartConsent').checked;

  const errs = [];
  if (!lastName) errs.push('Укажите фамилию');
  if (!firstName) errs.push('Укажите имя');
  const phoneClean = phone.replace(/[\s\-()]/g, '');
  if (!/^(\+7|8)?\d{10}$/.test(phoneClean)) errs.push('Некорректный телефон');
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) errs.push('Некорректная почта');
  if (!consent) { cartConsentGroup.classList.add('error'); errs.push('Нужно согласие'); }

  const items = cartLoad();
  if (!items.length) errs.push('Корзина пуста');

  if (errs.length) {
    cartFormError.textContent = errs.join('. ');
    cartFormError.classList.add('show');
    return;
  }

  cartSubmitBtn.disabled = true;
  const orig = cartSubmitBtn.textContent;
  cartSubmitBtn.textContent = 'Отправка…';

  try {
    const res = await fetch('order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        lastName, firstName, phone, email, consent: true,
        items: items.map(i => ({ model: i.model, memory: i.memory, color: i.color, price: i.price, qty: i.qty }))
      })
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Ошибка');

    fetch('cart.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ customer: `${lastName} ${firstName}`, phone, email, items })
    }).catch(() => {});

    cartSave([]);
    cartRender();
    cartFormState.style.display = 'none';
    cartSuccess.style.display = '';
  } catch (err) {
    cartFormError.textContent = err.message;
    cartFormError.classList.add('show');
  } finally {
    cartSubmitBtn.disabled = false;
    cartSubmitBtn.textContent = orig;
  }
});