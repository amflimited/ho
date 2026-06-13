<?php
declare(strict_types=1);
/**
 * Web worker trigger (the operator is phone-only; cPanel cron also hits this):
 *   /cron.php?job=all&key=ADMIN_KEY
 * Jobs: migrate research verify personalize voice send heat all
 *
 * Agent API (the VPS brain talks through these two doors):
 *   POST /cron.php?job=import&key=…   body = canonical {"research_results":[…]} JSON
 *   GET  /cron.php?job=status&key=…   funnel counts + recent names (dupe avoidance)
 */
require dirname(__DIR__) . '/bin/bootstrap.php';

use HoV2\Import\Importer;
use HoV2\Workers\Runner;

$pdo = ho_pdo();
$hash = ho_setting($pdo, 'admin_key');
$key  = (string)($_GET['key'] ?? '');
if ($hash === '' || $key === '' || !password_verify($key, $hash)) {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: application/json');
$job = (string)($_GET['job'] ?? 'all');

if ($job === 'import') {
    $payload = (string)file_get_contents('php://input');
    if (trim($payload) === '') {
        http_response_code(400);
        exit(json_encode(['error' => 'POST a {"research_results":[...]} JSON body']));
    }
    try {
        echo json_encode((new Importer($pdo))->import($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($job === 'status') {
    $funnel = $pdo->query('SELECT pipeline_status, COUNT(*) c FROM businesses GROUP BY pipeline_status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $byCat = $pdo->query(
        'SELECT COALESCE(c.name, "(none)") cat, COUNT(*) n FROM businesses b
         LEFT JOIN categories c ON c.id = b.category_id GROUP BY cat ORDER BY n ASC'
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $recent = $pdo->query(
        'SELECT CONCAT(business_name, " — ", location_city) FROM businesses ORDER BY id DESC LIMIT 150'
    )->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode([
        'funnel'             => $funnel,
        'businesses_by_category' => $byCat,
        'already_in_database'    => $recent,
        'awaiting_triage'    => (int)$pdo->query('SELECT COUNT(*) FROM businesses WHERE triaged = 0')->fetchColumn(),
        'unverified'         => (int)$pdo->query(
            "SELECT COUNT(*) FROM businesses b JOIN business_profile p ON p.business_id = b.id
             WHERE p.verified_at IS NULL AND b.triaged = 1
               AND b.pipeline_status IN ('researched','preview_ready','enhancement_ready')"
        )->fetchColumn(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(Runner::run($pdo, $job), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
