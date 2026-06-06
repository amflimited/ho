<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/production-lane-model.php';

$loadError = null;
$businesses = [];
try {
    $businesses = ho_lane_load_businesses();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}

$counts = ho_lane_counts($businesses);
$currentJob = ho_lane_current_job($counts);
$tools = ho_lane_tools();

ho_admin_render_start(
    'production_lane_home',
    'Hoosier Online',
    'Sales',
    'Hoosier Online <em>Production Lane</em>',
    'One current job. One continuation.'
);
?>

<section class="admin-lane-hero">
  <p>Current production job</p>
  <h1><?= ho_h($currentJob['headline']) ?></h1>
  <strong><?= ho_h($currentJob['why']) ?></strong>
  <a class="admin-lane-primary" href="<?= ho_h($currentJob['route']) ?>"><?= ho_h($currentJob['button']) ?></a>
  <div class="admin-lane-proof"><?= ho_h($currentJob['proof']) ?></div>
</section>

<?php if ($loadError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($loadError) ?></section>
<?php endif; ?>

<section class="admin-lane-mini-proof">
  <span>Send <?= ho_h((string)$counts['send_ready']) ?></span>
  <span>Prep <?= ho_h((string)$counts['prep_ready']) ?></span>
  <span>Repair <?= ho_h((string)$counts['problem_records']) ?></span>
  <span>Known <?= ho_h((string)$counts['known_records']) ?></span>
</section>

<section class="admin-lane-tools">
  <p style="margin:0 0 10px;font-weight:950;font-size:12px;text-transform:uppercase;letter-spacing:.08em;">All tools</p>
  <div class="admin-lane-tool-list">
    <?php foreach ($tools as $tool): ?>
      <a href="<?= ho_h($tool[1]) ?>">
        <strong><?= ho_h($tool[0]) ?></strong>
        <span><?= ho_h($tool[2]) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<?php ho_admin_render_end(); ?>
