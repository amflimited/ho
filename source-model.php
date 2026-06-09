<?php
/**
 * Hoosier Online Source Model
 * v133 — Lead Generation Prompt Builder
 *
 * Read-only prompt builder. No intake/import. No scraping automation.
 */

declare(strict_types=1);

const HO_SOURCE_MODEL_VERSION = 'HO-SOURCE-MODULE-133';

function ho_source_hard_limit_int($value, int $default, int $min, int $max): int {
    $n = (int)$value;
    if ($n < $min) return $default;
    if ($n > $max) return $max;
    return $n;
}

function ho_source_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_source_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_source_business_value(array $business, string $key, string $claimFallback = ''): string {
    $value = trim((string)($business[$key] ?? ''));
    if ($value !== '') return $value;
    if ($claimFallback !== '') return ho_source_claim_value($business, $claimFallback);
    return '';
}

function ho_source_category_label(string $key): string {
    $key = strtolower(trim($key));
    $labels = [
        'lawn_care' => 'Lawn Care',
        'cleaning' => 'Cleaning',
        'handyman' => 'Handyman',
        'photography' => 'Photography',
        'pressure_washing' => 'Pressure Washing',
        'junk_removal' => 'Junk Removal',
        'mobile_detailing' => 'Mobile Detailing',
        'pet_grooming' => 'Pet Grooming',
        'home_repair' => 'Home Repair',
        'contractor' => 'Contractor',
        'landscaping' => 'Landscaping',
        'tree_work' => 'Tree Work',
        'snow_removal' => 'Snow Removal',
        'property_maintenance' => 'Property Maintenance',
        'instructor_coach' => 'Instructor / Coach',
        'event_service' => 'Event Service',
        'local_service' => 'Local Service',
    ];
    return $labels[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key));
}

function ho_source_normalize_context(string $value, string $fallback): string {
    $value = trim($value);
    if ($value === '') return $fallback;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value;
}

function ho_source_known_business_packet(array $businesses, string $categoryContext = '', string $areaContext = '', int $limit = 150): array {
    $categoryContext = strtolower(trim($categoryContext));
    $areaContext = strtolower(trim($areaContext));
    $items = [];

    foreach ($businesses as $business) {
        if (!is_array($business)) continue;

        $id = (int)($business['id'] ?? 0);
        $slug = ho_source_business_value($business, 'business_slug');
        $name = ho_source_business_value($business, 'business_name_current');
        if ($slug === '' && $name === '') continue;

        $type = strtolower(ho_source_business_value($business, 'business_type'));
        $city = ho_source_business_value($business, 'location_city');
        $state = ho_source_business_value($business, 'location_state') ?: 'IN';

        // Compact relevance filter: if category/area are provided, prefer matches first but still include enough global knowns.
        $score = 0;
        if ($categoryContext !== '' && ($type === $categoryContext || str_contains($type, $categoryContext) || str_contains($categoryContext, $type))) $score += 2;
        if ($areaContext !== '') {
            $areaHaystack = strtolower($city . ' ' . $state . ' ' . ho_source_business_value($business, 'service_area_text'));
            foreach (preg_split('/[,;|]+/', $areaContext) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '' && str_contains($areaHaystack, $part)) $score += 2;
            }
        }

        $items[] = [
            '_score' => $score,
            'business_id' => $id,
            'business_slug' => $slug,
            'business_name' => $name,
            'city' => $city,
            'state' => $state,
            'website_url' => ho_source_business_value($business, 'website_url', 'website_url'),
            'facebook_url' => ho_source_business_value($business, 'facebook_url', 'facebook_url'),
            'google_profile_url' => ho_source_business_value($business, 'google_profile_url', 'google_profile_url'),
            'email_address' => ho_source_business_value($business, 'email_address', 'email_address'),
            'phone_number' => ho_source_business_value($business, 'phone_number', 'phone_number'),
        ];
    }

    usort($items, static function ($a, $b) {
        return ($b['_score'] <=> $a['_score']) ?: strcmp((string)$a['business_name'], (string)$b['business_name']);
    });

    $items = array_slice($items, 0, max(1, $limit));
    foreach ($items as &$item) unset($item['_score']);
    unset($item);

    return [
        'exclusion_basis' => [
            'definition' => 'Already-known businesses from the Hoosier Online database. Do not return new candidates that match these by identity, name+city, slug, website, social URL, email, phone, or obvious same-business identity.',
            'category_context' => $categoryContext,
            'area_context' => $areaContext,
            'limit_applied' => $limit,
            'known_count_in_packet' => count($items),
            'match_fields' => [
                'business_slug',
                'business_name',
                'city/state',
                'website_url',
                'facebook_url',
                'google_profile_url',
                'email_address',
                'phone_number',
            ],
        ],
        'known_businesses' => $items,
    ];
}

function ho_source_prompt_payload(array $args, array $exclusionPacket): array {
    $category = ho_source_normalize_context((string)($args['category_context'] ?? ''), 'local_service');
    $area = ho_source_normalize_context((string)($args['area_context'] ?? ''), 'Indiana');
    $target = ho_source_hard_limit_int($args['target_count'] ?? 15, 15, 5, 100);
    $sourceMethod = ho_source_normalize_context((string)($args['source_method'] ?? ''), 'gpt_public_research');

    return [
        'task' => 'source_candidate_indiana_local_service_businesses',
        'market_target' => [
            'category_context' => $category,
            'category_label' => ho_source_category_label($category),
            'state_gate' => 'IN',
            'area_context' => $area,
            'target_count' => $target,
            'source_method' => $sourceMethod,
        ],
        'known_business_exclusion_packet' => $exclusionPacket,
        'source_rules' => [
            'Indiana is the broad location gate.',
            'City, service_area, and category are sourcing context only.',
            'Do not require New Castle or any single city unless explicitly stated as the area_context.',
            'Do not reject adjacent Indiana local service businesses solely because the category_context is narrower.',
            'Use public customer-facing information only.',
            'Do not invent private facts.',
            'Exclude already-known businesses using the provided exclusion packet.',
            'Return ONLY valid JSON.',
        ],
        'diagnosis_and_personalization_precursors_to_gather' => [
            'visible_services',
            'visible_trust_signals',
            'visible_weakness_clues',
            'contact_path_clue',
            'personalization_clue',
            'duplicate_risk_clue',
            'source_confidence',
        ],
        'candidate_row_contract' => [
            'raw_business_name' => 'Business name as publicly shown',
            'likely_category' => $category,
            'city' => 'City',
            'state' => 'IN',
            'source_url' => 'Public page where the candidate was found',
            'website_url' => 'Business\'s own website URL only — NOT Angi, Thumbtack, Yelp, HomeAdvisor, Houzz, Bark, or Porch profile pages. Empty string if no real owned website found.',
            'facebook_url' => 'Public Facebook/profile URL if found, else empty string',
            'google_profile_url' => 'Google Business/Profile URL if found, else empty string',
            'public_email' => 'Public customer-facing email if found, else empty string',
            'public_phone' => 'Public customer-facing phone if found, else empty string',
            'visible_services' => ['service 1', 'service 2'],
            'visible_trust_signals' => ['reviews/photos/years/certifications/portfolio if public'],
            'visible_weakness_clues' => ['no website', 'unclear contact path', 'outdated page', 'unclear services'],
            'contact_path_clue' => 'Best visible customer contact path',
            'personalization_clue' => 'Short factual clue useful later for a personalized diagnosis or outreach',
            'duplicate_risk_clue' => 'Why this does or does not appear to match a known business',
            'source_confidence' => 'high|medium|low',
            'intake_status_recommendation' => 'intake_ready|needs_review|possible_duplicate|reject',
        ],
        'output_contract' => [
            'candidate_batch' => [
                'batch_type' => 'source_candidates',
                'market_target' => [
                    'category_context' => $category,
                    'state_gate' => 'IN',
                    'area_context' => $area,
                    'source_method' => $sourceMethod,
                ],
            ],
            'candidates' => [
                [
                    'raw_business_name' => 'Example Business LLC',
                    'likely_category' => $category,
                    'city' => 'Anderson',
                    'state' => 'IN',
                    'source_url' => 'https://...',
                    'website_url' => '',
                    'facebook_url' => '',
                    'google_profile_url' => '',
                    'public_email' => '',
                    'public_phone' => '',
                    'visible_services' => ['Example service'],
                    'visible_trust_signals' => ['Example public trust signal'],
                    'visible_weakness_clues' => ['Example public weakness clue'],
                    'contact_path_clue' => 'Example contact clue',
                    'personalization_clue' => 'Example personalization clue',
                    'duplicate_risk_clue' => 'No obvious match to exclusion packet.',
                    'source_confidence' => 'medium',
                    'intake_status_recommendation' => 'intake_ready',
                ],
            ],
        ],
    ];
}

function ho_source_prompt_text(array $payload): string {
    return "You are sourcing candidate Indiana local service businesses for Hoosier Online.\n\n"
        . "Return ONLY valid JSON. Do not include markdown.\n"
        . "Use public customer-facing information only. Do not invent private facts.\n"
        . "Respect the known-business exclusion packet. Do not return businesses that appear to already be known.\n"
        . "Indiana is the broad location gate. City/service_area/category are sourcing context only.\n"
        . "Adjacent Indiana local service businesses are allowed when they fit the Hoosier Online sales target.\n\n"
        . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>