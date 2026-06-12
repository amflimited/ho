<?php
declare(strict_types=1);
/** CLI worker trigger: php bin/cron.php [job]. Same dispatcher as public/cron.php. */
require __DIR__ . '/bootstrap.php';

use HoV2\Workers\Runner;

$job = $argv[1] ?? 'all';
echo json_encode(Runner::run(ho_pdo(), $job), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
