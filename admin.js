// routes/cart.js — API корзин
import { Router } from 'express';
import { readFileSync, writeFileSync, renameSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { usersFind, usersCanDo } from '../lib/users.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const CART_FILE = join(__dirname, '..', 'Корзины.json');

function cleanStr(s) {
  return String(s ?? '').replace(/[\x00-\x1F\x7F]/gu, '').trim();
}
function readAll() {
  if (!existsSync(CART_FILE)) return [];
  try {
    const raw = readFileSync(CART_FILE, 'utf8');
    if (!raw) return [];
    const d = JSON.parse(raw);
    return Array.isArray(d) ? d : [];
  } catch { return []; }
}
function saveAll(items) {
  const tmp = CART_FILE + '.tmp';
  writeFileSync(tmp, JSON.stringify(items, null, 2), 'utf8');
  renameSync(tmp, CART_FILE);
}

const router = Router();

// Публичное сохранение корзины
router.post('/', (req, res) => {
  const data = req.body && typeof req.body === 'object' ? req.body : {};
  const items = data.items;
  if (!Array.isArray(items) || !items.length) {
    return res.status(422).json({ ok: false, error: 'Корзина пуста' });
  }
  const all = readAll();
  const nextId = all.length ? Math.max(...all.map(c => parseInt(c.id) || 0)) + 1 : 1;

  let total = 0;
  const norm = items.map(i => {
    const price = parseInt(i.price) || 0;
    const qty   = Math.max(1, parseInt(i.qty) || 1);
    total += price * qty;
    return {
      id:     parseInt(i.id) || 0,
      model:  cleanStr(i.model).slice(0, 120),
      memory: cleanStr(i.memory).slice(0, 32),
      color:  cleanStr(i.color).slice(0, 64),
      price, qty,
    };
  });

  all.push({
    id:         String(nextId),
    customer:   cleanStr(data.customer).slice(0, 200),
    phone:      cleanStr(data.phone).slice(0, 40),
    email:      cleanStr(data.email).slice(0, 254),
    items:      norm,
    total,
    status:     'active',
    created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
  });

  try { saveAll(all); }
  catch { return res.status(500).json({ ok: false, error: 'Не удалось сохранить корзину' }); }

  res.json({ ok: true, id: nextId, total });
});

// Ниже — только авторизованные
function requireAuth(req, res, next) {
  if (!req.session?.admin_authed) return res.status(401).json({ ok: false, error: 'Требуется авторизация' });
  const user = usersFind(req.session.admin_user || '');
  if (!user) return res.status(401).json({ ok: false, error: 'Пользователь не найден' });
  req.user = user;
  next();
}

router.get('/', requireAuth, (req, res) => {
  if (!usersCanDo(req.user, 'cart.view')) return res.status(403).json({ ok: false, error: 'Недостаточно прав' });
  const all = readAll().sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''));
  res.json({ ok: true, items: all });
});

router.delete('/', requireAuth, (req, res) => {
  if (!usersCanDo(req.user, 'cart.view')) return res.status(403).json({ ok: false, error: 'Недостаточно прав' });
  const id = String(req.body?.id || '').trim();
  if (!id) return res.status(422).json({ ok: false, error: 'Не указан id' });
  const all = readAll();
  const next = all.filter(c => String(c.id) !== id);
  if (next.length === all.length) return res.status(404).json({ ok: false, error: 'Корзина не найдена' });
  try { saveAll(next); }
  catch { return res.status(500).json({ ok: false, error: 'Не удалось сохранить' }); }
  res.json({ ok: true });
});

export default router;