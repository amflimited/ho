<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';
require __DIR__ . '/prospect-model.php';


$dashboardImportResult = null;
$dashboardImportText = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dashboard_research_json'])) {
    $dashboardImportText = (string)($_POST['dashboard_research_json'] ?? '');
    $dashboardImportResult = ho_salesportal_dashboard_validate_or_import_json($dashboardImportText, isset($_POST['validate_only']));
}

$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$canon = ho_salesportal_canon();

if ($status !== '' && !in_array($status, $canon['marketing_statuses'], true)) {
    $status = '';
}

try {
    $snapshot = ho_salesportal_network_snapshot();
    $businesses = function_exists('ho_salesportal_list_businesses_with_readiness')
        ? ho_salesportal_list_businesses_with_readiness($status !== '' ? $status : null, $search)
        : ho_salesportal_list_businesses($status !== '' ? $status : null, $search);

    $readinessCounts = function_exists('ho_salesportal_preview_readiness_counts')
        ? ho_salesportal_preview_readiness_counts()
        : [];

    $previewSchemaStatus = function_exists('ho_salesportal_preview_schema_status')
        ? ho_salesportal_preview_schema_status()
        : ['ok' => true, 'tables' => []];

    $bulkTriagePrompt = ho_salesportal_bulk_triage_prompt($businesses);
    $bulkTriageCount = count(ho_salesportal_bulk_triage_candidates($businesses, 25));
    $dbError = null;
} catch (Throwable $e) {
    $snapshot = [
        'status_rows'=>[],
        'category_rows'=>[],
        'business_count'=>0,
        'claim_count'=>0,
        'source_count'=>0,
        'high_claims'=>0,
        'review_claims'=>0
    ];
    $businesses = [];
    $readinessCounts = [];
    $previewSchemaStatus = ['ok' => false, 'tables' => []];
    $bulkTriagePrompt = '';
    $bulkTriageCount = 0;
    $dbError = $e->getMessage();
}

$statusCounts = [];
foreach ($snapshot['status_rows'] as $row) {
    $statusCounts[(string)$row['status']] = (int)$row['total'];
}



function ho_salesportal_dashboard_normalize_json_text(string $text): string {
    $text = trim($text);
    return strtr($text, [
        "“" => '"',
        "”" => '"',
        "‘" => "'",
        "’" => "'",
        "\xC2\xA0" => ' ',
    ]);
}

function ho_salesportal_dashboard_slug(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'unknown-business';
}

function ho_salesportal_dashboard_source_type(string $type): string {
    $type = strtolower(trim($type));
    return match ($type) {
        'website' => 'website',
        'facebook' => 'facebook',
        'google_profile', 'google', 'gbp' => 'google_business_profile',
        'email' => 'email',
        'directory', 'address', 'phone' => 'directory',
        default => 'manual_observation',
    };
}

function ho_salesportal_dashboard_identifier_field(string $type): ?string {
    $type = strtolower(trim($type));
    return match ($type) {
        'website' => 'website_url',
        'facebook' => 'facebook_url',
        'google_profile', 'google', 'gbp' => 'google_profile_url',
        'phone' => 'phone_number',
        'email' => 'email_address',
        default => null,
    };
}

function ho_salesportal_dashboard_triage_status_to_clearance(string $status): string {
    return match ($status) {
        'research_with_website', 'research_ready' => 'warm_clear',
        'proceed_no_website', 'quick_hold', 'needs_identity_check', 'no_public_surface', 'duplicate_or_confused' => 'hold',
        'do_not_proceed', 'bad_fit', 'exclude' => 'skip',
        default => 'needs_review',
    };
}

function ho_salesportal_dashboard_candidate_to_payload(array $candidate, array $batch = []): array {
    $name = trim((string)($candidate['candidate_name'] ?? $candidate['business_name'] ?? $candidate['name'] ?? ''));
    $category = trim((string)($candidate['business_category'] ?? $candidate['category'] ?? $candidate['business_type'] ?? ''));
    $city = trim((string)($candidate['city'] ?? ''));
    $serviceArea = trim((string)($candidate['service_area'] ?? $candidate['city_service_area'] ?? $candidate['area'] ?? ''));
    $state = trim((string)($candidate['state'] ?? 'IN'));
    $notes = trim((string)($candidate['initial_notes'] ?? $candidate['notes'] ?? ''));

    if ($name === '') {
        throw new RuntimeException('Candidate is missing candidate_name/business_name.');
    }

    $businessType = $category !== '' ? $category : (string)($batch['category'] ?? 'local_service');
    $slugParts = [$name];
    if ($city !== '') $slugParts[] = $city;
    $slug = ho_salesportal_dashboard_slug(implode('-', $slugParts));

    $identifiers = $candidate['identifiers'] ?? [];
    if (!is_array($identifiers)) $identifiers = [];

    $evidenceSources = [[
        'source_type' => 'manual_observation',
        'source_url' => '',
        'source_title' => 'Candidate sourcing batch',
        'capture_status' => 'manual',
        'raw_excerpt' => json_encode($candidate, JSON_UNESCAPED_SLASHES),
        'notes' => 'Candidate sourced for later triage/refinement. Not outreach-ready until verified.'
    ]];

    foreach ($identifiers as $identifier) {
        if (!is_array($identifier)) continue;
        $type = strtolower(trim((string)($identifier['type'] ?? 'other')));
        $value = trim((string)($identifier['value'] ?? ''));
        if ($value === '') continue;
        $evidenceSources[] = [
            'source_type' => ho_salesportal_dashboard_source_type($type),
            'source_url' => filter_var($value, FILTER_VALIDATE_URL) ? $value : '',
            'source_title' => 'Candidate identifier: ' . $type,
            'capture_status' => 'manual',
            'raw_excerpt' => $value,
            'notes' => (string)($identifier['source_note'] ?? 'Candidate identifier from source batch.')
        ];
    }

    $claims = [
        [
            'field_key' => 'business_name',
            'claim_value' => $name,
            'normalized_value' => $name,
            'confidence_level' => 'likely',
            'confidence_score' => 70,
            'claim_status' => 'needs_review',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'Business name came from candidate sourcing and still needs verification.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.business_identity_clear',
            'evidence_source_index' => 0
        ],
        [
            'field_key' => 'business_type',
            'claim_value' => $businessType,
            'normalized_value' => $businessType,
            'confidence_level' => 'likely',
            'confidence_score' => 65,
            'claim_status' => 'needs_review',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'Business category came from candidate sourcing and still needs verification.',
            'supports_me_category' => 'show_me',
            'supports_requirement_key' => 'show_me.services_visible',
            'evidence_source_index' => 0
        ],
        [
            'field_key' => 'marketing_clearance_status',
            'claim_value' => 'hold',
            'normalized_value' => 'hold',
            'confidence_level' => 'inferred',
            'confidence_score' => 70,
            'claim_status' => 'active',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'Candidate records remain on hold until triage/refinement verifies identity and contactability.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.business_identity_clear',
            'evidence_source_index' => 0
        ],
    ];

    if ($city !== '') {
        $claims[] = [
            'field_key' => 'city',
            'claim_value' => $city,
            'normalized_value' => $city,
            'confidence_level' => 'likely',
            'confidence_score' => 65,
            'claim_status' => 'needs_review',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'City came from candidate sourcing.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.location_or_service_area_clear',
            'evidence_source_index' => 0
        ];
    }

    if ($serviceArea !== '') {
        $claims[] = [
            'field_key' => 'service_area',
            'claim_value' => $serviceArea,
            'normalized_value' => $serviceArea,
            'confidence_level' => 'likely',
            'confidence_score' => 65,
            'claim_status' => 'needs_review',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'Service area came from candidate sourcing.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.location_or_service_area_clear',
            'evidence_source_index' => 0
        ];
    }

    foreach ($identifiers as $i => $identifier) {
        if (!is_array($identifier)) continue;
        $type = strtolower(trim((string)($identifier['type'] ?? '')));
        $value = trim((string)($identifier['value'] ?? ''));
        if ($value === '') continue;
        $fieldKey = ho_salesportal_dashboard_identifier_field($type);
        if ($fieldKey === null) continue;
        $claims[] = [
            'field_key' => $fieldKey,
            'claim_value' => $value,
            'normalized_value' => $value,
            'confidence_level' => 'likely',
            'confidence_score' => 65,
            'claim_status' => 'needs_review',
            'source_type' => ho_salesportal_dashboard_source_type($type),
            'source_url' => filter_var($value, FILTER_VALIDATE_URL) ? $value : '',
            'source_label' => 'Candidate identifier',
            'evidence_note' => 'Identifier came from candidate sourcing and should be verified.',
            'supports_me_category' => in_array($fieldKey, ['phone_number','email_address'], true) ? 'contact_me' : 'find_me',
            'supports_requirement_key' => in_array($fieldKey, ['phone_number','email_address'], true) ? 'contact_me.clear_primary_contact' : 'find_me.public_search_presence',
            'evidence_source_index' => min(count($evidenceSources) - 1, $i + 1)
        ];
    }

    return [
        'business' => [
            'business_slug' => $slug,
            'business_name_current' => $name,
            'business_type' => $businessType,
            'location_city' => $city,
            'location_state' => $state !== '' ? $state : 'IN',
            'service_area_text' => $serviceArea !== '' ? $serviceArea : $city
        ],
        'evidence_sources' => $evidenceSources,
        'claims' => $claims,
        'marketing_clearance' => [
            'business_activity_score' => 0,
            'need_score' => 0,
            'fit_score' => 0,
            'confidence_score' => 35,
            'contactability_score' => 0,
            'buildability_score' => 0,
            'marketing_clearance_score' => 0,
            'marketing_clearance_status' => 'hold',
            'recommended_package' => 'unknown',
            'recommended_design' => '',
            'reason' => 'Candidate batch import only. Requires triage/refinement before outreach.'
        ],
        'notes' => ['Imported from candidate batch.', 'Do not contact until triage/refinement confirms identity and contactability.', $notes]
    ];
}

function ho_salesportal_dashboard_triage_to_payload(array $result, array $batch = []): array {
    $name = trim((string)($result['business_name'] ?? $result['candidate_name'] ?? ''));
    $slug = trim((string)($result['business_slug'] ?? ''));
    $status = trim((string)($result['status'] ?? 'quick_hold'));
    $nextStep = trim((string)($result['recommended_next_step'] ?? 'manual_check'));
    $reason = trim((string)($result['reason'] ?? 'Candidate triage result.'));
    $targetArea = trim((string)($batch['target_area'] ?? ''));
    $category = trim((string)($batch['category'] ?? 'lawn_care'));

    if ($name === '' && $slug === '') {
        throw new RuntimeException('Triage result is missing business_name or business_slug.');
    }
    if ($name === '') $name = ucwords(str_replace('-', ' ', $slug));
    if ($slug === '') $slug = ho_salesportal_dashboard_slug($name . '-' . $targetArea);

    $city = '';
    $state = 'IN';
    if (stripos($targetArea, ',') !== false) {
        [$cityPart, $statePart] = array_map('trim', explode(',', $targetArea, 2));
        $city = $cityPart;
        if ($statePart !== '') $state = $statePart;
    } else {
        $city = $targetArea;
    }

    $identifiers = $result['verified_identifiers'] ?? [];
    if (!is_array($identifiers)) $identifiers = [];

    $claims = [
        [
            'field_key' => 'marketing_clearance_status',
            'claim_value' => ho_salesportal_dashboard_triage_status_to_clearance($status),
            'normalized_value' => ho_salesportal_dashboard_triage_status_to_clearance($status),
            'confidence_level' => 'inferred',
            'confidence_score' => 75,
            'claim_status' => 'active',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Bulk candidate triage result',
            'evidence_note' => 'Bulk triage category: ' . $status . '. Next step: ' . $nextStep . '.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.business_identity_clear',
            'evidence_source_index' => 0
        ],
        [
            'field_key' => 'primary_sales_angle',
            'claim_value' => '[' . $status . ' / ' . $nextStep . '] ' . $reason,
            'normalized_value' => '[' . $status . ' / ' . $nextStep . '] ' . $reason,
            'confidence_level' => 'inferred',
            'confidence_score' => 65,
            'claim_status' => 'needs_review',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Bulk candidate triage result',
            'evidence_note' => 'Triage reason, not final sales copy.',
            'supports_me_category' => 'fix_me',
            'supports_requirement_key' => 'fix_me.customer_path_mess',
            'evidence_source_index' => 0
        ],
        [
            'field_key' => 'business_name',
            'claim_value' => $name,
            'normalized_value' => $name,
            'confidence_level' => 'likely',
            'confidence_score' => 70,
            'claim_status' => 'active',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Bulk candidate triage result',
            'evidence_note' => 'Business name from triage result.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.business_identity_clear',
            'evidence_source_index' => 0
        ],
    ];

    foreach ($identifiers as $identifier) {
        if (!is_array($identifier)) continue;
        $type = strtolower(trim((string)($identifier['type'] ?? '')));
        $value = trim((string)($identifier['value'] ?? ''));
        if ($value === '') continue;
        $fieldKey = ho_salesportal_dashboard_identifier_field($type);
        if ($fieldKey === null) continue;
        $claims[] = [
            'field_key' => $fieldKey,
            'claim_value' => $value,
            'normalized_value' => $value,
            'confidence_level' => (($identifier['confidence'] ?? '') === 'high') ? 'confirmed' : 'likely',
            'confidence_score' => (($identifier['confidence'] ?? '') === 'high') ? 85 : 65,
            'claim_status' => 'active',
            'source_type' => ho_salesportal_dashboard_source_type($type),
            'source_url' => filter_var($value, FILTER_VALIDATE_URL) ? $value : '',
            'source_label' => 'Verified identifier from triage',
            'evidence_note' => 'Verified identifier from bulk triage.',
            'supports_me_category' => in_array($fieldKey, ['phone_number','email_address'], true) ? 'contact_me' : 'find_me',
            'supports_requirement_key' => in_array($fieldKey, ['phone_number','email_address'], true) ? 'contact_me.clear_primary_contact' : 'find_me.public_search_presence',
            'evidence_source_index' => 0
        ];
    }

    return [
        'business' => [
            'business_slug' => $slug,
            'business_name_current' => $name,
            'business_type' => $category,
            'location_city' => $city,
            'location_state' => $state,
            'service_area_text' => $targetArea
        ],
        'evidence_sources' => [[
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_title' => 'Bulk candidate triage result',
            'capture_status' => 'manual',
            'raw_excerpt' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'notes' => 'Lightweight bulk triage result. This is not full prospect research.'
        ]],
        'claims' => $claims,
        'marketing_clearance' => [
            'business_activity_score' => in_array($status, ['research_with_website','proceed_no_website'], true) ? 55 : 20,
            'need_score' => in_array($status, ['research_with_website','proceed_no_website'], true) ? 70 : 30,
            'fit_score' => in_array($status, ['research_with_website','proceed_no_website'], true) ? 70 : 30,
            'confidence_score' => in_array($status, ['research_with_website','proceed_no_website'], true) ? 60 : 35,
            'contactability_score' => in_array($status, ['research_with_website','proceed_no_website'], true) ? 60 : 25,
            'buildability_score' => in_array($status, ['research_with_website','proceed_no_website'], true) ? 65 : 35,
            'marketing_clearance_score' => $status === 'research_with_website' ? 62 : ($status === 'proceed_no_website' ? 52 : 25),
            'marketing_clearance_status' => ho_salesportal_dashboard_triage_status_to_clearance($status),
            'recommended_package' => 'unknown',
            'recommended_design' => '',
            'reason' => $reason
        ],
        'notes' => ['Imported from bulk candidate triage.', 'Triage category: ' . $status, 'Recommended next step: ' . $nextStep]
    ];
}

function ho_salesportal_dashboard_payloads_from_input(array $decoded): array {
    if (isset($decoded['business']) && is_array($decoded['business'])) {
        return [$decoded];
    }

    $batch = $decoded['source_batch'] ?? $decoded['batch'] ?? $decoded['triage_batch'] ?? [];
    if (!is_array($batch)) $batch = [];

    if (isset($decoded['triage_results']) && is_array($decoded['triage_results'])) {
        if (count($decoded['triage_results']) > 25) throw new RuntimeException('Triage batch limit is 25 records.');
        return array_map(static fn($item) => ho_salesportal_dashboard_triage_to_payload((array)$item, $batch), $decoded['triage_results']);
    }

    if (isset($decoded['candidates']) && is_array($decoded['candidates'])) {
        if (count($decoded['candidates']) > 25) throw new RuntimeException('Candidate batch limit is 25 records.');
        return array_map(static fn($item) => ho_salesportal_dashboard_candidate_to_payload((array)$item, $batch), $decoded['candidates']);
    }

    foreach (['businesses','prospects','items'] as $key) {
        if (isset($decoded[$key]) && is_array($decoded[$key])) {
            if (count($decoded[$key]) > 25) throw new RuntimeException('Batch limit is 25 records.');
            $payloads = [];
            foreach ($decoded[$key] as $item) {
                if (!is_array($item)) continue;
                $payloads[] = isset($item['business']) ? $item : ho_salesportal_dashboard_candidate_to_payload($item, $batch);
            }
            return $payloads;
        }
    }

    return [$decoded];
}

function ho_salesportal_dashboard_auto_process(int $businessId): array {
    $auto = ['readiness' => null, 'assignment' => null, 'warnings' => []];

    try {
        if (function_exists('ho_salesportal_evaluate_preview_readiness')) {
            $auto['readiness'] = ho_salesportal_evaluate_preview_readiness($businessId, true);
        }
    } catch (Throwable $e) {
        $auto['warnings'][] = 'Readiness skipped: ' . $e->getMessage();
    }

    try {
        if (function_exists('ho_salesportal_assign_preview_options')) {
            $auto['assignment'] = ho_salesportal_assign_preview_options($businessId, true);
        }
    } catch (Throwable $e) {
        $auto['warnings'][] = 'Options skipped: ' . $e->getMessage();
    }

    return $auto;
}

function ho_salesportal_dashboard_validate_or_import_json(string $raw, bool $validateOnly): array {
    $normalized = ho_salesportal_dashboard_normalize_json_text($raw);
    $trimmed = ltrim($normalized);

    if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
        return ['ok' => false, 'message' => 'Paste GPT JSON here, not the prompt text.', 'details' => []];
    }

    $decoded = json_decode($normalized, true);
    if (!is_array($decoded)) {
        return ['ok' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg(), 'details' => []];
    }

    try {
        $payloads = ho_salesportal_dashboard_payloads_from_input($decoded);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => $e->getMessage(), 'details' => []];
    }

    if (count($payloads) > 25) {
        return ['ok' => false, 'message' => 'Batch limit is 25 records.', 'details' => []];
    }

    $results = [];
    $ok = 0;
    $fail = 0;

    foreach ($payloads as $index => $payload) {
        try {
            $result = $validateOnly ? ho_salesportal_validate_payload($payload) : ho_salesportal_import_payload($payload);
            if (!$validateOnly && !empty($result['ok']) && !empty($result['business_id'])) {
                $result['auto_processing'] = ho_salesportal_dashboard_auto_process((int)$result['business_id']);
            }
            if (!empty($result['ok'])) $ok++; else $fail++;
            $results[] = ['index' => $index, 'business_name' => (string)($payload['business']['business_name_current'] ?? ''), 'result' => $result];
        } catch (Throwable $e) {
            $fail++;
            $results[] = ['index' => $index, 'business_name' => (string)($payload['business']['business_name_current'] ?? ''), 'result' => ['ok' => false, 'message' => $e->getMessage(), 'details' => []]];
        }
    }

    return [
        'ok' => $fail === 0,
        'message' => ($validateOnly ? 'Dashboard validation complete. ' : 'Dashboard import complete. ') . $ok . ' ok, ' . $fail . ' failed.',
        'batch_count' => count($payloads),
        'ok_count' => $ok,
        'fail_count' => $fail,
        'details' => $results,
    ];
}

function ho_salesportal_bulk_triage_candidates(array $businesses, int $limit = 25): array {
    $selected = [];

    foreach ($businesses as $business) {
        $status = (string)($business['marketing_clearance_status'] ?? '');
        $ready = $business['_preview_readiness'] ?? null;
        $readinessStatus = $ready ? (string)($ready['readiness_status'] ?? '') : '';

        $isCandidate = in_array($status, ['hold','needs_review','warm_clear'], true)
            || in_array($readinessStatus, ['needs_more_research','manual_review','soft_ready'], true)
            || $readinessStatus === '';

        if (!$isCandidate) {
            continue;
        }

        $selected[] = $business;
        if (count($selected) >= $limit) {
            break;
        }
    }

    return $selected;
}

function ho_salesportal_bulk_triage_prompt(array $businesses): string {
    $items = [];

    foreach (ho_salesportal_bulk_triage_candidates($businesses, 25) as $business) {
        $items[] = [
            'business_id' => (int)($business['id'] ?? 0),
            'business_slug' => (string)($business['business_slug'] ?? ''),
            'business_name' => (string)($business['business_name_current'] ?? ''),
            'business_type' => (string)($business['business_type'] ?? ''),
            'city' => (string)($business['location_city'] ?? ''),
            'state' => (string)($business['location_state'] ?? ''),
            'service_area' => (string)($business['service_area_text'] ?? ''),
            'current_clearance' => (string)($business['marketing_clearance_status'] ?? ''),
            'recommended_package' => (string)($business['recommended_package'] ?? ''),
            'recommended_design' => (string)($business['recommended_design'] ?? ''),
        ];
    }

    $json = json_encode([
        'triage_goal' => 'bulk_candidate_triage',
        'category' => 'lawn_care',
        'target_area' => 'New Castle, IN',
        'candidates' => $items,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return <<<PROMPT
You are doing bulk candidate triage for Hoosier Online.

Goal:
Classify up to 25 candidate businesses into exactly one of three practical next-step categories.

Do NOT do full business research.
Do NOT inspect every detailed field.
Do NOT write sales copy.
Do NOT infer private facts.
Use only public customer-facing information.
This is triage, not final prospect research.

Current candidate batch:
$json

The three categories:

1. research_with_website
Use this when:
- the business appears real and local,
- it has a website or strong public customer-facing surface,
- there is enough public material to justify full research,
- we should later run the full Business Refinement Prompt.

2. proceed_no_website
Use this when:
- the business appears real and local,
- no clear website exists, or the web presence is extremely thin,
- but there is still a usable contact path or enough identity evidence,
- we do NOT need deep website research,
- we can continue with a simple Front Door angle later.

3. do_not_proceed
Use this when:
- wrong category,
- duplicate/confused identity,
- too large/polished,
- not local,
- no usable public identity,
- no reasonable contact path,
- or not worth current effort.

For each business, only verify basics:
- Is the business real/identifiable?
- Is it local and in the right category?
- Does it appear to have a website or strong public surface?
- Does it have a usable contact path?
- Which of the three categories should it go into?

Return ONLY valid JSON in this exact structure:

{
  "triage_batch": {
    "category": "lawn_care",
    "target_area": "New Castle, IN",
    "triage_method": "bulk_gpt_candidate_triage",
    "category_definitions": {
      "research_with_website": "Real local business with a website or strong public surface; full research justified.",
      "proceed_no_website": "Real local business with weak/no website but enough identity/contact to continue with a simple Front Door angle.",
      "do_not_proceed": "Not worth proceeding now."
    }
  },
  "triage_results": [
    {
      "business_id": 0,
      "business_slug": "",
      "business_name": "",
      "status": "research_with_website|proceed_no_website|do_not_proceed",
      "verified_identifiers": [
        {
          "type": "website|facebook|google_profile|phone|email|directory|address",
          "value": "",
          "confidence": "high|medium|low"
        }
      ],
      "has_website_or_strong_surface": true,
      "has_usable_contact_path": true,
      "reason": "",
      "recommended_next_step": "full_research|simple_front_door_path|skip"
    }
  ]
}

Keep reasons short. Do not write long evidence notes.

PROMPT;
}

function ho_salesportal_status_label(string $status): string {
    return ucwords(str_replace('_', ' ', $status));
}

function ho_salesportal_admin_next_action(array $business): string {
    $ready = $business['_preview_readiness'] ?? null;
    $readiness = $ready ? (string)($ready['readiness_status'] ?? '') : '';

    if ($readiness === 'ready') return 'Draft outreach / preview story';
    if ($readiness === 'soft_ready') return 'Draft carefully; verify weak points';
    if ($readiness === 'manual_review') return 'Open and review before outreach';
    if ($readiness === 'needs_more_research') return 'Add better public proof';
    if ($readiness === 'blocked') return 'Do not contact';

    $clearance = (string)($business['marketing_clearance_status'] ?? '');
    if ($clearance === 'cleared') return 'Ready for outreach draft';
    if ($clearance === 'warm_clear') return 'Verify then draft';
    if ($clearance === 'needs_review') return 'Open and review';
    if ($clearance === 'hold') return 'Research more';
    if ($clearance === 'skip' || $clearance === 'blocked') return 'Do not contact';

    return 'Review';
}

function ho_salesportal_admin_assignment_summary(int $businessId): array {
    if (!function_exists('ho_salesportal_dashboard_assignment_summary')) {
        return [
            'allowed' => false,
            'top_design_label' => null,
            'top_design_key' => null,
            'top_address' => null,
            'business_type_key' => null,
        ];
    }

    try {
        return ho_salesportal_dashboard_assignment_summary($businessId);
    } catch (Throwable $e) {
        return [
            'allowed' => false,
            'top_design_label' => null,
            'top_design_key' => null,
            'top_address' => null,
            'business_type_key' => null,
        ];
    }
}

ho_admin_render_start(
    'sales_prospects',
    'Sales Prospect Queue',
    'Sales',
    'Prospect <em>Queue</em>',
    'A simple operator queue: who is worth attention, what is the next action, and what setup appears likely.'
);
?>

<section class="admin-process-note">
  <strong>Operator view:</strong> this page shows candidates/prospects by next useful action. Use Business View for triage prompts or full refinement prompts.
  <?php if (empty($previewSchemaStatus['ok'])): ?>
    <br><strong>Backend note:</strong> preview readiness/options require v044 SQL import before full functionality is available.
  <?php endif; ?>
</section>



<section class="admin-card">
  <h2>Operator Rhythm</h2>
  <div class="admin-mini-flow">
    <span><b>Batch</b> candidates</span>
    <span><b>Triage</b> next 25</span>
    <span><b>Import</b> results here</span>
    <span><b>Research</b> only winners</span>
  </div>
</section>

<section class="admin-card admin-bulk-triage-card">
  <h2>Bulk Candidate Triage</h2>
  <p class="admin-muted">Copy this prompt, run it in GPT, then paste the JSON result directly below. No Research page needed.</p>
  <?php if ($bulkTriageCount <= 0): ?>
    <div class="admin-empty-state">No candidates are currently available for bulk triage.</div>
  <?php else: ?>
    <textarea id="bulkTriagePromptBox" class="admin-textarea"><?= ho_h($bulkTriagePrompt) ?></textarea>
    <p class="admin-next-row">
      <button class="admin-btn admin-btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('bulkTriagePromptBox').value)">Copy Bulk Triage Prompt</button>
      <a class="admin-btn admin-btn-secondary" href="#dashboard-import">Paste Below</a>
    </p>
  <?php endif; ?>
</section>


<section class="admin-card admin-dashboard-import-card" id="dashboard-import">
  <h2>Paste Results Here</h2>
  <p class="admin-muted">iPhone operator flow: paste candidate batches, triage results, or refined business JSON here. You do not need to open the Research page.</p>

  <?php if ($dashboardImportResult !== null): ?>
    <section class="admin-status <?= !empty($dashboardImportResult['ok']) ? 'success' : 'error' ?>">
      <div class="admin-status-head"><strong><?= !empty($dashboardImportResult['ok']) ? 'Success' : 'Failed' ?></strong></div>
      <p><?= ho_h((string)$dashboardImportResult['message']) ?></p>
      <?php if (!empty($dashboardImportResult['details'])): ?>
        <details>
          <summary>Show details</summary>
          <pre><?= ho_h(json_encode($dashboardImportResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <form method="post">
    <textarea name="dashboard_research_json" class="admin-textarea dashboard-import-textarea" placeholder="Paste GPT JSON result here. Candidate batch, bulk triage results, or full business research JSON are accepted."><?= ho_h($dashboardImportText) ?></textarea>
    <div class="admin-next-row">
      <button class="admin-btn admin-btn-secondary" type="submit" name="validate_only" value="1">Validate</button>
      <button class="admin-btn admin-btn-primary" type="submit" name="import_now" value="1">Import</button>
    </div>
  </form>
</section>

<?php if ($dbError !== null): ?>
<section class="admin-status error">
  <div class="admin-status-head"><strong>Database Error</strong></div>
  <div class="admin-status-body"><p><?= ho_h($dbError) ?></p></div>
</section>
<?php else: ?>

<section class="admin-card">
  <h2>Queue Summary</h2>
  <div class="admin-stat-grid">
    <article class="admin-stat-card">
      <strong><?= ho_h((string)$snapshot['business_count']) ?></strong>
      <span>Prospects</span>
    </article>
    <article class="admin-stat-card">
      <strong><?= ho_h((string)($readinessCounts['ready'] ?? 0)) ?></strong>
      <span>Ready</span>
    </article>
    <article class="admin-stat-card">
      <strong><?= ho_h((string)($readinessCounts['soft_ready'] ?? 0)) ?></strong>
      <span>Soft Ready</span>
    </article>
    <article class="admin-stat-card">
      <strong><?= ho_h((string)(($readinessCounts['manual_review'] ?? 0) + ($readinessCounts['needs_more_research'] ?? 0))) ?></strong>
      <span>Needs Work</span>
    </article>
  </div>
</section>

<section class="admin-card">
  <h2>Find / Filter</h2>
  <form method="get" class="admin-form-grid">
    <input class="admin-input" name="q" value="<?= ho_h($search) ?>" placeholder="Search business, type, city, or service area">
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= ho_h($status) ?>"><?php endif; ?>
    <button class="admin-btn admin-btn-primary" type="submit">Search</button>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Reset</a>
  </form>

  <div class="admin-pill-list" style="margin-top:14px">
    <a class="admin-pill" href="/sales-portal-dashboard.php"><strong><?= ho_h((string)$snapshot['business_count']) ?></strong><span>All</span></a>
    <?php foreach (['cleared','warm_clear','needs_review','hold','skip','blocked'] as $s): ?>
      <a class="admin-pill" href="/sales-portal-dashboard.php?status=<?= ho_h($s) ?>">
        <strong><?= ho_h((string)($statusCounts[$s] ?? 0)) ?></strong>
        <span><?= ho_h(ho_salesportal_status_label($s)) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <h2>Prospects<?= $status !== '' ? ' — ' . ho_h(ho_salesportal_status_label($status)) : '' ?></h2>

  <?php if (empty($businesses)): ?>
    <div class="admin-empty-state">
      No prospects found yet. Go to Research, import one manually chosen business, then return here.
    </div>
  <?php else: ?>
    <div class="admin-data-list">
      <?php foreach ($businesses as $business): ?>
        <?php
          $ready = $business['_preview_readiness'] ?? null;
          $readinessLabel = $ready
              ? ho_salesportal_status_label((string)($ready['readiness_status'] ?? 'unknown'))
              : 'Not evaluated';
          $readinessScore = $ready && isset($ready['readiness_score'])
              ? (string)$ready['readiness_score']
              : '—';
          $assignment = ho_salesportal_admin_assignment_summary((int)$business['id']);
          $businessName = (string)($business['business_name_current'] ?: $business['business_slug']);
          $location = trim((string)$business['location_city'] . ', ' . (string)$business['location_state'], ', ');
        ?>
        <div class="admin-data-row prospect-queue-row">
          <div>
            <div class="admin-data-row-title"><?= ho_h($businessName) ?></div>
            <div class="admin-data-row-note">
              <?= ho_h((string)$business['business_type']) ?>
              <?= $location !== '' ? ' · ' . ho_h($location) : '' ?>
            </div>
            <div class="admin-data-row-note">
              <strong>Readiness:</strong> <?= ho_h($readinessLabel) ?><?= $readinessScore !== '—' ? ' · ' . ho_h($readinessScore) : '' ?>
              · <strong>Setup:</strong> <?= $assignment['top_design_label'] ? ho_h((string)$assignment['top_design_label']) : 'Not assigned' ?>
              · <strong>Next:</strong> <?= ho_h(ho_salesportal_admin_next_action($business)) ?>
            </div>
            <?php if (!empty($assignment['top_address'])): ?>
              <div class="admin-data-row-note"><strong>Address idea:</strong> <?= ho_h((string)$assignment['top_address']) ?></div>
            <?php endif; ?>
          </div>
          <div class="admin-next-row">
            <a class="admin-btn admin-btn-primary" href="/sales-business.php?id=<?= ho_h((string)$business['id']) ?>">Open</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php endif; ?>


<section class="admin-action-dock" id="prospects-bottom-dock">
  <a class="admin-btn admin-btn-primary" href="#dashboard-import">Paste Results</a>
  <a class="admin-btn admin-btn-secondary" href="#bulkTriagePromptBox">Bulk Prompt</a>
  <a class="admin-btn admin-btn-secondary" href="/admin.php">Admin</a>
</section>

<?php ho_admin_render_end(); ?>
