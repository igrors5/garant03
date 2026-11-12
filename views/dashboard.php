<?php /* $stats, $currentUser */ extract($stats); ?>
<section class="view active" id="view-dashboard">
<div class="main-head">
<div><h1>Панель</h1><p>Сводка по заявкам и продажам</p></div>
<div style="font-size:13px;color:var(--muted)">Обновлено: <?= e(date('d.m.Y H:i')) ?></div>
</div>
<div class="kpis">
<div class="kpi"><div class="kpi-label">Всего заявок</div><div class="kpi-value"><?= $totalOrders ?></div><div class="kpi-sub">за всё время</div></div>
<div class="kpi accent"><div class="kpi-label">Сумма заявок</div><div class="kpi-value"><?= e(fmtPrice($totalSum)) ?></div><div class="kpi-sub">потенциальная выручка</div></div>
<div class="kpi blue"><div class="kpi-label">Принято</div><div class="kpi-value"><?= $statusCount['done'] ?></div><div class="kpi-sub">подтверждённые заявки</div></div>
<div class="kpi danger"><div class="kpi-label">Отклонено</div><div class="kpi-value"><?= $statusCount['cancelled'] ?></div><div class="kpi-sub">отменённые заявки</div></div>
</div>
<div class="two-col">
<div class="panel">
<div class="panel-head"><div class="panel-title">Продажи за 14 дней</div><div class="panel-legend"><div class="legend-item"><span class="legend-dot" style="background:var(--accent)"></span>Сумма заявок</div></div></div>
<div class="bars">
<?php foreach ($days as $d => $info):
$h = (int)round(($info['sum'] / $maxDaySum) * 160);
if ($info['sum'] > 0 && $h < 6) $h = 6;
$label = date('d.m', strtotime($d));
?>
<div class="bar-wrap"><div class="bar" style="height:<?= $h ?>px"><span class="bar-tip"><?= e($label) ?>: <?= e(fmtPrice($info['sum'])) ?> · <?= $info['count'] ?> шт.</span></div><div class="bar-label"><?= e($label) ?></div></div>
<?php endforeach; ?>
</div>
</div>
<div class="panel">
<div class="panel-head"><div class="panel-title">Статусы</div></div>
<div class="status-list">
<?php foreach (STATUS_LABELS as $key => $label):
$cnt = $statusCount[$key] ?? 0;
$pct = $totalOrders > 0 ? round($cnt / $totalOrders * 100) : 0;
?>
<div class="status-row"><div class="status-row-top"><span><?= e($label) ?></span><span><?= $cnt ?> · <?= $pct ?>%</span></div><div class="status-progress"><div class="p-<?= e($key) ?>" style="width:<?= $pct ?>%"></div></div></div>
<?php endforeach; ?>
</div>
</div>
</div>
<div class="panel">
<div class="panel-head"><div class="panel-title">Склад · визуально по заявкам</div><div class="panel-legend"><div class="legend-item"><span class="legend-dot" style="background:var(--accent)"></span>Кол-во заявок на товар</div></div></div>
<?php if (empty($stock)): ?>
<p style="font-size:14px;color:var(--muted)">Пока нет товаров в заявках.</p>
<?php else: ?>
<div class="stock-grid">
<?php foreach ($stock as $name => $qty): ?>
<div class="stock-card">
<div class="stock-img"><img src="<?= e(productImage($name)) ?>" alt="<?= e($name) ?>" loading="lazy"></div>
<div class="stock-info">
<div class="stock-name" title="<?= e($name) ?>"><?= e($name) ?></div>
<div class="stock-bar"><div style="width:<?= round($qty / $maxStock * 100) ?>%"></div></div>
<div class="stock-qty">В заявках: <strong><?= $qty ?></strong></div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<div class="panel">
<div class="panel-head"><div class="panel-title">Топ товаров по сумме заявок</div></div>
<?php if (empty($topProducts)): ?>
<p style="font-size:14px;color:var(--muted)">Пока нет данных.</p>
<?php else: ?>
<div class="top-products">
<?php foreach ($topProducts as $name => $sum): ?>
<div class="tp-row"><div class="tp-name"><span><?= e($name) ?></span><span><?= e(fmtPrice($sum)) ?></span></div><div class="tp-bar"><div style="width:<?= round($sum / $maxTop * 100) ?>%"></div></div></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</section>