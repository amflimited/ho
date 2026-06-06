<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
if (file_exists(__DIR__ . '/command-center-model.php')) require_once __DIR__ . '/command-center-model.php';
require_once __DIR__ . '/market-map-model.php';

/**
 * v129 Market Coverage Tracker
 * Read-oriented only. No scraping, sending, importing, or mutation.
 */

$loadError = null;
$market = null;

try {
    $businesses = ho_market_load_businesses();
    $market = ho_market_build_map($businesses);
} catch (Throwable $e) {
    $loadError = $e->getMessage();
    $market = [
        'version' => HO_MARKET_MAP_VERSION,
        'overall' => ho_market_blank_stats(),
        'categories' => [],
        'regions' => [],
        'sources' => [],
        'bottlenecks' => [],
        'next_market_action' => [
            'key' => 'market_map_error',
            'title' => 'Market Map could not load',
            'why' => $e->getMessage(),
            'target_url' => '/sales-portal-dashboard.php',
            'target_label' => 'Return To Command Center',
        ],
    ];
}

function ho_market_row(array $cat): void {
    $s = $cat['stats'];
    ?>
    <article class="admin-market-row">
      <div class="admin-market-main">
        <h3><?= ho_h((string)$cat['label']) ?></h3>
        <span><?= ho_h((string)$s['total']) ?> records · <?= ho_h((string)ho_market_completion_percent($s)) ?>% draft/customer ready</span>
      </div>
      <div class="admin-market-metrics">
        <span><b><?= ho_h((string)$s['need_triage']) ?></b> triage</span>
        <span><b><?= ho_h((string)$s['need_research']) ?></b> research</span>
        <span><b><?= ho_h((string)$s['contact_ready']) ?></b> contact</span>
        <span><b><?= ho_h((string)$s['diagnosis_ready']) ?></b> diagnosis</span>
        <span><b><?= ho_h((string)$s['go_ready']) ?></b> /go</span>
        <span><b><?= ho_h((string)$s['outreach_draft_needed']) ?></b> draft needed</span>
        <span><b><?= ho_h((string)$s['draft_ready']) ?></b> draft ready</span>
        <span><b><?= ho_h((string)$s['blocked_skip']) ?></b> blocked</span>
      </div>
      <details>
        <summary>Show category details</summary>
        <div class="admin-market-region-chips">
          <?php foreach (($cat['regions'] ?? []) as $region => $count): ?>
            <span><?= ho_h((string)$region) ?>: <?= ho_h((string)$count) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="admin-data-list">
          <?php foreach (array_slice($cat['records'] ?? [], 0, 40) as $business): ?>
            <?php $state = ho_market_state_for_business($business); ?>
            <div class="admin-data-row">
              <div>
                <strong><?= ho_h((string)($business['business_name_current'] ?? '')) ?></strong>
                <span><?= ho_h((string)($business['business_slug'] ?? '')) ?></span>
                <div class="admin-data-row-note">
                  <?= ho_h((string)($business['location_city'] ?? '')) ?>, <?= ho_h((string)($business['location_state'] ?? 'IN')) ?>
                  · queue <?= ho_h((string)$state['queue_key']) ?>
                  <?= $state['has_go_page'] ? ' · /go ready' : '' ?>
                  <?= $state['draft_ready'] ? ' · draft ready' : '' ?>
                </div>
              </div>
              <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)($business['id'] ?? 0)) ?>">Inspect</a>
            </div>
          <?php endforeach; ?>
        </div>
      </details>
    </article>
    <?php
}

ho_admin_render_start(
    'market_map',
    'Market Map',
    'Sales',
    'Market <em>Map</em>',
    'Read-only coverage tracker by category, city, and production state.'
);
?>

<?php if ($loadError): ?>
  <section class="admin-card admin-flash-card admin-flash-error">
    <strong>Market Map load error:</strong> <?= ho_h($loadError) ?>
  </section>
<?php endif; ?>

<section class="admin-card admin-market-next-card">
  <p class="admin-kicker">Next Market Action</p>
  <h2><?= ho_h((string)$market['next_market_action']['title']) ?></h2>
  <p class="admin-muted"><?= ho_h((string)$market['next_market_action']['why']) ?></p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="<?= ho_h((string)$market['next_market_action']['target_url']) ?>"><?= ho_h((string)$market['next_market_action']['target_label']) ?></a>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Return To Command Center</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-operator-guide.php">Operator Guide</a>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Overall Coverage</p>
  <h2>Current Records</h2>
  <?php $s = $market['overall']; ?>
  <div class="admin-stat-grid">
    <article><strong><?= ho_h((string)$s['total']) ?></strong><span>Total</span></article>
    <article><strong><?= ho_h((string)$s['need_triage']) ?></strong><span>Need Triage</span></article>
    <article><strong><?= ho_h((string)$s['need_research']) ?></strong><span>Need Research</span></article>
    <article><strong><?= ho_h((string)$s['contact_ready']) ?></strong><span>Contact Ready</span></article>
    <article><strong><?= ho_h((string)$s['diagnosis_ready']) ?></strong><span>Diagnosis Ready</span></article>
    <article><strong><?= ho_h((string)$s['go_ready']) ?></strong><span>/go Ready</span></article>
    <article><strong><?= ho_h((string)$s['outreach_draft_needed']) ?></strong><span>Draft Needed</span></article>
    <article><strong><?= ho_h((string)$s['draft_ready']) ?></strong><span>Draft Ready</span></article>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Bottlenecks</p>
  <h2>Where Work Is Stuck</h2>
  <div class="admin-market-bottleneck-grid">
    <?php foreach ($market['bottlenecks'] as $b): ?>
      <article>
        <span><?= ho_h((string)$b['label']) ?></span>
        <strong><?= ho_h((string)$b['category']) ?></strong>
        <em><?= ho_h((string)$b['count']) ?> records</em>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Category Coverage</p>
  <h2>Service Categories</h2>
  <div class="admin-market-list">
    <?php if (!$market['categories']): ?>
      <div class="admin-empty-state">No category records found.</div>
    <?php else: ?>
      <?php foreach ($market['categories'] as $cat): ?>
        <?php ho_market_row($cat); ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Region / City View</p>
  <h2>Indiana Context</h2>
  <p class="admin-muted">Indiana is the broad location gate. City and service area are sourcing context only.</p>
  <details>
    <summary>Show city/region counts</summary>
    <div class="admin-market-region-list">
      <?php foreach (array_slice($market['regions'], 0, 60) as $region): ?>
        <div class="admin-market-region-row">
          <strong><?= ho_h((string)$region['label']) ?></strong>
          <span><?= ho_h((string)$region['stats']['total']) ?> records</span>
          <em><?= ho_h((string)$region['stats']['go_ready']) ?> /go ready · <?= ho_h((string)$region['stats']['draft_ready']) ?> draft ready</em>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
</section>

<section class="admin-card admin-low-priority">
  <p class="admin-kicker">Source Context</p>
  <h2>Source Groups</h2>
  <details>
    <summary>Show source groups</summary>
    <div class="admin-market-region-list">
      <?php foreach ($market['sources'] as $source): ?>
        <div class="admin-market-region-row">
          <strong><?= ho_h((string)$source['label']) ?></strong>
          <span><?= ho_h((string)$source['stats']['total']) ?> records</span>
          <em><?= ho_h((string)$source['stats']['contact_ready']) ?> contact ready · <?= ho_h((string)$source['stats']['blocked_skip']) ?> blocked</em>
        </div>
      <?php endforeach; ?>
    </div>
  </details>
</section>

<?php ho_admin_render_end(); ?>
