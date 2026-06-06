<?php
declare(strict_types=1);

require_once __DIR__ . '/admin-core.php';

/**
 * v131a — Complete App Implementation Contract
 *
 * This page is intentionally not another workbench.
 * It is the binding implementation contract for rebuilding Hoosier Online as an Adam-only iPhone Safari mobile app.
 */

$completeChecklist = [
    'Source Prompt Contract',
    'Known Business Exclusion Contract',
    'Candidate Lead JSON Contract',
    'Intake Preview Contract',
    'Dedupe Contract',
    'Business Record Field Ownership',
    'Research Claim Boundary',
    'Sales Prep JSON Contract',
    'Computed /go Preview Rule',
    'Send Tray Contract',
    'Outcome Contract',
    'Failure / Partial Import Policy',
    'Legacy Migration Plan',
    'iPhone Screen Specifications',
    'Acceptance Tests',
];

$sourcePromptContract = [
    'purpose' => 'Help Adam generate candidate Indiana local service businesses while excluding already-known businesses and collecting the facts needed for later diagnosis and personalization.',
    'input' => [
        'market_target' => 'category/lane, Indiana gate, optional city/region as context, target count',
        'already_known_exclusion_packet' => 'compact list generated from existing records',
        'source_method' => 'GPT-assisted public research, pasted directory results, manual list, or future scraper output',
    ],
    'must_include' => [
        'candidate business name',
        'likely category',
        'city/state',
        'source URL',
        'website URL if found',
        'Facebook/public social profile if found',
        'Google profile URL if found',
        'public phone/email if customer-facing',
        'visible services',
        'visible trust signals',
        'visible weakness clues',
        'contact path clue',
        'personalization clue',
        'duplicate-risk clue',
        'source confidence',
        'intake_status recommendation',
    ],
    'rules' => [
        'Indiana is the broad location gate.',
        'City and service area are sourcing context only.',
        'Category is sourcing context only; adjacent local services are allowed.',
        'Do not require New Castle.',
        'Do not include businesses matching the known exclusion packet.',
        'Do not invent private facts.',
        'Return structured candidate rows only.',
        'Prefer public customer-facing information.',
    ],
];

$knownBusinessExclusion = [
    'definition' => 'A known business is any existing or held record that could reasonably match a new candidate.',
    'matching_fields' => [
        'business_slug',
        'business_name_current',
        'website_url',
        'google_profile_url',
        'facebook_url',
        'email_address',
        'phone_number',
        'normalized name + city/state',
    ],
    'packet_shape' => [
        'known_businesses' => [
            [
                'business_id' => 0,
                'business_slug' => 'existing-business-slug',
                'business_name' => 'Existing Business',
                'city' => 'City',
                'state' => 'IN',
                'website_url' => 'https://example.com',
                'facebook_url' => '',
                'google_profile_url' => '',
                'email_address' => '',
                'phone_number' => '',
            ],
        ],
    ],
    'prompt_instruction' => 'Do not return candidates that match these known businesses by name, slug, website, social URL, email, phone, or obvious same-business identity.',
];

$candidateJsonContract = [
    'candidate_batch' => [
        'batch_type' => 'source_candidates',
        'market_target' => [
            'category_context' => 'cleaning',
            'state_gate' => 'IN',
            'area_context' => 'Indiana or optional city/region',
            'source_method' => 'gpt_public_research',
        ],
    ],
    'candidates' => [[
        'raw_business_name' => 'Example Cleaning LLC',
        'likely_category' => 'cleaning',
        'city' => 'Anderson',
        'state' => 'IN',
        'source_url' => 'https://...',
        'website_url' => '',
        'facebook_url' => '',
        'google_profile_url' => '',
        'public_email' => '',
        'public_phone' => '',
        'visible_services' => ['residential cleaning', 'move-out cleaning'],
        'visible_trust_signals' => ['photos', 'reviews', 'years in business'],
        'visible_weakness_clues' => ['no website', 'contact only through Facebook'],
        'contact_path_clue' => 'Facebook message / public phone',
        'personalization_clue' => 'Uses before/after photos heavily.',
        'duplicate_risk_clue' => 'No obvious duplicate found.',
        'source_confidence' => 'medium',
        'intake_status_recommendation' => 'intake_ready',
    ]],
];

$intakePreviewContract = [
    'purpose' => 'Convert source candidates into a reviewed import decision before touching durable business records.',
    'groups' => [
        'new_business' => 'safe to insert',
        'update_existing' => 'matches existing record and can enrich it',
        'possible_duplicate' => 'hold for review',
        'needs_review' => 'missing important fields or uncertain identity',
        'reject' => 'outside Indiana, not a local service, obvious duplicate, or unusable',
    ],
    'preview_fields' => [
        'candidate_id',
        'decision',
        'decision_reason',
        'matched_business_id',
        'dedupe_score',
        'proposed_business_slug',
        'proposed_business_name_current',
        'proposed_business_type',
        'proposed_city',
        'proposed_state',
        'proposed_website_url',
        'proposed_facebook_url',
        'proposed_google_profile_url',
        'proposed_email',
        'proposed_phone',
        'source_context',
    ],
    'rule' => 'Source output is not a database import payload. It must pass through Intake Preview first.',
];

$dedupeContract = [
    'exact_duplicate' => [
        'same normalized website',
        'same Facebook URL',
        'same Google profile URL',
        'same email',
        'same phone',
        'same existing slug',
    ],
    'likely_duplicate' => [
        'same normalized name + same city/state',
        'similar name + same phone',
        'same business name + nearby city',
        'same owner-facing page but slightly different source listing',
    ],
    'not_duplicate_by_itself' => [
        'same category',
        'same city',
        'similar generic words like lawn, clean, pro, services',
        'same service area without matching identity/contact surface',
    ],
    'policy' => 'Possible duplicates go to Review, not direct import.',
];

$fieldOwnership = [
    'business_name_current' => 'Intake / Records',
    'business_slug' => 'Intake / Records',
    'business_type' => 'Intake / Records',
    'location_city' => 'Intake / Records',
    'location_state' => 'Intake / Records',
    'service_area_text' => 'Intake / Research',
    'website_url' => 'Intake / Research',
    'google_profile_url' => 'Intake / Research',
    'facebook_url' => 'Intake / Research',
    'email_address' => 'Intake / Research',
    'phone_number' => 'Intake / Research',
    'contact_readiness' => 'Research / Prep',
    'strength_keys_json' => 'Prep',
    'weakness_keys_json' => 'Prep',
    'recommendation_keys_json' => 'Prep',
    'primary_offer_path' => 'Prep',
    'preview_direction_keys_json' => 'Prep',
    'outreach_to' => 'Prep / Send',
    'outreach_subject' => 'Prep / Send',
    'outreach_body' => 'Prep / Send',
    'send_status' => 'Send',
    'outcome_status' => 'Send / Records',
];

$writePaths = [
    'BusinessRecord' => 'Intake writer or Records repair writer',
    'ResearchClaim' => 'Evidence importer only',
    'SalesPrep' => 'SalesPrep intake writer',
    'OutreachDraft' => 'SalesPrep intake or Send draft writer',
    'ContactAttempt' => 'Send outcome writer',
    'Outcome' => 'Send outcome writer',
    'ComputedPreview' => 'No write required; computed from business_slug and SalesPrep keys',
];

$salesPrepContract = [
    'purpose' => 'One GPT batch should prepare the sales opportunity: diagnosis keys + personalization + outreach draft.',
    'input' => [
        'contact-ready business records',
        'public contact path',
        'research claims / source clues',
        'computed preview_url = /go.php?slug={business_slug}',
    ],
    'output' => [
        'sales_prep_batch' => ['batch_type' => 'sales_prep'],
        'items' => [[
            'business_id' => 0,
            'business_slug' => 'business-slug',
            'diagnosis_status' => 'prep_ready',
            'strength_keys_json' => ['public_contact_visible'],
            'weakness_keys_json' => ['no_clear_website'],
            'recommendation_keys_json' => ['create_simple_front_door'],
            'primary_offer_path' => 'standard_front_door',
            'preview_direction_keys_json' => ['clean_trustworthy', 'local_service_plain', 'photo_forward'],
            'personalization_summary' => 'Short factual note based on public info.',
            'outreach_to' => 'public@example.com',
            'outreach_contact_method' => 'email',
            'outreach_subject' => 'Quick front-door preview for Business Name',
            'outreach_body' => 'Short respectful manual outreach copy with the computed /go link.',
            'warnings' => [],
            'next_step' => 'send_tray',
        ]],
    ],
    'rules' => [
        'Return only keys/structured draft data, not custom page HTML.',
        'No guaranteed leads, rankings, calls, or sales.',
        'No fake familiarity.',
        'No SMS.',
        'No AI calls.',
        'Use the computed /go link.',
    ],
];

$computedGoRule = [
    'rule' => 'The customer-facing preview URL is computed, not built.',
    'formula' => '/go.php?slug={business_slug}',
    'required_to_render' => [
        'business_slug',
        'business_name_current',
        'strength_keys_json',
        'weakness_keys_json',
        'recommendation_keys_json',
        'preview_direction_keys_json',
        'primary_offer_path',
    ],
    'do_not_store_unless_required' => [
        'go_slug',
        'go_path',
        'front_door_preview_status',
        'outreach_asset_url as proof of deterministic URL',
    ],
];

$sendTrayContract = [
    'purpose' => 'Show prepared outreach one sendable item at a time or as a simple queue; Adam manually sends outside the app.',
    'item_fields' => [
        'business name',
        'contact method',
        'outreach_to',
        'subject',
        'body',
        'computed /go link',
        'warnings',
        'copy controls',
        'manual outcome controls',
    ],
    'allowed_controls' => [
        'Copy To',
        'Copy Subject',
        'Copy Body',
        'Open Preview',
        'Mark Sent Manually',
        'Hold',
        'Skip',
        'Follow Up',
        'Not Interested',
        'Customer',
        'Do Not Contact',
    ],
    'forbidden_controls' => [
        'Send Email Automatically',
        'Send SMS',
        'Start AI Call',
        'Buy Domain',
        'Charge Payment',
    ],
];

$outcomeContract = [
    'statuses' => [
        'not_sent',
        'sent_manually',
        'follow_up_due',
        'no_response',
        'interested',
        'customer',
        'not_interested',
        'do_not_contact',
        'bad_contact',
        'manual_hold',
    ],
    'required_fields' => [
        'business_id',
        'status',
        'method',
        'date',
        'note',
        'follow_up_date optional',
    ],
];

$failurePolicy = [
    'Source' => 'No durable writes. Bad output can be discarded or regenerated.',
    'Intake' => 'Partial import allowed only after preview. Failed rows go to hold with reason.',
    'Records' => 'Manual repair writes one record at a time with visible before/after.',
    'Prep' => 'Partial import allowed. Invalid items go to Review with all validation reasons, not just first issue.',
    'Send' => 'No automatic send. Outcome write failure must not imply a message was sent.',
    'Global' => 'Every batch result must show total, ok, held, failed, and all failure reasons when available.',
];

$legacyMigration = [
    'sales-front-door-builder.php' => 'Legacy. Remove from primary flow. /go is computed.',
    'Preview Package Workbench' => 'Legacy/experimental. Not primary sales path.',
    'Package System' => 'Legacy/experimental. Not primary sales path.',
    'Domain Check' => 'Deferred. Not required before first sale motion.',
    'Materialization' => 'Deferred. Dynamic /go preview is enough for v1.',
    'Diagnosis Workbench' => 'Convert into Prep or replace with Sales Prep.',
    'Marketing Desk' => 'Convert into Send Tray.',
    'go_slug/go_path claims' => 'Ignore/tolerate existing data. Do not require going forward.',
    'package_status' => 'Legacy only unless explicitly revived.',
];

$screenSpecs = [
    'Source' => [
        'first_view' => 'Pick/confirm market target, see known-business exclusion count, copy source prompt.',
        'main_interaction' => 'Copy prompt, paste source results, send to Intake preview.',
        'should_not_show' => 'stat-card dashboard or raw database table',
    ],
    'Intake' => [
        'first_view' => 'Paste candidate batch or review pasted batch decisions.',
        'main_interaction' => 'Review New / Update / Hold / Reject groups and import selected.',
        'should_not_show' => 'generic importer internals',
    ],
    'Records' => [
        'first_view' => 'Search/repair surface for one business or duplicate set.',
        'main_interaction' => 'Fix identity/contact/category/source problems.',
        'should_not_show' => 'daily production queue unless entered from Review',
    ],
    'Prep' => [
        'first_view' => 'Businesses ready for Sales Prep and one combined prompt.',
        'main_interaction' => 'Copy Sales Prep prompt, paste Sales Prep result, import results.',
        'should_not_show' => 'separate build /go step',
    ],
    'Send' => [
        'first_view' => 'Next sendable draft with copy controls and preview link.',
        'main_interaction' => 'Copy/manual send/mark outcome.',
        'should_not_show' => 'automatic send button',
    ],
];

$roadmap = [
    'v132' => 'Contract-aligned simplification plan and page migration audit',
    'v133' => 'Source module: market target + known exclusion packet + source prompt builder',
    'v134' => 'Intake module: candidate JSON preview, dedupe, field mapping, import/hold',
    'v135' => 'Records repair module: search, fix, merge, restore, block',
    'v136' => 'Sales Prep module: combined diagnosis + outreach draft batch',
    'v137' => 'Send Tray: manual outreach and outcomes',
    'v138' => 'App Home: iPhone-first mode selector based on real app state',
];

function ho_contract_section(string $title, $content): void {
    ?>
    <section class="admin-app-contract-section">
      <h2><?= ho_h($title) ?></h2>
      <?php if (is_string($content)): ?>
        <p><?= ho_h($content) ?></p>
      <?php else: ?>
        <pre class="admin-contract-code"><?= ho_h(json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
      <?php endif; ?>
    </section>
    <?php
}

ho_admin_render_start(
    'app_contract_complete',
    'Complete App Contract',
    'Sales',
    'Complete <em>App Implementation Contract</em>',
    'Binding contract for Source, Intake, Records, Prep, Send, storage, failure policy, and iPhone app screens.'
);
?>

<section class="admin-app-contract-hero admin-app-contract-hero-v131a">
  <p>v131a complete contract</p>
  <h1>Stop patching pages. Build the mobile app.</h1>
  <strong>This contract defines the whole operating system: lead generation, candidate intake, table management, sales prep, computed /go previews, manual send, outcomes, and market coverage.</strong>
  <div class="admin-app-contract-actions">
    <a href="/sales-portal-dashboard.php">Command Center</a>
    <a href="/sales-app-contract.php">v131 Contract</a>
    <a href="/sales-system-check.php">System Check</a>
  </div>
</section>

<section class="admin-app-contract-section">
  <h2>Completeness checklist</h2>
  <ul>
    <?php foreach ($completeChecklist as $item): ?><li><?= ho_h($item) ?></li><?php endforeach; ?>
  </ul>
</section>

<?php
ho_contract_section('1. Source Prompt Contract', $sourcePromptContract);
ho_contract_section('2. Known Business Exclusion Contract', $knownBusinessExclusion);
ho_contract_section('3. Candidate Lead JSON Contract', $candidateJsonContract);
ho_contract_section('4. Intake Preview Contract', $intakePreviewContract);
ho_contract_section('5. Dedupe Contract', $dedupeContract);
ho_contract_section('6. Business Record Field Ownership', $fieldOwnership);
ho_contract_section('7. Write Path Policy By Object', $writePaths);
ho_contract_section('8. Sales Prep JSON Contract', $salesPrepContract);
ho_contract_section('9. Computed /go Preview Rule', $computedGoRule);
ho_contract_section('10. Send Tray Contract', $sendTrayContract);
ho_contract_section('11. Outcome Contract', $outcomeContract);
ho_contract_section('12. Failure / Partial Import Policy', $failurePolicy);
ho_contract_section('13. Legacy Migration Plan', $legacyMigration);
ho_contract_section('14. iPhone Screen Specifications', $screenSpecs);
ho_contract_section('15. Minimum Viable Rebuild Roadmap', $roadmap);
?>

<section class="admin-app-contract-section">
  <h2>Hard acceptance tests</h2>
  <ul>
    <li>A future build fails if Source does not produce a known-business exclusion packet.</li>
    <li>A future build fails if Candidate output can bypass Intake Preview.</li>
    <li>A future build fails if dedupe decisions are hidden.</li>
    <li>A future build fails if workflow state is written as fake research evidence.</li>
    <li>A future build fails if /go preview requires a builder step.</li>
    <li>A future build fails if the daily interface is still a stack of generic admin cards.</li>
    <li>A future build fails if lead generation, field conversion, or table management are treated as undefined support magic.</li>
    <li>A future build fails if it adds automatic sending, SMS, AI calls, payment, scraping automation, or domain purchasing without explicit approval.</li>
  </ul>
</section>

<?php ho_admin_render_end(); ?>
