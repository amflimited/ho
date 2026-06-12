<?php
declare(strict_types=1);
/**
 * Shared bootstrap: PSR-4 autoloader (no Composer on shared hosting) + PDO + settings.
 * Every entry point (public/*, bin/*) requires this first.
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'HoV2\\';
    if (str_starts_with($class, $prefix)) {
        $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) { require $path; }
    }
});

function ho_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }

    // config/db.php may return either a ready PDO (legacy) or a config array.
    // Accept both so the one file format question can never break the app again.
    $c = require dirname(__DIR__) . '/config/db.php';
    if ($c instanceof PDO) { return $pdo = $c; }

    $pdo = new PDO(
        "mysql:host={$c['host']};dbname={$c['dbname']};charset=utf8mb4",
        $c['user'], $c['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements bind PHP null as SQL NULL — the fix for the
            // "Incorrect integer value: '' for column has_online_booking" error.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

function ho_setting(PDO $pdo, string $key): string
{
    $s = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
    $s->execute([$key]);
    return (string)($s->fetchColumn() ?: '');
}

function ho_set_setting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    )->execute([$key, $value]);
}
