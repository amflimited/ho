<?php
declare(strict_types=1);
require_once __DIR__ . '/admin-core.php';

/**
 * v132 — Contract-Aligned Simplification and Migration Plan
 * Planning only. No sending, scraping, payment, domain purchase, or operational feature work.
 */

$pageMigration = [
 ['sales-portal-dashboard.php','Command Center / patched dashboard with next-move logic and support links.','App Home','convert','Become an app home that selects Source, Intake, Records, Prep, or Send.','If left as-is, it preserves the admin-card dashboard structure.'],
 ['sales-diagnosis-workbench.php','Separate diagnosis key prompt/import.','Prep','convert / merge','Diagnosis keys and outreach draft belong in one Sales Prep batch when possible.','Keeping it separate preserves unnecessary GPT/paste/import steps.'],
 ['sales-front-door-builder.php','Writes go_slug/go_path/front_door_preview_status using importer path.','Legacy / remove from primary flow','deprecate','/go URL is deterministic: /go.php?slug={business_slug}.','Continuing to use it recreates validation failures and fake workflow state.'],
 ['go.php','Customer-facing Front Door Preview renderer.','Computed Preview','keep / strengthen','Correct preview mechanism: render from BusinessRecord + SalesPrep keys.','Needs to tolerate legacy and new SalesPrep data during transition.'],
 ['sales-marketing-desk.php','Manual outreach draft prompt/intake and draft cards.','Send','convert','Should become Send Tray: one sendable item, copy controls, manual outcome marking.','If left as card dashboard, it remains visually/operationally too complex.'],
 ['sales-market-map.php','Read-oriented market/category coverage tracker.','Map support module','keep as support','Useful for choosing next market/category.','Should not become a required daily step.'],
 ['sales-command-center-audit.php','Read-only state truth/debug page.','Audit support module','keep as support','Useful when app state disagrees.','Should not be normal operating path.'],
 ['sales-system-check.php','Required file/function/canonical field check.','Check support module','keep as support','Useful for deployment sanity and contract health.','Should not substitute app acceptance tests.'],
 ['sales-operator-guide.php','Process documentation for transitional v124-v130 path.','Guide support module','rewrite later','Should be rewritten around Source → Intake → Records → Prep → Send.','May reinforce obsolete Front Door Builder flow.'],
 ['sales-app-contract.php','v131 mobile app framing contract.','Guide / Contract support','keep historical','Useful framing, but v131a is binding.','Do not use v131 alone as final contract.'],
 ['sales-app-contract-complete.php','v131a complete app implementation contract.','Guide / Contract support','keep source of truth','Binding contract for Source, Intake, Records, Prep, Send.','Future builds must be checked against it.'],
 ['sales-preview-package-workbench.php','Legacy package generation/domain/materialization workflow.','Legacy / Experimental','hide from primary flow','Pre-sale package/domain workflow is not v1 app path.','Keeping it prominent reintroduces old complexity.'],
 ['preview-package-model.php','Legacy package support model.','Legacy / Experimental','tolerate but do not build on','Package system should not drive v1 sales motion.','package_status can confuse app state if treated as primary.'],
];

$replacementBuilds = [
 'Source module' => ['Craft lead generation prompt and known-business exclusion packet.','Market target, known exclusion count, prompt copy surface.','Category/context, Indiana gate, optional area, known businesses.','Candidate Lead JSON for Intake.'],
 'Intake module' => ['Preview and convert candidate leads into durable business records.','Paste candidate batch, then New / Update / Hold / Reject groups.','Candidate Lead JSON.','Business records or held candidates.'],
 'Records repair module' => ['Repair identity/contact/category/source problems and duplicates.','Search or focused repair item from Review.','Existing records or held intake rows.','Clean record, merged duplicate, blocked record, or restored record.'],
 'Sales Prep module' => ['Generate diagnosis keys and outreach draft in one GPT batch.','Ready for Sales Prep count and one combined prompt.','Contact-ready business records with source/research clues.','SalesPrep + OutreachDraft.'],
 'Send Tray' => ['Manual outreach review, copy controls, and outcome marking.','One sendable item or simple queue.','Prepared OutreachDraft + computed /go URL.','Manual ContactAttempt + Outcome.'],
 'App Home' => ['Choose the correct app mode based on actual state.','One current mode, not a dashboard of boxes.','Computed state from Source/Intake/Records/Prep/Send counts.','Route Adam to Source, Intake, Records, Prep, or Send.'],
];

$stateMigration = [
 'diagnosis claims' => ['keep','strength/weakness/recommendation/offer/preview direction keys remain useful. Future Prep writes these as SalesPrep data.'],
 'package_status' => ['legacy only','Do not use package_status to decide primary app mode.'],
 'go_slug / go_path' => ['ignore/tolerate','/go URL is computed from business_slug. Do not require or write as proof.'],
 'front_door_preview_status' => ['deprecate required state','Preview readiness is computed from required SalesPrep keys.'],
 'marketing_desk_status' => ['convert to Send state','Draft readiness and outcomes should move to SendItem/Outcome model.'],
 'outreach drafts' => ['keep/convert','outreach_subject/body/to remain useful. Future Prep produces them with diagnosis keys.'],
 'legacy package records' => ['support only','Do not delete immediately; do not surface as primary app work.'],
];

$stopImmediately = [
 'Required Front Door Builder step',
 'Storing go_path/go_slug as required proof',
 'Generic importer for workflow state',
 'Separate diagnosis/outreach prompts when Sales Prep can cover both',
 'Package/domain/materialization states as primary v1 sales path',
 'More admin cards/pages before Source→Intake→Records→Prep→Send is implemented',
];

$futureHandling = [
 'lead generation prompt crafting' => 'Source generates a prompt using market target plus known-business exclusion packet and diagnosis precursor requirements.',
 'already-known business exclusion' => 'App generates compact known-business packet from names, slugs, URLs, phones, emails, socials, normalized name/city.',
 'candidate lead intake' => 'Intake accepts source candidate JSON, previews mapped fields, dedupe decisions, and import/hold/reject groups.',
 'table/record management' => 'Records owns search, repair, merge, block, restore, and claim/source history.',
 'dedupe' => 'Intake/Records use exact/likely/not-duplicate rules from v131a; possible duplicates go to Review.',
 'sales prep' => 'Prep generates diagnosis keys and outreach draft in one batch.',
 'computed /go preview' => 'go.php renders from business_slug + SalesPrep keys; no builder step or go_path write.',
 'manual send tray' => 'Send shows prepared outreach with copy controls and manual status/outcome controls.',
 'outcomes' => 'Send outcome writer records sent_manually, follow_up_due, interested, customer, not_interested, do_not_contact, etc.',
];

$smallestFirstImplementation = [
 'name'=>'v133 Source Module',
 'why_first'=>'Lead generation is the front of the app and does not require risky writes at first.',
 'scope'=>['market target selector/entry','known-business exclusion packet generator from current records','source prompt builder','copy source prompt','no import yet','no scraping automation'],
 'success'=>['Adam can choose a category/area target.','App shows known businesses excluded.','App generates source prompt with exclusions and diagnosis/personalization precursors.','Prompt output is designed for future Intake.'],
];

function ho_v132_pre($title,$data){ ?>
<section class="admin-v132-section">
  <h2><?= ho_h($title) ?></h2>
  <pre class="admin-contract-code"><?= ho_h(json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre>
</section>
<?php }

ho_admin_render_start('migration_plan','Migration Plan','Sales','Contract-Aligned <em>Migration Plan</em>','Move from patched pages to Source → Intake → Records → Prep → Send.');
?>
<section class="admin-v132-hero">
  <p>v132 migration plan</p>
  <h1>Stop preserving the patched workflow. Migrate it.</h1>
  <strong>Current workbenches become modules, support tools, or legacy. The final app is Source → Intake → Records → Prep → Send.</strong>
  <div class="admin-app-contract-actions">
    <a href="/sales-app-contract-complete.php">Complete Contract</a>
    <a href="/sales-portal-dashboard.php">Current Command Center</a>
  </div>
</section>

<section class="admin-v132-section">
  <h2>Primary module model</h2>
  <div class="admin-v132-module-strip">
    <?php foreach (['Source','Intake','Records','Prep','Send'] as $m): ?><span><?= ho_h($m) ?></span><?php endforeach; ?>
  </div>
  <h3>Support modules</h3>
  <div class="admin-v132-module-strip is-support">
    <?php foreach (['Review','Map','Check','Guide','Audit'] as $m): ?><span><?= ho_h($m) ?></span><?php endforeach; ?>
  </div>
</section>

<section class="admin-v132-section">
  <h2>Page migration table</h2>
  <div class="admin-v132-migration-list">
    <?php foreach ($pageMigration as $r): ?>
    <article>
      <h3><?= ho_h($r[0]) ?></h3>
      <dl>
        <dt>Current purpose</dt><dd><?= ho_h($r[1]) ?></dd>
        <dt>v131a destination</dt><dd><?= ho_h($r[2]) ?></dd>
        <dt>Action</dt><dd><?= ho_h($r[3]) ?></dd>
        <dt>Reason</dt><dd><?= ho_h($r[4]) ?></dd>
        <dt>Risk</dt><dd><?= ho_h($r[5]) ?></dd>
      </dl>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<?php
ho_v132_pre('Replacement builds', $replacementBuilds);
ho_v132_pre('Existing states/data migration', $stateMigration);
ho_v132_pre('Stop immediately', $stopImmediately);
ho_v132_pre('How the future app handles each function', $futureHandling);
ho_v132_pre('Smallest safe first implementation', $smallestFirstImplementation);
?>

<section class="admin-v132-section">
  <h2>Acceptance test for this plan</h2>
  <ul>
    <li>Lead generation is explicitly Source, not hidden.</li>
    <li>Lead-to-table conversion is explicitly Intake, not generic import magic.</li>
    <li>Table management is explicitly Records, not the daily cockpit.</li>
    <li>Front Door Builder is removed from the primary path.</li>
    <li>/go preview is computed from business_slug and SalesPrep keys.</li>
    <li>Sales Prep combines diagnosis and outreach draft generation.</li>
    <li>Send Tray remains manual-only.</li>
    <li>Audit, Check, Guide, and Map are support modules, not primary workflow clutter.</li>
  </ul>
</section>
<?php ho_admin_render_end(); ?>
