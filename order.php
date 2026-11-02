<?php
/**
 * order.php — обработчик заявок с сайта "Гарант"
 *
 * Принимает POST (JSON или form-data) с полями:
 *   lastName, firstName, phone, email, consent, product, price
 *
 * Складывает заявки в текстовый файл "Заявки.txt".
 * Отвечает JSON: { ok: true, id: 12 } или { ok: false, error: "..." }
 */

declare(strict_types=1);

// ---------- Настройки ----------
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Разрешаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Метод не разрешён. Используйте POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Файл с заявками (в той же папке, что и order.php)
$storageFile = __DIR__ . '/Заявки.txt';

// Максимальный размер файла заявок, чтобы не рос бесконечно (в байтах, ~5 МБ)
$maxFileSize = 5 * 1024 * 1024;

// ---------- Чтение входных данных ----------
$raw = file_get_contents('php://input');
$data = [];

// Пробуем JSON
if ($raw !== '' && $raw !== false) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}

// Если JSON не пришёл — пробуем обычный form-data / x-www-form-urlencoded
if (empty($data)) {
    $data = $_POST;
}

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
    // Убираем управляющие символы, обрезаем пробелы
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $s) ?? '';
    return trim($s);
}

/**
 * Читает текстовый файл заявок и возвращает массив заявок (для вычисления id).
 * Формат блока:
 *   === Заявка #N ===
 *   created_at: ...
 *   ...
 *   ---
 */
function readOrders(string $file): array {
    if (!is_file($file)) {
        return [];
    }
    $content = file_get_contents($file);
    if ($content === false || $content === '') {
        return [];
    }

    $orders = [];
    // Разбиваем по маркеру начала заявки
    $blocks = preg_split('/^=== Заявка #\d+ ===\s*$/mu', $content, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') {
            continue;
        }
        $order = [];
        foreach (preg_split('/\r\n|\r|\n/', $block) as $line) {
            $line = trim($line);
            if ($line === '' || $line === '---') {
                continue;
            }
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            $order[$key] = $val;
        }
        if (!empty($order['id'])) {
            $orders[] = [
                'id' => (int)$order['id'],
            ];
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
$product   = clean((string)($data['product']   ?? ''));
$price     = (int)($data['price'] ?? 0);

// Фамилия / Имя
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

// Телефон: допускаем +7/8 + 10 цифр, с пробелами/дефисами/скобками
$phoneClean = preg_replace('/[\s\-()]/', '', $phoneRaw) ?? '';
if (!preg_match('/^(\+7|8)?\d{10}$/', $phoneClean)) {
    $errors[] = 'Некорректный номер телефона.';
} else {
    // Приводим к виду +7XXXXXXXXXX
    if (strlen($phoneClean) === 11) {
        $phoneClean = '+' . substr($phoneClean, 1);
    } elseif (strlen($phoneClean) === 10) {
        $phoneClean = '+7' . $phoneClean;
    }
}

// Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Некорректный адрес электронной почты.';
} elseif (mb_strlen($email) > 254) {
    $errors[] = 'Email слишком длинный.';
}

// Согласие
if (!$consent) {
    $errors[] = 'Необходимо согласие на обработку персональных данных.';
}

// Товар и цена (не критично, но проверим)
if ($product === '') {
    $errors[] = 'Не указан товар.';
}
if ($price < 0 || $price > 10_000_000) {
    $price = 0;
}

if ($errors) {
    respond(false, implode(' ', $errors), 422);
}

// ---------- Простая защита от спама ----------
// Не больше 1 заявки с одного IP за 30 секунд
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
        // Архивная ротация: переименовываем старый файл
        @rename($storageFile, __DIR__ . '/Заявки_' . date('Y-m-d_H-i-s') . '.txt');
    } else {
        $existingOrders = readOrders($storageFile);
    }
}

// ---------- Новая заявка ----------
$nextId = 1;
if (!empty($existingOrders)) {
    $nextId = max(array_column($existingOrders, 'id')) + 1;
}

$createdAt  = date('Y-m-d H:i:s');
$userAgent  = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);

$block  = "=== Заявка #{$nextId} ===\r\n";
$block .= "id: {$nextId}\r\n";
$block .= "created_at: {$createdAt}\r\n";
$block .= "ip: {$ip}\r\n";
$block .= "user_agent: {$userAgent}\r\n";
$block .= "product: {$product}\r\n";
$block .= "price: {$price}\r\n";
$block .= "last_name: {$lastName}\r\n";
$block .= "first_name: {$firstName}\r\n";
$block .= "phone: {$phoneClean}\r\n";
$block .= "email: {$email}\r\n";
$block .= "consent: yes\r\n";
$block .= "status: new\r\n";
$block .= "---\r\n\r\n";

// ---------- Запись в файл ----------
// Дописываем блок в конец файла с эксклюзивной блокировкой
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

// ---------- Ответ ----------
respond(true, [
    'id'  => $nextId,
    'message' => 'Заявка принята.',
]);