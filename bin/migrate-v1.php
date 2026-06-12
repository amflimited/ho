<?php
declare(strict_types=1);
/**
 * ONE-TIME ETL: v1 database -> v2 canonical schema. CLI wrapper around V1Etl
 * (the cockpit "Run v1 ETL" button uses the same class).
 * Requires config/db.php (v2) and config/db-v1.php (old database, read-only creds).
 */
require __DIR__ . '/bootstrap.php';

use HoV2\Import\V1Etl;

$v1file = dirname(__DIR__) . '/config/db-v1.php';
if (!is_file($v1file)) {
    fwrite(STDERR, "Missing config/db-v1.php — copy db.php and point it at the old database.\n");
    exit(1);
}

echo json_encode(V1Etl::run(ho_pdo(), require $v1file), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
