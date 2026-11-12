<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (isset($_GET['logout'])) auth_logout();

if (auth_current_user()) {
    header('Location: admin.php');
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $res = auth_login(
        (string)($_POST['username'] ?? ''),
        (string)($_POST['password'] ?? '')
    );
    if ($res['ok']) {
        header('Location: admin.php');
        exit;
    }
    $loginError = $res['error'];
}
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
<form method="post" action="login.php" autocomplete="off">
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