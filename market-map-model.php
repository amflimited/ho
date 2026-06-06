<?php
/**
 * Hoosier Online Market Map Model
 * v129 — read-oriented market/category coverage tracker
 *
 * No scraping. No sending. No imports. No mutation.
 */

declare(strict_types=1);

const HO_MARKET_MAP_VERSION = 'HO-MARKET-MAP-129';

function ho_market_claim_value(array $business, string $fieldKey): string {
    if (function_exists('ho_command_safe_claim_value')) return ho_command_safe_claim_value($business, $fieldKey);
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_market_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_market_category_key(array $business): string {
    $type = strtolower(trim((string)($business['business_type'] ?? '')));
    if ($type === '') $type = strtolower(trim(ho_market_claim_value($business, 'business_type')));
    $type = preg_replace('/[^a-z0-9]+/', '_', $type) ?? '';
    $type = trim($type, '_');
    return $type !== '' ? $type : 'local_service';
}

function ho_market_category_label(string $key): string {
    $labels = [
        'lawn_care' => 'Lawn Care',
        'cleaning' => 'Cleaning',
        'cleaning_service' => 'Cleaning',
        'handyman' => 'Handyman',
        'photography' => 'Photography',
        'photographer' => 'Photography',
        'pressure_washing' => 'Pressure Washing',
        'power_washing' => 'Pressure Washing',
        'junk_removal' => 'Junk Removal',
        'mobile_detailing' => 'Mobile Detailing',
        'auto_detailing' => 'Mobile Detailing',
        'pet_grooming' => 'Pet Grooming',
        'home_repair' => 'Home Repair',
        'contractor' => 'Contractor',
        'landscaping' => 'Landscaping',
        'tree_work' => 'Tree Work',
        'tree_service' => 'Tree Work',
        'snow_removal' => 'Snow Removal',
        'property_maintenance' => 'Property Maintenance',
        'instructor_coach' => 'Instructor / Coach',
        'event_service' => 'Event Service',
        'local_service' => 'Local Service',
    ];
    return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
}

function ho_market_region_key(array $business): string {
    $state = strtoupper(trim((string)($business['location_state'] ?? 'IN')));
    $city = trim((string)($business['location_city'] ?? ''));
    if ($city === '') $city = 'Unknown City';
    return $state . ' / ' . $city;
}

function ho_market_source_context(array $business): string {
    $source = ho_market_claim_value($business, 'source_context');
    if ($source !== '') return $source;
    $batch = ho_market_claim_value($business, 'source_batch');
    if ($batch !== '') return $batch;
    $origin = ho_market_claim_value($business, 'lead_source');
    if ($origin !== '') return $origin;
    return 'Current records';
}

function ho_market_blank_stats(): array {
    return [
        'total' => 0,
        'need_triage' => 0,
        'need_research' => 0,
        'contact_ready' => 0,
        'diagnosis_ready' => 0,
        'go_ready' => 0,
        'outreach_draft_needed' => 0,
        'draft_ready' => 0,
        'blocked_skip' => 0,
        'manual_review' => 0,
        'customer_interested' => 0,
    ];
}

function ho_market_state_for_business(array $business): array {
    $state = function_exists('ho_command_evaluate_business')
        ? ho_command_evaluate_business($business)
        : [
            'queue_key' => 'need_triage',
            'diagnosis_ready' => false,
            'has_go_page' => false,
            'draft_ready' => false,
            'manual_review' => false,
        ];

    $marketingStatus = strtolower(ho_market_claim_value($business, 'marketing_desk_status'));
    $salesStatus = strtolower(ho_market_claim_value($business, 'sales_status'));
    $customerSignals = ['interested','customer','paid','won','closed_won'];

    return [
        'queue_key' => $state['queue_key'] ?? 'need_triage',
        'diagnosis_ready' => (bool)($state['diagnosis_ready'] ?? false),
        'has_go_page' => (bool)($state['has_go_page'] ?? false),
        'draft_ready' => (bool)($state['draft_ready'] ?? false),
        'manual_review' => (bool)($state['manual_review'] ?? false),
        'outreach_draft_needed' => (bool)($state['has_go_page'] ?? false) && !(bool)($state['draft_ready'] ?? false),
        'customer_interested' => in_array($marketingStatus, $customerSignals, true) || in_array($salesStatus, $customerSignals, true),
    ];
}

function ho_market_apply_state(array &$stats, array $state): void {
    $stats['total']++;

    match ($state['queue_key']) {
        'need_triage' => $stats['need_triage']++,
        'need_research' => $stats['need_research']++,
        'contact_ready' => $stats['contact_ready']++,
        'blocked', 'blocked_skip' => $stats['blocked_skip']++,
        'manual_review' => $stats['manual_review']++,
        default => null,
    };

    if ($state['diagnosis_ready']) $stats['diagnosis_ready']++;
    if ($state['has_go_page']) $stats['go_ready']++;
    if ($state['outreach_draft_needed']) $stats['outreach_draft_needed']++;
    if ($state['draft_ready']) $stats['draft_ready']++;
    if ($state['customer_interested']) $stats['customer_interested']++;
}

function ho_market_build_map(array $businesses): array {
    $categories = [];
    $regions = [];
    $sources = [];
    $overall = ho_market_blank_stats();

    foreach ($businesses as $business) {
        if (!is_array($business)) continue;

        $cat = ho_market_category_key($business);
        $region = ho_market_region_key($business);
        $source = ho_market_source_context($business);
        $state = ho_market_state_for_business($business);

        if (!isset($categories[$cat])) {
            $categories[$cat] = [
                'key' => $cat,
                'label' => ho_market_category_label($cat),
                'stats' => ho_market_blank_stats(),
                'records' => [],
                'regions' => [],
            ];
        }
        if (!isset($regions[$region])) {
            $regions[$region] = [
                'key' => $region,
                'label' => $region,
                'stats' => ho_market_blank_stats(),
                'records' => [],
            ];
        }
        if (!isset($sources[$source])) {
            $sources[$source] = [
                'key' => $source,
                'label' => $source,
                'stats' => ho_market_blank_stats(),
                'records' => [],
            ];
        }

        ho_market_apply_state($overall, $state);
        ho_market_apply_state($categories[$cat]['stats'], $state);
        ho_market_apply_state($regions[$region]['stats'], $state);
        ho_market_apply_state($sources[$source]['stats'], $state);

        $categories[$cat]['records'][] = $business;
        $categories[$cat]['regions'][$region] = ($categories[$cat]['regions'][$region] ?? 0) + 1;
        $regions[$region]['records'][] = $business;
        $sources[$source]['records'][] = $business;
    }

    uasort($categories, static fn($a, $b) => $b['stats']['total'] <=> $a['stats']['total']);
    uasort($regions, static fn($a, $b) => $b['stats']['total'] <=> $a['stats']['total']);
    uasort($sources, static fn($a, $b) => $b['stats']['total'] <=> $a['stats']['total']);

    return [
        'version' => HO_MARKET_MAP_VERSION,
        'overall' => $overall,
        'categories' => $categories,
        'regions' => $regions,
        'sources' => $sources,
        'bottlenecks' => ho_market_bottlenecks($categories),
        'next_market_action' => ho_market_next_action($categories),
    ];
}

function ho_market_largest(array $categories, string $field): ?array {
    $best = null;
    foreach ($categories as $cat) {
        if ($best === null || ($cat['stats'][$field] ?? 0) > ($best['stats'][$field] ?? 0)) {
            $best = $cat;
        }
    }
    return ($best && ($best['stats'][$field] ?? 0) > 0) ? $best : null;
}

function ho_market_bottlenecks(array $categories): array {
    $fields = [
        'need_triage' => 'Largest category needing triage',
        'need_research' => 'Largest category needing research',
        'diagnosis_ready' => 'Largest category with diagnosis ready',
        'go_ready' => 'Largest category with /go ready',
        'outreach_draft_needed' => 'Largest category needing outreach drafts',
        'blocked_skip' => 'Largest blocked/skip category',
        'manual_review' => 'Largest manual review category',
    ];

    $out = [];
    foreach ($fields as $field => $label) {
        $cat = ho_market_largest($categories, $field);
        $out[$field] = [
            'label' => $label,
            'category' => $cat['label'] ?? 'None',
            'count' => $cat ? (int)($cat['stats'][$field] ?? 0) : 0,
        ];
    }
    return $out;
}

function ho_market_next_action(array $categories): array {
    $draft = ho_market_largest($categories, 'outreach_draft_needed');
    if ($draft) {
        return [
            'key' => 'draft_outreach_for_category',
            'title' => 'Draft outreach for ' . $draft['label'],
            'why' => $draft['stats']['outreach_draft_needed'] . ' ' . $draft['label'] . ' records have /go previews but still need outreach drafts.',
            'target_url' => '/sales-marketing-desk.php',
            'target_label' => 'Open Marketing Desk',
        ];
    }

    $diagnosis = ho_market_largest($categories, 'contact_ready');
    if ($diagnosis) {
        $needs = max(0, (int)$diagnosis['stats']['contact_ready'] - (int)$diagnosis['stats']['diagnosis_ready']);
        if ($needs > 0) {
            return [
                'key' => 'diagnose_category',
                'title' => 'Finish diagnosis for ' . $diagnosis['label'],
                'why' => $needs . ' contact-ready ' . $diagnosis['label'] . ' records appear to still need diagnosis keys.',
                'target_url' => '/sales-diagnosis-workbench.php',
                'target_label' => 'Open Diagnosis Workbench',
            ];
        }
    }

    $go = ho_market_largest($categories, 'diagnosis_ready');
    if ($go) {
        $needsGo = max(0, (int)$go['stats']['diagnosis_ready'] - (int)$go['stats']['go_ready']);
        if ($needsGo > 0) {
            return [
                'key' => 'build_go_for_category',
                'title' => 'Finish /go previews for ' . $go['label'],
                'why' => $needsGo . ' diagnosed ' . $go['label'] . ' records need /go preview paths.',
                'target_url' => '/sales-front-door-builder.php',
                'target_label' => 'Open Front Door Builder',
            ];
        }
    }

    return [
        'key' => 'source_more_indiana_businesses',
        'title' => 'Find more Indiana local service businesses',
        'why' => 'No single category has a larger ready bottleneck. Start the next sourcing batch by category or region.',
        'target_url' => '/sales-portal-dashboard.php',
        'target_label' => 'Return To Command Center',
    ];
}

function ho_market_completion_percent(array $stats): int {
    $total = max(1, (int)($stats['total'] ?? 0));
    $ready = (int)($stats['draft_ready'] ?? 0) + (int)($stats['customer_interested'] ?? 0);
    return (int)round(($ready / $total) * 100);
}
?>