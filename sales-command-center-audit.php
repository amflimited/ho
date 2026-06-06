<?php
require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';

if (file_exists(__DIR__ . '/diagnosis-model.php')) {
    require_once __DIR__ . '/diagnosis-model.php';
}
require_once __DIR__ . '/command-center-model.php';

/**
 * v124 Command Center State Audit
 *
 * Read-only. No imports. No updates. No outreach. No payment.
 */

$loadError = null;
$businesses = [];
$audit = null;

try {
    $businesses = ho_command_load_businesses();
    $audit = ho_command_build_audit($businesses);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
    $audit = [
        'version' => HO_COMMAND_CENTER_VERSION,
        'total_records' => 0,
        'labels' => ho_command_bucket_labels(),
        'buckets' => ho_command_blank_buckets(),
        'states' => [],
        'next_move' => [
            'next_move_key' => 'audit_load_error',
            'title' => 'Audit Could Not Load',
            'why' => 'The audit hit a runtime error while reading records: ' . $e->getMessage(),
            'count' => 0,
            'target_url' => '/sales-portal-dashboard.php',
            'target_label' => 'Return To Dashboard',
            'expected_result' => 'Fix the read error, then reload the audit.',
            'bucket_key' => '',
        ],
    ];
}

function ho_command_audit_bucket(string $key, array $audit): array {
    return $audit['buckets'][$key] ?? [];
}

function ho_command_audit_record_row(array $business): void {
    $state = ho_command_evaluate_business($business);
    $clues = ho_command_status_clues($business, $state);
    $id = (int)($business['id'] ?? 0);
    ?>
    <div class="admin-command-record">
      <div class="admin-command-record-main">
        <strong><?= ho_h((string)($business['business_name_current'] ?? 'Unnamed business')) ?></strong>
        <span><?= ho_h((string)($business['business_slug'] ?? 'missing-slug')) ?></span>
        <em><?= ho_h((string)($business['business_type'] ?? 'local_service')) ?> · <?= ho_h((string)($business['location_city'] ?? '')) ?>, <?= ho_h((string)($business['location_state'] ?? 'IN')) ?></em>
      </div>
      <div class="admin-command-clues">
        <span>queue: <?= ho_h($state['queue_key']) ?></span>
        <?php if ($clues['diagnosis_status'] !== ''): ?><span>diagnosis: <?= ho_h($clues['diagnosis_status']) ?></span><?php endif; ?>
        <?php if ($clues['front_door_preview_status'] !== ''): ?><span>preview: <?= ho_h($clues['front_door_preview_status']) ?></span><?php endif; ?>
        <?php if ($clues['go_path'] !== ''): ?><span>go: <?= ho_h($clues['go_path']) ?></span><?php endif; ?>
        <?php if ($clues['marketing_desk_status'] !== ''): ?><span>marketing: <?= ho_h($clues['marketing_desk_status']) ?></span><?php endif; ?>
        <?php if ($state['problem_keys']): ?><span>problems: <?= ho_h(implode(', ', $state['problem_keys'])) ?></span><?php endif; ?>
      </div>
      <?php if ($id > 0): ?>
        <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)$id) ?>">Inspect</a>
      <?php endif; ?>
    </div>
    <?php
}

function ho_command_audit_bucket_card(string $key, array $audit, string $note = ''): void {
    $labels = $audit['labels'] ?? [];
    $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    $rows = ho_command_audit_bucket($key, $audit);
    ?>
    <article class="admin-command-bucket-card" id="bucket-<?= ho_h($key) ?>">
      <div class="admin-command-bucket-head">
        <div>
          <h3><?= ho_h($label) ?></h3>
          <?php if ($note !== ''): ?><p><?= ho_h($note) ?></p><?php endif; ?>
        </div>
        <strong><?= ho_h((string)count($rows)) ?></strong>
      </div>
      <details>
        <summary>Show records</summary>
        <?php if (!$rows): ?>
          <div class="admin-empty-state">No records in this bucket.</div>
        <?php else: ?>
          <div class="admin-command-record-list">
            <?php foreach ($rows as $business): ?>
              <?php ho_command_audit_record_row($business); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </details>
    </article>
    <?php
}

$next = $audit['next_move'];

ho_admin_render_start(
    'command_center_audit',
    'Command Center State Audit',
    'Sales',
    'Command Center <em>State Audit</em>',
    'Read-only truth page for lead pipeline, sales assets, data problems, and next move.'
);
?>

<?php if ($loadError): ?>
  <section class="admin-card admin-flash-card admin-flash-error">
    <strong>Read error:</strong> <?= ho_h($loadError) ?>
  </section>
<?php endif; ?>

<section class="admin-card admin-command-next-card">
  <p class="admin-kicker">Next Move</p>
  <h2><?= ho_h($next['title']) ?></h2>
  <div class="admin-command-next-meta">
    <span><b>next_move_key:</b> <?= ho_h($next['next_move_key']) ?></span>
    <span><b>count affected:</b> <?= ho_h((string)$next['count']) ?></span>
  </div>
  <p class="admin-muted"><?= ho_h($next['why']) ?></p>
  <div class="admin-command-expected">
    <b>Expected result:</b> <?= ho_h($next['expected_result']) ?>
  </div>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="<?= ho_h($next['target_url']) ?>"><?= ho_h($next['target_label']) ?></a>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Return To Dashboard</a>
  </div>
  <?php if (($next['bucket_key'] ?? '') !== ''): ?>
    <details class="admin-command-next-records">
      <summary>Show records included in this next move</summary>
      <div class="admin-command-record-list">
        <?php foreach (ho_command_audit_bucket($next['bucket_key'], $audit) as $business): ?>
          <?php ho_command_audit_record_row($business); ?>
        <?php endforeach; ?>
      </div>
    </details>
  <?php endif; ?>
</section>

<section class="admin-card">
  <p class="admin-kicker">Source Truth</p>
  <h2>Audit Summary</h2>
  <div class="admin-stat-grid">
    <article><strong><?= ho_h((string)$audit['total_records']) ?></strong><span>Total Records</span></article>
    <article><strong><?= ho_h((string)count(ho_command_audit_bucket('contact_ready', $audit))) ?></strong><span>Contact Ready</span></article>
    <article><strong><?= ho_h((string)count(ho_command_audit_bucket('contact_ready_without_diagnosis', $audit))) ?></strong><span>Need Diagnosis</span></article>
    <article><strong><?= ho_h((string)count(ho_command_audit_bucket('diagnosis_ready', $audit))) ?></strong><span>Diagnosis Ready</span></article>
    <article><strong><?= ho_h((string)count(ho_command_audit_bucket('diagnosis_ready_without_go_preview', $audit))) ?></strong><span>Need /go</span></article>
    <article><strong><?= ho_h((string)count(ho_command_audit_bucket('go_page_ready', $audit))) ?></strong><span>/go Ready</span></article>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Upstream</p>
  <h2>Lead Pipeline Counts</h2>
  <div class="admin-command-bucket-grid">
    <?php
      ho_command_audit_bucket_card('need_triage', $audit, 'Imported candidates that still need sorting.');
      ho_command_audit_bucket_card('need_research', $audit, 'Businesses that still need public-surface refinement.');
      ho_command_audit_bucket_card('proceed_no_website', $audit, 'Usable records that can skip deep web analysis.');
      ho_command_audit_bucket_card('ready_for_setup', $audit, 'Businesses ready for preview/contact setup in the old pipeline.');
      ho_command_audit_bucket_card('contact_ready', $audit, 'Businesses with enough contact direction to move toward sales assets.');
      ho_command_audit_bucket_card('blocked_skip', $audit, 'Duplicates, weak fits, bad fits, or records not worth active work.');
    ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Sales Asset Pipeline</p>
  <h2>Diagnosis → /go → Outreach</h2>
  <div class="admin-command-bucket-grid">
    <?php
      ho_command_audit_bucket_card('contact_ready_without_diagnosis', $audit, 'Contact-ready records that still need diagnosis keys.');
      ho_command_audit_bucket_card('diagnosis_ready', $audit, 'Records that have usable Front Door diagnosis keys.');
      ho_command_audit_bucket_card('diagnosis_ready_without_go_preview', $audit, 'Diagnosed records that still need a /go preview page/path.');
      ho_command_audit_bucket_card('front_door_preview_ready', $audit, 'Records with enough diagnosis data to render a Front Door Preview.');
      ho_command_audit_bucket_card('go_page_missing', $audit, 'Records that should have /go pages but do not have go_slug/go_path yet.');
      ho_command_audit_bucket_card('go_page_ready', $audit, 'Records with an actual /go path or slug.');
      ho_command_audit_bucket_card('outreach_draft_needed', $audit, 'Records with /go pages but no outreach draft yet.');
      ho_command_audit_bucket_card('draft_ready', $audit, 'Drafts staged for manual review.');
      ho_command_audit_bucket_card('manual_review_needed', $audit, 'Records requiring operator judgment.');
    ?>
  </div>
</section>

<section class="admin-card" id="data-problems">
  <p class="admin-kicker">Data Problems</p>
  <h2>Blocked Or Suspicious State</h2>
  <p class="admin-muted">These buckets do not update anything. They show why routing may be wrong or unsafe.</p>
  <div class="admin-command-bucket-grid">
    <?php
      ho_command_audit_bucket_card('missing_business_slug', $audit);
      ho_command_audit_bucket_card('missing_business_name', $audit);
      ho_command_audit_bucket_card('contact_ready_but_no_usable_contact_path', $audit);
      ho_command_audit_bucket_card('diagnosis_ready_but_missing_strength_keys_json', $audit);
      ho_command_audit_bucket_card('diagnosis_ready_but_missing_weakness_keys_json', $audit);
      ho_command_audit_bucket_card('diagnosis_ready_but_missing_recommendation_keys_json', $audit);
      ho_command_audit_bucket_card('diagnosis_ready_but_missing_preview_direction_keys_json', $audit);
      ho_command_audit_bucket_card('preview_ready_but_missing_go_slug_go_path', $audit);
      ho_command_audit_bucket_card('package_dummy_placeholder_identity', $audit);
      ho_command_audit_bucket_card('conflicting_status_claims', $audit);
      ho_command_audit_bucket_card('diagnosis_claims_but_no_diagnosis_status', $audit);
    ?>
  </div>
</section>

<section class="admin-card admin-low-priority">
  <p class="admin-kicker">Debug</p>
  <h2>Source Truth Fields</h2>
  <details>
    <summary>Show field logic</summary>
    <pre class="admin-code"><?= ho_h(json_encode([
      'version' => $audit['version'],
      'fields_used' => [
        'queue_key' => 'computed from upstream claims/status, or ho_salesportal_ui_queue_key if loaded',
        'contact_readiness',
        'diagnosis_status',
        'strength_keys_json',
        'weakness_keys_json',
        'recommendation_keys_json',
        'preview_direction_keys_json',
        'front_door_preview_status',
        'go_slug',
        'go_path',
        'outreach_asset_url',
        'marketing_desk_status',
        'outreach_subject',
        'outreach_body',
        'package_status',
      ],
      'read_only' => true,
      'mutates_records' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<?php ho_admin_render_end(); ?>
