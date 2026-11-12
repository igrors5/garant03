<?php /* $stats, $currentUser */ extract($stats); ?>
<section class="view active" id="view-settings">
<div class="main-head"><div><h1>Настройки</h1><p>Информация о хранилище и обслуживании</p></div></div>
<div class="panel">
<div class="panel-head"><div class="panel-title">Хранилище заявок</div></div>
<div class="settings-list">
<div class="settings-row"><div class="label">Файл</div><div class="value mono"><?= e(basename(STORAGE_FILE)) ?></div></div>
<div class="settings-row"><div class="label">Полный путь</div><div class="value mono"><?= e(STORAGE_FILE) ?></div></div>
<div class="settings-row"><div class="label">Существует</div><div class="value"><?= is_file(STORAGE_FILE) ? 'Да' : 'Нет' ?></div></div>
<div class="settings-row"><div class="label">Размер</div><div class="value"><?= is_file(STORAGE_FILE) ? number_format(filesize(STORAGE_FILE), 0, '.', ' ') . ' байт' : '—' ?></div></div>
<div class="settings-row"><div class="label">Заявок в файле</div><div class="value"><?= $totalOrders ?></div></div>
<div class="settings-row"><div class="label">Экспорт</div><div class="value"><?php if (is_file(STORAGE_FILE)): ?><a href="<?= e(basename(STORAGE_FILE)) ?>" download class="btn btn-accept" style="display:inline-block;padding:8px 18px;flex:none">Скачать .txt</a><?php else: ?>—<?php endif; ?></div></div>
</div>
</div>
<div class="panel">
<div class="panel-head"><div class="panel-title">Хранилище корзин</div></div>
<div class="settings-list">
<div class="settings-row"><div class="label">Файл</div><div class="value mono"><?= e(basename(CART_FILE)) ?></div></div>
<div class="settings-row"><div class="label">Существует</div><div class="value"><?= is_file(CART_FILE) ? 'Да' : 'Нет' ?></div></div>
<div class="settings-row"><div class="label">Экспорт</div><div class="value"><?php if (is_file(CART_FILE)): ?><a href="<?= e(basename(CART_FILE)) ?>" download class="btn btn-accept" style="display:inline-block;padding:8px 18px;flex:none">Скачать .json</a><?php else: ?>—<?php endif; ?></div></div>
</div>
</div>
<div class="panel">
<div class="panel-head"><div class="panel-title">Безопасность</div></div>
<div class="settings-list">
<div class="settings-row"><div class="label">Аутентификация</div><div class="value">Логин + пароль (сессия)</div></div>
<div class="settings-row"><div class="label">Защита от перебора</div><div class="value">5 попыток / 10 минут</div></div>
<div class="settings-row"><div class="label">Cookie</div><div class="value">httponly, SameSite=Lax</div></div>
</div>
</div>
</section>