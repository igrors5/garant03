// routes/order.js — обработчик заявок с сайта
import { Router } from 'express';
import { appendFileSync, readFileSync, renameSync, existsSync, statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const storageFile = join(__dirname, '..', 'Заявки.txt');
const MAX_FILE_SIZE = 5 * 1024 * 1024;

// Rate-limit по IP: не чаще 1 заявки в 30 секунд
const rateMap = new Map();

function clean(s) {
  return String(s ?? '').replace(/[\x00-\x1F\x7F]/gu, '').trim();
}

function readExistingIds() {
  if (!existsSync(storageFile)) return [];
  const content = readFileSync(storageFile, 'utf8');
  const ids = [];
  const re = /^id:\s*(\d+)/gm;
  let m;
  while ((m = re.exec(content))) ids.push(parseInt(m[1], 10));
  return ids;
}

const router = Router();

router.post('/', (req, res) => {
  const data = req.body && typeof req.body === 'object' ? req.body : {};

  const errors = [];
  const lastName  = clean(data.lastName);
  const firstName = clean(data.firstName);
  const phoneRaw  = clean(data.phone);
  const email     = clean(data.email);
  const consent   = !!data.consent && data.consent !== 'false' && data.consent !== '0' && data.consent !== false;

  let items = [];
  if (Array.isArray(data.items) && data.items.length) {
    items = data.items.map(i => ({
      model:  clean(i.model),
      memory: clean(i.memory),
      color:  clean(i.color),
      price:  Math.max(0, parseInt(i.price) || 0),
      qty:    Math.max(1, parseInt(i.qty)   || 1),
    }));
  }
  const product = clean(data.product);
  const price   = parseInt(data.price) || 0;
  if (!items.length && product) {
    items.push({ model: product, memory: '', color: '', price, qty: 1 });
  }

  if (!items.length) errors.push('Не указан товар.');
  if (!lastName  || lastName.length  < 2) errors.push('Укажите фамилию (минимум 2 символа).');
  else if (lastName.length  > 100) errors.push('Фамилия слишком длинная.');
  if (!firstName || firstName.length < 2) errors.push('Укажите имя (минимум 2 символа).');
  else if (firstName.length > 100) errors.push('Имя слишком длинное.');

  const phoneClean = phoneRaw.replace(/[\s\-()]/g, '');
  if (!/^(\+7|8)?\d{10}$/.test(phoneClean)) {
    errors.push('Некорректный номер телефона.');
  }
  let phoneFinal = phoneClean;
  if (phoneClean.length === 11) phoneFinal = '+' + phoneClean.slice(1);
  else if (phoneClean.length === 10) phoneFinal = '+7' + phoneClean;

  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) errors.push('Некорректный адрес электронной почты.');
  else if (email.length > 254) errors.push('Email слишком длинный.');

  if (!consent) errors.push('Необходимо согласие на обработку персональных данных.');

  if (errors.length) return res.status(422).json({ ok: false, error: errors.join(' ') });

  // Rate-limit
  const ip = req.ip || req.socket.remoteAddress || '0.0.0.0';
  const now = Date.now();
  const last = rateMap.get(ip) || 0;
  if (now - last < 30_000) {
    return res.status(429).json({ ok: false, error: 'Слишком много заявок. Подождите немного и попробуйте снова.' });
  }
  rateMap.set(ip, now);

  // Ротация файла
  try {
    if (existsSync(storageFile) && statSync(storageFile).size > MAX_FILE_SIZE) {
      const ts = new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19);
      renameSync(storageFile, join(__dirname, '..', `Заявки_${ts}.txt`));
    }
  } catch {}

  const existing = readExistingIds();
  const nextId = existing.length ? Math.max(...existing) + 1 : 1;
  const createdAt = new Date().toISOString().replace('T', ' ').slice(0, 19);
  const userAgent = String(req.get('user-agent') || '').slice(0, 300);

  let totalPrice = 0;
  let itemsLines = '';
  for (const i of items) {
    const line = (i.model + (i.memory ? ' ' + i.memory : '') + (i.color ? ' · ' + i.color : '')).slice(0, 200);
    totalPrice += i.price * i.qty;
    itemsLines += `item: ${line} | price: ${i.price} | qty: ${i.qty}\r\n`;
  }
  const productForFile = (items[0].model +
    (items.length > 1 ? ` (+${items.length - 1})` : '') +
    (items[0].memory ? ' ' + items[0].memory : '')).slice(0, 200);

  const block =
`=== Заявка #${nextId} ===\r
id: ${nextId}\r
created_at: ${createdAt}\r
ip: ${ip}\r
user_agent: ${userAgent}\r
product: ${productForFile}\r
price: ${totalPrice}\r
items_count: ${items.length}\r
${itemsLines}last_name: ${lastName}\r
first_name: ${firstName}\r
phone: ${phoneFinal}\r
email: ${email}\r
consent: yes\r
status: new\r
---\r
\r
`;

  try {
    appendFileSync(storageFile, block, 'utf8');
  } catch {
    return res.status(500).json({ ok: false, error: 'Не удалось сохранить заявку. Попробуйте позже.' });
  }

  res.json({ ok: true, id: nextId, message: 'Заявка принята.' });
});

export default router;