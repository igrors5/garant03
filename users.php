<?php
/**
* users.php — хранилище сотрудников, роли и права доступа.
*/
declare(strict_types=1);
const USERS_FILE = __DIR__ . '/Сотрудники.json';

const ROLE_LABELS = [
    'admin'    => 'Администратор',
    'operator' => 'Оператор',
    'viewer'   => 'Наблюдатель',
];

const VIEW_LABELS = [
    'dashboard' => 'Dashboard',
    'orders'    => 'Orders',
    'cart'      => 'Корзина',
    'staff'     => 'Сотрудники',
    'settings'  => 'Settings',
];

const ACTION_LABELS = [
    'orders.view'   => 'Просмотр заявок',
    'orders.accept' => 'Принимать заявки',
    'orders.reject' => 'Отклонять заявки',
    'cart.view'     => 'Просмотр корзин',
    'staff.manage'  => 'Управление сотрудниками',
];

const ROLE_PRESETS = [
    'admin'    => ['dashboard','orders','cart','staff','settings'],
    'operator' => ['dashboard','orders','cart'],
    'viewer'   => ['dashboard'],
];

const ROLE_ACTION_PRESETS = [
    'admin'    => ['orders.view','orders.accept','orders.reject','cart.view','staff.manage'],
    'operator' => ['orders.view','orders.accept','orders.reject','cart.view'],
    'viewer'   => ['orders.view'],
];

function users_all(): array {
    if (!is_file(USERS_FILE)) return [];
    $raw = file_get_contents(USERS_FILE);
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function users_save(array $users): bool {
    $tmp = USERS_FILE . '.tmp';
    $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, USERS_FILE);
}

function users_find(string $username): ?array {
    $all = users_all();
    return $all[$username] ?? null;
}

function users_upsert(array $data): array {
    $username = trim((string)($data['username'] ?? ''));
    $role     = (string)($data['role'] ?? 'viewer');
    $fullname = trim((string)($data['fullname'] ?? ''));

    if (!preg_match('/^[a-zA-Z0-9._-]{3,32}$/', $username)) {
        return ['ok' => false, 'error' => 'Логин: 3–32 символа, латиница/цифры/._-'];
    }
    if (!isset(ROLE_LABELS[$role])) {
        return ['ok' => false, 'error' => 'Неизвестная роль.'];
    }

    $all = users_all();
    $isNew = !isset($all[$username]);

    $password = (string)($data['password'] ?? '');
    if ($isNew && mb_strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Пароль минимум 6 символов.'];
    }
    if (!$isNew && $password !== '' && mb_strlen($password) < 6) {
        return ['ok' => false, 'error' => 'Новый пароль минимум 6 символов.'];
    }

    $views = array_values(array_intersect(
        array_keys(VIEW_LABELS),
        is_array($data['views'] ?? null) ? $data['views'] : []
    ));
    $actions = array_values(array_intersect(
        array_keys(ACTION_LABELS),
        is_array($data['actions'] ?? null) ? $data['actions'] : []
    ));

    if ($role === 'admin') {
        $views = array_keys(VIEW_LABELS);
        $actions = array_keys(ACTION_LABELS);
    }

    $record = $all[$username] ?? [
        'username' => $username,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    $record['role']     = $role;
    $record['fullname'] = $fullname;
    $record['views']    = $views;
    $record['actions']  = $actions;
    $record['updated_at'] = date('Y-m-d H:i:s');

    if ($password !== '') {
        $record['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    if ($isNew && empty($record['password_hash'])) {
        return ['ok' => false, 'error' => 'Пароль не задан.'];
    }

    $all[$username] = $record;
    if (!users_save($all)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл сотрудников.'];
    }
    return ['ok' => true, 'username' => $username, 'is_new' => $isNew];
}

function users_delete(string $username, string $currentUser): array {
    $all = users_all();
    if (!isset($all[$username])) {
        return ['ok' => false, 'error' => 'Пользователь не найден.'];
    }
    if ($username === $currentUser) {
        return ['ok' => false, 'error' => 'Нельзя удалить свою учётную запись.'];
    }
    if (($all[$username]['role'] ?? '') === 'admin') {
        $admins = array_filter($all, fn($u) => ($u['role'] ?? '') === 'admin');
        if (count($admins) <= 1) {
            return ['ok' => false, 'error' => 'Нельзя удалить последнего администратора.'];
        }
    }
    unset($all[$username]);
    if (!users_save($all)) {
        return ['ok' => false, 'error' => 'Не удалось сохранить файл.'];
    }
    return ['ok' => true];
}

function users_verify(string $username, string $password): ?array {
    $user = users_find($username);
    if (!$user || empty($user['password_hash'])) return null;
    if (!password_verify($password, $user['password_hash'])) return null;
    return $user;
}

function users_can_view(array $user, string $view): bool {
    if (($user['role'] ?? '') === 'admin') return true;
    return in_array($view, $user['views'] ?? [], true);
}

function users_can_do(array $user, string $action): bool {
    if (($user['role'] ?? '') === 'admin') return true;
    return in_array($action, $user['actions'] ?? [], true);
}