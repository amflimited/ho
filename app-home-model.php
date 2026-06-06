<?php
/**
 * Hoosier Online App Home Model
 * v138 — Source → Intake → Records → Prep → Send
 *
 * Read-only app home counts/status.
 */

declare(strict_types=1);

const HO_APP_HOME_MODEL_VERSION = 'HO-APP-HOME-138';

function ho_app_home_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_app_home_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_app_home_value(array $business, string $key, string $claimFallback = ''): string {
    $value = trim((string)($business[$key] ?? ''));
    if ($value !== '') return $value;
    if ($claimFallback !== '') return ho_app_home_claim_value($business, $claimFallback);
    return '';
}

function ho_app_home_json_claim(array $business, string $fieldKey): array {
    $raw = ho_app_home_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_app_home_has_surface(array $business): bool {
    $keys = [
        ['website_url','website_url'],
        ['facebook_url','facebook_url'],
        ['google_profile_url','google_profile_url'],
        ['email_address','email_address'],
        ['phone_number','phone_number'],
    ];
    foreach ($keys as $pair) {
        if (ho_app_home_value($business, $pair[0], $pair[1]) !== '') return true;
    }
    return false;
}

function ho_app_home_has_complete_salesprep(array $business): bool {
    return count(ho_app_home_json_claim($business, 'strength_keys_json')) > 0
        && count(ho_app_home_json_claim($business, 'weakness_keys_json')) > 0
        && count(ho_app_home_json_claim($business, 'recommendation_keys_json')) > 0
        && count(ho_app_home_json_claim($business, 'preview_direction_keys_json')) >= 3
        && ho_app_home_claim_value($business, 'primary_offer_path') !== ''
        && ho_app_home_claim_value($business, 'outreach_subject') !== ''
        && ho_app_home_claim_value($business, 'outreach_body') !== '';
}

function ho_app_home_is_contact_ready(array $business): bool {
    $readiness = strtolower(ho_app_home_value($business, 'contact_readiness', 'contact_readiness'));
    $queue = strtolower(ho_app_home_claim_value($business, 'queue_key'));
    $clearance = strtolower(ho_app_home_claim_value($business, 'marketing_clearance_status'));
    if (in_array($readiness, ['contact_ready','ready','ready_to_contact'], true)) return true;
    if (in_array($queue, ['contact_ready','proceed_no_website','ready_for_setup'], true)) return true;
    if (in_array($clearance, ['cleared','contact_ready'], true)) return true;
    return ho_app_home_value($business, 'business_slug') !== ''
        && ho_app_home_value($business, 'business_name_current') !== ''
        && ho_app_home_has_surface($business);
}

function ho_app_home_has_outreach_draft(array $business): bool {
    $to = ho_app_home_claim_value($business, 'outreach_to');
    if ($to === '') {
        $to = ho_app_home_value($business, 'email_address', 'email_address')
            ?: ho_app_home_value($business, 'facebook_url', 'facebook_url')
            ?: ho_app_home_value($business, 'website_url', 'website_url');
    }
    return $to !== ''
        && ho_app_home_claim_value($business, 'outreach_subject') !== ''
        && ho_app_home_claim_value($business, 'outreach_body') !== '';
}

function ho_app_home_problem_count(array $businesses): int {
    $count = 0;
    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        $state = strtoupper(ho_app_home_value($business, 'location_state') ?: 'IN');
        $hasProblem = ho_app_home_value($business, 'business_slug') === ''
            || ho_app_home_value($business, 'business_name_current') === ''
            || ho_app_home_value($business, 'business_type') === ''
            || !ho_app_home_has_surface($business)
            || $state !== 'IN';
        if ($hasProblem) $count++;
    }
    return $count;
}

function ho_app_home_counts(array $businesses): array {
    $total = count($businesses);
    $prepReady = 0;
    $sendReady = 0;
    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        if (ho_app_home_is_contact_ready($business) && !ho_app_home_has_complete_salesprep($business)) $prepReady++;
        if (ho_app_home_has_outreach_draft($business)) $sendReady++;
    }

    return [
        'source' => $total . ' known',
        'intake' => 'preview only',
        'records' => ho_app_home_problem_count($businesses) . ' repair flags',
        'prep' => $prepReady . ' ready',
        'send' => $sendReady . ' drafts',
    ];
}

function ho_app_home_modules(array $counts): array {
    return [
        [
            'key' => 'source',
            'name' => 'Source',
            'purpose' => 'Create lead-generation prompts with known-business exclusions.',
            'status' => $counts['source'] ?? '',
            'route' => '/sales-source.php',
            'verb' => 'Find candidates',
        ],
        [
            'key' => 'intake',
            'name' => 'Intake',
            'purpose' => 'Preview candidate-to-record conversion before import.',
            'status' => $counts['intake'] ?? '',
            'route' => '/sales-intake.php',
            'verb' => 'Preview candidates',
        ],
        [
            'key' => 'records',
            'name' => 'Records',
            'purpose' => 'Repair stored businesses and compare duplicates.',
            'status' => $counts['records'] ?? '',
            'route' => '/sales-records.php',
            'verb' => 'Repair records',
        ],
        [
            'key' => 'prep',
            'name' => 'Prep',
            'purpose' => 'Generate diagnosis keys and outreach drafts in one GPT batch.',
            'status' => $counts['prep'] ?? '',
            'route' => '/sales-prep.php',
            'verb' => 'Prepare outreach',
        ],
        [
            'key' => 'send',
            'name' => 'Send',
            'purpose' => 'Review drafts, copy outreach, and send manually.',
            'status' => $counts['send'] ?? '',
            'route' => '/sales-send.php',
            'verb' => 'Open send tray',
        ],
    ];
}

function ho_app_home_support_links(): array {
    return [
        ['Migration Plan', '/sales-migration-plan.php', 'How the old workbenches map to the app.'],
        ['App Contract', '/sales-app-contract-complete.php', 'The binding Source → Intake → Records → Prep → Send contract.'],
        ['Market Map', '/sales-market-map.php', 'Category and area coverage support.'],
        ['System Check', '/sales-system-check.php', 'Deployment and dependency health.'],
        ['State Audit', '/sales-command-center-audit.php', 'Read-only state proof and debugging.'],
    ];
}

function ho_app_home_legacy_links(): array {
    return [
        ['Diagnosis Workbench', '/sales-diagnosis-workbench.php', 'Legacy: use Prep for the app path.'],
        ['Front Door Builder', '/sales-front-door-builder.php', 'Legacy: /go is now computed from slug.'],
        ['Preview Package Workbench', '/sales-preview-package-workbench.php', 'Legacy/experimental package path.'],
        ['Package System', '/sales-preview-package-workbench.php', 'Legacy package/domain/materialization path.'],
    ];
}
?>