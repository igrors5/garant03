 ============================================================
   Гарант — VK-виджет сообщества notebookgarant.
   Замените VK_GROUP_ID на числовой ID сообщества, если нужно.
   ============================================================ 

(function () {
  var VK_GROUP_ID = 72258761;  ID сообщества vk.comnotebookgarant

  if (!VK_GROUP_ID) {
    var el = document.getElementById('vk_community');
    if (el) el.innerHTML = 'div style=padding40px;text-aligncenter;color#6e6e73;font-size14px' +
      'Виджет ВКонтакте не настроен. Укажите числовой ID сообщества в коде страницы.br' +
      'Сообщество a href=httpsvk.comnotebookgarant target=_blank rel=noopener style=color#2bba5e;font-weight500vk.comnotebookgarantadiv';
    return;
  }

  window.vkAsyncInit = function () {
    VK.Widgets.Group('vk_community', {
      mode 4,
      width 'auto',
      height 640,
      color1 'FFFFFF',
      color2 '1D1D1F',
      color3 '2BBA5E'
    }, VK_GROUP_ID);
  };

  var s = document.createElement('script');
  s.src = 'httpsvk.comjsapiopenapi.js169';
  s.async = true;
  s.onload = function () { if (window.vkAsyncInit) window.vkAsyncInit(); };
  document.head.appendChild(s);
})();