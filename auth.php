<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}

function auth_current_user(): ?array {
    if (empty($_SESSION['admin_authed'])) return null;
    $u = users_find((string)($_SESSION['admin_user'] ?? ''));
    if (!$u) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    return $u;
}

/** Возвращает ['ok'=>bool,'error'=>string] */
function auth_login(string $u, string $p): array {
    $_SESSION['login_attempts'] = array_values(array_filter(
        $_SESSION['login_attempts'] ?? [],
        fn($t) => $t > time() - 600
    ));
    if (count($_SESSION['login_attempts']) >= 5) {
        return ['ok' => false, 'error' => 'Слишком много попыток. Попробуйте позже.'];
    }
    $verified = users_verify($u, $p);
    if ($verified) {
        session_regenerate_id(true);
        $_SESSION['admin_authed'] = true;
        $_SESSION['admin_user']   = $verified['username'];
        $_SESSION['login_attempts'] = [];
        return ['ok' => true, 'user' => $verified];
    }
    $_SESSION['login_attempts'][] = time();
    return ['ok' => false, 'error' => 'Неверный логин или пароль.'];
}

/** Guard: если не авторизован — редирект на login.php */
function auth_require(): array {
    $user = auth_current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    return $user;
}