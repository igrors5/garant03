/* ============================================================
   Гарант — одиночная модалка «Купить».
   Зависит от глобалов: products, fmt (из products.js),
   cartAdd (из cart.js).
   ============================================================ */

const orderModal     = document.getElementById('orderModal');
const modalClose     = document.getElementById('modalClose');
const orderForm      = document.getElementById('orderForm');
const orderProduct   = document.getElementById('orderProduct');
const formState      = document.getElementById('formState');
const successState   = document.getElementById('successState');
const successText    = document.getElementById('successText');
const successClose   = document.getElementById('successClose');
const formError      = document.getElementById('formError');
const consentGroup   = document.getElementById('consentGroup');
const submitBtn      = document.getElementById('submitBtn');

let currentProduct = null;

function openModal(product) {
  currentProduct = product;
  orderProduct.innerHTML = `<strong>${product.model}</strong> · ${product.memory} · ${product.color} — <strong>${fmt(product.price)}</strong>`;
  formState.style.display = '';
  successState.style.display = 'none';
  orderForm.reset();
  clearErrors();
  orderModal.classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('lastName').focus(), 120);
}
function closeModal() {
  orderModal.classList.remove('open');
  document.body.style.overflow = '';
}
function clearErrors() {
  formError.classList.remove('show');
  formError.textContent = '';
  orderForm.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
  consentGroup.classList.remove('error');
}
function showError(msg) {
  formError.textContent = msg;
  formError.classList.add('show');
  formError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* Клики по карточкам каталога: «В корзину» и «Купить» */
grid.addEventListener('click', e => {
  const add = e.target.closest('.btn-cart');
  if (add) {
    const p = products.find(x => x.id === +add.dataset.id);
    if (p) {
      cartAdd(p);
      add.innerHTML = '✓';
      setTimeout(() => {
        add.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>`;
      }, 900);
    }
    return;
  }
  const buy = e.target.closest('.buy-btn');
  if (!buy) return;
  const p = products.find(x => x.id === +buy.dataset.id);
  if (p) openModal(p);
});

modalClose.addEventListener('click', closeModal);
orderModal.addEventListener('click', e => {
  if (e.target === orderModal) closeModal();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && orderModal.classList.contains('open')) closeModal();
});
successClose.addEventListener('click', closeModal);

function validate() {
  clearErrors();
  let valid = true;
  let firstInvalid = null;

  const lastName  = document.getElementById('lastName');
  const firstName = document.getElementById('firstName');
  const phone     = document.getElementById('phone');
  const email     = document.getElementById('email');
  const consent   = document.getElementById('consent');

  const markInvalid = (el) => {
    el.classList.add('invalid');
    if (!firstInvalid) firstInvalid = el;
    valid = false;
  };

  if (!lastName.value.trim())  markInvalid(lastName);
  if (!firstName.value.trim()) markInvalid(firstName);

  const phoneClean = phone.value.replace(/[\s\-()]/g, '');
  if (!/^(\+7|8)?\d{10}$/.test(phoneClean)) markInvalid(phone);

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) markInvalid(email);

  if (!consent.checked) {
    consentGroup.classList.add('error');
    valid = false;
  }

  if (!valid) {
    if (!consent.checked && !formError.textContent) {
      showError('Необходимо дать согласие на обработку персональных данных.');
    } else if (!formError.textContent) {
      showError('Пожалуйста, проверьте правильность заполнения полей.');
    }
    if (firstInvalid) firstInvalid.focus();
  }

  return valid;
}

orderForm.addEventListener('submit', e => {
  e.preventDefault();
  if (!validate()) return;
  if (!currentProduct) { closeModal(); return; }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Отправка…';

  const payload = {
    product: `${currentProduct.model} ${currentProduct.memory} ${currentProduct.color}`,
    price: currentProduct.price,
    lastName:  document.getElementById('lastName').value.trim(),
    firstName: document.getElementById('firstName').value.trim(),
    phone:     document.getElementById('phone').value.trim(),
    email:     document.getElementById('email').value.trim(),
    consent:   document.getElementById('consent').checked
  };

  fetch('order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
    .then(r => r.json().catch(() => ({ ok: false, error: 'Некорректный ответ сервера.' })))
    .then(res => {
      if (!res.ok) throw new Error(res.error || 'Не удалось отправить заявку.');
      successText.textContent = `Спасибо, ${payload.firstName}! Заявка №${res.id} на ${currentProduct.model} ${currentProduct.memory} принята. Мы свяжемся с вами по телефону или почте для подтверждения.`;
      formState.style.display = 'none';
      successState.style.display = '';
    })
    .catch(err => {
      showError(err.message || 'Не удалось отправить заявку. Попробуйте позже.');
    })
    .finally(() => {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Отправить заявку';
    });
});

['lastName', 'firstName', 'phone', 'email'].forEach(id => {
  document.getElementById(id).addEventListener('input', function () {
    this.classList.remove('invalid');
  });
});
document.getElementById('consent').addEventListener('change', function () {
  if (this.checked) consentGroup.classList.remove('error');
});