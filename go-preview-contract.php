<?php
/** v123 Front Door Preview Renderer Contract. No routing/payment/outreach. */
declare(strict_types=1);
require_once __DIR__ . '/diagnosis-model.php';
function ho_frontdoor_preview_contract(): array { return ho_diag_front_door_contract(); }
function ho_frontdoor_preview_assemble(array $business): array {
    $offerKey = ho_diag_claim_value($business,'primary_offer_path') ?: 'standard_front_door';
    $offers = ho_diag_offer_registry();
    return [
        'business_name'=>(string)($business['business_name_current'] ?? 'this business'),
        'intro'=>'We were looking through Indiana local service businesses and put together a quick online front-door preview.',
        'strength_keys'=>array_slice(ho_diag_json_claim($business,'strength_keys_json'),0,5),
        'weakness_keys'=>array_slice(ho_diag_json_claim($business,'weakness_keys_json'),0,5),
        'recommendation_keys'=>array_slice(ho_diag_json_claim($business,'recommendation_keys_json'),0,4),
        'preview_direction_keys'=>array_slice(ho_diag_json_claim($business,'preview_direction_keys_json'),0,3),
        'offer'=>$offers[$offerKey] ?? $offers['standard_front_door'],
        'cta'=>['primary'=>($offers[$offerKey]['cta'] ?? 'Start My Front Door'),'secondary'=>'Ask Adam A Question','email'=>'adam@hoosieronline.com'],
    ];
}
?>
