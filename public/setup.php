<?php
declare(strict_types=1);
/**
 * One-tap web setup (phone-friendly). Visit once:
 *   https://v2.hoosieronline.com/setup.php?k=TOKEN
 *
 * Applies migration 003, generates the admin_key, prints it.
 * Token-guarded and self-disabling: after a successful run it records a flag
 * in app_settings and refuses to run again (so the admin_key can't be reset
 * by a stray visit). Safe to leave deployed.
 */

const SETUP_TOKEN = '8908db4a3d5070e882b9ddf4';

header('Content-Type: text/html; charset=utf-8');

$k = (string)($_GET['k'] ?? '');
if (!hash_equals(SETUP_TOKEN, $k)) {
    http_response_code(403);
    exit('<p style="font:16px sans-serif">Forbidden. Append <code>?k=YOUR_TOKEN</code> to the URL.</p>');
}

require dirname(__DIR__) . '/bin/bootstrap.php';

function out(string $msg): void { echo '<p style="margin:.4em 0">' . $msg . '</p>'; flush(); }

echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">';
echo '<body style="font:17px/1.5 -apple-system,sans-serif;max-width:640px;margin:2em auto;padding:0 1em;color:#1c2430">';
echo '<h2>Hoosier Online &mdash; Setup</h2>';

try {
    $pdo = ho_pdo();
    out('&#10003; Database connected.');
} catch (Throwable $e) {
    out('&#10007; Database connection failed: ' . htmlspecialchars($e->getMessage()));
    out('Check <code>config/db.php</code> on the server.');
    exit;
}

// Refuse to re-run once done — protects the admin_key.
try {
    if (ho_setting($pdo, 'setup_done') === '1') {
        out('&#9888; Setup already completed on this database.');
        out('The admin key was shown once and is not stored in readable form. '
          . 'If you lost it, tell Claude to rotate it.');
        exit;
    }
} catch (Throwable) { /* app_settings may not exist yet on a fresh DB — continue */ }

// Apply migration 003
$sqlFile = dirname(__DIR__) . '/migrations/003_milestone2.sql';
$sql = is_file($sqlFile) ? (string)file_get_contents($sqlFile) : '';
if ($sql === '') {
    out('&#10007; migrations/003_milestone2.sql not found on server.');
    exit;
}
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    if ($stmt === '' || str_starts_with(ltrim($stmt), '--')) { continue; }
    try {
        $pdo->exec($stmt);
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') { out('&#9888; ' . htmlspecialchars($e->getMessage())); }
    }
}
out('&#10003; Migration 003 applied (pitch_drafts + default settings).');

// Generate admin_key
$key  = bin2hex(random_bytes(16));
ho_set_setting($pdo, 'admin_key', password_hash($key, PASSWORD_DEFAULT));
ho_set_setting($pdo, 'setup_done', '1');

out('&#10003; Admin key generated.');
echo '<div style="background:#f6f4ef;border:2px solid #1c2430;border-radius:10px;padding:1em;margin:1.2em 0">';
echo '<p style="margin:0 0 .3em;font-weight:600">Your admin key &mdash; copy it now (shown only once):</p>';
echo '<p style="margin:0;font:20px ui-monospace,monospace;word-break:break-all">' . htmlspecialchars($key) . '</p>';
echo '</div>';

$base = 'https://' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'v2.hoosieronline.com');
out('Run the workers any time from your phone:');
echo '<p style="font:15px ui-monospace,monospace;word-break:break-all">'
   . $base . '/cron.php?job=all&amp;key=' . htmlspecialchars($key) . '</p>';

out('<strong>Setup is complete.</strong> You can close this page.');
