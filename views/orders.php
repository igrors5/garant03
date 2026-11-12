<?php /* $filteredOrders, $orders, $q, $statusF */ ?>
<section class="view active" id="view-orders">
<div class="main-head"><div><h1>Заявки</h1><p>Все заявки с сайта</p></div></div>
<form class="toolbar" method="get" action="admin.php">
<input type="hidden" name="view" value="orders">
<input type="text" name="q" placeholder="Поиск: имя, телефон, email, товар…" value="<?= e($q) ?>">
<select name="status">
<option value="">Все статусы</option>
<?php foreach (STATUS_LABELS as $key => $label): ?>
<option value="<?= e($key) ?>" <?= $statusF === $key ? 'selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="btn btn-accept" style="flex:0 0 auto;padding:10px 20px">Найти</button>
<?php if ($q !== '' || $statusF !== ''): ?>
<a href="admin.php?view=orders" class="btn btn-reject" style="flex:0 0 auto;padding:10px 20px;text-align:center">Сбросить</a>
<?php endif; ?>
<div class="spacer"></div>
<div class="count">Показано: <?= count($filteredOrders) ?> из <?= count($orders) ?></div>
</form>
<?php if (empty($filteredOrders)): ?>
<div class="empty"><?= empty($orders) ? 'Заявок пока нет. Файл «Заявки.txt» пуст или не найден.' : 'По вашему запросу ничего не найдено.' ?></div>
<?php else: ?>
<div class="orders-table">
<div class="orders-head"><div>#</div><div>Дата</div><div>Клиент</div><div>Телефон</div><div>Товар</div><div>Цена</div><div>Статус</div><div></div></div>
<?php foreach ($filteredOrders as $o):
$status = $o['status'] ?? 'new';
if (!isset(STATUS_LABELS[$status])) $status = 'new';
$fullName = trim(($o['last_name'] ?? '') . ' ' . ($o['first_name'] ?? ''));
$price = (int)($o['price'] ?? 0);
$dateShort = !empty($o['created_at']) ? date('d.m.Y H:i', strtotime($o['created_at'])) : '—';
?>
<div class="order-row" data-id="<?= e($o['id']) ?>">
<div class="cell-id">#<?= e($o['id']) ?></div>
<div class="cell-date"><?= e($dateShort) ?></div>
<div class="cell-name"><?= e($fullName !== '' ? $fullName : 'Без имени') ?></div>
<div class="cell-phone"><?= e($o['phone'] ?? '—') ?></div>
<div class="cell-product" title="<?= e($o['product'] ?? '') ?>"><?= e($o['product'] ?? '—') ?></div>
<div class="cell-price"><?= e(fmtPrice($price)) ?></div>
<div class="cell-status"><span class="badge status-<?= e($status) ?>" data-role="status"><?= e(STATUS_LABELS[$status]) ?></span></div>
<div class="cell-actions"><button type="button" class="row-more" title="Подробнее" aria-label="Подробнее">⋯</button></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>