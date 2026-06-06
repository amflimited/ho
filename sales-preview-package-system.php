<?php
require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/preview-package-model.php';

ho_admin_render_start(
    'preview_package_system',
    'Preview Package System',
    'Sales',
    'Preview <em>Package</em>',
    'Contract, registries, and readiness rules for the Contact Ready downstream package stage.'
);

$summary = ho_preview_package_registry_summary();
$statuses = ho_preview_package_statuses();
$designs = ho_preview_web_design_registry();
$logos = ho_preview_logo_direction_registry();
$domainRules = ho_preview_domain_rules();
$slugRules = ho_preview_slug_rules();
$reportBlocks = ho_preview_sales_report_block_registry();
$contract = ho_preview_package_contract();
$criteria = ho_preview_package_readiness_criteria();
?>

<section class="admin-card">
  <p class="admin-kicker">Locked Product Definition</p>
  <h2>Contact Ready → Preview Package → Marketing Desk</h2>
  <p class="admin-muted">This is the planning foundation for the next workbench. The package system manufactures reusable personalized sales assets. It does not send outreach.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Return To Work Queue</a>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Registry Summary</p>
  <h2>Locked Counts</h2>
  <div class="admin-stat-grid">
    <?php foreach ($summary as $key => $value): ?>
      <article><strong><?= ho_h((string)$value) ?></strong><span><?= ho_h(str_replace('_', ' ', $key)) ?></span></article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Status Flow</p>
  <h2>Package States</h2>
  <div class="admin-data-list">
    <?php foreach ($statuses as $key => $status): ?>
      <div class="admin-data-row">
        <div>
          <strong><?= ho_h($status['label']) ?></strong>
          <span><?= ho_h($key) ?></span>
          <div class="admin-data-row-note"><?= ho_h($status['meaning']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Website Designs</p>
  <h2>10 Locked Design Styles</h2>
  <details open>
    <summary>Show design registry</summary>
    <div class="admin-data-list">
      <?php foreach ($designs as $design): ?>
        <div class="admin-data-row">
          <div>
            <strong><?= ho_h($design['display_name']) ?></strong>
            <span><?= ho_h($design['template_key']) ?></span>
            <div class="admin-data-row-note">
              <b>Best for:</b> <?= ho_h($design['best_for']) ?><br>
              <b>CTA:</b> <?= ho_h($design['default_cta']) ?><br>
              <b>Tone:</b> <?= ho_h($design['visual_tone']) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Identity Directions</p>
  <h2>10 Browser-Font Mockup Styles</h2>
  <details>
    <summary>Show logo direction registry</summary>
    <div class="admin-data-list">
      <?php foreach ($logos as $logo): ?>
        <div class="admin-data-row">
          <div>
            <strong><?= ho_h($logo['display_name']) ?></strong>
            <span><?= ho_h($logo['logo_key']) ?></span>
            <div class="admin-data-row-note">
              <b>Customer label:</b> <?= ho_h($logo['customer_label']) ?><br>
              <b>Font stack:</b> <?= ho_h($logo['font_stack']) ?><br>
              <b>Best for:</b> <?= ho_h($logo['best_for']) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Domains And Slugs</p>
  <h2>Candidates First, Verification Later</h2>
  <details open>
    <summary>Show domain and slug rules</summary>
    <h3>Domain Rules</h3>
    <pre class="admin-code"><?= ho_h(json_encode($domainRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
    <h3>Slug Rules</h3>
    <pre class="admin-code"><?= ho_h(json_encode($slugRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Sales Report</p>
  <h2>Block Registry Skeleton</h2>
  <details>
    <summary>Show report block keys</summary>
    <pre class="admin-code"><?= ho_h(json_encode($reportBlocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">JSON Contract</p>
  <h2>Preview Package Output Shape</h2>
  <details>
    <summary>Show package contract</summary>
    <pre class="admin-code"><?= ho_h(json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<section class="admin-card">
  <p class="admin-kicker">Validation</p>
  <h2>Readiness Criteria</h2>
  <details open>
    <summary>Show package readiness criteria</summary>
    <pre class="admin-code"><?= ho_h(json_encode($criteria, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
  </details>
</section>

<?php ho_admin_render_end(); ?>
