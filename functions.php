<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmtPrice(int $price): string {
    return number_format($price, 0, '.', ' ') . ' ₽';
}

function productImage(string $product, array $map = PRODUCT_IMAGES): string {
    foreach ($map as $needle => $path) {
        if (mb_stripos($product, $needle) !== false) return $path;
    }
    return 'https://placehold.co/400x400/f5f5f7/6e6e73?text=Нет+фото';
}

function update_order_status(string $file, string $orderId, string $newStatus): array {
    if (!in_array($newStatus, ['new','processing','done','cancelled'], true)) {
        return ['ok' => false, 'error' => 'Неизвестный статус.'];
    }
    if (!is_file($file)) return ['ok' => false, 'error' => 'Файл заявок не найден.'];
    $content = file_get_contents($file);
    if ($content === false) return ['ok' => false, 'error' => 'Не удалось прочитать файл.'];
    $pattern = '/(=== Заявка #' . preg_quote($orderId, '/') . ' ===\r?\n(?:.*\r?\n)*?status:\s*)\S+/u';
    $replaced = preg_replace($pattern, '${1}' . $newStatus, $content, 1, $count);
    if ($count === 0) return ['ok' => false, 'error' => 'Заявка не найдена.'];
    if (file_put_contents($file, $replaced, LOCK_EX) === false) {
        return ['ok' => false, 'error' => 'Не удалось записать файл.'];
    }
    return ['ok' => true];
}

function parseOrders(string $file): array {
    if (!is_file($file)) return [];
    $content = file_get_contents($file);
    if ($content === false || $content === '') return [];
    $orders = [];
    $blocks = preg_split('/^=== Заявка #\d+ ===\s*$/mu', $content, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $order = [];
        foreach (preg_split('/\r\n|\r|\n/', $block) as $line) {
            $line = trim($line);
            if ($line === '' || $line === '---') continue;
            $pos = strpos($line, ':');
            if ($pos === false) continue;
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            $order[$key] = $val;
        }
        if (!empty($order['id'])) $orders[] = $order;
    }
    usort($orders, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
    return $orders;
}

/** Считает аналитику для dashboard */
function build_stats(array $orders): array {
    $totalSum    = 0;
    $statusCount = ['new' => 0, 'processing' => 0, 'done' => 0, 'cancelled' => 0];
    $productSum  = [];
    $productCnt  = [];
    $daily       = [];

    foreach ($orders as $o) {
        $price = (int)($o['price'] ?? 0);
        $totalSum += $price;
        $st = $o['status'] ?? 'new';
        if (!isset($statusCount[$st])) $st = 'new';
        $statusCount[$st]++;

        $prod = $o['product'] ?? 'Неизвестно';
        $productSum[$prod] = ($productSum[$prod] ?? 0) + $price;
        $productCnt[$prod] = ($productCnt[$prod] ?? 0) + 1;

        $date = substr($o['created_at'] ?? '', 0, 10);
        if ($date !== '') {
            if (!isset($daily[$date])) $daily[$date] = ['count' => 0, 'sum' => 0];
            $daily[$date]['count']++;
            $daily[$date]['sum'] += $price;
        }
    }

    $days = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $days[$d] = $daily[$d] ?? ['count' => 0, 'sum' => 0];
    }

    arsort($productSum);
    $topProducts = array_slice($productSum, 0, 5, true);

    $sums   = array_map(fn($d) => (int)$d['sum'],   $days);
    $counts = array_map(fn($d) => (int)$d['count'], $days);

    $stock = $productCnt;
    arsort($stock);

    return [
        'totalOrders'  => count($orders),
        'totalSum'     => $totalSum,
        'statusCount'  => $statusCount,
        'productSum'   => $productSum,
        'productCnt'   => $productCnt,
        'daily'        => $daily,
        'days'         => $days,
        'topProducts'  => $topProducts,
        'maxDaySum'    => $sums   ? max(1, max($sums))   : 1,
        'maxDayCnt'    => $counts ? max(1, max($counts)) : 1,
        'maxTop'       => $topProducts ? max(1, max(array_values($topProducts))) : 1,
        'stock'        => $stock,
        'maxStock'     => $stock ? max(1, max(array_values($stock))) : 1,
    ];
}