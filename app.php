<?php
declare(strict_types=1);
// deploy-test-1

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/ho-model.php';

// ─── POST handlers ────────────────────────────────────────────────────────────
$pdo     = null;
$dbError = null;

try {
    $pdo = ho_db();
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if ($pdo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    try {
        switch ($action) {

            case 'create_run':
                $catId = (int)($_POST['category_id'] ?? 0);
                $area  = trim((string)($_POST['area'] ?? ''));
                $count = max(5, min(50, (int)($_POST['count'] ?? 15)));
                if ($catId === 0 || $area === '') throw new RuntimeException('Category and area are required.');
                $category = ho_get_category($pdo, $catId);
                if (!$category) throw new RuntimeException('Category not found.');
                $exclusions = ho_get_known_business_names($pdo, $catId, $area);
                $runId = ho_create_source_run($pdo, $catId, $area, $count);
                header('Location: ?tab=source&run_id=' . $runId);
                exit;

            case 'import_sourcing':
                $runId   = (int)($_POST['run_id'] ?? 0);
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($runId === 0) throw new RuntimeException('Missing source run ID.');
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from ChatGPT.');
                $result   = ho_import_sourcing_json($pdo, $runId, $rawJson);
                $promoted = ho_promote_candidates($pdo, $runId);
                header('Location: ?tab=source&flash=' . urlencode("Imported {$result['imported']} leads ({$result['skipped']} skipped). {$promoted} added to pipeline."));
                exit;

            case 'import_research':
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from ChatGPT.');
                $result = ho_import_research_json($pdo, $rawJson);
                $msg    = "Updated {$result['updated']} businesses.";
                if (!empty($result['errors'])) $msg .= ' Issues: ' . implode('; ', $result['errors']);
                header('Location: ?tab=research&flash=' . urlencode($msg));
                exit;

            case 'mark_sent':
                $bizId   = (int)($_POST['business_id'] ?? 0);
                $sentVia = trim((string)($_POST['sent_via'] ?? 'email'));
                $sentTo  = trim((string)($_POST['sent_to'] ?? ''));
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_sent($pdo, $bizId, $sentVia, $sentTo);
                header('Location: ?tab=send&flash=' . urlencode('Marked as sent.'));
                exit;

            case 'exclude_business':
                $bizId  = (int)($_POST['business_id'] ?? 0);
                $reason = trim((string)($_POST['reason'] ?? 'franchise'));
                $addBl  = (bool)($_POST['add_blocklist'] ?? false);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_excluded($pdo, $bizId, $reason, $addBl);
                header('Location: ?tab=research&research_cat_id=' . (int)($_POST['research_cat_id'] ?? 0) . '&flash=' . urlencode('Business excluded.'));
                exit;
        }
    } catch (Throwable $e) {
        header('Location: ?tab=' . urlencode($_POST['tab'] ?? 'source') . '&error=' . urlencode($e->getMessage()));
        exit;
    }
}

// ─── Load state ───────────────────────────────────────────────────────────────
$tab      = trim((string)($_GET['tab']     ?? ''));
$runId    = (int)($_GET['run_id']           ?? 0);
$flashMsg = trim((string)($_GET['flash']   ?? ''));
$errorMsg = trim((string)($_GET['error']   ?? ''));

$counts = $pdo ? ho_pipeline_counts($pdo) : ['identified'=>0,'researched'=>0,'preview_ready'=>0,'pitched'=>0,'converted'=>0,'total'=>0];
$job    = ho_current_job($counts);
if ($tab === '') $tab = $job;

$categories    = $pdo ? ho_get_categories($pdo) : [];
$resCatId      = (int)($_GET['research_cat_id'] ?? 0);
$unresearched     = $pdo ? ho_get_unresearched_businesses($pdo, 25, $resCatId) : [];
$resCatCounts     = $pdo ? ho_unresearched_category_counts($pdo) : [];
$multiMarketIds   = $pdo && !empty($unresearched) ? ho_multi_market_ids($pdo, $unresearched) : [];
$sendQueue     = $pdo ? ho_get_preview_ready($pdo) : [];

$coverage = $pdo ? ho_source_coverage($pdo) : [];

$templatedCategories = array_values(array_filter($categories, function($cat) {
    $dir = ho_template_dir_for_slug((string)($cat['slug'] ?? ''));
    return $dir !== '';
}));

$cityToRegion = [];
foreach (ho_indiana_regions() as $region => $cities) {
    foreach (explode(',', $cities) as $city) {
        $cityToRegion[trim($city)] = $region;
    }
}

// Rebuild source prompt from active run
$activeRun    = null;
$sourcePrompt = '';
if ($runId > 0 && $pdo) {
    $s = $pdo->prepare("
        SELECT sr.*, c.name AS cat_name, c.typical_services
        FROM source_runs sr
        JOIN categories c ON c.id = sr.category_id
        WHERE sr.id = ?
    ");
    $s->execute([$runId]);
    $activeRun = $s->fetch() ?: null;
    if ($activeRun) {
        $catForPrompt = ['name' => $activeRun['cat_name'], 'typical_services' => $activeRun['typical_services']];
        $exclusions   = ho_get_known_business_names($pdo, (int)$activeRun['category_id'], (string)$activeRun['area_query']);
        $sourcePrompt = ho_generate_sourcing_prompt($catForPrompt, (string)$activeRun['area_query'], (int)$activeRun['target_count'], $exclusions);
    }
}

$researchPrompt = '';
if (!empty($unresearched)) {
    $researchPrompt = ho_generate_research_prompt($unresearched);
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,viewport-fit=cover">
  <title>Hoosier Online</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;900&family=Inter:wght@400;500;700&display=swap">
  <link rel="stylesheet" href="/assets/css/cockpit.css">
</head>
<body>

<header class="cp-topbar">
  <div class="cp-brand">HO</div>
  <div class="cp-telemetry">
    <span class="cp-stat<?= $counts['identified']    > 0 ? ' cp-hi' : '' ?>"><em><?= $counts['identified']    ?></em>ID</span>
    <span class="cp-stat<?= $counts['researched']    > 0 ? ' cp-hi' : '' ?>"><em><?= $counts['researched']    ?></em>RES</span>
    <span class="cp-stat<?= $counts['preview_ready'] > 0 ? ' cp-hot' : '' ?>"><em><?= $counts['preview_ready'] ?></em>RDY</span>
    <span class="cp-stat"><em><?= $counts['pitched']    ?></em>SENT</span>
    <span class="cp-stat cp-win"><em><?= $counts['converted']  ?></em>WIN</span>
  </div>
</header>

<nav class="cp-tabs">
  <a href="?tab=source"   class="cp-tab<?= $tab === 'source'   ? ' is-active' : '' ?>">Source</a>
  <a href="?tab=research" class="cp-tab<?= $tab === 'research' ? ' is-active' : '' ?>">
    Research<?= $counts['identified'] > 0 ? '<span class="cp-badge">' . $counts['identified'] . '</span>' : '' ?>
  </a>
  <a href="?tab=send" class="cp-tab<?= $tab === 'send' ? ' is-active' : '' ?>">
    Send<?= $counts['preview_ready'] > 0 ? '<span class="cp-badge cp-badge-hot">' . $counts['preview_ready'] . '</span>' : '' ?>
  </a>
</nav>

<main class="cp-main">

<?php if ($dbError): ?>
  <div class="cp-alert cp-alert-err">Database error: <?= ho_h($dbError) ?></div>
<?php endif; ?>

<?php if ($errorMsg !== ''): ?>
  <div class="cp-alert cp-alert-err"><?= ho_h($errorMsg) ?></div>
<?php endif; ?>

<?php if ($flashMsg !== ''): ?>
  <div class="cp-alert cp-alert-ok"><?= ho_h($flashMsg) ?></div>
<?php endif; ?>

<?php if ($job === $tab): ?>
  <div class="cp-job-flag">
    <?php if ($tab === 'source'): ?>Find leads<?php endif; ?>
    <?php if ($tab === 'research'): ?>Research leads<?php endif; ?>
    <?php if ($tab === 'send'): ?>Send pitches<?php endif; ?>
    &mdash; current job
  </div>
<?php endif; ?>

<!-- ═══ SOURCE ═══════════════════════════════════════════════════════════════ -->
<?php if ($tab === 'source'): ?>

  <?php if ($activeRun && $sourcePrompt !== ''): ?>

    <section class="cp-section">
      <div class="cp-step">Step 1</div>
      <h2 class="cp-sh">Copy this prompt</h2>
      <p class="cp-hint">Run #<?= $runId ?> &mdash; <?= ho_h((string)$activeRun['cat_name']) ?> in <?= ho_h((string)$activeRun['area_query']) ?></p>
      <div class="cp-prompt-box">
        <pre id="srcPrompt" class="cp-prompt"><?= ho_h($sourcePrompt) ?></pre>
        <button class="cp-copy" onclick="doCopy('srcPrompt',this)">Copy</button>
      </div>
    </section>

    <section class="cp-section">
      <div class="cp-step">Step 2</div>
      <h2 class="cp-sh">Paste ChatGPT result</h2>
      <form method="POST">
        <input type="hidden" name="action" value="import_sourcing">
        <input type="hidden" name="tab" value="source">
        <input type="hidden" name="run_id" value="<?= $runId ?>">
        <textarea class="cp-textarea" name="result_json" rows="7" placeholder='{"candidates":[{"raw_name":"…","city":"…","state":"IN",…}]}'></textarea>
        <button class="cp-btn-primary" type="submit">Import &amp; Add to Pipeline</button>
      </form>
      <a class="cp-back" href="?tab=source">← Start a new run</a>
    </section>

  <?php else: ?>

    <section class="cp-section">
      <h2 class="cp-sh">Find new leads</h2>
      <form method="POST">
        <input type="hidden" name="action" value="create_run">
        <input type="hidden" name="tab" value="source">
        <label class="cp-label">Category
          <select class="cp-select" name="category_id" required>
            <option value="">Choose…</option>
            <?php foreach ($templatedCategories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>"><?= ho_h((string)$cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php
          // Build region run-count across all categories so dropdown shows which are fresh
          $regionRunCount = [];
          foreach ($coverage as $row) {
              $r = (string)$row['area_query'];
              $regionRunCount[$r] = ($regionRunCount[$r] ?? 0) + (int)$row['run_count'];
          }
          $allRegionNames = array_keys(ho_indiana_regions());
          // Sort: unsourced first, then by run count ascending
          usort($allRegionNames, fn($a,$b) =>
              ($regionRunCount[$a] ?? 0) <=> ($regionRunCount[$b] ?? 0)
          );
        ?>
        <label class="cp-label">Region
          <select class="cp-select" name="area" required>
            <?php foreach ($allRegionNames as $region):
              $runs = $regionRunCount[$region] ?? 0;
              $label = $region . ($runs === 0 ? ' — NEW' : ' (' . $runs . ' run' . ($runs !== 1 ? 's' : '') . ')');
            ?>
              <option value="<?= ho_h($region) ?>"><?= ho_h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="cp-label">Count
          <input class="cp-input" type="number" name="count" value="25" min="5" max="50">
        </label>
        <button class="cp-btn-primary" type="submit">Generate Prompt</button>
      </form>
    </section>

  <?php endif; ?>

  <?php
  // ── Coverage map ────────────────────────────────────────────────────────
  $covMap = [];
  foreach ($coverage as $row) {
      $covMap[(string)$row['category_name']][(string)$row['area_query']] = $row;
  }
  $allRegions  = array_keys(ho_indiana_regions());
  $regionAbbr  = [
      'Indianapolis Metro'         => 'Indianapolis',
      'Fort Wayne Area'            => 'Fort Wayne',
      'South Bend / Mishawaka'     => 'South Bend',
      'Northwest Indiana'          => 'NW Indiana',
      'Evansville Area'            => 'Evansville',
      'Lafayette / West Lafayette' => 'Lafayette',
      'Bloomington Area'           => 'Bloomington',
      'Muncie / Anderson'          => 'Muncie',
      'Terre Haute Area'           => 'Terre Haute',
      'Kokomo / Logansport'        => 'Kokomo',
      'Columbus / Bartholomew'     => 'Columbus',
      'Richmond / East Central'    => 'Richmond',
      'Southern Indiana'           => 'Southern IN',
  ];
  $tplCatNames = array_column($templatedCategories, 'name');
  $showCats    = array_unique(array_merge(array_keys($covMap), $tplCatNames));
  sort($showCats);
  ?>
  <?php if (!empty($showCats)): ?>
  <section class="cp-section">
    <h2 class="cp-sh" style="font-size:13px;margin-bottom:10px;letter-spacing:.08em;">Region coverage</h2>

    <div class="cp-cov-key">
      <span class="cp-cov-pill cp-cov-active">Active</span>
      <span class="cp-cov-pill cp-cov-slowing">Slowing</span>
      <span class="cp-cov-pill cp-cov-low">Low</span>
      <span class="cp-cov-pill cp-cov-dry">Dry</span>
      <span class="cp-cov-key-note">= last run yield per region</span>
    </div>

    <?php foreach ($showCats as $catName):
      $regMap    = $covMap[$catName] ?? [];
      $totRuns   = (int)array_sum(array_column($regMap, 'run_count'));
      $totFound  = (int)array_sum(array_column($regMap, 'total_found'));
      $nRegions  = count($allRegions);
      $sourced   = count($regMap);
      $remaining = $nRegions - $sourced;
      $stCounts  = ['active'=>0,'slowing'=>0,'low'=>0,'dry'=>0];
      foreach ($regMap as $r) {
          $ly = (int)$r['last_yield'];
          if ($ly >= 10)     $stCounts['active']++;
          elseif ($ly >= 5)  $stCounts['slowing']++;
          elseif ($ly >= 1)  $stCounts['low']++;
          else               $stCounts['dry']++;
      }
    ?>
    <div class="cp-cov-card">
      <div class="cp-cov-card-head">
        <strong><?= ho_h($catName) ?></strong>
        <?php if ($totRuns > 0): ?>
          <span><?= $sourced ?>/<?= $nRegions ?> &middot; <?= $totFound ?> leads &middot; <?= $totRuns ?> run<?= $totRuns !== 1 ? 's' : '' ?></span>
        <?php else: ?>
          <span class="cp-cov-untouched">0/<?= $nRegions ?> &middot; not yet sourced</span>
        <?php endif; ?>
      </div>

      <div class="cp-cov-bar">
        <?php foreach (['active','slowing','low','dry'] as $st): if ($stCounts[$st] > 0): ?>
          <div class="cp-cov-bar-fill cp-cov-bar-<?= $st ?>" style="width:<?= round($stCounts[$st]/$nRegions*100) ?>%"></div>
        <?php endif; endforeach; ?>
      </div>

      <div class="cp-cov-regions">
        <?php if (empty($regMap)): ?>
          <span class="cp-cov-none"><?= $nRegions ?> regions available &mdash; no runs yet</span>
        <?php else: ?>
          <?php foreach ($regMap as $region => $row):
            $ly   = (int)$row['last_yield'];
            if ($ly >= 10)     $st = 'active';
            elseif ($ly >= 5)  $st = 'slowing';
            elseif ($ly >= 1)  $st = 'low';
            else               $st = 'dry';
            $abbr = $regionAbbr[$region] ?? $region;
          ?>
            <span class="cp-cov-pill cp-cov-<?= $st ?>" title="<?= ho_h($region) ?>">
              <?= ho_h($abbr) ?><em><?= $ly ?></em>
            </span>
          <?php endforeach; ?>
          <?php if ($remaining > 0): ?>
            <span class="cp-cov-more">+<?= $remaining ?> untouched</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

<!-- ═══ RESEARCH ════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'research'): ?>

  <?php if (!empty($resCatCounts)): ?>
  <div class="cp-cat-toggle">
    <?php $totalUnres = array_sum(array_column($resCatCounts, 'cnt')); ?>
    <a href="?tab=research" class="cp-cat-btn<?= $resCatId === 0 ? ' is-active' : '' ?>">All <span class="cp-badge"><?= $totalUnres ?></span></a>
    <?php foreach ($resCatCounts as $rc): ?>
    <a href="?tab=research&research_cat_id=<?= (int)$rc['id'] ?>" class="cp-cat-btn<?= $resCatId === (int)$rc['id'] ? ' is-active' : '' ?>"><?= ho_h((string)$rc['name']) ?> <span class="cp-badge"><?= (int)$rc['cnt'] ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($unresearched)): ?>
    <div class="cp-empty">No leads waiting for research<?= $resCatId > 0 ? ' in this category' : '' ?>. Source some first.</div>
  <?php else: ?>

    <section class="cp-section">
      <div class="cp-step">Step 1</div>
      <h2 class="cp-sh">Copy this prompt</h2>
      <p class="cp-hint"><?= count($unresearched) ?> businesses queued</p>
      <div class="cp-prompt-box">
        <pre id="resPrompt" class="cp-prompt"><?= ho_h($researchPrompt) ?></pre>
        <button class="cp-copy" onclick="doCopy('resPrompt',this)">Copy</button>
      </div>
    </section>

    <section class="cp-section">
      <div class="cp-step">Step 2</div>
      <h2 class="cp-sh">Paste ChatGPT result</h2>
      <form method="POST">
        <input type="hidden" name="action" value="import_research">
        <input type="hidden" name="tab" value="research">
        <textarea class="cp-textarea" name="result_json" rows="7" placeholder='{"research_results":[{"raw_name":"…",…}]}'></textarea>
        <button class="cp-btn-primary" type="submit">Import Research</button>
      </form>
    </section>

    <?php if (!empty($multiMarketIds)): ?>
    <div class="cp-alert cp-alert-warn">
      <strong><?= count($multiMarketIds) ?> multi-market flag<?= count($multiMarketIds) !== 1 ? 's' : '' ?></strong> &mdash; same business name appears in multiple cities. Review below &mdash; likely national franchises.
    </div>
    <?php endif; ?>

    <section class="cp-section">
      <h2 class="cp-sh" style="font-size:14px;">In this batch</h2>
      <?php
        // Sort: flagged businesses first
        $sortedBatch = $unresearched;
        usort($sortedBatch, fn($a,$b) =>
            in_array((int)$b['id'], $multiMarketIds, true) <=> in_array((int)$a['id'], $multiMarketIds, true)
        );
      ?>
      <ul class="cp-biz-list">
        <?php foreach ($sortedBatch as $b):
          $isMulti = in_array((int)$b['id'], $multiMarketIds, true);
        ?>
          <li class="cp-biz-row<?= $isMulti ? ' cp-biz-row-flagged' : '' ?>">
            <div class="cp-biz-info">
              <?php if ($isMulti): ?><span class="cp-multi-badge">MULTI-MARKET</span><?php endif; ?>
              <strong><?= ho_h((string)$b['business_name']) ?></strong>
              <span><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
            </div>
            <?php if ($isMulti): ?>
            <form method="POST" class="cp-exclude-form">
              <input type="hidden" name="action" value="exclude_business">
              <input type="hidden" name="tab" value="research">
              <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="research_cat_id" value="<?= $resCatId ?>">
              <input type="hidden" name="reason" value="franchise">
              <input type="hidden" name="add_blocklist" value="1">
              <button class="cp-btn-exclude" type="submit">Not Local</button>
            </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>

  <?php endif; ?>

<!-- ═══ SEND ════════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'send'): ?>

  <?php if (empty($sendQueue)): ?>
    <div class="cp-empty">No pitches ready. Finish research to generate previews.</div>
  <?php else: ?>

    <section class="cp-section">
      <div class="cp-send-filters">
        <select class="cp-select" id="filterCat" onchange="applyFilters()">
          <option value="">All categories</option>
          <?php
          $seenCats = [];
          foreach ($sendQueue as $b) {
              $cn = (string)$b['category_name'];
              if (!in_array($cn, $seenCats, true)) { $seenCats[] = $cn; ?>
          <option value="<?= ho_h($cn) ?>"><?= ho_h($cn) ?></option>
          <?php }} ?>
        </select>
        <select class="cp-select" id="filterRegion" onchange="applyFilters()">
          <option value="">All regions</option>
          <?php
          $seenRegions = [];
          foreach ($sendQueue as $b) {
              $reg = $cityToRegion[(string)$b['location_city']] ?? '';
              if ($reg !== '' && !in_array($reg, $seenRegions, true)) { $seenRegions[] = $reg; ?>
          <option value="<?= ho_h($reg) ?>"><?= ho_h($reg) ?></option>
          <?php }} ?>
        </select>
      </div>
      <h2 class="cp-sh" id="sendCount"><?= count($sendQueue) ?> ready to send</h2>
      <div class="cp-send-list" id="sendList">
        <?php foreach ($sendQueue as $b):
          $region = $cityToRegion[(string)$b['location_city']] ?? '';
        ?>
          <div class="cp-send-card" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>">
            <div class="cp-send-head">
              <div>
                <strong><?= ho_h((string)$b['business_name']) ?></strong>
                <span><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
              </div>
              <div class="cp-send-meta">
                <span class="cp-pkg cp-pkg-<?= ho_h((string)$b['package_recommendation']) ?>"><?= strtoupper((string)$b['package_recommendation']) ?></span>
                <?php if ((int)$b['view_count'] > 0): ?>
                  <span class="cp-view-count"><?= (int)$b['view_count'] ?> view<?= (int)$b['view_count'] !== 1 ? 's' : '' ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="cp-send-actions">
              <a class="cp-btn-ghost" href="/go/<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Preview ↗</a>
              <details class="cp-sent-wrap">
                <summary class="cp-btn-outline">Mark Sent</summary>
                <form method="POST" class="cp-sent-form">
                  <input type="hidden" name="action" value="mark_sent">
                  <input type="hidden" name="tab" value="send">
                  <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                  <select class="cp-select" name="sent_via">
                    <option value="email"<?= (string)$b['best_contact_method'] === 'email'    ? ' selected' : '' ?>>Email</option>
                    <option value="facebook_dm"<?= (string)$b['best_contact_method'] === 'facebook' ? ' selected' : '' ?>>Facebook DM</option>
                    <option value="phone"<?= (string)$b['best_contact_method'] === 'phone'    ? ' selected' : '' ?>>Phone</option>
                    <option value="website_form"<?= (string)$b['best_contact_method'] === 'website_form' ? ' selected' : '' ?>>Website Form</option>
                    <option value="other">Other</option>
                  </select>
                  <input class="cp-input" type="text" name="sent_to"
                    placeholder="email / handle / number"
                    value="<?= ho_h((string)($b['email_address'] ?: $b['phone_number'] ?: '')) ?>">
                  <button class="cp-btn-primary" type="submit">Confirm Sent</button>
                </form>
              </details>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

  <?php endif; ?>

<?php endif; ?>

</main>

<script>
function doCopy(id, btn) {
  var el = document.getElementById(id);
  if (!el) return;
  navigator.clipboard.writeText(el.textContent.trim()).then(function() {
    var orig = btn.textContent;
    btn.textContent = 'Copied!';
    setTimeout(function(){ btn.textContent = orig; }, 2000);
  });
}
function applyFilters() {
  var cat    = document.getElementById('filterCat')    ? document.getElementById('filterCat').value    : '';
  var region = document.getElementById('filterRegion') ? document.getElementById('filterRegion').value : '';
  var cards  = document.querySelectorAll('#sendList .cp-send-card');
  var visible = 0;
  cards.forEach(function(card) {
    var show = (!cat    || card.dataset.cat    === cat) &&
               (!region || card.dataset.region === region);
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  var h = document.getElementById('sendCount');
  if (h) h.textContent = visible + ' ready to send';
}
</script>

</body>
</html>
