<?php
declare(strict_types=1);

const HO_ADMIN_AUTH_VERSION = 'HO-ADMIN-AUTH-052';

$secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

function ho_admin_private_path(string $file): string {
    $home = dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
    return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'hoosier-online-private' . DIRECTORY_SEPARATOR . $file;
}

function ho_admin_load_secrets(): array {
    $private = ho_admin_private_path('admin-secrets.php');
    if (is_file($private)) {
        $secrets = require $private;
        return is_array($secrets) ? $secrets : [];
    }

    $public = __DIR__ . '/admin-secrets.php';
    if (is_file($public)) {
        $secrets = require $public;
        return is_array($secrets) ? $secrets : [];
    }

    return [];
}

function ho_admin_is_logged_in(): bool {
    return !empty($_SESSION['ho_admin_authenticated']);
}

function ho_admin_require_login(): void {
    if (ho_admin_is_logged_in()) {
        return;
    }

    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/admin.php'), PHP_URL_PATH);
    $next = is_string($path) && $path !== '' ? $path : '/admin.php';
    header('Location: /admin-login.php?next=' . rawurlencode($next));
    exit;
}

function ho_admin_csrf_token(): string {
    if (empty($_SESSION['ho_csrf_token'])) {
        $_SESSION['ho_csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['ho_csrf_token'];
}

function ho_admin_verify_csrf(?string $token): bool {
    return is_string($token) && hash_equals((string)($_SESSION['ho_csrf_token'] ?? ''), $token);
}

function ho_admin_csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(ho_admin_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function ho_admin_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}
