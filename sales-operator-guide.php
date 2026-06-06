<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';

/**
 * v130 Operator Guide
 * Locks the v1 Hoosier Online sales machine workflow.
 */

$steps = [
    [
        'num' => 1,
        'title' => 'Find leads',
        'means' => 'Build or import a batch of Indiana-relevant local service businesses.',
        'when' => 'Use when Command Center and Market Map show no active production pile or a category/region needs more coverage.',
        'batch' => '25–50 candidates',
        'input' => 'Category and Indiana source context.',
        'output' => 'Candidate records entering the upstream pipeline.',
        'next' => 'Triage',
        'click' => '/sales-portal-dashboard.php',
        'label' => 'Command Center / Lead tools',
    ],
    [
        'num' => 2,
        'title' => 'Triage',
        'means' => 'Sort raw candidates into usable, research-needed, duplicate, blocked, or manual-review piles.',
        'when' => 'Use when records are in Need Triage.',
        'batch' => '25–50',
        'input' => 'Candidate business names, locations, categories, and public hints.',
        'output' => 'Need Research, Proceed No Website, Blocked / Skip, or Manual Review.',
        'next' => 'Research',
        'click' => '/sales-portal-dashboard.php',
        'label' => 'Command Center',
    ],
    [
        'num' => 3,
        'title' => 'Research',
        'means' => 'Gather enough public customer-facing information to decide if a business is contactable and diagnosable.',
        'when' => 'Use when records are Need Research or Manual Check.',
        'batch' => '10–25',
        'input' => 'Current record data and public surfaces.',
        'output' => 'Contact Ready, Manual Review, or Blocked / Skip.',
        'next' => 'Diagnose',
        'click' => '/sales-portal-dashboard.php',
        'label' => 'Command Center',
    ],
    [
        'num' => 4,
        'title' => 'Diagnose',
        'means' => 'Assign reusable diagnosis keys: strengths, weaknesses, recommendations, offer path, and three preview directions.',
        'when' => 'Use when Command Center says Contact Ready records still need diagnosis.',
        'batch' => '25',
        'input' => 'Contact Ready businesses.',
        'output' => 'diagnosis_status, strength_keys_json, weakness_keys_json, recommendation_keys_json, primary_offer_path, preview_direction_keys_json.',
        'next' => 'Build /go preview',
        'click' => '/sales-diagnosis-workbench.php',
        'label' => 'Diagnosis Workbench',
    ],
    [
        'num' => 5,
        'title' => 'Build /go preview',
        'means' => 'Assign the dynamic customer-facing preview path. The page renders from diagnosis keys.',
        'when' => 'Use when Command Center says diagnosed records need /go previews.',
        'batch' => 'Up to 25',
        'input' => 'Diagnosis-ready businesses with business_slug.',
        'output' => 'front_door_preview_status = go_ready, go_slug, go_path, outreach_asset_url.',
        'next' => 'Draft outreach',
        'click' => '/sales-front-door-builder.php',
        'label' => 'Front Door Builder',
    ],
    [
        'num' => 6,
        'title' => 'Draft outreach',
        'means' => 'Generate short manual outreach drafts centered on one /go preview link.',
        'when' => 'Use when /go-ready businesses need outreach drafts.',
        'batch' => 'Up to 25',
        'input' => '/go-ready businesses, public contact path, top weakness/recommendation keys.',
        'output' => 'outreach_to, outreach_subject, outreach_body, outreach_asset_url, marketing_desk_status.',
        'next' => 'Manual send',
        'click' => '/sales-marketing-desk.php',
        'label' => 'Marketing Desk',
    ],
    [
        'num' => 7,
        'title' => 'Manual send',
        'means' => 'Human reviews To, Subject, Body, compliance checklist, and /go link, then manually sends outside the system.',
        'when' => 'Use when Marketing Desk shows Draft Ready.',
        'batch' => 'Manual card-by-card or small reviewed groups',
        'input' => 'Draft Ready cards.',
        'output' => 'A manually sent message. No automatic sending exists in v1.',
        'next' => 'Follow up',
        'click' => '/sales-marketing-desk.php',
        'label' => 'Marketing Desk',
    ],
    [
        'num' => 8,
        'title' => 'Follow up',
        'means' => 'Track whether a business needs another manual contact, is interested, not interested, or should not be contacted.',
        'when' => 'Use after manual outreach has occurred.',
        'batch' => 'Future/manual in v1',
        'input' => 'Manual send outcomes.',
        'output' => 'Follow-up or sales status when that layer is added.',
        'next' => 'Mark result / track market coverage',
        'click' => '/sales-market-map.php',
        'label' => 'Market Map',
    ],
    [
        'num' => 9,
        'title' => 'Mark result / track market coverage',
        'means' => 'Use category and region coverage to see where the market is incomplete or bottlenecked.',
        'when' => 'Use when choosing the next category, region, or production pile.',
        'batch' => 'Read-only in v1',
        'input' => 'All current records and their pipeline states.',
        'output' => 'Next Market Action and bottleneck view.',
        'next' => 'Find leads or continue the next active pile.',
        'click' => '/sales-market-map.php',
        'label' => 'Market Map',
    ],
];

ho_admin_render_start(
    'operator_guide',
    'Operator Guide',
    'Sales',
    'Operator <em>Guide</em>',
    'Locked v1 workflow for Hoosier Online sales production.'
);
?>

<section class="admin-card admin-guide-hero">
  <p class="admin-kicker">Workflow Lock</p>
  <h2>Command Center → Diagnosis → Front Door → Marketing → Market Map</h2>
  <p class="admin-muted">This is the v1 operating path. The system is batch-first. Case files are exception handling. No sending, SMS, AI calls, payment, scraping, or domain purchasing happens inside this v1 flow.</p>
  <div class="admin-action-row">
    <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php">Open Command Center</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-system-check.php">Open System Check</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-market-map.php">Open Market Map</a>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Primary Rule</p>
  <h2>Workbenches process piles. Case files handle exceptions.</h2>
  <p class="admin-muted">If the next step feels unclear, return to the Command Center. If the count looks suspicious, open the State Audit. If a category is the question, open Market Map.</p>
</section>

<section class="admin-card">
  <p class="admin-kicker">V1 Workflow</p>
  <h2>Step-by-step operating path</h2>
  <div class="admin-guide-step-list">
    <?php foreach ($steps as $step): ?>
      <article class="admin-guide-step">
        <div class="admin-guide-step-num"><?= ho_h((string)$step['num']) ?></div>
        <div class="admin-guide-step-body">
          <h3><?= ho_h($step['title']) ?></h3>
          <dl>
            <dt>What it means</dt><dd><?= ho_h($step['means']) ?></dd>
            <dt>When to use it</dt><dd><?= ho_h($step['when']) ?></dd>
            <dt>Batch size</dt><dd><?= ho_h($step['batch']) ?></dd>
            <dt>Input</dt><dd><?= ho_h($step['input']) ?></dd>
            <dt>Output</dt><dd><?= ho_h($step['output']) ?></dd>
            <dt>Next step</dt><dd><?= ho_h($step['next']) ?></dd>
          </dl>
          <a class="admin-btn admin-btn-secondary" href="<?= ho_h($step['click']) ?>"><?= ho_h($step['label']) ?></a>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">No-send Rule</p>
  <h2>Nothing in this v1 machine sends automatically</h2>
  <p class="admin-muted">Marketing Desk stages copy only. The operator manually reviews and manually sends outside the system. No SMS, AI calls, CRM automation, or automatic email is included.</p>
</section>

<section class="admin-card admin-low-priority">
  <p class="admin-kicker">Legacy / Experimental</p>
  <h2>Old package tools are not the primary flow</h2>
  <p class="admin-muted">Preview Package Workbench, Package System, materialization/domain-check paths, and old 10-design dashboard experiments are legacy/experimental until the /go path is stable.</p>
</section>

<?php ho_admin_render_end(); ?>
