<?php
// One-time migration runner. Delete this file after use.
if (($_GET['run'] ?? '') !== 'go') {
    echo '<form><button name="run" value="go">Run migrations</button></form>';
    exit;
}

ini_set('display_errors', '1');
ob_implicit_flush(true);
echo '<pre>';

try {
    $pdo = require dirname(__DIR__) . '/config/db.php';
    echo "DB connected.\n\n";

    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (filename VARCHAR(190) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)');
    $applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $files = glob(dirname(__DIR__) . '/migrations/*.sql');
    sort($files);
    foreach ($files as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) { echo "Already applied: {$name}\n"; continue; }
        echo "Applying {$name}... ";
        $pdo->exec(file_get_contents($file));
        $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)')->execute([$name]);
        echo "done\n";
    }
    echo "\nMigrations complete.\n";
    echo 'Businesses: ' . $pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn() . "\n";
    echo 'Profiles:   ' . $pdo->query('SELECT COUNT(*) FROM business_profile')->fetchColumn() . "\n";
    echo 'Suppressed: ' . $pdo->query('SELECT COUNT(*) FROM suppression')->fetchColumn() . "\n";
    echo "\nDELETE THIS FILE: public/run-migrations.php";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage();
}
echo '</pre>';
