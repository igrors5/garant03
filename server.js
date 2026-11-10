// server.js — запуск Express
import express from 'express';
import session from 'express-session';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import orderRouter from './routes/order.js';
import cartRouter  from './routes/cart.js';
import adminRouter from './routes/admin.js';
import { ensureAdmin } from './lib/users.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const app = express();

app.set('trust proxy', 1);
app.use(express.json({ limit: '1mb' }));
app.use(express.urlencoded({ extended: true }));

app.use(session({
  secret: process.env.SESSION_SECRET || 'change_me_in_production_please',
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: true, sameSite: 'lax', maxAge: 24 * 60 * 60 * 1000 },
}));

// Статика (index.html сайта + admin.html)
app.use(express.static(join(__dirname, 'public')));

// API
app.post('/api/order', orderRouter);
app.use('/api/order', orderRouter);
app.use('/api/cart',  cartRouter);
app.use('/api/admin', adminRouter);

// Скачивание файлов заявок/корзин (только для авторизованных)
app.get('/download/:file', (req, res) => {
  if (!req.session?.admin_authed) return res.status(401).send('Не авторизован');
  const allowed = ['Заявки.txt', 'Корзины.json'];
  if (!allowed.includes(req.params.file)) return res.status(404).send('Не найден');
  res.download(join(__dirname, req.params.file));
});

ensureAdmin(); // создаёт admin/change_me_please при первом запуске

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Гарант: http://localhost:${PORT}`));