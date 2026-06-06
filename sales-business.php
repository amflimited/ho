<?php
declare(strict_types=1);
require __DIR__ . '/admin-core.php';
require __DIR__ . '/prospect-model.php';


if (!function_exists('ho_sales_business_local_setup_prompt')) {
    function ho_sales_business_local_setup_prompt(array $business, array $claims, array $evidence, int $businessId): string {
        $importantClaims=[];
        foreach($claims as $claim){
            $field=(string)($claim['field_key'] ?? '');
            if(in_array($field,['business_name','business_type','city','service_area','website_url','google_profile_url','facebook_url','phone_number','email_address','contact_form_present','request_form_present','facebook_message_enabled','single_customer_destination_present','contact_path_clarity','primary_sales_angle','recommended_design','recommended_features','recommended_package','marketing_clearance_status'],true)){
                $importantClaims[]=['field_key'=>$field,'claim_value'=>(string)($claim['claim_value'] ?? ''),'confidence_level'=>(string)($claim['confidence_level'] ?? ''),'claim_status'=>(string)($claim['claim_status'] ?? ''),'evidence_note'=>(string)($claim['evidence_note'] ?? '')];
            }
        }
        $packet=['setup_goal'=>'single_business_preview_contact_setup','business'=>['business_id'=>$businessId,'business_slug'=>(string)($business['business_slug'] ?? ''),'business_name'=>(string)($business['business_name_current'] ?? ''),'business_type'=>(string)($business['business_type'] ?? ''),'city'=>(string)($business['location_city'] ?? ''),'state'=>(string)($business['location_state'] ?? ''),'service_area'=>(string)($business['service_area_text'] ?? ''),'current_clearance'=>(string)($business['marketing_clearance_status'] ?? ''),'recommended_package'=>(string)($business['recommended_package'] ?? ''),'recommended_design'=>(string)($business['recommended_design'] ?? '')],'important_claims'=>$importantClaims];
        $json=json_encode($packet, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        return <<<PROMPT
You are setting up one Hoosier Online business for preview readiness and contact prep.

Goal:
Decide the backend setup path, preview approach, contact readiness, and best contact method.

Do NOT do full research.
Do NOT build preview.php.
Do NOT write long sales copy.
Use only public customer-facing information and the provided record.

Current business:
$json

Return ONLY valid JSON in this exact structure:

{
  "setup_batch": {"method": "preview_contact_setup", "category": "local_service", "target_area": "New Castle, IN"},
  "setup_results": [
    {
      "business_id": $businessId,
      "business_slug": "{$business['business_slug']}",
      "business_name": "{$business['business_name_current']}",
      "setup_path": "research_with_website|proceed_no_website|do_not_proceed",
      "preview_approach": "website_fix_preview|simple_front_door_preview|local_service_card|quote_request_page|recurring_service_page|none",
      "contact_readiness": "ready_to_contact|needs_manual_check|not_contactable|do_not_contact",
      "best_contact_method": "website_contact_form|public_email|facebook_message|phone_manual_later|none",
      "contact_value": "",
      "contact_angle": "",
      "must_verify_before_contact": [],
      "reason": ""
    }
  ]
}

Rules:
- If there is a real website/contact form, use research_with_website and website_contact_form when appropriate.
- If there is no website but the business appears real and contactable, use proceed_no_website and simple_front_door_preview.
- If identity/contact are too weak, use needs_manual_check or do_not_contact.
- Keep contact_angle short and practical.

PROMPT;
    }
}



if (!function_exists('ho_sales_business_local_triage_prompt')) {
    function ho_sales_business_local_triage_prompt(array $business, array $claims, array $evidence, int $businessId): string {
        $claimSummary = [];$setupPrompt='';$triagePrompt='';
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
    "category": "local_service",
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
        $claimSummary = [];$setupPrompt='';$triagePrompt='';$refinementPrompt='';
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
try { $business=$id>0?ho_salesportal_get_business_by_id($id):null; $evidence=$business?ho_salesportal_business_evidence($id):[]; $claims=$business?ho_salesportal_business_claims($id):[]; $claimGroups=ho_salesportal_group_claims_by_category($claims); $claimSummary=ho_salesportal_claim_summary($claims); $setupPrompt=$business?ho_sales_business_local_setup_prompt($business,$claims,$evidence,$id):''; $triagePrompt=$business?ho_sales_business_local_triage_prompt($business,$claims,$evidence,$id):''; $refinementPrompt=$business?ho_sales_business_local_refinement_prompt($business,$claims,$evidence,$id):''; $dbError=null; }
catch(Throwable $e){$business=null;$evidence=[];$claims=[];$claimGroups=[];$claimSummary=[];$setupPrompt='';$triagePrompt='';$refinementPrompt='';$dbError=$e->getMessage();}
function ho_salesportal_readiness_text(string $status): string { return ['cleared'=>'Ready for preview and outreach.','warm_clear'=>'Close to ready. Keep any weak facts out of outreach.','needs_review'=>'Not ready yet. Review claims first.','hold'=>'More research needed.','skip'=>'Low priority / low value.','blocked'=>'Do not contact.'][$status] ?? 'Needs manual review.'; }
$titleName = $business ? ($business['business_name_current'] ?: $business['business_slug']) : 'Business';
ho_admin_render_start(
    'cases',
    'Case File',
    'Sales',
    'Case <em>File</em>',
    'One record. One next decision.'
);
?>

<style>
/* v097 Case File viewport lock */
html, body {
  max-width: 100%;
  overflow-x: hidden !important;
}
body.admin-experience-v082,
body.admin-experience-v082 * {
  box-sizing: border-box;
}
body.admin-experience-v082 .admin-shell,
body.admin-experience-v082 .admin-page,
body.admin-experience-v082 .admin-main,
body.admin-experience-v082 main,
body.admin-experience-v082 section,
body.admin-experience-v082 .admin-card {
  max-width: 100vw !important;
  overflow-x: hidden;
}
body.admin-experience-v082 .admin-card {
  width: auto !important;
}
body.admin-experience-v082 textarea,
body.admin-experience-v082 .admin-textarea,
body.admin-experience-v082 pre,
body.admin-experience-v082 code,
body.admin-experience-v082 .admin-code,
body.admin-experience-v082 .admin-auto-collapsed-body,
body.admin-experience-v082 .admin-case-prompt,
body.admin-experience-v082 .admin-case-prompt details,
body.admin-experience-v082 .admin-case-details,
body.admin-experience-v082 .admin-case-details details {
  max-width: 100% !important;
  width: 100% !important;
  min-width: 0 !important;
  box-sizing: border-box !important;
}
body.admin-experience-v082 textarea,
body.admin-experience-v082 .admin-textarea {
  display: block !important;
  white-space: pre-wrap !important;
  overflow-wrap: anywhere !important;
  word-break: break-word !important;
  overflow-x: auto !important;
}
body.admin-experience-v082 pre,
body.admin-experience-v082 code,
body.admin-experience-v082 .admin-code {
  white-space: pre-wrap !important;
  overflow-wrap: anywhere !important;
  word-break: break-word !important;
}
body.admin-experience-v082 table {
  max-width: 100% !important;
  width: 100% !important;
  table-layout: fixed !important;
}
body.admin-experience-v082 th,
body.admin-experience-v082 td {
  overflow-wrap: anywhere !important;
  word-break: break-word !important;
}
@media (max-width: 760px) {
  body.admin-experience-v082 .admin-card {
    margin-left: 10px !important;
    margin-right: 10px !important;
    width: calc(100vw - 20px) !important;
  }
}
</style>



<style>
/* v096 collapse raw case machinery */
.admin-auto-collapsed-section,
.admin-server-collapsed-section,
.admin-case-details {
  opacity: .86;
}
.admin-auto-collapsed-details,
.admin-case-details > details {
  border: 1px solid var(--ho-border-soft);
  border-radius: 16px;
  background: rgba(255,255,255,.62);
  overflow: hidden;
}
.admin-auto-collapsed-details > summary,
.admin-case-details > details > summary {
  padding: 13px 14px;
  font-weight: 900;
  cursor: pointer;
  list-style: none;
}
.admin-auto-collapsed-details > summary::-webkit-details-marker,
.admin-case-details > details > summary::-webkit-details-marker {
  display: none;
}
.admin-auto-collapsed-body {
  padding: 10px 12px 12px;
  overflow-x: auto;
}
.admin-auto-collapsed-body table {
  font-size: 13px;
}
</style>


<style>
/* v095 case action-first cleanup */
.admin-case-details{opacity:.86}
.admin-case-details details,.admin-case-prompt details{border:1px solid var(--ho-border-soft);border-radius:16px;background:rgba(255,255,255,.62);overflow:hidden}
.admin-case-details summary,.admin-case-prompt summary{padding:13px 14px;font-weight:900;cursor:pointer;list-style:none}
.admin-case-details summary::-webkit-details-marker,.admin-case-prompt summary::-webkit-details-marker{display:none}
.admin-case-details table,.admin-case-details .admin-data-list,.admin-case-details .admin-stat-grid{margin:10px 12px 12px}
.admin-case-prompt textarea,.admin-case-prompt .admin-next-row,.admin-case-prompt .admin-empty-state,.admin-case-prompt p.admin-muted{margin:10px 12px 12px}
</style>



<?php
function ho_case_claim_value(array $claims, string $fieldKey): string {
    foreach ($claims as $claim) {
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_case_setup_path(array $claims): string {
    foreach (['setup_path','preview_setup_path'] as $field) {
        $value = strtolower(ho_case_claim_value($claims, $field));
        if ($value !== '') return $value;
    }
    $angle = strtolower(ho_case_claim_value($claims, 'primary_sales_angle'));
    foreach (['research_with_website','website_fix_preview','proceed_no_website','simple_front_door','simple_front_door_preview','ready_to_contact','do_not_proceed','blocked'] as $candidate) {
        if (str_contains($angle, $candidate)) return $candidate;
    }
    return '';
}

function ho_case_contact_readiness(array $claims): string {
    foreach (['contact_readiness','contact_path_clarity','contact_method_status'] as $field) {
        $value = strtolower(ho_case_claim_value($claims, $field));
        if ($value !== '') return $value;
    }
    return '';
}

function ho_case_has_setup_result(array $claims): bool {
    return ho_case_setup_path($claims) !== ''
        || ho_case_contact_readiness($claims) !== ''
        || ho_case_claim_value($claims, 'best_contact_method') !== ''
        || ho_case_claim_value($claims, 'preview_approach') !== ''
        || ho_case_claim_value($claims, 'must_verify_before_contact') !== '';
}

function ho_case_state_label(array $business, array $claims): string {
    $setupPath = ho_case_setup_path($claims);
    $contact = ho_case_contact_readiness($claims);
    $status = strtolower((string)($business['marketing_clearance_status'] ?? ''));

    if (str_contains($setupPath, 'do_not_proceed') || str_contains($setupPath, 'blocked') || in_array($status, ['skip','blocked'], true)) {
        return 'Blocked / Skip';
    }

    if (str_contains($setupPath, 'research_with_website') || str_contains($setupPath, 'website_fix') || str_contains($contact, 'needs_manual_check') || str_contains($contact, 'manual_check')) {
        return 'Need Research';
    }

    if (str_contains($contact, 'not_contactable') || str_contains($contact, 'bad_contact')) {
        return 'Blocked / Skip';
    }

    if (ho_case_has_setup_result($claims) && (
        str_contains($setupPath, 'proceed_no_website')
        || str_contains($setupPath, 'simple_front_door')
        || str_contains($setupPath, 'ready')
        || str_contains($contact, 'ready')
        || str_contains($contact, 'usable')
        || str_contains($contact, 'website_contact_form')
        || str_contains($contact, 'email')
        || str_contains($contact, 'phone')
    )) {
        return 'Ready To Contact';
    }

    $triageStatus = strtolower(ho_case_claim_value($claims, 'triage_result_status'));
    $angle = strtolower(ho_case_claim_value($claims, 'primary_sales_angle'));
    if (str_contains($triageStatus, 'research') || str_contains($angle, 'research_with_website')) return 'Need Research';
    if (str_contains($triageStatus, 'proceed') || str_contains($angle, 'proceed_no_website')) return 'Ready For Setup';

    return 'Need Triage';
}

function ho_case_next_action_text(array $business, array $claims): string {
    $state = ho_case_state_label($business, $claims);
    $reason = ho_case_setup_reason($claims);
    return match ($state) {
        'Need Research' => 'Setup result requested research/manual check' . ($reason ? ': ' . $reason : '') . '.',
        'Ready To Contact' => 'Setup result produced a usable contact path' . ($reason ? ': ' . $reason : '') . '.',
        'Ready For Setup' => 'Copy setup prompt, run GPT, paste setup result in Intake.',
        'Need Triage' => 'Copy triage prompt, run GPT, paste result in Intake.',
        'Blocked / Skip' => 'Do not proceed unless manually reopened' . ($reason ? ': ' . $reason : '') . '.',
        default => 'Open and decide next action.',
    };
}

function ho_case_setup_reason(array $claims): string {
    $parts = [];
    $path = ho_case_setup_path($claims);
    $contact = ho_case_contact_readiness($claims);
    if ($path !== '') $parts[] = 'setup_path=' . $path;
    if ($contact !== '') $parts[] = 'contact_readiness=' . $contact;
    return implode('; ', $parts);
}

function ho_case_display_location(array $business, array $claims): string {
    $city = trim((string)($business['location_city'] ?? ''));
    $state = trim((string)($business['location_state'] ?? 'IN'));
    $service = trim((string)($business['service_area_text'] ?? ''));

    $strongCity = trim(ho_case_claim_value($claims, 'city'));
    $strongState = trim(ho_case_claim_value($claims, 'state'));
    if ($strongCity !== '' && strtolower($strongCity) !== strtolower($city)) {
        $city = $strongCity;
    }
    if ($strongState !== '') {
        $state = $strongState;
    }

    $bits = [];
    if ($city !== '' || $state !== '') $bits[] = trim($city . ', ' . $state, ', ');
    if ($service !== '' && strtolower($service) !== strtolower($city) && !str_contains(strtolower($service), 'new castle')) {
        $bits[] = $service;
    } else {
        $bits[] = 'Indiana';
    }
    return implode(' · ', array_unique(array_filter($bits)));
}

$caseStateLabel = ho_case_state_label($business, $claims);
$caseNextActionText = ho_case_next_action_text($business, $claims);
$caseLocationText = ho_case_display_location($business, $claims);
?>

<?php
function ho_case_primary_prompt_label(string $state): string {
    return match ($state) {
        'Need Research' => 'Copy Research Prompt',
        'Need Triage' => 'Copy Triage Prompt',
        'Ready For Setup' => 'Copy Setup Prompt',
        'Ready To Contact' => 'Review Contact Path',
        'Blocked / Skip' => 'Review Block Reason',
        default => 'Open Relevant Prompt',
    };
}
function ho_case_primary_prompt_target(string $state): string {
    return match ($state) {
        'Need Research' => '#businessRefinementPromptBox',
        'Need Triage' => '#primary-case-prompt',
        'Ready For Setup' => '#setupPromptBox',
        default => '#caseDetails',
    };
}
function ho_case_primary_prompt_help(string $state): string {
    return match ($state) {
        'Need Research' => 'This case has evidence or setup output asking for deeper research/manual check. Use the refinement prompt next.',
        'Need Triage' => 'This case still needs first-pass sorting.',
        'Ready For Setup' => 'This case can move into preview/contact setup.',
        'Ready To Contact' => 'This case appears to have enough contact direction. Review before outreach.',
        'Blocked / Skip' => 'This case is not active unless manually reopened.',
        default => 'Use the next relevant action for this case.',
    };
}
?>

<?php
function ho_case_primary_prompt_copy_id(string $state): string {
    return match ($state) {
        'Need Research' => 'businessRefinementPromptBox',
        'Need Triage' => 'candidateTriagePromptBox',
        'Ready For Setup' => 'setupPromptBox',
        default => '',
    };
}
?>






<style>
/* v084 case file simplification */
.admin-case-file,
.admin-case-action-card{
  margin:12px 12px !important;
  padding:18px !important;
  border-radius:22px !important;
}
.admin-case-file h2,
.admin-case-action-card h2{
  font-size:clamp(28px, 8vw, 40px) !important;
  line-height:.95 !important;
}
.admin-case-file .admin-muted{
  font-size:16px !important;
  line-height:1.35 !important;
}
.admin-case-next,
.admin-case-action-card{
  background:rgba(47,94,54,.07) !important;
  border:1px solid rgba(47,94,54,.2) !important;
}
.admin-status-blocks{
  display:grid !important;
  grid-template-columns:repeat(2,minmax(0,1fr)) !important;
  gap:8px !important;
}
.admin-status-blocks article{
  padding:10px !important;
  border-radius:14px !important;
}
.admin-case-prompt details,
.admin-case-details details{
  border:1px solid var(--ho-border-soft) !important;
  border-radius:18px !important;
  background:rgba(255,255,255,.64) !important;
  overflow:hidden !important;
}
.admin-case-prompt summary,
.admin-case-details summary{
  padding:14px 15px !important;
  min-height:52px !important;
  font-weight:900 !important;
  list-style:none !important;
}
.admin-case-prompt summary::-webkit-details-marker,
.admin-case-details summary::-webkit-details-marker{display:none !important}
</style>


<?php if ($dbError !== null): ?><section class="admin-status error"><div class="admin-status-head"><strong>Database Error</strong></div><p><?= ho_h($dbError) ?></p></section><?php elseif (!$business): ?><section class="admin-status error"><div class="admin-status-head"><strong>Not Found</strong></div><p>No business found for this ID.</p></section><?php else: ?>


<section class="admin-card admin-sourcing-context-note">
  <p class="admin-kicker">Sourcing Context</p>
  <details>
    <summary>Indiana and category are loose context</summary>
    <p class="admin-muted">City, service area, and category describe how the record was sourced. They are not hard gates. The only broad location gate is Indiana relevance unless the record clearly belongs outside Indiana. Category can include cleaners, handyman services, photographers, and other customer-facing local service operators.</p>
  </details>
</section>



<section class="admin-card admin-merge-repair-note">
  <p class="admin-kicker">State Rule</p>
  <details>
    <summary>Setup results move cases forward</summary>
    <p class="admin-muted">If setup_path or contact_readiness exists, this case should not keep looping through setup. Research/manual-check results route to Need Research; usable contact paths route to Ready To Contact.</p>
  </details>
</section>

<section class="admin-card admin-batch-first-note">
  <p class="admin-kicker">Batch First</p>
  <details>
    <summary>Do not work one-by-one unless this is an exception</summary>
    <p class="admin-muted">The normal workflow is Work Queue → copy pile prompt → paste batch result. Case File is for inspection, stuck records, or checking why a business landed in a pile.</p>
  </details>
</section>

<section class="admin-card admin-case-file">
  <p class="admin-kicker">Case File</p>
  <h2><?= ho_h($titleName) ?></h2>
  <p class="admin-muted">
    <?= ho_h((string)$business['business_type']) ?>
    <?php if ($business['location_city'] || $business['location_state']): ?> · <?= ho_h(trim((string)$business['location_city'] . ', ' . (string)$business['location_state'], ', ')) ?><?php endif; ?>
    <?php if (!empty($business['service_area_text'])): ?> · <?= ho_h((string)$business['service_area_text']) ?><?php endif; ?>
  </p>

  <div class="admin-case-next">
    <span class="admin-dispatch-label">State</span>
    <strong><?= ho_h($caseStateLabel) ?></strong>
    <p><?= ho_h($caseNextActionText) ?></p>
  </div>

  <div class="admin-status-blocks">
    <article><strong><?= ho_h($business['marketing_clearance_score'] !== null ? (string)$business['marketing_clearance_score'] : '—') ?></strong><span>Clearance</span></article>
    <article><strong><?= ho_h((string)count($claims)) ?></strong><span>Claims</span></article>
    <article><strong><?= ho_h((string)count($evidence)) ?></strong><span>Evidence</span></article>
    <article><strong><?= ho_h((string)($claimSummary['needs_review'] + $claimSummary['conflicting'])) ?></strong><span>Flags</span></article>
  </div>
</section>



<section class="admin-card admin-case-prompt admin-triage-prompt-card" id="primary-case-prompt">
  <details <?= $caseStateLabel === 'Need Triage' ? 'open' : '' ?>>
    <summary>Candidate Triage Prompt<?= $caseStateLabel !== 'Need Triage' ? ' · not current action' : '' ?></summary>
    
  <p class="admin-kicker">Primary Prompt</p><h2>Candidate Triage Prompt</h2>
  <p class="admin-muted">This is the prompt for deciding whether this candidate needs research, can proceed without website research, or should be skipped.</p>
  <p class="admin-muted">Use this before full research. It asks GPT to decide whether this candidate deserves full refinement, manual checking, or elimination.</p>
  <textarea id="triagePromptBox" class="admin-textarea"><?= ho_h($triagePrompt ?? '') ?></textarea>
  <p class="admin-next-row">
    <button class="admin-btn admin-btn-primary js-copy-prompt" type="button" data-copy-target="triagePromptBox">Copy Triage Prompt</button>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Paste In Intake Desk</a>
  </p>

  </details>
</section>


<section class="admin-card admin-case-prompt admin-preview-contact-setup-card">
  <details <?= $caseStateLabel === 'Ready For Setup' ? 'open' : '' ?>>
    <summary>Preview / Contact Setup Prompt</summary>
    
  <h2>Preview & Contact Setup</h2>
  <p class="admin-muted">Use this after triage/refinement to decide whether this business is ready for a preview setup and contact prep.</p>
  <textarea id="singleSetupPromptBox" class="admin-textarea"><?= ho_h($setupPrompt ?? '') ?></textarea>
  <p class="admin-next-row">
    <button class="admin-btn admin-btn-primary js-copy-prompt" type="button" data-copy-target="singleSetupPromptBox">Copy Setup Prompt</button>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Paste In Intake Desk</a>
  </p>

  </details>
</section>

<section class="admin-card admin-case-prompt admin-refinement-prompt-card">
  <details <?= $caseStateLabel === 'Need Research' ? 'open' : '' ?>>
    <summary>Business Refinement Prompt</summary>
    
  <h2>Business Refinement Prompt</h2>
  <p class="admin-muted">Copy this prompt after a rough import. It tells GPT what the system already knows, what is missing, and how to return a stronger importable JSON update.</p>
  <?php if (trim((string)$refinementPrompt) === ''): ?>
    <div class="admin-empty-state">Refinement prompt could not be generated for this business record.</div>
  <?php else: ?>
    <textarea id="businessRefinementPromptBox" class="admin-textarea"><?= ho_h($refinementPrompt) ?></textarea>
    <p class="admin-next-row">
      <button class="admin-btn admin-btn-primary js-copy-prompt" type="button" data-copy-target="refinementPromptBox">Copy Refinement Prompt</button>
      <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Paste In Intake Desk</a>
    </p>
  <?php endif; ?>

  </details>
</section>

<section class="admin-card admin-case-details" id="caseDetails">
  <details>
    <summary>Workflow Position</summary>
    <div class="admin-workflow-strip"><span>Business</span><span>Evidence</span><span>Claims</span><span>Clearance</span><span>Preview</span><span>Outreach</span><span>Build</span></div><p class="admin-muted"><strong>Status:</strong> <?= ho_h(ucwords(str_replace('_',' ',(string)$business['marketing_clearance_status']))) ?> · <?= ho_h(ho_salesportal_readiness_text((string)$business['marketing_clearance_status'])) ?></p><p class="admin-muted"><strong>Recommended package:</strong> <?= ho_h((string)$business['recommended_package']) ?><?= !empty($business['recommended_design']) ? ' · ' . ho_h((string)$business['recommended_design']) : '' ?></p>
  </details>
</section>
<section class="admin-card-grid two"><section class="admin-card admin-case-details" id="caseDetails">
  <details>
    <summary>Best Signals</summary>
    <?php if (empty($claimSummary['top_strengths'])): ?><p class="admin-muted">No strengths surfaced yet.</p><?php else: ?><?= ho_admin_doc_list($claimSummary['top_strengths']) ?><?php endif; ?>
  </details>
</section><section class="admin-card admin-case-details admin-server-collapsed-section" id="caseDetails">
  <details>
    <summary>Main Problems</summary>
    <div class="admin-auto-collapsed-body">
      <h2>Main Problems</h2><?php if (empty($claimSummary['top_issues'])): ?><p class="admin-muted">No major issues surfaced yet.</p><?php else: ?><?= ho_admin_doc_list($claimSummary['top_issues']) ?><?php endif; ?>
    </div>
  </details>
</section></section>
<section class="admin-card admin-case-details" id="caseDetails">
  <details>
    <summary>Claims by Category</summary>
    <?php if (empty($claimGroups)): ?><p class="admin-muted">No claims recorded.</p><?php else: ?><div class="admin-section"><?php foreach ($claimGroups as $category=>$items): ?><section class="admin-secondary-card"><h3><?= ho_h(ucwords(str_replace('_',' ',(string)$category))) ?></h3><table class="admin-file-table"><thead><tr><th>Field</th><th>Value</th><th>Confidence</th></tr></thead><tbody><?php foreach ($items as $claim): ?><tr><td><code><?= ho_h((string)$claim['field_key']) ?></code></td><td><?= ho_h((string)$claim['claim_value']) ?><?php if (!empty($claim['evidence_note'])): ?><div class="admin-muted"><?= ho_h((string)$claim['evidence_note']) ?></div><?php endif; ?></td><td><?= ho_h((string)$claim['confidence_level']) ?><div class="admin-muted"><?= ho_h((string)$claim['confidence_score']) ?></div></td></tr><?php endforeach; ?></tbody></table>
  </details>
</section><?php endforeach; ?></div><?php endif; ?></section>
<section class="admin-card admin-case-details">
  <details>
    <summary>Evidence Sources</summary>
    <?php if (empty($evidence)): ?><p class="admin-muted">No evidence sources recorded.</p><?php else: ?><div class="admin-data-list"><?php foreach ($evidence as $source): ?><div class="admin-data-row"><div><div class="admin-data-row-title"><?= ho_h((string)$source['source_type']) ?><?= !empty($source['source_title']) ? ' · ' . ho_h((string)$source['source_title']) : '' ?></div><?php if (!empty($source['notes'])): ?><div class="admin-data-row-note"><?= ho_h((string)$source['notes']) ?></div><?php endif; ?></div><?php if (!empty($source['source_url'])): ?><a class="admin-btn admin-btn-secondary" href="<?= ho_h((string)$source['source_url']) ?>" target="_blank" rel="noopener">Open</a><?php endif; ?></div><?php endforeach; ?></div><?php endif; ?>
  </details>
</section>
<section class="admin-card"><h2>Next Action</h2><p><?= ho_h(ho_salesportal_readiness_text((string)$business['marketing_clearance_status'])) ?></p><p><a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Back to Prospects</a> <a class="admin-btn admin-btn-primary" href="/sales-research.php">Add Research</a></p></section>
<?php endif; ?>

<section class="admin-action-dock" id="business-bottom-dock">
  <a class="admin-btn admin-btn-primary" href="/sales-portal-dashboard.php#dashboard-import">Paste Result</a>
  <a class="admin-btn admin-btn-secondary" href="#singleSetupPromptBox">Setup</a>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
  <a class="admin-btn admin-btn-secondary" href="/admin.php">Admin</a>
</section>


<script>
document.addEventListener('DOMContentLoaded', function () {
  const rawTitles = [
    'workflow position',
    'best signals',
    'main problem',
    'main problems',
    'claims by category',
    'find me',
    'fix me',
    'trust me',
    'show me',
    'contact me',
    'evidence sources',
    'raw claims',
    'all claims',
    'latest claims',
    'requirement scores',
    'me scores',
    'preview readiness',
    'option assignment'
  ];

  document.querySelectorAll('section.admin-card').forEach(function (section) {
    if (section.closest('.admin-case-prompt') || section.classList.contains('admin-case-file') || section.classList.contains('admin-case-action-card') || section.classList.contains('admin-sourcing-context-note')) {
      return;
    }
    if (section.querySelector('details')) {
      return;
    }

    const titleNode = section.querySelector('h2,h3,.admin-kicker,strong');
    const title = titleNode ? titleNode.textContent.trim() : '';
    const lower = title.toLowerCase();
    const hasRawTitle = rawTitles.some(function (raw) { return lower === raw || lower.includes(raw); });
    const hasTable = !!section.querySelector('table');
    const hasManyRows = section.querySelectorAll('tr,.admin-data-row,.admin-claim-row,.admin-signal-row').length >= 3;

    if (!hasRawTitle && !hasTable && !hasManyRows) {
      return;
    }

    const summaryText = title || 'Case Details';
    const details = document.createElement('details');
    details.className = 'admin-auto-collapsed-details';
    const summary = document.createElement('summary');
    summary.textContent = summaryText;

    const body = document.createElement('div');
    body.className = 'admin-auto-collapsed-body';

    while (section.firstChild) {
      body.appendChild(section.firstChild);
    }

    details.appendChild(summary);
    details.appendChild(body);
    section.appendChild(details);
    section.classList.add('admin-case-details', 'admin-auto-collapsed-section');
  });
});
</script>


<script>
function hoCopyTextById(id, button) {
  const el = document.getElementById(id);
  if (!el) {
    alert('Prompt box not found on this case.');
    return false;
  }

  const text = el.value || el.textContent || '';
  if (!text.trim()) {
    alert('Prompt is empty.');
    return false;
  }

  function markDone() {
    if (button) {
      const old = button.textContent;
      button.textContent = 'Copied';
      button.classList.add('admin-copy-done');
      setTimeout(function () {
        button.textContent = old;
        button.classList.remove('admin-copy-done');
      }, 1400);
    }
  }

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(markDone).catch(function () {
      fallbackCopy(el, markDone);
    });
  } else {
    fallbackCopy(el, markDone);
  }

  return false;
}

function fallbackCopy(el, done) {
  const oldReadonly = el.getAttribute('readonly');
  el.removeAttribute('readonly');
  el.focus();
  el.select();
  el.setSelectionRange(0, (el.value || '').length);
  try {
    document.execCommand('copy');
    if (done) done();
  } catch (err) {
    alert('Copy failed. Tap inside the prompt, Select All, then Copy.');
  }
  if (oldReadonly !== null) el.setAttribute('readonly', oldReadonly);
}
</script>


<script>
(function(){
  function setCopyStatus(button, message, ok) {
    if (!button) return;
    var old = button.getAttribute('data-original-label') || button.textContent;
    if (!button.getAttribute('data-original-label')) button.setAttribute('data-original-label', old);
    button.textContent = message;
    button.classList.toggle('admin-copy-done', !!ok);
    button.classList.toggle('admin-copy-error', !ok);
    setTimeout(function(){
      button.textContent = button.getAttribute('data-original-label') || old;
      button.classList.remove('admin-copy-done');
      button.classList.remove('admin-copy-error');
    }, 1600);
  }

  function fallbackCopyText(text, button) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.top = '0';
    ta.style.left = '0';
    ta.style.width = '1px';
    ta.style.height = '1px';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    try {
      var ok = document.execCommand('copy');
      setCopyStatus(button, ok ? 'Copied' : 'Copy Failed', ok);
    } catch (e) {
      setCopyStatus(button, 'Copy Failed', false);
      window.prompt('Copy this prompt:', text);
    }
    document.body.removeChild(ta);
  }

  window.hoCopyPromptById = function(id, button) {
    var el = document.getElementById(id);
    if (!el) {
      setCopyStatus(button, 'Prompt Missing', false);
      return false;
    }

    var text = el.value || el.textContent || '';
    if (!text.trim()) {
      setCopyStatus(button, 'Prompt Empty', false);
      return false;
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(function(){
        setCopyStatus(button, 'Copied', true);
      }).catch(function(){
        fallbackCopyText(text, button);
      });
    } else {
      fallbackCopyText(text, button);
    }

    return false;
  };

  document.addEventListener('click', function(event){
    var button = event.target.closest('.js-copy-prompt');
    if (!button) return;
    event.preventDefault();
    var target = button.getAttribute('data-copy-target');
    window.hoCopyPromptById(target, button);
  });
})();
</script>

<?php ho_admin_render_end(); ?>
