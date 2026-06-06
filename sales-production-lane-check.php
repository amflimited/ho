<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/production-lane-model.php';

/**
 * v141 Production Lane Check
 * Read-only reality check for current job selection.
 */

$loadError = null;
$businesses = [];
try {
    $businesses = ho_lane_load_businesses();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$counts = ho_lane_counts($businesses);
$currentJob = ho_lane_current_job($counts);
$priorityRows = ho_lane_priority_explanation($counts, (string)$currentJob['job_key']);
$samples = ho_lane_sample_records_for_job($businesses, (string)$currentJob['job_key'], 10);

ho_admin_render_start(
    'production_lane_check',
    'Lane Check',
    'Sales',
    'Production Lane <em>Check</em>',
    'Read-only proof for why the home page selected the current job.'
);
?>

<section class="admin-lane-check-hero">
  <p>v141 read-only check</p>
  <h1><?= ho_h($currentJob['headline']) ?></h1>
  <strong><?= ho_h($currentJob['why']) ?></strong>
  <a class="admin-lane-check-primary" href="/sales-portal-dashboard.php">Return to Production Lane</a>
</section>

<?php if ($loadError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($loadError) ?></section>
<?php endif; ?>

<section class="admin-lane-check-section">
  <h2>Counts used</h2>
  <div class="admin-lane-check-counts">
    <div><span>send_ready</span><strong><?= ho_h((string)$counts['send_ready']) ?></strong></div>
    <div><span>prep_ready</span><strong><?= ho_h((string)$counts['prep_ready']) ?></strong></div>
    <div><span>intake_waiting</span><strong><?= ho_h((string)$counts['intake_waiting']) ?></strong></div>
    <div><span>problem_records</span><strong><?= ho_h((string)$counts['problem_records']) ?></strong></div>
    <div><span>known_records</span><strong><?= ho_h((string)$counts['known_records']) ?></strong></div>
  </div>
</section>

<section class="admin-lane-check-section">
  <h2>Priority decision</h2>
  <div class="admin-lane-check-priority">
    <?php foreach ($priorityRows as $row): ?>
      <article class="<?= $row['selected'] ? 'is-selected' : '' ?>">
        <span><?= ho_h((string)$row['job_key']) ?></span>
        <strong><?= ho_h((string)$row['label']) ?></strong>
        <em><?= ho_h((string)$row['count_key']) ?> = <?= ho_h((string)$row['count']) ?></em>
        <p><?= ho_h((string)$row['reason']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-lane-check-section">
  <h2>Selected job proof</h2>
  <dl class="admin-lane-check-dl">
    <dt>job_key</dt><dd><?= ho_h((string)$currentJob['job_key']) ?></dd>
    <dt>headline</dt><dd><?= ho_h((string)$currentJob['headline']) ?></dd>
    <dt>route</dt><dd><?= ho_h((string)$currentJob['route']) ?></dd>
    <dt>proof</dt><dd><?= ho_h((string)$currentJob['proof']) ?></dd>
  </dl>
</section>

<section class="admin-lane-check-section">
  <h2>Sample records</h2>
  <?php if (!$samples): ?>
    <div class="admin-empty-state">No safe sample records for this job. This is expected for Source or undetectable Intake states.</div>
  <?php else: ?>
    <div class="admin-lane-check-samples">
      <?php foreach ($samples as $sample): ?>
        <article>
          <strong><?= ho_h((string)$sample['business_name']) ?></strong>
          <span><?= ho_h((string)$sample['business_slug']) ?></span>
          <em><?= ho_h((string)$sample['category']) ?> · <?= ho_h((string)$sample['city_state']) ?></em>
          <?php if (!empty($sample['flags'])): ?>
            <p>Flags: <?= ho_h(implode(', ', $sample['flags'])) ?></p>
          <?php endif; ?>
          <?php if (!empty($sample['business_id'])): ?>
            <a href="<?= ho_h((string)$sample['inspect_url']) ?>">Inspect</a>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="admin-lane-check-section">
  <details>
    <summary>Raw debug payload</summary>
    <pre class="admin-contract-code"><?= ho_h(json_encode([
        'current_job' => $currentJob,
        'counts' => $counts,
        'priority_rows' => $priorityRows,
        'sample_records' => $samples,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<section class="admin-lane-check-section">
  <details>
    <summary>No-automation boundary</summary>
    <ul>
      <li>Read-only check page.</li>
      <li>No operational writes.</li>
      <li>No automatic sending.</li>
      <li>No SMS.</li>
      <li>No AI calls.</li>
      <li>No scraping automation.</li>
      <li>No payments.</li>
      <li>No domain purchasing.</li>
    </ul>
  </details>
</section>

<?php ho_admin_render_end(); ?>
