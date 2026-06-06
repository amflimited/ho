<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';

/**
 * v139 — Production Lane Redesign Plan
 * Planning only. No operational writes. No sending. No scraping. No payments. No domains.
 */

$jobHierarchy = [
    [
        'priority' => 1,
        'job_key' => 'send_ready_drafts',
        'headline' => 'Drafts are ready to send.',
        'operator_message' => 'Open the send lane and manually contact prepared businesses.',
        'why_first' => 'Prepared drafts are closest to revenue. If they exist, sending is the current job.',
        'uses_helpers' => ['send-model.php', 'sales-send.php'],
        'visible_label' => 'Start sending',
    ],
    [
        'priority' => 2,
        'job_key' => 'prep_contact_ready',
        'headline' => 'Businesses are ready to prep.',
        'operator_message' => 'Generate diagnosis keys and outreach drafts in one GPT batch.',
        'why_first' => 'Contact-ready records need to become sendable opportunities.',
        'uses_helpers' => ['prep-model.php', 'sales-prep.php'],
        'visible_label' => 'Prepare outreach',
    ],
    [
        'priority' => 3,
        'job_key' => 'review_intake_candidates',
        'headline' => 'Candidate leads are waiting for intake.',
        'operator_message' => 'Preview candidate mapping, duplicates, and import decisions before writing records.',
        'why_first' => 'Raw sourced businesses must be cleaned before prep.',
        'uses_helpers' => ['intake-model.php', 'sales-intake.php'],
        'visible_label' => 'Review candidates',
    ],
    [
        'priority' => 4,
        'job_key' => 'repair_blocked_records',
        'headline' => 'Some records need repair.',
        'operator_message' => 'Fix missing identity, contact surfaces, or duplicate uncertainty.',
        'why_first' => 'Broken records should not pollute prep or outreach.',
        'uses_helpers' => ['records-model.php', 'sales-records.php'],
        'visible_label' => 'Repair records',
    ],
    [
        'priority' => 5,
        'job_key' => 'source_more_businesses',
        'headline' => 'Choose a market and find more businesses.',
        'operator_message' => 'Generate a lead-sourcing prompt with known-business exclusions.',
        'why_first' => 'When no active work exists, grow the market pool.',
        'uses_helpers' => ['source-model.php', 'sales-source.php'],
        'visible_label' => 'Find businesses',
    ],
];

$currentCodeUse = [
    'source-model.php' => 'Useful backend helper for known-business exclusions and sourcing prompts. Not the primary visible experience.',
    'sales-source.php' => 'Useful specialist screen. Should be opened by the lane when the current job is Source.',
    'intake-model.php' => 'Useful preview/dedupe helper. Needs future import writer before it can finish the job.',
    'sales-intake.php' => 'Useful specialist screen. Should not be a main app button forever.',
    'records-model.php' => 'Useful repair/dedupe helper. Should remain repair bay, not cockpit.',
    'sales-records.php' => 'Useful specialist repair screen. Open only when blocked/repair job is current or requested.',
    'prep-model.php' => 'Useful combined prompt helper. Needs dedicated writer before Prep can finish the job.',
    'sales-prep.php' => 'Useful specialist screen. Should be surfaced only when prep is current.',
    'send-model.php' => 'Useful manual send helper. Needs outcome writer later.',
    'sales-send.php' => 'Useful specialist screen. Should become the current-job surface when drafts are ready.',
    'sales-portal-dashboard.php' => 'Wrong if it remains five equal module buttons. Should become production lane home.',
];

$visibleAppRules = [
    'The app should open to the current unfinished production job, not a menu of modules.',
    'Source, Intake, Records, Prep, and Send are internal stages, not equal primary navigation buttons.',
    'If drafts are ready, the app should tell Adam to send them before showing sourcing tools.',
    'If prep is needed, the app should show prep as the job before asking for more sourcing.',
    'If candidate intake is waiting, intake is the job.',
    'If records are blocked, repair is the job.',
    'Only when no active work exists should the app ask Adam to source more businesses.',
    'Support tools belong behind a small tools escape hatch.',
    'Legacy workbenches should not appear on the main surface.',
    'The app should reduce Adam’s attention cost, not organize more choices.',
];

$futureHomeSpec = [
    'first_line' => 'Hoosier Online',
    'current_job_area' => [
        'headline' => 'One sentence describing the current unfinished job.',
        'why' => 'One sentence explaining why this job is next.',
        'primary_control' => 'One large control leading into the specialist surface or embedded lane.',
        'proof' => 'Small count/reason text, not stat cards.',
    ],
    'secondary_escape' => [
        'label' => 'Tools',
        'contains' => ['Source', 'Intake', 'Records', 'Prep', 'Send', 'Map', 'Check', 'Audit', 'Contracts', 'Legacy'],
        'rule' => 'Tools is an escape hatch, not the main operating experience.',
    ],
];

$productionLoop = [
    'market_target' => 'Pick where/category to source only when the app has no current work.',
    'candidate_discovery' => 'Use Source prompt and known-business exclusions.',
    'candidate_cleanup' => 'Use Intake to preview new/update/duplicate/review/reject.',
    'record_repair' => 'Use Records only when blocked or intentionally repairing data.',
    'sales_prep' => 'Use Prep to create diagnosis keys plus outreach draft in one pass.',
    'manual_contact' => 'Use Send Tray to copy and manually contact.',
    'outcome_tracking' => 'Future writer records sent/follow-up/customer/do-not-contact.',
    'coverage_learning' => 'Map learns what categories/areas have been worked.',
];

$nextBuild = [
    'recommended_next' => 'v140 — Production Lane Home',
    'why_not_v139_writer' => 'A writer makes the current module system more functional, but it does not fix the visible product problem.',
    'goal' => 'Change sales-portal-dashboard.php from five equal module buttons into one current-job production lane.',
    'constraints' => [
        'No new operational writes.',
        'No sending.',
        'No scraping automation.',
        'No payments.',
        'No domain purchasing.',
        'Use current helper models only for read-only counts/status.',
    ],
];

function ho_v139_section(string $title, $data): void { ?>
<section class="admin-v139-section">
  <h2><?= ho_h($title) ?></h2>
  <pre class="admin-contract-code"><?= ho_h(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
</section>
<?php }

ho_admin_render_start(
    'production_lane_plan',
    'Production Lane',
    'Sales',
    'Production Lane <em>Redesign</em>',
    'The app opens to the current unfinished job, not module buttons.'
);
?>

<section class="admin-v139-hero">
  <p>v139 redesign plan</p>
  <h1>The app is not the modules. The app is the next job.</h1>
  <strong>Source, Intake, Records, Prep, and Send remain useful internally. They should stop being the visible primary interface.</strong>
</section>

<section class="admin-v139-thesis">
  <h2>Correction</h2>
  <p>The current app home still gives Adam choices. The better app decides the current production job and opens around that job.</p>
  <p>Modules become helpers behind the lane. The daily surface becomes: what is next, why it matters, and the one thing Adam must do because the app cannot do it safely.</p>
</section>

<?php
ho_v139_section('Production job hierarchy', $jobHierarchy);
ho_v139_section('How current code should be used', $currentCodeUse);
ho_v139_section('Visible app rules', $visibleAppRules);
ho_v139_section('Future Home specification', $futureHomeSpec);
ho_v139_section('Full production loop', $productionLoop);
ho_v139_section('Recommended next build', $nextBuild);
?>

<section class="admin-v139-section">
  <h2>Acceptance test</h2>
  <ul>
    <li>A user opening the app should not see five equal module buttons as the main experience.</li>
    <li>The app should pick the current production job using state/counts.</li>
    <li>The main surface should show only the current job, a reason, and one primary continuation.</li>
    <li>Specialist pages may exist, but they should not define the operating experience.</li>
    <li>Legacy pages should stay outside the daily lane.</li>
  </ul>
</section>

<?php ho_admin_render_end(); ?>
