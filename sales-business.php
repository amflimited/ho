<?php
declare(strict_types=1);
require __DIR__ . '/admin-core.php';
require __DIR__ . '/prospect-model.php';


if (!function_exists('ho_sales_business_local_triage_prompt')) {
    function ho_sales_business_local_triage_prompt(array $business, array $claims, array $evidence, int $businessId): string {
        $claimSummary = [];$triagePrompt='';
        foreach ($claims as $claim) {
            $field = (string)($claim['field_key'] ?? '');
            if (in_array($field, [
                'business_name','business_type','city','service_area','website_url','google_profile_url','facebook_url',
                'phone_number','email_address','single_customer_destination_present','contact_path_clarity',
                'marketing_clearance_status','primary_sales_angle'
            ], true)) {
                $claimSummary[] = [
                    'field_key' => $field,
                    'claim_value' => (string)($claim['claim_value'] ?? ''),
                    'confidence_level' => (string)($claim['confidence_level'] ?? ''),
                    'claim_status' => (string)($claim['claim_status'] ?? ''),
                ];
            }
        }

        $evidenceSummary = [];
        foreach ($evidence as $source) {
            $evidenceSummary[] = [
                'source_type' => (string)($source['source_type'] ?? ''),
                'source_url' => (string)($source['source_url'] ?? ''),
                'source_title' => (string)($source['source_title'] ?? ''),
                'notes' => (string)($source['notes'] ?? ''),
            ];
        }

        $packet = [
            'business_id' => $businessId,
            'business' => [
                'business_slug' => (string)($business['business_slug'] ?? ''),
                'business_name_current' => (string)($business['business_name_current'] ?? ''),
                'business_type' => (string)($business['business_type'] ?? ''),
                'location_city' => (string)($business['location_city'] ?? ''),
                'location_state' => (string)($business['location_state'] ?? ''),
                'service_area_text' => (string)($business['service_area_text'] ?? ''),
                'marketing_clearance_status' => (string)($business['marketing_clearance_status'] ?? ''),
            ],
            'known_evidence_sources' => $evidenceSummary,
            'important_existing_claims' => $claimSummary,
        ];

        $json = json_encode($packet, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
You are doing lightweight candidate triage for Hoosier Online.

Goal:
Quickly decide whether this candidate deserves full research, manual checking, or elimination.

Do NOT deeply research every field.
Do NOT inspect booking/payment/proof details unless an obvious public surface exists.
Do NOT write a full Sales Research payload.
Do NOT invent private facts.
Use only public customer-facing information.

Current candidate data:
$json

Answer only these questions:
1. Is this a real identifiable business?
2. Is it local and in the right category?
3. Can we find at least one usable public contact path?
4. Does it have enough public surface to justify full research?
5. Should we eliminate, hold, manually check, or fully research?

Triage statuses:
- research_ready: identity/contact/surface are good enough for full Business Refinement.
- quick_hold: potentially useful, but not enough public proof yet.
- needs_identity_check: similar names, weak identifiers, or wrong-business risk.
- no_public_surface: no website/Facebook/Google/directory surface found beyond thin identifiers.
- bad_fit: wrong category, too large, too polished, or not a good first-lane prospect.
- duplicate_or_confused: multiple similar businesses or mismatched identity/domain.
- exclude: do not pursue.

Recommended next steps:
- full_research
- manual_check
- skip

Return ONLY valid JSON in this structure:

{
  "triage_batch": {
    "category": "lawn_care",
    "target_area": "New Castle, IN",
    "triage_method": "manual_gpt_assisted_candidate_triage"
  },
  "triage_results": [
    {
      "business_id": $businessId,
      "business_slug": "{$business['business_slug']}",
      "business_name": "{$business['business_name_current']}",
      "status": "research_ready|quick_hold|needs_identity_check|no_public_surface|bad_fit|duplicate_or_confused|exclude",
      "verified_identifiers": [
        {
          "type": "website|facebook|google_profile|phone|email|directory|address",
          "value": "",
          "confidence": "high|medium|low"
        }
      ],
      "missing_basics": [],
      "reason": "",
      "recommended_next_step": "full_research|manual_check|skip"
    }
  ]
}

PROMPT;
    }
}



if (!function_exists('ho_sales_business_local_refinement_prompt')) {
    function ho_sales_business_local_array_text(array $items): string {
        return implode("\n", array_map(static fn($v) => (string)$v, $items));
    }

    function ho_sales_business_local_refinement_prompt(array $business, array $claims, array $evidence, int $businessId): string {
        $allowedFieldKeys = [
            'business_name','business_type','business_description','owner_name','brand_name_consistency',
            'street_address','city','state','service_area','hours_of_operation','location_consistency',
            'website_url','google_profile_url','facebook_url','instagram_url','directory_listing_url',
            'single_customer_destination_present','public_presence_consistency','phone_number','email_address',
            'contact_form_present','request_form_present','facebook_message_enabled','primary_cta_text',
            'confirmation_message_present','contact_path_clarity','services_list_present','products_list_present',
            'menu_present','pricing_present','package_or_offer_present','service_descriptions_clear',
            'customer_use_case_clear','photos_present','photo_quality','before_after_present','portfolio_present',
            'reviews_present','review_count','average_rating','testimonials_present','licenses_certifications_present',
            'recent_activity_present','booking_link_present','appointment_form_present','estimate_request_form_present',
            'calendar_link_present','preferred_time_field_present','availability_note_present','booking_expectation_text',
            'payment_link_present','deposit_link_present','invoice_link_present','checkout_link_present',
            'payment_provider_visible','payment_terms_present','payment_path_clarity','broken_links_present',
            'conflicting_phone_numbers','conflicting_hours','dead_website','bad_mobile_layout','missing_images',
            'old_posts_or_stale_activity','duplicate_profiles','domain_confusion','too_much_scrolling_required',
            'scattered_customer_path','primary_sales_angle','recommended_package','recommended_design',
            'recommended_features','marketing_clearance_score','marketing_clearance_status'
        ];

        $requirements = [
            'find_me.business_identity_clear','find_me.location_or_service_area_clear','find_me.public_search_presence','find_me.single_customer_destination',
            'trust_me.appears_active','trust_me.has_proof','trust_me.has_consistent_identity','trust_me.has_credible_presentation',
            'contact_me.clear_primary_contact','contact_me.structured_request_path','contact_me.customer_next_step_clear',
            'show_me.services_visible','show_me.products_or_work_visible','show_me.offer_clarity','show_me.visual_proof',
            'book_me.request_time_possible','book_me.appointment_or_estimate_path','book_me.booking_expectation_clear',
            'pay_me.payment_path_exists','pay_me.deposit_path_exists','pay_me.payment_instructions_clear',
            'fix_me.broken_or_conflicting_info','fix_me.outdated_presence','fix_me.technical_mess','fix_me.customer_path_mess'
        ];

        $existingClaimKeys = [];
        $claimSummary = [];$triagePrompt='';$refinementPrompt='';
        foreach ($claims as $claim) {
            $field = (string)($claim['field_key'] ?? '');
            if ($field !== '') {
                $existingClaimKeys[$field] = true;
            }
            $claimSummary[] = [
                'field_key' => $field,
                'claim_value' => (string)($claim['claim_value'] ?? ''),
                'confidence_level' => (string)($claim['confidence_level'] ?? ''),
                'confidence_score' => (float)($claim['confidence_score'] ?? 0),
                'claim_status' => (string)($claim['claim_status'] ?? ''),
                'evidence_note' => (string)($claim['evidence_note'] ?? ''),
            ];
        }

        $priorityFields = [
            'business_name','business_type','city','service_area','website_url','google_profile_url','facebook_url',
            'phone_number','email_address','contact_form_present','request_form_present','services_list_present',
            'photos_present','reviews_present','recent_activity_present','single_customer_destination_present',
            'contact_path_clarity','primary_sales_angle','recommended_package','recommended_design'
        ];

        $missing = [];
        foreach ($priorityFields as $field) {
            if (empty($existingClaimKeys[$field])) {
                $missing[] = $field;
            }
        }

        $evidenceSummary = [];
        foreach ($evidence as $source) {
            $evidenceSummary[] = [
                'source_type' => (string)($source['source_type'] ?? ''),
                'source_url' => (string)($source['source_url'] ?? ''),
                'source_title' => (string)($source['source_title'] ?? ''),
                'notes' => (string)($source['notes'] ?? ''),
            ];
        }

        $knownPacket = [
            'business_id' => $businessId,
            'business' => [
                'business_slug' => (string)($business['business_slug'] ?? ''),
                'business_name_current' => (string)($business['business_name_current'] ?? ''),
                'business_type' => (string)($business['business_type'] ?? ''),
                'location_city' => (string)($business['location_city'] ?? ''),
                'location_state' => (string)($business['location_state'] ?? ''),
                'service_area_text' => (string)($business['service_area_text'] ?? ''),
                'marketing_clearance_status' => (string)($business['marketing_clearance_status'] ?? ''),
                'marketing_clearance_score' => (string)($business['marketing_clearance_score'] ?? ''),
                'recommended_package' => (string)($business['recommended_package'] ?? ''),
                'recommended_design' => (string)($business['recommended_design'] ?? ''),
            ],
            'known_evidence_sources' => $evidenceSummary,
            'existing_claims' => $claimSummary,
            'missing_or_unconfirmed_priority_fields' => $missing,
        ];

        $knownJson = json_encode($knownPacket, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $fields = ho_sales_business_local_array_text($allowedFieldKeys);
        $reqs = ho_sales_business_local_array_text($requirements);

        return <<<PROMPT
You are refining one existing Hoosier Online Sales Portal business record.

Goal:
Use the current known business data below to return a stronger importable JSON payload for the same business, but do it efficiently. Do not exhaustively research every possible field.

Core rule:
Do the smallest amount of public research needed to decide whether this business is identifiable, contactable, and a possible Front Door prospect.

Important:
- Return ONLY valid JSON.
- Do not include markdown.
- Do not invent private facts.
- Use only public customer-facing information.
- Confirm what is true.
- Mark what is missing when it appears not to exist.
- Mark what cannot be confirmed as weak_inference or needs_review.
- Do not use owner_name unless clearly public and high confidence.
- Do not include sensitive personal assumptions.
- Do not create new field_key values.
- Do not search every detailed field just because the field exists.
- A confirmed absence is useful, but only confirm absences that matter to the current surface.

Current known system data:
$knownJson

Allowed field_key values:
$fields

Allowed confidence_level values:
confirmed, likely, inferred, weak_inference, missing, conflicting, rejected

Allowed claim_status values:
active, needs_review, missing, conflicting, rejected, superseded

Allowed source_type values:
website, google_business_profile, facebook, instagram, directory, email, manual_observation, phone_call, customer_submission, other

Allowed supports_me_category values:
find_me, trust_me, contact_me, show_me, book_me, pay_me, fix_me

Allowed supports_requirement_key values:
$reqs

Efficient research rules:

Tier 1 — identity and contact first:
1. Confirm business_name, business_type, city/service_area.
2. Look for website_url, google_profile_url, facebook_url, phone_number, email_address.
3. If you cannot confirm the same business identity across at least two public identifiers, keep marketing_clearance_status as needs_review or hold.

Tier 2 — only inspect surfaces that exist:
- If no website appears to exist, do not deeply inspect website-only fields.
  Instead, mark website_url as missing and, if appropriate, mark contact_form_present, request_form_present, booking_link_present, payment_link_present, and single_customer_destination_present as missing or inferred missing because no official website/customer destination was found.
- If a Facebook page exists, inspect Facebook only for broad signs: recent_activity_present, photos_present, services_list_present, facebook_message_enabled, phone/email if visible.
- If a Google profile exists, inspect Google only for broad signs: reviews_present, review_count, average_rating, photos_present, phone_number, hours_of_operation, service area if visible.
- Only look for before_after_present or portfolio_present if photos/work examples are already visible on an existing public surface.
- Only look for booking/payment fields if a website, booking link, payment link, or clear checkout/request surface exists.
- Do not search for payment details unless there is a visible payment surface.
- Do not search Instagram/directories unless they are already provided or show up as an obvious primary public identifier.

Tier 3 — stop conditions:
Stop researching deeper once you can answer:
1. Is this the right business?
2. Can a customer contact them?
3. Is there a clean customer destination?
4. Is there enough visible proof/activity to justify outreach?
5. What is the practical Front Door opportunity?

Minimum useful output:
Return enough claims to support a decision. Do not return a giant claim list.

Preferred claim count:
- 8 to 18 claims for a thin business.
- 12 to 25 claims for a business with website/Facebook/Google surfaces.
- Do not exceed 30 claims unless the business has unusually strong public data.

Prioritize these field groups:
1. Identity: business_name, business_type, city, service_area, brand_name_consistency.
2. Public surfaces: website_url, google_profile_url, facebook_url.
3. Contactability: phone_number, email_address, contact_form_present, request_form_present, facebook_message_enabled, contact_path_clarity.
4. Customer path: single_customer_destination_present, services_list_present, service_descriptions_clear, primary_cta_text.
5. Proof/activity if visible: photos_present, reviews_present, review_count, average_rating, recent_activity_present.
6. Problems: dead_website, bad_mobile_layout, duplicate_profiles, domain_confusion, scattered_customer_path, broken_links_present.
7. Sales conclusion: primary_sales_angle, recommended_package, recommended_design, marketing_clearance_status, marketing_clearance_score.

How to handle missing:
- If a website is not found, mark website_url missing.
- If there is no website, do not separately hunt for website-only details.
- If no official single destination is found, mark single_customer_destination_present missing or inferred missing.
- If contact is only an email/phone/Facebook page, mark contact_path_clarity as partial or needs_review.
- If a thing may exist but you did not have enough evidence, use weak_inference or needs_review instead of confirmed missing.
- Missing does not mean bad. Missing means the system should know the customer path lacks that asset.

Marketing clearance guidance:
- cleared: strong identity, clear contact path, obvious customer-path issue, enough public proof/activity.
- warm_clear: good possible fit, but some verification needed.
- needs_review: useful but identity/contact/fit needs review.
- hold: not enough evidence yet.
- skip: not worth current effort.
- blocked: do not contact.

Recommended package:
- standard: simple Front Door setup is enough.
- managed: business likely needs ongoing cleanup/help due to scattered, outdated, or inconsistent public presence.
- unknown: not enough information.

Recommended design:
Use only seeded/internal direction names when reasonable, such as:
- simple_service_card
- local_pro
- quote_request
- before_after_proof
- mobile_call_now
- neighborhood_handyman
- repair_estimate
- recurring_service

For lawn care, recurring_service or simple_service_card is usually enough unless evidence supports another option.

Return ONLY this JSON structure:

{
  "business": {
    "business_slug": "",
    "business_name_current": "",
    "business_type": "",
    "location_city": "",
    "location_state": "IN",
    "service_area_text": ""
  },
  "evidence_sources": [
    {
      "source_type": "website|google_business_profile|facebook|instagram|directory|email|manual_observation|phone_call|customer_submission|other",
      "source_url": "",
      "source_title": "",
      "capture_status": "manual",
      "raw_excerpt": "",
      "notes": ""
    }
  ],
  "claims": [
    {
      "field_key": "",
      "claim_value": "",
      "normalized_value": "",
      "confidence_level": "",
      "confidence_score": 0,
      "claim_status": "active|needs_review|missing|conflicting|rejected|superseded",
      "source_type": "",
      "source_url": "",
      "source_label": "",
      "evidence_note": "",
      "supports_me_category": "",
      "supports_requirement_key": "",
      "evidence_source_index": 0
    }
  ],
  "marketing_clearance": {
    "business_activity_score": 0,
    "need_score": 0,
    "fit_score": 0,
    "confidence_score": 0,
    "contactability_score": 0,
    "buildability_score": 0,
    "marketing_clearance_score": 0,
    "marketing_clearance_status": "cleared|warm_clear|needs_review|hold|skip|blocked",
    "recommended_package": "standard|managed|unknown",
    "recommended_design": "",
    "reason": ""
  },
  "notes": []
}

PROMPT;
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
try { $business=$id>0?ho_salesportal_get_business_by_id($id):null; $evidence=$business?ho_salesportal_business_evidence($id):[]; $claims=$business?ho_salesportal_business_claims($id):[]; $claimGroups=ho_salesportal_group_claims_by_category($claims); $claimSummary=ho_salesportal_claim_summary($claims); $triagePrompt=$business?ho_sales_business_local_triage_prompt($business,$claims,$evidence,$id):''; $refinementPrompt=$business?ho_sales_business_local_refinement_prompt($business,$claims,$evidence,$id):''; $dbError=null; }
catch(Throwable $e){$business=null;$evidence=[];$claims=[];$claimGroups=[];$claimSummary=[];$triagePrompt='';$refinementPrompt='';$dbError=$e->getMessage();}
function ho_salesportal_readiness_text(string $status): string { return ['cleared'=>'Ready for preview and outreach.','warm_clear'=>'Close to ready. Keep any weak facts out of outreach.','needs_review'=>'Not ready yet. Review claims first.','hold'=>'More research needed.','skip'=>'Low priority / low value.','blocked'=>'Do not contact.'][$status] ?? 'Needs manual review.'; }
$titleName = $business ? ($business['business_name_current'] ?: $business['business_slug']) : 'Business';
ho_admin_render_start('sales_prospects','Sales Business View','Sales','Business <em>View</em>','Internal business operator view: verify claims, readiness, option assignment, and whether a future preview would be safe.');
?>
<section class="admin-process-note">
  <strong>Internal review only:</strong> do not send this page to a prospect. Use it to decide whether the stored research can support a future preview.
</section>


<section class="admin-card">
  <h2>Business Operator Steps</h2>
  <div class="admin-mini-flow">
    <span><b>1</b> Triage first</span>
    <span><b>2</b> Research only if useful</span>
    <span><b>3</b> Import on Prospects</span>
    <span><b>4</b> Decide next action</span>
  </div>
</section>

<?php if ($dbError !== null): ?><section class="admin-status error"><div class="admin-status-head"><strong>Database Error</strong></div><p><?= ho_h($dbError) ?></p></section><?php elseif (!$business): ?><section class="admin-status error"><div class="admin-status-head"><strong>Not Found</strong></div><p>No business found for this ID.</p></section><?php else: ?>
<section class="admin-card"><h2><?= ho_h($titleName) ?></h2><p class="admin-muted"><?= ho_h((string)$business['business_type']) ?><?php if ($business['location_city'] || $business['location_state']): ?> · <?= ho_h(trim((string)$business['location_city'] . ', ' . (string)$business['location_state'], ', ')) ?><?php endif; ?><?php if (!empty($business['service_area_text'])): ?> · <?= ho_h((string)$business['service_area_text']) ?><?php endif; ?></p><div class="admin-stat-grid"><article class="admin-stat-card"><strong><?= ho_h($business['marketing_clearance_score'] !== null ? (string)$business['marketing_clearance_score'] : '—') ?></strong><span>Clearance Score</span></article><article class="admin-stat-card"><strong><?= ho_h((string)count($claims)) ?></strong><span>Claims Stored</span></article><article class="admin-stat-card"><strong><?= ho_h((string)count($evidence)) ?></strong><span>Evidence Sources</span></article><article class="admin-stat-card"><strong><?= ho_h((string)($claimSummary['needs_review'] + $claimSummary['conflicting'])) ?></strong><span>Review Flags</span></article></div></section>


<section class="admin-card admin-triage-prompt-card">
  <h2>Candidate Triage Prompt</h2>
  <p class="admin-muted">Use this before full research. It asks GPT to decide whether this candidate deserves full refinement, manual checking, or elimination.</p>
  <textarea id="triagePromptBox" class="admin-textarea"><?= ho_h($triagePrompt ?? '') ?></textarea>
  <p class="admin-next-row">
    <button class="admin-btn admin-btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('triagePromptBox').value)">Copy Triage Prompt</button>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Paste Result on Prospects</a>
  </p>
</section>

<section class="admin-card admin-refinement-prompt-card">
  <h2>Business Refinement Prompt</h2>
  <p class="admin-muted">Copy this prompt after a rough import. It tells GPT what the system already knows, what is missing, and how to return a stronger importable JSON update.</p>
  <?php if (trim((string)$refinementPrompt) === ''): ?>
    <div class="admin-empty-state">Refinement prompt could not be generated for this business record.</div>
  <?php else: ?>
    <textarea id="refinementPromptBox" class="admin-textarea"><?= ho_h($refinementPrompt) ?></textarea>
    <p class="admin-next-row">
      <button class="admin-btn admin-btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('refinementPromptBox').value)">Copy Refinement Prompt</button>
      <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Paste Result on Prospects</a>
    </p>
  <?php endif; ?>
</section>

<section class="admin-card"><h2>Workflow Position</h2><div class="admin-workflow-strip"><span>Business</span><span>Evidence</span><span>Claims</span><span>Clearance</span><span>Preview</span><span>Outreach</span><span>Build</span></div><p class="admin-muted"><strong>Status:</strong> <?= ho_h(ucwords(str_replace('_',' ',(string)$business['marketing_clearance_status']))) ?> · <?= ho_h(ho_salesportal_readiness_text((string)$business['marketing_clearance_status'])) ?></p><p class="admin-muted"><strong>Recommended package:</strong> <?= ho_h((string)$business['recommended_package']) ?><?= !empty($business['recommended_design']) ? ' · ' . ho_h((string)$business['recommended_design']) : '' ?></p></section>
<section class="admin-card-grid two"><section class="admin-card"><h2>Best Signals</h2><?php if (empty($claimSummary['top_strengths'])): ?><p class="admin-muted">No strengths surfaced yet.</p><?php else: ?><?= ho_admin_doc_list($claimSummary['top_strengths']) ?><?php endif; ?></section><section class="admin-card"><h2>Main Problems</h2><?php if (empty($claimSummary['top_issues'])): ?><p class="admin-muted">No major issues surfaced yet.</p><?php else: ?><?= ho_admin_doc_list($claimSummary['top_issues']) ?><?php endif; ?></section></section>
<section class="admin-card"><h2>Claims by Category</h2><?php if (empty($claimGroups)): ?><p class="admin-muted">No claims recorded.</p><?php else: ?><div class="admin-section"><?php foreach ($claimGroups as $category=>$items): ?><section class="admin-secondary-card"><h3><?= ho_h(ucwords(str_replace('_',' ',(string)$category))) ?></h3><table class="admin-file-table"><thead><tr><th>Field</th><th>Value</th><th>Confidence</th></tr></thead><tbody><?php foreach ($items as $claim): ?><tr><td><code><?= ho_h((string)$claim['field_key']) ?></code></td><td><?= ho_h((string)$claim['claim_value']) ?><?php if (!empty($claim['evidence_note'])): ?><div class="admin-muted"><?= ho_h((string)$claim['evidence_note']) ?></div><?php endif; ?></td><td><?= ho_h((string)$claim['confidence_level']) ?><div class="admin-muted"><?= ho_h((string)$claim['confidence_score']) ?></div></td></tr><?php endforeach; ?></tbody></table></section><?php endforeach; ?></div><?php endif; ?></section>
<section class="admin-card"><h2>Evidence Sources</h2><?php if (empty($evidence)): ?><p class="admin-muted">No evidence sources recorded.</p><?php else: ?><div class="admin-data-list"><?php foreach ($evidence as $source): ?><div class="admin-data-row"><div><div class="admin-data-row-title"><?= ho_h((string)$source['source_type']) ?><?= !empty($source['source_title']) ? ' · ' . ho_h((string)$source['source_title']) : '' ?></div><?php if (!empty($source['notes'])): ?><div class="admin-data-row-note"><?= ho_h((string)$source['notes']) ?></div><?php endif; ?></div><?php if (!empty($source['source_url'])): ?><a class="admin-btn admin-btn-secondary" href="<?= ho_h((string)$source['source_url']) ?>" target="_blank" rel="noopener">Open</a><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?></section>
<section class="admin-card"><h2>Next Action</h2><p><?= ho_h(ho_salesportal_readiness_text((string)$business['marketing_clearance_status'])) ?></p><p><a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Back to Prospects</a> <a class="admin-btn admin-btn-primary" href="/sales-research.php">Add Research</a></p></section>
<?php endif; ?>

<section class="admin-action-dock" id="business-bottom-dock">
  <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php#dashboard-import">Paste Result</a>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
  <a class="admin-btn admin-btn-secondary" href="/admin.php">Admin</a>
</section>

<?php ho_admin_render_end(); ?>
