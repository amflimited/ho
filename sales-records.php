<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';
require_once __DIR__ . '/prospect-model.php';
require_once __DIR__ . '/records-model.php';

/**
 * v135 Records Repair Module
 * Read-only repair bay. No destructive writes. No durable merge.
 */

$query = trim((string)($_GET['q'] ?? ''));
$filter = trim((string)($_GET['filter'] ?? ''));
$compareA = (int)($_GET['compare_a'] ?? 0);
$compareB = (int)($_GET['compare_b'] ?? 0);

$loadError = null;
$businesses = [];
try {
    $businesses = ho_records_load_businesses();
} catch (Throwable $e) {
    $loadError = $e->getMessage();
}
$results = ho_records_search($businesses, $query, $filter, 80);

$problemFilters = [
    '' => 'All Records',
    'missing_slug' => 'Missing Slug',
    'missing_name' => 'Missing Name',
    'missing_category' => 'Missing Category',
    'missing_contact_surface' => 'Missing Contact Surface',
    'outside_indiana' => 'Outside Indiana',
    'possible_duplicate_clues' => 'Possible Duplicate Clues',
];

function ho_records_render_card(array $business, array $allBusinesses): void {
    $id = (int)($business['id'] ?? 0);
    $name = ho_records_value($business, 'business_name_current') ?: 'Missing business name';
    $slug = ho_records_value($business, 'business_slug') ?: 'missing-slug';
    $type = ho_records_value($business, 'business_type') ?: 'missing-category';
    $city = ho_records_value($business, 'location_city');
    $state = ho_records_value($business, 'location_state') ?: 'IN';
    $surfaces = ho_records_surface_summary($business);
    $clues = ho_records_status_clues($business);
    $flags = ho_records_problem_flags($business, $allBusinesses);
    $guidance = ho_records_repair_guidance($business, $flags);
    ?>
    <article class="admin-record-card">
      <h3><?= ho_h($name) ?></h3>
      <div class="admin-record-meta">
        <span><?= ho_h($slug) ?></span>
        <span><?= ho_h($type) ?></span>
        <span><?= ho_h(trim($city . ', ' . $state, ', ')) ?></span>
      </div>

      <?php if ($flags): ?>
        <div class="admin-record-flags">
          <?php foreach ($flags as $flag): ?><span><?= ho_h(str_replace('_', ' ', $flag)) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <details>
        <summary>Contact surfaces</summary>
        <dl>
          <?php foreach ($surfaces as $label => $value): ?>
            <dt><?= ho_h(ucwords($label)) ?></dt><dd><?= ho_h((string)$value) ?></dd>
          <?php endforeach; ?>
        </dl>
      </details>

      <details>
        <summary>Status clues</summary>
        <?php if (!$clues): ?>
          <p>No status clues found.</p>
        <?php else: ?>
          <dl>
            <?php foreach ($clues as $key => $value): ?>
              <dt><?= ho_h($key) ?></dt><dd><?= ho_h($value) ?></dd>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
      </details>

      <details>
        <summary>Repair guidance</summary>
        <ul>
          <?php foreach ($guidance as $line): ?><li><?= ho_h($line) ?></li><?php endforeach; ?>
        </ul>
      </details>

      <div class="admin-record-actions">
        <a class="admin-btn admin-btn-secondary" href="/sales-business.php?id=<?= ho_h((string)$id) ?>">Inspect</a>
        <a class="admin-btn admin-btn-secondary" href="/sales-records.php?compare_a=<?= ho_h((string)$id) ?>">Compare A</a>
        <a class="admin-btn admin-btn-secondary" href="/sales-records.php?compare_b=<?= ho_h((string)$id) ?>">Compare B</a>
      </div>
    </article>
    <?php
}

$recordA = $compareA ? ho_records_find_by_id($businesses, $compareA) : null;
$recordB = $compareB ? ho_records_find_by_id($businesses, $compareB) : null;
$comparison = ($recordA && $recordB) ? ho_records_compare_businesses($recordA, $recordB) : null;

ho_admin_render_start(
    'records_module',
    'Records',
    'Sales',
    'Records <em>Repair</em>',
    'Search, inspect, and compare business records without raw table editing.'
);
?>

<section class="admin-records-hero">
  <p>Records lane</p>
  <h1>Repair bay, not the cockpit.</h1>
  <strong>Search records, find missing fields, inspect status clues, and compare duplicates. v135 does not merge or write data.</strong>
</section>

<section class="admin-lane-return-ribbon">
  <a href="/sales-portal-dashboard.php">← Production Lane</a>
  <span>This is a specialist surface opened from the lane.</span>
</section>


<?php if ($loadError): ?>
  <section class="admin-card admin-flash-card admin-flash-error"><?= ho_h($loadError) ?></section>
<?php endif; ?>

<section class="admin-records-search">
  <form method="get">
    <label>
      <span>Search records</span>
      <input name="q" value="<?= ho_h($query) ?>" placeholder="name, slug, city, category, website, email, phone">
    </label>
    <label>
      <span>Problem filter</span>
      <select name="filter">
        <?php foreach ($problemFilters as $key => $label): ?>
          <option value="<?= ho_h($key) ?>" <?= $filter === $key ? 'selected' : '' ?>><?= ho_h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="admin-records-main-button" type="submit">Search Records</button>
  </form>
</section>

<section class="admin-records-compare">
  <h2>Duplicate Review Helper</h2>
  <form method="get">
    <input type="hidden" name="q" value="<?= ho_h($query) ?>">
    <input type="hidden" name="filter" value="<?= ho_h($filter) ?>">
    <label>
      <span>Record A ID</span>
      <input name="compare_a" type="number" value="<?= ho_h((string)$compareA) ?>">
    </label>
    <label>
      <span>Record B ID</span>
      <input name="compare_b" type="number" value="<?= ho_h((string)$compareB) ?>">
    </label>
    <button class="admin-records-main-button is-secondary" type="submit">Compare Records</button>
  </form>
  <?php if ($comparison): ?>
    <div class="admin-records-comparison-result">
      <strong><?= ho_h(strtoupper((string)$comparison['level'])) ?> · score <?= ho_h((string)$comparison['score']) ?></strong>
      <span>Name similarity: <?= ho_h((string)$comparison['name_similarity']) ?>%</span>
      <ul>
        <?php foreach ($comparison['reasons'] as $reason): ?><li><?= ho_h($reason) ?></li><?php endforeach; ?>
      </ul>
      <p>No merge was performed. This is review guidance only.</p>
    </div>
  <?php elseif ($compareA || $compareB): ?>
    <div class="admin-records-comparison-result">
      <strong>Comparison incomplete</strong>
      <span>Enter two valid record IDs.</span>
    </div>
  <?php endif; ?>
</section>

<section class="admin-records-results-head">
  <h2><?= ho_h((string)count($results)) ?> records shown</h2>
  <p>Limited to 80 results. Use search/filter to narrow.</p>
</section>

<section class="admin-records-list">
  <?php if (!$results): ?>
    <div class="admin-empty-state">No records matched.</div>
  <?php else: ?>
    <?php foreach ($results as $business): ?>
      <?php ho_records_render_card($business, $businesses); ?>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<section class="admin-records-rules">
  <details>
    <summary>Records rules</summary>
    <ul>
      <li>Records is the repair bay, not the daily cockpit.</li>
      <li>No raw table editing in this module.</li>
      <li>No destructive writes in v135.</li>
      <li>No durable merge in v135.</li>
      <li>Same category or same city alone is not a duplicate.</li>
      <li>Use Inspect for deep record review.</li>
    </ul>
  </details>
</section>

<?php ho_admin_render_end(); ?>
