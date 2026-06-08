<?php
declare(strict_types=1);

require_once __DIR__ . '/../database.php';

function ho_salesportal_canon(): array {
    return [
        'claim_fields' => [
            'business_name','business_type','business_description','owner_name','brand_name_consistency',
            'street_address','city','state','service_area','hours_of_operation','location_consistency',
            'website_url','google_profile_url','facebook_url','instagram_url','directory_listing_url','single_customer_destination_present','public_presence_consistency',
            'phone_number','email_address','contact_form_present','request_form_present','facebook_message_enabled','primary_cta_text','confirmation_message_present','contact_path_clarity',
            'services_list_present','products_list_present','menu_present','pricing_present','package_or_offer_present','service_descriptions_clear','customer_use_case_clear',
            'photos_present','photo_quality','before_after_present','portfolio_present','reviews_present','review_count','average_rating','testimonials_present','licenses_certifications_present','recent_activity_present',
            'booking_link_present','appointment_form_present','estimate_request_form_present','calendar_link_present','preferred_time_field_present','availability_note_present','booking_expectation_text',
            'payment_link_present','deposit_link_present','invoice_link_present','checkout_link_present','payment_provider_visible','payment_terms_present','payment_path_clarity',
            'broken_links_present','conflicting_phone_numbers','conflicting_hours','dead_website','bad_mobile_layout','missing_images','old_posts_or_stale_activity','duplicate_profiles','domain_confusion','too_much_scrolling_required','scattered_customer_path',
            'primary_sales_angle','recommended_package','recommended_design','recommended_features','marketing_clearance_score','marketing_clearance_status',
        ],
        'confidence_levels' => ['confirmed','likely','inferred','weak_inference','missing','conflicting','rejected'],
        'claim_statuses' => ['active','needs_review','missing','conflicting','rejected','superseded'],
        'source_types' => ['website','google_business_profile','facebook','instagram','directory','email','manual_observation','phone_call','customer_submission','other'],
        'requirements' => [
            'find_me.business_identity_clear','find_me.location_or_service_area_clear','find_me.public_search_presence','find_me.single_customer_destination',
            'trust_me.appears_active','trust_me.has_proof','trust_me.has_consistent_identity','trust_me.has_credible_presentation',
            'contact_me.clear_primary_contact','contact_me.structured_request_path','contact_me.customer_next_step_clear',
            'show_me.services_visible','show_me.products_or_work_visible','show_me.offer_clarity','show_me.visual_proof',
            'book_me.request_time_possible','book_me.appointment_or_estimate_path','book_me.booking_expectation_clear',
            'pay_me.payment_path_exists','pay_me.deposit_path_exists','pay_me.payment_instructions_clear',
            'fix_me.broken_or_conflicting_info','fix_me.outdated_presence','fix_me.technical_mess','fix_me.customer_path_mess',
        ],
        'me_categories' => ['find_me','trust_me','contact_me','show_me','book_me','pay_me','fix_me'],
        'marketing_statuses' => ['cleared','warm_clear','needs_review','hold','skip','blocked'],
        'packages' => ['standard','managed','unknown'],
    ];
}

function ho_salesportal_slugify(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'business-' . date('YmdHis');
}

function ho_salesportal_validate_payload(array $payload): array {
    $canon = ho_salesportal_canon();
    $errors = [];

    if (!isset($payload['business']) || !is_array($payload['business'])) $errors[] = 'Missing required object: business';
    if (!isset($payload['claims']) || !is_array($payload['claims'])) $errors[] = 'Missing required array: claims';

    if (isset($payload['claims']) && is_array($payload['claims'])) {
        foreach ($payload['claims'] as $i => $claim) {
            if (!is_array($claim)) { $errors[] = "claims[$i] must be an object."; continue; }

            $fieldKey = (string)($claim['field_key'] ?? '');
            if ($fieldKey === '' || !in_array($fieldKey, $canon['claim_fields'], true)) $errors[] = "claims[$i].field_key is invalid: " . $fieldKey;

            $confidence = (string)($claim['confidence_level'] ?? 'missing');
            if (!in_array($confidence, $canon['confidence_levels'], true)) $errors[] = "claims[$i].confidence_level is invalid: " . $confidence;

            $status = (string)($claim['claim_status'] ?? 'active');
            if (!in_array($status, $canon['claim_statuses'], true)) $errors[] = "claims[$i].claim_status is invalid: " . $status;

            $sourceType = (string)($claim['source_type'] ?? 'other');
            if (!in_array($sourceType, $canon['source_types'], true)) $errors[] = "claims[$i].source_type is invalid: " . $sourceType;

            $req = (string)($claim['supports_requirement_key'] ?? '');
            if ($req !== '' && !in_array($req, $canon['requirements'], true)) $errors[] = "claims[$i].supports_requirement_key is invalid: " . $req;

            $cat = (string)($claim['supports_me_category'] ?? '');
            if ($cat !== '' && !in_array($cat, $canon['me_categories'], true)) $errors[] = "claims[$i].supports_me_category is invalid: " . $cat;

            $score = $claim['confidence_score'] ?? 0;
            if (!is_numeric($score) || $score < 0 || $score > 100) $errors[] = "claims[$i].confidence_score must be 0-100.";
        }
    }

    return ['ok' => count($errors) === 0, 'message' => count($errors) ? 'Validation failed.' : 'Validation passed.', 'details' => $errors];
}

function ho_salesportal_get_business_by_slug(string $slug): ?array {
    $stmt = ho_db()->prepare('SELECT * FROM businesses WHERE business_slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ho_salesportal_get_business_by_id(int $id): ?array {
    $stmt = ho_db()->prepare('SELECT * FROM businesses WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ho_salesportal_upsert_business(array $business, ?array $clearance = null): int {
    $pdo = ho_db();

    $name = trim((string)($business['business_name_current'] ?? $business['business_name'] ?? ''));
    $slug = trim((string)($business['business_slug'] ?? ''));
    if ($slug === '') $slug = ho_salesportal_slugify($name !== '' ? $name : 'business');

    $existing = ho_salesportal_get_business_by_slug($slug);

    $params = [
        'business_slug' => $slug,
        'business_name_current' => $name !== '' ? $name : null,
        'business_type' => $business['business_type'] ?? null,
        'location_city' => $business['location_city'] ?? $business['city'] ?? null,
        'location_state' => $business['location_state'] ?? $business['state'] ?? null,
        'service_area_text' => $business['service_area_text'] ?? $business['service_area'] ?? null,
        'marketing_clearance_score' => $clearance['marketing_clearance_score'] ?? null,
        'marketing_clearance_status' => $clearance['marketing_clearance_status'] ?? 'hold',
        'recommended_package' => $clearance['recommended_package'] ?? 'unknown',
        'recommended_design' => $clearance['recommended_design'] ?? null,
    ];

    if ($existing) {
        $stmt = $pdo->prepare(
            'UPDATE businesses SET
              business_name_current = COALESCE(:business_name_current, business_name_current),
              business_type = COALESCE(:business_type, business_type),
              location_city = COALESCE(:location_city, location_city),
              location_state = COALESCE(:location_state, location_state),
              service_area_text = COALESCE(:service_area_text, service_area_text),
              marketing_clearance_score = COALESCE(:marketing_clearance_score, marketing_clearance_score),
              marketing_clearance_status = :marketing_clearance_status,
              recommended_package = :recommended_package,
              recommended_design = COALESCE(:recommended_design, recommended_design)
             WHERE business_slug = :business_slug'
        );
        $stmt->execute($params);
        return (int)$existing['id'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO businesses
          (business_slug, business_name_current, business_type, location_city, location_state, service_area_text, marketing_clearance_score, marketing_clearance_status, recommended_package, recommended_design)
         VALUES
          (:business_slug, :business_name_current, :business_type, :location_city, :location_state, :service_area_text, :marketing_clearance_score, :marketing_clearance_status, :recommended_package, :recommended_design)'
    );
    $stmt->execute($params);
    return (int)$pdo->lastInsertId();
}

function ho_salesportal_import_payload(array $payload): array {
    $validation = ho_salesportal_validate_payload($payload);
    if (!$validation['ok']) return $validation;

    $pdo = ho_db();
    $pdo->beginTransaction();

    try {
        $businessId = ho_salesportal_upsert_business($payload['business'], $payload['marketing_clearance'] ?? null);

        $sourceMap = [];
        foreach (($payload['evidence_sources'] ?? []) as $idx => $source) {
            if (!is_array($source)) continue;
            $stmt = $pdo->prepare(
                'INSERT INTO evidence_sources
                  (business_id, source_type, source_url, source_title, captured_at, capture_status, raw_excerpt, screenshot_path, notes)
                 VALUES
                  (:business_id, :source_type, :source_url, :source_title, :captured_at, :capture_status, :raw_excerpt, :screenshot_path, :notes)'
            );
            $stmt->execute([
                'business_id' => $businessId,
                'source_type' => $source['source_type'] ?? 'other',
                'source_url' => $source['source_url'] ?? null,
                'source_title' => $source['source_title'] ?? null,
                'captured_at' => $source['captured_at'] ?? null,
                'capture_status' => $source['capture_status'] ?? 'manual',
                'raw_excerpt' => $source['raw_excerpt'] ?? null,
                'screenshot_path' => $source['screenshot_path'] ?? null,
                'notes' => $source['notes'] ?? null,
            ]);
            $sourceMap[$idx] = (int)$pdo->lastInsertId();
        }

        $claimCount = 0;
        foreach ($payload['claims'] as $claim) {
            $evidenceId = null;
            if (isset($claim['evidence_source_index']) && isset($sourceMap[(int)$claim['evidence_source_index']])) {
                $evidenceId = $sourceMap[(int)$claim['evidence_source_index']];
            }

            $stmt = $pdo->prepare(
                'INSERT INTO business_claims
                  (business_id, evidence_source_id, field_key, field_label, claim_value, normalized_value, confidence_level, confidence_score, claim_status, source_type, source_url, source_label, evidence_note, supports_me_category, supports_requirement_key)
                 VALUES
                  (:business_id, :evidence_source_id, :field_key, :field_label, :claim_value, :normalized_value, :confidence_level, :confidence_score, :claim_status, :source_type, :source_url, :source_label, :evidence_note, :supports_me_category, :supports_requirement_key)'
            );

            $stmt->execute([
                'business_id' => $businessId,
                'evidence_source_id' => $evidenceId,
                'field_key' => $claim['field_key'],
                'field_label' => $claim['field_label'] ?? ucwords(str_replace('_', ' ', (string)$claim['field_key'])),
                'claim_value' => is_scalar($claim['claim_value'] ?? null) ? (string)$claim['claim_value'] : json_encode($claim['claim_value'] ?? null),
                'normalized_value' => is_scalar($claim['normalized_value'] ?? null) ? (string)$claim['normalized_value'] : json_encode($claim['normalized_value'] ?? null),
                'confidence_level' => $claim['confidence_level'] ?? 'missing',
                'confidence_score' => (float)($claim['confidence_score'] ?? 0),
                'claim_status' => $claim['claim_status'] ?? 'active',
                'source_type' => $claim['source_type'] ?? null,
                'source_url' => $claim['source_url'] ?? null,
                'source_label' => $claim['source_label'] ?? null,
                'evidence_note' => $claim['evidence_note'] ?? null,
                'supports_me_category' => $claim['supports_me_category'] ?? null,
                'supports_requirement_key' => $claim['supports_requirement_key'] ?? null,
            ]);
            $claimCount++;
        }

        $pdo->commit();

        return [
            'ok' => true,
            'message' => 'Import complete.',
            'business_id' => $businessId,
            'business_slug' => $payload['business']['business_slug'] ?? null,
            'evidence_sources_imported' => count($sourceMap),
            'claims_imported' => $claimCount,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['ok' => false, 'message' => $e->getMessage(), 'details' => []];
    }
}

function ho_salesportal_dashboard_counts(): array {
    $rows = ho_db()->query("SELECT marketing_clearance_status, COUNT(*) AS total FROM businesses GROUP BY marketing_clearance_status")->fetchAll();
    $counts = [];
    foreach ($rows as $row) $counts[(string)$row['marketing_clearance_status']] = (int)$row['total'];
    return $counts;
}

function ho_salesportal_list_businesses(?string $status = null, string $search = ''): array {
    $pdo = ho_db();
    $where = [];
    $params = [];

    if ($status !== null && $status !== '') { $where[] = 'b.marketing_clearance_status = :status'; $params['status'] = $status; }
    if ($search !== '') {
        $where[] = '(b.business_name_current LIKE :search OR b.business_type LIKE :search OR b.location_city LIKE :search OR b.service_area_text LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    $whereSql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT b.*,
            COUNT(DISTINCT c.id) AS claim_count,
            COUNT(DISTINCT e.id) AS evidence_count,
            MAX(c.updated_at) AS last_claim_at
        FROM businesses b
        LEFT JOIN business_claims c ON c.business_id = b.id
        LEFT JOIN evidence_sources e ON e.business_id = b.id
        $whereSql
        GROUP BY b.id
        ORDER BY
            CASE b.marketing_clearance_status
                WHEN 'cleared' THEN 1 WHEN 'warm_clear' THEN 2 WHEN 'needs_review' THEN 3
                WHEN 'hold' THEN 4 WHEN 'skip' THEN 5 WHEN 'blocked' THEN 6 ELSE 7
            END,
            b.marketing_clearance_score DESC,
            b.updated_at DESC
        LIMIT 250
    ");
    $stmt->execute($params);
    return ho_salesportal_attach_latest_claims_to_businesses($stmt->fetchAll());
}

function ho_salesportal_business_evidence(int $businessId): array {
    $stmt = ho_db()->prepare('SELECT * FROM evidence_sources WHERE business_id = :id ORDER BY created_at DESC, id DESC');
    $stmt->execute(['id' => $businessId]);
    return $stmt->fetchAll();
}

function ho_salesportal_business_claims(int $businessId): array {
    $stmt = ho_db()->prepare('SELECT * FROM business_claims WHERE business_id = :id ORDER BY supports_me_category, field_key, confidence_score DESC, updated_at DESC');
    $stmt->execute(['id' => $businessId]);
    return $stmt->fetchAll();
}

function ho_salesportal_group_claims_by_category(array $claims): array {
    $groups = [];
    foreach ($claims as $claim) {
        $key = $claim['supports_me_category'] ?: 'unmapped';
        if (!isset($groups[$key])) $groups[$key] = [];
        $groups[$key][] = $claim;
    }
    return $groups;
}

function ho_salesportal_claim_summary(array $claims): array {
    $summary = [
        'high_confidence' => 0,
        'medium_confidence' => 0,
        'low_confidence' => 0,
        'needs_review' => 0,
        'conflicting' => 0,
        'top_strengths' => [],
        'top_issues' => [],
        'category_counts' => [],
    ];

    $strengthFields = ['photos_present','reviews_present','recent_activity_present','phone_number','services_list_present','google_profile_url','facebook_url','website_url','business_name','business_type'];
    $issueFields = ['request_form_present','scattered_customer_path','bad_mobile_layout','broken_links_present','old_posts_or_stale_activity','conflicting_phone_numbers','conflicting_hours','dead_website','too_much_scrolling_required','single_customer_destination_present','contact_form_present','booking_link_present','payment_link_present'];

    foreach ($claims as $claim) {
        $score = (float)$claim['confidence_score'];
        if ($score >= 75) $summary['high_confidence']++;
        elseif ($score >= 50) $summary['medium_confidence']++;
        else $summary['low_confidence']++;

        if ($claim['claim_status'] === 'needs_review') $summary['needs_review']++;
        if ($claim['claim_status'] === 'conflicting' || $claim['confidence_level'] === 'conflicting') $summary['conflicting']++;

        $category = $claim['supports_me_category'] ?: 'unmapped';
        $summary['category_counts'][$category] = ($summary['category_counts'][$category] ?? 0) + 1;

        $field = (string)$claim['field_key'];
        $note = trim((string)($claim['evidence_note'] ?? ''));
        $value = trim((string)($claim['claim_value'] ?? ''));
        $line = $note !== '' ? $note : ($field . ': ' . $value);

        if (in_array($field, $strengthFields, true) && $value !== '' && !in_array(strtolower($value), ['false','0','no','missing'], true) && count($summary['top_strengths']) < 5) {
            $summary['top_strengths'][] = $line;
        }

        if (in_array($field, $issueFields, true) && ($value === '' || in_array(strtolower($value), ['false','0','no','missing','not present'], true) || str_contains(strtolower($line), 'no ') || str_contains(strtolower($line), 'missing') || str_contains(strtolower($line), 'unclear')) && count($summary['top_issues']) < 5) {
            $summary['top_issues'][] = $line;
        }
    }

    return $summary;
}

function ho_salesportal_business_scores(int $businessId): array {
    $pdo = ho_db();

    $meStmt = $pdo->prepare("
        SELECT c.category_key, c.category_name, s.score, s.confidence_score, s.top_issue, s.top_strength, s.status
        FROM business_me_scores s
        JOIN me_categories c ON c.id = s.category_id
        WHERE s.business_id = :id
        ORDER BY c.category_order
    ");
    $meStmt->execute(['id' => $businessId]);

    $reqStmt = $pdo->prepare("
        SELECT c.category_key, r.requirement_key, r.requirement_label, s.score, s.confidence_score, s.status, s.reason
        FROM business_requirement_scores s
        JOIN me_requirements r ON r.id = s.requirement_id
        JOIN me_categories c ON c.id = r.category_id
        WHERE s.business_id = :id
        ORDER BY c.category_order, r.id
    ");
    $reqStmt->execute(['id' => $businessId]);

    return ['me_scores' => $meStmt->fetchAll(), 'requirement_scores' => $reqStmt->fetchAll()];
}

function ho_salesportal_network_snapshot(): array {
    $pdo = ho_db();

    $businessCount = (int)$pdo->query('SELECT COUNT(*) FROM businesses')->fetchColumn();
    $claimCount = (int)$pdo->query('SELECT COUNT(*) FROM business_claims')->fetchColumn();
    $sourceCount = (int)$pdo->query('SELECT COUNT(*) FROM evidence_sources')->fetchColumn();
    $highClaims = (int)$pdo->query('SELECT COUNT(*) FROM business_claims WHERE confidence_score >= 75')->fetchColumn();
    $reviewClaims = (int)$pdo->query("SELECT COUNT(*) FROM business_claims WHERE claim_status IN ('needs_review','conflicting') OR confidence_level = 'conflicting'")->fetchColumn();

    $categoryRows = $pdo->query("
        SELECT COALESCE(supports_me_category, 'unmapped') AS category, COUNT(*) AS total
        FROM business_claims
        GROUP BY COALESCE(supports_me_category, 'unmapped')
        ORDER BY total DESC
    ")->fetchAll();

    $statusRows = $pdo->query("SELECT marketing_clearance_status AS status, COUNT(*) AS total FROM businesses GROUP BY marketing_clearance_status")->fetchAll();

    $recent = $pdo->query("
        SELECT b.*, COUNT(DISTINCT c.id) AS claim_count, COUNT(DISTINCT e.id) AS evidence_count
        FROM businesses b
        LEFT JOIN business_claims c ON c.business_id = b.id
        LEFT JOIN evidence_sources e ON e.business_id = b.id
        GROUP BY b.id
        ORDER BY b.updated_at DESC
        LIMIT 12
    ")->fetchAll();

    return [
        'business_count' => $businessCount,
        'claim_count' => $claimCount,
        'source_count' => $sourceCount,
        'high_claims' => $highClaims,
        'review_claims' => $reviewClaims,
        'category_rows' => $categoryRows,
        'status_rows' => $statusRows,
        'recent_businesses' => $recent,
    ];
}




/**
 * Backend hardening helpers v049
 *
 * These helpers prevent preview-readiness/assignment features from crashing the site
 * when the preview schema has not been imported yet or when optional tables are absent.
 */
function ho_salesportal_table_exists(string $tableName): bool {
    static $cache = [];

    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $stmt = ho_db()->prepare(
            "SELECT COUNT(*)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name"
        );
        $stmt->execute(['table_name' => $tableName]);
        $cache[$tableName] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function ho_salesportal_preview_schema_ready(): bool {
    return
        ho_salesportal_table_exists('preview_readiness') &&
        ho_salesportal_table_exists('preview_option_groups') &&
        ho_salesportal_table_exists('preview_design_options') &&
        ho_salesportal_table_exists('preview_address_options') &&
        ho_salesportal_table_exists('preview_customer_choices') &&
        ho_salesportal_table_exists('preview_build_handoff_links');
}

function ho_salesportal_preview_schema_status(): array {
    $tables = [
        'preview_readiness',
        'preview_option_groups',
        'preview_design_options',
        'preview_address_options',
        'preview_customer_choices',
        'preview_build_handoff_links',
    ];

    $result = [];
    foreach ($tables as $table) {
        $result[$table] = ho_salesportal_table_exists($table);
    }

    return [
        'ok' => !in_array(false, $result, true),
        'tables' => $result,
    ];
}

function ho_salesportal_safe_json_encode($value): string {
    $json = json_encode($value, JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}

function ho_salesportal_backend_unavailable_result(int $businessId, string $feature, string $message): array {
    return [
        'ok' => false,
        'business_id' => $businessId,
        'feature' => $feature,
        'readiness_status' => 'unavailable',
        'readiness_score' => null,
        'customer_safe_summary' => $message,
        'internal_review_notes' => $message,
        'missing_inputs' => [],
        'blocked_reason' => 'schema_not_ready',
        'message' => $message,
        'recommended_design_options' => [],
        'address_options' => [],
        'assignment_allowed' => false,
    ];
}

/**
 * Preview Readiness Evaluator v045
 *
 * Internal-only evaluator:
 * - reads existing business_claims / businesses
 * - computes preview readiness
 * - writes/updates preview_readiness
 * - does not generate customer-facing preview pages
 */
function ho_salesportal_normalize_claim_value($value): string {
    return strtolower(trim((string)$value));
}

function ho_salesportal_claim_truthy(array $claim): bool {
    $value = ho_salesportal_normalize_claim_value($claim['claim_value'] ?? '');
    if ($value === '') return false;
    if (in_array($value, ['0','false','no','none','missing','not present','n/a','na','unknown'], true)) return false;
    return true;
}

function ho_salesportal_best_claim_by_field(array $claims, string $fieldKey): ?array {
    $best = null;
    foreach ($claims as $claim) {
        if (($claim['field_key'] ?? '') !== $fieldKey) continue;
        if ($best === null || (float)$claim['confidence_score'] > (float)$best['confidence_score']) {
            $best = $claim;
        }
    }
    return $best;
}

function ho_salesportal_has_claim(array $claims, string $fieldKey, float $minConfidence = 0, ?bool $truthy = null): bool {
    foreach ($claims as $claim) {
        if (($claim['field_key'] ?? '') !== $fieldKey) continue;
        if ((float)($claim['confidence_score'] ?? 0) < $minConfidence) continue;
        if ($truthy === true && !ho_salesportal_claim_truthy($claim)) continue;
        if ($truthy === false && ho_salesportal_claim_truthy($claim)) continue;
        return true;
    }
    return false;
}

function ho_salesportal_has_any_claim(array $claims, array $fieldKeys, float $minConfidence = 0, ?bool $truthy = null): bool {
    foreach ($fieldKeys as $fieldKey) {
        if (ho_salesportal_has_claim($claims, $fieldKey, $minConfidence, $truthy)) {
            return true;
        }
    }
    return false;
}

function ho_salesportal_get_preview_readiness(int $businessId): ?array {
    if (!ho_salesportal_table_exists('preview_readiness')) return null;
    $stmt = ho_db()->prepare('SELECT * FROM preview_readiness WHERE business_id = :id LIMIT 1');
    $stmt->execute(['id' => $businessId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function ho_salesportal_evaluate_preview_readiness(int $businessId, bool $write = true): array {
    if (!ho_salesportal_table_exists('preview_readiness')) {
        return ho_salesportal_backend_unavailable_result($businessId, 'preview_readiness', 'Preview readiness unavailable because v044 preview schema has not been imported.');
    }

    $business = ho_salesportal_get_business_by_id($businessId);
    if (!$business) {
        return [
            'ok' => false,
            'business_id' => $businessId,
            'readiness_status' => 'blocked',
            'readiness_score' => 0,
            'customer_safe_summary' => '',
            'internal_review_notes' => 'Business not found.',
            'missing_inputs' => ['business record'],
            'blocked_reason' => 'business_not_found',
        ];
    }

    $claims = ho_salesportal_business_claims($businessId);
    $evidence = ho_salesportal_business_evidence($businessId);

    $missing = [];
    $review = [];
    $score = 0;

    $businessNameClaim = ho_salesportal_best_claim_by_field($claims, 'business_name');
    $businessTypeClaim = ho_salesportal_best_claim_by_field($claims, 'business_type');
    $cityClaim = ho_salesportal_best_claim_by_field($claims, 'city');
    $serviceAreaClaim = ho_salesportal_best_claim_by_field($claims, 'service_area');

    $businessNameConfidence = $businessNameClaim ? (float)$businessNameClaim['confidence_score'] : ($business['business_name_current'] ? 70 : 0);
    $businessTypeConfidence = $businessTypeClaim ? (float)$businessTypeClaim['confidence_score'] : ($business['business_type'] ? 60 : 0);
    $locationConfidence = max(
        $cityClaim ? (float)$cityClaim['confidence_score'] : ($business['location_city'] ? 60 : 0),
        $serviceAreaClaim ? (float)$serviceAreaClaim['confidence_score'] : ($business['service_area_text'] ? 60 : 0)
    );

    if ($businessNameConfidence >= 70) $score += 15; else $missing[] = 'business_name confidence >= 70';
    if ($businessTypeConfidence >= 60) $score += 10; else $missing[] = 'business_type confidence >= 60';
    if ($locationConfidence >= 60) $score += 10; else $missing[] = 'city or service_area confidence >= 60';

    if (count($evidence) > 0) $score += 10; else $missing[] = 'at least one public evidence source';

    $contactPositive = ho_salesportal_has_any_claim($claims, [
        'phone_number','email_address','contact_form_present','facebook_message_enabled','website_url','google_profile_url','facebook_url'
    ], 60, true);

    $contactWeakness = ho_salesportal_has_any_claim($claims, [
        'contact_path_clarity','request_form_present','contact_form_present','single_customer_destination_present'
    ], 50, false);

    if ($contactPositive || $contactWeakness) $score += 10; else $missing[] = 'usable contact path or clear contact-path weakness';

    $activeSignal = ho_salesportal_has_any_claim($claims, [
        'recent_activity_present','photos_present','reviews_present','services_list_present','products_list_present','portfolio_present','google_profile_url','facebook_url'
    ], 55, true);

    if ($activeSignal) $score += 10; else $review[] = 'No strong active-business signal found. Use soft preview only if manually approved.';

    $gapSignal = ho_salesportal_has_any_claim($claims, [
        'request_form_present','contact_form_present','booking_link_present','payment_link_present','single_customer_destination_present','bad_mobile_layout','scattered_customer_path','old_posts_or_stale_activity','broken_links_present'
    ], 45, false);

    $explicitGap = ho_salesportal_has_any_claim($claims, [
        'bad_mobile_layout','scattered_customer_path','old_posts_or_stale_activity','broken_links_present','too_much_scrolling_required','conflicting_phone_numbers','conflicting_hours'
    ], 45, true);

    if ($gapSignal || $explicitGap) $score += 15; else $missing[] = 'at least one customer-path gap';

    $clearance = (string)($business['marketing_clearance_status'] ?? 'hold');
    if (in_array($clearance, ['cleared','warm_clear'], true)) {
        $score += 15;
    } elseif ($clearance === 'needs_review') {
        $score += 7;
        $review[] = 'Business clearance is needs_review.';
    } elseif (in_array($clearance, ['blocked','skip'], true)) {
        $review[] = 'Business clearance status is ' . $clearance . '.';
    } else {
        $review[] = 'Business clearance is hold or not ready.';
    }

    $conflictCount = 0;
    foreach ($claims as $claim) {
        if (($claim['claim_status'] ?? '') === 'conflicting' || ($claim['confidence_level'] ?? '') === 'conflicting') {
            $conflictCount++;
        }
    }

    if ($conflictCount > 0) {
        $score -= min(20, $conflictCount * 5);
        $review[] = $conflictCount . ' conflicting claim(s) need review.';
    }

    $score = max(0, min(100, $score));

    $blockedReason = null;
    if (in_array($clearance, ['blocked','skip'], true)) {
        $status = $clearance === 'blocked' ? 'blocked' : 'manual_review';
        $blockedReason = $clearance === 'blocked' ? 'business_clearance_blocked' : null;
    } elseif ($businessNameConfidence < 50 || $businessTypeConfidence < 40) {
        $status = 'needs_more_research';
    } elseif ($score >= 75 && empty($missing) && $conflictCount === 0) {
        $status = 'ready';
    } elseif ($score >= 60 && $businessNameConfidence >= 60 && $businessTypeConfidence >= 50) {
        $status = 'soft_ready';
    } elseif ($score >= 45 || $conflictCount > 0 || !empty($review)) {
        $status = 'manual_review';
    } else {
        $status = 'needs_more_research';
    }

    $name = $business['business_name_current'] ?: ($businessNameClaim['claim_value'] ?? 'This business');
    $summaryParts = [];
    $summaryParts[] = $name . ' has been evaluated for Front Door preview readiness.';
    $summaryParts[] = 'Readiness: ' . str_replace('_', ' ', $status) . '.';
    $summaryParts[] = 'Score: ' . number_format($score, 0) . '/100.';
    if (!empty($missing)) $summaryParts[] = 'Missing: ' . implode('; ', array_slice($missing, 0, 5)) . '.';
    if (!empty($review)) $summaryParts[] = 'Review: ' . implode('; ', array_slice($review, 0, 4)) . '.';

    $result = [
        'ok' => true,
        'business_id' => $businessId,
        'readiness_status' => $status,
        'readiness_score' => $score,
        'customer_safe_summary' => implode(' ', $summaryParts),
        'internal_review_notes' => implode("\n", $review),
        'missing_inputs' => array_values($missing),
        'blocked_reason' => $blockedReason,
    ];

    if ($write) {
        $pdo = ho_db();
        $stmt = $pdo->prepare(
            "INSERT INTO preview_readiness
              (business_id, readiness_status, readiness_score, customer_safe_summary, internal_review_notes, missing_inputs_json, blocked_reason, last_evaluated_at)
             VALUES
              (:business_id, :readiness_status, :readiness_score, :customer_safe_summary, :internal_review_notes, :missing_inputs_json, :blocked_reason, NOW())
             ON DUPLICATE KEY UPDATE
              readiness_status = VALUES(readiness_status),
              readiness_score = VALUES(readiness_score),
              customer_safe_summary = VALUES(customer_safe_summary),
              internal_review_notes = VALUES(internal_review_notes),
              missing_inputs_json = VALUES(missing_inputs_json),
              blocked_reason = VALUES(blocked_reason),
              last_evaluated_at = NOW(),
              updated_at = NOW()"
        );

        $stmt->execute([
            'business_id' => $businessId,
            'readiness_status' => $status,
            'readiness_score' => $score,
            'customer_safe_summary' => $result['customer_safe_summary'],
            'internal_review_notes' => $result['internal_review_notes'],
            'missing_inputs_json' => ho_salesportal_safe_json_encode($result['missing_inputs']),
            'blocked_reason' => $blockedReason,
        ]);
    }

    return $result;
}

function ho_salesportal_preview_readiness_counts(): array {
    if (!ho_salesportal_table_exists('preview_readiness')) return [];
    $rows = ho_db()->query("SELECT readiness_status, COUNT(*) AS total FROM preview_readiness GROUP BY readiness_status")->fetchAll();
    $counts = [];
    foreach ($rows as $row) {
        $counts[(string)$row['readiness_status']] = (int)$row['total'];
    }
    return $counts;
}


function ho_salesportal_attach_latest_claims_to_businesses(array $businesses): array {
    if (!$businesses) return [];

    $ids = array_values(array_filter(array_map(static fn($row) => (int)($row['id'] ?? 0), $businesses)));
    if (!$ids) return $businesses;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = ho_db()->prepare("
        SELECT *
        FROM business_claims
        WHERE business_id IN ($placeholders)
          AND claim_status <> 'archived'
        ORDER BY business_id, field_key, updated_at DESC, id DESC
    ");
    $stmt->execute($ids);
    $claimRows = $stmt->fetchAll();

    $latestByBusiness = [];
    foreach ($claimRows as $claim) {
        $bid = (int)($claim['business_id'] ?? 0);
        $field = (string)($claim['field_key'] ?? '');
        if ($bid <= 0 || $field === '') continue;

        if (!isset($latestByBusiness[$bid])) {
            $latestByBusiness[$bid] = [];
        }

        // Rows are ordered newest first per field, so keep first field occurrence.
        if (!isset($latestByBusiness[$bid][$field])) {
            $latestByBusiness[$bid][$field] = $claim;
        }
    }

    foreach ($businesses as &$business) {
        $bid = (int)($business['id'] ?? 0);
        $business['_claims'] = array_values($latestByBusiness[$bid] ?? []);
        $business['_latest_claims_by_key'] = $latestByBusiness[$bid] ?? [];
    }
    unset($business);

    return $businesses;
}

function ho_salesportal_list_businesses_with_readiness(?string $status = null, string $search = ''): array {
    $businesses = ho_salesportal_list_businesses($status, $search);
    if (!$businesses) return [];

    $ids = array_map(static fn($row) => (int)$row['id'], $businesses);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = ho_db()->prepare("SELECT * FROM preview_readiness WHERE business_id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $byBusiness = [];
    foreach ($rows as $row) {
        $byBusiness[(int)$row['business_id']] = $row;
    }

    foreach ($businesses as &$business) {
        $business['_preview_readiness'] = $byBusiness[(int)$business['id']] ?? null;
    }
    unset($business);

    return ho_salesportal_attach_latest_claims_to_businesses($businesses);
}


/**
 * Preview Option Assignment v046
 *
 * Internal-only assignment:
 * - recommends preview design option(s)
 * - creates/suggests address options
 * - reads preview_readiness, business, claims, seeded preview_design_options
 * - does not generate customer-facing preview pages
 */
function ho_salesportal_claim_value_for_field(array $claims, string $fieldKey): ?string {
    $claim = ho_salesportal_best_claim_by_field($claims, $fieldKey);
    if (!$claim) return null;
    $value = trim((string)($claim['claim_value'] ?? ''));
    return $value !== '' ? $value : null;
}

function ho_salesportal_business_type_key(array $business, array $claims): string {
    $type = strtolower(trim((string)($business['business_type'] ?? '')));
    $claimType = ho_salesportal_claim_value_for_field($claims, 'business_type');
    if ($claimType) $type .= ' ' . strtolower($claimType);

    if (preg_match('/handy|repair|fix|maintenance|odd job|contractor|remodel|carpentry|drywall|paint|plumb|electric/i', $type)) {
        return 'handyman';
    }

    if (preg_match('/lawn|mow|landscap|clean|pressure|wash|gutter|snow|yard|exterior|maid|housekeep/i', $type)) {
        return 'lawn_cleaning';
    }

    return 'general';
}

function ho_salesportal_fetch_preview_design_options_for_business_type(string $businessTypeKey): array {
    if (!ho_salesportal_table_exists('preview_design_options') || !ho_salesportal_table_exists('preview_option_groups')) return [];
    $pdo = ho_db();
    $stmt = $pdo->prepare("
        SELECT d.*, g.group_key, g.group_label, g.business_type_key
        FROM preview_design_options d
        JOIN preview_option_groups g ON g.id = d.group_id
        WHERE d.is_active = 1
          AND g.is_active = 1
          AND (g.business_type_key = :business_type_key OR g.business_type_key = 'general')
        ORDER BY
          CASE WHEN g.business_type_key = :business_type_key THEN 0 ELSE 1 END,
          g.sort_order,
          d.sort_order
    ");
    $stmt->execute(['business_type_key' => $businessTypeKey]);
    return $stmt->fetchAll();
}

function ho_salesportal_choose_design_options(array $business, array $claims, string $businessTypeKey): array {
    $available = ho_salesportal_fetch_preview_design_options_for_business_type($businessTypeKey);

    $scores = [];
    foreach ($available as $option) {
        $key = (string)$option['option_key'];
        $score = 10;

        if (($option['business_type_key'] ?? '') === $businessTypeKey) $score += 20;

        if ($key === 'quote_request') {
            if (ho_salesportal_has_any_claim($claims, ['request_form_present','contact_form_present'], 45, false)) $score += 25;
            if (ho_salesportal_has_any_claim($claims, ['service_descriptions_clear','services_list_present'], 55, true)) $score += 8;
        }

        if ($key === 'before_after_proof') {
            if (ho_salesportal_has_any_claim($claims, ['photos_present','before_after_present','portfolio_present'], 55, true)) $score += 25;
            if (ho_salesportal_has_any_claim($claims, ['photo_quality'], 55, true)) $score += 10;
        }

        if ($key === 'mobile_call_now') {
            if (ho_salesportal_has_any_claim($claims, ['phone_number'], 60, true)) $score += 18;
            if (ho_salesportal_has_any_claim($claims, ['contact_path_clarity'], 50, false)) $score += 12;
        }

        if ($key === 'local_pro') {
            if (ho_salesportal_has_any_claim($claims, ['reviews_present','licenses_certifications_present','testimonials_present'], 55, true)) $score += 18;
            if (ho_salesportal_has_any_claim($claims, ['google_profile_url','website_url'], 55, true)) $score += 8;
        }

        if ($key === 'simple_service_card') {
            if (!ho_salesportal_has_any_claim($claims, ['website_url'], 55, true)) $score += 12;
            if (ho_salesportal_has_any_claim($claims, ['services_list_present'], 45, true)) $score += 10;
        }

        if ($key === 'neighborhood_handyman') {
            if ($businessTypeKey === 'handyman') $score += 25;
            if (ho_salesportal_has_any_claim($claims, ['phone_number','facebook_url'], 55, true)) $score += 8;
        }

        if ($key === 'repair_estimate') {
            if ($businessTypeKey === 'handyman') $score += 15;
            if (ho_salesportal_has_any_claim($claims, ['request_form_present','contact_form_present'], 45, false)) $score += 15;
        }

        if ($key === 'recurring_service') {
            if ($businessTypeKey === 'lawn_cleaning') $score += 25;
            if (ho_salesportal_has_any_claim($claims, ['service_area','services_list_present'], 55, true)) $score += 10;
        }

        $scores[] = [
            'option' => $option,
            'assignment_score' => $score,
        ];
    }

    usort($scores, static fn($a, $b) => $b['assignment_score'] <=> $a['assignment_score']);

    return array_slice($scores, 0, 3);
}

function ho_salesportal_clean_slug_piece(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    return substr($value, 0, 32);
}

function ho_salesportal_generate_address_suggestions(array $business, array $claims, string $businessTypeKey): array {
    $name = (string)($business['business_name_current'] ?? '');
    $city = (string)($business['location_city'] ?? '');
    $serviceArea = (string)($business['service_area_text'] ?? '');

    $claimName = ho_salesportal_claim_value_for_field($claims, 'business_name');
    $claimCity = ho_salesportal_claim_value_for_field($claims, 'city');
    $claimServiceArea = ho_salesportal_claim_value_for_field($claims, 'service_area');

    if ($claimName) $name = $claimName;
    if ($claimCity) $city = $claimCity;
    if (!$city && $claimServiceArea) $serviceArea = $claimServiceArea;

    $baseName = ho_salesportal_clean_slug_piece($name);
    $cityPiece = ho_salesportal_clean_slug_piece($city ?: $serviceArea);
    $typePiece = $businessTypeKey === 'lawn_cleaning' ? 'service' : $businessTypeKey;

    $suggestions = [];

    if ($baseName !== '') {
        $suggestions[] = [
            'option_type' => 'included_hoosier_subdomain',
            'address_value' => $baseName . '.hoosieronline.com',
            'display_label' => $name . ' starter address',
            'is_recommended' => 1,
            'sort_order' => 10,
            'notes' => 'Fast included starter address based on business name.'
        ];
    }

    if ($cityPiece !== '' && $typePiece !== '') {
        $suggestions[] = [
            'option_type' => 'local_service_hoosier_subdomain',
            'address_value' => $cityPiece . $typePiece . '.hoosieronline.com',
            'display_label' => 'Local service address',
            'is_recommended' => 0,
            'sort_order' => 20,
            'notes' => 'Local/service-style included address.'
        ];
    }

    if ($baseName !== '') {
        $suggestions[] = [
            'option_type' => 'custom_domain_idea',
            'address_value' => $baseName . '.com',
            'display_label' => 'Custom domain idea',
            'is_recommended' => 0,
            'sort_order' => 30,
            'notes' => 'Availability must be checked before customer-facing use.'
        ];
    }

    $suggestions[] = [
        'option_type' => 'undecided_help_me_choose',
        'address_value' => 'help-me-choose',
        'display_label' => 'Help me choose',
        'is_recommended' => 0,
        'sort_order' => 99,
        'notes' => 'Fallback option for customers who do not want to decide yet.'
    ];

    return $suggestions;
}

function ho_salesportal_upsert_address_suggestions(int $businessId, array $suggestions): array {
    if (!ho_salesportal_table_exists('preview_address_options')) return [];
    $pdo = ho_db();
    $created = [];

    foreach ($suggestions as $suggestion) {
        $stmt = $pdo->prepare("
            SELECT id FROM preview_address_options
            WHERE business_id = :business_id
              AND option_type = :option_type
              AND address_value = :address_value
            LIMIT 1
        ");
        $stmt->execute([
            'business_id' => $businessId,
            'option_type' => $suggestion['option_type'],
            'address_value' => $suggestion['address_value'],
        ]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $update = $pdo->prepare("
                UPDATE preview_address_options SET
                  display_label = :display_label,
                  is_recommended = :is_recommended,
                  sort_order = :sort_order,
                  notes = :notes,
                  availability_status = CASE
                    WHEN availability_status IN ('claimed','reserved','unavailable') THEN availability_status
                    ELSE 'suggested'
                  END
                WHERE id = :id
            ");
            $update->execute([
                'display_label' => $suggestion['display_label'],
                'is_recommended' => (int)$suggestion['is_recommended'],
                'sort_order' => (int)$suggestion['sort_order'],
                'notes' => $suggestion['notes'],
                'id' => $existingId,
            ]);
            $created[] = (int)$existingId;
        } else {
            $insert = $pdo->prepare("
                INSERT INTO preview_address_options
                  (business_id, option_type, address_value, display_label, availability_status, is_recommended, sort_order, notes)
                VALUES
                  (:business_id, :option_type, :address_value, :display_label, 'suggested', :is_recommended, :sort_order, :notes)
            ");
            $insert->execute([
                'business_id' => $businessId,
                'option_type' => $suggestion['option_type'],
                'address_value' => $suggestion['address_value'],
                'display_label' => $suggestion['display_label'],
                'is_recommended' => (int)$suggestion['is_recommended'],
                'sort_order' => (int)$suggestion['sort_order'],
                'notes' => $suggestion['notes'],
            ]);
            $created[] = (int)$pdo->lastInsertId();
        }
    }

    return $created;
}

function ho_salesportal_get_preview_address_options(int $businessId): array {
    if (!ho_salesportal_table_exists('preview_address_options')) return [];
    $stmt = ho_db()->prepare("
        SELECT * FROM preview_address_options
        WHERE business_id = :business_id
        ORDER BY is_recommended DESC, sort_order ASC, id ASC
    ");
    $stmt->execute(['business_id' => $businessId]);
    return $stmt->fetchAll();
}

function ho_salesportal_assign_preview_options(int $businessId, bool $write = true): array {
    if (!ho_salesportal_table_exists('preview_option_groups') || !ho_salesportal_table_exists('preview_design_options') || !ho_salesportal_table_exists('preview_address_options')) {
        return ho_salesportal_backend_unavailable_result($businessId, 'preview_option_assignment', 'Preview option assignment unavailable because v044 preview schema/seeds are not installed.');
    }

    $business = ho_salesportal_get_business_by_id($businessId);
    if (!$business) {
        return [
            'ok' => false,
            'business_id' => $businessId,
            'message' => 'Business not found.',
            'recommended_design_options' => [],
            'address_options' => [],
        ];
    }

    $claims = ho_salesportal_business_claims($businessId);
    $readiness = ho_salesportal_get_preview_readiness($businessId);
    if (!$readiness) {
        $readinessResult = ho_salesportal_evaluate_preview_readiness($businessId, true);
        $readiness = ho_salesportal_get_preview_readiness($businessId);
    }

    $readinessStatus = (string)($readiness['readiness_status'] ?? 'needs_more_research');
    $allowed = in_array($readinessStatus, ['ready','soft_ready','manual_review'], true);

    $businessTypeKey = ho_salesportal_business_type_key($business, $claims);
    $recommendedDesigns = $allowed ? ho_salesportal_choose_design_options($business, $claims, $businessTypeKey) : [];
    $addressSuggestions = $allowed ? ho_salesportal_generate_address_suggestions($business, $claims, $businessTypeKey) : [];

    if ($write && $allowed) {
        ho_salesportal_upsert_address_suggestions($businessId, $addressSuggestions);
    }

    $addressOptions = ho_salesportal_get_preview_address_options($businessId);

    return [
        'ok' => true,
        'business_id' => $businessId,
        'business_type_key' => $businessTypeKey,
        'readiness_status' => $readinessStatus,
        'assignment_allowed' => $allowed,
        'message' => $allowed ? 'Preview options assigned.' : 'Preview options not assigned because readiness is not sufficient.',
        'recommended_design_options' => $recommendedDesigns,
        'address_options' => $addressOptions,
    ];
}

function ho_salesportal_dashboard_assignment_summary(int $businessId): array {
    $assignment = ho_salesportal_assign_preview_options($businessId, false);
    $topDesign = $assignment['recommended_design_options'][0]['option'] ?? null;
    $topAddress = null;
    foreach ($assignment['address_options'] as $addr) {
        if ((int)($addr['is_recommended'] ?? 0) === 1) {
            $topAddress = $addr;
            break;
        }
    }
    if (!$topAddress && !empty($assignment['address_options'])) $topAddress = $assignment['address_options'][0];

    return [
        'allowed' => (bool)($assignment['assignment_allowed'] ?? false),
        'top_design_label' => $topDesign['option_label'] ?? null,
        'top_design_key' => $topDesign['option_key'] ?? null,
        'top_address' => $topAddress['address_value'] ?? null,
        'business_type_key' => $assignment['business_type_key'] ?? null,
    ];
}




if (!function_exists('ho_salesportal_db')) {
    function ho_salesportal_db(): PDO {
        return ho_db();
    }
}

if (!function_exists('ho_salesportal_normalize_identifier')) {
    function ho_salesportal_normalize_identifier(string $value, string $type = ''): string {
        $value = trim(strtolower($value));
        if ($value === '') return '';

        $type = strtolower(trim($type));

        if ($type === 'phone_number' || $type === 'phone') {
            $digits = preg_replace('/\D+/', '', $value) ?? '';
            if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
                $digits = substr($digits, 1);
            }
            return $digits;
        }

        if ($type === 'email_address' || $type === 'email') {
            return $value;
        }

        if (str_contains($value, '://')) {
            $parts = parse_url($value);
            $host = strtolower((string)($parts['host'] ?? ''));
            $path = strtolower(trim((string)($parts['path'] ?? ''), '/'));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            return trim($host . '/' . $path, '/');
        }

        if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}(\/.*)?$/i', $value)) {
            $value = preg_replace('/^www\./', '', $value) ?? $value;
            return trim($value, '/');
        }

        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}

if (!function_exists('ho_salesportal_collect_payload_identifiers')) {
    function ho_salesportal_collect_payload_identifiers(array $payload): array {
        $identifiers = [];

        $business = $payload['business'] ?? [];
        if (is_array($business)) {
            foreach (['business_slug', 'business_name_current', 'location_city', 'location_state'] as $key) {
                if (!empty($business[$key])) {
                    $identifiers[] = ['type' => $key, 'value' => (string)$business[$key]];
                }
            }
        }

        foreach (($payload['claims'] ?? []) as $claim) {
            if (!is_array($claim)) continue;
            $field = (string)($claim['field_key'] ?? '');
            $value = (string)($claim['normalized_value'] ?? $claim['claim_value'] ?? '');
            if ($field !== '' && trim($value) !== '') {
                $identifiers[] = ['type' => $field, 'value' => $value];
            }
        }

        foreach (($payload['evidence_sources'] ?? []) as $source) {
            if (!is_array($source)) continue;
            $type = (string)($source['source_type'] ?? '');
            foreach (['source_url', 'raw_excerpt', 'source_title'] as $key) {
                if (!empty($source[$key])) {
                    $identifiers[] = ['type' => $type, 'value' => (string)$source[$key]];
                }
            }
        }

        return $identifiers;
    }
}

if (!function_exists('ho_salesportal_duplicate_check_payload')) {
    function ho_salesportal_duplicate_check_payload(array $payload, ?PDO $pdo = null): array {
        $pdo = $pdo ?: ho_db();

        $business = $payload['business'] ?? [];
        if (!is_array($business)) $business = [];

        $slug = trim((string)($business['business_slug'] ?? ''));
        $name = trim((string)($business['business_name_current'] ?? ''));
        $city = trim((string)($business['location_city'] ?? ''));

        $exact = [];
        $possible = [];

        if ($slug !== '') {
            $stmt = $pdo->prepare("SELECT id, business_name_current, business_slug, location_city FROM businesses WHERE business_slug = ? LIMIT 5");
            $stmt->execute([$slug]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $exact[] = ['reason' => 'same_slug', 'business' => $row];
            }
        }

        if ($name !== '') {
            $normalizedName = ho_salesportal_normalize_identifier($name, 'business_name');
            $stmt = $pdo->query("SELECT id, business_name_current, business_slug, location_city FROM businesses");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rowName = ho_salesportal_normalize_identifier((string)($row['business_name_current'] ?? ''), 'business_name');
                $rowCity = trim(strtolower((string)($row['location_city'] ?? '')));

                if ($rowName !== '' && $rowName === $normalizedName) {
                    if ($city === '' || $rowCity === strtolower($city)) {
                        $exact[] = ['reason' => 'same_name_city', 'business' => $row];
                    } else {
                        $possible[] = ['reason' => 'same_name_different_city', 'business' => $row];
                    }
                } elseif ($rowName !== '' && $normalizedName !== '') {
                    similar_text($rowName, $normalizedName, $percent);
                    if ($percent >= 88 && ($city === '' || $rowCity === strtolower($city))) {
                        $possible[] = ['reason' => 'similar_name_city', 'business' => $row, 'similarity' => round($percent, 1)];
                    }
                }
            }
        }

        $payloadIds = ho_salesportal_collect_payload_identifiers($payload);
        $interesting = [
            'website_url', 'facebook_url', 'google_profile_url', 'phone_number', 'email_address',
            'website', 'facebook', 'google_business_profile', 'email', 'directory'
        ];

        if ($payloadIds) {
            $stmt = $pdo->query("SELECT bc.business_id, bc.field_key, bc.normalized_value, bc.claim_value, b.business_name_current, b.business_slug, b.location_city
                                 FROM business_claims bc
                                 JOIN businesses b ON b.id = bc.business_id
                                 WHERE bc.claim_status <> 'archived'");
            $claimRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($payloadIds as $pid) {
                $ptype = strtolower((string)($pid['type'] ?? ''));
                $pval = (string)($pid['value'] ?? '');
                if (trim($pval) === '') continue;

                $pnorm = ho_salesportal_normalize_identifier($pval, $ptype);
                if ($pnorm === '' || strlen($pnorm) < 4) continue;

                foreach ($claimRows as $row) {
                    $field = strtolower((string)($row['field_key'] ?? ''));
                    $claimValue = (string)($row['normalized_value'] ?? $row['claim_value'] ?? '');
                    $rnorm = ho_salesportal_normalize_identifier($claimValue, $field);
                    if ($rnorm === '' || strlen($rnorm) < 4) continue;

                    $strongType = in_array($field, ['website_url','facebook_url','google_profile_url','phone_number','email_address'], true)
                        || in_array($ptype, $interesting, true);

                    if ($strongType && $pnorm === $rnorm) {
                        $exact[] = [
                            'reason' => 'same_identifier_' . ($field ?: $ptype),
                            'business' => [
                                'id' => $row['business_id'],
                                'business_name_current' => $row['business_name_current'],
                                'business_slug' => $row['business_slug'],
                                'location_city' => $row['location_city'],
                            ]
                        ];
                    }
                }
            }
        }

        $dedupe = function(array $matches): array {
            $seen = [];
            $out = [];
            foreach ($matches as $match) {
                $id = (string)($match['business']['id'] ?? '');
                $reason = (string)($match['reason'] ?? '');
                $key = $reason . ':' . $id;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $out[] = $match;
            }
            return $out;
        };

        $exact = $dedupe($exact);
        $possible = $dedupe($possible);

        return [
            'has_exact_duplicate' => count($exact) > 0,
            'has_possible_duplicate' => count($possible) > 0,
            'exact_matches' => $exact,
            'possible_matches' => $possible,
        ];
    }
}

if (!function_exists('ho_salesportal_known_business_exclusions')) {
    function ho_salesportal_known_business_exclusions(string $category = '', string $targetArea = '', int $limit = 100): array {
        $pdo = ho_db();

        $rows = [];
        try {
            $stmt = $pdo->query("SELECT id, business_name_current, business_slug, business_type, location_city, location_state, service_area_text FROM businesses ORDER BY id DESC LIMIT 500");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        $categoryNorm = strtolower(trim($category));
        $areaNorm = strtolower(trim($targetArea));

        $filtered = [];
        foreach ($rows as $row) {
            $type = strtolower((string)($row['business_type'] ?? ''));
            $city = strtolower((string)($row['location_city'] ?? ''));
            $service = strtolower((string)($row['service_area_text'] ?? ''));

            $categoryMatch = $categoryNorm === '' || $type === '' || $type === $categoryNorm || str_contains($type, $categoryNorm) || str_contains($categoryNorm, $type);
            $areaMatch = $areaNorm === '' || ($city !== '' && str_contains($areaNorm, $city)) || ($service !== '' && (str_contains($service, $areaNorm) || str_contains($areaNorm, $service)));

            if ($categoryMatch || $areaMatch || count($rows) <= 100) {
                $filtered[] = $row;
            }
            if (count($filtered) >= $limit) break;
        }

        if (!$filtered) {
            $filtered = array_slice($rows, 0, $limit);
        }

        $ids = array_map(static fn($r) => (int)$r['id'], $filtered);
        $claimsByBusiness = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT business_id, field_key, normalized_value, claim_value
                                   FROM business_claims
                                   WHERE business_id IN ($placeholders)
                                   AND field_key IN ('website_url','facebook_url','google_profile_url','phone_number','email_address','address')
                                   AND claim_status <> 'archived'");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $claim) {
                $bid = (int)$claim['business_id'];
                $value = trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
                if ($value !== '') {
                    $claimsByBusiness[$bid][] = $value;
                }
            }
        }

        $out = [];
        foreach ($filtered as $row) {
            $bid = (int)$row['id'];
            $out[] = [
                'business_name' => (string)($row['business_name_current'] ?? ''),
                'business_slug' => (string)($row['business_slug'] ?? ''),
                'city' => (string)($row['location_city'] ?? ''),
                'state' => (string)($row['location_state'] ?? ''),
                'identifiers' => array_values(array_unique(array_slice($claimsByBusiness[$bid] ?? [], 0, 6))),
            ];
        }

        return $out;
    }
}

