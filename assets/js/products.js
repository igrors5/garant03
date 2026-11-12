/* ============================================================
   Гарант — каталог товаров: данные, фильтры, рендер.
   Экспортирует глобалы: products, fmt, render, grid.
   ============================================================ */

const products = [
  {id:1,  model:"iPhone 18 Pro",     memory:"256 ГБ", color:"Burgundy",        condition:"new",  sim:"eSIM", battery:100, price:128000, image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg"},
  {id:2,  model:"iPhone 17 Pro Max", memory:"256 ГБ", color:"Deep Blue",       condition:"new",  sim:"eSIM", battery:100, price:109000, image:"images/iPhone%2017%20Pro%20Max.jpg"},
  {id:3,  model:"iPhone 17 Pro",     memory:"256 ГБ", color:"Deep Blue",       condition:"new",  sim:"eSIM", battery:100, price:103000, image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro.jpg"},
  {id:4,  model:"iPhone 17",         memory:"256 ГБ", color:"Black",           condition:"new",  sim:"SIM",  battery:100, price:85000,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg"},
  {id:5,  model:"iPhone 16 Pro Max", memory:"256 ГБ", color:"Desert Titanium", condition:"used", sim:"SIM",  battery:94,  price:75000,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg"},
  {id:6,  model:"iPhone 15 Pro Max", memory:"256 ГБ", color:"Natural Titanium",condition:"used", sim:"SIM",  battery:100, price:70000,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg"},
  {id:7,  model:"iPhone 15 Pro",     memory:"256 ГБ", color:"Black Titanium",  condition:"used", sim:"SIM",  battery:100, price:65000,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro.jpg"},
  {id:8,  model:"iPhone 14 Pro Max", memory:"256 ГБ", color:"Gold",            condition:"used", sim:"SIM",  battery:88,  price:51500,  image:"images/iPhone%2014%20Pro%20Max.jpg"},
  {id:9,  model:"iPhone 14",         memory:"128 ГБ", color:"Midnight",        condition:"used", sim:"SIM",  battery:100, price:33500,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-14.jpg"},
  {id:10, model:"iPhone 12 Pro",     memory:"256 ГБ", color:"Silver",          condition:"used", sim:"SIM",  battery:100, price:30000,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-12-pro.jpg"},
  {id:11, model:"iPhone 13",         memory:"128 ГБ", color:"Pink",            condition:"used", sim:"SIM",  battery:100, price:29000,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-13.jpg"},
  {id:12, model:"iPhone 11 Pro Max", memory:"512 ГБ", color:"Midnight Green",  condition:"used", sim:"SIM",  battery:100, price:26000,  image:"images/iPhone%2011%20Pro%20Max.jpg"},
  {id:13, model:"iPhone 12",         memory:"64 ГБ",  color:"Blue",            condition:"used", sim:"SIM",  battery:100, price:17000,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-12.jpg"},
  {id:14, model:"iPhone 11",         memory:"128 ГБ", color:"Red",             condition:"used", sim:"SIM",  battery:100, price:16500,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-11.jpg"},
  {id:15, model:"iPhone 11",         memory:"64 ГБ",  color:"Red",             condition:"used", sim:"SIM",  battery:100, price:13500,  image:"https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-11.jpg"},
];

const grid       = document.getElementById('grid');
const countEl    = document.getElementById('count');
const fModel     = document.getElementById('f-model');
const fMemory    = document.getElementById('f-memory');
const fCondition = document.getElementById('f-condition');
const fSort      = document.getElementById('f-sort');
const fSearch    = document.getElementById('f-search');
const resetBtn   = document.getElementById('reset');

const fmt = n => Number(n).toLocaleString('ru-RU') + ' ₽';

/* Заполнение селектов */
[...new Set(products.map(p => p.model))].forEach(m => fModel.add(new Option(m, m)));
[...new Set(products.map(p => p.memory))]
  .sort((a, b) => parseInt(b) - parseInt(a))
  .forEach(m => fMemory.add(new Option(m, m)));

function render() {
  const model     = fModel.value;
  const memory    = fMemory.value;
  const condition = fCondition.value;
  const search    = fSearch.value.trim().toLowerCase();
  const sort      = fSort.value;

  let list = products.filter(p => {
    if (model && p.model !== model) return false;
    if (memory && p.memory !== memory) return false;
    if (condition && p.condition !== condition) return false;
    if (search) {
      const hay = `${p.model} ${p.memory} ${p.color} ${p.sim}`.toLowerCase();
      if (!hay.includes(search)) return false;
    }
    return true;
  });

  if (sort === 'price-asc')       list.sort((a, b) => a.price - b.price);
  else if (sort === 'price-desc') list.sort((a, b) => b.price - a.price);
  else if (sort === 'battery-desc') list.sort((a, b) => b.battery - a.battery);

  countEl.textContent = `Найдено: ${list.length}`;

  if (!list.length) {
    grid.innerHTML = '<div class="empty">Ничего не найдено. Попробуйте изменить фильтры.</div>';
    return;
  }

  grid.innerHTML = list.map(p => {
    const cond = p.condition === 'new' ? 'Новый' : 'Б/У';
    return `
      <article class="card">
        <div class="card-image">
          <img src="${p.image}" alt="${p.model}" loading="lazy" onerror="this.src='https://placehold.co/400x400/f5f5f7/6e6e73?text=Нет+фото'">
        </div>
        <div>
          <div class="card-title">${p.model}</div>
          <div class="card-meta">${p.memory} · ${p.color}</div>
        </div>
        <div class="badges">
          <span class="badge">${cond}</span>
          <span class="badge">${p.sim}</span>
          <span class="badge">АКБ ${p.battery}%</span>
        </div>
        <div class="card-bottom">
          <span class="price">${fmt(p.price)}</span>
          <div class="card-actions">
            <button class="btn-cart" data-id="${p.id}" title="Добавить в корзину" aria-label="В корзину">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </button>
            <button class="buy-btn" data-id="${p.id}">Купить</button>
          </div>
        </div>
      </article>
    `;
  }).join('');
}

[fModel, fMemory, fCondition, fSort].forEach(el => el.addEventListener('change', render));
fSearch.addEventListener('input', render);
resetBtn.addEventListener('click', () => {
  fModel.value = '';
  fMemory.value = '';
  fCondition.value = '';
  fSort.value = 'default';
  fSearch.value = '';
  render();
});

render();