<?php
/**
 * Hoosier Online Intake Model
 * v134 — Candidate Lead Preview
 *
 * Preview-only. No durable import/write.
 */

declare(strict_types=1);

const HO_INTAKE_MODEL_VERSION = 'HO-INTAKE-PREVIEW-134';

function ho_intake_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_intake_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_intake_business_value(array $business, string $key, string $claimFallback = ''): string {
    $value = trim((string)($business[$key] ?? ''));
    if ($value !== '') return $value;
    if ($claimFallback !== '') return ho_intake_claim_value($business, $claimFallback);
    return '';
}

function ho_intake_clean_pasted_json(string $raw): string {
    $raw = trim($raw);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $map = [
        "\xE2\x80\x9C" => '"', "\xE2\x80\x9D" => '"',
        "\xE2\x80\x98" => "'", "\xE2\x80\x99" => "'",
        "\xE2\x80\x93" => "-", "\xE2\x80\x94" => "-",
        "\xC2\xA0" => " ",
    ];
    $raw = strtr($raw, $map);
    $raw = preg_replace('/^```(?:json|javascript|js)?\s*/i', '', $raw) ?? $raw;
    $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;

    $firstObj = strpos($raw, '{');
    $firstArr = strpos($raw, '[');
    $starts = array_filter([$firstObj, $firstArr], static fn($v) => $v !== false);
    if ($starts) {
        $start = min($starts);
        $lastObj = strrpos($raw, '}');
        $lastArr = strrpos($raw, ']');
        $ends = array_filter([$lastObj, $lastArr], static fn($v) => $v !== false);
        if ($ends) {
            $end = max($ends);
            if ($end > $start) $raw = substr($raw, $start, $end - $start + 1);
        }
    }
    return trim($raw);
}

function ho_intake_slugify(string $name, string $city = ''): string {
    $base = trim($name . ' ' . $city);
    $base = strtolower($base);
    $base = preg_replace('/&/', ' and ', $base) ?? $base;
    $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? $base;
    $base = trim($base, '-');
    $base = preg_replace('/-+/', '-', $base) ?? $base;
    return $base !== '' ? substr($base, 0, 80) : 'candidate-business';
}

function ho_intake_normalize_name(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/\b(llc|l\.l\.c\.|inc|co|company|services|service|the)\b/i', '', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function ho_intake_normalize_url(string $value): string {
    $value = strtolower(trim($value));
    if ($value === '') return '';
    $value = preg_replace('#^https?://#', '', $value) ?? $value;
    $value = preg_replace('#^www\.#', '', $value) ?? $value;
    $value = rtrim($value, "/ \t\n\r\0\x0B");
    return $value;
}

function ho_intake_normalize_phone(string $value): string {
    return preg_replace('/\D+/', '', $value) ?? '';
}

function ho_intake_normalize_email(string $value): string {
    return strtolower(trim($value));
}

function ho_intake_string_similarity(string $a, string $b): int {
    $a = ho_intake_normalize_name($a);
    $b = ho_intake_normalize_name($b);
    if ($a === '' || $b === '') return 0;
    similar_text($a, $b, $pct);
    return (int)round($pct);
}

function ho_intake_existing_index(array $businesses): array {
    $rows = [];
    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        $name = ho_intake_business_value($business, 'business_name_current');
        $city = ho_intake_business_value($business, 'location_city');
        $state = ho_intake_business_value($business, 'location_state') ?: 'IN';
        $rows[] = [
            'business_id' => (int)($business['id'] ?? 0),
            'business_slug' => ho_intake_business_value($business, 'business_slug'),
            'business_name' => $name,
            'name_norm' => ho_intake_normalize_name($name),
            'city' => $city,
            'city_norm' => strtolower(trim($city)),
            'state' => strtoupper(trim($state)),
            'website_url' => ho_intake_normalize_url(ho_intake_business_value($business, 'website_url', 'website_url')),
            'facebook_url' => ho_intake_normalize_url(ho_intake_business_value($business, 'facebook_url', 'facebook_url')),
            'google_profile_url' => ho_intake_normalize_url(ho_intake_business_value($business, 'google_profile_url', 'google_profile_url')),
            'email_address' => ho_intake_normalize_email(ho_intake_business_value($business, 'email_address', 'email_address')),
            'phone_number' => ho_intake_normalize_phone(ho_intake_business_value($business, 'phone_number', 'phone_number')),
        ];
    }
    return $rows;
}

function ho_intake_candidate_to_business(array $candidate): array {
    $name = trim((string)($candidate['raw_business_name'] ?? $candidate['business_name'] ?? ''));
    $city = trim((string)($candidate['city'] ?? ''));
    $state = strtoupper(trim((string)($candidate['state'] ?? 'IN')));
    if ($state === '') $state = 'IN';

    $category = trim((string)($candidate['likely_category'] ?? 'local_service'));
    $category = strtolower(preg_replace('/[^a-z0-9]+/', '_', $category) ?? $category);
    $category = trim($category, '_');
    if ($category === '') $category = 'local_service';

    return [
        'business_name_current' => $name,
        'business_slug' => ho_intake_slugify($name, $city),
        'business_type' => $category,
        'location_city' => $city,
        'location_state' => $state,
        'website_url' => trim((string)($candidate['website_url'] ?? '')),
        'facebook_url' => trim((string)($candidate['facebook_url'] ?? '')),
        'google_profile_url' => trim((string)($candidate['google_profile_url'] ?? '')),
        'email_address' => trim((string)($candidate['public_email'] ?? $candidate['email_address'] ?? '')),
        'phone_number' => trim((string)($candidate['public_phone'] ?? $candidate['phone_number'] ?? '')),
        'source_context' => trim((string)($candidate['source_url'] ?? '')),
    ];
}

function ho_intake_dedupe_candidate(array $candidate, array $proposed, array $existingIndex): array {
    $candidateName = (string)$proposed['business_name_current'];
    $nameNorm = ho_intake_normalize_name($candidateName);
    $cityNorm = strtolower(trim((string)$proposed['location_city']));
    $state = strtoupper(trim((string)$proposed['location_state']));
    $slug = (string)$proposed['business_slug'];
    $website = ho_intake_normalize_url((string)$proposed['website_url']);
    $facebook = ho_intake_normalize_url((string)$proposed['facebook_url']);
    $google = ho_intake_normalize_url((string)$proposed['google_profile_url']);
    $email = ho_intake_normalize_email((string)$proposed['email_address']);
    $phone = ho_intake_normalize_phone((string)$proposed['phone_number']);

    $best = [
        'level' => 'none',
        'score' => 0,
        'matched_business_id' => 0,
        'matched_business_name' => '',
        'reasons' => [],
    ];

    foreach ($existingIndex as $existing) {
        $reasons = [];
        $level = 'none';
        $score = 0;

        if ($slug !== '' && $slug === $existing['business_slug']) {
            $level = 'exact';
            $score = 100;
            $reasons[] = 'same existing slug';
        }
        if ($website !== '' && $website === $existing['website_url']) {
            $level = 'exact';
            $score = max($score, 100);
            $reasons[] = 'same normalized website';
        }
        if ($facebook !== '' && $facebook === $existing['facebook_url']) {
            $level = 'exact';
            $score = max($score, 100);
            $reasons[] = 'same Facebook URL';
        }
        if ($google !== '' && $google === $existing['google_profile_url']) {
            $level = 'exact';
            $score = max($score, 100);
            $reasons[] = 'same Google profile URL';
        }
        if ($email !== '' && $email === $existing['email_address']) {
            $level = 'exact';
            $score = max($score, 100);
            $reasons[] = 'same email';
        }
        if ($phone !== '' && $phone === $existing['phone_number']) {
            $level = 'exact';
            $score = max($score, 100);
            $reasons[] = 'same phone';
        }

        if ($level !== 'exact') {
            $similarity = ho_intake_string_similarity($candidateName, (string)$existing['business_name']);
            $sameCity = $cityNorm !== '' && $cityNorm === $existing['city_norm'];
            $sameState = $state !== '' && $state === $existing['state'];

            if ($nameNorm !== '' && $nameNorm === $existing['name_norm'] && $sameCity && $sameState) {
                $level = 'likely';
                $score = max($score, 90);
                $reasons[] = 'same normalized name + same city/state';
            } elseif ($similarity >= 86 && ($phone !== '' && $phone === $existing['phone_number'])) {
                $level = 'likely';
                $score = max($score, 88);
                $reasons[] = 'similar name + same phone';
            } elseif ($nameNorm !== '' && $nameNorm === $existing['name_norm'] && $sameState) {
                $level = 'likely';
                $score = max($score, 78);
                $reasons[] = 'same business name + same state/nearby city review needed';
            } elseif ($similarity >= 92 && $sameCity && $sameState) {
                $level = 'likely';
                $score = max($score, 76);
                $reasons[] = 'very similar name + same city/state';
            }
        }

        if ($score > $best['score']) {
            $best = [
                'level' => $level,
                'score' => $score,
                'matched_business_id' => (int)$existing['business_id'],
                'matched_business_name' => (string)$existing['business_name'],
                'reasons' => $reasons,
            ];
        }
    }

    return $best;
}

function ho_intake_decide(array $candidate, array $proposed, array $dedupe): array {
    $reasons = [];
    $state = strtoupper(trim((string)$proposed['location_state']));

    if (trim((string)$proposed['business_name_current']) === '') {
        return ['group' => 'reject', 'reason' => 'Missing business name.'];
    }
    if ($state !== 'IN') {
        return ['group' => 'reject', 'reason' => 'Outside Indiana broad location gate.'];
    }

    $recommendation = strtolower(trim((string)($candidate['intake_status_recommendation'] ?? '')));
    if ($recommendation === 'reject') {
        return ['group' => 'reject', 'reason' => 'Source recommended reject.'];
    }

    if ($dedupe['level'] === 'exact') {
        return ['group' => 'update_existing', 'reason' => 'Exact duplicate/update match: ' . implode(', ', $dedupe['reasons'])];
    }
    if ($dedupe['level'] === 'likely' || $recommendation === 'possible_duplicate') {
        return ['group' => 'possible_duplicate', 'reason' => 'Possible duplicate: ' . implode(', ', $dedupe['reasons'])];
    }

    if ($recommendation === 'needs_review') {
        return ['group' => 'needs_review', 'reason' => 'Source recommended needs_review.'];
    }

    $hasSurface = trim((string)$proposed['website_url']) !== ''
        || trim((string)$proposed['facebook_url']) !== ''
        || trim((string)$proposed['google_profile_url']) !== ''
        || trim((string)$proposed['email_address']) !== ''
        || trim((string)$proposed['phone_number']) !== ''
        || trim((string)$proposed['source_context']) !== '';

    if (!$hasSurface) {
        return ['group' => 'needs_review', 'reason' => 'No public source/contact surface provided.'];
    }

    return ['group' => 'new_business', 'reason' => 'No exact or likely duplicate found; Indiana candidate with usable public source/contact clue.'];
}

function ho_intake_preview(array $decoded, array $existingBusinesses): array {
    $batchType = strtolower(trim((string)($decoded['candidate_batch']['batch_type'] ?? $decoded['batch_type'] ?? '')));
    if ($batchType !== 'source_candidates') {
        throw new RuntimeException('Expected candidate_batch.batch_type = source_candidates.');
    }
    if (!isset($decoded['candidates']) || !is_array($decoded['candidates'])) {
        throw new RuntimeException('Missing candidates[].');
    }

    $existingIndex = ho_intake_existing_index($existingBusinesses);
    $groups = [
        'new_business' => [],
        'update_existing' => [],
        'possible_duplicate' => [],
        'needs_review' => [],
        'reject' => [],
    ];

    foreach ($decoded['candidates'] as $idx => $candidate) {
        if (!is_array($candidate)) continue;
        $proposed = ho_intake_candidate_to_business($candidate);
        $dedupe = ho_intake_dedupe_candidate($candidate, $proposed, $existingIndex);
        $decision = ho_intake_decide($candidate, $proposed, $dedupe);
        $row = [
            'candidate_index' => $idx,
            'decision' => $decision['group'],
            'decision_reason' => $decision['reason'],
            'matched_business_id' => $dedupe['matched_business_id'],
            'matched_business_name' => $dedupe['matched_business_name'],
            'dedupe_score' => $dedupe['score'],
            'dedupe_level' => $dedupe['level'],
            'dedupe_reasons' => $dedupe['reasons'],
            'proposed_business' => $proposed,
            'source_candidate' => $candidate,
        ];
        $groups[$decision['group']][] = $row;
    }

    return [
        'batch_type' => 'intake_preview',
        'source_batch' => $decoded['candidate_batch'] ?? [],
        'groups' => $groups,
        'counts' => array_map('count', $groups),
        'durable_import_performed' => false,
    ];
}
?>