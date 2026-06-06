<?php
/**
 * Hoosier Online Front Door Preview Model
 * v126 — customer-facing /go.php?slug={business_slug}
 *
 * Deterministic renderer from stored diagnosis claims.
 * No outreach. No payment. No GPT. No static generation.
 */

declare(strict_types=1);

const HO_FRONT_DOOR_PREVIEW_VERSION = 'HO-FRONT-DOOR-PREVIEW-126';

function ho_front_claim_value(array $business, string $fieldKey): string {
    if (function_exists('ho_diag_claim_value')) {
        return ho_diag_claim_value($business, $fieldKey);
    }
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_front_json_claim(array $business, string $fieldKey): array {
    if (function_exists('ho_diag_json_claim')) {
        return ho_diag_json_claim($business, $fieldKey);
    }
    $raw = ho_front_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_front_find_business_by_slug(string $slug): ?array {
    $slug = trim($slug);
    if ($slug === '') return null;

    $businesses = [];
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        $businesses = ho_salesportal_list_businesses_with_readiness(null, '');
    } elseif (function_exists('ho_salesportal_list_businesses')) {
        $businesses = ho_salesportal_list_businesses(null, '');
    }

    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        if ((string)($business['business_slug'] ?? '') === $slug) {
            return $business;
        }
    }

    return null;
}

function ho_front_template(string $template, array $business): string {
    if (function_exists('ho_diag_render_template')) {
        return ho_diag_render_template($template, $business);
    }
    $name = (string)($business['business_name_current'] ?? 'this business');
    $category = str_replace('_', ' ', (string)($business['business_type'] ?? 'local service business'));
    $city = (string)($business['location_city'] ?? '');
    $state = (string)($business['location_state'] ?? 'IN');
    return strtr($template, [
        '{business_name}' => $name,
        '{category_label}' => $category,
        '{city}' => $city,
        '{state}' => $state,
        '{location_label}' => trim($city . ', ' . $state, ', '),
    ]);
}

function ho_front_block(array $registry, string $key): ?array {
    if (function_exists('ho_diag_find_block')) {
        return ho_diag_find_block($registry, $key);
    }
    foreach ($registry as $group => $data) {
        if (isset($data['blocks'][$key])) {
            $block = $data['blocks'][$key];
            $block['group_key'] = $group;
            $block['group_label'] = $data['label'] ?? ucwords(str_replace('_', ' ', $group));
            $block['key'] = $key;
            return $block;
        }
    }
    return null;
}

function ho_front_registry_available(): bool {
    return function_exists('ho_diag_strength_registry')
        && function_exists('ho_diag_weakness_registry')
        && function_exists('ho_diag_recommendation_registry')
        && function_exists('ho_diag_preview_direction_registry')
        && function_exists('ho_diag_offer_registry');
}

function ho_front_business_ready(array $business): bool {
    $strengths = ho_front_json_claim($business, 'strength_keys_json');
    $weaknesses = ho_front_json_claim($business, 'weakness_keys_json');
    $recommendations = ho_front_json_claim($business, 'recommendation_keys_json');
    $directions = ho_front_json_claim($business, 'preview_direction_keys_json');
    return count($strengths) > 0 && count($weaknesses) > 0 && count($recommendations) > 0 && count($directions) > 0;
}

function ho_front_assemble(array $business): array {
    if (!ho_front_registry_available()) {
        throw new RuntimeException('Diagnosis registry functions are unavailable.');
    }

    $strengthKeys = array_slice(ho_front_json_claim($business, 'strength_keys_json'), 0, 5);
    $weaknessKeys = array_slice(ho_front_json_claim($business, 'weakness_keys_json'), 0, 5);
    $recommendationKeys = array_slice(ho_front_json_claim($business, 'recommendation_keys_json'), 0, 4);
    $directionKeys = array_slice(ho_front_json_claim($business, 'preview_direction_keys_json'), 0, 3);
    $offerKey = ho_front_claim_value($business, 'primary_offer_path') ?: 'standard_front_door';

    $strengths = [];
    foreach ($strengthKeys as $key) {
        $block = ho_front_block(ho_diag_strength_registry(), (string)$key);
        if ($block) {
            $strengths[] = [
                'key' => (string)$key,
                'group' => $block['group_label'] ?? '',
                'headline' => $block['headline'] ?? '',
                'body' => ho_front_template((string)($block['body_template'] ?? ''), $business),
            ];
        }
    }

    $weaknesses = [];
    foreach ($weaknessKeys as $key) {
        $block = ho_front_block(ho_diag_weakness_registry(), (string)$key);
        if ($block) {
            $weaknesses[] = [
                'key' => (string)$key,
                'group' => $block['group_label'] ?? '',
                'headline' => $block['headline'] ?? '',
                'body' => ho_front_template((string)($block['body_template'] ?? ''), $business),
            ];
        }
    }

    $recommendations = [];
    foreach ($recommendationKeys as $key) {
        $block = ho_front_block(ho_diag_recommendation_registry(), (string)$key);
        if ($block) {
            $recommendations[] = [
                'key' => (string)$key,
                'group' => $block['group_label'] ?? '',
                'headline' => $block['headline'] ?? '',
                'body' => ho_front_template((string)($block['body_template'] ?? ''), $business),
            ];
        }
    }

    $directionRegistry = ho_diag_preview_direction_registry();
    $directions = [];
    foreach ($directionKeys as $key) {
        $key = (string)$key;
        if (isset($directionRegistry[$key])) {
            $directions[] = [
                'key' => $key,
                'label' => $directionRegistry[$key]['label'] ?? ucwords(str_replace('_', ' ', $key)),
                'description' => $directionRegistry[$key]['description'] ?? '',
                'default_cta' => $directionRegistry[$key]['default_cta'] ?? 'Start Here',
            ];
        }
    }

    // Maintain exactly 3 preview cards when possible by filling with safe defaults.
    foreach ($directionRegistry as $key => $data) {
        if (count($directions) >= 3) break;
        $exists = false;
        foreach ($directions as $dir) {
            if ($dir['key'] === $key) { $exists = true; break; }
        }
        if (!$exists) {
            $directions[] = [
                'key' => $key,
                'label' => $data['label'] ?? ucwords(str_replace('_', ' ', $key)),
                'description' => $data['description'] ?? '',
                'default_cta' => $data['default_cta'] ?? 'Start Here',
            ];
        }
    }

    $offers = ho_diag_offer_registry();
    $offer = $offers[$offerKey] ?? ($offers['standard_front_door'] ?? [
        'label' => 'Standard Front Door',
        'price_label' => '$499 setup',
        'summary' => 'One clean customer-facing front door with services, trust sections, and a clear contact/request path.',
        'cta' => 'Start My Front Door',
    ]);

    $name = (string)($business['business_name_current'] ?? 'this business');
    $city = (string)($business['location_city'] ?? '');
    $state = (string)($business['location_state'] ?? 'IN');
    $category = str_replace('_', ' ', (string)($business['business_type'] ?? 'local service business'));
    $location = trim($city . ', ' . $state, ', ');

    return [
        'version' => HO_FRONT_DOOR_PREVIEW_VERSION,
        'business' => [
            'name' => $name,
            'slug' => (string)($business['business_slug'] ?? ''),
            'category' => $category,
            'location' => $location,
        ],
        'intro' => "We were looking through Indiana local service businesses and put together a quick online front-door preview for {$name}.",
        'noticed' => "The goal is not to make something fancy just for the sake of it. The goal is to give a customer one clear place to understand the business, trust it, and take the next step.",
        'strengths' => $strengths,
        'weaknesses' => $weaknesses,
        'recommendations' => $recommendations,
        'preview_directions' => array_slice($directions, 0, 3),
        'offer' => $offer,
        'cta' => [
            'primary' => $offer['cta'] ?? 'Start My Front Door',
            'secondary' => 'Ask Adam A Question',
            'email' => 'adam@hoosieronline.com',
        ],
    ];
}
?>