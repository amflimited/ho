<?php
require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/diagnosis-model.php';
ho_admin_render_start('diagnosis_system','Diagnosis System','Sales','Diagnosis <em>System</em>','Registry and contract for the customer-facing /go Front Door Preview page.');
$keys = ho_diag_all_block_keys();
$summary = ['strength_blocks'=>count($keys['strengths']),'weakness_blocks'=>count($keys['weaknesses']),'recommendation_blocks'=>count($keys['recommendations']),'preview_directions'=>count($keys['preview_directions']),'offer_paths'=>count($keys['offers'])];
?>
<section class="admin-card">
  <p class="admin-kicker">Front Door Preview</p>
  <h2>/go/{slug} Contract</h2>
  <p class="admin-muted">The pre-sale page is assembled from diagnosis keys, not custom one-off sales copy.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-diagnosis-workbench.php">Open Diagnosis Workbench</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Return To Work Queue</a>
  </div>
</section>
<section class="admin-card"><p class="admin-kicker">Registry Summary</p><h2>Block Counts</h2><div class="admin-stat-grid"><?php foreach($summary as $k=>$v): ?><article><strong><?= ho_h((string)$v) ?></strong><span><?= ho_h(str_replace('_',' ',$k)) ?></span></article><?php endforeach; ?></div></section>
<section class="admin-card"><p class="admin-kicker">Strengths</p><h2>Strength Block Registry</h2><details><summary>Show strengths</summary><pre class="admin-code"><?= ho_h(json_encode(ho_diag_strength_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details></section>
<section class="admin-card"><p class="admin-kicker">Weaknesses</p><h2>Weakness Block Registry</h2><details><summary>Show weaknesses</summary><pre class="admin-code"><?= ho_h(json_encode(ho_diag_weakness_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details></section>
<section class="admin-card"><p class="admin-kicker">Recommendations</p><h2>Recommendation Block Registry</h2><details><summary>Show recommendations</summary><pre class="admin-code"><?= ho_h(json_encode(ho_diag_recommendation_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details></section>
<section class="admin-card"><p class="admin-kicker">Preview Directions</p><h2>Three Direction Model</h2><details open><summary>Show preview directions</summary><pre class="admin-code"><?= ho_h(json_encode(ho_diag_preview_direction_registry(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details></section>
<section class="admin-card"><p class="admin-kicker">Contract</p><h2>Front Door Page Contract</h2><details open><summary>Show /go contract</summary><pre class="admin-code"><?= ho_h(json_encode(ho_diag_front_door_contract(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></details></section>
<?php ho_admin_render_end(); ?>
