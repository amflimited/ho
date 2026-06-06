<?php
/**
 * Hoosier Online Production Lane Model
 * v140 — Current unfinished job home
 *
 * Read-only current-job selector. No operational writes.
 */

declare(strict_types=1);

const HO_PRODUCTION_LANE_MODEL_VERSION = 'HO-PRODUCTION-LANE-HOME-140';

function ho_lane_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_lane_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_lane_value(array $business, string $key, string $claimFallback = ''): string {
    $value = trim((string)($business[$key] ?? ''));
    if ($value !== '') return $value;
    if ($claimFallback !== '') return ho_lane_claim_value($business, $claimFallback);
    return '';
}

function ho_lane_json_claim(array $business, string $fieldKey): array {
    $raw = ho_lane_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_lane_has_public_surface(array $business): bool {
    $keys = [
        ['website_url','website_url'],
        ['facebook_url','facebook_url'],
        ['google_profile_url','google_profile_url'],
        ['email_address','email_address'],
        ['phone_number','phone_number'],
    ];
    foreach ($keys as $pair) {
        if (ho_lane_value($business, $pair[0], $pair[1]) !== '') return true;
    }
    return false;
}

function ho_lane_is_contact_ready(array $business): bool {
    $readiness = strtolower(ho_lane_value($business, 'contact_readiness', 'contact_readiness'));
    $queue = strtolower(ho_lane_claim_value($business, 'queue_key'));
    $clearance = strtolower(ho_lane_claim_value($business, 'marketing_clearance_status'));
    $blocked = strtolower(ho_lane_claim_value($business, 'blocked_reason'));
    $skip = strtolower(ho_lane_claim_value($business, 'skip_reason'));

    if ($blocked !== '' || $skip !== '') return false;
    if (in_array($readiness, ['contact_ready','ready','ready_to_contact'], true)) return true;
    if (in_array($queue, ['contact_ready','proceed_no_website','ready_for_setup'], true)) return true;
    if (in_array($clearance, ['cleared','contact_ready'], true)) return true;

    return ho_lane_value($business, 'business_slug') !== ''
        && ho_lane_value($business, 'business_name_current') !== ''
        && ho_lane_has_public_surface($business);
}

function ho_lane_has_complete_salesprep(array $business): bool {
    return count(ho_lane_json_claim($business, 'strength_keys_json')) > 0
        && count(ho_lane_json_claim($business, 'weakness_keys_json')) > 0
        && count(ho_lane_json_claim($business, 'recommendation_keys_json')) > 0
        && count(ho_lane_json_claim($business, 'preview_direction_keys_json')) >= 3
        && ho_lane_claim_value($business, 'primary_offer_path') !== ''
        && ho_lane_claim_value($business, 'outreach_subject') !== ''
        && ho_lane_claim_value($business, 'outreach_body') !== '';
}

function ho_lane_has_send_ready_draft(array $business): bool {
    $to = ho_lane_claim_value($business, 'outreach_to');
    if ($to === '') {
        $to = ho_lane_value($business, 'email_address', 'email_address')
            ?: ho_lane_value($business, 'facebook_url', 'facebook_url')
            ?: ho_lane_value($business, 'website_url', 'website_url');
    }
    return $to !== ''
        && ho_lane_claim_value($business, 'outreach_subject') !== ''
        && ho_lane_claim_value($business, 'outreach_body') !== '';
}

function ho_lane_problem_flags(array $business): array {
    $flags = [];
    $state = strtoupper(ho_lane_value($business, 'location_state') ?: 'IN');
    if (ho_lane_value($business, 'business_slug') === '') $flags[] = 'missing_slug';
    if (ho_lane_value($business, 'business_name_current') === '') $flags[] = 'missing_name';
    if (ho_lane_value($business, 'business_type') === '') $flags[] = 'missing_category';
    if (!ho_lane_has_public_surface($business)) $flags[] = 'missing_contact_surface';
    if ($state !== 'IN') $flags[] = 'outside_indiana';
    return $flags;
}

function ho_lane_counts(array $businesses): array {
    $sendReady = 0;
    $prepReady = 0;
    $problemRecords = 0;

    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        if (ho_lane_has_send_ready_draft($business)) $sendReady++;
        if (ho_lane_is_contact_ready($business) && !ho_lane_has_complete_salesprep($business)) $prepReady++;
        if (ho_lane_problem_flags($business)) $problemRecords++;
    }

    return [
        'known_records' => count($businesses),
        'send_ready' => $sendReady,
        'prep_ready' => $prepReady,
        'intake_waiting' => 0,
        'problem_records' => $problemRecords,
    ];
}

function ho_lane_current_job(array $counts): array {
    if (($counts['send_ready'] ?? 0) > 0) {
        return [
            'job_key' => 'send_ready_drafts',
            'headline' => 'Drafts are ready to send.',
            'why' => 'Prepared outreach is closest to revenue. Review the drafts, copy them, and send manually.',
            'route' => '/sales-send.php',
            'button' => 'Start sending',
            'proof' => (string)$counts['send_ready'] . ' draft' . (($counts['send_ready'] ?? 0) === 1 ? '' : 's') . ' ready',
        ];
    }

    if (($counts['prep_ready'] ?? 0) > 0) {
        return [
            'job_key' => 'prep_contact_ready',
            'headline' => 'Businesses are ready to prep.',
            'why' => 'These records are contactable but still need diagnosis keys and outreach drafts.',
            'route' => '/sales-prep.php',
            'button' => 'Prepare outreach',
            'proof' => (string)$counts['prep_ready'] . ' business' . (($counts['prep_ready'] ?? 0) === 1 ? '' : 'es') . ' ready for prep',
        ];
    }

    if (($counts['intake_waiting'] ?? 0) > 0) {
        return [
            'job_key' => 'review_intake_candidates',
            'headline' => 'Candidate leads need intake.',
            'why' => 'Raw candidates need mapping and duplicate review before they become records.',
            'route' => '/sales-intake.php',
            'button' => 'Review candidates',
            'proof' => (string)$counts['intake_waiting'] . ' candidate batch waiting',
        ];
    }

    if (($counts['problem_records'] ?? 0) > 0) {
        return [
            'job_key' => 'repair_blocked_records',
            'headline' => 'Some records need repair.',
            'why' => 'Fix missing identity, contact surfaces, or Indiana relevance before those records move forward.',
            'route' => '/sales-records.php?filter=missing_contact_surface',
            'button' => 'Repair records',
            'proof' => (string)$counts['problem_records'] . ' record' . (($counts['problem_records'] ?? 0) === 1 ? '' : 's') . ' with repair flags',
        ];
    }

    return [
        'job_key' => 'source_more_businesses',
        'headline' => 'Find more businesses.',
        'why' => 'No higher-priority production work is waiting, so the next job is sourcing more candidates.',
        'route' => '/sales-source.php',
        'button' => 'Find businesses',
        'proof' => (string)($counts['known_records'] ?? 0) . ' known records in the app',
    ];
}

function ho_lane_tools(): array {
    return [
        ['Source', '/sales-source.php', 'Lead-generation prompt builder'],
        ['Intake', '/sales-intake.php', 'Candidate preview and dedupe'],
        ['Records', '/sales-records.php', 'Record repair bay'],
        ['Prep', '/sales-prep.php', 'Diagnosis + draft prompt'],
        ['Send', '/sales-send.php', 'Manual send tray'],
        ['Map', '/sales-market-map.php', 'Market coverage'],
        ['Check', '/sales-system-check.php', 'System health'],
        ['Audit', '/sales-command-center-audit.php', 'State proof'],
        ['Contracts', '/sales-app-contract-complete.php', 'App contract'],
        ['Lane Check', '/sales-production-lane-check.php', 'Why the current job was selected'],
    ];
}

function ho_lane_legacy_tools(): array {
    return [
        ['Diagnosis Workbench', '/sales-diagnosis-workbench.php', 'Legacy: use Prep for main path'],
        ['Front Door Builder', '/sales-front-door-builder.php', 'Legacy: /go is computed now'],
        ['Preview Package Workbench', '/sales-preview-package-workbench.php', 'Legacy package path'],
        ['Package System', '/sales-preview-package-workbench.php', 'Legacy domain/materialization path'],
    ];
}

function ho_lane_sample_records_for_job(array $businesses, string $jobKey, int $limit = 8): array {
    $samples = [];
    foreach ($businesses as $business) {
        if (!is_array($business)) continue;

        $include = false;
        if ($jobKey === 'send_ready_drafts') {
            $include = ho_lane_has_send_ready_draft($business);
        } elseif ($jobKey === 'prep_contact_ready') {
            $include = ho_lane_is_contact_ready($business) && !ho_lane_has_complete_salesprep($business);
        } elseif ($jobKey === 'repair_blocked_records') {
            $include = (bool)ho_lane_problem_flags($business);
        } elseif ($jobKey === 'source_more_businesses') {
            $include = true;
        }

        if (!$include) continue;

        $samples[] = [
            'business_id' => (int)($business['id'] ?? 0),
            'business_name' => ho_lane_value($business, 'business_name_current') ?: '(missing name)',
            'business_slug' => ho_lane_value($business, 'business_slug') ?: '(missing slug)',
            'category' => ho_lane_value($business, 'business_type') ?: '(missing category)',
            'city_state' => trim(ho_lane_value($business, 'location_city') . ', ' . (ho_lane_value($business, 'location_state') ?: 'IN'), ', '),
            'flags' => ho_lane_problem_flags($business),
            'inspect_url' => '/sales-business.php?id=' . (int)($business['id'] ?? 0),
        ];

        if (count($samples) >= $limit) break;
    }
    return $samples;
}

function ho_lane_priority_explanation(array $counts, string $selectedJobKey): array {
    $priority = [
        ['job_key' => 'send_ready_drafts', 'count_key' => 'send_ready', 'label' => 'Send ready drafts'],
        ['job_key' => 'prep_contact_ready', 'count_key' => 'prep_ready', 'label' => 'Prep contact-ready businesses'],
        ['job_key' => 'review_intake_candidates', 'count_key' => 'intake_waiting', 'label' => 'Intake waiting candidates'],
        ['job_key' => 'repair_blocked_records', 'count_key' => 'problem_records', 'label' => 'Repair blocked/problem records'],
        ['job_key' => 'source_more_businesses', 'count_key' => 'known_records', 'label' => 'Source more businesses'],
    ];
    $rows = [];
    foreach ($priority as $row) {
        $count = (int)($counts[$row['count_key']] ?? 0);
        $selected = $row['job_key'] === $selectedJobKey;
        if ($selected) {
            $reason = 'Selected here by priority.';
        } elseif ($row['job_key'] === 'source_more_businesses') {
            $reason = $selectedJobKey === 'source_more_businesses'
                ? 'Fallback selected because no higher-priority work was found.'
                : 'Skipped because a higher-priority job was selected first.';
        } elseif ($count > 0) {
            $reason = 'Has work, but a higher-priority job was selected first.';
        } else {
            $reason = 'Skipped because count is zero.';
        }
        $rows[] = [
            'job_key' => $row['job_key'],
            'label' => $row['label'],
            'count_key' => $row['count_key'],
            'count' => $count,
            'selected' => $selected,
            'reason' => $reason,
        ];
    }
    return $rows;
}

?>