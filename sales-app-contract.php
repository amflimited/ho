<?php
declare(strict_types=1);
require_once __DIR__ . '/admin-core.php';

$modules = [
  ['Source','Help Adam choose a market target and craft GPT-assisted lead generation prompts.','Owns category/area/source choice, exclusion of already-known businesses, diagnosis/personalization precursor capture, and structured candidate output.','Candidate Lead rows ready for Intake.'],
  ['Intake','Convert generated lead output into clean table-ready business records.','Owns field mapping, normalization, dedupe detection, source tracking, import preview, and new/update/hold/reject grouping.','Business Records or held Candidate Leads.'],
  ['Records','Manage the stored business database as a repair bay, not the daily cockpit.','Owns search, inspect, merge, slug/name/category/contact repair, blocked/manual flags, and source/claim history.','Clean Business Records.'],
  ['Prep','Turn clean contactable businesses into sendable sales opportunities.','Owns combined GPT sales-prep prompts, diagnosis keys, personalization summary, computed /go URL, outreach draft, and warnings.','SalesPrep and OutreachDraft data.'],
  ['Send','Let Adam manually review and send prepared outreach outside automatic sending.','Owns the send tray, copy controls, skip/hold/sent/follow-up/outcome marking.','Contact Attempts and Outcomes.'],
];
$pipeline=['Market Target','Source Batch','Candidate Lead','Intake Preview','Business Record','Research / Contact Readiness','Sales Prep','Computed /go Preview','Outreach Draft','Send Item','Manual Contact Attempt','Outcome','Market Coverage'];
$objects=[
 'MarketTarget'=>'Category/area/source goal for a sourcing run.',
 'SourceBatch'=>'Generated or gathered candidate batch with source and exclusion context.',
 'CandidateLead'=>'Raw possible business before clean intake.',
 'BusinessRecord'=>'Durable row with name, slug, category, location, contact and public surfaces.',
 'ResearchClaim'=>'Evidence-backed public facts about the business.',
 'SalesPrep'=>'Diagnosis keys, personalization summary, offer path, preview directions, and warnings.',
 'Preview'=>'Computed /go.php?slug={business_slug} view from BusinessRecord + SalesPrep.',
 'OutreachDraft'=>'Subject/body/contact path prepared for manual review.',
 'SendItem'=>'Prepared outreach item in Adam’s manual send tray.',
 'ContactAttempt'=>'A manually performed contact event.',
 'Outcome'=>'No response, follow-up, interested, customer, not interested, do not contact.',
 'MarketCoverage'=>'Read model showing category/area/source progress and bottlenecks.',
];
$sourceReq=[
 'The app must help craft the lead-generation prompt, not treat sourcing as outside work.',
 'The prompt must include already-known business names/slugs/URLs to exclude.',
 'The prompt must gather diagnosis and personalization precursors, not just business names.',
 'The prompt must collect customer-facing public clues useful for later diagnosis keys.',
 'Indiana is the location gate; city/service area are sourcing context.',
 'Category is sourcing context; adjacent Indiana local service businesses remain valid.',
 'Output must be structured candidate rows for Intake preview.',
];
$precursors=['business name','likely service lane','city/state','website URL','Facebook/social URL','public email/phone','visible service list','trust signals','weakness clues such as no website, unclear services, weak contact path, outdated page, missing proof, poor mobile path','customer-facing differentiators','source URL/evidence note'];
$storage=['Research evidence uses the evidence/import contract.','Workflow state must not be faked as research evidence.','Computed values should be computed, not stored, unless persistence is necessary.','/go preview URL is computed from business_slug.','SalesPrep and OutreachDraft are generated data objects, not generic research claims.','Outcomes need a purpose-built status writer later.'];
$interface=['Adam-only iPhone Safari app, not responsive desktop admin.','Do not design desktop grids that merely collapse onto mobile.','Do not make daily operation a stack of equal-weight cards.','Do not create buttons for deterministic work the app can compute.','Do not expose support/debug as primary work.','Do not make Records management the cockpit.','The app asks Adam only for external GPT paste/output, manual sending, and judgment on exceptions.'];
$removed=['Front Door Builder as a required user step','Preview Package Workbench as primary flow','Package System as primary flow','Domain Check as pre-sale requirement','Materialization as pre-sale requirement','10-template/10-logo/10-domain pre-sale dashboard','Separate diagnosis and outreach prompts when one Sales Prep batch can do both'];

ho_admin_render_start('app_contract','Mobile App Contract','Sales','Hoosier Online <em>Mobile App Contract</em>','Source, Intake, Records, Prep, Send.');
?>
<section class="admin-app-contract-hero">
  <p>v131 contract</p>
  <h1>Hoosier Online is a mobile sales production app, not an admin website.</h1>
  <strong>Primary user: Adam. Primary device: iPhone 17 Safari. Primary outcome: manually sendable sales opportunities with personalized /go previews.</strong>
  <div class="admin-app-contract-actions"><a href="/sales-portal-dashboard.php">Command Center</a><a href="/sales-system-check.php">System Check</a><a href="/sales-market-map.php">Market Map</a></div>
</section>
<section class="admin-app-contract-strip"><?php foreach(['Source','Intake','Records','Prep','Send'] as $m): ?><span><?= ho_h($m) ?></span><?php endforeach; ?></section>
<section class="admin-app-contract-section"><h2>Business objective</h2><p>Identify Indiana local service businesses, gather enough public-facing context to personalize a useful preview, prepare one computed /go page, stage respectful outreach, and let Adam manually contact the business.</p></section>
<section class="admin-app-contract-section"><h2>Main app modules</h2><div class="admin-app-module-list"><?php foreach($modules as $m): ?><article><h3><?= ho_h($m[0]) ?></h3><p><?= ho_h($m[1]) ?></p><details><summary>Ownership</summary><p><b>Owns:</b> <?= ho_h($m[2]) ?></p><p><b>Output:</b> <?= ho_h($m[3]) ?></p></details></article><?php endforeach; ?></div></section>
<section class="admin-app-contract-section"><h2>Lead generation is first-class</h2><p>The app must help craft the sourcing prompt and gather useful precursor data for later diagnosis and personalization.</p><h3>Prompt requirements</h3><ul><?php foreach($sourceReq as $x): ?><li><?= ho_h($x) ?></li><?php endforeach; ?></ul><h3>Precursors to gather</h3><ul><?php foreach($precursors as $x): ?><li><?= ho_h($x) ?></li><?php endforeach; ?></ul></section>
<section class="admin-app-contract-section"><h2>Full data pipeline</h2><ol class="admin-app-pipeline"><?php foreach($pipeline as $x): ?><li><?= ho_h($x) ?></li><?php endforeach; ?></ol></section>
<section class="admin-app-contract-section"><h2>Core objects</h2><div class="admin-app-object-list"><?php foreach($objects as $k=>$v): ?><div><strong><?= ho_h($k) ?></strong><span><?= ho_h($v) ?></span></div><?php endforeach; ?></div></section>
<section class="admin-app-contract-section"><h2>Storage and write rules</h2><ul><?php foreach($storage as $x): ?><li><?= ho_h($x) ?></li><?php endforeach; ?></ul></section>
<section class="admin-app-contract-section"><h2>Mobile app interface rules</h2><ul><?php foreach($interface as $x): ?><li><?= ho_h($x) ?></li><?php endforeach; ?></ul></section>
<section class="admin-app-contract-section"><h2>Deleted or merged fake steps</h2><ul><?php foreach($removed as $x): ?><li><?= ho_h($x) ?></li><?php endforeach; ?></ul></section>
<section class="admin-app-contract-section"><h2>Acceptance tests for future builds</h2><ul><li>A build fails if lead generation, intake, or record repair are undefined.</li><li>A build fails if it adds a button for deterministic work the app can compute.</li><li>A build fails if it treats the app as desktop-first or responsive-card-stack admin.</li><li>A build fails if workflow state is routed through fake research evidence.</li><li>A build fails if it hides source/intake/table management instead of defining ownership.</li></ul></section>
<section class="admin-app-contract-section admin-v131a-complete-link">
  <h2>Complete implementation contract</h2>
  <p>v131a expands this framing into the binding implementation contract: Source prompt shape, known-business exclusions, Intake preview, dedupe, field ownership, write paths, Sales Prep, Send Tray, Outcomes, failure policy, migration, and iPhone screen specs.</p>
  <a class="admin-btn admin-btn-primary" href="/sales-app-contract-complete.php">Open Complete App Contract</a>
</section>

<?php ho_admin_render_end(); ?>
