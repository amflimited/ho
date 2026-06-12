<?php
declare(strict_types=1);
/** Migration runner. Usage: php bin/migrate.php  (reads config/db.php for PDO) */
$pdo = require dirname(__DIR__) . '/config/db.php';
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(190) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(dirname(__DIR__) . '/migrations/*.sql');
sort($files);
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) { continue; }
    echo "Applying {$name}... ";
    $pdo->exec(file_get_contents($file));
    $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
    echo "done\n";
}
echo "Migrations up to date.\n";
