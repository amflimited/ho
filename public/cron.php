<?php
declare(strict_types=1);
/**
 * Web worker trigger (the operator is phone-only; cPanel cron also hits this):
 *   /cron.php?job=all&key=ADMIN_KEY
 * Jobs: migrate research verify personalize send heat all
 */
require dirname(__DIR__) . '/bin/bootstrap.php';

use HoV2\Workers\Runner;

$pdo = ho_pdo();
$hash = ho_setting($pdo, 'admin_key');
$key  = (string)($_GET['key'] ?? '');
if ($hash === '' || $key === '' || !password_verify($key, $hash)) {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: application/json');
echo json_encode(Runner::run($pdo, (string)($_GET['job'] ?? 'all')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
