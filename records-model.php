<?php
/**
 * Hoosier Online Records Model
 * v135 — Records Repair Module
 *
 * Read-only repair bay helpers. No destructive writes. No merge writes.
 */

declare(strict_types=1);

const HO_RECORDS_MODEL_VERSION = 'HO-RECORDS-REPAIR-135';

function ho_records_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_records_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_records_value(array $business, string $key, string $claimFallback = ''): string {
    $value = trim((string)($business[$key] ?? ''));
    if ($value !== '') return $value;
    if ($claimFallback !== '') return ho_records_claim_value($business, $claimFallback);
    return '';
}

function ho_records_normalize_url(string $value): string {
    $value = strtolower(trim($value));
    if ($value === '') return '';
    $value = preg_replace('#^https?://#', '', $value) ?? $value;
    $value = preg_replace('#^www\.#', '', $value) ?? $value;
    return rtrim($value, "/ \t\n\r\0\x0B");
}

function ho_records_normalize_phone(string $value): string {
    return preg_replace('/\D+/', '', $value) ?? '';
}

function ho_records_normalize_email(string $value): string {
    return strtolower(trim($value));
}

function ho_records_normalize_name(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/\b(llc|l\.l\.c\.|inc|co|company|services|service|the)\b/i', '', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function ho_records_similarity(string $a, string $b): int {
    $a = ho_records_normalize_name($a);
    $b = ho_records_normalize_name($b);
    if ($a === '' || $b === '') return 0;
    similar_text($a, $b, $pct);
    return (int)round($pct);
}

function ho_records_surface_summary(array $business): array {
    return [
        'website' => ho_records_value($business, 'website_url', 'website_url'),
        'facebook' => ho_records_value($business, 'facebook_url', 'facebook_url'),
        'google' => ho_records_value($business, 'google_profile_url', 'google_profile_url'),
        'email' => ho_records_value($business, 'email_address', 'email_address'),
        'phone' => ho_records_value($business, 'phone_number', 'phone_number'),
    ];
}

function ho_records_status_clues(array $business): array {
    $keys = [
        'contact_readiness',
        'diagnosis_status',
        'marketing_desk_status',
        'front_door_preview_status',
        'package_status',
        'marketing_clearance_status',
    ];
    $out = [];
    foreach ($keys as $key) {
        $value = ho_records_claim_value($business, $key);
        if ($value !== '') $out[$key] = $value;
    }
    if (!$out && isset($business['contact_readiness']) && trim((string)$business['contact_readiness']) !== '') {
        $out['contact_readiness'] = trim((string)$business['contact_readiness']);
    }
    return $out;
}

function ho_records_problem_flags(array $business, array $allBusinesses = []): array {
    $flags = [];
    $slug = ho_records_value($business, 'business_slug');
    $name = ho_records_value($business, 'business_name_current');
    $type = ho_records_value($business, 'business_type');
    $state = strtoupper(ho_records_value($business, 'location_state') ?: 'IN');
    $surfaces = ho_records_surface_summary($business);

    if ($slug === '') $flags[] = 'missing_slug';
    if ($name === '') $flags[] = 'missing_name';
    if ($type === '') $flags[] = 'missing_category';
    if (!array_filter($surfaces, static fn($v) => trim((string)$v) !== '')) $flags[] = 'missing_contact_surface';
    if ($state !== 'IN') $flags[] = 'outside_indiana';

    if ($allBusinesses) {
        foreach ($allBusinesses as $other) {
            if (!is_array($other)) continue;
            if ((int)($other['id'] ?? 0) === (int)($business['id'] ?? 0)) continue;
            $comparison = ho_records_compare_businesses($business, $other);
            if (($comparison['level'] ?? '') === 'exact' || (($comparison['score'] ?? 0) >= 78 && ($comparison['level'] ?? '') === 'likely')) {
                $flags[] = 'possible_duplicate_clues';
                break;
            }
        }
    }

    return array_values(array_unique($flags));
}

function ho_records_search(array $businesses, string $query = '', string $filter = '', int $limit = 80): array {
    $query = strtolower(trim($query));
    $filter = trim($filter);
    $results = [];

    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        $haystack = strtolower(implode(' ', [
            ho_records_value($business, 'business_name_current'),
            ho_records_value($business, 'business_slug'),
            ho_records_value($business, 'business_type'),
            ho_records_value($business, 'location_city'),
            ho_records_value($business, 'location_state'),
            ho_records_value($business, 'website_url', 'website_url'),
            ho_records_value($business, 'facebook_url', 'facebook_url'),
            ho_records_value($business, 'google_profile_url', 'google_profile_url'),
            ho_records_value($business, 'email_address', 'email_address'),
            ho_records_value($business, 'phone_number', 'phone_number'),
        ]));

        if ($query !== '' && !str_contains($haystack, $query)) continue;

        if ($filter !== '') {
            $flags = ho_records_problem_flags($business, $businesses);
            if (!in_array($filter, $flags, true)) continue;
        }

        $results[] = $business;
        if (count($results) >= $limit) break;
    }

    return $results;
}

function ho_records_find_by_id(array $businesses, int $id): ?array {
    foreach ($businesses as $business) {
        if (is_array($business) && (int)($business['id'] ?? 0) === $id) return $business;
    }
    return null;
}

function ho_records_compare_businesses(array $a, array $b): array {
    $surfacesA = ho_records_surface_summary($a);
    $surfacesB = ho_records_surface_summary($b);
    $reasons = [];
    $level = 'none';
    $score = 0;

    $slugA = ho_records_value($a, 'business_slug');
    $slugB = ho_records_value($b, 'business_slug');
    if ($slugA !== '' && $slugA === $slugB) {
        $level = 'exact'; $score = 100; $reasons[] = 'same slug';
    }

    foreach (['website', 'facebook', 'google'] as $k) {
        $va = ho_records_normalize_url((string)$surfacesA[$k]);
        $vb = ho_records_normalize_url((string)$surfacesB[$k]);
        if ($va !== '' && $va === $vb) {
            $level = 'exact'; $score = max($score, 100); $reasons[] = 'same ' . $k . ' URL';
        }
    }

    $emailA = ho_records_normalize_email((string)$surfacesA['email']);
    $emailB = ho_records_normalize_email((string)$surfacesB['email']);
    if ($emailA !== '' && $emailA === $emailB) {
        $level = 'exact'; $score = max($score, 100); $reasons[] = 'same email';
    }

    $phoneA = ho_records_normalize_phone((string)$surfacesA['phone']);
    $phoneB = ho_records_normalize_phone((string)$surfacesB['phone']);
    if ($phoneA !== '' && $phoneA === $phoneB) {
        $level = 'exact'; $score = max($score, 100); $reasons[] = 'same phone';
    }

    $nameA = ho_records_value($a, 'business_name_current');
    $nameB = ho_records_value($b, 'business_name_current');
    $nameNormA = ho_records_normalize_name($nameA);
    $nameNormB = ho_records_normalize_name($nameB);
    $cityA = strtolower(ho_records_value($a, 'location_city'));
    $cityB = strtolower(ho_records_value($b, 'location_city'));
    $stateA = strtoupper(ho_records_value($a, 'location_state') ?: 'IN');
    $stateB = strtoupper(ho_records_value($b, 'location_state') ?: 'IN');
    $sim = ho_records_similarity($nameA, $nameB);

    if ($level !== 'exact') {
        if ($nameNormA !== '' && $nameNormA === $nameNormB && $cityA !== '' && $cityA === $cityB && $stateA === $stateB) {
            $level = 'likely'; $score = max($score, 90); $reasons[] = 'same normalized name + same city/state';
        } elseif ($sim >= 92 && $cityA !== '' && $cityA === $cityB && $stateA === $stateB) {
            $level = 'likely'; $score = max($score, 80); $reasons[] = 'very similar name + same city/state';
        } elseif ($nameNormA !== '' && $nameNormA === $nameNormB && $stateA === $stateB) {
            $level = 'likely'; $score = max($score, 76); $reasons[] = 'same normalized name + same state';
        }
    }

    return [
        'level' => $level,
        'score' => $score,
        'reasons' => $reasons ?: ['No strong duplicate signal found.'],
        'name_similarity' => $sim,
    ];
}

function ho_records_repair_guidance(array $business, array $flags): array {
    $guidance = [];
    if (in_array('missing_slug', $flags, true)) $guidance[] = 'Needs a safe short slug before it can be used in computed /go preview URLs.';
    if (in_array('missing_name', $flags, true)) $guidance[] = 'Needs a public-facing business name before sourcing, prep, or outreach.';
    if (in_array('missing_category', $flags, true)) $guidance[] = 'Needs a category context. Category is sourcing context, not a hard rejection.';
    if (in_array('missing_contact_surface', $flags, true)) $guidance[] = 'Needs at least one public surface: website, Facebook, Google profile, public email, phone, or source URL.';
    if (in_array('outside_indiana', $flags, true)) $guidance[] = 'Review Indiana relevance. Indiana is the broad location gate.';
    if (in_array('possible_duplicate_clues', $flags, true)) $guidance[] = 'Compare against likely duplicate before adding more prep/outreach data.';
    if (!$guidance) $guidance[] = 'No obvious repair issue found. Use Inspect for detailed review.';
    return $guidance;
}
?>