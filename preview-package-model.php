<?php
/**
 * Hoosier Online Preview Package Model
 * v107 — Preview Package Contract + Registries
 *
 * Purpose:
 * Defines the locked product contract for moving Contact Ready businesses into
 * preview-package manufacturing before Marketing Desk.
 *
 * This file is intentionally read-only/config-like for v107:
 * - no schema changes
 * - no domain API checks
 * - no outreach
 * - no generated customer pages yet
 */

declare(strict_types=1);

const HO_PREVIEW_PACKAGE_MODEL_VERSION = 'HO-PREVIEW-PACKAGE-107';

/**
 * Package statuses for the downstream package system.
 */
function ho_preview_package_statuses(): array {
    return [
        'contact_ready' => [
            'label' => 'Contact Ready',
            'meaning' => 'Upstream record has a usable contact path or enough direction to package.',
            'is_terminal' => false,
        ],
        'package_needed' => [
            'label' => 'Package Needed',
            'meaning' => 'Record is eligible for preview package generation.',
            'is_terminal' => false,
        ],
        'package_drafted' => [
            'label' => 'Package Drafted',
            'meaning' => 'Design/logo/report/domain candidates exist, but domains are not verified.',
            'is_terminal' => false,
        ],
        'domain_check_needed' => [
            'label' => 'Domain Check Needed',
            'meaning' => 'Package has candidate domains but not ten proven available domains.',
            'is_terminal' => false,
        ],
        'package_ready' => [
            'label' => 'Package Ready',
            'meaning' => 'Package has passed validation and can be materialized into static dashboard/report assets.',
            'is_terminal' => false,
        ],
        'ready_for_marketing' => [
            'label' => 'Ready For Marketing Desk',
            'meaning' => 'Design dashboard/report/hotlink assets are ready for Marketing Desk. No sending has occurred.',
            'is_terminal' => false,
        ],
        'manual_package_review' => [
            'label' => 'Manual Package Review',
            'meaning' => 'Package has a warning, collision, missing data, or uncertain fit.',
            'is_terminal' => false,
        ],
        'package_blocked' => [
            'label' => 'Package Blocked',
            'meaning' => 'Package should not continue without being reopened.',
            'is_terminal' => true,
        ],
    ];
}

/**
 * Ten locked website design styles.
 * These are intentionally stable. GPT should personalize from these options,
 * not invent new designs per business.
 */
function ho_preview_web_design_registry(): array {
    return [
        [
            'template_key' => 'clean_local_service',
            'display_name' => 'Clean Local Service',
            'best_for' => 'General local service businesses that need trust and contact clarity.',
            'bad_for' => 'Highly visual brands needing portfolio-first presentation.',
            'default_cta' => 'Request a Quote',
            'sections' => ['hero', 'services', 'why_hire_us', 'recent_work', 'service_area', 'contact'],
            'visual_tone' => 'clean, practical, trustworthy, local',
            'skeleton_notes' => 'Use as the safest default for most Indiana operators.',
        ],
        [
            'template_key' => 'bold_contractor',
            'display_name' => 'Bold Contractor',
            'best_for' => 'Contractors, handyman, pressure washing, property maintenance, repair, exterior services.',
            'bad_for' => 'Soft personal services or premium creative portfolios.',
            'default_cta' => 'Get An Estimate',
            'sections' => ['hero', 'proof_points', 'services', 'project_types', 'reviews', 'contact'],
            'visual_tone' => 'strong, direct, job-ready',
            'skeleton_notes' => 'Heavier headline, larger CTA, service credibility first.',
        ],
        [
            'template_key' => 'friendly_neighborhood',
            'display_name' => 'Friendly Neighborhood',
            'best_for' => 'Cleaners, pet services, family/local operators, personal services.',
            'bad_for' => 'Emergency trades or aggressive contractor positioning.',
            'default_cta' => 'Ask About Availability',
            'sections' => ['hero', 'about', 'services', 'what_to_expect', 'local_area', 'contact'],
            'visual_tone' => 'warm, approachable, neighborly',
            'skeleton_notes' => 'Use softer copy and reassurance-first layout.',
        ],
        [
            'template_key' => 'premium_portfolio',
            'display_name' => 'Premium Portfolio',
            'best_for' => 'Photographers, event services, premium visual work, specialty services.',
            'bad_for' => 'Businesses with no photos or visual proof.',
            'default_cta' => 'View Work',
            'sections' => ['hero', 'portfolio', 'featured_services', 'process', 'inquiry', 'contact'],
            'visual_tone' => 'polished, visual, selective',
            'skeleton_notes' => 'Image-forward once assets exist; use placeholders before final photos.',
        ],
        [
            'template_key' => 'emergency_fast_response',
            'display_name' => 'Emergency / Fast Response',
            'best_for' => 'Urgent services, repair, storm cleanup, junk removal, mitigation-style work.',
            'bad_for' => 'Non-urgent or appointment-only businesses.',
            'default_cta' => 'Call Now',
            'sections' => ['hero', 'urgent_services', 'response_area', 'proof', 'call_box'],
            'visual_tone' => 'fast, clear, high-contrast, action-oriented',
            'skeleton_notes' => 'Use only when fast response is supported by facts.',
        ],
        [
            'template_key' => 'simple_quote_page',
            'display_name' => 'Simple Quote Page',
            'best_for' => 'Thin web presence, no website, quote-driven service businesses.',
            'bad_for' => 'Businesses needing deep content or many service categories.',
            'default_cta' => 'Request A Quote',
            'sections' => ['hero', 'services', 'quote_form', 'service_area', 'contact'],
            'visual_tone' => 'simple, low-friction, conversion-focused',
            'skeleton_notes' => 'Best for getting from unknown visitor to inquiry quickly.',
        ],
        [
            'template_key' => 'family_owned_traditional',
            'display_name' => 'Family-Owned Traditional',
            'best_for' => 'Established small operators, family-owned language, rural/suburban services.',
            'bad_for' => 'Modern creative brands or high-urgency services.',
            'default_cta' => 'Contact Us',
            'sections' => ['hero', 'local_story', 'services', 'values', 'service_area', 'contact'],
            'visual_tone' => 'traditional, grounded, sincere',
            'skeleton_notes' => 'Use respectful local-business framing without inventing family ownership.',
        ],
        [
            'template_key' => 'modern_minimal',
            'display_name' => 'Modern Minimal',
            'best_for' => 'Photographers, consultants, coaches, specialty operators, clean brands.',
            'bad_for' => 'Businesses needing lots of explanation or heavy proof.',
            'default_cta' => 'Start Here',
            'sections' => ['hero', 'offer', 'selected_work', 'process', 'contact'],
            'visual_tone' => 'spacious, clean, restrained',
            'skeleton_notes' => 'Use only when minimalism helps clarity, not when it hides missing content.',
        ],
        [
            'template_key' => 'before_after_gallery',
            'display_name' => 'Before & After Gallery',
            'best_for' => 'Pressure washing, landscaping, cleaning, remodeling, outdoor services, visual transformations.',
            'bad_for' => 'Businesses without visual proof or before/after work.',
            'default_cta' => 'See What We Can Do',
            'sections' => ['hero', 'before_after', 'services', 'proof', 'quote_request'],
            'visual_tone' => 'visual proof, transformation, practical',
            'skeleton_notes' => 'Can use placeholder gallery directions until real photos are supplied.',
        ],
        [
            'template_key' => 'seasonal_service',
            'display_name' => 'Seasonal Service',
            'best_for' => 'Lawn care, snow removal, holiday/event, seasonal maintenance, recurring seasonal work.',
            'bad_for' => 'Year-round non-seasonal businesses unless seasonality is central.',
            'default_cta' => 'Get On The Schedule',
            'sections' => ['hero', 'seasonal_offers', 'recurring_service', 'service_area', 'contact'],
            'visual_tone' => 'timely, useful, schedule-focused',
            'skeleton_notes' => 'Use for businesses where timing and recurring service matter.',
        ],
    ];
}

/**
 * Ten locked browser-font identity directions.
 * These are mockups/directions only, not official logos.
 */
function ho_preview_logo_direction_registry(): array {
    return [
        [
            'logo_key' => 'clean_wordmark',
            'display_name' => 'Clean Wordmark',
            'font_stack' => 'Arial, Helvetica, sans-serif',
            'layout' => 'simple wordmark',
            'best_for' => 'Most local service businesses needing clarity over decoration.',
            'customer_label' => 'Clean identity direction',
        ],
        [
            'logo_key' => 'bold_contractor',
            'display_name' => 'Bold Contractor',
            'font_stack' => 'Impact, Haettenschweiler, Arial Narrow Bold, sans-serif',
            'layout' => 'heavy wordmark with block initials',
            'best_for' => 'Contractors, repair, pressure washing, property services.',
            'customer_label' => 'Bold service-business direction',
        ],
        [
            'logo_key' => 'heritage_serif',
            'display_name' => 'Heritage Serif',
            'font_stack' => 'Georgia, Times New Roman, serif',
            'layout' => 'serif wordmark with small descriptor',
            'best_for' => 'Traditional local businesses, family-style operators, established services.',
            'customer_label' => 'Traditional identity direction',
        ],
        [
            'logo_key' => 'initials_badge',
            'display_name' => 'Initials Badge',
            'font_stack' => 'Arial Black, Arial, sans-serif',
            'layout' => 'initials mark plus business name',
            'best_for' => 'Names that abbreviate cleanly.',
            'customer_label' => 'Initials badge direction',
        ],
        [
            'logo_key' => 'local_stamp',
            'display_name' => 'Local Stamp',
            'font_stack' => 'Trebuchet MS, Arial, sans-serif',
            'layout' => 'stamp-style text badge',
            'best_for' => 'Local operators where community/local presence matters.',
            'customer_label' => 'Local stamp direction',
        ],
        [
            'logo_key' => 'modern_minimal',
            'display_name' => 'Modern Minimal',
            'font_stack' => 'system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif',
            'layout' => 'minimal wordmark with small line mark',
            'best_for' => 'Creative, personal, coaching, photography, and cleaner modern services.',
            'customer_label' => 'Modern identity direction',
        ],
        [
            'logo_key' => 'friendly_rounded',
            'display_name' => 'Friendly Rounded',
            'font_stack' => 'Verdana, Geneva, sans-serif',
            'layout' => 'rounded wordmark with soft badge',
            'best_for' => 'Cleaners, pet services, child/family-friendly service businesses.',
            'customer_label' => 'Friendly identity direction',
        ],
        [
            'logo_key' => 'premium_script_accent',
            'display_name' => 'Premium Script Accent',
            'font_stack' => 'Georgia, Times New Roman, serif',
            'layout' => 'serif wordmark with italic/script-like accent using browser fonts',
            'best_for' => 'Photography, events, boutique services, premium personal work.',
            'customer_label' => 'Premium identity direction',
        ],
        [
            'logo_key' => 'utility_block',
            'display_name' => 'Utility Block',
            'font_stack' => 'Arial Narrow, Arial, sans-serif',
            'layout' => 'condensed uppercase block wordmark',
            'best_for' => 'Practical service operators, maintenance, handyman, industrial-feeling work.',
            'customer_label' => 'Utility identity direction',
        ],
        [
            'logo_key' => 'outdoor_service_mark',
            'display_name' => 'Outdoor Service Mark',
            'font_stack' => 'Trebuchet MS, Arial, sans-serif',
            'layout' => 'outdoor/local service wordmark with initials lockup',
            'best_for' => 'Landscaping, lawn care, tree work, snow, exterior maintenance.',
            'customer_label' => 'Outdoor service direction',
        ],
    ];
}

/**
 * Domain candidate rules. Availability is not proven here.
 * v108/v109 should generate candidates then verify availability by copy/paste bulk GPT prompt or later API.
 */
function ho_preview_domain_rules(): array {
    return [
        'preferred_tlds' => ['.com', '.net', '.co'],
        'preferred_count_verified' => 10,
        'candidate_count_before_check' => 20,
        'avoid' => ['hyphens unless necessary', 'weird spellings', 'overly broad geography', 'trademark confusion', 'domains longer than needed'],
        'patterns' => [
            '{brand}.com',
            '{brand}{service}.com',
            '{brand}{city}.com',
            '{brand}in.com',
            '{brand}indiana.com',
            '{brand}services.com',
            'hire{brand}.com',
            '{brand}{category}.com',
        ],
        'status_flow' => [
            'domain_candidates',
            'domain_check_needed',
            'verified_domain_options',
        ],
    ];
}

/**
 * Short campaign hotlink slug rules.
 */
function ho_preview_slug_rules(): array {
    return [
        'path_prefix' => '/go/',
        'design_path_prefix' => '/design/',
        'report_path_prefix' => '/report/',
        'rules' => [
            'shortest recognizable safe slug',
            'lowercase',
            'letters and numbers only when possible',
            'avoid hyphens for hotlink slugs',
            'business-distinctive word first',
            'city only when needed for collision clarity',
            'numbers only as last resort',
            'must be unique before package_ready',
        ],
        'fallback_order' => [
            'distinctive_brand_word',
            'compressed_two_word_brand',
            'brand_plus_service',
            'brand_plus_city',
            'brand_plus_short_number',
        ],
    ];
}

/**
 * Sales report block registry skeleton.
 * v107 defines the keys. Later versions can fill in final copy blocks.
 */
function ho_preview_sales_report_block_registry(): array {
    return [
        'strengths' => [
            'existing_website',
            'google_profile_found',
            'facebook_active',
            'phone_visible',
            'email_visible',
            'photos_present',
            'reviews_present',
            'clear_service_category',
            'local_identity_clear',
            'contact_form_exists',
        ],
        'weaknesses' => [
            'no_single_customer_destination',
            'unclear_contact_path',
            'weak_service_list',
            'few_or_no_photos',
            'reviews_not_prominent',
            'stale_or_unclear_activity',
            'website_present_but_confusing',
            'facebook_only_presence',
            'no_quote_request_path',
            'domain_or_brand_confusion',
        ],
        'recommendations' => [
            'simple_front_door',
            'website_fix_preview',
            'quote_path_cleanup',
            'visual_proof_upgrade',
            'trust_builder_page',
            'seasonal_service_page',
            'portfolio_first_page',
            'contact_path_cleanup',
        ],
    ];
}

/**
 * Preview package JSON contract skeleton.
 */
function ho_preview_package_contract(): array {
    return [
        'package_batch' => [
            'batch_type' => 'contact_ready_preview_package',
            'created_for' => 'Hoosier Online Preview Package Workbench',
        ],
        'packages' => [
            [
                'business_id' => 0,
                'business_slug' => 'existing-business-slug',
                'business_name' => 'Business Name',
                'package_status' => 'domain_check_needed',
                'short_slug' => 'shortslug',
                'hotlink_path' => '/go/shortslug',
                'design_dashboard_path' => '/design/shortslug',
                'sales_report_path' => '/report/shortslug',
                'recommended_template_key' => 'clean_local_service',
                'web_design_options' => [
                    [
                        'rank' => 1,
                        'template_key' => 'clean_local_service',
                        'display_name' => 'Clean Local Service',
                        'personalized_headline' => 'Personalized headline using stored business facts',
                        'reason' => 'Why this locked template fits this business.',
                    ],
                ],
                'logo_options' => [
                    [
                        'rank' => 1,
                        'logo_key' => 'clean_wordmark',
                        'display_name' => 'Clean Wordmark',
                        'mockup_text' => 'Business Name',
                        'mark_text' => 'BN',
                        'font_stack' => 'Arial, Helvetica, sans-serif',
                        'color_pair' => 'deep green / warm cream',
                        'reason' => 'Why this identity direction fits.',
                    ],
                ],
                'domain_candidates' => [
                    [
                        'rank' => 1,
                        'domain' => 'examplebusiness.com',
                        'reason' => 'Exact-match .com candidate. Availability not verified in package generation.',
                    ],
                ],
                'verified_domain_options' => [],
                'sales_report' => [
                    'headline' => 'Online Front Door Snapshot for Business Name',
                    'strength_blocks' => ['local_identity_clear'],
                    'weakness_blocks' => ['unclear_contact_path'],
                    'recommendation_blocks' => ['simple_front_door'],
                    'personalized_summary' => 'Short personalized report summary.',
                ],
                'warnings' => [],
                'next_step' => 'check_domain_availability',
            ],
        ],
    ];
}

/**
 * Package readiness criteria.
 */
function ho_preview_package_readiness_criteria(): array {
    return [
        'package_ready' => [
            'short_slug_unique' => true,
            'web_design_options_count' => 10,
            'logo_options_count' => 10,
            'verified_domain_options_count' => 10,
            'sales_report_present' => true,
            'contact_method_exists' => true,
            'warnings_empty' => true,
        ],
        'ready_for_marketing' => [
            'package_ready' => true,
            'hotlink_path_present' => true,
            'design_dashboard_path_present' => true,
            'sales_report_path_present' => true,
        ],
    ];
}

function ho_preview_package_registry_summary(): array {
    return [
        'version' => HO_PREVIEW_PACKAGE_MODEL_VERSION,
        'web_design_count' => count(ho_preview_web_design_registry()),
        'logo_direction_count' => count(ho_preview_logo_direction_registry()),
        'domain_candidate_target' => ho_preview_domain_rules()['candidate_count_before_check'],
        'verified_domain_target' => ho_preview_domain_rules()['preferred_count_verified'],
        'report_strength_blocks' => count(ho_preview_sales_report_block_registry()['strengths']),
        'report_weakness_blocks' => count(ho_preview_sales_report_block_registry()['weaknesses']),
        'report_recommendation_blocks' => count(ho_preview_sales_report_block_registry()['recommendations']),
    ];
}

/**
 * v108 helper: infer preview package status from latest claims.
 * Without a dedicated schema, package status is stored as/importable claim data later.
 */
function ho_preview_package_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) {
            return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
        }
    }
    return '';
}

function ho_preview_package_status_for_business(array $business): string {
    $status = strtolower(ho_preview_package_claim_value($business, 'package_status'));
    if ($status !== '') return $status;

    $warning = strtolower(ho_preview_package_claim_value($business, 'package_warning'));
    if ($warning !== '') return 'manual_package_review';

    return 'package_needed';
}

function ho_preview_package_status_label(string $status): string {
    $statuses = ho_preview_package_statuses();
    return $statuses[$status]['label'] ?? ucwords(str_replace('_', ' ', $status));
}

function ho_preview_package_short_business_summary(array $business): array {
    return [
        'business_id' => (int)($business['id'] ?? 0),
        'business_slug' => (string)($business['business_slug'] ?? ''),
        'business_name' => (string)($business['business_name_current'] ?? ''),
        'business_type' => (string)($business['business_type'] ?? ''),
        'city' => (string)($business['location_city'] ?? ''),
        'state' => (string)($business['location_state'] ?? 'IN'),
        'service_area' => (string)($business['service_area_text'] ?? 'Indiana'),
        'website_url' => (string)($business['website_url'] ?? ''),
        'phone_number' => ho_preview_package_claim_value($business, 'phone_number'),
        'email_address' => ho_preview_package_claim_value($business, 'email_address'),
        'google_profile_url' => ho_preview_package_claim_value($business, 'google_profile_url'),
        'facebook_url' => ho_preview_package_claim_value($business, 'facebook_url'),
        'contact_readiness' => ho_preview_package_claim_value($business, 'contact_readiness'),
        'best_contact_method' => ho_preview_package_claim_value($business, 'best_contact_method'),
        'primary_sales_angle' => ho_preview_package_claim_value($business, 'primary_sales_angle'),
        'recommended_design' => ho_preview_package_claim_value($business, 'recommended_design'),
        'marketing_clearance_status' => ho_preview_package_claim_value($business, 'marketing_clearance_status'),
        'marketing_clearance_score' => ho_preview_package_claim_value($business, 'marketing_clearance_score'),
    ];
}

function ho_preview_package_generation_prompt(array $contactReadyBusinesses): string {
    $businesses = array_map('ho_preview_package_short_business_summary', $contactReadyBusinesses);

    $promptPayload = [
        'task' => 'generate_preview_packages',
        'input_status' => 'contact_ready',
        'businesses' => $businesses,
        'locked_web_design_registry' => ho_preview_web_design_registry(),
        'locked_identity_direction_registry' => ho_preview_logo_direction_registry(),
        'slug_rules' => ho_preview_slug_rules(),
        'domain_candidate_rules' => ho_preview_domain_rules(),
        'sales_report_block_registry' => ho_preview_sales_report_block_registry(),
        'package_contract' => ho_preview_package_contract(),
        'readiness_criteria' => ho_preview_package_readiness_criteria(),
    ];

    return "You are creating Hoosier Online Preview Packages for Contact Ready businesses.\n\n"
        . "Goal:\nFor each business, manufacture a personalized preview package for the future Design Dashboard and Sales Report workflow.\n\n"
        . "Important product rules:\n"
        . "- Return ONLY valid JSON.\n"
        . "- Do not include markdown.\n"
        . "- Do not send outreach.\n"
        . "- Do not claim domains are available.\n"
        . "- Generate domain_candidates only; availability will be verified later.\n"
        . "- Use the locked 10 website design styles. Do not invent new design styles.\n"
        . "- Use the locked 10 browser-font identity/logo direction styles. Do not invent new logo styles.\n"
        . "- Select and personalize all 10 website design options for each business.\n"
        . "- Select and personalize all 10 identity/logo direction options for each business.\n"
        . "- Generate the shortest safe hotlink slug candidate for each business.\n"
        . "- Generate 20 domain candidates for each business.\n"
        . "- Generate a personalized sales report using the approved block keys plus a short personalized summary.\n"
        . "- Set package_status to domain_check_needed unless blocked or manual_package_review is clearly required.\n"
        . "- Use identity mockup / identity direction language; do not call browser-font mockups official logos.\n"
        . "- Indiana relevance and local-service fit are already upstream-cleared unless a warning is obvious.\n\n"
        . "Output shape:\n"
        . "- Use package_batch.batch_type = contact_ready_preview_package.\n"
        . "- Return packages[].\n"
        . "- Each package must include business_id, business_slug, business_name, package_status, short_slug, hotlink_path, design_dashboard_path, sales_report_path, recommended_template_key, web_design_options, logo_options, domain_candidates, sales_report, warnings, and next_step.\n\n"
        . "Input data and registries:\n"
        . json_encode($promptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}


/**
 * v109 helper: create a claim row shape for package metadata.
 */
function ho_preview_package_claim(string $fieldKey, $value, string $note = 'Preview package result imported.'): array {
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES);
    }
    $value = trim((string)$value);

    return [
        'field_key' => $fieldKey,
        'claim_value' => $value,
        'normalized_value' => $value,
        'confidence_level' => 'inferred',
        'confidence_score' => 80,
        'claim_status' => 'active',
        'source_type' => 'manual_observation',
        'source_url' => '',
        'source_label' => 'Preview package workbench',
        'evidence_note' => $note,
        'supports_me_category' => 'fix_me',
        'supports_requirement_key' => 'fix_me.customer_path_mess',
        'evidence_source_index' => 0,
    ];
}

/**
 * v109 helper: convert a preview package result to the existing business payload shape.
 * No schema change: package metadata is stored as claims.
 */
function ho_preview_package_to_business_payload(array $package): array {
    $businessId = (int)($package['business_id'] ?? 0);
    $businessSlug = trim((string)($package['business_slug'] ?? ''));
    $businessName = trim((string)($package['business_name'] ?? ''));

    if ($businessName === '' && $businessSlug !== '') {
        $businessName = ucwords(str_replace('-', ' ', $businessSlug));
    }

    if ($businessName === '' && $businessSlug === '') {
        throw new RuntimeException('Preview package is missing business_id/business_slug/business_name.');
    }

    $business = [
        'business_slug' => $businessSlug !== '' ? $businessSlug : ho_salesportal_dashboard_slug($businessName),
        'business_name_current' => $businessName,
        'business_type' => (string)($package['business_type'] ?? 'local_service'),
        'location_city' => (string)($package['city'] ?? ''),
        'location_state' => (string)($package['state'] ?? 'IN'),
        'service_area_text' => (string)($package['service_area'] ?? 'Indiana'),
    ];
    if ($businessId > 0) {
        $business['id'] = $businessId;
    }

    $warnings = $package['warnings'] ?? [];
    if (!is_array($warnings)) $warnings = [$warnings];

    $domainCandidates = $package['domain_candidates'] ?? [];
    if (!is_array($domainCandidates)) $domainCandidates = [];

    $verifiedDomains = $package['verified_domain_options'] ?? [];
    if (!is_array($verifiedDomains)) $verifiedDomains = [];

    $status = trim((string)($package['package_status'] ?? 'domain_check_needed'));
    if ($status === '') $status = 'domain_check_needed';

    if (count($verifiedDomains) >= 10) {
        $status = 'package_ready';
    } elseif (count($domainCandidates) > 0 && !in_array($status, ['manual_package_review','package_blocked'], true)) {
        $status = 'domain_check_needed';
    } elseif (!in_array($status, ['manual_package_review','package_blocked'], true)) {
        $status = 'package_drafted';
    }

    $claims = [
        ho_preview_package_claim('package_status', $status, 'Preview package stage/status.'),
        ho_preview_package_claim('short_slug', $package['short_slug'] ?? '', 'Short campaign hotlink slug candidate.'),
        ho_preview_package_claim('hotlink_path', $package['hotlink_path'] ?? '', 'Campaign hotlink path candidate.'),
        ho_preview_package_claim('design_dashboard_path', $package['design_dashboard_path'] ?? '', 'Future design dashboard path.'),
        ho_preview_package_claim('sales_report_path', $package['sales_report_path'] ?? '', 'Future sales report path.'),
        ho_preview_package_claim('recommended_template_key', $package['recommended_template_key'] ?? '', 'Recommended locked website design style.'),
        ho_preview_package_claim('web_design_options_json', $package['web_design_options'] ?? [], 'All ten personalized locked website design options.'),
        ho_preview_package_claim('logo_options_json', $package['logo_options'] ?? [], 'All ten personalized browser-font identity directions.'),
        ho_preview_package_claim('domain_candidates_json', $domainCandidates, 'Generated domain candidates; availability not proven.'),
        ho_preview_package_claim('verified_domain_options_json', $verifiedDomains, 'Verified available domain options.'),
        ho_preview_package_claim('sales_report_json', $package['sales_report'] ?? [], 'Personalized sales report draft.'),
        ho_preview_package_claim('package_warnings_json', $warnings, 'Preview package warnings, if any.'),
        ho_preview_package_claim('package_next_step', $package['next_step'] ?? ($status === 'domain_check_needed' ? 'check_domain_availability' : 'review_package'), 'Next package workflow step.'),
    ];

    $claims = array_values(array_filter($claims, static fn($claim) => trim((string)($claim['claim_value'] ?? '')) !== ''));

    $evidence = [[
        'source_type' => 'manual_observation',
        'source_url' => '',
        'source_title' => 'Preview package workbench result',
        'capture_status' => 'manual',
        'raw_excerpt' => json_encode($package, JSON_UNESCAPED_SLASHES),
        'notes' => 'Preview package metadata imported through workbench. No outreach or domain purchase occurred.'
    ]];

    return [
        'business' => $business,
        'evidence_sources' => $evidence,
        'claims' => $claims,
        'marketing_clearance' => [
            'marketing_clearance_status' => 'contact_ready',
            'marketing_clearance_score' => 75,
            'recommended_package' => 'standard',
            'recommended_design' => (string)($package['recommended_template_key'] ?? 'clean_local_service'),
            'reason' => 'Preview package metadata imported and staged for domain verification.'
        ],
        'notes' => ['Preview package imported. Status: ' . $status],
    ];
}

/**
 * v109 helper: parse package-generation JSON into payloads.
 */
function ho_preview_package_payloads_from_input(array $decoded): ?array {
    $batchType = strtolower(trim((string)($decoded['package_batch']['batch_type'] ?? $decoded['batch_type'] ?? '')));
    $hasPackages = isset($decoded['packages']) && is_array($decoded['packages']);

    if ($batchType !== 'contact_ready_preview_package' && !$hasPackages) {
        return null;
    }

    if (!$hasPackages) {
        throw new RuntimeException('Preview package input is missing packages[].');
    }

    $payloads = [];
    foreach ($decoded['packages'] as $package) {
        if (!is_array($package)) continue;
        $payloads[] = ho_preview_package_to_business_payload($package);
    }

    return $payloads;
}

/**
 * v109 helper: domain-check prompt for staged packages.
 */
function ho_preview_domain_check_prompt(array $packages): string {
    $items = [];

    foreach ($packages as $business) {
        $domainJson = ho_preview_package_claim_value($business, 'domain_candidates_json');
        $domains = json_decode($domainJson, true);
        if (!is_array($domains)) $domains = [];

        $items[] = [
            'business_id' => (int)($business['id'] ?? 0),
            'business_slug' => (string)($business['business_slug'] ?? ''),
            'business_name' => (string)($business['business_name_current'] ?? ''),
            'short_slug' => ho_preview_package_claim_value($business, 'short_slug'),
            'domain_candidates' => $domains,
        ];
    }

    $payload = [
        'task' => 'verify_preview_package_domain_candidates',
        'rule' => 'Return exactly 10 verified available domains per business where possible. Do not invent availability. If availability cannot be checked, set package_status=domain_check_manual_review.',
        'businesses' => $items,
        'expected_output' => [
            'package_batch' => [
                'batch_type' => 'domain_availability_verification'
            ],
            'domain_results' => [
                [
                    'business_id' => 0,
                    'business_slug' => 'existing-business-slug',
                    'package_status' => 'package_ready',
                    'verified_domain_options' => [
                        [
                            'rank' => 1,
                            'domain' => 'example.com',
                            'available' => true,
                            'reason' => 'Availability verified by domain-check step.'
                        ]
                    ],
                    'warnings' => []
                ]
            ]
        ]
    ];

    return "You are verifying domain availability for Hoosier Online Preview Packages.\n\n"
        . "Goal:\nFor each business, check the provided domain candidates and return up to 10 domains that are proven available.\n\n"
        . "Rules:\n"
        . "- Return ONLY valid JSON.\n"
        . "- Do not include markdown.\n"
        . "- Do not claim a domain is available unless checked.\n"
        . "- Do not purchase domains.\n"
        . "- Prefer .com, then .net, then .co only when strong.\n"
        . "- Avoid hyphens, weird spellings, and overly long domains unless no better option exists.\n"
        . "- If 10 available domains cannot be proven, return fewer and set package_status=manual_package_review.\n"
        . "- If availability cannot be checked at all, set package_status=domain_check_manual_review and explain in warnings.\n\n"
        . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}


/**
 * v110 helper: decode a package JSON claim.
 */
function ho_preview_package_json_claim(array $business, string $fieldKey): array {
    $raw = ho_preview_package_claim_value($business, $fieldKey);
    if ($raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * v110 helper: package validation checklist.
 */
function ho_preview_package_validation(array $business): array {
    $webDesigns = ho_preview_package_json_claim($business, 'web_design_options_json');
    $logos = ho_preview_package_json_claim($business, 'logo_options_json');
    $domains = ho_preview_package_json_claim($business, 'verified_domain_options_json');
    $report = ho_preview_package_json_claim($business, 'sales_report_json');
    $warnings = ho_preview_package_json_claim($business, 'package_warnings_json');

    $checks = [
        'short_slug' => ho_preview_package_claim_value($business, 'short_slug') !== '',
        'hotlink_path' => ho_preview_package_claim_value($business, 'hotlink_path') !== '',
        'design_dashboard_path' => ho_preview_package_claim_value($business, 'design_dashboard_path') !== '',
        'sales_report_path' => ho_preview_package_claim_value($business, 'sales_report_path') !== '',
        'web_design_options_10' => count($webDesigns) >= 10,
        'logo_options_10' => count($logos) >= 10,
        'verified_domains_10' => count($domains) >= 10,
        'sales_report' => !empty($report),
        'no_blocking_warnings' => empty($warnings),
    ];

    return [
        'is_package_ready' => !in_array(false, $checks, true),
        'checks' => $checks,
        'counts' => [
            'web_design_options' => count($webDesigns),
            'logo_options' => count($logos),
            'verified_domain_options' => count($domains),
            'warnings' => count($warnings),
        ],
    ];
}

/**
 * v110 helper: skeleton of static assets that will be materialized later.
 */
function ho_preview_materialization_skeleton(array $business): array {
    $shortSlug = ho_preview_package_claim_value($business, 'short_slug');
    if ($shortSlug === '') {
        $shortSlug = 'missing-slug';
    }

    return [
        'hotlink_path' => ho_preview_package_claim_value($business, 'hotlink_path') ?: '/go/' . $shortSlug,
        'design_dashboard_path' => ho_preview_package_claim_value($business, 'design_dashboard_path') ?: '/design/' . $shortSlug,
        'sales_report_path' => ho_preview_package_claim_value($business, 'sales_report_path') ?: '/report/' . $shortSlug,
        'will_include' => [
            'design_dashboard' => [
                'business_header',
                '10_locked_website_design_options',
                '10_browser_font_identity_directions',
                '10_verified_domain_options',
                'selection_call_to_action',
            ],
            'sales_report' => [
                'business_snapshot',
                'strength_blocks',
                'weakness_blocks',
                'recommendation_blocks',
                'plain_english_next_step',
            ],
            'hotlink_router' => [
                'short_campaign_url',
                'links_to_design_dashboard_and_report',
            ],
        ],
        'not_generated_in_v110' => true,
    ];
}

/**
 * v110 helper: convert domain verification result into business payload.
 */
function ho_preview_domain_result_to_business_payload(array $result): array {
    $businessId = (int)($result['business_id'] ?? 0);
    $businessSlug = trim((string)($result['business_slug'] ?? ''));
    $businessName = trim((string)($result['business_name'] ?? ''));

    if ($businessName === '' && $businessSlug !== '') {
        $businessName = ucwords(str_replace('-', ' ', $businessSlug));
    }

    if ($businessId <= 0 && $businessSlug === '' && $businessName === '') {
        throw new RuntimeException('Domain verification result is missing business_id/business_slug/business_name.');
    }

    $verified = $result['verified_domain_options'] ?? [];
    if (!is_array($verified)) $verified = [];

    $warnings = $result['warnings'] ?? [];
    if (!is_array($warnings)) $warnings = [$warnings];

    $status = trim((string)($result['package_status'] ?? ''));
    if ($status === '') {
        $status = count($verified) >= 10 ? 'package_ready' : 'manual_package_review';
    }
    if (count($verified) >= 10 && !in_array($status, ['package_blocked','manual_package_review'], true)) {
        $status = 'package_ready';
    }
    if (count($verified) < 10 && !in_array($status, ['package_blocked','domain_check_needed'], true)) {
        $status = 'manual_package_review';
    }

    $business = [
        'business_slug' => $businessSlug !== '' ? $businessSlug : ho_salesportal_dashboard_slug($businessName),
        'business_name_current' => $businessName,
        'business_type' => (string)($result['business_type'] ?? 'local_service'),
        'location_city' => (string)($result['city'] ?? ''),
        'location_state' => (string)($result['state'] ?? 'IN'),
        'service_area_text' => (string)($result['service_area'] ?? 'Indiana'),
    ];
    if ($businessId > 0) {
        $business['id'] = $businessId;
    }

    $claims = [
        ho_preview_package_claim('verified_domain_options_json', $verified, 'Domain availability verification result.'),
        ho_preview_package_claim('package_status', $status, 'Package status after domain availability verification.'),
        ho_preview_package_claim('package_warnings_json', $warnings, 'Warnings from domain availability verification.'),
        ho_preview_package_claim('package_next_step', $status === 'package_ready' ? 'materialization_skeleton_ready' : 'manual_package_review', 'Next package step after domain verification.'),
    ];

    $evidence = [[
        'source_type' => 'manual_observation',
        'source_url' => '',
        'source_title' => 'Domain availability verification result',
        'capture_status' => 'manual',
        'raw_excerpt' => json_encode($result, JSON_UNESCAPED_SLASHES),
        'notes' => 'Domain availability verification imported through Preview Package Workbench. No domain purchase occurred.'
    ]];

    return [
        'business' => $business,
        'evidence_sources' => $evidence,
        'claims' => $claims,
        'marketing_clearance' => [
            'marketing_clearance_status' => 'contact_ready',
            'marketing_clearance_score' => 80,
            'recommended_package' => 'standard',
            'recommended_design' => 'preview_package',
            'reason' => 'Domain verification completed for preview package.'
        ],
        'notes' => ['Domain verification imported. Status: ' . $status],
    ];
}

/**
 * v110 helper: parse domain availability verification JSON into payloads.
 */
function ho_preview_domain_payloads_from_input(array $decoded): ?array {
    $batchType = strtolower(trim((string)($decoded['package_batch']['batch_type'] ?? $decoded['batch_type'] ?? '')));
    $hasResults = isset($decoded['domain_results']) && is_array($decoded['domain_results']);

    if ($batchType !== 'domain_availability_verification' && !$hasResults) {
        return null;
    }

    if (!$hasResults) {
        throw new RuntimeException('Domain verification input is missing domain_results[].');
    }

    $payloads = [];
    foreach ($decoded['domain_results'] as $result) {
        if (!is_array($result)) continue;
        $payloads[] = ho_preview_domain_result_to_business_payload($result);
    }

    return $payloads;
}


/**
 * v112 helper: marketing desk claim/status helper.
 */
function ho_marketing_desk_claim_value(array $business, string $fieldKey): string {
    return function_exists('ho_preview_package_claim_value')
        ? ho_preview_package_claim_value($business, $fieldKey)
        : '';
}

function ho_marketing_desk_status_for_business(array $business): string {
    $status = strtolower(ho_marketing_desk_claim_value($business, 'marketing_desk_status'));
    if ($status !== '') return $status;

    $packageStatus = strtolower(ho_marketing_desk_claim_value($business, 'package_status'));
    if ($packageStatus === 'ready_for_marketing') return 'draft_needed';

    return 'not_ready';
}

function ho_marketing_desk_status_label(string $status): string {
    return match ($status) {
        'ready_for_outreach_review' => 'Ready For Outreach Review',
        'draft_needed' => 'Draft Needed',
        'draft_ready' => 'Draft Ready',
        'paused_manual_review' => 'Paused / Manual Review',
        'sent_later' => 'Sent Later',
        default => ucwords(str_replace('_', ' ', $status)),
    };
}

function ho_marketing_desk_business_summary(array $business): array {
    return [
        'business_id' => (int)($business['id'] ?? 0),
        'business_slug' => (string)($business['business_slug'] ?? ''),
        'business_name' => (string)($business['business_name_current'] ?? ''),
        'business_type' => (string)($business['business_type'] ?? 'local_service'),
        'city' => (string)($business['location_city'] ?? ''),
        'state' => (string)($business['location_state'] ?? 'IN'),
        'service_area' => (string)($business['service_area_text'] ?? 'Indiana'),
        'hotlink_path' => ho_marketing_desk_claim_value($business, 'hotlink_path'),
        'design_dashboard_path' => ho_marketing_desk_claim_value($business, 'design_dashboard_path'),
        'sales_report_path' => ho_marketing_desk_claim_value($business, 'sales_report_path'),
        'short_slug' => ho_marketing_desk_claim_value($business, 'short_slug'),
        'email_address' => ho_marketing_desk_claim_value($business, 'email_address'),
        'phone_number' => ho_marketing_desk_claim_value($business, 'phone_number'),
        'best_contact_method' => ho_marketing_desk_claim_value($business, 'best_contact_method'),
        'contact_readiness' => ho_marketing_desk_claim_value($business, 'contact_readiness'),
        'primary_sales_angle' => ho_marketing_desk_claim_value($business, 'primary_sales_angle'),
        'recommended_template_key' => ho_marketing_desk_claim_value($business, 'recommended_template_key'),
    ];
}

function ho_marketing_desk_outreach_prompt(array $businesses): string {
    $items = array_map('ho_marketing_desk_business_summary', $businesses);

    $payload = [
        'task' => 'draft_marketing_desk_outreach_cards',
        'businesses' => $items,
        'output_contract' => [
            'marketing_batch' => [
                'batch_type' => 'marketing_desk_outreach_drafts',
            ],
            'drafts' => [
                [
                    'business_id' => 0,
                    'business_slug' => 'existing-business-slug',
                    'business_name' => 'Business Name',
                    'marketing_desk_status' => 'draft_ready',
                    'contact_method' => 'email',
                    'to' => 'public@email.example',
                    'subject' => 'I made a quick preview for Business Name',
                    'body' => 'Short respectful outreach copy.',
                    'asset_links' => [
                        'hotlink_path' => '/go/slug/',
                        'design_dashboard_path' => '/design/slug/',
                        'sales_report_path' => '/report/slug/',
                    ],
                    'warnings' => [],
                    'next_step' => 'manual_review_before_send'
                ]
            ]
        ],
    ];

    return "You are preparing Marketing Desk outreach draft cards for Hoosier Online.\n\n"
        . "Goal:\nFor each ready_for_marketing package, draft a short respectful outreach card that a human can review later.\n\n"
        . "Rules:\n"
        . "- Return ONLY valid JSON.\n"
        . "- Do not include markdown.\n"
        . "- Do not send anything.\n"
        . "- Do not imply anything has been sent.\n"
        . "- No SMS.\n"
        . "- No AI calls.\n"
        . "- No guaranteed leads, rankings, sales, or performance claims.\n"
        . "- No fake familiarity. Do not pretend we have spoken before.\n"
        . "- Be truthful: we made a preview package / design dashboard / sales report.\n"
        . "- Keep copy short, clear, local, and low-pressure.\n"
        . "- Prefer email when a public email exists.\n"
        . "- If no usable email exists, set marketing_desk_status=paused_manual_review and explain in warnings.\n"
        . "- Reference the hotlink path primarily. The design/report links are supporting assets.\n"
        . "- The next step is manual review before any send action.\n\n"
        . "Input data:\n"
        . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}


/**
 * v113 helper: marketing draft claim.
 */
function ho_marketing_desk_claim(string $fieldKey, $value, string $note = 'Marketing draft imported.'): array {
    if (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_SLASHES);
    }
    $value = trim((string)$value);

    return [
        'field_key' => $fieldKey,
        'claim_value' => $value,
        'normalized_value' => $value,
        'confidence_level' => 'inferred',
        'confidence_score' => 80,
        'claim_status' => 'active',
        'source_type' => 'manual_observation',
        'source_url' => '',
        'source_label' => 'Marketing Desk draft intake',
        'evidence_note' => $note,
        'supports_me_category' => 'contact_me',
        'supports_requirement_key' => 'contact_me.customer_next_step_clear',
        'evidence_source_index' => 0,
    ];
}

function ho_marketing_desk_draft_to_payload(array $draft): array {
    $businessId = (int)($draft['business_id'] ?? 0);
    $businessSlug = trim((string)($draft['business_slug'] ?? ''));
    $businessName = trim((string)($draft['business_name'] ?? ''));

    if ($businessName === '' && $businessSlug !== '') {
        $businessName = ucwords(str_replace('-', ' ', $businessSlug));
    }

    if ($businessId <= 0 && $businessSlug === '' && $businessName === '') {
        throw new RuntimeException('Marketing draft is missing business_id/business_slug/business_name.');
    }

    $warnings = $draft['warnings'] ?? [];
    if (!is_array($warnings)) $warnings = [$warnings];

    $subject = trim((string)($draft['subject'] ?? ''));
    $body = trim((string)($draft['body'] ?? ''));
    $to = trim((string)($draft['to'] ?? $draft['outreach_to'] ?? ''));
    $method = trim((string)($draft['contact_method'] ?? 'email'));

    $status = trim((string)($draft['marketing_desk_status'] ?? ''));
    if ($status === '') {
        $status = ($subject !== '' && $body !== '' && $to !== '' && empty($warnings)) ? 'draft_ready' : 'paused_manual_review';
    }

    if ($subject === '' || $body === '' || $to === '' || !empty($warnings)) {
        if ($status !== 'sent_later') {
            $status = 'paused_manual_review';
        }
    } elseif ($status !== 'sent_later') {
        $status = 'draft_ready';
    }

    $business = [
        'business_slug' => $businessSlug !== '' ? $businessSlug : (function_exists('ho_salesportal_dashboard_slug') ? ho_salesportal_dashboard_slug($businessName) : strtolower(preg_replace('/[^a-z0-9]+/', '-', $businessName))),
        'business_name_current' => $businessName,
        'business_type' => (string)($draft['business_type'] ?? 'local_service'),
        'location_city' => (string)($draft['city'] ?? ''),
        'location_state' => (string)($draft['state'] ?? 'IN'),
        'service_area_text' => (string)($draft['service_area'] ?? 'Indiana'),
    ];
    if ($businessId > 0) $business['id'] = $businessId;

    $assetLinks = $draft['asset_links'] ?? [];
    if (!is_array($assetLinks)) $assetLinks = [];

    $claims = [
        ho_marketing_desk_claim('marketing_desk_status', $status, 'Marketing Desk draft status.'),
        ho_marketing_desk_claim('contact_method', $method, 'Draft contact method.'),
        ho_marketing_desk_claim('outreach_to', $to, 'Manual-send recipient/contact target.'),
        ho_marketing_desk_claim('outreach_subject', $subject, 'Manual-send outreach subject.'),
        ho_marketing_desk_claim('outreach_body', $body, 'Manual-send outreach body.'),
        ho_marketing_desk_claim('outreach_asset_links_json', $assetLinks, 'Preview package links referenced by draft.'),
        ho_marketing_desk_claim('outreach_warnings_json', $warnings, 'Marketing draft warnings, if any.'),
        ho_marketing_desk_claim('outreach_next_step', $draft['next_step'] ?? 'manual_review_before_send', 'Next Marketing Desk step.'),
    ];

    $claims = array_values(array_filter($claims, static fn($claim) => trim((string)($claim['claim_value'] ?? '')) !== ''));

    return [
        'business' => $business,
        'evidence_sources' => [[
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_title' => 'Marketing Desk outreach draft',
            'capture_status' => 'manual',
            'raw_excerpt' => json_encode($draft, JSON_UNESCAPED_SLASHES),
            'notes' => 'Outreach draft imported for manual review. Nothing was sent.'
        ]],
        'claims' => $claims,
        'marketing_clearance' => [
            'marketing_clearance_status' => 'contact_ready',
            'marketing_clearance_score' => 85,
            'recommended_package' => 'standard',
            'recommended_design' => 'preview_package',
            'reason' => 'Marketing draft staged for manual review. No outreach sent.'
        ],
        'notes' => ['Marketing Desk draft imported. Status: ' . $status],
    ];
}

function ho_marketing_desk_payloads_from_input(array $decoded): ?array {
    $batchType = strtolower(trim((string)($decoded['marketing_batch']['batch_type'] ?? $decoded['batch_type'] ?? '')));
    $hasDrafts = isset($decoded['drafts']) && is_array($decoded['drafts']);

    if ($batchType !== 'marketing_desk_outreach_drafts' && !$hasDrafts) {
        return null;
    }

    if (!$hasDrafts) {
        throw new RuntimeException('Marketing draft input is missing drafts[].');
    }

    $payloads = [];
    foreach ($decoded['drafts'] as $draft) {
        if (!is_array($draft)) continue;
        $payloads[] = ho_marketing_desk_draft_to_payload($draft);
    }

    return $payloads;
}


/**
 * v114 helper: manual pre-send checklist.
 * This is display-only and does not send anything.
 */
function ho_marketing_desk_send_checklist(array $business): array {
    $method = strtolower(ho_marketing_desk_claim_value($business, 'contact_method'));
    $to = trim(ho_marketing_desk_claim_value($business, 'outreach_to'));
    $subject = trim(ho_marketing_desk_claim_value($business, 'outreach_subject'));
    $body = trim(ho_marketing_desk_claim_value($business, 'outreach_body'));

    $assetLinks = json_decode(ho_marketing_desk_claim_value($business, 'outreach_asset_links_json'), true);
    if (!is_array($assetLinks)) $assetLinks = [];

    $warnings = json_decode(ho_marketing_desk_claim_value($business, 'outreach_warnings_json'), true);
    if (!is_array($warnings)) $warnings = [];

    $bodyLower = strtolower($body);
    $subjectLower = strtolower($subject);
    $combined = $subjectLower . ' ' . $bodyLower;

    $hasGuarantee = str_contains($combined, 'guarantee')
        || str_contains($combined, 'guaranteed')
        || str_contains($combined, 'rank #1')
        || str_contains($combined, 'first page')
        || str_contains($combined, 'more leads')
        || str_contains($combined, 'increase sales');

    $fakeFamiliarity = str_contains($combined, 'as we discussed')
        || str_contains($combined, 'following up')
        || str_contains($combined, 'per our conversation')
        || str_contains($combined, 'great speaking')
        || str_contains($combined, 'talked earlier');

    $referencesPreview = str_contains($combined, 'preview')
        || str_contains($combined, 'design dashboard')
        || str_contains($combined, 'sales report')
        || str_contains($combined, '/go/');

    $noPressure = str_contains($combined, 'no pressure')
        || str_contains($combined, 'if it is useful')
        || str_contains($combined, 'if this is useful')
        || str_contains($combined, 'feel free')
        || str_contains($combined, 'no worries');

    $hasAssets = trim((string)($assetLinks['hotlink_path'] ?? ho_marketing_desk_claim_value($business, 'hotlink_path'))) !== ''
        && trim((string)($assetLinks['design_dashboard_path'] ?? ho_marketing_desk_claim_value($business, 'design_dashboard_path'))) !== ''
        && trim((string)($assetLinks['sales_report_path'] ?? ho_marketing_desk_claim_value($business, 'sales_report_path'))) !== '';

    $checks = [
        [
            'key' => 'public_contact_method',
            'label' => 'Contact method is public/customer-facing',
            'pass' => $to !== '' && in_array($method, ['email', 'contact_form', 'manual_email', 'website_form'], true),
            'detail' => $to !== '' ? $method . ': ' . $to : 'Missing recipient/contact target.',
        ],
        [
            'key' => 'truthful_subject',
            'label' => 'Subject is truthful and specific',
            'pass' => $subject !== '' && !$fakeFamiliarity,
            'detail' => $subject !== '' ? $subject : 'Missing subject.',
        ],
        [
            'key' => 'references_preview_without_fake_familiarity',
            'label' => 'Body references preview package without fake familiarity',
            'pass' => $body !== '' && $referencesPreview && !$fakeFamiliarity,
            'detail' => $fakeFamiliarity ? 'Possible fake familiarity phrase detected.' : ($referencesPreview ? 'Preview/package reference present.' : 'Missing preview/package reference.'),
        ],
        [
            'key' => 'no_guarantees',
            'label' => 'No lead/ranking/sales guarantee',
            'pass' => !$hasGuarantee,
            'detail' => $hasGuarantee ? 'Possible guarantee/performance claim detected.' : 'No obvious guarantee language detected.',
        ],
        [
            'key' => 'low_pressure_language',
            'label' => 'Low-pressure/no-pressure language present',
            'pass' => $noPressure,
            'detail' => $noPressure ? 'Low-pressure language detected.' : 'Consider adding no-pressure language before sending.',
        ],
        [
            'key' => 'asset_links_present',
            'label' => 'Preview asset links are present',
            'pass' => $hasAssets,
            'detail' => $hasAssets ? 'Hotlink, design dashboard, and sales report are present.' : 'One or more package asset links are missing.',
        ],
        [
            'key' => 'no_sms_or_call_action',
            'label' => 'No SMS/call action',
            'pass' => !in_array($method, ['sms', 'text', 'call', 'phone'], true),
            'detail' => in_array($method, ['sms', 'text', 'call', 'phone'], true) ? 'SMS/call method is not allowed in this desk.' : 'No SMS/call method selected.',
        ],
        [
            'key' => 'warnings_clear',
            'label' => 'No unresolved warnings',
            'pass' => empty($warnings),
            'detail' => empty($warnings) ? 'No warnings stored.' : json_encode($warnings, JSON_UNESCAPED_SLASHES),
        ],
    ];

    $allPass = true;
    foreach ($checks as $check) {
        if (empty($check['pass'])) {
            $allPass = false;
            break;
        }
    }

    return [
        'manual_ready_to_send' => $allPass,
        'checks' => $checks,
    ];
}

?>
