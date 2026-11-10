const {
    orders: ORDERS,
    rolePresets: ROLE_PRESETS,
    roleActionPresets: ROLE_ACTION_PRESETS,
    newsPerms: NEWS_PERMS,
} = window.ADMIN_DATA;

const STATUS_LABELS = { new:'Новая', processing:'В обработке', done:'Принята', cancelled:'Отклонена' };

const modal       = document.getElementById('orderModal');
const modalClose  = document.getElementById('modalClose');
const modalTitle  = document.getElementById('orderModalTitle');
const modalSub    = document.getElementById('modalSub');
const modalImage  = document.getElementById('modalImage');
const modalAccept = document.getElementById('modalAccept');
const modalReject = document.getElementById('modalReject');
let currentId = null;
const fmtPrice = n => n.toLocaleString('ru-RU') + ' ₽';

function openOrder(id){
    const o = ORDERS.find(x => x.id === String(id));
    if (!o) return;
    currentId = o.id;
    const fullName = `${o.last_name} ${o.first_name}`.trim() || 'Без имени';
    modalTitle.textContent = `Заявка #${o.id}`;
    modalSub.textContent = o.created_at || '';
    modalImage.src = o.image;
    modalImage.alt = o.product || 'Товар';
    document.getElementById('dName').textContent = fullName;
    document.getElementById('dPhone').innerHTML = o.phone ? `<a href="tel:${o.phone.replace(/[^\d+]/g,'')}">${o.phone}</a>` : '—';
    document.getElementById('dEmail').innerHTML = o.email ? `<a href="mailto:${o.email}">${o.email}</a>` : '—';
    document.getElementById('dProduct').textContent = o.product || '—';
    document.getElementById('dPrice').textContent = fmtPrice(o.price);
    document.getElementById('dDate').textContent = o.created_at || '—';
    document.getElementById('dIp').textContent = o.ip || '—';
    document.getElementById('dUa').textContent = o.user_agent || '—';
    const st = STATUS_LABELS[o.status] || STATUS_LABELS.new;
    document.getElementById('dStatus').innerHTML = `<span class="badge status-${o.status}">${st}</span>`;
    [modalAccept, modalReject].forEach(b => { if (b) { b.disabled = false; b.style.opacity = ''; b.style.cursor = ''; } });
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(){ modal.classList.remove('open'); document.body.style.overflow = ''; }

document.querySelectorAll('.order-row').forEach(row => {
    row.addEventListener('click', () => openOrder(row.dataset.id));
});
modalClose.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

function applyFakeStatus(newStatus){
    if (!currentId) return;
    const row = document.querySelector(`.order-row[data-id="${currentId}"]`);
    const label = STATUS_LABELS[newStatus];
    if (row){
        const badge = row.querySelector('[data-role="status"]');
        if (badge){ badge.className = `badge status-${newStatus}`; badge.textContent = label; }
    }
    const dStatus = document.getElementById('dStatus');
    dStatus.innerHTML = `<span class="badge status-${newStatus}">${label}</span>`;
    [modalAccept, modalReject].forEach(b => { if (b) { b.disabled = true; b.style.opacity = '.5'; b.style.cursor = 'not-allowed'; } });
}
if (modalAccept) modalAccept.addEventListener('click', () => applyFakeStatus('done'));
if (modalReject) modalReject.addEventListener('click', () => applyFakeStatus('cancelled'));

/* ---------- Переключение вкладок ---------- */
const views = document.querySelectorAll('.view');
const sideButtons = document.querySelectorAll('.side-item[data-view]');
function switchView(name, updateHash = true){
    views.forEach(v => v.classList.toggle('active', v.id === 'view-' + name));
    sideButtons.forEach(b => b.classList.toggle('active', b.dataset.view === name));
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (updateHash) {
        history.replaceState(null, '', name === 'dashboard' ? 'admin.php' : 'admin.php?view=' + name);
    }
}
sideButtons.forEach(btn => { btn.addEventListener('click', () => switchView(btn.dataset.view)); });
(function(){
    const params = new URLSearchParams(location.search);
    const view = params.get('view') || 'dashboard';
    switchView(view, false);
})();

/* ---------- Логика вкладки «Сотрудники» ---------- */
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

function setCheckboxes(selector, values){
    document.querySelectorAll(selector).forEach(cb => { cb.checked = values.includes(cb.value); });
}
function applyRolePreset(role){
    setCheckboxes('.perm-views',   ROLE_PRESETS[role]        || []);
    setCheckboxes('.perm-actions', ROLE_ACTION_PRESETS[role] || []);
}
function resetStaffForm(){
    if (!staffForm) return;
    staffForm.querySelector('form').reset();
    editModeInput.value = '0';
    usernameInput.readOnly = false;
    usernameInput.style.background = '';
    staffTitle.textContent = 'Новый сотрудник';
    staffModeBadge.textContent = 'создание';
    staffModeBadge.style.background = '';
    staffModeBadge.style.color = '';
    staffSubmitBtn.textContent = 'Создать сотрудника';
    pwdHint.textContent = '*';
    pwdSub.textContent = 'Минимум 6 символов. Обязательно при создании.';
    applyRolePreset(roleSelect.value);
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
        usernameInput.value = row.dataset.username;
        usernameInput.readOnly = true;
        usernameInput.style.background = '#f0f0f2';
        fullnameInput.value = row.dataset.fullname;
        passwordInput.value = '';
        roleSelect.value = row.dataset.role;
        setCheckboxes('.perm-views', JSON.parse(row.dataset.views || '[]'));
        setCheckboxes('.perm-actions', JSON.parse(row.dataset.actions || '[]'));
        editModeInput.value = '1';
        staffTitle.textContent = 'Редактирование: ' + row.dataset.username;
        staffModeBadge.textContent = 'изменение';
        staffModeBadge.style.background = 'var(--blue-light)';
        staffModeBadge.style.color = '#1e4f9a';
        staffSubmitBtn.textContent = 'Сохранить изменения';
        pwdHint.textContent = '';
        pwdSub.textContent = 'Оставьте пустым, чтобы не менять пароль.';
        switchView('staff', true);
        staffForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

/* ============================================================
   ЛОГИКА ВКЛАДКИ «НОВОСТИ»
   ============================================================ */
const newsForm      = document.getElementById('newsForm');
const newsFormEl    = document.getElementById('newsFormEl');
const newsFormTitle = document.getElementById('newsFormTitle');
const newsModeBadge = document.getElementById('newsModeBadge');
const newsSubmitBtn = document.getElementById('newsSubmitBtn');
const newsCancelBtn = document.getElementById('newsCancelBtn');
const newsAlert     = document.getElementById('newsAlert');
const newsList      = document.getElementById('newsList');
const newsCounter   = document.getElementById('newsCounter');

const nfId          = document.getElementById('nf-id');
const nfTitle       = document.getElementById('nf-title');
const nfSubtitle    = document.getElementById('nf-subtitle');
const nfDescription = document.getElementById('nf-description');
const nfImage       = document.getElementById('nf-image');
const nfFile        = document.getElementById('nf-file');
const nfUrl         = document.getElementById('nf-url');
const imgPreview    = document.getElementById('imgPreview');
const imgUploadRow  = document.getElementById('imgUploadRow');
const imgUrlRow     = document.getElementById('imgUrlRow');
const nfStatus      = document.getElementById('nf-status');

let newsImageSource = 'upload'; // 'upload' | 'url'

function showNewsAlert(ok, text){
    if (!newsAlert) return;
    newsAlert.innerHTML = `<div class="news-alert ${ok ? 'ok' : 'err'}">${ok ? '✓' : '✕'} ${text}</div>`;
    setTimeout(() => { newsAlert.innerHTML = ''; }, 5000);
}

function setPreview(src){
    if (!imgPreview) return;
    if (!src){
        imgPreview.classList.add('empty');
        imgPreview.innerHTML = '<span>Нет картинки</span>';
        return;
    }
    imgPreview.classList.remove('empty');
    imgPreview.innerHTML = `<img src="${src}" alt="Превью">`;
}

/* Переключение источника картинки */
document.querySelectorAll('.img-source-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.img-source-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        newsImageSource = tab.dataset.src;
        if (newsImageSource === 'upload'){
            imgUploadRow.style.display = '';
            imgUrlRow.style.display = 'none';
            // Если до этого был URL — очищаем
            if (nfUrl && !nfUrl.value.startsWith('uploads/news/')) {
                nfImage.value = '';
                setPreview('');
            }
        } else {
            imgUploadRow.style.display = 'none';
            imgUrlRow.style.display = '';
            nfImage.value = nfUrl ? nfUrl.value : '';
            setPreview(nfImage.value);
        }
    });
});

/* Превью при выборе URL */
if (nfUrl) {
    nfUrl.addEventListener('input', () => {
        nfImage.value = nfUrl.value.trim();
        setPreview(nfImage.value);
    });
}

/* Загрузка файла */
if (nfFile) {
    nfFile.addEventListener('change', async () => {
        if (!nfFile.files || !nfFile.files[0]) return;
        const file = nfFile.files[0];
        if (file.size > 5 * 1024 * 1024) {
            showNewsAlert(false, 'Файл слишком большой (макс. 5 МБ)');
            nfFile.value = '';
            return;
        }
        const fd = new FormData();
        fd.append('image', file);
        fd.append('action', 'upload');
        try {
            newsSubmitBtn.disabled = true;
            const origText = newsSubmitBtn.innerHTML;
            newsSubmitBtn.innerHTML = '<span class="spinner"></span>Загрузка...';
            const res = await fetch('news.php?action=upload', { method: 'POST', body: fd });
            const data = await res.json();
            newsSubmitBtn.disabled = false;
            newsSubmitBtn.innerHTML = origText;
            if (data.ok) {
                nfImage.value = data.url;
                setPreview(data.url);
                showNewsAlert(true, 'Картинка загружена');
            } else {
                showNewsAlert(false, data.error || 'Ошибка загрузки');
                nfFile.value = '';
            }
        } catch (err) {
            newsSubmitBtn.disabled = false;
            newsSubmitBtn.innerHTML = origText;
            showNewsAlert(false, 'Ошибка сети: ' + err.message);
            nfFile.value = '';
        }
    });
}

/* Загрузка списка новостей */
async function loadNews(){
    if (!newsList) return;
    try {
        const res = await fetch('news.php?action=all');
        const data = await res.json();
        if (!data.ok) {
            newsList.innerHTML = `<div class="empty">Ошибка: ${data.error}</div>`;
            return;
        }
        const items = data.items || [];
        if (newsCounter) newsCounter.textContent = `Всего: ${items.length}`;
        if (items.length === 0) {
            newsList.innerHTML = `<div class="empty" style="grid-column:1/-1">Новостей пока нет. Создайте первую!</div>`;
            return;
        }
        newsList.innerHTML = items.map(n => {
            const statusBadge = n.status === 'published'
                ? '<span class="badge status-published">Опубликовано</span>'
                : '<span class="badge status-draft">Черновик</span>';
            const date = n.created_at ? new Date(n.created_at).toLocaleDateString('ru-RU') : '';
            const imgHtml = n.image
                ? `<img src="${n.image}" alt="${escapeHtml(n.title)}" loading="lazy">`
                : `<div class="placeholder">Нет картинки</div>`;
            const actions = [];
            if (NEWS_PERMS.edit) {
                if (n.status === 'published') {
                    actions.push(`<button class="btn btn-warn" data-act="unpublish" data-id="${n.id}">Снять с публикации</button>`);
                } else {
                    actions.push(`<button class="btn btn-accept" data-act="publish" data-id="${n.id}">Опубликовать</button>`);
                }
                actions.push(`<button class="btn btn-ghost" data-act="edit" data-id="${n.id}">Редактировать</button>`);
            }
            if (NEWS_PERMS.del) {
                actions.push(`<button class="btn btn-reject" data-act="delete" data-id="${n.id}">Удалить</button>`);
            }
            return `
                <div class="news-card" data-id="${n.id}">
                    <div class="news-card-img">${imgHtml}</div>
                    <div class="news-card-body">
                        <div class="news-card-title">${escapeHtml(n.title)}</div>
                        ${n.subtitle ? `<div class="news-card-sub">${escapeHtml(n.subtitle)}</div>` : ''}
                        <div class="news-card-meta">
                            <span>${date}</span>
                            ${statusBadge}
                        </div>
                    </div>
                    ${actions.length ? `<div class="news-card-actions">${actions.join('')}</div>` : ''}
                </div>
            `;
        }).join('');

        // Навешиваем обработчики на кнопки карточек
        newsList.querySelectorAll('[data-act]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                const act = btn.dataset.act;
                if (act === 'edit') {
                    editNews(id);
                } else if (act === 'delete') {
                    if (!confirm('Удалить новость? Это действие нельзя отменить.')) return;
                    await apiNews('delete', { id });
                } else if (act === 'publish') {
                    await apiNews('toggle_status', { id, status: 'published' });
                } else if (act === 'unpublish') {
                    await apiNews('toggle_status', { id, status: 'draft' });
                }
            });
        });
    } catch (err) {
        newsList.innerHTML = `<div class="empty" style="grid-column:1/-1">Ошибка загрузки: ${err.message}</div>`;
    }
}

function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function apiNews(action, payload){
    try {
        const res = await fetch('news.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.ok) {
            showNewsAlert(true, 'Сохранено');
            loadNews();
        } else {
            showNewsAlert(false, data.error || 'Ошибка');
        }
        return data;
    } catch (err) {
        showNewsAlert(false, 'Ошибка сети: ' + err.message);
        return { ok: false };
    }
}

function editNews(id){
    if (!newsForm) return;
    fetch('news.php?action=all')
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            const n = (data.items || []).find(x => String(x.id) === String(id));
            if (!n) return;
            nfId.value = n.id;
            nfTitle.value = n.title || '';
            nfSubtitle.value = n.subtitle || '';
            nfDescription.value = n.description || '';
            nfStatus.value = n.status || 'draft';

            // Определяем источник картинки
            const img = n.image || '';
            if (img.startsWith('uploads/news/')) {
                newsImageSource = 'upload';
                document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'upload'));
                imgUploadRow.style.display = '';
                imgUrlRow.style.display = 'none';
            } else if (img) {
                newsImageSource = 'url';
                document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'url'));
                imgUploadRow.style.display = 'none';
                imgUrlRow.style.display = '';
                nfUrl.value = img;
            } else {
                newsImageSource = 'upload';
                document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'upload'));
                imgUploadRow.style.display = '';
                imgUrlRow.style.display = 'none';
            }
            nfImage.value = img;
            setPreview(img);

            newsFormTitle.textContent = 'Редактирование: ' + n.title;
            newsModeBadge.textContent = 'изменение';
            newsModeBadge.style.background = 'var(--blue-light)';
            newsModeBadge.style.color = '#1e4f9a';
            newsSubmitBtn.textContent = 'Сохранить изменения';

            switchView('news', true);
            newsForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
}

function resetNewsForm(){
    if (!newsFormEl) return;
    newsFormEl.reset();
    nfId.value = '';
    nfImage.value = '';
    setPreview('');
    if (nfFile) nfFile.value = '';
    if (nfUrl) nfUrl.value = '';
    newsImageSource = 'upload';
    document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'upload'));
    imgUploadRow.style.display = '';
    imgUrlRow.style.display = 'none';
    newsFormTitle.textContent = 'Новая публикация';
    newsModeBadge.textContent = 'создание';
    newsModeBadge.style.background = '';
    newsModeBadge.style.color = '';
    newsSubmitBtn.textContent = 'Создать';
}

if (newsCancelBtn) newsCancelBtn.addEventListener('click', resetNewsForm);

if (newsFormEl) {
    newsFormEl.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            id:          nfId.value,
            title:       nfTitle.value.trim(),
            subtitle:    nfSubtitle.value.trim(),
            description: nfDescription.value.trim(),
            image:       nfImage.value.trim(),
            status:      nfStatus.value,
        };
        if (!payload.title) {
            showNewsAlert(false, 'Укажите заголовок');
            return;
        }
        newsSubmitBtn.disabled = true;
        const origText = newsSubmitBtn.innerHTML;
        newsSubmitBtn.innerHTML = '<span class="spinner"></span>Сохранение...';
        const data = await apiNews('save', payload);
        newsSubmitBtn.disabled = false;
        newsSubmitBtn.innerHTML = origText;
        if (data && data.ok) resetNewsForm();
    });
}

// Первичная загрузка
if (newsList) loadNews();
