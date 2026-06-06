<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';

$optionalLoads = [
    'command-center-model.php',
    'diagnosis-model.php',
    'front-door-preview-model.php',
    'market-map-model.php',
];

$loadResults = [];
foreach ($optionalLoads as $file) {
    try {
        if (file_exists(__DIR__ . '/' . $file)) {
            require_once __DIR__ . '/' . $file;
            $loadResults[$file] = ['ok' => true, 'message' => 'Loaded'];
        } else {
            $loadResults[$file] = ['ok' => false, 'message' => 'Missing'];
        }
    } catch (Throwable $e) {
        $loadResults[$file] = ['ok' => false, 'message' => $e->getMessage()];
    }
}

function ho_check_result(bool $ok, string $label, string $message): array {
    return ['ok' => $ok, 'label' => $label, 'message' => $message];
}

$checks = [];

$requiredFiles = [
    'admin-core.php',
    'prospect-model.php',
    'command-center-model.php',
    'diagnosis-model.php',
    'front-door-preview-model.php',
    'go.php',
    'sales-command-center-audit.php',
    'sales-diagnosis-workbench.php',
    'sales-front-door-builder.php',
    'sales-marketing-desk.php',
    'market-map-model.php',
    'sales-market-map.php',
    'sales-operator-guide.php',
];

foreach ($requiredFiles as $file) {
    $checks[] = ho_check_result(file_exists(__DIR__ . '/' . $file), 'File: ' . $file, file_exists(__DIR__ . '/' . $file) ? 'Present' : 'Missing');
}

$functionChecks = [
    'ho_command_load_businesses',
    'ho_command_build_audit',
    'ho_diag_strength_registry',
    'ho_diag_weakness_registry',
    'ho_front_find_business_by_slug',
    'ho_front_assemble',
    'ho_market_build_map',
];

foreach ($functionChecks as $fn) {
    $checks[] = ho_check_result(function_exists($fn), 'Function: ' . $fn, function_exists($fn) ? 'Available' : 'Missing');
}

$importAvailable = function_exists('ho_salesportal_import_payload') || function_exists('ho_salesportal_import_business_payload');
$checks[] = ho_check_result($importAvailable, 'Import function', $importAvailable ? 'Compatible import function available' : 'No compatible import function found');

$canonicalFields = [
    'diagnosis_status',
    'strength_keys_json',
    'weakness_keys_json',
    'recommendation_keys_json',
    'primary_offer_path',
    'preview_direction_keys_json',
    'front_door_preview_status',
    'go_slug',
    'go_path',
    'outreach_asset_url',
    'marketing_desk_status',
    'outreach_subject',
    'outreach_body',
];

$canonFields = [];
try {
    if (function_exists('ho_salesportal_canon')) {
        $canon = ho_salesportal_canon();
        $canonFields = $canon['claim_fields'] ?? [];
    }
} catch (Throwable $e) {
    $canonFields = [];
}

foreach ($canonicalFields as $field) {
    $checks[] = ho_check_result(in_array($field, $canonFields, true), 'Canonical claim: ' . $field, in_array($field, $canonFields, true) ? 'Allowed' : 'Missing from claim_fields');
}

$passCount = count(array_filter($checks, static fn($c) => $c['ok']));
$failCount = count($checks) - $passCount;

ho_admin_render_start(
    'system_check',
    'System Check',
    'Sales',
    'System <em>Check</em>',
    'Required files, functions, importer, and canonical claim fields.'
);
?>

<section class="admin-card admin-system-check-hero">
  <p class="admin-kicker">v130 Health Check</p>
  <h2><?= ho_h((string)$passCount) ?> pass · <?= ho_h((string)$failCount) ?> fail</h2>
  <p class="admin-muted">This page verifies the locked v1 workflow dependencies. It does not mutate records.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Return To Command Center</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-operator-guide.php">Open Operator Guide</a>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Load Results</p>
  <h2>Model Files</h2>
  <div class="admin-system-list">
    <?php foreach ($loadResults as $file => $result): ?>
      <div class="admin-system-row <?= $result['ok'] ? 'is-ok' : 'is-bad' ?>">
        <strong><?= ho_h($file) ?></strong>
        <span><?= ho_h($result['message']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Checks</p>
  <h2>Required Workflow Dependencies</h2>
  <div class="admin-system-list">
    <?php foreach ($checks as $check): ?>
      <div class="admin-system-row <?= $check['ok'] ? 'is-ok' : 'is-bad' ?>">
        <strong><?= ho_h($check['label']) ?></strong>
        <span><?= ho_h($check['message']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php ho_admin_render_end(); ?>
