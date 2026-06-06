<?php
declare(strict_types=1);
require __DIR__ . '/admin-core.php';
require __DIR__ . '/prospect-model.php';


if (!function_exists('ho_salesportal_normalize_research_json_text')) {
    function ho_salesportal_normalize_research_json_text(string $text): string {
        $text = trim($text);

        $replacements = [
            "“" => '"',
            "”" => '"',
            "‘" => "'",
            "’" => "'",
            "\xC2\xA0" => ' ',
        ];

        return strtr($text, $replacements);
    }
}

if (!function_exists('ho_salesportal_looks_like_prompt_not_json')) {
    function ho_salesportal_looks_like_prompt_not_json(string $text): bool {
        $trimmed = ltrim($text);
        if ($trimmed === '') return false;
        if ($trimmed[0] === '{' || $trimmed[0] === '[') return false;

        return stripos($trimmed, 'You are refining') !== false
            || stripos($trimmed, 'Business/category rule:
- The current category/business_type is sourcing context only.
- Do not 
- Accept adjacent and different local service categories when they are customer-facing and practical for Hoosier Online.
- Examples: cleaners, handyman services, photographers, pressure washing, junk removal, mobile detailing, pet grooming, home repair, small contractors, landscaping, tree work, snow removal, property maintenance, local instructors/coaches, and event services.
- Reject only if clearly outside Indiana, not a real/customer-facing business, a big chain/franchise where the offer makes no sense, government/institutional, pure online/non-local, adult/regulated/high-risk, duplicate/confused, or lacking any reasonable contact/setup path.

Return ONLY valid JSON') !== false
            || stripos($trimmed, 'Allowed field_key values') !== false;
    }
}


if (!function_exists('ho_salesportal_batch_slug')) {
    function ho_salesportal_batch_slug(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'unknown-business';
    }
}

if (!function_exists('ho_salesportal_batch_identifier_source_type')) {
    function ho_salesportal_batch_identifier_source_type(string $type): string {
        $type = strtolower(trim($type));
        return match ($type) {
            'website' => 'website',
            'facebook' => 'facebook',
            'google_profile', 'google', 'gbp' => 'google_business_profile',
            'email' => 'email',
            'directory', 'address', 'phone' => 'directory',
            default => 'manual_observation',
        };
    }
}

if (!function_exists('ho_salesportal_batch_identifier_field_key')) {
    function ho_salesportal_batch_identifier_field_key(string $type): ?string {
        $type = strtolower(trim($type));
        return match ($type) {
            'website' => 'website_url',
            'facebook' => 'facebook_url',
            'google_profile', 'google', 'gbp' => 'google_profile_url',
            'phone' => 'phone_number',
            'email' => 'email_address',
            default => null,
        };
    }
}

if (!function_exists('ho_salesportal_candidate_to_payload')) {
    function ho_salesportal_candidate_to_payload(array $candidate, array $batch = []): array {
        $name = trim((string)($candidate['candidate_name'] ?? $candidate['business_name'] ?? $candidate['name'] ?? ''));
        $category = trim((string)($candidate['business_category'] ?? $candidate['category'] ?? $candidate['business_type'] ?? ''));
        $city = trim((string)($candidate['city'] ?? ''));
        $serviceArea = trim((string)($candidate['service_area'] ?? $candidate['city_service_area'] ?? $candidate['area'] ?? ''));
        $state = trim((string)($candidate['state'] ?? 'IN'));
        $notes = trim((string)($candidate['initial_notes'] ?? $candidate['notes'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Candidate is missing candidate_name/business_name.');
        }

        $businessType = $category !== '' ? $category : (string)($batch['category'] ?? 'local_service');
        $slugParts = [$name];
        if ($city !== '') $slugParts[] = $city;
        $slug = ho_salesportal_batch_slug(implode('-', $slugParts));

        $identifiers = $candidate['identifiers'] ?? [];
        if (!is_array($identifiers)) $identifiers = [];

        $evidenceSources = [[
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_title' => 'Candidate sourcing batch',
            'capture_status' => 'manual',
            'raw_excerpt' => json_encode($candidate, JSON_UNESCAPED_SLASHES),
            'notes' => 'Candidate sourced for later refinement. Not outreach-ready until researched and verified.'
        ]];

        foreach ($identifiers as $identifier) {
            if (!is_array($identifier)) continue;
            $type = strtolower(trim((string)($identifier['type'] ?? 'other')));
            $value = trim((string)($identifier['value'] ?? ''));
            if ($value === '') continue;

            $evidenceSources[] = [
                'source_type' => ho_salesportal_batch_identifier_source_type($type),
                'source_url' => filter_var($value, FILTER_VALIDATE_URL) ? $value : '',
                'source_title' => 'Candidate identifier: ' . $type,
                'capture_status' => 'manual',
                'raw_excerpt' => $value,
                'notes' => (string)($identifier['source_note'] ?? 'Candidate identifier from source batch.')
            ];
        }

        $claims = [];
        $claims[] = [
            'field_key' => 'business_name',
            'claim_value' => $name,
            'normalized_value' => $name,
            'confidence_level' => 'likely',
            'confidence_score' => 70,
            'claim_status' => 'needs_review',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'Business name came from candidate sourcing and still needs refinement verification.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.business_identity_clear',
            'evidence_source_index' => 0
        ];

        $claims[] = [
            'field_key' => 'business_type',
            'claim_value' => $businessType,
            'normalized_value' => $businessType,
            'confidence_level' => 'likely',
            'confidence_score' => 65,
            'claim_status' => 'needs_review',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'Business category came from candidate sourcing and still needs refinement verification.',
            'supports_me_category' => 'show_me',
            'supports_requirement_key' => 'show_me.services_visible',
            'evidence_source_index' => 0
        ];

        if ($city !== '') {
            $claims[] = [
                'field_key' => 'city',
                'claim_value' => $city,
                'normalized_value' => $city,
                'confidence_level' => 'likely',
                'confidence_score' => 65,
                'claim_status' => 'needs_review',
                'source_type' => 'manual_observation',
                'source_url' => '',
                'source_label' => 'Candidate source batch',
                'evidence_note' => 'City/service area came from candidate sourcing and should be refined before outreach.',
                'supports_me_category' => 'find_me',
                'supports_requirement_key' => 'find_me.location_or_service_area_clear',
                'evidence_source_index' => 0
            ];
        }

        if ($serviceArea !== '') {
            $claims[] = [
                'field_key' => 'service_area',
                'claim_value' => $serviceArea,
                'normalized_value' => $serviceArea,
                'confidence_level' => 'likely',
                'confidence_score' => 65,
                'claim_status' => 'needs_review',
                'source_type' => 'manual_observation',
                'source_url' => '',
                'source_label' => 'Candidate source batch',
                'evidence_note' => 'Service area came from candidate sourcing and should be refined before outreach.',
                'supports_me_category' => 'find_me',
                'supports_requirement_key' => 'find_me.location_or_service_area_clear',
                'evidence_source_index' => 0
            ];
        }

        foreach ($identifiers as $i => $identifier) {
            if (!is_array($identifier)) continue;
            $type = strtolower(trim((string)($identifier['type'] ?? '')));
            $value = trim((string)($identifier['value'] ?? ''));
            if ($value === '') continue;
            $fieldKey = ho_salesportal_batch_identifier_field_key($type);
            if ($fieldKey === null) continue;

            $claims[] = [
                'field_key' => $fieldKey,
                'claim_value' => $value,
                'normalized_value' => $value,
                'confidence_level' => 'likely',
                'confidence_score' => 65,
                'claim_status' => 'needs_review',
                'source_type' => ho_salesportal_batch_identifier_source_type($type),
                'source_url' => filter_var($value, FILTER_VALIDATE_URL) ? $value : '',
                'source_label' => 'Candidate identifier',
                'evidence_note' => 'Identifier came from candidate sourcing and should be verified during refinement.',
                'supports_me_category' => $fieldKey === 'phone_number' || $fieldKey === 'email_address' ? 'contact_me' : 'find_me',
                'supports_requirement_key' => $fieldKey === 'phone_number' || $fieldKey === 'email_address' ? 'contact_me.clear_primary_contact' : 'find_me.public_search_presence',
                'evidence_source_index' => min(count($evidenceSources) - 1, $i + 1)
            ];
        }

        $claims[] = [
            'field_key' => 'marketing_clearance_status',
            'claim_value' => 'needs_review',
            'normalized_value' => 'needs_review',
            'confidence_level' => 'inferred',
            'confidence_score' => 70,
            'claim_status' => 'active',
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_label' => 'Candidate source batch',
            'evidence_note' => 'Candidate records remain on hold until refinement verifies identity, contactability, and fit.',
            'supports_me_category' => 'find_me',
            'supports_requirement_key' => 'find_me.business_identity_clear',
            'evidence_source_index' => 0
        ];

        return [
            'business' => [
                'business_slug' => $slug,
                'business_name_current' => $name,
                'business_type' => $businessType,
                'location_city' => $city,
                'location_state' => $state !== '' ? $state : 'IN',
                'service_area_text' => $serviceArea !== '' ? $serviceArea : $city
            ],
            'evidence_sources' => $evidenceSources,
            'claims' => $claims,
            'marketing_clearance' => [
                'business_activity_score' => 0,
                'need_score' => 0,
                'fit_score' => 0,
                'confidence_score' => 35,
                'contactability_score' => 0,
                'buildability_score' => 0,
                'marketing_clearance_score' => 0,
                'marketing_clearance_status' => 'hold',
                'recommended_package' => 'unknown',
                'recommended_design' => '',
                'reason' => 'Candidate batch import only. Requires refinement before outreach.'
            ],
            'notes' => [
                'Imported from candidate batch.',
                'Do not contact until refinement confirms identity, public surface, and contactability.',
                $notes
            ]
        ];
    }
}


if (!function_exists('ho_salesportal_triage_slug')) {
    function ho_salesportal_triage_slug(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');
        return $value !== '' ? $value : 'unknown-business';
    }
}

if (!function_exists('ho_salesportal_triage_status_to_clearance')) {
    function ho_salesportal_triage_status_to_clearance(string $status): string {
        return match ($status) {
            'research_with_website', 'research_ready' => 'warm_clear',
            'proceed_no_website', 'quick_hold', 'needs_identity_check', 'no_public_surface', 'duplicate_or_confused' => 'hold',
            'do_not_proceed', 'bad_fit', 'exclude' => 'skip',
            default => 'needs_review',
        };
    }
}

if (!function_exists('ho_salesportal_triage_to_payload')) {
    function ho_salesportal_triage_to_payload(array $result, array $batch = []): array {
        $name = trim((string)($result['business_name'] ?? $result['candidate_name'] ?? ''));
        $slug = trim((string)($result['business_slug'] ?? ''));
        $status = trim((string)($result['status'] ?? 'quick_hold'));
        $nextStep = trim((string)($result['recommended_next_step'] ?? 'manual_check'));
        $reason = trim((string)($result['reason'] ?? 'Candidate triage result.'));
        $targetArea = trim((string)($batch['target_area'] ?? ''));
        $category = trim((string)($batch['category'] ?? 'lawn_care'));

        if ($name === '' && $slug === '') {
            throw new RuntimeException('Triage result is missing business_name or business_slug.');
        }
        if ($name === '') $name = ucwords(str_replace('-', ' ', $slug));
        if ($slug === '') $slug = ho_salesportal_triage_slug($name . '-' . $targetArea);

        $city = '';
        $state = 'IN';
        if (stripos($targetArea, ',') !== false) {
            [$cityPart, $statePart] = array_map('trim', explode(',', $targetArea, 2));
            $city = $cityPart;
            if ($statePart !== '') $state = $statePart;
        } else {
            $city = $targetArea;
        }

        $identifiers = $result['verified_identifiers'] ?? [];
        if (!is_array($identifiers)) $identifiers = [];

        $evidence = [[
            'source_type' => 'manual_observation',
            'source_url' => '',
            'source_title' => 'Candidate triage result',
            'capture_status' => 'manual',
            'raw_excerpt' => json_encode($result, JSON_UNESCAPED_SLASHES),
            'notes' => 'Lightweight triage result. This is not full prospect research.'
        ]];

        $claims = [
            [
                'field_key' => 'marketing_clearance_status',
                'claim_value' => ho_salesportal_triage_status_to_clearance($status),
                'normalized_value' => ho_salesportal_triage_status_to_clearance($status),
                'confidence_level' => 'inferred',
                'confidence_score' => 75,
                'claim_status' => 'active',
                'source_type' => 'manual_observation',
                'source_url' => '',
                'source_label' => 'Candidate triage result',
                'evidence_note' => 'Bulk triage category: ' . $status . '. Next step: ' . $nextStep . '.',
                'supports_me_category' => 'find_me',
                'supports_requirement_key' => 'find_me.business_identity_clear',
                'evidence_source_index' => 0
            ],
            [
                'field_key' => 'primary_sales_angle',
                'claim_value' => '[' . $status . ' / ' . $nextStep . '] ' . $reason,
                'normalized_value' => '[' . $status . ' / ' . $nextStep . '] ' . $reason,
                'confidence_level' => 'inferred',
                'confidence_score' => 65,
                'claim_status' => 'needs_review',
                'source_type' => 'manual_observation',
                'source_url' => '',
                'source_label' => 'Candidate triage result',
                'evidence_note' => 'Triage reason, not final sales copy.',
                'supports_me_category' => 'fix_me',
                'supports_requirement_key' => 'fix_me.customer_path_mess',
                'evidence_source_index' => 0
            ],
            [
                'field_key' => 'business_name',
                'claim_value' => $name,
                'normalized_value' => $name,
                'confidence_level' => 'likely',
                'confidence_score' => 70,
                'claim_status' => $status === 'needs_identity_check' ? 'needs_review' : 'active',
                'source_type' => 'manual_observation',
                'source_url' => '',
                'source_label' => 'Candidate triage result',
                'evidence_note' => 'Business name from triage result.',
                'supports_me_category' => 'find_me',
                'supports_requirement_key' => 'find_me.business_identity_clear',
                'evidence_source_index' => 0
            ],
        ];

        foreach ($identifiers as $identifier) {
            if (!is_array($identifier)) continue;
            $type = strtolower(trim((string)($identifier['type'] ?? '')));
            $value = trim((string)($identifier['value'] ?? ''));
            if ($value === '') continue;

            $fieldKey = match ($type) {
                'website' => 'website_url',
                'facebook' => 'facebook_url',
                'google_profile', 'google' => 'google_profile_url',
                'phone' => 'phone_number',
                'email' => 'email_address',
                default => null,
            };
            if ($fieldKey === null) continue;

            $sourceType = match ($type) {
                'website' => 'website',
                'facebook' => 'facebook',
                'google_profile', 'google' => 'google_business_profile',
                'email' => 'email',
                default => 'manual_observation',
            };

            $claims[] = [
                'field_key' => $fieldKey,
                'claim_value' => $value,
                'normalized_value' => $value,
                'confidence_level' => (($identifier['confidence'] ?? '') === 'high') ? 'confirmed' : 'likely',
                'confidence_score' => (($identifier['confidence'] ?? '') === 'high') ? 85 : 65,
                'claim_status' => 'active',
                'source_type' => $sourceType,
                'source_url' => filter_var($value, FILTER_VALIDATE_URL) ? $value : '',
                'source_label' => 'Verified identifier from triage',
                'evidence_note' => 'Verified identifier from lightweight triage.',
                'supports_me_category' => in_array($fieldKey, ['phone_number','email_address'], true) ? 'contact_me' : 'find_me',
                'supports_requirement_key' => in_array($fieldKey, ['phone_number','email_address'], true) ? 'contact_me.clear_primary_contact' : 'find_me.public_search_presence',
                'evidence_source_index' => 0
            ];
        }

        return [
            'business' => [
                'business_slug' => $slug,
                'business_name_current' => $name,
                'business_type' => $category,
                'location_city' => $city,
                'location_state' => $state,
                'service_area_text' => $targetArea
            ],
            'evidence_sources' => $evidence,
            'claims' => $claims,
            'marketing_clearance' => [
                'business_activity_score' => in_array($status, ['research_ready','research_with_website','proceed_no_website'], true) ? 55 : 20,
                'need_score' => in_array($status, ['research_ready','research_with_website','proceed_no_website'], true) ? 70 : 30,
                'fit_score' => in_array($status, ['research_ready','research_with_website','proceed_no_website'], true) ? 70 : 30,
                'confidence_score' => in_array($status, ['research_ready','research_with_website','proceed_no_website'], true) ? 60 : 35,
                'contactability_score' => in_array($status, ['research_ready','research_with_website','proceed_no_website'], true) ? 60 : 25,
                'buildability_score' => in_array($status, ['research_ready','research_with_website','proceed_no_website'], true) ? 65 : 35,
                'marketing_clearance_score' => in_array($status, ['research_ready','research_with_website'], true) ? 62 : ($status === 'proceed_no_website' ? 52 : 25),
                'marketing_clearance_status' => ho_salesportal_triage_status_to_clearance($status),
                'recommended_package' => 'unknown',
                'recommended_design' => '',
                'reason' => $reason
            ],
            'notes' => [
                'Imported from lightweight candidate triage.',
                'Triage status: ' . $status,
                'Recommended next step: ' . $nextStep,
            ]
        ];
    }
}

if (!function_exists('ho_salesportal_payloads_from_triage_input')) {
    function ho_salesportal_payloads_from_triage_input(array $decoded): ?array {
        if (!isset($decoded['triage_results']) || !is_array($decoded['triage_results'])) {
            return null;
        }

        $batch = $decoded['triage_batch'] ?? [];
        if (!is_array($batch)) $batch = [];

        $payloads = [];
        foreach ($decoded['triage_results'] as $result) {
            if (!is_array($result)) continue;
            $payloads[] = ho_salesportal_triage_to_payload($result, $batch);
        }

        return $payloads;
    }
}


if (!function_exists('ho_salesportal_setup_status_to_clearance')) {
    function ho_salesportal_setup_status_to_clearance(string $readiness): string { return match($readiness){'ready_to_contact'=>'cleared','needs_manual_check'=>'needs_review','not_contactable'=>'hold','do_not_contact'=>'skip',default=>'needs_review'}; }
}
if (!function_exists('ho_salesportal_setup_to_payload')) {
    function ho_salesportal_setup_to_payload(array $result, array $batch=[]): array {
        $name=trim((string)($result['business_name'] ?? '')); $slug=trim((string)($result['business_slug'] ?? '')); $setupPath=trim((string)($result['setup_path'] ?? 'proceed_no_website')); $previewApproach=trim((string)($result['preview_approach'] ?? 'simple_front_door_preview')); $readiness=trim((string)($result['contact_readiness'] ?? 'needs_manual_check')); $method=trim((string)($result['best_contact_method'] ?? 'none')); $contactValue=trim((string)($result['contact_value'] ?? '')); $angle=trim((string)($result['contact_angle'] ?? '')); $reason=trim((string)($result['reason'] ?? 'Preview/contact setup result.')); $category=trim((string)($batch['category'] ?? 'lawn_care')); $targetArea=trim((string)($batch['target_area'] ?? 'New Castle, IN'));
        if($name==='' && $slug==='') throw new RuntimeException('Setup result is missing business_name or business_slug.'); if($name==='') $name=ucwords(str_replace('-',' ',$slug)); if($slug==='') $slug=ho_salesportal_batch_slug($name.'-'.$targetArea);
        $clearance=ho_salesportal_setup_status_to_clearance($readiness); $salesAngle='['.$setupPath.' / '.$readiness.' / '.$method.'] '.($angle!==''?$angle:$reason);
        return ['business'=>['business_slug'=>$slug,'business_name_current'=>$name,'business_type'=>$category,'location_city'=>'New Castle','location_state'=>'IN','service_area_text'=>$targetArea], 'evidence_sources'=>[['source_type'=>'manual_observation','source_url'=>'','source_title'=>'Preview and contact setup result','capture_status'=>'manual','raw_excerpt'=>json_encode($result, JSON_UNESCAPED_SLASHES),'notes'=>'Backend setup gate result.']], 'claims'=>[
            ['field_key'=>'marketing_clearance_status','claim_value'=>$clearance,'normalized_value'=>$clearance,'confidence_level'=>'inferred','confidence_score'=>75,'claim_status'=>'active','source_type'=>'manual_observation','source_url'=>'','source_label'=>'Preview and contact setup result','evidence_note'=>'Contact readiness: '.$readiness.'. Setup path: '.$setupPath.'.','supports_me_category'=>'contact_me','supports_requirement_key'=>'contact_me.customer_next_step_clear','evidence_source_index'=>0],
            ['field_key'=>'recommended_design','claim_value'=>$previewApproach,'normalized_value'=>$previewApproach,'confidence_level'=>'inferred','confidence_score'=>70,'claim_status'=>'active','source_type'=>'manual_observation','source_url'=>'','source_label'=>'Preview and contact setup result','evidence_note'=>'Preview approach selected during setup gate.','supports_me_category'=>'show_me','supports_requirement_key'=>'show_me.offer_clarity','evidence_source_index'=>0],
            ['field_key'=>'recommended_features','claim_value'=>'setup_path='.$setupPath.'; contact_readiness='.$readiness.'; best_contact_method='.$method,'normalized_value'=>'setup_path='.$setupPath.'; contact_readiness='.$readiness.'; best_contact_method='.$method,'confidence_level'=>'inferred','confidence_score'=>70,'claim_status'=>'needs_review','source_type'=>'manual_observation','source_url'=>'','source_label'=>'Preview and contact setup result','evidence_note'=>'Temporary setup metadata stored in allowed field.','supports_me_category'=>'fix_me','supports_requirement_key'=>'fix_me.customer_path_mess','evidence_source_index'=>0],
            ['field_key'=>'primary_sales_angle','claim_value'=>$salesAngle,'normalized_value'=>$salesAngle,'confidence_level'=>'inferred','confidence_score'=>70,'claim_status'=>'needs_review','source_type'=>'manual_observation','source_url'=>'','source_label'=>'Preview and contact setup result','evidence_note'=>$reason,'supports_me_category'=>'fix_me','supports_requirement_key'=>'fix_me.customer_path_mess','evidence_source_index'=>0],
            ['field_key'=>'contact_path_clarity','claim_value'=>$method.($contactValue!==''?': '.$contactValue:''),'normalized_value'=>$method,'confidence_level'=>$method!=='none'?'likely':'missing','confidence_score'=>$method!=='none'?70:55,'claim_status'=>$method!=='none'?'active':'missing','source_type'=>'manual_observation','source_url'=>filter_var($contactValue,FILTER_VALIDATE_URL)?$contactValue:'','source_label'=>'Preview and contact setup result','evidence_note'=>'Best contact method selected during setup gate.','supports_me_category'=>'contact_me','supports_requirement_key'=>'contact_me.clear_primary_contact','evidence_source_index'=>0]
        ], 'marketing_clearance'=>['business_activity_score'=>$readiness==='ready_to_contact'?70:40,'need_score'=>75,'fit_score'=>$readiness==='ready_to_contact'?75:40,'confidence_score'=>$readiness==='ready_to_contact'?70:45,'contactability_score'=>$method!=='none'?75:20,'buildability_score'=>$previewApproach!=='none'?75:20,'marketing_clearance_score'=>$readiness==='ready_to_contact'?72:52,'marketing_clearance_status'=>$clearance,'recommended_package'=>$setupPath==='proceed_no_website'?'standard':'unknown','recommended_design'=>$previewApproach,'reason'=>$reason], 'notes'=>['Imported from Preview & Contact Setup.','Setup path: '.$setupPath,'Contact readiness: '.$readiness,'Best contact method: '.$method]];
    }
}
if (!function_exists('ho_salesportal_payloads_from_setup_input')) {
    function ho_salesportal_payloads_from_setup_input(array $decoded): ?array { if(!isset($decoded['setup_results']) || !is_array($decoded['setup_results'])) return null; $batch=$decoded['setup_batch'] ?? []; if(!is_array($batch)) $batch=[]; $payloads=[]; foreach($decoded['setup_results'] as $result){ if(is_array($result)) $payloads[]=ho_salesportal_setup_to_payload($result,$batch); } return $payloads; }
}

if (!function_exists('ho_salesportal_payloads_from_research_input')) {
    function ho_salesportal_payloads_from_research_input(array $decoded): array {
        $setupPayloads = ho_salesportal_payloads_from_setup_input($decoded);
        if (is_array($setupPayloads)) return $setupPayloads;
        $triagePayloads = ho_salesportal_payloads_from_triage_input($decoded);
        if (is_array($triagePayloads)) return $triagePayloads;
        if (isset($decoded['business']) && is_array($decoded['business'])) {
            return [$decoded];
        }

        $batch = $decoded['source_batch'] ?? $decoded['batch'] ?? [];
        if (!is_array($batch)) $batch = [];

        foreach (['businesses', 'prospects', 'items'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                $items = $decoded[$key];
                $payloads = [];
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $payloads[] = isset($item['business']) ? $item : ho_salesportal_candidate_to_payload($item, $batch);
                }
                return $payloads;
            }
        }

        if (isset($decoded['candidates']) && is_array($decoded['candidates'])) {
            $payloads = [];
            foreach ($decoded['candidates'] as $candidate) {
                if (!is_array($candidate)) continue;
                $payloads[] = ho_salesportal_candidate_to_payload($candidate, $batch);
            }
            return $payloads;
        }

        return [$decoded];
    }
}

if (!function_exists('ho_salesportal_auto_process_business_after_import')) {
    function ho_salesportal_auto_process_business_after_import(int $businessId): array {
        $auto = [
            'readiness' => null,
            'assignment' => null,
            'warnings' => [],
        ];

        try {
            if (function_exists('ho_salesportal_evaluate_preview_readiness')) {
                $auto['readiness'] = ho_salesportal_evaluate_preview_readiness($businessId, true);
            }
        } catch (Throwable $e) {
            $auto['warnings'][] = 'Readiness auto-evaluation skipped: ' . $e->getMessage();
        }

        try {
            if (function_exists('ho_salesportal_assign_preview_options')) {
                $auto['assignment'] = ho_salesportal_assign_preview_options($businessId, true);
            }
        } catch (Throwable $e) {
            $auto['warnings'][] = 'Option assignment skipped: ' . $e->getMessage();
        }

        return $auto;
    }
}

if (!function_exists('ho_salesportal_validate_or_import_input_payload')) {
    function ho_salesportal_validate_or_import_input_payload(array $decoded, bool $validateOnly): array {
        $payloads = ho_salesportal_payloads_from_research_input($decoded);

        if (count($payloads) === 1) {
            $result = $validateOnly ? ho_salesportal_validate_payload($payloads[0]) : ho_salesportal_import_payload($payloads[0]);
            if (!$validateOnly && !empty($result['ok']) && !empty($result['business_id'])) {
                $result['auto_processing'] = ho_salesportal_auto_process_business_after_import((int)$result['business_id']);
                $result['message'] .= ' Auto-processing attempted.';
            }
            return $result;
        }

        $results = [];
        $okCount = 0;
        $failCount = 0;

        foreach ($payloads as $index => $payload) {
            try {
                $result = $validateOnly ? ho_salesportal_validate_payload_with_duplicates($payload) : ho_salesportal_import_payload_with_duplicates($payload);
                if (!$validateOnly && !empty($result['ok']) && !empty($result['business_id'])) {
                    $result['auto_processing'] = ho_salesportal_auto_process_business_after_import((int)$result['business_id']);
                }

                if (!empty($result['ok'])) $okCount++; else $failCount++;
                $results[] = [
                    'index' => $index,
                    'business_name' => (string)($payload['business']['business_name_current'] ?? ''),
                    'result' => $result,
                ];
            } catch (Throwable $e) {
                $failCount++;
                $results[] = [
                    'index' => $index,
                    'business_name' => (string)($payload['business']['business_name_current'] ?? ''),
                    'result' => [
                        'ok' => false,
                        'message' => $e->getMessage(),
                        'details' => [],
                    ],
                ];
            }
        }

        return [
            'ok' => $failCount === 0,
            'message' => ($validateOnly ? 'Batch validation complete. ' : 'Batch import complete. ') . $okCount . ' ok, ' . $failCount . ' failed.',
            'batch_count' => count($payloads),
            'ok_count' => $okCount,
            'fail_count' => $failCount,
            'details' => $results,
        ];
    }
}

$canon = ho_salesportal_canon();
$fieldList = implode("\n", $canon['claim_fields']);
$reqList = implode("\n", $canon['requirements']);
$confidenceList = implode(', ', $canon['confidence_levels']);

$knownExclusions = [];
try {
    if (function_exists('ho_salesportal_known_business_exclusions')) {
        $knownExclusions = ho_salesportal_known_business_exclusions('lawn_care', 'New Castle, IN', 100);
    }
} catch (Throwable $e) {
    $knownExclusions = [];
}
$knownExclusionsJson = json_encode($knownExclusions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

$prompt = <<<PROMPT
Find up to 25 NEW candidate businesses for Hoosier Online.

Goal:
Generate a source candidate batch that can be pasted into the Hoosier Online admin import tool.

Category:
lawn_care

Target area:
New Castle, IN and nearby service areas

Known businesses to exclude:
$knownExclusionsJson

Do not include any business that appears to match the exclusion list by:
- business name
- slug
- website/domain
- Facebook URL
- Google profile
- phone
- email
- address

If a candidate might be the same as a known business, exclude it rather than duplicating it.

Look for:
- lawn care
- mowing
- landscaping
- yard cleanup
- pressure washing
- exterior property service
- small local contractor/operator that overlaps with lawn/exterior work

Only include:
- small local operators
- owner-operated or simple local service businesses
- businesses that could benefit from a simple Front Door page
- businesses with at least two public identifiers

Avoid:
- franchises
- national chains
- large polished regional companies
- government entities
- businesses that appear already digitally strong
- businesses with fewer than two public identifiers

For each candidate, collect at least two identifiers.

Preferred identifiers:
- website
- Facebook page
- Google Business Profile
- phone
- email
- address
- directory listing/service area

Do NOT:
- write sales copy
- judge the business deeply
- infer owner names
- infer private facts
- include sensitive personal information
- do full research
- include a candidate unless it has at least two public identifiers

Return ONLY valid JSON in this exact structure:

{
  "source_batch": {
    "category": "local_service",
    "target_area": "New Castle, IN",
    "source_method": "manual_gpt_assisted_candidate_search",
    "count_requested": 25,
    "notes": "Candidate batch only. Not outreach-ready until triaged/refined."
  },
  "candidates": [
    {
      "candidate_name": "",
      "business_category": "lawn_care",
      "city": "",
      "state": "IN",
      "service_area": "",
      "identifiers": [
        {
          "type": "website|facebook|google_profile|phone|email|address|directory|other",
          "value": "",
          "source_note": ""
        },
        {
          "type": "website|facebook|google_profile|phone|email|address|directory|other",
          "value": "",
          "source_note": ""
        }
      ],
      "initial_notes": ""
    }
  ]
}

Important:
- Return 10 to 25 candidates if possible.
- The JSON must be directly pasteable into Hoosier Online Prospects → Paste Results Here or Sales Research.
- Keep notes short.
- Do not use markdown.

PROMPT;
$result = null; $raw = '';


function ho_salesportal_payload_is_new_candidate_submission(array $payload): bool {
    foreach (($payload['evidence_sources'] ?? []) as $source) {
        if (!is_array($source)) continue;
        $title = strtolower((string)($source['source_title'] ?? ''));
        $notes = strtolower((string)($source['notes'] ?? ''));
        $raw = strtolower((string)($source['raw_excerpt'] ?? ''));
        if (str_contains($title, 'source candidate') || str_contains($notes, 'candidate seed') || str_contains($raw, 'candidate_name')) return true;
    }
    foreach (($payload['claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        $field = strtolower((string)($claim['field_key'] ?? ''));
        $note = strtolower((string)($claim['evidence_note'] ?? ''));
        if ($field === 'marketing_clearance_status' && (str_contains($note, 'candidate records enter needs_review') || str_contains($note, 'candidate records remain on hold'))) return true;
    }
    return false;
}

if (!function_exists('ho_salesportal_validate_payload_with_duplicates')) {
    function ho_salesportal_validate_payload_with_duplicates(array $payload): array {
        $validation = ho_salesportal_validate_payload($payload);

        if (!ho_salesportal_payload_is_new_candidate_submission($payload)) {
            $validation['duplicate_check'] = [
                'mode' => 'update_existing_allowed',
                'has_exact_duplicate' => false,
                'has_possible_duplicate' => false,
                'exact_matches' => [],
                'possible_matches' => [],
            ];
            $validation['details'][] = 'Update/triage/setup payload: duplicate guard bypassed so existing business data can be absorbed.';
            return $validation;
        }

        try {
            $dupes = ho_salesportal_duplicate_check_payload($payload);
        } catch (Throwable $e) {
            $dupes = [
                'has_exact_duplicate' => false,
                'has_possible_duplicate' => false,
                'exact_matches' => [],
                'possible_matches' => [],
                'warning' => $e->getMessage(),
            ];
        }

        $validation['duplicate_check'] = $dupes;

        if (!empty($dupes['has_exact_duplicate'])) {
            $validation['ok'] = true;
            $validation['skipped'] = true;
            $validation['message'] = 'Validation ok: exact duplicate lead will be skipped.';
            $validation['details'][] = 'Exact duplicate lead found. Import will skip this new candidate record.';
        } elseif (!empty($dupes['has_possible_duplicate'])) {
            $validation['details'][] = 'Possible duplicate lead found. Review before import.';
        }

        return $validation;
    }
}

if (!function_exists('ho_salesportal_import_payload_with_duplicates')) {
    function ho_salesportal_import_payload_with_duplicates(array $payload): array {
        if (!ho_salesportal_payload_is_new_candidate_submission($payload)) {
            $result = ho_salesportal_import_payload($payload);
            $result['duplicate_check'] = [
                'mode' => 'update_existing_allowed',
                'has_exact_duplicate' => false,
                'has_possible_duplicate' => false,
                'exact_matches' => [],
                'possible_matches' => [],
            ];
            return $result;
        }

        $dupes = ho_salesportal_duplicate_check_payload($payload);

        if (!empty($dupes['has_exact_duplicate'])) {
            return [
                'ok' => true,
                'skipped' => true,
                'message' => 'Skipped exact duplicate new lead.',
                'business_id' => $dupes['exact_matches'][0]['business']['id'] ?? null,
                'duplicate_check' => $dupes,
            ];
        }

        $result = ho_salesportal_import_payload($payload);
        $result['duplicate_check'] = $dupes;
        if (!empty($dupes['has_possible_duplicate'])) {
            $result['warning'] = 'Possible duplicate new lead was imported. Review recommended.';
        }
        return $result;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = trim((string)($_POST['research_json'] ?? ''));
    $normalizedJsonText = ho_salesportal_normalize_research_json_text($raw);
    if (ho_salesportal_looks_like_prompt_not_json($normalizedJsonText)) {
        throw new RuntimeException('This looks like a GPT prompt, not a JSON response. Paste the prompt into GPT first, then paste GPT’s JSON answer here.');
    }
    $decoded = json_decode($normalizedJsonText, true);
    if (!is_array($decoded)) {
        $result = ['ok'=>false,'message'=>'Invalid JSON: '.json_last_error_msg(),'details'=>[]];
    } else {
        try {
            $result = ho_salesportal_validate_or_import_input_payload($decoded, isset($_POST['validate_only']));
        } catch (Throwable $e) {
            $result = ['ok'=>false,'message'=>$e->getMessage(),'details'=>[]];
        }
    }
}
ho_admin_render_start(
    'sales_research',
    'Find Leads',
    'Sales',
    'Find <em>Leads</em>',
    'Copy prompt. Paste result in Work Queue.'
);
?>

<section class="admin-card admin-find-leads-card">
  <p class="admin-kicker">Find</p>
  <h2>Copy Lead Prompt</h2>
  <div class="admin-action-row">
    <button class="admin-btn admin-btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('leadPromptBox').value)">Copy Prompt</button>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php#dashboard-import">Paste Result</a>
    <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Work Queue</a>
  </div>
</section>

<section class="admin-card">
  <p class="admin-kicker">Lead Generation Prompt</p>
  <h2>Prompt Text</h2>
  <textarea id="leadPromptBox" class="admin-textarea lead-generation-prompt-box"><?= ho_h($prompt) ?></textarea>
</section>

<section class="admin-card admin-low-priority">
  <details>
    <summary>What this page accepts</summary>
    <p class="admin-muted">This page is for generating lead-search prompts only. It is not the primary paste/import surface. Paste returned JSON into the Intake Desk on the Work Queue page.</p>
  </details>
</section>

<?php ho_admin_render_end(); ?>
