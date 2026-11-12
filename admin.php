<?php
/**
 * admin.php — фронт-контроллер панели.
 * Отвечает: сессия, POST-действия, роутинг view, рендер layout.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// logout
if (isset($_GET['logout'])) auth_logout();

$currentUser = auth_require();   // редирект на login.php, если нет сессии

// ---------- POST-действия ----------
$staffMsg = null;
$allowedPostActions = ['staff_save', 'staff_delete', 'orders_status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (in_array($action, $allowedPostActions, true)) {
        $staffMsg = handle_post_action($action, $currentUser);
    }
}

function handle_post_action(string $action, array $currentUser): array {
    switch ($action) {
        case 'staff_save':
            if (!users_can_do($currentUser, 'staff.manage')) {
                return ['ok' => false, 'error' => 'Недостаточно прав.'];
            }
            return users_upsert([
                'username' => $_POST['username'] ?? '',
                'password' => $_POST['password'] ?? '',
                'role'     => $_POST['role'] ?? 'viewer',
                'fullname' => $_POST['fullname'] ?? '',
                'views'    => $_POST['views'] ?? [],
                'actions'  => $_POST['actions'] ?? [],
            ]);

        case 'staff_delete':
            if (!users_can_do($currentUser, 'staff.manage')) {
                return ['ok' => false, 'error' => 'Недостаточно прав.'];
            }
            return users_delete(
                (string)($_POST['username'] ?? ''),
                $currentUser['username']
            );

        case 'orders_status':
            $newStatus = (string)($_POST['status'] ?? '');
            $orderId   = (string)($_POST['id'] ?? '');
            $allow =
                ($newStatus === 'done'      && users_can_do($currentUser, 'orders.accept')) ||
                ($newStatus === 'cancelled' && users_can_do($currentUser, 'orders.reject'));
            if (!$allow) {
                return ['ok' => false, 'error' => 'Недостаточно прав для этого действия.'];
            }
            return update_order_status(STORAGE_FILE, $orderId, $newStatus);
    }
    return ['ok' => false, 'error' => 'Неизвестное действие.'];
}

// ---------- Данные ----------
$orders = parseOrders(STORAGE_FILE);
$stats  = build_stats($orders);

// Фильтры (для orders view)
$q       = trim((string)($_GET['q'] ?? ''));
$statusF = trim((string)($_GET['status'] ?? ''));

$filteredOrders = array_values(array_filter($orders, function($o) use ($q, $statusF) {
    if ($statusF !== '' && ($o['status'] ?? 'new') !== $statusF) return false;
    if ($q !== '') {
        $hay = mb_strtolower(implode(' ', [
            $o['id'] ?? '', $o['last_name'] ?? '', $o['first_name'] ?? '',
            $o['phone'] ?? '', $o['email'] ?? '', $o['product'] ?? '',
        ]));
        if (mb_strpos($hay, mb_strtolower($q)) === false) return false;
    }
    return true;
}));

// ---------- Роутинг view ----------
$views = ['dashboard', 'orders', 'staff', 'settings'];
$view  = (string)($_GET['view'] ?? 'dashboard');
if (!in_array($view, $views, true) || !users_can_view($currentUser, $view)) {
    // первая доступная
    $view = 'dashboard';
    foreach ($views as $v) {
        if (users_can_view($currentUser, $v)) { $view = $v; break; }
    }
}

// ---------- Рендер ----------
$allowedViews = array_values(array_filter($views, fn($v) => users_can_view($currentUser, $v)));
$allUsers = users_all();
$canManageStaff = users_can_do($currentUser, 'staff.manage');

require __DIR__ . '/partials/layout_head.php';
require __DIR__ . '/partials/sidebar.php';
require __DIR__ . '/views/' . $view . '.php';
require __DIR__ . '/partials/layout_foot.php';