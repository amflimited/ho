<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$businessReviewLock = json_decode(<<<'JSON'
{
  "schema": "hoosier_online.business_review_lock.v050",
  "version": "HO-BUSINESS-REVIEW-LOCK-050",
  "status": "business_review_lock",
  "scope": [
    "sales-system.php only",
    "No database schema changes",
    "No prospect-model.php changes",
    "No dashboard changes",
    "No business view changes",
    "No upload changes",
    "No admin CSS changes",
    "No scraping",
    "No outreach automation",
    "No payment integration",
    "No customer-facing UI",
    "No preview.php"
  ],
  "current_system_state": [
    "Admin Hub is accepted as the primary operator control surface.",
    "Research page supports manual one-prospect-at-a-time GPT JSON import.",
    "Prospects page supports internal review of businesses, claims, readiness, and option assignment.",
    "Business View supports internal inspection of one business.",
    "v044 prepared preview schema and seeds.",
    "v045 added internal Preview Readiness Evaluator.",
    "v046 added internal Preview Option Assignment.",
    "v048 completed UI/operator-process review and safe clarity fixes.",
    "v049 completed backend/code review and safe missing-schema hardening.",
    "The system is not ready for scraping, preview.php, outreach automation, or payment integration yet."
  ],
  "accepted_manual_operating_workflow": [
    "Start at Admin Hub.",
    "Open Research.",
    "Choose one manually selected local operator.",
    "Run the locked GPT research prompt.",
    "Paste one valid JSON response.",
    "Validate before importing.",
    "Import the prospect.",
    "Open Prospects.",
    "Open the Business View for that prospect.",
    "Review evidence, claims, readiness, and option assignment.",
    "Manually draft outreach outside the system.",
    "Manually draft what a future preview would say outside the system.",
    "Record what worked, what failed, and what fields or logic were missing.",
    "Repeat for 3–5 prospects only."
  ],
  "final_decisions_from_v048_v049": [
    {
      "issue": "Business View auto-writes readiness/address suggestions",
      "decision": "Allowed temporarily during manual testing.",
      "final_rule": "Business View may auto-write preview_readiness and preview_address_options during the 3–5 prospect manual test. Before preview.php or real customer-facing flow, this should become explicit operator action such as Evaluate Readiness and Assign Preview Options."
    },
    {
      "issue": "Sales System canon history",
      "decision": "Cumulative canon should be preserved before major future expansion.",
      "final_rule": "sales-system.php may remain the current operating summary for now. Before broad feature work, create or preserve a cumulative canon/history location so prior doctrine is not lost."
    },
    {
      "issue": "Old split pages",
      "decision": "Keep but classify as reference/legacy.",
      "final_rule": "Primary workflow is Admin → Research → Prospects → Business View. Sales Philosophy, Sales Portal Canon, Old Prompt Page, and Old Intake Page remain reference-only unless manually reactivated."
    },
    {
      "issue": "Inline styling",
      "decision": "Do not require perfect cleanup, but block new visual drift.",
      "final_rule": "No major new page may invent layout styles inline. Customer-facing preview.php must use shared CSS patterns or a dedicated preview stylesheet."
    },
    {
      "issue": "preview.php permission",
      "decision": "Blocked until manual proof.",
      "final_rule": "preview.php is not allowed until the 3–5 manual prospect test passes."
    },
    {
      "issue": "Readiness thresholds and business-type classifier",
      "decision": "Must be validated manually before trusted.",
      "final_rule": "Readiness scores and keyword-based business type classification are test assumptions until validated against 3–5 real prospects."
    },
    {
      "issue": "v044 SQL/seeds",
      "decision": "Must be confirmed live.",
      "final_rule": "Before preview.php, confirm v044 SQL and seed rows are installed on the live database."
    }
  ],
  "temporary_behaviors": [
    "Business View may auto-evaluate readiness during manual testing.",
    "Business View may auto-assign preview options during manual testing.",
    "Business View may write address suggestions during manual testing.",
    "Sales System may show current-release summary instead of full cumulative canon during this stop point.",
    "Manual drafting happens outside the system until preview.php is allowed."
  ],
  "blocked_behaviors": [
    "No preview.php.",
    "No customer-facing preview links.",
    "No scraping.",
    "No lead-list imports.",
    "No mass outreach.",
    "No outreach automation.",
    "No cold SMS workflow.",
    "No payment integration.",
    "No automatic domain purchase or availability claims.",
    "No expanding schema because of one odd prospect.",
    "No major UI redesign before business review."
  ],
  "preview_php_permission_gate": {
    "allowed_only_if": [
      "3–5 prospects have been manually selected.",
      "Each prospect imports cleanly through Research.",
      "Each Business View renders without errors.",
      "Readiness status and score make sense for each prospect.",
      "At least 2 prospects reach ready or soft_ready without forcing the result.",
      "Preview option assignment is sensible for at least 2 prospects.",
      "Address suggestions are usable and not embarrassing.",
      "Manual outreach can be written from stored claims without inventing facts.",
      "Manual preview copy can be written from stored claims without inventing facts.",
      "No schema panic occurred during testing.",
      "v044 SQL and seed rows are confirmed installed live."
    ],
    "still_blocked_if": [
      "Research prompt output is inconsistent.",
      "Prospect data feels bloated or confusing.",
      "Readiness scoring feels wrong across multiple prospects.",
      "Option assignment produces irrelevant design or address ideas.",
      "Manual preview drafts require too much invention.",
      "The customer-facing angle is still unclear.",
      "There is pressure to scrape before manual proof exists."
    ]
  },
  "required_3_to_5_prospect_validation_checks": [
    "Can the user pick a prospect without scraping?",
    "Can GPT return valid JSON using the locked prompt?",
    "Does the imported data contain useful customer-safe facts?",
    "Does Business View clearly show what is known, what is missing, and what is risky?",
    "Does preview_readiness produce a believable status?",
    "Does option assignment recommend a sensible design direction?",
    "Do address suggestions make sense?",
    "Can an outreach draft be written from stored facts?",
    "Can a manual preview draft be written from stored facts?",
    "Does the system avoid creating random new field needs?",
    "Does the operator understand whether the prospect should move forward?"
  ],
  "top_level_business_review_agenda": [
    "Define the actual first customer profile.",
    "Define the first offer in plain customer language.",
    "Confirm whether Standard and Managed are still the only packages.",
    "Confirm pricing and renewal logic.",
    "Define the first manual outreach angle.",
    "Draft 3 outreach message patterns: email, contact form, Facebook message.",
    "Define what a prospect sees after clicking a future preview link.",
    "Decide whether domain/subdomain choice is part of the first sale or a later setup step.",
    "Decide what must be delivered within 24 hours.",
    "Define what counts as a successful manual sale test.",
    "Decide when preview.php becomes worth building.",
    "Decide when scraping becomes worth building.",
    "Decide what should be ignored until revenue proof exists."
  ],
  "next_direction_menu": [
    "Manual prospect testing",
    "Top-level business offer review",
    "Outreach copy workshop",
    "Pricing and package lock",
    "Customer preview mockup on paper only",
    "Fulfillment/build checklist",
    "Pause development and test the current system"
  ],
  "recommended_next_step": "Stop feature development and run a business review/planning session before building preview.php or scraping."
}
JSON, true);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($businessReviewLock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

ho_admin_render_start(
    'sales_system',
    'Hoosier Online Sales System',
    'Sales system',
    'Sales <em>System</em>',
    'Business Review Lock: stop feature development, validate the manual sales process, and prepare for top-level business planning.'
);
?>
<script type="application/json" id="ho-business-review-lock"><?= ho_h(json_encode($businessReviewLock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></script>

<section class="admin-status warning">
  <div class="admin-status-head"><strong>v050 Scope</strong></div>
  <?= ho_admin_doc_list($businessReviewLock['scope']) ?>
</section>


<section class="admin-operator-banner">
  <div>
    <strong>Reference / planning</strong>
    <span>This page supports the operator workflow. Use Prospects as the main working surface unless this page is needed for reference or maintenance.</span>
  </div>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
</section>

<section class="admin-card">
  <h2>Current System State</h2>
  <?= ho_admin_doc_list($businessReviewLock['current_system_state']) ?>
</section>

<section class="admin-card">
  <h2>Accepted Manual Operating Workflow</h2>
  <div class="admin-workflow-strip">
    <?php foreach ($businessReviewLock['accepted_manual_operating_workflow'] as $i => $step): ?>
      <span><?= ho_h(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)) ?> · <?= ho_h($step) ?></span>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <h2>Final Decisions from v048 / v049</h2>
  <div class="admin-data-list">
    <?php foreach ($businessReviewLock['final_decisions_from_v048_v049'] as $decision): ?>
      <div class="admin-data-row">
        <div>
          <div class="admin-data-row-title"><?= ho_h($decision['issue']) ?></div>
          <div class="admin-data-row-note"><strong>Decision:</strong> <?= ho_h($decision['decision']) ?></div>
          <div class="admin-data-row-note"><strong>Rule:</strong> <?= ho_h($decision['final_rule']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card-grid two">
  <article class="admin-secondary-card">
    <h2>Temporary Behaviors</h2>
    <?= ho_admin_doc_list($businessReviewLock['temporary_behaviors']) ?>
  </article>

  <article class="admin-secondary-card">
    <h2>Blocked Behaviors</h2>
    <?= ho_admin_doc_list($businessReviewLock['blocked_behaviors']) ?>
  </article>
</section>

<section class="admin-status success">
  <div class="admin-status-head"><strong>preview.php Permission Gate</strong></div>
  <h3>Allowed only if</h3>
  <?= ho_admin_doc_list($businessReviewLock['preview_php_permission_gate']['allowed_only_if']) ?>
</section>

<section class="admin-status error">
  <div class="admin-status-head"><strong>preview.php Still Blocked If</strong></div>
  <?= ho_admin_doc_list($businessReviewLock['preview_php_permission_gate']['still_blocked_if']) ?>
</section>

<section class="admin-card">
  <h2>Required 3–5 Prospect Validation Checks</h2>
  <?= ho_admin_doc_list($businessReviewLock['required_3_to_5_prospect_validation_checks']) ?>
</section>

<section class="admin-card">
  <h2>Top-Level Business Review Agenda</h2>
  <?= ho_admin_doc_list($businessReviewLock['top_level_business_review_agenda']) ?>
</section>

<section class="admin-card-grid two">
  <article class="admin-secondary-card">
    <h2>Next Direction Menu</h2>
    <?= ho_admin_doc_list($businessReviewLock['next_direction_menu']) ?>
  </article>

  <article class="admin-secondary-card">
    <h2>Recommended Next Step</h2>
    <p><?= ho_h($businessReviewLock['recommended_next_step']) ?></p>
  </article>
</section>

<section class="admin-reference-panel">
  <details>
    <summary>Machine-readable / reference</summary>
    <div class="admin-reference-grid">
      <a href="/sales-system.php?format=json">Business Review Lock JSON</a>
      <a href="/sales-research.php">Research</a>
      <a href="/sales-portal-dashboard.php">Prospects</a>
      <a href="/admin.php">Admin Hub</a>
    </div>
  </details>
</section>

<?php
ho_admin_render_end();
