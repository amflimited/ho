<?php
/**
 * Hoosier Online Diagnosis Model
 * v123 — Diagnosis Registry + Front Door Preview Contract
 */
declare(strict_types=1);
const HO_DIAGNOSIS_MODEL_VERSION = 'HO-DIAGNOSIS-123';

function ho_diag_strength_registry(): array {
    return [
        'find_me' => ['label'=>'Find Me','blocks'=>[
            'website_exists'=>['headline'=>'Customers have somewhere to land','body_template'=>'{business_name} already has at least one public web destination. That is a useful starting point because customers are not starting completely from zero.'],
            'google_profile_found'=>['headline'=>'There is a familiar search signal','body_template'=>'A Google-style public presence can help customers confirm they found the right {category_label} before they reach out.'],
            'facebook_active'=>['headline'=>'There are signs of activity','body_template'=>'A social page can help show that {business_name} is real and active, especially when customers are checking quickly from their phone.'],
            'clear_business_name'=>['headline'=>'The name is recognizable','body_template'=>'{business_name} has a clear enough name to build a simple front-door page around.'],
            'location_signal_present'=>['headline'=>'There is a local signal','body_template'=>'Customers can see enough local context to understand that this is an Indiana-area business.'],
        ]],
        'trust_me' => ['label'=>'Trust Me','blocks'=>[
            'reviews_present'=>['headline'=>'Public trust signals exist','body_template'=>'Visible reviews or reputation signals can help a customer feel safer before reaching out.'],
            'photos_present'=>['headline'=>'Customers can see some proof','body_template'=>'Photos or visual examples help customers picture the work instead of guessing.'],
            'recent_activity_visible'=>['headline'=>'The business appears active','body_template'=>'Recent visible activity makes it easier for a customer to believe someone is still paying attention.'],
            'local_identity_clear'=>['headline'=>'The local identity is usable','body_template'=>'{business_name} already has enough local identity to build trust around a clearer front door.'],
            'business_details_consistent'=>['headline'=>'The basic details are mostly consistent','body_template'=>'Consistent public details reduce friction for customers deciding whether they found the right business.'],
        ]],
        'understand_me' => ['label'=>'Understand Me','blocks'=>[
            'service_category_clear'=>['headline'=>'The service category is understandable','body_template'=>'A customer can reasonably tell what kind of work {business_name} does.'],
            'service_list_visible'=>['headline'=>'Some services are visible','body_template'=>'A visible service list gives customers a starting point before they ask for help.'],
            'before_after_examples_present'=>['headline'=>'Examples help explain the work','body_template'=>'Before-and-after or project examples can make the value easier to understand quickly.'],
            'clear_customer_type'=>['headline'=>'The likely customer is understandable','body_template'=>'The public presence gives enough context to understand who this business likely helps.'],
        ]],
        'contact_me' => ['label'=>'Contact Me','blocks'=>[
            'phone_visible'=>['headline'=>'Customers can find a phone path','body_template'=>'A visible phone number gives ready customers a direct way to take action.'],
            'email_visible'=>['headline'=>'Customers have a written contact path','body_template'=>'A visible email can help customers reach out without stopping their day to call.'],
            'contact_form_exists'=>['headline'=>'A form path exists','body_template'=>'A contact form can structure the next step and reduce back-and-forth.'],
            'quote_request_visible'=>['headline'=>'There is a quote path','body_template'=>'A visible quote path helps customers understand what to do next.'],
        ]],
        'choose_me' => ['label'=>'Choose Me','blocks'=>[
            'specialty_visible'=>['headline'=>'A specialty can be seen','body_template'=>'Customers can see some reason {business_name} may be a fit for their specific need.'],
            'local_experience_visible'=>['headline'=>'Local experience can be part of the story','body_template'=>'Local service context can help customers feel like they are dealing with someone nearby.'],
            'photos_show_quality'=>['headline'=>'Visual proof can support quality','body_template'=>'When photos are easy to find, the work itself can help customers decide.'],
            'reviews_support_quality'=>['headline'=>'Reviews can support the choice','body_template'=>'Review signals can help customers feel safer choosing one local operator over another.'],
            'clear_differentiator'=>['headline'=>'There is something to build a reason around','body_template'=>'There is enough public signal to create a clearer reason for customers to choose {business_name}.'],
        ]],
        'start_me' => ['label'=>'Start Me','blocks'=>[
            'cta_visible'=>['headline'=>'There is some next-step language','body_template'=>'A visible call to action helps customers understand how to move from interest to contact.'],
            'quote_form_exists'=>['headline'=>'A quote form can help start the job','body_template'=>'A quote form can gather the details needed to respond well.'],
            'booking_path_exists'=>['headline'=>'A booking path exists','body_template'=>'A booking or scheduling path can lower friction when customers are ready.'],
            'pricing_or_estimate_language_visible'=>['headline'=>'Estimate language can reduce uncertainty','body_template'=>'Clear estimate or quote language can help customers know what kind of first step to expect.'],
        ]],
        'remember_me' => ['label'=>'Remember Me','blocks'=>[
            'simple_business_name'=>['headline'=>'The name can be remembered','body_template'=>'A simple recognizable name makes it easier for customers to come back later.'],
            'memorable_domain'=>['headline'=>'The domain can support recall','body_template'=>'A memorable domain can make the business easier to revisit and share.'],
            'consistent_branding'=>['headline'=>'Brand consistency can be built on','body_template'=>'Consistent naming and presentation make the business easier to recognize across places.'],
        ]],
    ];
}
function ho_diag_weakness_registry(): array {
    return [
        'find_me'=>['label'=>'Find Me','blocks'=>[
            'missing_website'=>['headline'=>'There is no single front door','body_template'=>'A customer looking for {business_name} may not have one clean place to understand what you do, where you work, and how to ask for help.'],
            'facebook_only_presence'=>['headline'=>'A social page is doing too much work','body_template'=>'A Facebook-only presence can leave customers without a clean destination, especially if they are not already using Facebook.'],
            'dead_or_missing_links'=>['headline'=>'The customer path may break','body_template'=>'Broken or missing links can make a good business look harder to reach than it really is.'],
            'domain_missing'=>['headline'=>'A simple domain could help','body_template'=>'Without a memorable web address, customers may have to search again instead of going directly to you.'],
            'search_identity_confusing'=>['headline'=>'The search identity could be clearer','body_template'=>'If the name, location, or public pages do not line up cleanly, customers may hesitate or choose someone easier to verify.'],
        ]],
        'trust_me'=>['label'=>'Trust Me','blocks'=>[
            'few_or_no_photos'=>['headline'=>'Customers may not see enough proof','body_template'=>'When photos are limited or hard to find, customers have to imagine the quality instead of seeing it.'],
            'reviews_not_prominent'=>['headline'=>'Trust signals could be easier to notice','body_template'=>'If reviews or reputation signals are not obvious, customers may miss reasons to trust you.'],
            'stale_or_unclear_activity'=>['headline'=>'Activity may not look current','body_template'=>'If the public presence looks quiet or outdated, customers may wonder whether the business is still active.'],
            'thin_public_presence'=>['headline'=>'There may not be enough public context','body_template'=>'A thin public presence can make a capable local operator look less established than they are.'],
            'inconsistent_brand_signals'=>['headline'=>'The business may look scattered','body_template'=>'Inconsistent naming, visuals, or details can make customers work harder to feel confident.'],
        ]],
        'understand_me'=>['label'=>'Understand Me','blocks'=>[
            'weak_service_list'=>['headline'=>'The service list could be clearer','body_template'=>'A customer should be able to quickly tell what {business_name} does and whether it fits their job.'],
            'unclear_service_scope'=>['headline'=>'The scope may be hard to understand','body_template'=>'If the public information does not clearly explain the kinds of jobs you want, customers may not know whether to reach out.'],
            'category_confusion'=>['headline'=>'The category could be easier to read','body_template'=>'When the business category is not obvious, customers may not connect their need to your service.'],
            'no_examples_of_work'=>['headline'=>'Examples would make the service easier to understand','body_template'=>'A few clear examples can explain the work faster than a long paragraph.'],
        ]],
        'contact_me'=>['label'=>'Contact Me','blocks'=>[
            'contact_path_unclear'=>['headline'=>'The next step could be simpler','body_template'=>'A customer may be interested but still not know the easiest way to ask for help or request a quote.'],
            'no_quote_request_path'=>['headline'=>'There is no obvious quote path','body_template'=>'A simple quote/request path can reduce friction and help customers give you the details you need.'],
            'contact_form_missing'=>['headline'=>'A form could reduce back-and-forth','body_template'=>'Without a simple form, customers may have to call before they know what information you need.'],
            'too_many_contact_paths'=>['headline'=>'Too many paths can still cause friction','body_template'=>'When customers see several scattered contact options, a single recommended next step can make the process feel easier.'],
            'contact_info_hidden'=>['headline'=>'Contact details could be more visible','body_template'=>'If contact information is buried, customers may leave before reaching out.'],
        ]],
        'choose_me'=>['label'=>'Choose Me','blocks'=>[
            'no_clear_differentiator'=>['headline'=>'The reason to choose you could be clearer','body_template'=>'Customers may need a clearer reason to choose {business_name} instead of the next similar option.'],
            'generic_public_presence'=>['headline'=>'The public presence may feel generic','body_template'=>'A generic online presence can make a real local operator look interchangeable.'],
            'quality_not_visible'=>['headline'=>'Quality may not be visible enough','body_template'=>'If customers cannot quickly see proof of quality, they may judge only by convenience or price.'],
            'weak_project_proof'=>['headline'=>'Project proof could be stronger','body_template'=>'Showing the work clearly can make the sale feel safer before anyone calls.'],
        ]],
        'start_me'=>['label'=>'Start Me','blocks'=>[
            'no_clear_next_step'=>['headline'=>'There is not one obvious next step','body_template'=>'Customers should not have to think hard about how to start. One clear button or form can help.'],
            'cta_missing'=>['headline'=>'The call to action could be stronger','body_template'=>'A clear call to action tells customers exactly what to do when they are ready.'],
            'booking_path_confusing'=>['headline'=>'Booking or scheduling may feel unclear','body_template'=>'If scheduling is part of the business, the path should feel simple and obvious.'],
            'customer_has_to_call_without_context'=>['headline'=>'Customers may have to call too early','body_template'=>'Some customers want to understand the service before making a call. A simple front door can answer first questions before they reach out.'],
        ]],
        'remember_me'=>['label'=>'Remember Me','blocks'=>[
            'domain_or_brand_confusion'=>['headline'=>'The name or domain could be easier to remember','body_template'=>'A cleaner name/domain path can make it easier for customers to return later or share the business.'],
            'inconsistent_name_usage'=>['headline'=>'The name should appear consistently','body_template'=>'When a business name appears differently across places, customers may not be sure they found the same business.'],
            'hard_to_remember_url'=>['headline'=>'The web address could be simpler','body_template'=>'A hard-to-remember URL makes it harder for customers to return without searching again.'],
            'weak_visual_identity'=>['headline'=>'A simple visual identity could help','body_template'=>'A lightweight identity direction can make the business look more established without turning it into a giant branding project.'],
        ]],
    ];
}
function ho_diag_recommendation_registry(): array {
    return [
        'find_me'=>['label'=>'Find Me','blocks'=>[
            'simple_front_door'=>['headline'=>'Build one clean front door','body_template'=>'Start with one page that explains who {business_name} is, what you do, where you work, and how customers should take the next step.'],
            'domain_cleanup'=>['headline'=>'Use a cleaner web address','body_template'=>'A simple domain can make the business easier to remember, share, and revisit.'],
            'search_identity_cleanup'=>['headline'=>'Make the public identity easier to connect','body_template'=>'The goal is to make the name, location, and contact path feel consistent wherever customers find you.'],
        ]],
        'trust_me'=>['label'=>'Trust Me','blocks'=>[
            'trust_builder_page'=>['headline'=>'Add trust before the call','body_template'=>'A front-door page can put the most important trust signals in one place before customers reach out.'],
            'visual_proof_upgrade'=>['headline'=>'Make visual proof easier to see','body_template'=>'Photos, examples, or project-style sections can help customers feel safer choosing you.'],
            'review_signal_placement'=>['headline'=>'Put reputation where customers notice it','body_template'=>'If review signals exist, they should be placed where they support the decision instead of hiding in another platform.'],
            'local_proof_section'=>['headline'=>'Show the local connection','body_template'=>'A local proof section can make the business feel more real and nearby.'],
        ]],
        'understand_me'=>['label'=>'Understand Me','blocks'=>[
            'service_list_cleanup'=>['headline'=>'Clarify the service list','body_template'=>'A simple service section can help customers quickly decide whether {business_name} handles their type of job.'],
            'portfolio_first_page'=>['headline'=>'Let examples explain the work','body_template'=>'For visual services, a portfolio-style section can show value faster than a long explanation.'],
            'category_specific_front_door'=>['headline'=>'Use a category-specific front door','body_template'=>'The page should match how customers think about this type of local service, not force them through a generic website.'],
        ]],
        'contact_me'=>['label'=>'Contact Me','blocks'=>[
            'quote_path_cleanup'=>['headline'=>'Make the quote path obvious','body_template'=>'One clear quote/request path can make the next step easier for customers and cleaner for you.'],
            'contact_path_cleanup'=>['headline'=>'Clean up the contact path','body_template'=>'The page should give customers one obvious way to reach out without hunting across platforms.'],
            'simple_request_form'=>['headline'=>'Use a simple request form','body_template'=>'A request form can collect the details you need while keeping the customer path simple.'],
        ]],
        'choose_me'=>['label'=>'Choose Me','blocks'=>[
            'differentiator_section'=>['headline'=>'Add a reason to choose you','body_template'=>'A short differentiator section can explain why {business_name} is a better fit than just another local option.'],
            'project_proof_section'=>['headline'=>'Show proof of work','body_template'=>'A project proof section can make quality easier to understand before customers reach out.'],
            'local_service_positioning'=>['headline'=>'Position the business as a local service choice','body_template'=>'The page should make the local, practical value obvious without overcomplicating the offer.'],
        ]],
        'start_me'=>['label'=>'Start Me','blocks'=>[
            'single_primary_cta'=>['headline'=>'Use one primary next step','body_template'=>'The front door should guide customers toward one main action instead of making them choose from a cluttered list.'],
            'request_quote_flow'=>['headline'=>'Create a request flow','body_template'=>'A request flow can turn customer interest into useful details without making the process feel heavy.'],
            'start_project_section'=>['headline'=>'Explain how to start','body_template'=>'A simple how-to-start section can reduce hesitation and help customers know what happens next.'],
        ]],
        'remember_me'=>['label'=>'Remember Me','blocks'=>[
            'brand_cleanup'=>['headline'=>'Clean up the visual identity','body_template'=>'A lightweight visual direction can help the business feel more established without requiring a full rebrand.'],
            'simple_domain_option'=>['headline'=>'Offer a simpler domain option','body_template'=>'A short domain option can make the business easier to remember and share.'],
            'browser_font_identity'=>['headline'=>'Use a lightweight identity direction','body_template'=>'A browser-font identity can make the front door feel intentional without slowing down the build.'],
        ]],
    ];
}
function ho_diag_preview_direction_registry(): array {
    return [
        'clean_local_service'=>['label'=>'Clean Local Service','description'=>'A simple, trustworthy page for customers who need to understand the service and request help quickly.','default_cta'=>'Request a Quote'],
        'bold_contractor'=>['label'=>'Bold Contractor','description'=>'A stronger service-business layout for hands-on work, repairs, property services, or contractor-style operators.','default_cta'=>'Get An Estimate'],
        'friendly_neighborhood'=>['label'=>'Friendly Neighborhood','description'=>'A warmer local layout for personal, home, cleaning, pet, or family-friendly services.','default_cta'=>'Ask About Availability'],
        'premium_portfolio'=>['label'=>'Premium Portfolio','description'=>'A visual-first direction for photographers, event services, specialty work, and businesses where examples matter.','default_cta'=>'View The Work'],
        'emergency_fast_response'=>['label'=>'Emergency / Fast Response','description'=>'A direct response layout for urgent services where speed and clarity matter.','default_cta'=>'Call Now'],
        'simple_quote_page'=>['label'=>'Simple Quote Page','description'=>'A stripped-down page focused on getting a customer from interest to quote request.','default_cta'=>'Request A Quote'],
        'family_owned_traditional'=>['label'=>'Family-Owned Traditional','description'=>'A grounded local-business direction. Use only when the public facts support family/traditional positioning.','default_cta'=>'Contact Us'],
        'modern_minimal'=>['label'=>'Modern Minimal','description'=>'A clean and restrained direction for businesses that benefit from a polished, uncluttered feel.','default_cta'=>'Start Here'],
        'before_after_gallery'=>['label'=>'Before & After Gallery','description'=>'A visual proof direction for cleaning, pressure washing, landscaping, remodeling, detailing, and transformation work.','default_cta'=>'See What We Can Do'],
        'seasonal_service'=>['label'=>'Seasonal Service','description'=>'A schedule-focused direction for lawn care, snow removal, seasonal maintenance, or recurring service work.','default_cta'=>'Get On The Schedule'],
    ];
}
function ho_diag_offer_registry(): array {
    return [
        'standard_front_door'=>['label'=>'Standard Front Door','price_label'=>'$499 setup','summary'=>'One clean customer-facing front door with services, trust sections, and a clear contact/request path.','cta'=>'Start My Front Door'],
        'managed_front_door'=>['label'=>'Managed Front Door','price_label'=>'$999 setup + 3 months included','summary'=>'A more hands-on front door package with ongoing updates after launch.','cta'=>'Ask About Managed'],
    ];
}
function ho_diag_claim_value(array $business, string $fieldKey): string {
    foreach (($business['_claims'] ?? []) as $claim) {
        if (!is_array($claim)) continue;
        if ((string)($claim['field_key'] ?? '') === $fieldKey) return trim((string)($claim['normalized_value'] ?? $claim['claim_value'] ?? ''));
    }
    return '';
}
function ho_diag_json_claim(array $business, string $fieldKey): array {
    $raw = ho_diag_claim_value($business, $fieldKey);
    $decoded = $raw !== '' ? json_decode($raw, true) : [];
    return is_array($decoded) ? $decoded : [];
}
function ho_diag_all_block_keys(): array {
    $out=['strengths'=>[],'weaknesses'=>[],'recommendations'=>[],'preview_directions'=>array_keys(ho_diag_preview_direction_registry()),'offers'=>array_keys(ho_diag_offer_registry())];
    foreach(ho_diag_strength_registry() as $g) $out['strengths']=array_merge($out['strengths'],array_keys($g['blocks']));
    foreach(ho_diag_weakness_registry() as $g) $out['weaknesses']=array_merge($out['weaknesses'],array_keys($g['blocks']));
    foreach(ho_diag_recommendation_registry() as $g) $out['recommendations']=array_merge($out['recommendations'],array_keys($g['blocks']));
    return $out;
}
function ho_diag_is_contact_ready(array $business): bool {
    if (in_array(ho_diag_claim_value($business,'diagnosis_status'), ['diagnosis_ready','preview_ready'], true)) return false;
    if (function_exists('ho_salesportal_ui_queue_key')) { try { $q=ho_salesportal_ui_queue_key($business); if ($q==='contact_ready'||$q==='ready_contact') return true; } catch(Throwable $e){} }
    $r=strtolower(ho_diag_claim_value($business,'contact_readiness'));
    $m=strtolower(ho_diag_claim_value($business,'best_contact_method'));
    if (str_contains($r,'ready')||str_contains($r,'usable')) return true;
    if ($m!=='' && !in_array($m,['none','missing','unknown','needs_manual_check','manual_check'],true)) return true;
    if (ho_diag_claim_value($business,'email_address')!=='') return true;
    return false;
}
function ho_diag_business_summary(array $b): array {
    return [
        'business_id'=>(int)($b['id']??0),'business_slug'=>(string)($b['business_slug']??''),'business_name'=>(string)($b['business_name_current']??''),
        'business_type'=>(string)($b['business_type']??'local_service'),'city'=>(string)($b['location_city']??''),'state'=>(string)($b['location_state']??'IN'),
        'service_area'=>(string)($b['service_area_text']??'Indiana'),'website_url'=>(string)($b['website_url']??''),
        'contact_readiness'=>ho_diag_claim_value($b,'contact_readiness'),'best_contact_method'=>ho_diag_claim_value($b,'best_contact_method'),
        'email_address'=>ho_diag_claim_value($b,'email_address'),'phone_number'=>ho_diag_claim_value($b,'phone_number'),
        'google_profile_url'=>ho_diag_claim_value($b,'google_profile_url'),'facebook_url'=>ho_diag_claim_value($b,'facebook_url'),
        'primary_sales_angle'=>ho_diag_claim_value($b,'primary_sales_angle'),'recommended_design'=>ho_diag_claim_value($b,'recommended_design')
    ];
}
function ho_diag_batch_prompt(array $businesses, int $limit=25): string {
    $businesses=array_slice($businesses,0,$limit);
    $payload=['task'=>'assign_front_door_diagnosis_keys','businesses'=>array_map('ho_diag_business_summary',$businesses),'allowed_keys'=>ho_diag_all_block_keys(),'output_contract'=>['diagnosis_batch'=>['batch_type'=>'front_door_diagnosis_keys'],'diagnoses'=>[['business_id'=>0,'business_slug'=>'existing-business-slug','business_name'=>'Business Name','diagnosis_status'=>'diagnosis_ready','strength_keys'=>['local_identity_clear','service_category_clear'],'weakness_keys'=>['contact_path_unclear','weak_service_list'],'recommendation_keys'=>['simple_front_door','quote_path_cleanup'],'primary_offer_path'=>'standard_front_door','preview_direction_keys'=>['clean_local_service','simple_quote_page','friendly_neighborhood'],'risk_flags'=>[],'notes'=>'Internal notes only. No custom sales copy.']]]];
    return "You are assigning Hoosier Online Front Door diagnosis keys for Contact Ready Indiana local service businesses.\n\nReturn ONLY valid JSON. Do not include markdown. Use only allowed keys. Do not write custom sales copy. Pick 2-5 strengths, 2-5 weaknesses, 2-4 recommendations, exactly 3 preview_direction_keys. Set primary_offer_path to standard_front_door unless managed_front_door is clearly better.\n\n".json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
}
function ho_diag_claim(string $fieldKey, $value, string $note='Diagnosis result imported.'): array {
    if (is_array($value)||is_object($value)) $value=json_encode($value,JSON_UNESCAPED_SLASHES);
    $value=trim((string)$value);
    return ['field_key'=>$fieldKey,'claim_value'=>$value,'normalized_value'=>$value,'confidence_level'=>'inferred','confidence_score'=>80,'claim_status'=>'active','source_type'=>'manual_observation','source_url'=>'','source_label'=>'Diagnosis workbench','evidence_note'=>$note,'supports_me_category'=>'fix_me','supports_requirement_key'=>'fix_me.customer_path_mess','evidence_source_index'=>0];
}
function ho_diag_payloads_from_input(array $decoded): ?array {
    $batchType=strtolower(trim((string)($decoded['diagnosis_batch']['batch_type']??$decoded['batch_type']??'')));
    if ($batchType!=='front_door_diagnosis_keys' && !isset($decoded['diagnoses'])) return null;
    if (!isset($decoded['diagnoses']) || !is_array($decoded['diagnoses'])) throw new RuntimeException('Diagnosis input is missing diagnoses[].');
    $payloads=[];
    foreach($decoded['diagnoses'] as $d){ if(!is_array($d)) continue; $id=(int)($d['business_id']??0); $slug=trim((string)($d['business_slug']??'')); $name=trim((string)($d['business_name']??'')); if($name===''&&$slug!=='')$name=ucwords(str_replace('-',' ',$slug));
        $dirs=$d['preview_direction_keys']??[]; if(!is_array($dirs))$dirs=[]; $dirs=array_values(array_slice($dirs,0,3)); $status=(string)($d['diagnosis_status']??'diagnosis_ready');
        $business=['business_slug'=>$slug,'business_name_current'=>$name,'business_type'=>(string)($d['business_type']??'local_service'),'location_city'=>(string)($d['city']??''),'location_state'=>(string)($d['state']??'IN'),'service_area_text'=>(string)($d['service_area']??'Indiana')]; if($id>0)$business['id']=$id;
        $claims=[ho_diag_claim('diagnosis_status',$status),ho_diag_claim('strength_keys_json',$d['strength_keys']??[]),ho_diag_claim('weakness_keys_json',$d['weakness_keys']??[]),ho_diag_claim('recommendation_keys_json',$d['recommendation_keys']??[]),ho_diag_claim('primary_offer_path',$d['primary_offer_path']??'standard_front_door'),ho_diag_claim('preview_direction_keys_json',$dirs),ho_diag_claim('diagnosis_risk_flags_json',$d['risk_flags']??[]),ho_diag_claim('front_door_preview_status',$status==='diagnosis_ready'?'preview_ready':'diagnosis_review')];
        $payloads[]=['business'=>$business,'evidence_sources'=>[['source_type'=>'manual_observation','source_url'=>'','source_title'=>'Front Door diagnosis keys','capture_status'=>'manual','raw_excerpt'=>json_encode($d,JSON_UNESCAPED_SLASHES),'notes'=>'Diagnosis keys imported for pre-sale /go page. No outreach or payment action occurred.']],'claims'=>$claims,'marketing_clearance'=>['marketing_clearance_status'=>'contact_ready','marketing_clearance_score'=>85,'recommended_package'=>(string)($d['primary_offer_path']??'standard_front_door'),'recommended_design'=>implode(',',$dirs),'reason'=>'Front Door diagnosis keys imported.'],'notes'=>['Front Door diagnosis imported. Status: '.$status]];
    }
    return $payloads;
}
function ho_diag_front_door_contract(): array {
    return ['route'=>'/go/{slug}','purpose'=>'Personalized pre-sale Front Door Preview page.','sections'=>['personal_intro','what_we_noticed','strengths','weaknesses','recommended_fixes','three_preview_directions','simple_offer','cta'],'data_sources'=>['business facts','strength_keys_json','weakness_keys_json','recommendation_keys_json','primary_offer_path','preview_direction_keys_json'],'not_in_scope'=>['payment','outreach sending','SMS','AI calls','post-sale customization','full 10-design dashboard']];
}

/**
 * v123a helper: normalize pasted GPT JSON before decoding.
 * Handles common paste wrappers: ```json fences, leading notes, trailing notes, BOM.
 */
function ho_diag_clean_pasted_json(string $raw): string {
    $raw = trim($raw);
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $m)) {
        $raw = trim($m[1]);
    }

    $firstObj = strpos($raw, '{');
    $firstArr = strpos($raw, '[');

    if ($firstObj === false && $firstArr === false) {
        return $raw;
    }

    if ($firstObj === false) {
        $start = $firstArr;
        $endChar = ']';
    } elseif ($firstArr === false) {
        $start = $firstObj;
        $endChar = '}';
    } else {
        $start = min($firstObj, $firstArr);
        $endChar = ($start === $firstObj) ? '}' : ']';
    }

    $end = strrpos($raw, $endChar);
    if ($end !== false && $end >= $start) {
        $raw = substr($raw, $start, $end - $start + 1);
    }

    return trim($raw);
}

function ho_diag_decode_pasted_json(string $raw): array {
    $clean = ho_diag_clean_pasted_json($raw);
    try {
        $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Diagnosis JSON syntax error after cleanup: ' . $e->getMessage() . '. Paste only the JSON object containing diagnosis_batch and diagnoses[].');
    }
    if (!is_array($decoded)) {
        throw new RuntimeException('Diagnosis JSON decoded, but did not produce an object/array.');
    }
    return $decoded;
}

?>
