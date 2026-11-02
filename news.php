<?php
/**
* news.php — API новостей
*/
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const NEWS_FILE     = __DIR__ . '/Новости.json';
const NEWS_UPLOADS  = __DIR__ . '/uploads/news';

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

function current_user(): ?array {
    if (empty($_SESSION['admin_authed'])) return null;
    return users_find((string)($_SESSION['admin_user'] ?? ''));
}

function news_all(): array {
    if (!is_file(NEWS_FILE)) return [];
    $raw = file_get_contents(NEWS_FILE);
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function news_save_all(array $items): bool {
    $tmp = NEWS_FILE . '.tmp';
    $json = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, NEWS_FILE);
}

function clean_str(string $s): string {
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    return trim($s);
}

$action = (string)($_REQUEST['action'] ?? '');

// ---------- Публичный список (для сайта) ----------
if ($action === 'list') {
    $all = news_all();
    $published = array_values(array_filter($all, fn($n) => ($n['status'] ?? '') === 'published'));
    usort($published, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    respond(true, ['items' => $published]);
}

// ---------- Публичный просмотр одной новости ----------
if ($action === 'view') {
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') respond(false, 'Не указан id', 422);
    $all = news_all();
    foreach ($all as $n) {
        if ((string)($n['id'] ?? '') === $id && ($n['status'] ?? '') === 'published') {
            respond(true, ['item' => $n]);
        }
    }
    respond(false, 'Новость не найдена', 404);
}

// ---------- Ниже — только авторизованные ----------
$user = current_user();
if (!$user) {
    respond(false, 'Требуется авторизация', 401);
}

// ---------- Загрузка картинки ----------
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!users_can_do($user, 'news.create') && !users_can_do($user, 'news.edit')) {
        respond(false, 'Недостаточно прав', 403);
    }
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $err = $_FILES['image']['error'] ?? 0;
        respond(false, "Ошибка загрузки файла (код $err)", 422);
    }
    $file = $_FILES['image'];
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        respond(false, 'Файл слишком большой (макс. 5 МБ)', 422);
    }
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        respond(false, 'Разрешены только JPG, PNG, WEBP, GIF', 422);
    }
    if (!is_dir(NEWS_UPLOADS)) {
        @mkdir(NEWS_UPLOADS, 0755, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) $ext = 'jpg';
    $newName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = NEWS_UPLOADS . '/' . $newName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        respond(false, 'Не удалось сохранить файл', 500);
    }
    respond(true, ['url' => 'uploads/news/' . $newName]);
}

if ($action === 'all') {
    if (!users_can_do($user, 'news.view')) respond(false, 'Недостаточно прав', 403);
    $all = news_all();
    usort($all, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    respond(true, ['items' => $all]);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;

    $id          = trim((string)($data['id'] ?? ''));
    $title       = clean_str((string)($data['title'] ?? ''));
    $subtitle    = clean_str((string)($data['subtitle'] ?? ''));
    $description = clean_str((string)($data['description'] ?? ''));
    $image       = clean_str((string)($data['image'] ?? ''));
    $status      = (string)($data['status'] ?? 'draft');

    if ($title === '' || mb_strlen($title) < 3) respond(false, 'Заголовок обязателен (минимум 3 символа)', 422);
    if (mb_strlen($title) > 200) respond(false, 'Заголовок слишком длинный', 422);
    if ($subtitle !== '' && mb_strlen($subtitle) > 300) respond(false, 'Подзаголовок слишком длинный', 422);
    if (!in_array($status, ['draft', 'published'], true)) $status = 'draft';
    if ($image !== '') {
        if (!preg_match('#^uploads/news/[a-zA-Z0-9_\-\.]+$#', $image) &&
            !filter_var($image, FILTER_VALIDATE_URL)) {
            respond(false, 'Некорректная ссылка на картинку', 422);
        }
    }

    $all = news_all();
    $isNew = ($id === '');

    if ($isNew) {
        if (!users_can_do($user, 'news.create')) respond(false, 'Недостаточно прав для создания', 403);
        $nextId = 1;
        if (!empty($all)) {
            $nextId = max(array_map(fn($n) => (int)($n['id'] ?? 0), $all)) + 1;
        }
        $item = [
            'id'          => (string)$nextId,
            'title'       => $title,
            'subtitle'    => $subtitle,
            'description' => $description,
            'image'       => $image,
            'status'      => $status,
            'author'      => $user['username'],
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        $all[] = $item;
    } else {
        if (!users_can_do($user, 'news.edit')) respond(false, 'Недостаточно прав для редактирования', 403);
        $found = false;
        foreach ($all as &$n) {
            if ((string)$n['id'] === $id) {
                $n['title']       = $title;
                $n['subtitle']    = $subtitle;
                $n['description'] = $description;
                $n['image']       = $image;
                $n['status']      = $status;
                $n['updated_at']  = date('Y-m-d H:i:s');
                $found = true;
                $item = $n;
                break;
            }
        }
        unset($n);
        if (!$found) respond(false, 'Новость не найдена', 404);
    }

    if (!news_save_all($all)) respond(false, 'Не удалось сохранить', 500);
    respond(true, ['item' => $item, 'is_new' => $isNew]);
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!users_can_do($user, 'news.delete')) respond(false, 'Недостаточно прав', 403);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;
    $id = trim((string)($data['id'] ?? ''));
    if ($id === '') respond(false, 'Не указан id', 422);

    $all = news_all();
    $newAll = [];
    $deleted = null;
    foreach ($all as $n) {
        if ((string)$n['id'] === $id) {
            $deleted = $n;
        } else {
            $newAll[] = $n;
        }
    }
    if (!$deleted) respond(false, 'Новость не найдена', 404);

    if (!news_save_all($newAll)) respond(false, 'Не удалось сохранить', 500);

    $img = $deleted['image'] ?? '';
    if ($img !== '' && strpos($img, 'uploads/news/') === 0) {
        $path = __DIR__ . '/' . $img;
        if (is_file($path)) @unlink($path);
    }
    respond(true);
}

if ($action === 'toggle_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!users_can_do($user, 'news.edit')) respond(false, 'Недостаточно прав', 403);
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;
    $id = trim((string)($data['id'] ?? ''));
    $status = (string)($data['status'] ?? '');
    if ($id === '' || !in_array($status, ['draft', 'published'], true)) {
        respond(false, 'Некорректные параметры', 422);
    }
    $all = news_all();
    $found = false;
    foreach ($all as &$n) {
        if ((string)$n['id'] === $id) {
            $n['status'] = $status;
            $n['updated_at'] = date('Y-m-d H:i:s');
            $found = true;
            break;
        }
    }
    unset($n);
    if (!$found) respond(false, 'Новость не найдена', 404);
    if (!news_save_all($all)) respond(false, 'Не удалось сохранить', 500);
    respond(true);
}

respond(false, 'Неизвестное действие', 400);