/* ============================================================
   Гарант — общие скрипты сайта (reveal-анимации).
   Загружается последним.
   ============================================================ */

(function () {
  const items = document.querySelectorAll('.reveal');
  if (!('IntersectionObserver' in window)) {
    items.forEach(el => el.classList.add('is-visible'));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.01, rootMargin: '0px 0px -40px 0px' });

  items.forEach(el => observer.observe(el));

  // Страховка: показать элементы, уже находящиеся во вьюпорте
  const revealVisible = () => {
    items.forEach(el => {
      if (el.classList.contains('is-visible')) return;
      const r = el.getBoundingClientRect();
      if (r.top < window.innerHeight && r.bottom > 0) {
        el.classList.add('is-visible');
      }
    });
  };
  revealVisible();
  window.addEventListener('load', revealVisible);
  setTimeout(revealVisible, 300);
})();