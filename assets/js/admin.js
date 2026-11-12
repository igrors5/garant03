/* ============================================================
   Гарант — админ-панель. Клиентская логика
   Ожидает глобалы из layout_foot.php:
     window.ORDERS, window.ROLE_PRESETS,
     window.ROLE_ACTION_PRESETS, window.CURRENT_VIEW
   ============================================================ */

const ORDERS = window.ORDERS || [];
const ROLE_PRESETS = window.ROLE_PRESETS || {};
const ROLE_ACTION_PRESETS = window.ROLE_ACTION_PRESETS || {};

const STATUS_LABELS = {
  new: 'Новая',
  processing: 'В обработке',
  done: 'Принята',
  cancelled: 'Отклонена'
};

const fmtPrice = n => Number(n).toLocaleString('ru-RU') + ' ₽';

/* ============================================================
   Модалка заявки
   ============================================================ */
const modal       = document.getElementById('orderModal');
const modalClose  = document.getElementById('modalClose');
const modalTitle  = document.getElementById('orderModalTitle');
const modalSub    = document.getElementById('modalSub');
const modalImage  = document.getElementById('modalImage');
const modalAccept = document.getElementById('modalAccept');
const modalReject = document.getElementById('modalReject');

let currentId = null;

function openOrder(id) {
  const o = ORDERS.find(x => x.id === String(id));
  if (!o || !modal) return;

  currentId = o.id;
  const fullName = `${o.last_name} ${o.first_name}`.trim() || 'Без имени';

  modalTitle.textContent = `Заявка #${o.id}`;
  modalSub.textContent   = o.created_at || '';
  modalImage.src         = o.image;
  modalImage.alt         = o.product || 'Товар';

  document.getElementById('dName').textContent    = fullName;
  document.getElementById('dPhone').innerHTML     = o.phone ? `<a href="tel:${o.phone.replace(/[^\d+]/g,'')}">${o.phone}</a>` : '—';
  document.getElementById('dEmail').innerHTML     = o.email ? `<a href="mailto:${o.email}">${o.email}</a>` : '—';
  document.getElementById('dProduct').textContent = o.product || '—';
  document.getElementById('dPrice').textContent   = fmtPrice(o.price);
  document.getElementById('dDate').textContent    = o.created_at || '—';
  document.getElementById('dIp').textContent      = o.ip || '—';
  document.getElementById('dUa').textContent      = o.user_agent || '—';

  const st = STATUS_LABELS[o.status] || STATUS_LABELS.new;
  document.getElementById('dStatus').innerHTML =
    `<span class="badge status-${o.status}">${st}</span>`;

  [modalAccept, modalReject].forEach(b => {
    if (b) { b.disabled = false; b.style.opacity = ''; b.style.cursor = ''; }
  });

  modal.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  if (!modal) return;
  modal.classList.remove('open');
  document.body.style.overflow = '';
}

document.querySelectorAll('.order-row').forEach(row => {
  row.addEventListener('click', () => openOrder(row.dataset.id));
});

if (modalClose) modalClose.addEventListener('click', closeModal);
if (modal) {
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && modal && modal.classList.contains('open')) closeModal();
});

/**
 * ВНИМАНИЕ: в исходной версии кнопки «Принять/Отклонить» только меняют
 * бейдж на клиенте. Реальный POST orders_status с фронта не отправляется.
 * Поведение сохранено «как было».
 */
function applyFakeStatus(newStatus) {
  if (!currentId) return;
  const row = document.querySelector(`.order-row[data-id="${currentId}"]`);
  const label = STATUS_LABELS[newStatus];

  if (row) {
    const badge = row.querySelector('[data-role="status"]');
    if (badge) {
      badge.className = `badge status-${newStatus}`;
      badge.textContent = label;
    }
  }
  const dStatus = document.getElementById('dStatus');
  if (dStatus) dStatus.innerHTML = `<span class="badge status-${newStatus}">${label}</span>`;

  [modalAccept, modalReject].forEach(b => {
    if (b) { b.disabled = true; b.style.opacity = '.5'; b.style.cursor = 'not-allowed'; }
  });
}

if (modalAccept) modalAccept.addEventListener('click', () => applyFakeStatus('done'));
if (modalReject) modalReject.addEventListener('click', () => applyFakeStatus('cancelled'));

/* ============================================================
   Переключение вкладок (fallback для программных переходов)
   Основная навигация идёт через ссылки admin.php?view=...
   ============================================================ */
const views = document.querySelectorAll('.view');
const sideButtons = document.querySelectorAll('.side-item[data-view]');

function switchView(name, updateHash = true) {
  views.forEach(v => v.classList.toggle('active', v.id === 'view-' + name));
  sideButtons.forEach(b => b.classList.toggle('active', b.dataset.view === name));
  window.scrollTo({ top: 0, behavior: 'smooth' });
  if (updateHash) {
    history.replaceState(null, '', name === 'dashboard' ? 'admin.php' : 'admin.php?view=' + name);
  }
  if (name === 'cart') loadCarts();
}

sideButtons.forEach(btn => {
  btn.addEventListener('click', e => {
    // Если это <a href="admin.php?view=..."> — дадим перейти обычным способом
    if (btn.tagName === 'A' && btn.getAttribute('href')) return;
    e.preventDefault();
    switchView(btn.dataset.view);
  });
});

/* ============================================================
   Корзины клиентов (используется только если есть view-cart)
   ============================================================ */
function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));
}

async function loadCarts() {
  const el = document.getElementById('cartList');
  if (!el) return;
  try {
    const res = await fetch('cart.php?action=all');
    const data = await res.json();
    if (!data.ok) {
      el.innerHTML = '<div class="empty">Ошибка: ' + escapeHtml(data.error || '') + '</div>';
      return;
    }
    const items = data.items || [];
    const counter = document.getElementById('cartCounter');
    if (counter) counter.textContent = 'Всего: ' + items.length;

    if (!items.length) {
      el.innerHTML = '<div class="empty">Корзин пока нет.</div>';
      return;
    }
    el.innerHTML = items.map(c => {
      const rows = (c.items || []).map(i =>
        `<div>${escapeHtml(i.model)} ${escapeHtml(i.memory)} ${escapeHtml(i.color)} — ` +
        `${Number(i.price || 0).toLocaleString('ru-RU')} ₽ × ${i.qty || 1}</div>`
      ).join('');
      return `
        <div class="cart-item">
          <div class="cart-item-info">
            <div class="cart-item-title">Корзина #${escapeHtml(c.id)}</div>
            <div class="cart-item-meta">
              ${escapeHtml(c.customer || 'Гость')} · ${escapeHtml(c.phone || '—')}
              ${c.created_at ? ' · ' + escapeHtml(c.created_at) : ''}
            </div>
            <div class="cart-item-items">${rows}</div>
          </div>
          <div class="cart-item-total">${Number(c.total || 0).toLocaleString('ru-RU')} ₽</div>
        </div>
      `;
    }).join('');
  } catch (e) {
    el.innerHTML = '<div class="empty">Не удалось загрузить корзины.</div>';
  }
}

if (document.getElementById('view-cart')) loadCarts();

/* ============================================================
   Вкладка «Сотрудники»
   ============================================================ */
const staffForm      = document.getElementById('staffForm');
const staffTitle     = document.getElementById('staffFormTitle');
const staffModeBadge = document.getElementById('staffModeBadge');
const staffSubmitBtn = document.getElementById('staffSubmitBtn');
const staffCancelBtn = document.getElementById('staffCancelBtn');
const editModeInput  = document.getElementById('editMode');
const usernameInput  = document.getElementById('sf-username');
const fullnameInput  = document.getElementById('sf-fullname');
const passwordInput  = document.getElementById('sf-password');
const roleSelect     = document.getElementById('sf-role');
const pwdHint        = document.getElementById('pwd-hint');
const pwdSub         = document.getElementById('pwd-sub');

function setCheckboxes(selector, values) {
  document.querySelectorAll(selector).forEach(cb => {
    cb.checked = values.includes(cb.value);
  });
}

function applyRolePreset(role) {
  setCheckboxes('.perm-views',   ROLE_PRESETS[role]        || []);
  setCheckboxes('.perm-actions', ROLE_ACTION_PRESETS[role] || []);
}

function resetStaffForm() {
  if (!staffForm) return;
  const form = staffForm.querySelector('form');
  if (form) form.reset();
  if (editModeInput) editModeInput.value = '0';
  if (usernameInput) {
    usernameInput.readOnly = false;
    usernameInput.style.background = '';
  }
  if (staffTitle) staffTitle.textContent = 'Новый сотрудник';
  if (staffModeBadge) {
    staffModeBadge.textContent = 'создание';
    staffModeBadge.style.background = '';
    staffModeBadge.style.color = '';
  }
  if (staffSubmitBtn) staffSubmitBtn.textContent = 'Создать сотрудника';
  if (pwdHint) pwdHint.textContent = '*';
  if (pwdSub) pwdSub.textContent = 'Минимум 6 символов. Обязательно при создании.';
  if (roleSelect) applyRolePreset(roleSelect.value);
}

if (staffCancelBtn) staffCancelBtn.addEventListener('click', resetStaffForm);

if (roleSelect) {
  roleSelect.addEventListener('change', () => {
    if (editModeInput && editModeInput.value !== '1') applyRolePreset(roleSelect.value);
  });
}

document.querySelectorAll('.toggle-all').forEach(el => {
  el.addEventListener('click', () => {
    const target = el.dataset.target;
    const cbs = document.querySelectorAll('.perm-' + target);
    const allChecked = Array.from(cbs).every(cb => cb.checked);
    cbs.forEach(cb => cb.checked = !allChecked);
    el.textContent = allChecked ? 'выбрать все' : 'снять все';
  });
});

document.querySelectorAll('.staff-edit').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const row = btn.closest('.staff-table-row');
    if (!row || !staffForm) return;

    if (usernameInput) {
      usernameInput.value = row.dataset.username;
      usernameInput.readOnly = true;
      usernameInput.style.background = '#f0f0f2';
    }
    if (fullnameInput) fullnameInput.value = row.dataset.fullname;
    if (passwordInput) passwordInput.value = '';
    if (roleSelect) roleSelect.value = row.dataset.role;

    setCheckboxes('.perm-views',   JSON.parse(row.dataset.views   || '[]'));
    setCheckboxes('.perm-actions', JSON.parse(row.dataset.actions || '[]'));

    if (editModeInput) editModeInput.value = '1';
    if (staffTitle) staffTitle.textContent = 'Редактирование: ' + row.dataset.username;
    if (staffModeBadge) {
      staffModeBadge.textContent = 'изменение';
      staffModeBadge.style.background = 'var(--blue-light)';
      staffModeBadge.style.color = '#1e4f9a';
    }
    if (staffSubmitBtn) staffSubmitBtn.textContent = 'Сохранить изменения';
    if (pwdHint) pwdHint.textContent = '';
    if (pwdSub) pwdSub.textContent = 'Оставьте пустым, чтобы не менять пароль.';

    switchView('staff', true);
    staffForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});