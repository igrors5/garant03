<?php
/**
* admin.php — панель заявок "Гарант"
*/
declare(strict_types=1);

$ADMIN_USER = 'admin';
$ADMIN_PASS = 'change_me_please';
$storageFile = __DIR__ . '/Заявки.txt';
require __DIR__ . '/users.php';

if (!is_file(USERS_FILE)) {
    users_save([
        $ADMIN_USER => [
            'username'      => $ADMIN_USER,
            'fullname'      => 'Администратор',
            'role'          => 'admin',
            'password_hash' => password_hash($ADMIN_PASS, PASSWORD_DEFAULT),
            'views'         => array_keys(VIEW_LABELS),
            'actions'       => array_keys(ACTION_LABELS),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ],
    ]);
}

$productImages = [
    'iPhone 18 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg',
    'iPhone 17 Pro Max' => 'images/iPhone%2017%20Pro%20Max.jpg',
    'iPhone 17 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro.jpg',
    'iPhone 17'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg',
    'iPhone 16 Pro Max' => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg',
    'iPhone 15 Pro Max' => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg',
    'iPhone 15 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro.jpg',
    'iPhone 14 Pro Max' => 'images/iPhone%2014%20Pro%20Max.jpg',
    'iPhone 14'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-14.jpg',
    'iPhone 12 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-12-pro.jpg',
    'iPhone 13'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-13.jpg',
    'iPhone 11 Pro Max' => 'images/iPhone%2011%20Pro%20Max.jpg',
    'iPhone 12'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-12.jpg',
    'iPhone 11'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-11.jpg',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: admin.php');
    exit;
}

$loginError = '';
$isAuthed = !empty($_SESSION['admin_authed']);
$currentUser = null;
if ($isAuthed) {
    $currentUser = users_find((string)($_SESSION['admin_user'] ?? ''));
    if (!$currentUser) {
        $_SESSION = [];
        session_destroy();
        header('Location: admin.php');
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $u = (string)($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    $_SESSION['login_attempts'] = array_values(array_filter(
        $_SESSION['login_attempts'] ?? [],
        fn($t) => $t > time() - 600
    ));
    if (count($_SESSION['login_attempts']) >= 5) {
        $loginError = 'Слишком много попыток. Попробуйте позже.';
    } else {
        $verified = users_verify($u, $p);
        if ($verified) {
            session_regenerate_id(true);
            $_SESSION['admin_authed'] = true;
            $_SESSION['admin_user'] = $verified['username'];
            $_SESSION['login_attempts'] = [];
            header('Location: admin.php');
            exit;
        }
        $_SESSION['login_attempts'][] = time();
        $loginError = 'Неверный логин или пароль.';
    }
}

$staffMsg = null;
if ($isAuthed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'staff_save') {
        if (!users_can_do($currentUser, 'staff.manage')) {
            $staffMsg = ['ok' => false, 'error' => 'Недостаточно прав.'];
        } else {
            $staffMsg = users_upsert([
                'username' => $_POST['username'] ?? '',
                'password' => $_POST['password'] ?? '',
                'role'     => $_POST['role'] ?? 'viewer',
                'fullname' => $_POST['fullname'] ?? '',
                'views'    => $_POST['views'] ?? [],
                'actions'  => $_POST['actions'] ?? [],
            ]);
        }
    } elseif ($action === 'staff_delete') {
        if (!users_can_do($currentUser, 'staff.manage')) {
            $staffMsg = ['ok' => false, 'error' => 'Недостаточно прав.'];
        } else {
            $staffMsg = users_delete(
                (string)($_POST['username'] ?? ''),
                $currentUser['username']
            );
        }
    } elseif ($action === 'orders_status') {
        $newStatus = (string)($_POST['status'] ?? '');
        $orderId   = (string)($_POST['id'] ?? '');
        $allow =
            ($newStatus === 'done'      && users_can_do($currentUser, 'orders.accept')) ||
            ($newStatus === 'cancelled' && users_can_do($currentUser, 'orders.reject'));
        if (!$allow) {
            $staffMsg = ['ok' => false, 'error' => 'Недостаточно прав для этого действия.'];
        } else {
            $staffMsg = update_order_status($storageFile, $orderId, $newStatus);
        }
    }
}

if (!$isAuthed) {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Гарант — Вход</title>
<style>
:root{--bg:#f5f5f7;--card:#fff;--text:#1d1d1f;--muted:#6e6e73;--line:#e5e5e7;
--accent:#2bba5e;--accent-hover:#219b4c;--accent-light:#e8f8ee;
--danger:#e5484d;--danger-light:#fdecec;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",Roboto,Helvetica,Arial,sans-serif;
background:var(--bg);color:var(--text);line-height:1.5;-webkit-font-smoothing:antialiased;
min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.login-card{background:var(--card);border:1px solid var(--line);border-radius:24px;
padding:40px 36px;width:100%;max-width:400px;box-shadow:0 24px 60px rgba(0,0,0,.08);}
.login-logo{font-size:22px;font-weight:600;letter-spacing:-.02em;margin-bottom:6px;}
.login-logo span{color:var(--muted)}
.login-sub{font-size:14px;color:var(--muted);margin-bottom:28px}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:14px;font-weight:500;margin-bottom:6px}
.form-group input{width:100%;border:1px solid var(--line);border-radius:12px;
padding:12px 14px;font-size:15px;font-family:inherit;color:var(--text);
background:#fbfbfd;transition:border-color .2s,box-shadow .2s;}
.form-group input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light)}
.error{background:var(--danger-light);color:#9b1c20;border:1px solid rgba(229,72,77,.25);
border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:16px;}
.btn-login{width:100%;background:var(--accent);color:#fff;border:none;border-radius:980px;
padding:14px;font-size:16px;font-weight:500;font-family:inherit;cursor:pointer;
transition:background .2s;margin-top:6px;}
.btn-login:hover{background:var(--accent-hover)}
.back-link{display:block;text-align:center;margin-top:18px;font-size:13px;color:var(--muted)}
.back-link:hover{color:var(--accent)}
</style>
</head>
<body>
<div class="login-card">
<div class="login-logo">Гарант<span>.</span></div>
<div class="login-sub">Вход в панель управления заявками</div>
<?php if ($loginError !== ''): ?>
<div class="error"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<form method="post" action="admin.php" autocomplete="off">
<input type="hidden" name="action" value="login">
<div class="form-group">
<label for="username">Логин</label>
<input type="text" id="username" name="username" required autofocus>
</div>
<div class="form-group">
<label for="password">Пароль</label>
<input type="password" id="password" name="password" required>
</div>
<button type="submit" class="btn-login">Войти</button>
</form>
<a href="index.html" class="back-link">← Вернуться на сайт</a>
</div>
</body>
</html>
    <?php
    exit;
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
    if (file_put_contents($file, $replaced, LOCK_EX) === false) return ['ok' => false, 'error' => 'Не удалось записать файл.'];
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

$orders = parseOrders($storageFile);

$allowedViews = [];
foreach (array_keys(VIEW_LABELS) as $v) {
    if (users_can_view($currentUser, $v)) $allowedViews[] = $v;
}
if (empty($allowedViews)) $allowedViews = ['dashboard'];
$defaultView = $allowedViews[0];

$totalOrders = count($orders);
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
$maxDaySum = $sums   ? max(1, max($sums))   : 1;
$maxDayCnt = $counts ? max(1, max($counts)) : 1;
$maxTop = $topProducts ? max(1, max(array_values($topProducts))) : 1;
$stock = $productCnt;
arsort($stock);
$maxStock = $stock ? max(1, max(array_values($stock))) : 1;

$q       = trim((string)($_GET['q'] ?? ''));
$statusF = trim((string)($_GET['status'] ?? ''));
$filtered = array_values(array_filter($orders, function($o) use ($q, $statusF) {
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

$statusLabels = [
    'new'        => 'Новая',
    'processing' => 'В обработке',
    'done'       => 'Принята',
    'cancelled'  => 'Отклонена',
];

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fmtPrice(int $price): string { return number_format($price, 0, '.', ' ') . ' ₽'; }
function productImage(string $product, array $map): string {
    foreach ($map as $needle => $path) {
        if (mb_stripos($product, $needle) !== false) return $path;
    }
    return 'https://placehold.co/400x400/f5f5f7/6e6e73?text=Нет+фото';
}

$allUsers = users_all();
$canManageStaff = users_can_do($currentUser, 'staff.manage');
$canViewNews    = users_can_do($currentUser, 'news.view');
$canCreateNews  = users_can_do($currentUser, 'news.create');
$canEditNews    = users_can_do($currentUser, 'news.edit');
$canDeleteNews  = users_can_do($currentUser, 'news.delete');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Гарант — Панель заявок</title>
<style>
:root{
--bg:#f5f5f7;--card:#fff;--text:#1d1d1f;--muted:#6e6e73;--line:#e5e5e7;
--accent:#2bba5e;--accent-hover:#219b4c;--accent-light:#e8f8ee;
--danger:#e5484d;--danger-light:#fdecec;--warn:#f5a623;--warn-light:#fef5e7;
--blue:#3a7bd5;--blue-light:#e7f0ff;
--sidebar-w:240px;
}
*{box-sizing:border-box;margin:0;padding:0}
body{
font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",Roboto,Helvetica,Arial,sans-serif;
background:var(--bg);color:var(--text);line-height:1.5;-webkit-font-smoothing:antialiased;
min-height:100vh;
}
a{color:inherit;text-decoration:none}
.app{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh}
.main{padding:24px 24px 40px;min-width:0}
.main-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.main-head h1{font-size:26px;font-weight:600;letter-spacing:-.02em}
.main-head p{font-size:14px;color:var(--muted);margin-top:4px}

.sidebar{
position:sticky;top:0;height:100vh;
background:rgba(255,255,255,.85);
backdrop-filter:saturate(180%) blur(20px);
-webkit-backdrop-filter:saturate(180%) blur(20px);
border-right:1px solid var(--line);
padding:24px 16px;
display:flex;flex-direction:column;gap:6px;
overflow-y:auto;
}
.sidebar-brand{font-size:17px;font-weight:600;letter-spacing:-.02em;padding:0 12px 20px}
.sidebar-brand span{color:var(--muted)}
.side-item{
display:flex;align-items:center;gap:12px;
padding:12px 14px;border-radius:12px;
font-size:14px;font-weight:500;color:var(--muted);
cursor:pointer;border:none;background:transparent;
font-family:inherit;text-align:left;width:100%;
transition:background .2s,color .2s;
}
.side-item:hover{background:rgba(0,0,0,.04);color:var(--text)}
.side-item.active{background:var(--accent-light);color:#166b38}
.side-item svg{flex-shrink:0}
.side-item.exit{color:var(--danger);margin-top:auto}
.side-item.exit:hover{background:var(--danger-light);color:#9b1c20}

.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:20px;display:flex;flex-direction:column;gap:6px}
.kpi-label{font-size:13px;color:var(--muted);font-weight:500}
.kpi-value{font-size:28px;font-weight:600;letter-spacing:-.02em}
.kpi-sub{font-size:12px;color:var(--muted)}
.kpi.accent .kpi-value{color:var(--accent)}
.kpi.danger .kpi-value{color:var(--danger)}
.kpi.blue .kpi-value{color:var(--blue)}
.panel{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:20px;margin-bottom:20px}
.panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap}
.panel-title{font-size:16px;font-weight:600;letter-spacing:-.01em}
.panel-legend{display:flex;gap:14px;font-size:12px;color:var(--muted)}
.legend-item{display:flex;align-items:center;gap:6px}
.legend-dot{width:10px;height:10px;border-radius:50%}
.two-col{display:grid;grid-template-columns:2fr 1fr;gap:16px}
@media(max-width:900px){.two-col{grid-template-columns:1fr}}

.bars{display:flex;align-items:flex-end;gap:8px;height:180px;padding-top:8px}
.bar-wrap{flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:6px;min-width:0}
.bar{width:100%;max-width:36px;border-radius:6px 6px 3px 3px;background:linear-gradient(180deg,var(--accent),#8ce1ab);transition:opacity .2s;position:relative}
.bar:hover{opacity:.85}
.bar-label{font-size:11px;color:var(--muted);white-space:nowrap;transform:rotate(-45deg);transform-origin:center;height:20px}
.bar-tip{position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#1d1d1f;color:#fff;font-size:11px;padding:4px 8px;border-radius:6px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .15s;margin-bottom:6px;z-index:2}
.bar:hover .bar-tip{opacity:1}

.status-list{display:flex;flex-direction:column;gap:12px}
.status-row{display:flex;flex-direction:column;gap:4px}
.status-row-top{display:flex;justify-content:space-between;font-size:13px;font-weight:500}
.status-row-top span:last-child{color:var(--muted);font-weight:400}
.status-progress{height:8px;border-radius:4px;background:#f0f0f2;overflow:hidden}
.status-progress > div{height:100%;border-radius:4px;transition:width .3s}
.p-new{background:var(--warn)}
.p-processing{background:var(--blue)}
.p-done{background:var(--accent)}
.p-cancelled{background:var(--danger)}

.top-products{display:flex;flex-direction:column;gap:14px}
.tp-row{display:flex;flex-direction:column;gap:4px}
.tp-name{font-size:13px;font-weight:500;display:flex;justify-content:space-between;gap:12px}
.tp-name span:last-child{color:var(--muted);font-weight:400;white-space:nowrap}
.tp-bar{height:6px;border-radius:3px;background:#f0f0f2;overflow:hidden}
.tp-bar > div{height:100%;border-radius:3px;background:var(--accent);transition:width .3s}

.stock-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px}
.stock-card{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:14px;padding:10px 12px;background:#fbfbfd}
.stock-img{width:48px;height:48px;flex-shrink:0;border-radius:10px;overflow:hidden;background:#fff;border:1px solid var(--line);display:flex;align-items:center;justify-content:center}
.stock-img img{max-width:100%;max-height:100%;object-fit:contain}
.stock-info{flex:1;min-width:0}
.stock-name{font-size:13px;font-weight:600;line-height:1.25;margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.stock-bar{height:6px;border-radius:3px;background:#e9e9eb;overflow:hidden;margin-bottom:4px}
.stock-bar > div{height:100%;border-radius:3px;background:var(--accent)}
.stock-qty{font-size:12px;color:var(--muted)}
.stock-qty strong{color:var(--text);font-weight:600}

.toolbar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:14px 16px;margin-bottom:20px}
.toolbar input[type=text], .toolbar select{border:1px solid var(--line);border-radius:10px;padding:10px 14px;font-size:14px;font-family:inherit;background:#fbfbfd;color:var(--text);min-width:180px}
.toolbar input[type=text]:focus,.toolbar select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light)}
.toolbar .spacer{flex:1}
.toolbar .count{font-size:14px;color:var(--muted)}
.orders-table{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden}
.orders-head{display:grid;grid-template-columns:56px 120px 1.4fr 140px 1.2fr 110px 130px 40px;gap:12px;align-items:center;padding:12px 16px;background:#fbfbfd;border-bottom:1px solid var(--line);font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.order-row{display:grid;grid-template-columns:56px 120px 1.4fr 140px 1.2fr 110px 130px 40px;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid var(--line);font-size:14px;cursor:pointer;transition:background .15s}
.order-row:last-child{border-bottom:none}
.order-row:hover{background:#f9f9fb}
.cell-id{font-weight:600;color:var(--muted);font-size:13px}
.cell-date{font-size:13px;color:var(--muted);white-space:nowrap}
.cell-name{font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cell-phone{color:var(--muted);white-space:nowrap;font-size:13px}
.cell-product{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px}
.cell-price{font-weight:600;white-space:nowrap;letter-spacing:-.01em}
.cell-actions{display:flex;justify-content:flex-end}
.row-more{width:32px;height:32px;border-radius:50%;border:1px solid var(--line);background:#fff;display:flex;align-items:center;justify-content:center;color:var(--muted);cursor:pointer;transition:background .15s,color .15s;font-size:18px;line-height:1}
.order-row:hover .row-more{background:var(--accent-light);color:#166b38;border-color:transparent}
.badge{font-size:12px;padding:3px 10px;border-radius:8px;background:#f5f5f7;color:var(--muted);font-weight:500;white-space:nowrap;display:inline-block}
.badge.status-new{background:var(--warn-light);color:#9a6a00}
.badge.status-processing{background:var(--blue-light);color:#1e4f9a}
.badge.status-done{background:var(--accent-light);color:#166b38}
.badge.status-cancelled{background:var(--danger-light);color:#9b1c20}
.badge.status-draft{background:#f0f0f2;color:var(--muted)}
.badge.status-published{background:var(--accent-light);color:#166b38}
.empty{text-align:center;padding:60px 20px;color:var(--muted);background:var(--card);border:1px dashed var(--line);border-radius:16px}

.settings-list{display:flex;flex-direction:column;gap:0}
.settings-row{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:16px 0;border-bottom:1px solid var(--line);flex-wrap:wrap}
.settings-row:last-child{border-bottom:none}
.settings-row .label{font-size:14px;font-weight:500}
.settings-row .value{font-size:14px;color:var(--muted);word-break:break-all}
.settings-row .value.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px}

.staff-alert,.news-alert{padding:14px 16px;border-radius:12px;margin-bottom:16px;font-size:14px;display:flex;align-items:center;gap:10px}
.staff-alert.ok,.news-alert.ok{background:var(--accent-light);color:#166b38;border:1px solid rgba(43,186,94,.2)}
.staff-alert.err,.news-alert.err{background:var(--danger-light);color:#9b1c20;border:1px solid rgba(229,72,77,.25)}

.staff-form,.news-form{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:20px;margin-bottom:20px}
.staff-form-title,.news-form-title{font-size:16px;font-weight:600;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.staff-form-title .mode-badge,.news-form-title .mode-badge{font-size:11px;font-weight:500;padding:3px 10px;border-radius:8px;background:var(--accent-light);color:#166b38}
.staff-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:700px){.staff-form-grid{grid-template-columns:1fr}}
.staff-field,.news-field{display:flex;flex-direction:column;gap:6px}
.staff-field label,.news-field label{font-size:13px;font-weight:500;color:var(--muted)}
.staff-field input,.staff-field select,.news-field input,.news-field select,.news-field textarea{
border:1px solid var(--line);border-radius:10px;padding:10px 12px;
font-size:14px;font-family:inherit;background:#fbfbfd;color:var(--text);
transition:border-color .2s,box-shadow .2s;
}
.news-field textarea{min-height:120px;resize:vertical;line-height:1.5}
.staff-field input:focus,.staff-field select:focus,
.news-field input:focus,.news-field select:focus,.news-field textarea:focus{
outline:none;border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);
}
.staff-field .hint,.news-field .hint{font-size:11px;color:var(--muted);margin-top:2px}
.staff-perms{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:6px}
@media(max-width:700px){.staff-perms{grid-template-columns:1fr}}
.perm-block{background:#fbfbfd;border:1px solid var(--line);border-radius:12px;padding:14px}
.perm-block-title{font-size:13px;font-weight:600;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.perm-block-title .toggle-all{font-size:11px;color:var(--accent);cursor:pointer;font-weight:500}
.perm-block-title .toggle-all:hover{text-decoration:underline}
.perm-item{display:flex;align-items:center;gap:8px;padding:6px 0;font-size:13px}
.perm-item input[type=checkbox]{width:16px;height:16px;accent-color:var(--accent);cursor:pointer}
.perm-item label{cursor:pointer;flex:1}
.staff-form-actions,.news-form-actions{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}

.btn{border:none;border-radius:980px;padding:10px 18px;font-size:14px;font-weight:500;font-family:inherit;cursor:pointer;transition:background .2s,opacity .2s}
.btn-accept{background:var(--accent);color:#fff}
.btn-accept:hover{background:var(--accent-hover)}
.btn-reject{background:#fff;color:var(--danger);border:1px solid rgba(229,72,77,.4)}
.btn-reject:hover{background:var(--danger-light)}
.btn-ghost{background:#f0f0f2;color:var(--text)}
.btn-ghost:hover{background:#e5e5e7}
.btn-warn{background:#fff;color:#9a6a00;border:1px solid rgba(245,166,35,.4)}
.btn-warn:hover{background:var(--warn-light)}
.btn[disabled]{opacity:.5;cursor:not-allowed}

.staff-table,.news-table{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden}
.staff-table-head,.staff-table-row{display:grid;grid-template-columns:1.2fr 1.4fr 1fr 1.3fr 1.3fr 140px 110px;gap:12px;align-items:center;padding:12px 16px}
.staff-table-head{background:#fbfbfd;border-bottom:1px solid var(--line);font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.staff-table-row{border-bottom:1px solid var(--line);font-size:13px}
.staff-table-row:last-child{border-bottom:none}
.staff-table-row:hover{background:#f9f9fb}
.staff-cell{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.staff-cell.login{font-weight:600;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px}
.staff-cell .mini-badges{display:flex;flex-wrap:wrap;gap:4px}
.mini-badge{font-size:10px;padding:2px 7px;border-radius:6px;background:#f0f0f2;color:var(--muted);font-weight:500}
.mini-badge.role-admin{background:var(--accent-light);color:#166b38}
.mini-badge.role-editor{background:#f3e8ff;color:#6b21a8}
.mini-badge.role-operator{background:var(--blue-light);color:#1e4f9a}
.mini-badge.role-viewer{background:#f0f0f2;color:var(--muted)}
.staff-actions{display:flex;gap:6px}
.icon-btn{width:30px;height:30px;border-radius:8px;border:1px solid var(--line);background:#fff;display:inline-flex;align-items:center;justify-content:center;color:var(--muted);cursor:pointer;transition:all .15s;font-size:14px}
.icon-btn:hover{background:var(--accent-light);color:#166b38;border-color:transparent}
.icon-btn.danger:hover{background:var(--danger-light);color:#9b1c20}
.staff-you{font-size:10px;color:var(--accent);margin-left:4px;font-weight:500}

/* ---------- News ---------- */
.news-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.news-card{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s}
.news-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.06);transform:translateY(-2px)}
.news-card-img{aspect-ratio:16/10;background:#f0f0f2;overflow:hidden;display:flex;align-items:center;justify-content:center}
.news-card-img img{width:100%;height:100%;object-fit:cover}
.news-card-img .placeholder{color:var(--muted);font-size:13px}
.news-card-body{padding:16px;display:flex;flex-direction:column;gap:8px;flex:1}
.news-card-title{font-size:16px;font-weight:600;letter-spacing:-.01em;line-height:1.3}
.news-card-sub{font-size:13px;color:var(--muted);line-height:1.4}
.news-card-meta{display:flex;justify-content:space-between;align-items:center;font-size:11px;color:var(--muted);margin-top:auto;padding-top:8px}
.news-card-actions{display:flex;gap:6px;padding:0 16px 16px;flex-wrap:wrap}
.news-card-actions .btn{padding:6px 14px;font-size:12px}

/* Image uploader */
.img-uploader{display:flex;flex-direction:column;gap:10px}
.img-source-tabs{display:flex;gap:6px}
.img-source-tab{padding:6px 14px;border-radius:8px;border:1px solid var(--line);background:#fff;font-size:12px;font-weight:500;cursor:pointer;color:var(--muted);font-family:inherit;transition:all .15s}
.img-source-tab.active{background:var(--accent-light);color:#166b38;border-color:transparent}
.img-preview{width:100%;max-width:240px;aspect-ratio:16/10;border-radius:12px;overflow:hidden;background:#f0f0f2;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:12px}
.img-preview img{width:100%;height:100%;object-fit:cover}
.img-preview.empty{border-style:dashed}
.img-upload-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.img-upload-row input[type=file]{font-size:12px}

.view{display:none}
.view.active{display:block;animation:fade .25s ease}
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

.modal-overlay{position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.4);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;padding:20px;opacity:0;transition:opacity .2s ease}
.modal-overlay.open{display:flex;opacity:1}
.modal{background:#fff;border-radius:24px;max-width:640px;width:100%;padding:28px;position:relative;box-shadow:0 24px 80px rgba(0,0,0,.18);max-height:calc(100vh - 40px);overflow-y:auto;transform:translateY(12px) scale(.98);transition:transform .2s cubic-bezier(.22,.61,.36,1)}
.modal-overlay.open .modal{transform:none}
.modal-close{position:absolute;top:14px;right:14px;width:34px;height:34px;border:none;background:rgba(0,0,0,.04);border-radius:50%;font-size:20px;color:var(--muted);cursor:pointer;line-height:1;display:flex;align-items:center;justify-content:center;transition:background .2s,color .2s}
.modal-close:hover{background:var(--line);color:var(--text)}
.modal-title{font-size:20px;font-weight:600;letter-spacing:-.02em;margin-bottom:4px;padding-right:32px}
.modal-sub{font-size:13px;color:var(--muted);margin-bottom:20px}
.detail-grid{display:grid;grid-template-columns:180px 1fr;gap:20px;align-items:flex-start}
@media(max-width:600px){.detail-grid{grid-template-columns:1fr}}
.detail-image{width:100%;aspect-ratio:1/1;border-radius:16px;overflow:hidden;background:#fbfbfd;border:1px solid var(--line);display:flex;align-items:center;justify-content:center;padding:12px}
.detail-image img{max-width:100%;max-height:100%;object-fit:contain}
.detail-rows{display:flex;flex-direction:column;gap:10px;font-size:14px}
.detail-row{display:grid;grid-template-columns:120px 1fr;gap:10px}
.detail-row .k{color:var(--muted);font-size:13px}
.detail-row .v{color:var(--text);word-break:break-word}
.detail-row .v a{color:var(--text);border-bottom:1px dashed var(--line)}
.detail-row .v a:hover{color:var(--accent);border-color:var(--accent)}
.detail-row .v.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;color:var(--muted)}
.modal-actions{display:flex;gap:8px;margin-top:20px;padding-top:16px;border-top:1px solid var(--line)}

.spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite;vertical-align:middle;margin-right:6px}
@keyframes spin{to{transform:rotate(360deg)}}

@media(max-width:860px){
.app{grid-template-columns:1fr;grid-template-rows:1fr auto}
.sidebar{position:fixed;left:0;right:0;bottom:0;top:auto;height:auto;flex-direction:row;border-right:none;border-top:1px solid var(--line);padding:8px;z-index:50;gap:4px}
.sidebar-brand{display:none}
.side-item{flex-direction:column;gap:4px;padding:8px;font-size:11px;text-align:center}
.side-item.exit{margin-top:0}
.main{padding:20px 16px 100px}
.orders-head{display:none}
.order-row{grid-template-columns:1fr auto;grid-template-areas:"name more" "product price" "meta status";gap:6px 12px;padding:14px 16px}
.cell-id{grid-area:meta;font-size:12px}
.cell-date{display:none}
.cell-name{grid-area:name;font-size:15px}
.cell-phone{display:none}
.cell-product{grid-area:product;font-size:13px}
.cell-price{grid-area:price;text-align:right}
.cell-status{grid-area:status;text-align:right}
.cell-actions{grid-area:more}
.staff-table-head{display:none}
.staff-table-row{grid-template-columns:1fr auto;grid-template-areas:"login actions" "name name" "role role" "views views" "acts acts" "upd upd";gap:4px 8px;padding:14px 16px}
.staff-cell.login{grid-area:login}
.staff-cell.name{grid-area:name}
.staff-cell.role{grid-area:role}
.staff-cell.views{grid-area:views}
.staff-cell.actions-col{grid-area:acts}
.staff-cell.upd{grid-area:upd}
.staff-actions{grid-area:actions}
}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar">
<div class="sidebar-brand">Гарант<span>.</span></div>
<?php if (users_can_view($currentUser, 'dashboard')): ?>
<button class="side-item active" data-view="dashboard">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
Панель
</button>
<?php endif; ?>
<?php if (users_can_view($currentUser, 'orders')): ?>
<button class="side-item" data-view="orders">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
Заявки
</button>
<?php endif; ?>
<?php if (users_can_view($currentUser, 'news')): ?>
<button class="side-item" data-view="news">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6z"/></svg>
Новости
</button>
<?php endif; ?>
<?php if (users_can_view($currentUser, 'staff')): ?>
<button class="side-item" data-view="staff">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
Сотрудники
</button>
<?php endif; ?>
<?php if (users_can_view($currentUser, 'settings')): ?>
<button class="side-item" data-view="settings">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.2.47.66.79 1.18.85H21a2 2 0 1 1 0 4h-.09c-.52.06-.98.38-1.18.85z"/></svg>
Настройки
</button>
<?php endif; ?>
<a href="admin.php?logout=1" class="side-item exit">
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
Выход
</a>
</aside>

<main class="main">

<!-- ===== DASHBOARD ===== -->
<?php if (users_can_view($currentUser, 'dashboard')): ?>
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
<?php foreach ($statusLabels as $key => $label):
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
<div class="stock-img"><img src="<?= e(productImage($name, $productImages)) ?>" alt="<?= e($name) ?>" loading="lazy"></div>
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
<?php endif; ?>

<!-- ===== ORDERS ===== -->
<?php if (users_can_view($currentUser, 'orders')): ?>
<section class="view" id="view-orders">
<div class="main-head"><div><h1>Заявки</h1><p>Все заявки с сайта</p></div></div>
<form class="toolbar" method="get" action="admin.php">
<input type="hidden" name="view" value="orders">
<input type="text" name="q" placeholder="Поиск: имя, телефон, email, товар…" value="<?= e($q) ?>">
<select name="status">
<option value="">Все статусы</option>
<?php foreach ($statusLabels as $key => $label): ?>
<option value="<?= e($key) ?>" <?= $statusF === $key ? 'selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
</select>
<button type="submit" class="btn btn-accept" style="flex:0 0 auto;padding:10px 20px">Найти</button>
<?php if ($q !== '' || $statusF !== ''): ?>
<a href="admin.php?view=orders" class="btn btn-reject" style="flex:0 0 auto;padding:10px 20px;text-align:center">Сбросить</a>
<?php endif; ?>
<div class="spacer"></div>
<div class="count">Показано: <?= count($filtered) ?> из <?= count($orders) ?></div>
</form>
<?php if (empty($filtered)): ?>
<div class="empty"><?= empty($orders) ? 'Заявок пока нет. Файл «Заявки.txt» пуст или не найден.' : 'По вашему запросу ничего не найдено.' ?></div>
<?php else: ?>
<div class="orders-table">
<div class="orders-head"><div>#</div><div>Дата</div><div>Клиент</div><div>Телефон</div><div>Товар</div><div>Цена</div><div>Статус</div><div></div></div>
<?php foreach ($filtered as $o):
$status = $o['status'] ?? 'new';
if (!isset($statusLabels[$status])) $status = 'new';
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
<div class="cell-status"><span class="badge status-<?= e($status) ?>" data-role="status"><?= e($statusLabels[$status]) ?></span></div>
<div class="cell-actions"><button type="button" class="row-more" title="Подробнее" aria-label="Подробнее">⋯</button></div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
<?php endif; ?>

<!-- ===== NEWS ===== -->
<?php if (users_can_view($currentUser, 'news')): ?>
<section class="view" id="view-news">
<div class="main-head">
<div><h1>Новости</h1><p>Управление публикациями на сайте</p></div>
<div style="font-size:13px;color:var(--muted)" id="newsCounter">Всего: —</div>
</div>

<div id="newsAlert"></div>

<?php if ($canCreateNews || $canEditNews): ?>
<div class="news-form" id="newsForm">
<div class="news-form-title">
<span id="newsFormTitle">Новая публикация</span>
<span class="mode-badge" id="newsModeBadge">создание</span>
</div>
<form id="newsFormEl" autocomplete="off">
<input type="hidden" name="id" id="nf-id" value="">

<div class="staff-form-grid">
<div class="news-field">
<label for="nf-title">Заголовок *</label>
<input type="text" id="nf-title" name="title" required maxlength="200" placeholder="Например: Новая поставка iPhone 17">
</div>
<div class="news-field">
<label for="nf-subtitle">Подзаголовок</label>
<input type="text" id="nf-subtitle" name="subtitle" maxlength="300" placeholder="Краткое описание для превью">
</div>
</div>

<div class="news-field" style="margin-top:14px">
<label for="nf-description">Описание</label>
<textarea id="nf-description" name="description" placeholder="Полный текст новости..."></textarea>
</div>

<div class="news-field" style="margin-top:14px">
<label>Картинка</label>
<div class="img-uploader">
<div class="img-source-tabs">
<button type="button" class="img-source-tab active" data-src="upload">Загрузить файл</button>
<button type="button" class="img-source-tab" data-src="url">Указать URL</button>
</div>
<div class="img-upload-row" id="imgUploadRow">
<input type="file" id="nf-file" accept="image/jpeg,image/png,image/webp,image/gif">
<div class="hint" style="margin:0">JPG, PNG, WEBP, GIF · до 5 МБ</div>
</div>
<div class="img-upload-row" id="imgUrlRow" style="display:none">
<input type="url" id="nf-url" placeholder="https://..." style="flex:1;min-width:200px">
</div>
<input type="hidden" id="nf-image" name="image" value="">
<div class="img-preview empty" id="imgPreview">
<span>Нет картинки</span>
</div>
</div>
</div>

<div class="staff-form-grid" style="margin-top:14px">
<div class="news-field">
<label for="nf-status">Статус</label>
<select id="nf-status" name="status">
<option value="draft">Черновик (не опубликовано)</option>
<option value="published">Опубликовано</option>
</select>
</div>
</div>

<div class="news-form-actions">
<button type="submit" class="btn btn-accept" id="newsSubmitBtn">Создать</button>
<button type="button" class="btn btn-ghost" id="newsCancelBtn">Отмена</button>
</div>
</form>
</div>
<?php endif; ?>

<div id="newsList" class="news-grid"></div>
</section>
<?php endif; ?>

<!-- ===== STAFF ===== -->
<?php if (users_can_view($currentUser, 'staff')): ?>
<section class="view" id="view-staff">
<div class="main-head">
<div><h1>Сотрудники</h1><p>Управление учётными записями, ролями и правами доступа</p></div>
<div style="font-size:13px;color:var(--muted)">Всего: <?= count($allUsers) ?></div>
</div>

<?php if ($staffMsg !== null): ?>
<div class="staff-alert <?= !empty($staffMsg['ok']) ? 'ok' : 'err' ?>">
<?php if (!empty($staffMsg['ok'])): ?>
    ✓ <?= e($staffMsg['is_new'] ?? false ? 'Сотрудник создан.' : 'Изменения сохранены.') ?>
<?php else: ?>
    ✕ <?= e($staffMsg['error'] ?? 'Ошибка') ?>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canManageStaff): ?>
<div class="staff-form" id="staffForm">
<div class="staff-form-title">
<span id="staffFormTitle">Новый сотрудник</span>
<span class="mode-badge" id="staffModeBadge">создание</span>
</div>
<form method="post" action="admin.php" autocomplete="off">
<input type="hidden" name="action" value="staff_save">
<input type="hidden" name="edit_mode" id="editMode" value="0">
<div class="staff-form-grid">
<div class="staff-field">
<label for="sf-username">Логин *</label>
<input type="text" id="sf-username" name="username" required pattern="[a-zA-Z0-9._\-]{3,32}" placeholder="например, editor1">
<div class="hint">3–32 символа, латиница, цифры, . _ -</div>
</div>
<div class="staff-field">
<label for="sf-fullname">ФИО</label>
<input type="text" id="sf-fullname" name="fullname" placeholder="Иванов Иван Иванович">
</div>
<div class="staff-field">
<label for="sf-password">Пароль <span id="pwd-hint">*</span></label>
<input type="text" id="sf-password" name="password" autocomplete="new-password">
<div class="hint" id="pwd-sub">Минимум 6 символов. Обязательно при создании.</div>
</div>
<div class="staff-field">
<label for="sf-role">Роль *</label>
<select id="sf-role" name="role" required>
<?php foreach (ROLE_LABELS as $k => $l): ?>
<option value="<?= e($k) ?>"><?= e($l) ?></option>
<?php endforeach; ?>
</select>
<div class="hint">При выборе роли подставляются рекомендуемые права.</div>
</div>
</div>
<div class="staff-perms">
<div class="perm-block">
<div class="perm-block-title">
<span>Доступ к разделам</span>
<span class="toggle-all" data-target="views">выбрать все</span>
</div>
<?php foreach (VIEW_LABELS as $k => $l): ?>
<div class="perm-item">
<input type="checkbox" name="views[]" value="<?= e($k) ?>" id="v-<?= e($k) ?>" class="perm-views">
<label for="v-<?= e($k) ?>"><?= e($l) ?></label>
</div>
<?php endforeach; ?>
</div>
<div class="perm-block">
<div class="perm-block-title">
<span>Права на действия</span>
<span class="toggle-all" data-target="actions">выбрать все</span>
</div>
<?php foreach (ACTION_LABELS as $k => $l): ?>
<div class="perm-item">
<input type="checkbox" name="actions[]" value="<?= e($k) ?>" id="a-<?= e($k) ?>" class="perm-actions">
<label for="a-<?= e($k) ?>"><?= e($l) ?></label>
</div>
<?php endforeach; ?>
</div>
</div>
<div class="staff-form-actions">
<button type="submit" class="btn btn-accept" id="staffSubmitBtn">Создать сотрудника</button>
<button type="button" class="btn btn-ghost" id="staffCancelBtn">Отмена</button>
</div>
</form>
</div>
<?php endif; ?>

<div class="staff-table">
<div class="staff-table-head">
<div>Логин</div><div>ФИО</div><div>Роль</div><div>Разделы</div><div>Действия</div><div>Обновлён</div><div></div>
</div>
<?php if (empty($allUsers)): ?>
<div style="padding:40px;text-align:center;color:var(--muted)">Сотрудников пока нет.</div>
<?php else: ?>
<?php foreach ($allUsers as $u):
$role = $u['role'] ?? 'viewer';
$roleBadge = 'role-' . e($role);
$views = $u['views'] ?? [];
$actions = $u['actions'] ?? [];
$updated = !empty($u['updated_at']) ? date('d.m.Y H:i', strtotime($u['updated_at'])) : '—';
$isYou = ($u['username'] === $currentUser['username']);
?>
<div class="staff-table-row"
     data-username="<?= e($u['username']) ?>"
     data-fullname="<?= e($u['fullname'] ?? '') ?>"
     data-role="<?= e($role) ?>"
     data-views='<?= e(json_encode($views, JSON_UNESCAPED_UNICODE)) ?>'
     data-actions='<?= e(json_encode($actions, JSON_UNESCAPED_UNICODE)) ?>'>
<div class="staff-cell login"><?= e($u['username']) ?><?php if ($isYou): ?><span class="staff-you">(вы)</span><?php endif; ?></div>
<div class="staff-cell name"><?= e($u['fullname'] ?? '—') ?></div>
<div class="staff-cell role"><span class="mini-badge <?= $roleBadge ?>"><?= e(ROLE_LABELS[$role] ?? $role) ?></span></div>
<div class="staff-cell views"><div class="mini-badges"><?php foreach ($views as $v): ?><span class="mini-badge"><?= e(VIEW_LABELS[$v] ?? $v) ?></span><?php endforeach; ?><?php if (empty($views)): ?><span class="mini-badge" style="color:#bbb">нет</span><?php endif; ?></div></div>
<div class="staff-cell actions-col"><div class="mini-badges"><?php foreach ($actions as $a): ?><span class="mini-badge"><?= e(ACTION_LABELS[$a] ?? $a) ?></span><?php endforeach; ?><?php if (empty($actions)): ?><span class="mini-badge" style="color:#bbb">нет</span><?php endif; ?></div></div>
<div class="staff-cell upd"><?= e($updated) ?></div>
<div class="staff-actions">
<?php if ($canManageStaff): ?>
<button type="button" class="icon-btn staff-edit" title="Редактировать">✎</button>
<?php if ($u['username'] !== $currentUser['username']): ?>
<form method="post" action="admin.php" style="display:inline" onsubmit="return confirm('Удалить сотрудника «<?= e($u['username']) ?>»?');">
<input type="hidden" name="action" value="staff_delete">
<input type="hidden" name="username" value="<?= e($u['username']) ?>">
<button type="submit" class="icon-btn danger" title="Удалить">🗑</button>
</form>
<?php endif; ?>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section>
<?php endif; ?>

<!-- ===== SETTINGS ===== -->
<?php if (users_can_view($currentUser, 'settings')): ?>
<section class="view" id="view-settings">
<div class="main-head"><div><h1>Настройки</h1><p>Информация о хранилище и обслуживании</p></div></div>
<div class="panel">
<div class="panel-head"><div class="panel-title">Хранилище заявок</div></div>
<div class="settings-list">
<div class="settings-row"><div class="label">Файл</div><div class="value mono"><?= e(basename($storageFile)) ?></div></div>
<div class="settings-row"><div class="label">Полный путь</div><div class="value mono"><?= e($storageFile) ?></div></div>
<div class="settings-row"><div class="label">Существует</div><div class="value"><?= is_file($storageFile) ? 'Да' : 'Нет' ?></div></div>
<div class="settings-row"><div class="label">Размер</div><div class="value"><?= is_file($storageFile) ? number_format(filesize($storageFile), 0, '.', ' ') . ' байт' : '—' ?></div></div>
<div class="settings-row"><div class="label">Заявок в файле</div><div class="value"><?= $totalOrders ?></div></div>
<div class="settings-row"><div class="label">Экспорт</div><div class="value"><?php if (is_file($storageFile)): ?><a href="<?= e(basename($storageFile)) ?>" download class="btn btn-accept" style="display:inline-block;padding:8px 18px;flex:none">Скачать .txt</a><?php else: ?>—<?php endif; ?></div></div>
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
<?php endif; ?>

</main>
</div>

<!-- ===== Модалка заявки ===== -->
<div class="modal-overlay" id="orderModal" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
<div class="modal">
<button class="modal-close" id="modalClose" aria-label="Закрыть">×</button>
<div class="modal-title" id="orderModalTitle">Заявка</div>
<div class="modal-sub" id="modalSub"></div>
<div class="detail-grid">
<div class="detail-image"><img id="modalImage" src="" alt="Фото товара"></div>
<div class="detail-rows">
<div class="detail-row"><div class="k">Клиент</div><div class="v" id="dName">—</div></div>
<div class="detail-row"><div class="k">Телефон</div><div class="v" id="dPhone">—</div></div>
<div class="detail-row"><div class="k">Email</div><div class="v" id="dEmail">—</div></div>
<div class="detail-row"><div class="k">Товар</div><div class="v" id="dProduct">—</div></div>
<div class="detail-row"><div class="k">Цена</div><div class="v" id="dPrice">—</div></div>
<div class="detail-row"><div class="k">Создана</div><div class="v" id="dDate">—</div></div>
<div class="detail-row"><div class="k">IP</div><div class="v mono" id="dIp">—</div></div>
<div class="detail-row"><div class="k">User-Agent</div><div class="v mono" id="dUa">—</div></div>
<div class="detail-row"><div class="k">Статус</div><div class="v" id="dStatus">—</div></div>
</div>
</div>
<div class="modal-actions">
<?php if (users_can_do($currentUser, 'orders.accept')): ?>
<button type="button" class="btn btn-accept" id="modalAccept">Принять</button>
<?php endif; ?>
<?php if (users_can_do($currentUser, 'orders.reject')): ?>
<button type="button" class="btn btn-reject" id="modalReject">Отклонить</button>
<?php endif; ?>
</div>
</div>
</div>

<script>
const ORDERS = <?= json_encode(array_map(function($o) use ($productImages) {
    return [
        'id' => (string)($o['id'] ?? ''),
        'last_name' => (string)($o['last_name'] ?? ''),
        'first_name' => (string)($o['first_name'] ?? ''),
        'phone' => (string)($o['phone'] ?? ''),
        'email' => (string)($o['email'] ?? ''),
        'product' => (string)($o['product'] ?? ''),
        'price' => (int)($o['price'] ?? 0),
        'created_at' => (string)($o['created_at'] ?? ''),
        'ip' => (string)($o['ip'] ?? ''),
        'user_agent' => (string)($o['user_agent'] ?? ''),
        'status' => (string)($o['status'] ?? 'new'),
        'image' => productImage((string)($o['product'] ?? ''), $productImages),
    ];
}, $filtered), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

const STATUS_LABELS = { new:'Новая', processing:'В обработке', done:'Принята', cancelled:'Отклонена' };
const ROLE_PRESETS = <?= json_encode(ROLE_PRESETS, JSON_UNESCAPED_UNICODE) ?>;
const ROLE_ACTION_PRESETS = <?= json_encode(ROLE_ACTION_PRESETS, JSON_UNESCAPED_UNICODE) ?>;

const NEWS_PERMS = {
    view:   <?= $canViewNews   ? 'true' : 'false' ?>,
    create: <?= $canCreateNews ? 'true' : 'false' ?>,
    edit:   <?= $canEditNews   ? 'true' : 'false' ?>,
    del:    <?= $canDeleteNews ? 'true' : 'false' ?>,
};

const modal       = document.getElementById('orderModal');
const modalClose  = document.getElementById('modalClose');
const modalTitle  = document.getElementById('orderModalTitle');
const modalSub    = document.getElementById('modalSub');
const modalImage  = document.getElementById('modalImage');
const modalAccept = document.getElementById('modalAccept');
const modalReject = document.getElementById('modalReject');
let currentId = null;
const fmtPrice = n => n.toLocaleString('ru-RU') + ' ₽';

function openOrder(id){
    const o = ORDERS.find(x => x.id === String(id));
    if (!o) return;
    currentId = o.id;
    const fullName = `${o.last_name} ${o.first_name}`.trim() || 'Без имени';
    modalTitle.textContent = `Заявка #${o.id}`;
    modalSub.textContent = o.created_at || '';
    modalImage.src = o.image;
    modalImage.alt = o.product || 'Товар';
    document.getElementById('dName').textContent = fullName;
    document.getElementById('dPhone').innerHTML = o.phone ? `<a href="tel:${o.phone.replace(/[^\d+]/g,'')}">${o.phone}</a>` : '—';
    document.getElementById('dEmail').innerHTML = o.email ? `<a href="mailto:${o.email}">${o.email}</a>` : '—';
    document.getElementById('dProduct').textContent = o.product || '—';
    document.getElementById('dPrice').textContent = fmtPrice(o.price);
    document.getElementById('dDate').textContent = o.created_at || '—';
    document.getElementById('dIp').textContent = o.ip || '—';
    document.getElementById('dUa').textContent = o.user_agent || '—';
    const st = STATUS_LABELS[o.status] || STATUS_LABELS.new;
    document.getElementById('dStatus').innerHTML = `<span class="badge status-${o.status}">${st}</span>`;
    [modalAccept, modalReject].forEach(b => { if (b) { b.disabled = false; b.style.opacity = ''; b.style.cursor = ''; } });
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(){ modal.classList.remove('open'); document.body.style.overflow = ''; }

document.querySelectorAll('.order-row').forEach(row => {
    row.addEventListener('click', () => openOrder(row.dataset.id));
});
modalClose.addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && modal.classList.contains('open')) closeModal(); });

function applyFakeStatus(newStatus){
    if (!currentId) return;
    const row = document.querySelector(`.order-row[data-id="${currentId}"]`);
    const label = STATUS_LABELS[newStatus];
    if (row){
        const badge = row.querySelector('[data-role="status"]');
        if (badge){ badge.className = `badge status-${newStatus}`; badge.textContent = label; }
    }
    const dStatus = document.getElementById('dStatus');
    dStatus.innerHTML = `<span class="badge status-${newStatus}">${label}</span>`;
    [modalAccept, modalReject].forEach(b => { if (b) { b.disabled = true; b.style.opacity = '.5'; b.style.cursor = 'not-allowed'; } });
}
if (modalAccept) modalAccept.addEventListener('click', () => applyFakeStatus('done'));
if (modalReject) modalReject.addEventListener('click', () => applyFakeStatus('cancelled'));

/* ---------- Переключение вкладок ---------- */
const views = document.querySelectorAll('.view');
const sideButtons = document.querySelectorAll('.side-item[data-view]');
function switchView(name, updateHash = true){
    views.forEach(v => v.classList.toggle('active', v.id === 'view-' + name));
    sideButtons.forEach(b => b.classList.toggle('active', b.dataset.view === name));
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (updateHash) {
        history.replaceState(null, '', name === 'dashboard' ? 'admin.php' : 'admin.php?view=' + name);
    }
}
sideButtons.forEach(btn => { btn.addEventListener('click', () => switchView(btn.dataset.view)); });
(function(){
    const params = new URLSearchParams(location.search);
    const view = params.get('view') || 'dashboard';
    switchView(view, false);
})();

/* ---------- Логика вкладки «Сотрудники» ---------- */
const staffForm      = document.getElementById('staffForm');
const staffTitle     = document.getElementById('staffFormTitle');
const staffModeBadge = document.getElementById('staffModeBadge');
const staffSubmitBtn = document.getElementById('staffSubmitBtn');
const staffCancelBtn = document.getElementById('staffCancelBtn');
const editModeInput  = document.getElementById('editMode');
const usernameInput  = document.getElementById('sf-username');
const fullnameInput  = document.getElementById('sf-fullname');
const passwordInput  = document.getElementById('sf-password');
const roleSelect     = document.getElementById('sf-role');
const pwdHint        = document.getElementById('pwd-hint');
const pwdSub         = document.getElementById('pwd-sub');

function setCheckboxes(selector, values){
    document.querySelectorAll(selector).forEach(cb => { cb.checked = values.includes(cb.value); });
}
function applyRolePreset(role){
    setCheckboxes('.perm-views',   ROLE_PRESETS[role]        || []);
    setCheckboxes('.perm-actions', ROLE_ACTION_PRESETS[role] || []);
}
function resetStaffForm(){
    if (!staffForm) return;
    staffForm.querySelector('form').reset();
    editModeInput.value = '0';
    usernameInput.readOnly = false;
    usernameInput.style.background = '';
    staffTitle.textContent = 'Новый сотрудник';
    staffModeBadge.textContent = 'создание';
    staffModeBadge.style.background = '';
    staffModeBadge.style.color = '';
    staffSubmitBtn.textContent = 'Создать сотрудника';
    pwdHint.textContent = '*';
    pwdSub.textContent = 'Минимум 6 символов. Обязательно при создании.';
    applyRolePreset(roleSelect.value);
}
if (staffCancelBtn) staffCancelBtn.addEventListener('click', resetStaffForm);
if (roleSelect) {
    roleSelect.addEventListener('change', () => {
        if (editModeInput && editModeInput.value !== '1') applyRolePreset(roleSelect.value);
    });
}
document.querySelectorAll('.toggle-all').forEach(el => {
    el.addEventListener('click', () => {
        const target = el.dataset.target;
        const cbs = document.querySelectorAll('.perm-' + target);
        const allChecked = Array.from(cbs).every(cb => cb.checked);
        cbs.forEach(cb => cb.checked = !allChecked);
        el.textContent = allChecked ? 'выбрать все' : 'снять все';
    });
});
document.querySelectorAll('.staff-edit').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const row = btn.closest('.staff-table-row');
        if (!row || !staffForm) return;
        usernameInput.value = row.dataset.username;
        usernameInput.readOnly = true;
        usernameInput.style.background = '#f0f0f2';
        fullnameInput.value = row.dataset.fullname;
        passwordInput.value = '';
        roleSelect.value = row.dataset.role;
        setCheckboxes('.perm-views', JSON.parse(row.dataset.views || '[]'));
        setCheckboxes('.perm-actions', JSON.parse(row.dataset.actions || '[]'));
        editModeInput.value = '1';
        staffTitle.textContent = 'Редактирование: ' + row.dataset.username;
        staffModeBadge.textContent = 'изменение';
        staffModeBadge.style.background = 'var(--blue-light)';
        staffModeBadge.style.color = '#1e4f9a';
        staffSubmitBtn.textContent = 'Сохранить изменения';
        pwdHint.textContent = '';
        pwdSub.textContent = 'Оставьте пустым, чтобы не менять пароль.';
        switchView('staff', true);
        staffForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
});

/* ============================================================
   ЛОГИКА ВКЛАДКИ «НОВОСТИ»
   ============================================================ */
const newsForm      = document.getElementById('newsForm');
const newsFormEl    = document.getElementById('newsFormEl');
const newsFormTitle = document.getElementById('newsFormTitle');
const newsModeBadge = document.getElementById('newsModeBadge');
const newsSubmitBtn = document.getElementById('newsSubmitBtn');
const newsCancelBtn = document.getElementById('newsCancelBtn');
const newsAlert     = document.getElementById('newsAlert');
const newsList      = document.getElementById('newsList');
const newsCounter   = document.getElementById('newsCounter');

const nfId          = document.getElementById('nf-id');
const nfTitle       = document.getElementById('nf-title');
const nfSubtitle    = document.getElementById('nf-subtitle');
const nfDescription = document.getElementById('nf-description');
const nfImage       = document.getElementById('nf-image');
const nfFile        = document.getElementById('nf-file');
const nfUrl         = document.getElementById('nf-url');
const imgPreview    = document.getElementById('imgPreview');
const imgUploadRow  = document.getElementById('imgUploadRow');
const imgUrlRow     = document.getElementById('imgUrlRow');
const nfStatus      = document.getElementById('nf-status');

let newsImageSource = 'upload'; // 'upload' | 'url'

function showNewsAlert(ok, text){
    if (!newsAlert) return;
    newsAlert.innerHTML = `<div class="news-alert ${ok ? 'ok' : 'err'}">${ok ? '✓' : '✕'} ${text}</div>`;
    setTimeout(() => { newsAlert.innerHTML = ''; }, 5000);
}

function setPreview(src){
    if (!imgPreview) return;
    if (!src){
        imgPreview.classList.add('empty');
        imgPreview.innerHTML = '<span>Нет картинки</span>';
        return;
    }
    imgPreview.classList.remove('empty');
    imgPreview.innerHTML = `<img src="${src}" alt="Превью">`;
}

/* Переключение источника картинки */
document.querySelectorAll('.img-source-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.img-source-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        newsImageSource = tab.dataset.src;
        if (newsImageSource === 'upload'){
            imgUploadRow.style.display = '';
            imgUrlRow.style.display = 'none';
            // Если до этого был URL — очищаем
            if (nfUrl && !nfUrl.value.startsWith('uploads/news/')) {
                nfImage.value = '';
                setPreview('');
            }
        } else {
            imgUploadRow.style.display = 'none';
            imgUrlRow.style.display = '';
            nfImage.value = nfUrl ? nfUrl.value : '';
            setPreview(nfImage.value);
        }
    });
});

/* Превью при выборе URL */
if (nfUrl) {
    nfUrl.addEventListener('input', () => {
        nfImage.value = nfUrl.value.trim();
        setPreview(nfImage.value);
    });
}

/* Загрузка файла */
if (nfFile) {
    nfFile.addEventListener('change', async () => {
        if (!nfFile.files || !nfFile.files[0]) return;
        const file = nfFile.files[0];
        if (file.size > 5 * 1024 * 1024) {
            showNewsAlert(false, 'Файл слишком большой (макс. 5 МБ)');
            nfFile.value = '';
            return;
        }
        const fd = new FormData();
        fd.append('image', file);
        fd.append('action', 'upload');
        try {
            newsSubmitBtn.disabled = true;
            const origText = newsSubmitBtn.innerHTML;
            newsSubmitBtn.innerHTML = '<span class="spinner"></span>Загрузка...';
            const res = await fetch('news.php?action=upload', { method: 'POST', body: fd });
            const data = await res.json();
            newsSubmitBtn.disabled = false;
            newsSubmitBtn.innerHTML = origText;
            if (data.ok) {
                nfImage.value = data.url;
                setPreview(data.url);
                showNewsAlert(true, 'Картинка загружена');
            } else {
                showNewsAlert(false, data.error || 'Ошибка загрузки');
                nfFile.value = '';
            }
        } catch (err) {
            newsSubmitBtn.disabled = false;
            newsSubmitBtn.innerHTML = origText;
            showNewsAlert(false, 'Ошибка сети: ' + err.message);
            nfFile.value = '';
        }
    });
}

/* Загрузка списка новостей */
async function loadNews(){
    if (!newsList) return;
    try {
        const res = await fetch('news.php?action=all');
        const data = await res.json();
        if (!data.ok) {
            newsList.innerHTML = `<div class="empty">Ошибка: ${data.error}</div>`;
            return;
        }
        const items = data.items || [];
        if (newsCounter) newsCounter.textContent = `Всего: ${items.length}`;
        if (items.length === 0) {
            newsList.innerHTML = `<div class="empty" style="grid-column:1/-1">Новостей пока нет. Создайте первую!</div>`;
            return;
        }
        newsList.innerHTML = items.map(n => {
            const statusBadge = n.status === 'published'
                ? '<span class="badge status-published">Опубликовано</span>'
                : '<span class="badge status-draft">Черновик</span>';
            const date = n.created_at ? new Date(n.created_at).toLocaleDateString('ru-RU') : '';
            const imgHtml = n.image
                ? `<img src="${n.image}" alt="${escapeHtml(n.title)}" loading="lazy">`
                : `<div class="placeholder">Нет картинки</div>`;
            const actions = [];
            if (NEWS_PERMS.edit) {
                if (n.status === 'published') {
                    actions.push(`<button class="btn btn-warn" data-act="unpublish" data-id="${n.id}">Снять с публикации</button>`);
                } else {
                    actions.push(`<button class="btn btn-accept" data-act="publish" data-id="${n.id}">Опубликовать</button>`);
                }
                actions.push(`<button class="btn btn-ghost" data-act="edit" data-id="${n.id}">Редактировать</button>`);
            }
            if (NEWS_PERMS.del) {
                actions.push(`<button class="btn btn-reject" data-act="delete" data-id="${n.id}">Удалить</button>`);
            }
            return `
                <div class="news-card" data-id="${n.id}">
                    <div class="news-card-img">${imgHtml}</div>
                    <div class="news-card-body">
                        <div class="news-card-title">${escapeHtml(n.title)}</div>
                        ${n.subtitle ? `<div class="news-card-sub">${escapeHtml(n.subtitle)}</div>` : ''}
                        <div class="news-card-meta">
                            <span>${date}</span>
                            ${statusBadge}
                        </div>
                    </div>
                    ${actions.length ? `<div class="news-card-actions">${actions.join('')}</div>` : ''}
                </div>
            `;
        }).join('');

        // Навешиваем обработчики на кнопки карточек
        newsList.querySelectorAll('[data-act]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const id = btn.dataset.id;
                const act = btn.dataset.act;
                if (act === 'edit') {
                    editNews(id);
                } else if (act === 'delete') {
                    if (!confirm('Удалить новость? Это действие нельзя отменить.')) return;
                    await apiNews('delete', { id });
                } else if (act === 'publish') {
                    await apiNews('toggle_status', { id, status: 'published' });
                } else if (act === 'unpublish') {
                    await apiNews('toggle_status', { id, status: 'draft' });
                }
            });
        });
    } catch (err) {
        newsList.innerHTML = `<div class="empty" style="grid-column:1/-1">Ошибка загрузки: ${err.message}</div>`;
    }
}

function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function apiNews(action, payload){
    try {
        const res = await fetch('news.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (data.ok) {
            showNewsAlert(true, 'Сохранено');
            loadNews();
        } else {
            showNewsAlert(false, data.error || 'Ошибка');
        }
        return data;
    } catch (err) {
        showNewsAlert(false, 'Ошибка сети: ' + err.message);
        return { ok: false };
    }
}

function editNews(id){
    if (!newsForm) return;
    fetch('news.php?action=all')
        .then(r => r.json())
        .then(data => {
            if (!data.ok) return;
            const n = (data.items || []).find(x => String(x.id) === String(id));
            if (!n) return;
            nfId.value = n.id;
            nfTitle.value = n.title || '';
            nfSubtitle.value = n.subtitle || '';
            nfDescription.value = n.description || '';
            nfStatus.value = n.status || 'draft';

            // Определяем источник картинки
            const img = n.image || '';
            if (img.startsWith('uploads/news/')) {
                newsImageSource = 'upload';
                document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'upload'));
                imgUploadRow.style.display = '';
                imgUrlRow.style.display = 'none';
            } else if (img) {
                newsImageSource = 'url';
                document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'url'));
                imgUploadRow.style.display = 'none';
                imgUrlRow.style.display = '';
                nfUrl.value = img;
            } else {
                newsImageSource = 'upload';
                document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'upload'));
                imgUploadRow.style.display = '';
                imgUrlRow.style.display = 'none';
            }
            nfImage.value = img;
            setPreview(img);

            newsFormTitle.textContent = 'Редактирование: ' + n.title;
            newsModeBadge.textContent = 'изменение';
            newsModeBadge.style.background = 'var(--blue-light)';
            newsModeBadge.style.color = '#1e4f9a';
            newsSubmitBtn.textContent = 'Сохранить изменения';

            switchView('news', true);
            newsForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
}

function resetNewsForm(){
    if (!newsFormEl) return;
    newsFormEl.reset();
    nfId.value = '';
    nfImage.value = '';
    setPreview('');
    if (nfFile) nfFile.value = '';
    if (nfUrl) nfUrl.value = '';
    newsImageSource = 'upload';
    document.querySelectorAll('.img-source-tab').forEach(t => t.classList.toggle('active', t.dataset.src === 'upload'));
    imgUploadRow.style.display = '';
    imgUrlRow.style.display = 'none';
    newsFormTitle.textContent = 'Новая публикация';
    newsModeBadge.textContent = 'создание';
    newsModeBadge.style.background = '';
    newsModeBadge.style.color = '';
    newsSubmitBtn.textContent = 'Создать';
}

if (newsCancelBtn) newsCancelBtn.addEventListener('click', resetNewsForm);

if (newsFormEl) {
    newsFormEl.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            id:          nfId.value,
            title:       nfTitle.value.trim(),
            subtitle:    nfSubtitle.value.trim(),
            description: nfDescription.value.trim(),
            image:       nfImage.value.trim(),
            status:      nfStatus.value,
        };
        if (!payload.title) {
            showNewsAlert(false, 'Укажите заголовок');
            return;
        }
        newsSubmitBtn.disabled = true;
        const origText = newsSubmitBtn.innerHTML;
        newsSubmitBtn.innerHTML = '<span class="spinner"></span>Сохранение...';
        const data = await apiNews('save', payload);
        newsSubmitBtn.disabled = false;
        newsSubmitBtn.innerHTML = origText;
        if (data && data.ok) resetNewsForm();
    });
}

// Первичная загрузка
if (newsList) loadNews();
</script>
</body>
</html>