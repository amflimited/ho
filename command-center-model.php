<?php
/**
 * Hoosier Online Command Center Model
 * v124 — read-only state audit helpers
 *
 * No writes. No imports. No outreach. No payment.
 */

declare(strict_types=1);

const HO_COMMAND_CENTER_VERSION = 'HO-COMMAND-CENTER-AUDIT-124';

function ho_command_safe_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_command_json_claim(array $business, string $fieldKey): array {
    $raw = ho_command_safe_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function ho_command_has_contact_path(array $business): bool {
    $method = strtolower(ho_command_safe_claim_value($business, 'best_contact_method'));
    $email = ho_command_safe_claim_value($business, 'email_address');
    $phone = ho_command_safe_claim_value($business, 'phone_number');
    $contactForm = strtolower(ho_command_safe_claim_value($business, 'contact_form_present'));
    $requestForm = strtolower(ho_command_safe_claim_value($business, 'request_form_present'));
    $website = trim((string)($business['website_url'] ?? ''));

    if ($email !== '' || $phone !== '') return true;
    if ($method !== '' && !in_array($method, ['none','missing','unknown','manual_check','needs_manual_check'], true)) return true;
    if (in_array($contactForm, ['yes','true','present','confirmed','likely'], true)) return true;
    if (in_array($requestForm, ['yes','true','present','confirmed','likely'], true)) return true;
    if ($website !== '' && str_contains($method, 'form')) return true;

    return false;
}

function ho_command_queue_key(array $business): string {
    // Prefer the dashboard's queue key if loaded, but do not require it.
    if (function_exists('ho_salesportal_ui_queue_key')) {
        try {
            return (string)ho_salesportal_ui_queue_key($business);
        } catch (Throwable $e) {
            // Fall through to local read-only approximation.
        }
    }

    $status = strtolower((string)($business['marketing_clearance_status'] ?? ho_command_safe_claim_value($business, 'marketing_clearance_status')));
    $scoreRaw = (string)($business['marketing_clearance_score'] ?? ho_command_safe_claim_value($business, 'marketing_clearance_score'));
    $score = is_numeric($scoreRaw) ? (int)$scoreRaw : 0;

    $triageStatus = strtolower(ho_command_safe_claim_value($business, 'triage_result_status'));
    $triageNext = strtolower(ho_command_safe_claim_value($business, 'triage_next_step'));
    $angle = strtolower(ho_command_safe_claim_value($business, 'primary_sales_angle'));
    $features = strtolower(ho_command_safe_claim_value($business, 'recommended_features'));
    $contactReadiness = strtolower(ho_command_safe_claim_value($business, 'contact_readiness'));
    $setupPath = strtolower(ho_command_safe_claim_value($business, 'setup_path'));
    $website = trim((string)($business['website_url'] ?? ''));

    if (in_array($status, ['skip','blocked'], true)
        || in_array($triageStatus, ['do_not_proceed','bad_fit','exclude','duplicate_or_confused'], true)
        || str_contains($angle, 'do_not')
        || str_contains($features, 'do_not')
        || str_contains($features, 'duplicate')) {
        return 'blocked';
    }

    if (str_contains($contactReadiness, 'manual_check') || str_contains($features, 'manual_check') || str_contains($setupPath, 'manual_check')) {
        return 'manual_review';
    }

    if ($status === 'cleared'
        || str_contains($contactReadiness, 'ready')
        || str_contains($features, 'ready_to_contact')
        || str_contains($angle, 'ready_to_contact')) {
        return 'contact_ready';
    }

    if (str_contains($setupPath, 'proceed_no_website') || str_contains($features, 'proceed_no_website') || str_contains($angle, 'proceed_no_website')) {
        return 'proceed_no_website';
    }

    if (str_contains($setupPath, 'ready') || str_contains($features, 'setup_path=') || ($status === 'hold' && $score >= 45)) {
        return 'ready_setup';
    }

    if ($website !== '' || $status === 'warm_clear' || str_contains($triageNext, 'research') || str_contains($features, 'research')) {
        return 'need_research';
    }

    if (str_contains($triageNext, 'triage') || $triageStatus === '' || $status === '') {
        return 'need_triage';
    }

    return 'blocked';
}

function ho_command_has_diagnosis_keys(array $business): bool {
    if (ho_command_safe_claim_value($business, 'diagnosis_status') !== '') return true;
    foreach (['strength_keys_json','weakness_keys_json','recommendation_keys_json','preview_direction_keys_json'] as $field) {
        if (ho_command_safe_claim_value($business, $field) !== '') return true;
    }
    return false;
}

function ho_command_is_diagnosis_ready(array $business): bool {
    $status = strtolower(ho_command_safe_claim_value($business, 'diagnosis_status'));
    if (in_array($status, ['diagnosis_ready','preview_ready','go_ready'], true)) return true;

    $strengths = ho_command_json_claim($business, 'strength_keys_json');
    $weaknesses = ho_command_json_claim($business, 'weakness_keys_json');
    $recommendations = ho_command_json_claim($business, 'recommendation_keys_json');
    $directions = ho_command_json_claim($business, 'preview_direction_keys_json');

    return count($strengths) > 0 && count($weaknesses) > 0 && count($recommendations) > 0 && count($directions) >= 3;
}

function ho_command_has_go_page(array $business): bool {
    $goPath = ho_command_safe_claim_value($business, 'go_path');
    $goSlug = ho_command_safe_claim_value($business, 'go_slug');
    $assetUrl = ho_command_safe_claim_value($business, 'outreach_asset_url');
    $status = strtolower(ho_command_safe_claim_value($business, 'front_door_preview_status'));

    if ($goPath !== '' || $goSlug !== '' || $assetUrl !== '') return true;
    return in_array($status, ['go_ready','go_page_ready','published','live'], true);
}

function ho_command_is_preview_ready(array $business): bool {
    $status = strtolower(ho_command_safe_claim_value($business, 'front_door_preview_status'));
    return in_array($status, ['preview_ready','go_ready','go_page_ready','published','live'], true) || ho_command_is_diagnosis_ready($business);
}

function ho_command_is_draft_ready(array $business): bool {
    $status = strtolower(ho_command_safe_claim_value($business, 'marketing_desk_status'));
    $subject = ho_command_safe_claim_value($business, 'outreach_subject');
    $body = ho_command_safe_claim_value($business, 'outreach_body');
    return in_array($status, ['draft_ready','ready_to_send','manual_ready_to_send'], true) || ($subject !== '' && $body !== '');
}

function ho_command_is_placeholder_identity(array $business): bool {
    $id = (int)($business['id'] ?? 0);
    $slug = strtolower(trim((string)($business['business_slug'] ?? '')));
    $name = strtolower(trim((string)($business['business_name_current'] ?? '')));
    $shortSlug = strtolower(ho_command_safe_claim_value($business, 'short_slug'));
    $bad = ['', '0', 'dummy', 'example', 'existing-business-slug', 'business-name', 'shortslug', 'missing-slug'];
    return ($id <= 0 && in_array($slug, $bad, true))
        || in_array($slug, ['dummy','existing-business-slug'], true)
        || in_array($shortSlug, ['dummy','shortslug','missing-slug'], true)
        || in_array($name, ['business name','dummy'], true);
}

function ho_command_status_clues(array $business, ?array $state = null): array {
    $state = $state ?? ho_command_evaluate_business($business);
    return [
        'queue_key' => $state['queue_key'],
        'contact_readiness' => ho_command_safe_claim_value($business, 'contact_readiness'),
        'diagnosis_status' => ho_command_safe_claim_value($business, 'diagnosis_status'),
        'front_door_preview_status' => ho_command_safe_claim_value($business, 'front_door_preview_status'),
        'go_path' => ho_command_safe_claim_value($business, 'go_path'),
        'go_slug' => ho_command_safe_claim_value($business, 'go_slug'),
        'marketing_desk_status' => ho_command_safe_claim_value($business, 'marketing_desk_status'),
        'package_status' => ho_command_safe_claim_value($business, 'package_status'),
        'marketing_clearance_status' => (string)($business['marketing_clearance_status'] ?? ''),
        'marketing_clearance_score' => (string)($business['marketing_clearance_score'] ?? ''),
    ];
}



function ho_command_front_door_status(array $business): string {
    return strtolower(trim(ho_command_safe_claim_value($business, 'front_door_preview_status')));
}

function ho_command_is_preview_status_ready(array $business): bool {
    return in_array(ho_command_front_door_status($business), ['preview_ready','diagnosis_ready','go_ready','go_page_ready','published','live'], true);
}

function ho_command_has_downstream_sales_asset_state(array $business): bool {
    $frontStatus = strtolower(ho_command_safe_claim_value($business, 'front_door_preview_status'));
    $packageStatus = strtolower(ho_command_safe_claim_value($business, 'package_status'));
    $marketingStatus = strtolower(ho_command_safe_claim_value($business, 'marketing_desk_status'));

    $downstreamFields = [
        'go_slug',
        'go_path',
        'outreach_asset_url',
        'short_slug',
        'hotlink_path',
        'design_dashboard_path',
        'sales_report_path',
        'outreach_subject',
        'outreach_body',
    ];

    foreach ($downstreamFields as $field) {
        if (ho_command_safe_claim_value($business, $field) !== '') return true;
    }

    $frontStatuses = [
        'preview_ready',
        'go_ready',
        'go_page_ready',
        'published',
        'live',
        'ready_for_marketing',
    ];

    $packageStatuses = [
        'package_drafted',
        'domain_check_needed',
        'package_ready',
        'ready_for_marketing',
        'materialized',
    ];

    $marketingStatuses = [
        'draft_needed',
        'draft_ready',
        'ready_to_send',
        'manual_ready_to_send',
        'sent',
        'sent_later',
        'paused_manual_review',
    ];

    return in_array($frontStatus, $frontStatuses, true)
        || in_array($packageStatus, $packageStatuses, true)
        || in_array($marketingStatus, $marketingStatuses, true);
}

function ho_command_needs_diagnosis(array $business): bool {
    if (ho_command_has_downstream_sales_asset_state($business)) return false;
    if (ho_command_has_diagnosis_keys($business)) return false;
    return ho_command_queue_key($business) === 'contact_ready';
}

function ho_command_evaluate_business(array $business): array {
    $queue = ho_command_queue_key($business);
    $contactReady = $queue === 'contact_ready';
    $hasContactPath = ho_command_has_contact_path($business);
    $hasDownstream = ho_command_has_downstream_sales_asset_state($business);
    $hasDiagnosis = ho_command_has_diagnosis_keys($business) || $hasDownstream;
    $previewStatusReady = ho_command_is_preview_status_ready($business);
    $diagnosisReady = ho_command_is_diagnosis_ready($business) || $previewStatusReady;
    $previewReady = ho_command_is_preview_ready($business) || $hasDownstream || $previewStatusReady;
    $hasGo = ho_command_has_go_page($business);
    $draftReady = ho_command_is_draft_ready($business);

    $strengths = ho_command_json_claim($business, 'strength_keys_json');
    $weaknesses = ho_command_json_claim($business, 'weakness_keys_json');
    $recommendations = ho_command_json_claim($business, 'recommendation_keys_json');
    $directions = ho_command_json_claim($business, 'preview_direction_keys_json');

    $problems = [];

    if (trim((string)($business['business_slug'] ?? '')) === '') $problems[] = 'missing_business_slug';
    if (trim((string)($business['business_name_current'] ?? '')) === '') $problems[] = 'missing_business_name';
    if ($contactReady && !$hasContactPath) $problems[] = 'contact_ready_but_no_usable_contact_path';

    if ($diagnosisReady && count($strengths) === 0) $problems[] = 'diagnosis_ready_but_missing_strength_keys_json';
    if ($diagnosisReady && count($weaknesses) === 0) $problems[] = 'diagnosis_ready_but_missing_weakness_keys_json';
    if ($diagnosisReady && count($recommendations) === 0) $problems[] = 'diagnosis_ready_but_missing_recommendation_keys_json';
    if ($diagnosisReady && count($directions) < 3) $problems[] = 'diagnosis_ready_but_missing_preview_direction_keys_json';

    if ($previewReady && !$hasGo) $problems[] = 'preview_ready_but_missing_go_slug_go_path';
    if (ho_command_is_placeholder_identity($business)) $problems[] = 'package_dummy_placeholder_identity';

    $diagStatus = ho_command_safe_claim_value($business, 'diagnosis_status');
    if ($hasDiagnosis && $diagStatus === '') $problems[] = 'diagnosis_claims_but_no_diagnosis_status';

    if ($draftReady && !$hasGo) $problems[] = 'conflicting_status_claims';
    if ($hasGo && !$diagnosisReady) $problems[] = 'conflicting_status_claims';

    return [
        'queue_key' => $queue,
        'contact_ready' => $contactReady,
        'has_contact_path' => $hasContactPath,
        'has_diagnosis' => $hasDiagnosis,
        'has_downstream_sales_asset_state' => $hasDownstream ?? false,
        'needs_diagnosis' => ho_command_needs_diagnosis($business),
        'diagnosis_ready' => $diagnosisReady,
        'preview_status_ready' => $previewStatusReady ?? false,
        'preview_ready' => $previewReady,
        'has_go_page' => $hasGo,
        'draft_ready' => $draftReady,
        'manual_review' => $queue === 'manual_review' || in_array('conflicting_status_claims', $problems, true),
        'problem_keys' => array_values(array_unique($problems)),
    ];
}

function ho_command_bucket_labels(): array {
    return [
        'need_triage' => 'Need Triage',
        'need_research' => 'Need Research',
        'proceed_no_website' => 'Proceed No Website',
        'ready_for_setup' => 'Ready For Setup',
        'contact_ready' => 'Contact Ready',
        'blocked_skip' => 'Blocked / Skip',

        'contact_ready_without_diagnosis' => 'Contact Ready Without Diagnosis',
        'diagnosis_ready' => 'Diagnosis Ready',
        'diagnosis_ready_without_go_preview' => 'Diagnosis Ready Without /go Preview',
        'front_door_preview_ready' => 'Front Door Preview Ready',
        'go_page_missing' => '/go Page Missing',
        'go_page_ready' => '/go Page Ready',
        'outreach_draft_needed' => 'Outreach Draft Needed',
        'draft_ready' => 'Draft Ready',
        'manual_review_needed' => 'Manual Review Needed',

        'missing_business_slug' => 'Missing Business Slug',
        'missing_business_name' => 'Missing Business Name',
        'contact_ready_but_no_usable_contact_path' => 'Contact Ready But No Usable Contact Path',
        'diagnosis_ready_but_missing_strength_keys_json' => 'Diagnosis Ready But Missing Strength Keys',
        'diagnosis_ready_but_missing_weakness_keys_json' => 'Diagnosis Ready But Missing Weakness Keys',
        'diagnosis_ready_but_missing_recommendation_keys_json' => 'Diagnosis Ready But Missing Recommendation Keys',
        'diagnosis_ready_but_missing_preview_direction_keys_json' => 'Diagnosis Ready But Missing Preview Directions',
        'preview_ready_but_missing_go_slug_go_path' => 'Preview Ready But Missing go_slug/go_path',
        'package_dummy_placeholder_identity' => 'Package Dummy / Placeholder Identity',
        'conflicting_status_claims' => 'Conflicting Status Claims',
        'diagnosis_claims_but_no_diagnosis_status' => 'Diagnosis Claims But No Diagnosis Status',
    ];
}

function ho_command_blank_buckets(): array {
    $keys = array_keys(ho_command_bucket_labels());
    return array_fill_keys($keys, []);
}

function ho_command_build_audit(array $businesses): array {
    $buckets = ho_command_blank_buckets();
    $states = [];

    foreach ($businesses as $business) {
        if (!is_array($business)) continue;
        $id = (int)($business['id'] ?? 0);
        $state = ho_command_evaluate_business($business);
        $states[$id] = $state;

        // Upstream pipeline.
        match ($state['queue_key']) {
            'need_triage' => $buckets['need_triage'][] = $business,
            'need_research' => $buckets['need_research'][] = $business,
            'proceed_no_website' => $buckets['proceed_no_website'][] = $business,
            'ready_setup', 'ready_for_setup' => $buckets['ready_for_setup'][] = $business,
            'contact_ready' => $buckets['contact_ready'][] = $business,
            'manual_review' => $buckets['manual_review_needed'][] = $business,
            default => $buckets['blocked_skip'][] = $business,
        };

        // Sales asset pipeline.
        if (($state['needs_diagnosis'] ?? false) === true) {
            $buckets['contact_ready_without_diagnosis'][] = $business;
        }

        if ($state['diagnosis_ready']) {
            $buckets['diagnosis_ready'][] = $business;
        }

        if ($state['preview_ready']) {
            $buckets['front_door_preview_ready'][] = $business;
        }

        if (($state['diagnosis_ready'] || $state['preview_ready']) && !$state['has_go_page']) {
            $buckets['diagnosis_ready_without_go_preview'][] = $business;
            $buckets['go_page_missing'][] = $business;
        }

        if ($state['has_go_page']) {
            $buckets['go_page_ready'][] = $business;
            if (!$state['draft_ready']) {
                $buckets['outreach_draft_needed'][] = $business;
            }
        }

        if ($state['draft_ready']) {
            $buckets['draft_ready'][] = $business;
        }

        if ($state['manual_review']) {
            $buckets['manual_review_needed'][] = $business;
        }

        foreach ($state['problem_keys'] as $problem) {
            if (isset($buckets[$problem])) {
                $buckets[$problem][] = $business;
            }
        }
    }

    // Deduplicate bucket rows by business id.
    foreach ($buckets as $key => $rows) {
        $seen = [];
        $deduped = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $dedupeKey = $id > 0 ? 'id:' . $id : 'slug:' . (string)($row['business_slug'] ?? spl_object_id((object)$row));
            if (isset($seen[$dedupeKey])) continue;
            $seen[$dedupeKey] = true;
            $deduped[] = $row;
        }
        $buckets[$key] = $deduped;
    }

    return [
        'version' => HO_COMMAND_CENTER_VERSION,
        'total_records' => count($businesses),
        'labels' => ho_command_bucket_labels(),
        'buckets' => $buckets,
        'states' => $states,
        'next_move' => ho_command_next_move($buckets),
    ];
}

function ho_command_next_move(array $buckets): array {
    $severeProblems = array_merge(
        $buckets['missing_business_slug'] ?? [],
        $buckets['missing_business_name'] ?? [],
        $buckets['conflicting_status_claims'] ?? [],
        $buckets['package_dummy_placeholder_identity'] ?? []
    );
    $severeCount = count($severeProblems);

    if ($severeCount > 0) {
        return [
            'next_move_key' => 'review_data_problems',
            'title' => 'Review Data Problems',
            'why' => 'Severe identity or conflicting-state problems exist. These can cause bad routing, bad links, or unusable sales assets.',
            'count' => $severeCount,
            'target_url' => '/sales-command-center-audit.php#data-problems',
            'target_label' => 'Review Data Problems',
            'expected_result' => 'Clean or isolate the broken records so batch work can move without hidden failures.',
            'bucket_key' => 'conflicting_status_claims',
        ];
    }

    if (count($buckets['diagnosis_ready_without_go_preview'] ?? []) > 0) {
        return [
            'next_move_key' => 'build_go_previews',
            'title' => 'Build /go Front Door Preview Pages',
            'why' => 'Diagnosis or preview-ready records exist, but these businesses do not yet have a customer-facing /go preview path.',
            'count' => count($buckets['diagnosis_ready_without_go_preview']),
            'target_url' => '/sales-front-door-builder.php',
            'target_label' => 'Open Front Door Builder',
            'expected_result' => 'Each preview-ready business receives a go_slug/go_path and becomes ready for outreach draft preparation.',
            'bucket_key' => 'diagnosis_ready_without_go_preview',
        ];
    }

    if (count($buckets['contact_ready_without_diagnosis'] ?? []) > 0) {
        return [
            'next_move_key' => 'run_diagnosis_batch',
            'title' => 'Run Diagnosis Batch',
            'why' => 'Contact Ready businesses still need strength, weakness, recommendation, and preview direction keys before a /go page can be built.',
            'count' => count($buckets['contact_ready_without_diagnosis']),
            'target_url' => '/sales-diagnosis-workbench.php',
            'target_label' => 'Open Diagnosis Workbench',
            'expected_result' => 'Diagnosis keys are imported and those records become ready for /go preview rendering.',
            'bucket_key' => 'contact_ready_without_diagnosis',
        ];
    }

    if (count($buckets['outreach_draft_needed'] ?? []) > 0) {
        return [
            'next_move_key' => 'draft_outreach',
            'title' => 'Draft Outreach',
            'why' => '/go preview pages exist, but outreach drafts have not been prepared for manual review.',
            'count' => count($buckets['outreach_draft_needed']),
            'target_url' => '/sales-marketing-desk.php',
            'target_label' => 'Open Marketing Desk',
            'expected_result' => 'Draft subject/body copy is staged for manual send review.',
            'bucket_key' => 'outreach_draft_needed',
        ];
    }

    if (count($buckets['draft_ready'] ?? []) > 0) {
        return [
            'next_move_key' => 'manual_send_review',
            'title' => 'Manual Send Review',
            'why' => 'Outreach drafts are staged and need a human review before any manual sending.',
            'count' => count($buckets['draft_ready']),
            'target_url' => '/sales-marketing-desk.php',
            'target_label' => 'Review Drafts',
            'expected_result' => 'Approved drafts are copied/sent manually and then marked for follow-up later.',
            'bucket_key' => 'draft_ready',
        ];
    }

    return [
        'next_move_key' => 'find_new_leads',
        'title' => 'Find New Leads',
        'why' => 'No active sales-asset production pile is waiting according to the current state audit.',
        'count' => 0,
        'target_url' => '/sales-research.php',
        'target_label' => 'Open Lead Finder',
        'expected_result' => 'A new candidate batch enters the upstream lead pipeline.',
        'bucket_key' => 'need_triage',
    ];
}

function ho_command_load_businesses(): array {
    if (function_exists('ho_salesportal_list_businesses_with_readiness')) {
        return ho_salesportal_list_businesses_with_readiness(null, '');
    }
    if (function_exists('ho_salesportal_list_businesses')) {
        return ho_salesportal_list_businesses(null, '');
    }
    return [];
}

function ho_command_after_this(array $nextMove): array {
    $key = (string)($nextMove['next_move_key'] ?? '');
    return match ($key) {
        'review_data_problems' => [
            'title' => 'Then Run The Waiting Batch',
            'body' => 'After severe data issues are isolated, the dashboard should return to the highest waiting production pile.',
            'target_label' => 'Open Full State Audit',
            'target_url' => '/sales-command-center-audit.php',
        ],
        'run_diagnosis_batch' => [
            'title' => 'Then Build /go Preview Pages',
            'body' => 'Once diagnosis keys are imported, the next production step is assigning /go paths and rendering the customer-facing Front Door Preview.',
            'target_label' => 'Next: Front Door Builder',
            'target_url' => '/sales-front-door-builder.php',
        ],
        'build_go_previews' => [
            'title' => 'Then Draft Outreach',
            'body' => 'Once /go previews exist, Marketing Desk can create short manual outreach drafts around one customer-facing link.',
            'target_label' => 'Next: Marketing Desk',
            'target_url' => '/sales-marketing-desk.php',
        ],
        'draft_outreach' => [
            'title' => 'Then Manual Send Review',
            'body' => 'After drafts are imported, review the To, Subject, Body, and /go link before any manual sending.',
            'target_label' => 'Review Drafts',
            'target_url' => '/sales-marketing-desk.php',
        ],
        'manual_send_review' => [
            'title' => 'Then Track Follow-Up',
            'body' => 'After manual sending, the next layer is follow-up status and market coverage tracking.',
            'target_label' => 'Open Marketing Desk',
            'target_url' => '/sales-marketing-desk.php',
        ],
        default => [
            'title' => 'Then Continue The Pipeline',
            'body' => 'Once new leads enter the system, they move through triage, research, diagnosis, /go preview, and outreach preparation.',
            'target_label' => 'Open Full State Audit',
            'target_url' => '/sales-command-center-audit.php',
        ],
    };
}

function ho_command_bucket_count(array $audit, string $key): int {
    return count($audit['buckets'][$key] ?? []);
}

?>