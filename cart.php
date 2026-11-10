<?php
/**
 * cart.php — API корзин: сохранение из сайта и просмотр из админки.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const CART_FILE = __DIR__ . '/Корзины.json';
require __DIR__ . '/users.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function respond(bool $ok, $payload = null, int $code = 200): void {
    http_response_code($code);
    if ($ok) {
        echo json_encode(['ok' => true] + (is_array($payload) ? $payload : []), JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => false, 'error' => (string)$payload], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function cart_all(): array {
    if (!is_file(CART_FILE)) return [];
    $raw = file_get_contents(CART_FILE);
    if ($raw === false || $raw === '') return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function cart_save_all(array $items): bool {
    $tmp = CART_FILE . '.tmp';
    $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, CART_FILE);
}

function clean_str(string $s): string {
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    return trim($s);
}

$action = (string)($_REQUEST['action'] ?? '');

// ---------- Публичное сохранение корзины (с сайта) ----------
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;

    $items = $data['items'] ?? [];
    if (!is_array($items) || !$items) respond(false, 'Корзина пуста', 422);

    $all = cart_all();
    $nextId = 1;
    if ($all) {
        $nextId = max(array_map(fn($c) => (int)($c['id'] ?? 0), $all)) + 1;
    }

    $total = 0;
    $norm = [];
    foreach ($items as $i) {
        $price = (int)($i['price'] ?? 0);
        $qty   = max(1, (int)($i['qty'] ?? 1));
        $total += $price * $qty;
        $norm[] = [
            'id'     => (int)($i['id'] ?? 0),
            'model'  => mb_substr(clean_str((string)($i['model'] ?? '')), 0, 120),
            'memory' => mb_substr(clean_str((string)($i['memory'] ?? '')), 0, 32),
            'color'  => mb_substr(clean_str((string)($i['color'] ?? '')), 0, 64),
            'price'  => $price,
            'qty'    => $qty,
        ];
    }

    $record = [
        'id'         => (string)$nextId,
        'customer'   => mb_substr(clean_str((string)($data['customer'] ?? '')), 0, 200),
        'phone'      => mb_substr(clean_str((string)($data['phone'] ?? '')), 0, 40),
        'email'      => mb_substr(clean_str((string)($data['email'] ?? '')), 0, 254),
        'items'      => $norm,
        'total'      => $total,
        'status'     => 'active',
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $all[] = $record;
    if (!cart_save_all($all)) respond(false, 'Не удалось сохранить корзину', 500);
    respond(true, ['id' => $nextId, 'total' => $total]);
}

// ---------- Ниже — только авторизованные ----------
if (empty($_SESSION['admin_authed'])) respond(false, 'Требуется авторизация', 401);
$user = users_find((string)($_SESSION['admin_user'] ?? ''));
if (!$user) respond(false, 'Пользователь не найден', 401);

if ($action === 'all') {
    if (!users_can_do($user, 'cart.view')) respond(false, 'Недостаточно прав', 403);
    $all = cart_all();
    usort($all, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    respond(true, ['items' => $all]);
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!users_can_do($user, 'cart.view')) respond(false, 'Недостаточно прав', 403);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;
    $id = trim((string)($data['id'] ?? ''));
    if ($id === '') respond(false, 'Не указан id', 422);

    $all = cart_all();
    $new = array_values(array_filter($all, fn($c) => (string)($c['id'] ?? '') !== $id));
    if (count($new) === count($all)) respond(false, 'Корзина не найдена', 404);
    if (!cart_save_all($new)) respond(false, 'Не удалось сохранить', 500);
    respond(true);
}

respond(false, 'Неизвестное действие', 400);