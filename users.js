// lib/users.js — хранилище сотрудников, роли и права доступа
import { readFileSync, writeFileSync, renameSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import bcrypt from 'bcryptjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
export const USERS_FILE = join(__dirname, '..', 'Сотрудники.json');

export const ROLE_LABELS = {
  admin:    'Администратор',
  operator: 'Оператор',
  viewer:   'Наблюдатель',
};

export const VIEW_LABELS = {
  dashboard: 'Dashboard',
  orders:    'Orders',
  cart:      'Корзина',
  staff:     'Сотрудники',
  settings:  'Settings',
};

export const ACTION_LABELS = {
  'orders.view':   'Просмотр заявок',
  'orders.accept': 'Принимать заявки',
  'orders.reject': 'Отклонять заявки',
  'cart.view':     'Просмотр корзин',
  'staff.manage':  'Управление сотрудниками',
};

export const ROLE_PRESETS = {
  admin:    ['dashboard','orders','cart','staff','settings'],
  operator: ['dashboard','orders','cart'],
  viewer:   ['dashboard'],
};

export const ROLE_ACTION_PRESETS = {
  admin:    ['orders.view','orders.accept','orders.reject','cart.view','staff.manage'],
  operator: ['orders.view','orders.accept','orders.reject','cart.view'],
  viewer:   ['orders.view'],
};

function readUsers() {
  if (!existsSync(USERS_FILE)) return {};
  try {
    const raw = readFileSync(USERS_FILE, 'utf8');
    if (!raw) return {};
    const d = JSON.parse(raw);
    return (d && typeof d === 'object') ? d : {};
  } catch { return {}; }
}

function saveUsers(users) {
  const tmp = USERS_FILE + '.tmp';
  writeFileSync(tmp, JSON.stringify(users, null, 2), { encoding: 'utf8' });
  renameSync(tmp, USERS_FILE);
}

export function usersAll() { return readUsers(); }

export function usersFind(username) {
  const all = readUsers();
  return all[username] || null;
}

export function usersVerify(username, password) {
  const user = usersFind(username);
  if (!user || !user.password_hash) return null;
  if (!bcrypt.compareSync(password, user.password_hash)) return null;
  return user;
}

export function usersCanView(user, view) {
  if ((user?.role || '') === 'admin') return true;
  return (user?.views || []).includes(view);
}

export function usersCanDo(user, action) {
  if ((user?.role || '') === 'admin') return true;
  return (user?.actions || []).includes(action);
}

export function usersUpsert(data) {
  const username = String(data.username || '').trim();
  const role     = String(data.role || 'viewer');
  const fullname = String(data.fullname || '').trim();

  if (!/^[a-zA-Z0-9._-]{3,32}$/.test(username)) {
    return { ok: false, error: 'Логин: 3–32 символа, латиница/цифры/._-' };
  }
  if (!ROLE_LABELS[role]) return { ok: false, error: 'Неизвестная роль.' };

  const all = readUsers();
  const isNew = !all[username];
  const password = String(data.password || '');

  if (isNew && password.length < 6) return { ok: false, error: 'Пароль минимум 6 символов.' };
  if (!isNew && password && password.length < 6) return { ok: false, error: 'Новый пароль минимум 6 символов.' };

  const validViews = Array.isArray(data.views) ? data.views : [];
  const validActs  = Array.isArray(data.actions) ? data.actions : [];

  let views   = [...new Set(validViews.filter(v => VIEW_LABELS[v]))];
  let actions = [...new Set(validActs.filter(a => ACTION_LABELS[a]))];

  if (role === 'admin') {
    views   = Object.keys(VIEW_LABELS);
    actions = Object.keys(ACTION_LABELS);
  }

  const record = all[username] || {
    username,
    created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
  };

  record.role       = role;
  record.fullname   = fullname;
  record.views      = views;
  record.actions    = actions;
  record.updated_at = new Date().toISOString().replace('T', ' ').slice(0, 19);

  if (password) record.password_hash = bcrypt.hashSync(password, 10);
  if (isNew && !record.password_hash) return { ok: false, error: 'Пароль не задан.' };

  all[username] = record;
  try { saveUsers(all); }
  catch { return { ok: false, error: 'Не удалось сохранить файл сотрудников.' }; }

  return { ok: true, username, is_new: isNew };
}

export function usersDelete(username, currentUser) {
  const all = readUsers();
  if (!all[username]) return { ok: false, error: 'Пользователь не найден.' };
  if (username === currentUser) return { ok: false, error: 'Нельзя удалить свою учётную запись.' };
  if ((all[username].role || '') === 'admin') {
    const admins = Object.values(all).filter(u => (u.role || '') === 'admin');
    if (admins.length <= 1) return { ok: false, error: 'Нельзя удалить последнего администратора.' };
  }
  delete all[username];
  try { saveUsers(all); }
  catch { return { ok: false, error: 'Не удалось сохранить файл.' }; }
  return { ok: true };
}

// Инициализация админа по умолчанию при первом запуске
export function ensureAdmin(defaultUser = 'admin', defaultPass = 'change_me_please') {
  const all = readUsers();
  if (!all[defaultUser]) {
    all[defaultUser] = {
      username:      defaultUser,
      fullname:      'Администратор',
      role:          'admin',
      password_hash: bcrypt.hashSync(defaultPass, 10),
      views:         Object.keys(VIEW_LABELS),
      actions:       Object.keys(ACTION_LABELS),
      created_at:    new Date().toISOString().replace('T', ' ').slice(0, 19),
      updated_at:    new Date().toISOString().replace('T', ' ').slice(0, 19),
    };
    saveUsers(all);
  }
}