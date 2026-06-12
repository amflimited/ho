<?php
declare(strict_types=1);
/**
 * One-time environment bootstrap. Safe to re-run (idempotent).
 * Run from repo root: php bin/setup.php
 *
 * Does:
 *   1. Verifies config/db.php exists and DB connection works
 *   2. Applies migrations/003_milestone2.sql (idempotent — IF NOT EXISTS + INSERT IGNORE)
 *   3. Generates a fresh admin_key and stores its bcrypt hash in app_settings
 *   4. Prints the key (only time it is ever shown — store it)
 */

$root = dirname(__DIR__);

// ── 1. DB config check ───────────────────────────────────────────────────────
$dbFile = $root . '/config/db.php';
if (!file_exists($dbFile)) {
    fwrite(STDERR, "ERROR: config/db.php not found.\n");
    fwrite(STDERR, "Create it with: <?php return ['host'=>'localhost','dbname'=>'...','user'=>'...','pass'=>'...'];\n");
    exit(1);
}

$cfg = require $dbFile;
try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset=utf8mb4",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: DB connection failed — " . $e->getMessage() . "\n");
    exit(1);
}
echo "DB connection OK.\n";

// ── 2. Apply migration 003 ───────────────────────────────────────────────────
$sql = file_get_contents($root . '/migrations/003_milestone2.sql');
if ($sql === false) {
    fwrite(STDERR, "ERROR: migrations/003_milestone2.sql not found.\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || str_starts_with(ltrim($stmt), '--')) { continue; }
    try {
        $pdo->exec($stmt);
    } catch (PDOException $e) {
        // Duplicate key on INSERT IGNORE is fine; re-report anything else
        if ($e->getCode() !== '23000') {
            fwrite(STDERR, "WARN: " . $e->getMessage() . "\n");
        }
    }
}
echo "Migration 003 applied.\n";

// ── 3. Generate admin_key ────────────────────────────────────────────────────
$existing = $pdo->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ?');
$existing->execute(['admin_key']);
$hash = $existing->fetchColumn();

if ($hash !== false && $hash !== '') {
    echo "admin_key already set (not replaced). Use existing key or delete the row to regenerate.\n";
} else {
    $key  = bin2hex(random_bytes(16));
    $hash = password_hash($key, PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
        ->execute(['admin_key', $hash]);
    echo "\n";
    echo "╔══════════════════════════════════════════════════════╗\n";
    echo "║  admin_key (shown once — copy it now):               ║\n";
    echo "║  {$key}  ║\n";
    echo "╚══════════════════════════════════════════════════════╝\n";
    echo "\nUse it at: /cron.php?job=all&key={$key}\n";
    echo "\n";
}

// ── 4. Record migration in schema_migrations if table exists ─────────────────
try {
    $pdo->exec("INSERT IGNORE INTO schema_migrations (version) VALUES ('003_milestone2')");
} catch (PDOException) { /* table may not exist yet — non-fatal */ }

echo "Setup complete.\n";
