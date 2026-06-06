<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
if (file_exists(__DIR__ . '/diagnosis-model.php')) require_once __DIR__ . '/diagnosis-model.php';
if (file_exists(__DIR__ . '/front-door-preview-model.php')) require_once __DIR__ . '/front-door-preview-model.php';
if (file_exists(__DIR__ . '/command-center-model.php')) require_once __DIR__ . '/command-center-model.php';

/**
 * v127 Front Door Builder / Preview State Writer
 *
 * Deterministic batch assignment of /go preview paths.
 * No GPT. No outreach. No payment. No static generation.
 */


function ho_front_builder_result_message(array $result): string {
    $parts = [];
    foreach (['message','error','first_issue'] as $key) {
        if (!empty($result[$key]) && is_scalar($result[$key])) $parts[] = (string)$result[$key];
    }
    foreach (['details','errors','failed','validation_errors'] as $key) {
        if (!empty($result[$key])) {
            $encoded = json_encode($result[$key], JSON_UNESCAPED_SLASHES);
            if ($encoded) $parts[] = $key . ': ' . substr($encoded, 0, 500);
        }
    }
    return $parts ? implode(' | ', $parts) : 'Import failed.';
}

function ho_front_builder_claim_value(array $business, string $fieldKey): string {
    if (function_exists('ho_command_safe_claim_value')) return ho_command_safe_claim_value($business, $fieldKey);
    if (function_exists('ho_diag_claim_value')) return ho_diag_claim_value($business, $fieldKey);
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_front_builder_json_claim(array $business, string $fieldKey): array {
    $raw = ho_front_builder_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_front_builder_is_diagnosis_ready(array $business): bool {
    if (function_exists('ho_command_is_diagnosis_ready')) return ho_command_is_diagnosis_ready($business);

    $status = strtolower(ho_front_builder_claim_value($business, 'diagnosis_status'));
    if (in_array($status, ['diagnosis_ready','preview_ready','go_ready'], true)) return true;

    return count(ho_front_builder_json_claim($business, 'strength_keys_json')) > 0
        && count(ho_front_builder_json_claim($business, 'weakness_keys_json')) > 0
        && count(ho_front_builder_json_claim($business, 'recommendation_keys_json')) > 0
        && count(ho_front_builder_json_claim($business, 'preview_direction_keys_json')) >= 3;
}

function ho_front_builder_has_go(array $business): bool {
    if (function_exists('ho_command_has_go_page')) return ho_command_has_go_page($business);
    return ho_front_builder_claim_value($business, 'go_slug') !== ''
        || ho_front_builder_claim_value($business, 'go_path') !== ''
        || strtolower(ho_front_builder_claim_value($business, 'front_door_preview_status')) === 'go_ready';
}

function ho_front_builder_needs_manual_review(array $business): bool {
    $slug = trim((string)($business['business_slug'] ?? ''));
    $name = trim((string)($business['business_name_current'] ?? ''));
    if ($slug === '' || $name === '') return true;
    if (!ho_front_builder_is_diagnosis_ready($business)) return false;
    if (count(ho_front_builder_json_claim($business, 'strength_keys_json')) === 0) return true;
    if (count(ho_front_builder_json_claim($business, 'weakness_keys_json')) === 0) return true;
    if (count(ho_front_builder_json_claim($business, 'recommendation_keys_json')) === 0) return true;
    if (count(ho_front_builder_json_claim($business, 'preview_direction_keys_json')) < 3) return true;
    return false;
}

function ho_front_builder_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_front_builder_import_payload(array $payload): array {
    if (function_exists('ho_salesportal_import_payload')) return ho_salesportal_import_payload($payload);
    if (function_exists('ho_salesportal_import_business_payload')) return ho_salesportal_import_business_payload($payload);
    return ['ok' => false, 'message' => 'No compatible Sales Portal import function is available.'];
}

function ho_front_builder_claim(string $fieldKey, string $value, string $note): array {
    return [
        'field_key' => $fieldKey,
        'claim_value' => $value,
        'normalized_value' => $value,
        'confidence_level' => 'confirmed',
        'confidence_score' => 95,
        'claim_status' => 'active',
        'source_type' => 'manual_observation',
        'source_url' => '',
        'source_label' => 'Front Door Builder',
        'evidence_note' => $note,
        'supports_me_category' => 'contact_me',
        'supports_requirement_key' => 'contact_me.clear_next_step',
        'evidence_source_index' => 0,
    ];
}

function ho_front_builder_payload_for_business(array $business): array {
    $slug = trim((string)($business['business_slug'] ?? ''));
    $name = trim((string)($business['business_name_current'] ?? ''));
    if ($slug === '') throw new RuntimeException('Missing business_slug.');
    if ($name === '') throw new RuntimeException('Missing business_name_current.');

    $goPath = '/go.php?slug=' . rawurlencode($slug);
    $version = 'front-door-preview-v126';

    return [
        'business' => [
            'id' => (int)($business['id'] ?? 0),
            'business_slug' => $slug,
            'business_name_current' => $name,
            'business_type' => (string)($business['business_type'] ?? 'local_service'),
            'location_city' => (string)($business['location_city'] ?? ''),
            'location_state' => (string)($business['location_state'] ?? 'IN'),
            'service_area_text' => (string)($business['service_area_text'] ?? 'Indiana'),
        ],
        'evidence_sources' => [[
            'source_type' => 'manual_observation',
            'source_url' => $goPath,
            'source_title' => 'Front Door Preview path assigned',
            'capture_status' => 'manual',
            'raw_excerpt' => $goPath,
            'notes' => 'Dynamic /go preview path assigned by Front Door Builder.'
        ]],
        'claims' => [
            ho_front_builder_claim('front_door_preview_status', 'go_ready', 'Dynamic /go preview is ready.'),
            ho_front_builder_claim('go_slug', $slug, 'Uses existing business_slug as go_slug.'),
            ho_front_builder_claim('go_path', $goPath, 'Query-string /go preview route assigned.'),
            ho_front_builder_claim('go_preview_version', $version, 'Renderer version used for this preview.'),
            ho_front_builder_claim('outreach_asset_url', $goPath, 'Primary outreach asset URL.'),
        ],
        'marketing_clearance' => [
            'marketing_clearance_status' => 'cleared',
            'marketing_clearance_score' => 90,
            'recommended_package' => 'standard',
            'recommended_design' => 'simple_front_door_preview',
            'reason' => 'Front Door Preview path assigned and ready for outreach drafting.'
        ],
        'notes' => ['Front Door Preview path assigned: ' . $goPath],
    ];
}

$flash = null;
$flashError = null;
$businesses = ho_front_builder_load_businesses();

function ho_front_builder_partition(array $businesses): array {
    $needed = [];
    $ready = [];
    $manual = [];
    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        if (ho_front_builder_needs_manual_review($business)) {
            if (ho_front_builder_is_diagnosis_ready($business) || ho_front_builder_has_go($business)) $manual[] = $business;
            continue;
        }
        if (ho_front_builder_has_go($business)) {
            $ready[] = $business;
            continue;
        }
        if (ho_front_builder_is_diagnosis_ready($business)) {
            $needed[] = $business;
        }
    }
    return [$needed, $ready, $manual];
}

[$previewNeeded, $previewReady, $manualReview] = ho_front_builder_partition($businesses);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['front_door_action'] ?? '') === 'create_go_batch') {
    $selected = $_POST['business_ids'] ?? [];
    if (!is_array($selected)) $selected = [];
    $selectedIds = array_map('intval', $selected);

    if (!$selectedIds) {
        // default to current batch if no boxes were selected
        $selectedIds = array_map(static fn($b) => (int)($b['id'] ?? 0), array_slice($previewNeeded, 0, 25));
        $selectedIds = array_values(array_filter($selectedIds));
    }

    $index = [];
    foreach ($previewNeeded as $business) {
        $index[(int)($business['id'] ?? 0)] = $business;
    }

    $ok = 0;
    $failed = 0;
    $firstIssue = '';

    foreach ($selectedIds as $id) {
        try {
            if (!isset($index[$id])) {
                throw new RuntimeException('Business id ' . $id . ' is not in the Front Door Preview Needed pile.');
            }
            $payload = ho_front_builder_payload_for_business($index[$id]);
            $result = ho_front_builder_import_payload($payload);
            if (($result['ok'] ?? false) === false) {
                throw new RuntimeException(ho_front_builder_result_message($result));
            }
            $ok++;
        } catch (Throwable $e) {
            $failed++;
            if ($firstIssue === '') $firstIssue = $e->getMessage();
        }
    }

    $flash = 'Front Door Preview batch complete. ' . $ok . ' ready, ' . $failed . ' failed.';
    if ($firstIssue !== '') $flash .= ' First issue: ' . $firstIssue;

    $businesses = ho_front_builder_load_businesses();
    [$previewNeeded, $previewReady, $manualReview] = ho_front_builder_partition($businesses);
}

$activeBatch = array_slice($previewNeeded, 0, 25);

ho_admin_render_start(
    'front_door_builder',
    'Front Door Builder',
    'Sales',
    'Front Door <em>Builder</em>',
    'Assign dynamic /go preview paths to diagnosis-ready businesses.'
);
?>

<?php if ($flash): ?>
  <section class="admin-card admin-flash-card admin-flash-success"><?= ho_h($flash) ?></section>
<?php endif; ?>
<?php if ($flashError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($flashError) ?></section>
<?php endif; ?>

<section class="admin-card admin-front-door-builder-hero">
  <p class="admin-kicker">Batch Step</p>
  <h2>Create /go Preview Paths</h2>
  <p class="admin-muted">This does not use GPT. It assigns a dynamic customer-facing preview path using the existing business slug and diagnosis keys.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Return To Command Center</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-operator-guide.php">Operator Guide</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-command-center-audit.php">Open State Audit</a>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Front Door Status</p>
  <h2>Preview Path Pipeline</h2>
  <div class="admin-stat-grid">
    <article><strong><?= ho_h((string)count($previewNeeded)) ?></strong><span>Preview Needed</span></article>
    <article><strong><?= ho_h((string)count($previewReady)) ?></strong><span>Preview Ready</span></article>
    <article><strong><?= ho_h((string)count($manualReview)) ?></strong><span>Manual Review</span></article>
  </div>
</section>

<section class="admin-card admin-active-prompt-card">
  <p class="admin-kicker">Primary Action</p>
  <h2>Create /go Preview Batch</h2>
  <?php if (!$activeBatch): ?>
    <div class="admin-empty-state">No diagnosis-ready businesses currently need /go preview paths.</div>
  <?php else: ?>
    <p class="admin-muted">This will assign paths for up to <?= ho_h((string)count($activeBatch)) ?> businesses in the current batch.</p>
    <form method="post">
      <input type="hidden" name="front_door_action" value="create_go_batch">
      <div class="admin-front-door-path-list">
        <?php foreach ($activeBatch as $business): ?>
          <?php
            $id = (int)($business['id'] ?? 0);
            $slug = (string)($business['business_slug'] ?? '');
            $goPath = '/go.php?slug=' . rawurlencode($slug);
          ?>
          <label class="admin-front-door-path-row">
            <input type="checkbox" name="business_ids[]" value="<?= ho_h((string)$id) ?>" checked>
            <span>
              <strong><?= ho_h((string)($business['business_name_current'] ?? '')) ?></strong>
              <em><?= ho_h($goPath) ?></em>
            </span>
            <a href="<?= ho_h($goPath) ?>" target="_blank" rel="noopener">Test</a>
          </label>
        <?php endforeach; ?>
      </div>
      <button class="admin-btn admin-btn-primary" type="submit">Create /go Preview Batch</button>
    </form>
  <?php endif; ?>
</section>

<section class="admin-card">
  <p class="admin-kicker">Preview Needed</p>
  <h2>Diagnosis-Ready Records</h2>
  <details>
    <summary>Show records needing /go path</summary>
    <?php if (!$previewNeeded): ?>
      <div class="admin-empty-state">No records waiting.</div>
    <?php else: ?>
      <div class="admin-data-list">
        <?php foreach ($previewNeeded as $business): ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)($business['business_name_current'] ?? '')) ?></strong>
              <span><?= ho_h((string)($business['business_slug'] ?? '')) ?></span>
              <div class="admin-data-row-note">Will use: /go.php?slug=<?= ho_h(rawurlencode((string)($business['business_slug'] ?? ''))) ?></div>
            </div>
            <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)($business['id'] ?? 0)) ?>">Inspect</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Preview Ready</p>
  <h2>Usable /go Paths</h2>
  <details>
    <summary>Show ready previews</summary>
    <?php if (!$previewReady): ?>
      <div class="admin-empty-state">No ready /go previews yet.</div>
    <?php else: ?>
      <div class="admin-data-list">
        <?php foreach ($previewReady as $business): ?>
          <?php $path = ho_front_builder_claim_value($business, 'go_path') ?: '/go.php?slug=' . rawurlencode((string)($business['business_slug'] ?? '')); ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)($business['business_name_current'] ?? '')) ?></strong>
              <span><?= ho_h($path) ?></span>
            </div>
            <a class="admin-btn admin-btn-secondary" href="<?= ho_h($path) ?>" target="_blank" rel="noopener">Open Preview</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Manual Review</p>
  <h2>Records Not Safe To Build Automatically</h2>
  <details>
    <summary>Show manual review records</summary>
    <?php if (!$manualReview): ?>
      <div class="admin-empty-state">No manual review records.</div>
    <?php else: ?>
      <div class="admin-data-list">
        <?php foreach ($manualReview as $business): ?>
          <div class="admin-data-row">
            <div>
              <strong><?= ho_h((string)($business['business_name_current'] ?? 'Missing name')) ?></strong>
              <span><?= ho_h((string)($business['business_slug'] ?? 'Missing slug')) ?></span>
              <div class="admin-data-row-note">Check slug, name, diagnosis keys, and preview directions.</div>
            </div>
            <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)($business['id'] ?? 0)) ?>">Inspect</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </details>
</section>

<?php ho_admin_render_end(); ?>
