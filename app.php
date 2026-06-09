<?php
declare(strict_types=1);
// deploy-test-1

require_once __DIR__ . '/../database.php';
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

            case 'import_contact_research':
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from ChatGPT.');
                $result = ho_import_contact_json($pdo, $rawJson);
                $msg    = "Updated {$result['updated']} businesses.";
                if (!empty($result['errors'])) $msg .= ' Issues: ' . implode('; ', $result['errors']);
                header('Location: ?tab=research&flash=' . urlencode($msg));
                exit;

            case 'exclude_business':
                $bizId  = (int)($_POST['business_id'] ?? 0);
                $reason = trim((string)($_POST['reason'] ?? 'franchise'));
                $addBl  = (bool)($_POST['add_blocklist'] ?? false);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_excluded($pdo, $bizId, $reason, $addBl);
                header('Location: ?tab=research&research_cat_id=' . (int)($_POST['research_cat_id'] ?? 0) . '&flash=' . urlencode('Business excluded.'));
                exit;

            case 'disqualify_lead':
                $bizId = (int)($_POST['business_id'] ?? 0);
                if ($bizId === 0) throw new RuntimeException('Business ID missing.');
                ho_mark_excluded($pdo, $bizId, 'not_a_fit');
                header('Location: ?tab=send&flash=' . urlencode('Lead removed.'));
                exit;

            case 'mark_outcome':
                $logId   = (int)($_POST['log_id']  ?? 0);
                $outcome = trim((string)($_POST['outcome'] ?? ''));
                if ($logId === 0) throw new RuntimeException('Log ID missing.');
                ho_mark_outcome($pdo, $logId, $outcome);
                header('Location: ?tab=send&flash=' . urlencode('Follow-up recorded.'));
                exit;

            case 'update_order':
                $orderId = (int)($_POST['order_id'] ?? 0);
                if ($orderId === 0) throw new RuntimeException('Order ID missing.');
                $allowed = ['domain_status','hosting_status','design_status','launch_status','customer_note','internal_note'];
                $updates = [];
                foreach ($allowed as $col) {
                    if (isset($_POST[$col])) $updates[$col] = $_POST[$col];
                }
                ho_update_order($pdo, $orderId, $updates);
                header('Location: ?tab=sales&flash=' . urlencode('Order updated.'));
                exit;

            case 'import_enrichment':
                $rawJson = trim((string)($_POST['result_json'] ?? ''));
                if ($rawJson === '') throw new RuntimeException('Paste the JSON result from ChatGPT.');
                $result = ho_import_enrichment_json($pdo, $rawJson);
                $msg    = "Enriched {$result['updated']} businesses.";
                if (!empty($result['errors'])) $msg .= ' Issues: ' . implode('; ', $result['errors']);
                header('Location: ?tab=research&flash=' . urlencode($msg));
                exit;

            case 'audit_websites':
                set_time_limit(180);
                $result = ho_audit_and_fix_websites($pdo);
                header('Location: ?tab=research&flash=' . urlencode(
                    "Website audit complete: {$result['live']} real sites confirmed, {$result['fixed']} bad records cleared, {$result['total']} total checked."
                ));
                exit;

            case 'reroute_decent_sites':
                set_time_limit(120);
                $rerouteRows = $pdo->query("
                    SELECT b.id, b.business_name, b.business_slug, b.location_city,
                           b.email_address, b.phone_number, b.facebook_url, b.website_url,
                           c.name AS category_name,
                           r.has_website, r.website_quality, r.booking_method,
                           r.has_angi, r.has_thumbtack, r.has_google_business,
                           r.mobile_friendly, r.has_ssl, r.gbp_photo_count,
                           r.last_review_date, r.google_review_count
                    FROM businesses b
                    JOIN categories c ON c.id = b.category_id
                    JOIN research_records r ON r.business_id = b.id
                    WHERE r.has_website = 1
                      AND r.website_quality IN ('decent','good')
                      AND b.pipeline_status IN ('preview_ready','researched','identified','needs_contact','excluded')
                ")->fetchAll();
                $reRouted = 0;
                foreach ($rerouteRows as $rRow) {
                    ho_route_to_enhancement($pdo, (int)$rRow['id'], $rRow);
                    $reRouted++;
                }
                header('Location: ?tab=research&flash=' . urlencode("Re-routed {$reRouted} decent-site lead(s) to the enhancement track."));
                exit;

            case 'requeue_no_contact':
                ho_requeue_no_contact_leads($pdo);
                $requeuedCount = ho_count_no_contact_ready($pdo); // should be 0 now
                header('Location: ?tab=send&flash=' . urlencode("No-contact leads moved back to the contact-research queue."));
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

$counts = $pdo ? ho_pipeline_counts($pdo) : ['identified'=>0,'researched'=>0,'preview_ready'=>0,'enhancement_ready'=>0,'pitched'=>0,'converted'=>0,'needs_contact'=>0,'excluded'=>0,'total'=>0];
$job    = ho_current_job($counts);
if ($tab === '') $tab = $job;

$categories    = $pdo ? ho_get_categories($pdo) : [];
$resCatId      = (int)($_GET['research_cat_id'] ?? 0);
$unresearched     = $pdo ? ho_get_unresearched_businesses($pdo, 25, $resCatId) : [];
$resCatCounts     = $pdo ? ho_unresearched_category_counts($pdo) : [];
$multiMarketIds   = $pdo && !empty($unresearched) ? ho_multi_market_ids($pdo, $unresearched) : [];
$needsContactBatch = $pdo ? ho_get_needs_contact_businesses($pdo, 20) : [];
$needsContactPrompt = !empty($needsContactBatch) ? ho_generate_contact_prompt($needsContactBatch) : '';
$dashboardData    = $pdo ? ho_dashboard_data($pdo) : ['categories'=>[],'region_leads'=>[]];
$enrichmentBatch  = $pdo ? ho_get_needs_enrichment($pdo, 50) : [];
$enrichmentPrompt = !empty($enrichmentBatch) ? ho_generate_enrichment_prompt($enrichmentBatch) : '';
$enrichmentTotal  = 0;
if ($pdo && !empty($enrichmentBatch)) {
    try {
        $enrichmentTotal = (int)$pdo->query("
            SELECT COUNT(*) FROM businesses b
            JOIN research_records r ON r.business_id = b.id
            WHERE r.research_status = 'complete'
              AND r.years_in_business IS NULL
        ")->fetchColumn();
    } catch (Throwable) {}
}
try { $sendQueue = $pdo ? ho_get_preview_ready($pdo) : []; } catch (Throwable $e) { $sendQueue = []; $dbError = $dbError ?? $e->getMessage(); }
try { $enhancementQueue = $pdo ? ho_get_enhancement_ready($pdo) : []; } catch (Throwable) { $enhancementQueue = []; }
$noContactStuckCount = $pdo ? ho_count_no_contact_ready($pdo) : 0;
try { $followupDue = $pdo ? ho_get_followup_due($pdo) : []; } catch (Throwable) { $followupDue = []; }
try { $pendingOrders = $pdo ? ho_get_pending_orders($pdo) : []; } catch (Throwable) { $pendingOrders = []; }

if (isset($_GET['demo']) && empty($pendingOrders)) {
    $pendingOrders = [[
        'id'              => 0,
        'business_name'   => 'Smith\'s Lawn Care',
        'location_city'   => 'New Castle',
        'owner_first_name'=> 'Tyler',
        'category_name'   => 'Lawn mowing',
        'package'         => 'standard',
        'template_key'    => 'lawn_mowing_clean',
        'chosen_domain'   => 'smithslawncare.com',
        'email_address'   => 'tyler@smithslawncare.com',
        'phone_number'    => '(765) 555-0192',
        'status_token'    => 'demo00000000',
        'domain_status'   => 'complete',
        'hosting_status'  => 'complete',
        'design_status'   => 'in_progress',
        'launch_status'   => 'pending',
        'customer_note'   => 'Domain registered! Starting the build today.',
        'internal_note'   => 'Porkbun login saved in 1Password. HostGator cPanel set up.',
        'paid_at'         => date('Y-m-d H:i:s', strtotime('-3 hours')),
    ]];
}

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
  <link rel="stylesheet" href="/assets/css/cockpit.css?v=<?= filemtime(__DIR__ . '/assets/css/cockpit.css') ?>">
</head>
<body>

<header class="cp-topbar">
  <div class="cp-brand">HO</div>
  <div class="cp-telemetry" onclick="openDash()" title="View dashboard">
    <span class="cp-stat<?= $counts['identified']    > 0 ? ' cp-hi' : '' ?>"><em><?= $counts['identified']    ?></em>LEADS</span>
    <span class="cp-stat<?= $counts['preview_ready'] > 0 ? ' cp-hot' : '' ?>"><em><?= $counts['preview_ready'] ?></em>READY</span>
    <span class="cp-stat<?= $counts['pitched']   > 0 ? ' cp-sent' : '' ?>"><em><?= $counts['pitched']   ?></em>SENT</span>
    <span class="cp-stat cp-win"><em><?= $counts['converted'] ?></em>WON</span>
  </div>
</header>

<nav class="cp-tabs">
  <a href="?tab=source"   class="cp-tab<?= $tab === 'source'   ? ' is-active' : '' ?>">Source</a>
  <a href="?tab=research" class="cp-tab<?= $tab === 'research' ? ' is-active' : '' ?>">
    Research<?= $counts['identified'] > 0 ? '<span class="cp-badge">' . $counts['identified'] . '</span>' : '' ?>
  </a>
  <?php $totalSend = ($counts['preview_ready'] ?? 0) + ($counts['enhancement_ready'] ?? 0); ?>
  <a href="?tab=send" class="cp-tab<?= $tab === 'send' ? ' is-active' : '' ?>">
    Send<?= $totalSend > 0 ? '<span class="cp-badge cp-badge-hot">' . $totalSend . '</span>' : '' ?>
  </a>
  <a href="?tab=sales" class="cp-tab<?= $tab === 'sales' ? ' is-active' : '' ?>">
    Sales<?= count($pendingOrders) > 0 ? '<span class="cp-badge cp-badge-win">' . count($pendingOrders) . '</span>' : '' ?>
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

  <?php if (!empty($needsContactBatch)): ?>
  <?php $ncTotal = $counts['needs_contact']; $ncBatch = count($needsContactBatch); ?>
  <section class="cp-section cp-section-contact">
    <div class="cp-step">Contact Research</div>
    <h2 class="cp-sh">Find contact info</h2>
    <p class="cp-hint">
      <?php if ($ncBatch < $ncTotal): ?>
        Showing <strong><?= $ncBatch ?></strong> of <strong><?= $ncTotal ?></strong> remaining &mdash; import these results, then reload to continue the next batch.
      <?php else: ?>
        <strong><?= $ncTotal ?></strong> business<?= $ncTotal !== 1 ? 'es' : '' ?> remaining &mdash; this is the full list.
      <?php endif; ?>
    </p>
    <?php if ($ncBatch < $ncTotal): ?>
    <div class="cp-nc-progress">
      <div class="cp-nc-bar" style="width:<?= round($ncBatch / $ncTotal * 100) ?>%"></div>
    </div>
    <?php endif; ?>
    <div class="cp-prompt-box">
      <pre id="contactPrompt" class="cp-prompt"><?= ho_h($needsContactPrompt) ?></pre>
      <button class="cp-copy" onclick="doCopy('contactPrompt',this)">Copy</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="import_contact_research">
      <input type="hidden" name="tab" value="research">
      <textarea class="cp-textarea" name="result_json" rows="5" placeholder='{"contacts":[{"raw_name":"…","email":"…","website_url":"…"}]}'></textarea>
      <button class="cp-btn-primary" type="submit">Import Contact Info</button>
    </form>
    <details style="margin-top:10px">
      <summary class="cp-hint" style="cursor:pointer">Show <?= count($needsContactBatch) ?> businesses</summary>
      <ul class="cp-biz-list" style="margin-top:8px">
        <?php foreach ($needsContactBatch as $b): ?>
          <li class="cp-biz-row">
            <div class="cp-biz-info">
              <strong><?= ho_h((string)$b['business_name']) ?></strong>
              <span><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  </section>
  <?php endif; ?>

  <!-- ── Enrichment queue ─────────────────────────────────────────────────── -->
  <?php if (!empty($enrichmentBatch)): ?>
  <section class="cp-section" style="margin-top:18px">
    <h2 class="cp-sh">Enrich existing leads</h2>
    <p class="cp-hint">
      <strong><?= $enrichmentTotal ?> lead<?= $enrichmentTotal !== 1 ? 's' : '' ?> still need enrichment</strong> &mdash;
      showing <?= count($enrichmentBatch) ?> in this batch.
      Missing fields: competitor, booking method, years in business, Angi/Thumbtack.
      Shorter prompt &mdash; won&rsquo;t overwrite existing research.
    </p>

    <div class="cp-step" style="margin-bottom:6px">Step 1</div>
    <h3 class="cp-sh" style="font-size:14px;margin-bottom:6px">Copy this prompt</h3>
    <div style="position:relative">
      <pre id="enrichPrompt" class="cp-prompt"><?= ho_h($enrichmentPrompt) ?></pre>
      <button class="cp-copy" onclick="doCopy('enrichPrompt',this)">Copy</button>
    </div>

    <div class="cp-step" style="margin:14px 0 6px">Step 2</div>
    <h3 class="cp-sh" style="font-size:14px;margin-bottom:6px">Paste ChatGPT&rsquo;s result</h3>
    <form method="POST">
      <input type="hidden" name="action" value="import_enrichment">
      <input type="hidden" name="tab"    value="research">
      <textarea name="result_json" class="cp-textarea" rows="6"
                placeholder='{"enrichment_results":[...]}'></textarea>
      <button type="submit" class="cp-btn" style="margin-top:8px">Import enrichment data</button>
    </form>
  </section>
  <?php endif; ?>

  <!-- ── Audit Tools (collapsed) ─────────────────────────────────────────── -->
  <?php
  $websiteBizIds = [];
  $noWebsiteIds  = [];
  try {
      if ($pdo) {
          $websiteBizIds = array_map('intval', $pdo->query("
              SELECT b.id FROM businesses b
              JOIN research_records r ON r.business_id = b.id
              WHERE r.has_website = 1 ORDER BY b.id ASC
          ")->fetchAll(PDO::FETCH_COLUMN));
          $noWebsiteIds = array_map('intval', $pdo->query("
              SELECT b.id FROM businesses b
              JOIN research_records r ON r.business_id = b.id
              WHERE r.has_website = 0
                AND r.research_status = 'complete'
                AND b.pipeline_status NOT IN ('excluded','converted','pitched')
              ORDER BY b.id ASC
          ")->fetchAll(PDO::FETCH_COLUMN));
      }
  } catch (Throwable) {}
  ?>
  <?php if (!empty($websiteBizIds) || !empty($noWebsiteIds)): ?>
  <details class="cp-section" style="margin-top:18px">
    <summary style="cursor:pointer;list-style:none;font-size:13px;color:#888;user-select:none">
      ▸ Audit tools
    </summary>

    <?php if (!empty($websiteBizIds)): ?>
    <div style="margin-top:14px" id="auditSection">
      <h3 class="cp-sh" style="font-size:14px">Website Data Audit</h3>
      <p class="cp-hint"><?= count($websiteBizIds) ?> lead<?= count($websiteBizIds) !== 1 ? 's' : '' ?> marked as having a website. Checks each URL live and clears bad AI guesses.</p>
      <button class="cp-btn" id="auditBtn" onclick="runAudit()">
        Scan &amp; fix <?= count($websiteBizIds) ?> website<?= count($websiteBizIds) !== 1 ? 's' : '' ?>
      </button>
      <div id="auditProgress" style="display:none;margin-top:12px">
        <div style="background:#e8e3d8;border-radius:6px;height:8px;overflow:hidden">
          <div id="auditBar" style="background:#2a7a35;height:100%;width:0;transition:width .2s"></div>
        </div>
        <p class="cp-hint" id="auditStatus" style="margin-top:6px">Starting…</p>
      </div>
    </div>
    <script>
    (function(){
      var ids = <?= json_encode($websiteBizIds) ?>;
      var total = ids.length, done = 0, fixed = 0, live = 0;
      window.runAudit = function() {
        document.getElementById('auditBtn').disabled = true;
        document.getElementById('auditProgress').style.display = 'block';
        processNext(0);
      };
      function processNext(i) {
        if (i >= ids.length) {
          document.getElementById('auditBar').style.width = '100%';
          document.getElementById('auditStatus').textContent =
            'Done. ' + live + ' real site' + (live !== 1 ? 's' : '') + ' confirmed, ' +
            fixed + ' bad record' + (fixed !== 1 ? 's' : '') + ' cleared.';
          document.getElementById('auditBtn').textContent = 'Run again';
          document.getElementById('auditBtn').disabled = false;
          return;
        }
        var fd = new FormData();
        fd.append('id', ids[i]);
        fetch('/audit-url.php', {method:'POST', body:fd})
          .then(function(r){ return r.json(); })
          .then(function(d){
            done++;
            if (d.fixed) fixed++;
            else if (d.alive) live++;
            var pct = Math.round(done / total * 100);
            document.getElementById('auditBar').style.width = pct + '%';
            document.getElementById('auditStatus').textContent =
              done + ' of ' + total + ' checked — ' + fixed + ' cleared so far';
          })
          .catch(function(){done++;})
          .finally(function(){ processNext(i + 1); });
      }
    })();
    </script>
    <?php endif; ?>

    <?php if (!empty($noWebsiteIds)): ?>
    <div style="margin-top:14px" id="domainAuditSection">
      <h3 class="cp-sh" style="font-size:14px">Hidden Website Check</h3>
      <p class="cp-hint"><?= count($noWebsiteIds) ?> lead<?= count($noWebsiteIds) !== 1 ? 's' : '' ?> marked as no website. Tries their likely .com — auto-routes anyone the AI missed.</p>
      <button class="cp-btn" id="domainAuditBtn" onclick="runDomainAudit()">
        Check <?= count($noWebsiteIds) ?> lead<?= count($noWebsiteIds) !== 1 ? 's' : '' ?> for hidden websites
      </button>
      <div id="domainAuditProgress" style="display:none;margin-top:12px">
        <div style="background:#e8e3d8;border-radius:6px;height:8px;overflow:hidden">
          <div id="domainAuditBar" style="background:#2a7a35;height:100%;width:0;transition:width .2s"></div>
        </div>
        <p class="cp-hint" id="domainAuditStatus" style="margin-top:6px">Starting…</p>
      </div>
    </div>
    <script>
    (function(){
      var ids = <?= json_encode($noWebsiteIds) ?>;
      var total = ids.length, done = 0, found = 0, excluded = 0;
      window.runDomainAudit = function() {
        document.getElementById('domainAuditBtn').disabled = true;
        document.getElementById('domainAuditProgress').style.display = 'block';
        domainNext(0);
      };
      function domainNext(i) {
        if (i >= ids.length) {
          document.getElementById('domainAuditBar').style.width = '100%';
          document.getElementById('domainAuditStatus').textContent =
            'Done. ' + found + ' hidden site' + (found !== 1 ? 's' : '') + ' found, ' +
            excluded + ' lead' + (excluded !== 1 ? 's' : '') + ' auto-removed.';
          document.getElementById('domainAuditBtn').textContent = 'Run again';
          document.getElementById('domainAuditBtn').disabled = false;
          return;
        }
        var fd = new FormData();
        fd.append('id', ids[i]);
        fetch('/audit-domain.php', {method:'POST', body:fd})
          .then(function(r){ return r.json(); })
          .then(function(d){
            done++;
            if (d.alive) found++;
            if (d.excluded) excluded++;
            var pct = Math.round(done / total * 100);
            document.getElementById('domainAuditBar').style.width = pct + '%';
            document.getElementById('domainAuditStatus').textContent =
              done + ' of ' + total + ' checked — ' + found + ' found, ' + excluded + ' removed';
          })
          .catch(function(){ done++; })
          .finally(function(){ domainNext(i + 1); });
      }
    })();
    </script>
    <?php endif; ?>

  </details>
  <?php endif; ?>

<!-- ═══ SEND ════════════════════════════════════════════════════════════════ -->
<?php elseif ($tab === 'send'): ?>

  <?php if (!empty($followupDue)): ?>
    <section class="cp-section">
      <details class="cp-followup-wrap" open>
        <summary class="cp-followup-summary">
          <span class="cp-followup-badge"><?= count($followupDue) ?></span>
          Follow-up<?= count($followupDue) !== 1 ? 's' : '' ?> due
        </summary>
        <?php foreach ($followupDue as $fu):
          $sentDaysAgo = (int)floor((time() - strtotime((string)$fu['sent_at'])) / 86400);
          $previewHref = (string)$fu['preview_slug'] !== '' ? '/go/' . ho_h((string)$fu['preview_slug']) : '';
        ?>
        <div class="cp-followup-card">
          <div class="cp-followup-head">
            <strong><?= ho_h((string)$fu['business_name']) ?></strong>
            <span><?= ho_h((string)$fu['location_city']) ?> &middot; sent <?= $sentDaysAgo ?> day<?= $sentDaysAgo !== 1 ? 's' : '' ?> ago</span>
          </div>
          <div class="cp-followup-actions">
            <form method="POST" style="display:contents">
              <input type="hidden" name="action" value="mark_outcome">
              <input type="hidden" name="tab" value="send">
              <input type="hidden" name="log_id" value="<?= (int)$fu['log_id'] ?>">
              <button class="cp-btn-outcome cp-btn-outcome-yes" name="outcome" value="interested" type="submit">Interested</button>
              <button class="cp-btn-outcome cp-btn-outcome-no" name="outcome" value="no_response" type="submit">No Reply</button>
              <button class="cp-btn-outcome cp-btn-outcome-pass" name="outcome" value="not_interested" type="submit">Not Interested</button>
            </form>
            <?php if ($previewHref !== ''): ?>
              <a class="cp-btn-ghost" href="<?= $previewHref ?>" target="_blank">Preview ↗</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </details>
    </section>
  <?php endif; ?>

  <?php if ($noContactStuckCount > 0): ?>
  <div class="cp-notice cp-notice-warn" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 16px;background:rgba(184,112,32,.1);border:1.5px solid rgba(184,112,32,.3);border-radius:10px;margin-bottom:12px">
    <span style="font-size:14px;font-weight:600;color:#7a4800"><?= $noContactStuckCount ?> lead<?= $noContactStuckCount !== 1 ? 's' : '' ?> in the queue with no contact info — hidden until re-queued.</span>
    <form method="POST" style="margin:0">
      <input type="hidden" name="action" value="requeue_no_contact">
      <input type="hidden" name="tab" value="send">
      <button class="cp-btn-outline" type="submit" style="font-size:13px">Re-queue for contact research &rarr;</button>
    </form>
  </div>
  <?php endif; ?>

  <?php if (empty($sendQueue) && empty($enhancementQueue)): ?>
    <div class="cp-empty">No pitches ready. Finish research to generate previews.</div>
  <?php else: ?>

    <section class="cp-section">
      <?php $allSendable = array_merge($sendQueue, $enhancementQueue); ?>
      <div class="cp-send-filters">
        <select class="cp-select" id="filterCat" onchange="applyFilters()">
          <option value="">All categories</option>
          <?php
          $seenCats = [];
          foreach ($allSendable as $b) {
              $cn = (string)$b['category_name'];
              if (!in_array($cn, $seenCats, true)) { $seenCats[] = $cn; ?>
          <option value="<?= ho_h($cn) ?>"><?= ho_h($cn) ?></option>
          <?php }} ?>
        </select>
        <select class="cp-select" id="filterRegion" onchange="applyFilters()">
          <option value="">All regions</option>
          <?php
          $seenRegions = [];
          foreach ($allSendable as $b) {
              $reg = $cityToRegion[(string)$b['location_city']] ?? '';
              if ($reg !== '' && !in_array($reg, $seenRegions, true)) { $seenRegions[] = $reg; ?>
          <option value="<?= ho_h($reg) ?>"><?= ho_h($reg) ?></option>
          <?php }} ?>
        </select>
      </div>
      <h2 class="cp-sh" id="sendCount"><?= count($allSendable) ?> ready to send</h2>
      <?php
        // Partition: email/website first, phone/FB-only second
        $sendPrimary   = [];
        $sendSecondary = [];
        foreach ($sendQueue as $b) {
            if ((string)$b['email_address'] !== '' || (string)$b['website_url'] !== '') {
                $sendPrimary[] = $b;
            } else {
                $sendSecondary[] = $b;
            }
        }
      ?>
      <div class="cp-send-list" id="sendList">
        <?php foreach ($sendPrimary as $b):
          $region     = $cityToRegion[(string)$b['location_city']] ?? '';
          $previewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/go/' . $b['business_slug'];
          $hasEmail   = (string)$b['email_address'] !== '';
          $_siteUrl   = (string)$b['website_url'];
          $hasSiteUrl = $_siteUrl !== '' && !ho_is_lead_platform_url($_siteUrl);
          $hasFb      = (string)$b['facebook_url']  !== '';
          $hasPhone   = (string)$b['phone_number']  !== '';
          $method     = (string)$b['best_contact_method'];
        ?>
          <?php
            $accentCls = match(true) {
              $hasEmail  => 'cp-send-card-email',
              $hasFb     => 'cp-send-card-fb',
              $hasPhone  => 'cp-send-card-phone',
              default    => 'cp-send-card-none',
            };
          ?>
          <?php
            $score       = (int)$b['fit_score'];
            $scoreCls    = $score >= 5 ? 'green' : ($score >= 3 ? 'amber' : 'grey');
            $viewCount   = (int)$b['view_count'];
            $lastViewed  = (string)($b['last_viewed_at'] ?? '');
            $viewedDaysAgo = $lastViewed !== '' ? (int)floor((time() - strtotime($lastViewed)) / 86400) : null;
            $opp         = trim((string)($b['opportunity_summary'] ?? ''));
            $siteQual    = (string)($b['website_quality'] ?? '');
            $hasSite     = (bool)($b['has_website'] ?? false);
            $gReviews    = (int)($b['google_review_count'] ?? 0);
            $gRating     = (float)($b['google_rating'] ?? 0);
            $fbActivity  = (string)($b['facebook_activity'] ?? '');
          ?>
          <div class="cp-send-card <?= $accentCls ?>" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>">

            <div class="cp-send-head">
              <strong><?= ho_h((string)$b['business_name']) ?></strong>
              <span class="cp-send-sub"><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
            </div>

            <div class="cp-card-badges">
              <span class="cp-pkg cp-pkg-<?= ho_h((string)$b['package_recommendation']) ?>"><?= strtoupper((string)$b['package_recommendation']) ?></span>
              <span class="cp-score cp-score-<?= $scoreCls ?>">fit&nbsp;<?= $score ?></span>
              <?php if ($viewCount > 0): ?>
                <span class="cp-view-count">
                  <?= $viewCount ?> view<?= $viewCount !== 1 ? 's' : '' ?>
                  <?php if ($viewedDaysAgo !== null): ?>
                    &middot; <?= $viewedDaysAgo === 0 ? 'today' : $viewedDaysAgo . 'd ago' ?>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
            </div>

            <div class="cp-send-primary">
              <?php if ($hasEmail): ?>
                <a class="cp-btn-send cp-btn-send-email" href="<?= ho_h(ho_pitch_mailto($b, $previewUrl)) ?>">
                  ✉&thinsp; Email <?= ho_h((string)$b['business_name']) ?>
                </a>
              <?php elseif ($hasFb): ?>
                <a class="cp-btn-send cp-btn-send-fb" href="<?= ho_h((string)$b['facebook_url']) ?>" target="_blank" rel="noopener">
                  Message on Facebook →
                </a>
              <?php elseif ($hasSiteUrl): ?>
                <a class="cp-btn-send cp-btn-send-web" href="<?= ho_h((string)$b['website_url']) ?>" target="_blank" rel="noopener">
                  Contact via Website →
                </a>
              <?php elseif ($hasPhone): ?>
                <a class="cp-btn-send cp-btn-send-phone" href="tel:<?= ho_h((string)$b['phone_number']) ?>">
                  Call <?= ho_h((string)$b['phone_number']) ?>
                </a>
              <?php else: ?>
                <span class="cp-send-no-contact">No contact info on file</span>
              <?php endif; ?>
            </div>

            <div class="cp-send-secondary">
              <a class="cp-btn-ghost" href="/go/<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Preview ↗</a>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $b['business_name'] . '" ' . $b['location_city'] . ' Indiana')) ?>" target="_blank" title="Verify on Google">Verify ↗</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove this lead as not a fit?')">
                <input type="hidden" name="action" value="disqualify_lead">
                <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="cp-btn-ghost cp-btn-disqualify">Not a fit ✕</button>
              </form>
              <details class="cp-research-wrap">
                <summary class="cp-btn-ghost">Research</summary>
                <div class="cp-research-panel">
                  <?php if ($opp !== ''): ?>
                    <p class="cp-research-opp"><?= ho_h($opp) ?></p>
                  <?php endif; ?>
                  <div class="cp-research-row">
                    <span class="cp-research-label">Website</span>
                    <span><?= $hasSite ? ho_h($siteQual ?: 'exists') : 'none' ?></span>
                  </div>
                  <?php if ($gReviews > 0): ?>
                  <div class="cp-research-row">
                    <span class="cp-research-label">Google</span>
                    <span><?= $gReviews ?> reviews<?= $gRating > 0 ? ', ' . number_format($gRating, 1) . '★' : '' ?></span>
                  </div>
                  <?php endif; ?>
                  <?php if ($fbActivity !== ''): ?>
                  <div class="cp-research-row">
                    <span class="cp-research-label">Facebook</span>
                    <span><?= ho_h($fbActivity) ?></span>
                  </div>
                  <?php endif; ?>
                </div>
              </details>
              <details class="cp-sent-wrap">
                <summary class="cp-btn-outline">Mark Sent</summary>
                <form method="POST" class="cp-sent-form">
                  <input type="hidden" name="action" value="mark_sent">
                  <input type="hidden" name="tab" value="send">
                  <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                  <select class="cp-select" name="sent_via">
                    <option value="email"<?=       $method === 'email'        ? ' selected' : '' ?>>Email</option>
                    <option value="facebook_dm"<?= $method === 'facebook'     ? ' selected' : '' ?>>Facebook DM</option>
                    <option value="phone"<?=        $method === 'phone'        ? ' selected' : '' ?>>Phone</option>
                    <option value="website_form"<?= $method === 'website_form' ? ' selected' : '' ?>>Website Form</option>
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

        <?php if (!empty($sendSecondary)): ?>
        <details class="cp-send-later">
          <summary>
            <?= count($sendSecondary) ?> more lead<?= count($sendSecondary) !== 1 ? 's' : '' ?> — phone &amp; social only
          </summary>
          <?php foreach ($sendSecondary as $b):
            $region     = $cityToRegion[(string)$b['location_city']] ?? '';
            $previewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/go/' . $b['business_slug'];
            $hasFb      = (string)$b['facebook_url'] !== '';
            $hasPhone   = (string)$b['phone_number'] !== '';
            $method     = (string)$b['best_contact_method'];
            $accentCls2 = $hasFb ? 'cp-send-card-fb' : ($hasPhone ? 'cp-send-card-phone' : 'cp-send-card-none');
          ?>
            <div class="cp-send-card <?= $accentCls2 ?>" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>">
              <div class="cp-send-head">
                <strong><?= ho_h((string)$b['business_name']) ?></strong>
                <span class="cp-send-sub"><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
              </div>
              <div class="cp-card-badges">
                <span class="cp-pkg cp-pkg-<?= ho_h((string)$b['package_recommendation']) ?>"><?= strtoupper((string)$b['package_recommendation']) ?></span>
              </div>
              <div class="cp-send-primary">
                <?php if ($hasFb): ?>
                  <a class="cp-btn-send cp-btn-send-fb" href="<?= ho_h((string)$b['facebook_url']) ?>" target="_blank" rel="noopener">Message on Facebook →</a>
                <?php elseif ($hasPhone): ?>
                  <a class="cp-btn-send cp-btn-send-phone" href="tel:<?= ho_h((string)$b['phone_number']) ?>">Call <?= ho_h((string)$b['phone_number']) ?></a>
                <?php endif; ?>
              </div>
              <div class="cp-send-secondary">
                <a class="cp-btn-ghost" href="/go/<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Preview ↗</a>
                <details class="cp-sent-wrap">
                  <summary class="cp-btn-outline">Mark Sent</summary>
                  <form method="POST" class="cp-sent-form">
                    <input type="hidden" name="action" value="mark_sent">
                    <input type="hidden" name="tab" value="send">
                    <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                    <select class="cp-select" name="sent_via">
                      <option value="facebook_dm"<?= $method === 'facebook' ? ' selected' : '' ?>>Facebook DM</option>
                      <option value="phone"<?= $method === 'phone' ? ' selected' : '' ?>>Phone</option>
                      <option value="other">Other</option>
                    </select>
                    <input class="cp-input" type="text" name="sent_to" placeholder="handle / number" value="<?= ho_h((string)($b['facebook_url'] ?: $b['phone_number'] ?: '')) ?>">
                    <button class="cp-btn-primary" type="submit">Confirm Sent</button>
                  </form>
                </details>
              </div>
            </div>
          <?php endforeach; ?>
        </details>
        <?php endif; ?>

        <?php if (!empty($enhancementQueue)):
          $gapLabels = [
              'contact_form'    => 'No contact form',
              'paid_leads'      => 'Paying Angi/Thumbtack',
              'google_business' => 'No Google Business',
              'tech_issues'     => 'Mobile/SSL issues',
              'gbp_photos'      => 'Low GBP photos',
              'stale_reviews'   => 'Stale reviews',
          ];
          foreach ($enhancementQueue as $b):
            $region     = $cityToRegion[(string)$b['location_city']] ?? '';
            $previewUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/go/' . $b['business_slug'];
            $hasEmail   = (string)$b['email_address'] !== '';
            $_siteUrl   = (string)$b['website_url'];
            $hasSiteUrl = $_siteUrl !== '' && !ho_is_lead_platform_url($_siteUrl);
            $hasFb      = (string)$b['facebook_url']  !== '';
            $hasPhone   = (string)$b['phone_number']  !== '';
            $method     = (string)$b['best_contact_method'];
            $eGaps      = (array)$b['enhancement_gaps'];
        ?>
          <div class="cp-send-card cp-send-card-enhance" data-cat="<?= ho_h((string)$b['category_name']) ?>" data-region="<?= ho_h($region) ?>">

            <div class="cp-send-head">
              <strong><?= ho_h((string)$b['business_name']) ?></strong>
              <span class="cp-send-sub"><?= ho_h((string)$b['category_name']) ?> &middot; <?= ho_h((string)$b['location_city']) ?></span>
            </div>

            <?php if (!empty($eGaps)): ?>
            <div class="cp-card-badges" style="flex-wrap:wrap;gap:4px">
              <?php foreach ($eGaps as $gk): ?>
                <span class="cp-gap-badge"><?= ho_h($gapLabels[$gk] ?? $gk) ?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="cp-send-primary">
              <?php if ($hasEmail): ?>
                <a class="cp-btn-send cp-btn-send-email" href="<?= ho_h(ho_pitch_mailto_enhancement($b, $previewUrl)) ?>">
                  ✉&thinsp; Email <?= ho_h((string)$b['business_name']) ?>
                </a>
              <?php elseif ($hasFb): ?>
                <a class="cp-btn-send cp-btn-send-fb" href="<?= ho_h((string)$b['facebook_url']) ?>" target="_blank" rel="noopener">Message on Facebook →</a>
              <?php elseif ($hasSiteUrl): ?>
                <a class="cp-btn-send cp-btn-send-web" href="<?= ho_h((string)$b['website_url']) ?>" target="_blank" rel="noopener">Contact via Website →</a>
              <?php elseif ($hasPhone): ?>
                <a class="cp-btn-send cp-btn-send-phone" href="tel:<?= ho_h((string)$b['phone_number']) ?>">Call <?= ho_h((string)$b['phone_number']) ?></a>
              <?php else: ?>
                <span class="cp-send-no-contact">No contact info on file</span>
              <?php endif; ?>
            </div>

            <div class="cp-send-secondary">
              <a class="cp-btn-ghost" href="/go/<?= ho_h((string)$b['business_slug']) ?>" target="_blank">Preview ↗</a>
              <a class="cp-btn-ghost" href="<?= ho_h('https://www.google.com/search?q=' . rawurlencode('"' . $b['business_name'] . '" ' . $b['location_city'] . ' Indiana')) ?>" target="_blank" title="Verify on Google">Verify ↗</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Remove this lead as not a fit?')">
                <input type="hidden" name="action" value="disqualify_lead">
                <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="cp-btn-ghost cp-btn-disqualify">Not a fit ✕</button>
              </form>
              <details class="cp-sent-wrap">
                <summary class="cp-btn-outline">Mark Sent</summary>
                <form method="POST" class="cp-sent-form">
                  <input type="hidden" name="action" value="mark_sent">
                  <input type="hidden" name="tab" value="send">
                  <input type="hidden" name="business_id" value="<?= (int)$b['id'] ?>">
                  <select class="cp-select" name="sent_via">
                    <option value="email"<?= $method === 'email' ? ' selected' : '' ?>>Email</option>
                    <option value="facebook_dm"<?= $method === 'facebook' ? ' selected' : '' ?>>Facebook DM</option>
                    <option value="phone"<?= $method === 'phone' ? ' selected' : '' ?>>Phone</option>
                    <option value="website_form"<?= $method === 'website_form' ? ' selected' : '' ?>>Website Form</option>
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
        <?php endforeach; endif; ?>

      </div>
    </section>

  <?php endif; ?>

<?php elseif ($tab === 'sales'): ?>

<!-- ═══ SALES ═══════════════════════════════════════════════════════════════ -->

<?php if (empty($pendingOrders)): ?>
  <div class="cp-alert cp-alert-ok" style="margin-top:20px">No active orders — sales show here after customers pay.</div>
<?php else: ?>
  <div class="cp-section-head" style="margin-bottom:16px">
    <strong><?= count($pendingOrders) ?> active order<?= count($pendingOrders) !== 1 ? 's' : '' ?></strong>
  </div>
  <?php foreach ($pendingOrders as $o):
    $oId        = (int)$o['id'];
    $oBiz       = (string)$o['business_name'];
    $oCity      = (string)$o['location_city'];
    $oFirst     = trim((string)($o['owner_first_name'] ?? ''));
    $oPkg       = (string)$o['package'];
    $oTpl       = (string)$o['template_key'];
    $oDomain    = (string)$o['chosen_domain'];
    $oEmail     = (string)($o['email_address'] ?? '');
    $oPhone     = (string)($o['phone_number']  ?? '');
    $oCat       = (string)$o['category_name'];
    $oNote      = (string)($o['customer_note']  ?? '');
    $oInternal  = (string)($o['internal_note']  ?? '');
    $oToken     = (string)$o['status_token'];
    $oStatusUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/status.php?token=' . $oToken;
    $oPaidAt    = (string)($o['paid_at'] ?? '');
    $hoursAgo   = $oPaidAt !== '' ? round((time() - strtotime($oPaidAt)) / 3600, 1) : 0;

    $statuses    = ['domain_status','hosting_status','design_status','launch_status'];
    $statLabels  = ['domain_status' => 'Domain','hosting_status' => 'Hosting','design_status' => 'Site build','launch_status' => 'Launch'];
    $statOpts    = ['pending' => 'Pending','in_progress' => 'In progress','complete' => 'Complete'];

    $updateMsg = ho_generate_status_update_text($o, $oBiz, $oFirst);
  ?>
  <div class="cp-order-card">
    <div class="cp-order-head">
      <div>
        <strong class="cp-order-biz"><?= ho_h($oBiz) ?></strong>
        <span class="cp-order-meta"><?= ho_h($oCity) ?> &middot; <?= ho_h($oCat) ?> &middot; paid <?= ho_h((string)$hoursAgo) ?>h ago</span>
      </div>
      <span class="cp-pkg cp-pkg-<?= ho_h($oPkg) ?>"><?= ho_h($oPkg) ?></span>
    </div>

    <div class="cp-order-specs">
      <?php if ($oDomain !== ''): ?><div class="cp-order-spec"><span>Domain</span><strong><?= ho_h($oDomain) ?></strong></div><?php endif; ?>
      <?php if ($oTpl   !== ''): ?><div class="cp-order-spec"><span>Design</span><strong><?= ho_h($oTpl) ?></strong></div><?php endif; ?>
      <?php if ($oEmail !== ''): ?><div class="cp-order-spec"><span>Email</span><a href="mailto:<?= ho_h($oEmail) ?>"><?= ho_h($oEmail) ?></a></div><?php endif; ?>
      <?php if ($oPhone !== ''): ?><div class="cp-order-spec"><span>Phone</span><a href="tel:<?= ho_h(preg_replace('/\D/','',$oPhone)) ?>"><?= ho_h($oPhone) ?></a></div><?php endif; ?>
    </div>

    <form method="POST" action="?tab=sales" class="cp-order-status-form">
      <input type="hidden" name="action"   value="update_order">
      <input type="hidden" name="order_id" value="<?= $oId ?>">
      <div class="cp-order-statuses">
        <?php foreach ($statuses as $sc): ?>
        <label class="cp-order-stat-label"><?= ho_h($statLabels[$sc]) ?>
          <select name="<?= $sc ?>" class="cp-select cp-order-stat-sel" onchange="this.form.submit()">
            <?php foreach ($statOpts as $sv => $sl): ?>
              <option value="<?= $sv ?>"<?= ($o[$sc] ?? 'pending') === $sv ? ' selected' : '' ?>><?= $sl ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php endforeach; ?>
      </div>
    </form>

    <div class="cp-order-notes">
      <form method="POST" action="?tab=sales" class="cp-order-note-form">
        <input type="hidden" name="action"   value="update_order">
        <input type="hidden" name="order_id" value="<?= $oId ?>">
        <label class="cp-label">Customer note <span class="cp-order-note-hint">(shown on their status page)</span>
          <textarea name="customer_note" class="cp-textarea cp-order-note-area" rows="2" placeholder="e.g. Domain is registered, starting build now..."><?= ho_h($oNote) ?></textarea>
        </label>
        <label class="cp-label" style="margin-top:8px">Internal note <span class="cp-order-note-hint">(Adam only)</span>
          <textarea name="internal_note" class="cp-textarea cp-order-note-area" rows="2" placeholder="e.g. Registrar login saved in 1Password..."><?= ho_h($oInternal) ?></textarea>
        </label>
        <button type="submit" class="cp-btn-ghost" style="margin-top:6px">Save notes</button>
      </form>
    </div>

    <div class="cp-order-footer">
      <a href="<?= ho_h($oStatusUrl) ?>" target="_blank" class="cp-btn-ghost cp-order-status-link">View customer status page &rarr;</a>
      <button class="cp-btn-ghost" onclick="this.nextElementSibling.hidden=!this.nextElementSibling.hidden">Generate update &darr;</button>
      <div class="cp-order-update-box" hidden>
        <p class="cp-label">Copy this and send to the customer:</p>
        <textarea class="cp-textarea cp-order-update-area" rows="12" readonly onclick="this.select()"><?= ho_h($updateMsg) ?></textarea>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>

</main>

<!-- ═══ DASHBOARD MODAL ══════════════════════════════════════════════════════ -->
<div id="cpDash" class="cp-dash" hidden aria-modal="true">
  <div class="cp-dash-backdrop" onclick="closeDash()"></div>
  <div class="cp-dash-panel">
    <div class="cp-dash-hd">
      <span class="cp-dash-title">Pipeline Dashboard</span>
      <button class="cp-dash-close" onclick="closeDash()">✕</button>
    </div>
    <div class="cp-dash-tabs" role="tablist">
      <button class="cp-dash-tab is-active" onclick="dashTab('overview',this)">Overview</button>
      <button class="cp-dash-tab" onclick="dashTab('map',this)">Map</button>
      <button class="cp-dash-tab" onclick="dashTab('cats',this)">Categories</button>
    </div>
    <div class="cp-dash-body">

      <!-- Overview -->
      <div id="dashOverview" class="cp-dash-pane">
        <div class="cp-dash-kpis">
          <div class="cp-kpi"><em><?= $counts['total'] ?></em><span>Total leads</span></div>
          <div class="cp-kpi cp-kpi-hot"><em><?= $counts['preview_ready'] ?></em><span>Ready to send</span></div>
          <div class="cp-kpi"><em><?= $counts['pitched'] ?></em><span>Sent</span></div>
          <div class="cp-kpi cp-kpi-win"><em><?= $counts['converted'] ?></em><span>Won</span></div>
          <?php if ($counts['needs_contact'] > 0): ?>
          <div class="cp-kpi cp-kpi-warn"><em><?= $counts['needs_contact'] ?></em><span>Need contact</span></div>
          <?php endif; ?>
          <?php if ($counts['excluded'] > 0): ?>
          <div class="cp-kpi cp-kpi-mute"><em><?= $counts['excluded'] ?></em><span>Excluded</span></div>
          <?php endif; ?>
        </div>
        <?php if ($counts['total'] > 0):
          $funnel = [
            'Identified'  => $counts['identified'],
            'Need Contact'=> $counts['needs_contact'],
            'Ready'       => $counts['preview_ready'],
            'Sent'        => $counts['pitched'],
            'Won'         => $counts['converted'],
          ];
          $funnelMax = max(1, ...array_values($funnel));
        ?>
        <div class="cp-dash-funnel">
          <?php foreach ($funnel as $label => $val): if ($val === 0) continue; ?>
          <div class="cp-funnel-row">
            <span class="cp-funnel-label"><?= $label ?></span>
            <div class="cp-funnel-track">
              <div class="cp-funnel-bar cp-funnel-<?= strtolower(str_replace(' ','_',$label)) ?>"
                   style="width:<?= round($val/$funnelMax*100) ?>%"></div>
            </div>
            <span class="cp-funnel-val"><?= $val ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Map -->
      <div id="dashMap" class="cp-dash-pane" hidden>
        <p class="cp-dash-maplabel">Lead density by region</p>
        <?php
          // Build city → lead count map
          $cityLeads = [];
          foreach ($dashboardData['region_leads'] as $r) {
              $cityLeads[trim((string)$r['location_city'])] = (int)$r['total'];
          }
          // Build region → total leads
          $regionLeads = [];
          foreach (ho_indiana_regions() as $region => $cityStr) {
              $tot = 0;
              foreach (array_map('trim', explode(',', $cityStr)) as $city) {
                  $tot += $cityLeads[$city] ?? 0;
              }
              $regionLeads[$region] = $tot;
          }
          $maxRegionLeads = max(1, ...array_values($regionLeads));
          $regionCoords = [
              'Indianapolis Metro'         => [140,128],
              'Fort Wayne Area'            => [170, 50],
              'South Bend / Mishawaka'     => [ 94, 14],
              'Northwest Indiana'          => [ 28, 26],
              'Evansville Area'            => [ 18,230],
              'Lafayette / West Lafayette' => [ 58, 90],
              'Bloomington Area'           => [ 82,162],
              'Muncie / Anderson'          => [149,103],
              'Terre Haute Area'           => [ 28,144],
              'Kokomo / Logansport'        => [107, 84],
              'Columbus / Bartholomew'     => [120,162],
              'Richmond / East Central'    => [179,127],
              'Southern Indiana'           => [ 94,218],
          ];
        ?>
        <svg viewBox="0 0 200 248" class="cp-in-map" xmlns="http://www.w3.org/2000/svg">
          <path class="cp-in-outline" d="M14,6 L8,18 L8,205 L20,216 L38,224 L56,234 L80,246 L108,244 L132,248 L158,245 L178,240 L192,230 L192,14 L192,6 Z"/>
          <?php foreach ($regionCoords as $region => [$rx,$ry]):
            $leads  = $regionLeads[$region] ?? 0;
            $r      = $leads > 0 ? max(6, min(22, 6 + round($leads/$maxRegionLeads*16))) : 5;
            $abbr   = preg_replace('/\s*\/.*/', '', $region);
            $abbr   = preg_replace('/ (Metro|Area|Area|Region)$/', '', $abbr);
            $color  = $leads > 20 ? '#2a7a35' : ($leads > 5 ? '#c49000' : ($leads > 0 ? '#c06010' : '#ccc'));
          ?>
          <circle cx="<?= $rx ?>" cy="<?= $ry ?>" r="<?= $r ?>" fill="<?= $color ?>" opacity=".85"/>
          <?php if ($leads > 0): ?>
          <text x="<?= $rx ?>" y="<?= $ry+1 ?>" class="cp-in-dot-label"><?= $leads ?></text>
          <?php endif; ?>
          <?php endforeach; ?>
        </svg>
        <div class="cp-map-legend">
          <span class="cp-ml cp-ml-hi">20+</span>
          <span class="cp-ml cp-ml-mid">6–20</span>
          <span class="cp-ml cp-ml-lo">1–5</span>
          <span class="cp-ml cp-ml-none">0</span>
          <span style="font-size:11px;color:var(--ink2)">leads per region</span>
        </div>
      </div>

      <!-- Categories -->
      <div id="dashCats" class="cp-dash-pane" hidden>
        <?php if (empty($dashboardData['categories'])): ?>
          <p class="cp-empty">No data yet.</p>
        <?php else:
          $catMax = max(1, ...array_map(fn($c)=>(int)$c['total'], $dashboardData['categories']));
        ?>
        <div class="cp-cat-chart">
          <?php foreach ($dashboardData['categories'] as $cat): ?>
          <div class="cp-cat-row">
            <span class="cp-cat-name"><?= ho_h((string)$cat['name']) ?></span>
            <div class="cp-cat-stack" title="<?= (int)$cat['total'] ?> leads">
              <?php
                $parts = ['queue'=>'#6aad7a','ready'=>'#f2b01e','sent'=>'#4a90d9','won'=>'#2f5e36'];
                foreach ($parts as $key => $col):
                  $w = round((int)$cat[$key]/$catMax*100);
                  if ($w < 1) continue;
              ?>
              <div style="width:<?= $w ?>%;background:<?= $col ?>;height:100%"></div>
              <?php endforeach; ?>
            </div>
            <span class="cp-cat-total"><?= (int)$cat['total'] ?></span>
          </div>
          <?php endforeach; ?>
          <div class="cp-cat-legend">
            <span style="background:#6aad7a">Queue</span>
            <span style="background:#f2b01e">Ready</span>
            <span style="background:#4a90d9">Sent</span>
            <span style="background:#2f5e36">Won</span>
          </div>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /cp-dash-body -->
  </div><!-- /cp-dash-panel -->
</div><!-- /cpDash -->

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
function openDash() {
  var el = document.getElementById('cpDash');
  if (el) { el.hidden = false; document.body.style.overflow = 'hidden'; }
}
function closeDash() {
  var el = document.getElementById('cpDash');
  if (el) { el.hidden = true; document.body.style.overflow = ''; }
}
function dashTab(id, btn) {
  document.querySelectorAll('.cp-dash-pane').forEach(function(p){ p.hidden = true; });
  document.querySelectorAll('.cp-dash-tab').forEach(function(b){ b.classList.remove('is-active'); });
  var pane = document.getElementById('dash' + id.charAt(0).toUpperCase() + id.slice(1));
  if (pane) pane.hidden = false;
  if (btn)  btn.classList.add('is-active');
}
</script>

</body>
</html>
