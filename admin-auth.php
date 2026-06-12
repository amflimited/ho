<?php
declare(strict_types=1);

/**
 * Operator lock for the Hoosier Online admin surface (app.php, money.php, the
 * audit endpoints). Single-operator, iPhone-only: one login, then a signed
 * 60-day remember cookie rehydrates the session so Adam never logs in twice.
 *
 * Secrets live at ../hoosier-online-private/admin-secrets.php (preferred) or
 * ./admin-secrets.php (fallback), shape:
 *   ['username' => '...', 'password_hash' => '...', 'session_key' => '<64 hex>']
 */

const HO_ADMIN_AUTH_VERSION = 'HO-ADMIN-AUTH-060';
const HO_ADMIN_REMEMBER_COOKIE = 'ho_admin_remember';
const HO_ADMIN_REMEMBER_DAYS   = 60;

function ho_admin_cookie_secure(): bool {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime'  => 0,
        'path'      => '/',
        'secure'    => ho_admin_cookie_secure(),
        'httponly'  => true,
        'samesite'  => 'Lax',
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
    return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'hoosier-online-private' . DIRECTORY_SEPARATOR . $file;
}

function ho_admin_secret_paths(): array {
    return [ho_admin_private_path('admin-secrets.php'), __DIR__ . '/admin-secrets.php'];
}

function ho_admin_load_secrets(): array {
    foreach (ho_admin_secret_paths() as $path) {
        if (is_file($path)) {
            $secrets = require $path;
            return is_array($secrets) ? $secrets : [];
        }
    }
    return [];
}

/** True only when a real password hash + session key are present (not the example placeholders). */
function ho_admin_setup_status(): array {
    $s = ho_admin_load_secrets();
    $hash = (string)($s['password_hash'] ?? '');
    $key  = (string)($s['session_key'] ?? '');
    $configured = $hash !== '' && !str_contains($hash, 'PASTE_')
        && $key !== '' && !str_contains($key, 'PASTE_');
    return ['configured' => $configured, 'checked_paths' => ho_admin_secret_paths()];
}

function ho_admin_secret_key(): string {
    return (string)(ho_admin_load_secrets()['session_key'] ?? '');
}

/* ── Remember-me cookie ─────────────────────────────────────────────────────
   value = "{expiry}.{hmac_sha256(expiry, session_key)}". The key never leaves
   the server, so the cookie can't be forged. */

function ho_admin_issue_remember(): void {
    $key = ho_admin_secret_key();
    if ($key === '') return;
    $expiry = time() + HO_ADMIN_REMEMBER_DAYS * 86400;
    $sig    = hash_hmac('sha256', (string)$expiry, $key);
    setcookie(HO_ADMIN_REMEMBER_COOKIE, $expiry . '.' . $sig, [
        'expires'  => $expiry,
        'path'     => '/',
        'secure'   => ho_admin_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function ho_admin_check_remember(): bool {
    $raw = (string)($_COOKIE[HO_ADMIN_REMEMBER_COOKIE] ?? '');
    if ($raw === '' || !str_contains($raw, '.')) return false;
    $key = ho_admin_secret_key();
    if ($key === '') return false;
    [$expiry, $sig] = explode('.', $raw, 2);
    if (!ctype_digit($expiry) || (int)$expiry < time()) return false;
    $expected = hash_hmac('sha256', $expiry, $key);
    return hash_equals($expected, $sig);
}

function ho_admin_clear_remember(): void {
    setcookie(HO_ADMIN_REMEMBER_COOKIE, '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'secure'   => ho_admin_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/* ── Login state ────────────────────────────────────────────────────────── */

function ho_admin_is_logged_in(): bool {
    if (!empty($_SESSION['ho_admin_authenticated'])) return true;
    if (ho_admin_check_remember()) {
        $_SESSION['ho_admin_authenticated'] = true;
        return true;
    }
    return false;
}

function ho_admin_login_attempt(string $username, string $password): bool {
    $s    = ho_admin_load_secrets();
    $user = (string)($s['username'] ?? '');
    $hash = (string)($s['password_hash'] ?? '');
    if ($user === '' || $hash === '') return false;
    if (!hash_equals($user, $username)) return false;
    if (!password_verify($password, $hash)) return false;

    session_regenerate_id(true);
    $_SESSION['ho_admin_authenticated'] = true;
    ho_admin_issue_remember();
    return true;
}

function ho_admin_require_login(): void {
    if (ho_admin_is_logged_in()) return;
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/money.php'), PHP_URL_PATH);
    $next = is_string($path) && $path !== '' ? $path : '/money.php';
    header('Location: /admin-login.php?next=' . rawurlencode($next));
    exit;
}

/** For fetch/JSON endpoints: 401 instead of an HTML redirect. */
function ho_admin_require_login_json(): void {
    if (ho_admin_is_logged_in()) return;
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

function ho_admin_logout(): void {
    ho_admin_clear_remember();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], (bool)$p['httponly']);
    }
    session_destroy();
}

/* ── CSRF helpers (kept verbatim from the harvested organ) ──────────────── */

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
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(ho_admin_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
