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
    return $pdo ??= require dirname(__DIR__) . '/config/db.php';
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
