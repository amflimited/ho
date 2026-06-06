<?php
/**
 * Hoosier Online Prep Model
 * v136 — Combined Diagnosis + Outreach Draft Prompt
 *
 * Prompt builder only by default. No fake research-evidence writes.
 */

declare(strict_types=1);

const HO_PREP_MODEL_VERSION = 'HO-SALES-PREP-136';

function ho_prep_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_prep_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_prep_value(array $business, string $key, string $claimFallback = ''): string {
    $value = trim((string)($business[$key] ?? ''));
    if ($value !== '') return $value;
    if ($claimFallback !== '') return ho_prep_claim_value($business, $claimFallback);
    return '';
}

function ho_prep_json_claim(array $business, string $fieldKey): array {
    $raw = ho_prep_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_prep_public_surfaces(array $business): array {
    return [
        'website_url' => ho_prep_value($business, 'website_url', 'website_url'),
        'facebook_url' => ho_prep_value($business, 'facebook_url', 'facebook_url'),
        'google_profile_url' => ho_prep_value($business, 'google_profile_url', 'google_profile_url'),
        'email_address' => ho_prep_value($business, 'email_address', 'email_address'),
        'phone_number' => ho_prep_value($business, 'phone_number', 'phone_number'),
    ];
}

function ho_prep_computed_preview_url(array $business): string {
    $slug = ho_prep_value($business, 'business_slug');
    return $slug !== '' ? '/go.php?slug=' . rawurlencode($slug) : '';
}

function ho_prep_has_complete_salesprep(array $business): bool {
    $strengths = ho_prep_json_claim($business, 'strength_keys_json');
    $weaknesses = ho_prep_json_claim($business, 'weakness_keys_json');
    $recommendations = ho_prep_json_claim($business, 'recommendation_keys_json');
    $directions = ho_prep_json_claim($business, 'preview_direction_keys_json');
    $offer = ho_prep_claim_value($business, 'primary_offer_path');
    $subject = ho_prep_claim_value($business, 'outreach_subject');
    $body = ho_prep_claim_value($business, 'outreach_body');

    return count($strengths) > 0
        && count($weaknesses) > 0
        && count($recommendations) > 0
        && count($directions) >= 3
        && $offer !== ''
        && $subject !== ''
        && $body !== '';
}

function ho_prep_is_contact_ready(array $business): bool {
    $readiness = strtolower(trim(ho_prep_value($business, 'contact_readiness', 'contact_readiness')));
    $queue = strtolower(trim(ho_prep_claim_value($business, 'queue_key')));
    $status = strtolower(trim(ho_prep_claim_value($business, 'marketing_clearance_status')));

    if (in_array($readiness, ['contact_ready', 'ready', 'ready_to_contact'], true)) return true;
    if (in_array($queue, ['contact_ready', 'proceed_no_website', 'ready_for_setup'], true)) return true;
    if (in_array($status, ['cleared', 'contact_ready'], true)) return true;

    // If there is a public contact surface and not explicitly blocked, it can be considered prep-eligible.
    $blocked = strtolower(trim(ho_prep_claim_value($business, 'blocked_reason')));
    $skip = strtolower(trim(ho_prep_claim_value($business, 'skip_reason')));
    if ($blocked !== '' || $skip !== '') return false;

    $surfaces = ho_prep_public_surfaces($business);
    return ho_prep_value($business, 'business_slug') !== ''
        && ho_prep_value($business, 'business_name_current') !== ''
        && (bool)array_filter($surfaces, static fn($v) => trim((string)$v) !== '');
}

function ho_prep_visible_clues(array $business): array {
    $clueKeys = [
        'visible_services',
        'visible_trust_signals',
        'visible_weakness_clues',
        'contact_path_clue',
        'personalization_clue',
        'primary_sales_angle',
        'contact_readiness',
        'best_contact_method',
    ];
    $out = [];
    foreach ($clueKeys as $key) {
        $value = ho_prep_value($business, $key, $key);
        if ($value !== '') $out[$key] = $value;
    }
    $notes = ho_prep_claim_value($business, 'research_notes');
    if ($notes !== '') $out['research_notes'] = $notes;
    return $out;
}

function ho_prep_registry_payload(): array {
    $payload = [
        'strength_keys' => [],
        'weakness_keys' => [],
        'recommendation_keys' => [],
        'preview_direction_keys' => [],
        'offer_paths' => ['standard_front_door', 'managed_front_door'],
    ];

    if (function_exists('ho_diag_strength_registry')) {
        $payload['strength_keys'] = array_keys(ho_diag_strength_registry());
    }
    if (function_exists('ho_diag_weakness_registry')) {
        $payload['weakness_keys'] = array_keys(ho_diag_weakness_registry());
    }
    if (function_exists('ho_diag_recommendation_registry')) {
        $payload['recommendation_keys'] = array_keys(ho_diag_recommendation_registry());
    }
    if (function_exists('ho_diag_preview_direction_registry')) {
        $payload['preview_direction_keys'] = array_keys(ho_diag_preview_direction_registry());
    }
    if (function_exists('ho_diag_offer_path_registry')) {
        $payload['offer_paths'] = array_keys(ho_diag_offer_path_registry());
    }

    // Fallback keys if registry names differ or model is unavailable.
    if (!$payload['strength_keys']) {
        $payload['strength_keys'] = ['public_contact_visible','local_service_clear','facebook_presence','photos_or_examples_visible','service_area_visible'];
    }
    if (!$payload['weakness_keys']) {
        $payload['weakness_keys'] = ['no_clear_website','weak_contact_path','unclear_services','missing_trust_proof','poor_mobile_first_impression'];
    }
    if (!$payload['recommendation_keys']) {
        $payload['recommendation_keys'] = ['create_simple_front_door','make_contact_easy','clarify_services','add_trust_signals','improve_mobile_path'];
    }
    if (!$payload['preview_direction_keys']) {
        $payload['preview_direction_keys'] = ['clean_trustworthy','local_service_plain','photo_forward','fast_contact_first','family_owned_warm'];
    }

    return $payload;
}

function ho_prep_queue(array $businesses, int $limit = 25): array {
    $rows = [];
    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        if (!ho_prep_is_contact_ready($business)) continue;
        if (ho_prep_has_complete_salesprep($business)) continue;
        if (ho_prep_computed_preview_url($business) === '') continue;
        $rows[] = $business;
        if (count($rows) >= $limit) break;
    }
    return $rows;
}

function ho_prep_prompt_items(array $businesses): array {
    $items = [];
    foreach ($businesses as $business) {
        $items[] = [
            'business_id' => (int)($business['id'] ?? 0),
            'business_slug' => ho_prep_value($business, 'business_slug'),
            'business_name' => ho_prep_value($business, 'business_name_current'),
            'category' => ho_prep_value($business, 'business_type') ?: 'local_service',
            'city' => ho_prep_value($business, 'location_city'),
            'state' => ho_prep_value($business, 'location_state') ?: 'IN',
            'public_contact_surfaces' => ho_prep_public_surfaces($business),
            'visible_research_or_source_clues' => ho_prep_visible_clues($business),
            'computed_preview_url' => ho_prep_computed_preview_url($business),
        ];
    }
    return $items;
}

function ho_prep_prompt_payload(array $businesses): array {
    $registry = ho_prep_registry_payload();
    return [
        'task' => 'combined_sales_prep_for_hoosier_online',
        'purpose' => 'Create diagnosis keys, a short personalization summary, and manual outreach draft data for contact-ready Indiana local service businesses.',
        'businesses' => ho_prep_prompt_items($businesses),
        'computed_preview_rule' => [
            'preview_url_formula' => '/go.php?slug={business_slug}',
            'do_not_write_go_path_or_go_slug' => true,
            'front_door_builder_required' => false,
        ],
        'allowed_registries' => $registry,
        'rules' => [
            'Return ONLY valid JSON.',
            'Use only allowed registry keys when possible.',
            'Do not write custom customer-facing page copy.',
            'Use public/customer-facing facts only.',
            'Do not invent private facts.',
            'No guaranteed leads, rankings, calls, or sales.',
            'No fake familiarity.',
            'No SMS.',
            'No AI calls.',
            'Use the computed preview_url for outreach.',
            'Keep outreach respectful, brief, and low-pressure.',
        ],
        'output_contract' => [
            'sales_prep_batch' => [
                'batch_type' => 'sales_prep',
                'source' => 'combined_diagnosis_and_outreach',
            ],
            'items' => [
                [
                    'business_id' => 0,
                    'business_slug' => 'existing-business-slug',
                    'diagnosis_status' => 'prep_ready',
                    'strength_keys_json' => ['allowed_strength_key'],
                    'weakness_keys_json' => ['allowed_weakness_key'],
                    'recommendation_keys_json' => ['allowed_recommendation_key'],
                    'primary_offer_path' => 'standard_front_door',
                    'preview_direction_keys_json' => ['allowed_direction_1','allowed_direction_2','allowed_direction_3'],
                    'personalization_summary' => 'One short factual summary based on public clues.',
                    'outreach_to' => 'public contact value or contact form URL',
                    'outreach_contact_method' => 'email|contact_form|facebook_public_message|phone_manual_only|manual_review',
                    'outreach_subject' => 'Short truthful subject line',
                    'outreach_body' => 'Short respectful manual outreach body using computed_preview_url.',
                    'warnings' => [],
                    'next_step' => 'send_tray',
                ],
            ],
        ],
    ];
}

function ho_prep_prompt_text(array $payload): string {
    return "You are preparing Hoosier Online manual sales outreach for Indiana local service businesses.\n\n"
        . "Return ONLY valid JSON. Do not include markdown.\n"
        . "Generate diagnosis keys and manual outreach draft data in one batch.\n"
        . "Use the computed preview URL supplied for each business. Do not require Front Door Builder. Do not write go_slug or go_path.\n"
        . "Use public customer-facing facts only. Do not invent private facts.\n"
        . "No guaranteed leads, rankings, calls, or sales. No fake familiarity. No SMS. No AI calls.\n\n"
        . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>