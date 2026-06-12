<?php
declare(strict_types=1);
/**
 * ONE-TIME ETL: v1 database -> v2 canonical schema.
 * Usage: php bin/migrate-v1.php
 * Requires config/db.php (v2) and config/db-v1.php (old database, read-only creds).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use HoV2\Import\Importer;
use HoV2\Outreach\Suppression;

$v2 = require dirname(__DIR__) . '/config/db.php';
$v1 = require dirname(__DIR__) . '/config/db-v1.php';
$importer = new Importer($v2);

echo "== Migrating businesses + research ==\n";
$rows = $v1->query(
    'SELECT b.*, r.* FROM businesses b LEFT JOIN research_records r ON r.business_id = b.id'
)->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
foreach ($rows as $row) {
    $payload = json_encode(['research_results' => [array_merge($row, [
        'raw_name' => $row['business_name'],
        'city'     => $row['location_city'],
        'state'    => $row['location_state'] ?? 'IN',
        'email'    => $row['email_address'] ?? '',
        'phone'    => $row['phone_number'] ?? '',
        'confidence' => 'high',
    ])]]);
    $result = $importer->import($payload);
    if ($result['rejected'] !== []) {
        foreach ($result['rejected'] as $name => $why) { echo "  SKIP {$name}: {$why}\n"; }
    }
    $status = $row['pipeline_status'] ?? 'identified';
    $stmt = $v2->prepare('UPDATE businesses SET pipeline_status = ?, triaged = ? WHERE business_name = ? AND location_city = ?');
    $stmt->execute([$status, (int)($row['triaged'] ?? 0), $row['business_name'], $row['location_city']]);
    $count++;
}
echo "Migrated {$count} businesses.\n";

echo "== Seeding suppression (the most important step) ==\n";
$optOuts = $v1->query(
    "SELECT DISTINCT b.id, b.email_address, b.business_name
     FROM businesses b
     LEFT JOIN outreach_log o ON o.business_id = b.id
     WHERE b.pipeline_status IN ('not_a_fit')
        OR o.outcome = 'not_interested'"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($optOuts as $row) {
    $find = $v2->prepare('SELECT id FROM businesses WHERE business_name = ?');
    $find->execute([$row['business_name']]);
    $v2Id = $find->fetchColumn();
    Suppression::add($v2, $row['email_address'] ?: null, 'v1_import', $v2Id ? (int)$v2Id : null, 'v1 not_a_fit / not_interested');
    echo "  suppressed: {$row['business_name']}\n";
}
echo "Suppression seeded: " . count($optOuts) . " entries.\n";
echo "\nETL complete. Spot-check counts before going live:\n";
echo "  v2 businesses: " . $v2->query('SELECT COUNT(*) FROM businesses')->fetchColumn() . "\n";
echo "  v2 profiles:   " . $v2->query('SELECT COUNT(*) FROM business_profile')->fetchColumn() . "\n";
echo "  v2 suppressed: " . $v2->query('SELECT COUNT(*) FROM suppression')->fetchColumn() . "\n";
