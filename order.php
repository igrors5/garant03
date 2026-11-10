<?php
/**
 * order.php — обработчик заявок с сайта "Гарант"
 *
 * Принимает POST (JSON или form-data) с полями:
 *   lastName, firstName, phone, email, consent
 *   product, price                          — одиночный товар (старый формат)
 *   items[]: {model, memory, color, price, qty}  — корзина (новый формат)
 *
 * Складывает заявки в текстовый файл "Заявки.txt".
 * Отвечает JSON: { ok: true, id: 12 } или { ok: false, error: "..." }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Метод не разрешён. Используйте POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$storageFile = __DIR__ . '/Заявки.txt';
$maxFileSize = 5 * 1024 * 1024;

// ---------- Чтение входных данных ----------
$raw = file_get_contents('php://input');
$data = [];
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $data = $decoded;
}
if (empty($data)) $data = $_POST;

// ---------- Хелперы ----------
function respond(bool $ok, $payload = null, int $code = 200): void {
    http_response_code($code);
    if ($ok) {
        echo json_encode(['ok' => true] + (is_array($payload) ? $payload : []), JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => false, 'error' => (string)$payload], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function clean(string $s): string {
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    return trim($s);
}

function readOrders(string $file): array {
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
        if (!empty($order['id'])) {
            $orders[] = ['id' => (int)$order['id']];
        }
    }
    return $orders;
}

// ---------- Валидация ----------
$errors = [];

$lastName  = clean((string)($data['lastName']  ?? ''));
$firstName = clean((string)($data['firstName'] ?? ''));
$phoneRaw  = clean((string)($data['phone']     ?? ''));
$email     = clean((string)($data['email']     ?? ''));
$consent   = !empty($data['consent']) && $data['consent'] !== 'false' && $data['consent'] !== '0';

$items = [];
if (!empty($data['items']) && is_array($data['items'])) {
    foreach ($data['items'] as $i) {
        $items[] = [
            'model'  => clean((string)($i['model'] ?? '')),
            'memory' => clean((string)($i['memory'] ?? '')),
            'color'  => clean((string)($i['color'] ?? '')),
            'price'  => max(0, (int)($i['price'] ?? 0)),
            'qty'    => max(1, (int)($i['qty'] ?? 1)),
        ];
    }
}
$product = clean((string)($data['product'] ?? ''));
$price   = (int)($data['price'] ?? 0);
if (!$items && $product !== '') {
    $items[] = ['model'=>$product, 'memory'=>'', 'color'=>'', 'price'=>$price, 'qty'=>1];
}
if (!$items) $errors[] = 'Не указан товар.';

if ($lastName === '' || mb_strlen($lastName) < 2) {
    $errors[] = 'Укажите фамилию (минимум 2 символа).';
} elseif (mb_strlen($lastName) > 100) {
    $errors[] = 'Фамилия слишком длинная.';
}
if ($firstName === '' || mb_strlen($firstName) < 2) {
    $errors[] = 'Укажите имя (минимум 2 символа).';
} elseif (mb_strlen($firstName) > 100) {
    $errors[] = 'Имя слишком длинное.';
}

$phoneClean = preg_replace('/[\s\-()]/', '', $phoneRaw) ?? '';
if (!preg_match('/^(\+7|8)?\d{10}$/', $phoneClean)) {
    $errors[] = 'Некорректный номер телефона.';
} else {
    if (strlen($phoneClean) === 11) {
        $phoneClean = '+' . substr($phoneClean, 1);
    } elseif (strlen($phoneClean) === 10) {
        $phoneClean = '+7' . $phoneClean;
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Некорректный адрес электронной почты.';
} elseif (mb_strlen($email) > 254) {
    $errors[] = 'Email слишком длинный.';
}

if (!$consent) {
    $errors[] = 'Необходимо согласие на обработку персональных данных.';
}

if ($errors) {
    respond(false, implode(' ', $errors), 422);
}

// ---------- Защита от спама ----------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = sys_get_temp_dir() . '/garant_order_' . md5($ip) . '.txt';
$now = time();
if (is_file($rateFile)) {
    $last = (int)@file_get_contents($rateFile);
    if ($now - $last < 30) {
        respond(false, 'Слишком много заявок. Подождите немного и попробуйте снова.', 429);
    }
}
@file_put_contents($rateFile, (string)$now, LOCK_EX);

// ---------- Чтение существующего файла (для id) ----------
$existingOrders = [];
if (is_file($storageFile)) {
    if (filesize($storageFile) > $maxFileSize) {
        @rename($storageFile, __DIR__ . '/Заявки_' . date('Y-m-d_H-i-s') . '.txt');
    } else {
        $existingOrders = readOrders($storageFile);
    }
}

$nextId = 1;
if (!empty($existingOrders)) {
    $nextId = max(array_column($existingOrders, 'id')) + 1;
}

$createdAt = date('Y-m-d H:i:s');
$userAgent = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);

// ---------- Сборка блока ----------
$totalPrice = 0;
$itemsLines = '';
foreach ($items as $i) {
    $line = $i['model']
          . ($i['memory'] !== '' ? ' ' . $i['memory'] : '')
          . ($i['color']  !== '' ? ' · ' . $i['color']  : '');
    $line = mb_substr($line, 0, 200);
    $totalPrice += $i['price'] * $i['qty'];
    $itemsLines .= "item: {$line} | price: {$i['price']} | qty: {$i['qty']}\r\n";
}
$productForFile = $items[0]['model']
    . (count($items) > 1 ? ' (+' . (count($items) - 1) . ')' : '')
    . ($items[0]['memory'] !== '' ? ' ' . $items[0]['memory'] : '');

$block  = "=== Заявка #{$nextId} ===\r\n";
$block .= "id: {$nextId}\r\n";
$block .= "created_at: {$createdAt}\r\n";
$block .= "ip: {$ip}\r\n";
$block .= "user_agent: {$userAgent}\r\n";
$block .= "product: " . mb_substr($productForFile, 0, 200) . "\r\n";
$block .= "price: {$totalPrice}\r\n";
$block .= "items_count: " . count($items) . "\r\n";
$block .= $itemsLines;
$block .= "last_name: {$lastName}\r\n";
$block .= "first_name: {$firstName}\r\n";
$block .= "phone: {$phoneClean}\r\n";
$block .= "email: {$email}\r\n";
$block .= "consent: yes\r\n";
$block .= "status: new\r\n";
$block .= "---\r\n\r\n";

$handle = @fopen($storageFile, 'ab');
if ($handle === false) {
    respond(false, 'Не удалось сохранить заявку. Попробуйте позже.', 500);
}
if (!flock($handle, LOCK_EX)) {
    fclose($handle);
    respond(false, 'Не удалось сохранить заявку. Попробуйте позже.', 500);
}
$written = fwrite($handle, $block);
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);

if ($written === false || $written !== strlen($block)) {
    respond(false, 'Не удалось сохранить заявку. Попробуйте позже.', 500);
}

respond(true, [
    'id'      => $nextId,
    'message' => 'Заявка принята.',
]);