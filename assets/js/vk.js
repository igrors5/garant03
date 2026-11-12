/* ============================================================
   Гарант — VK-виджет сообщества notebookgarant.
   ============================================================ */

(function () {
  var VK_GROUP_ID = 72258761; // ID сообщества vk.com/notebookgarant
  var container = document.getElementById('vk_community');
  if (!container) return;

  // Если ID не задан — показываем заглушку
  if (!VK_GROUP_ID) {
    container.innerHTML = '<div style="padding:40px;text-align:center;color:#6e6e73;font-size:14px">' +
      'Виджет ВКонтакте не настроен. Укажите числовой ID сообщества.<br>' +
      'Сообщество: <a href="https://vk.com/notebookgarant" target="_blank" rel="noopener" style="color:#2bba5e;font-weight:500">vk.com/notebookgarant</a></div>';
    return;
  }

  // 1. Создаём скрипт VK
  var script = document.createElement('script');
  script.src = 'https://vk.com/js/api/openapi.js?169';
  script.async = true;

  // 2. Когда скрипт загрузится — вызываем виджет
  script.onload = function () {
    if (window.VK && VK.Widgets) {
      VK.Widgets.Group('vk_community', {
        mode: 4,
        width: 'auto',
        height: 640,
        color1: 'FFFFFF',
        color2: '1D1D1F',
        color3: '2BBA5E'
      }, VK_GROUP_ID);
    } else {
      container.innerHTML = '<div style="padding:40px;text-align:center;color:#6e6e73;font-size:14px">Не удалось загрузить VK API.</div>';
    }
  };

  // 3. Обработка ошибки загрузки
  script.onerror = function () {
    container.innerHTML = '<div style="padding:40px;text-align:center;color:#6e6e73;font-size:14px">' +
      'Не удалось загрузить виджет ВКонтакте. Проверьте соединение или отключите блокировщик рекламы.<br>' +
      '<a href="https://vk.com/notebookgarant" target="_blank" rel="noopener" style="color:#2bba5e;font-weight:500">Открыть сообщество</a></div>';
  };

  document.body.appendChild(script);
})();