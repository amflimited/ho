<?php
declare(strict_types=1);
/**
 * AI pitch draft endpoint.
 *
 * POST { "business_id": N }                        — generate an AI-written pitch draft and stash it.
 * POST { "business_id": N, "action": "skip" }      — delete the stashed draft for this business.
 *
 * Auth: X-Api-Key header or Bearer token (same key as llm-research.php).
 * No fire-and-forget needed — text-only generation with $search=false runs in ~2-5s.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

header('Content-Type: application/json');

function lp_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lp_out(405, ['ok' => false, 'error' => 'POST only.']);
}

try {
    $pdo = ho_db();
} catch (Throwable) {
    lp_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
}

$configuredKey = ho_get_setting($pdo, 'gpt_import_key');
if ($configuredKey === '') {
    lp_out(503, ['ok' => false, 'error' => 'Import key not configured.']);
}
$givenKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($givenKey === '' && preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $givenKey = trim($m[1]);
}
if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
    lp_out(401, ['ok' => false, 'error' => 'Unauthorized.']);
}

$raw    = (string)file_get_contents('php://input');
$body   = json_decode($raw, true);
$bizId  = (int)($body['business_id'] ?? 0);
$action = trim((string)($body['action'] ?? ''));

if ($bizId === 0) {
    lp_out(400, ['ok' => false, 'error' => 'business_id required.']);
}

if ($action === 'skip') {
    ho_del_setting($pdo, 'pitchdraft_' . $bizId);
    lp_out(200, ['ok' => true, 'deleted' => true]);
}

ho_llm_boot($pdo);
$llmCfg = ho_llm_settings();
if (($llmCfg['key'] ?? '') === '') {
    lp_out(503, ['ok' => false, 'error' => 'No AI engine configured. Add a key in the cockpit (Send → Autopilot → AI engine).']);
}

$s = $pdo->prepare("
    SELECT b.id, b.business_name, b.location_city, b.owner_first_name,
           b.email_address, b.phone_number, b.facebook_url, b.website_url,
           b.pipeline_status,
           c.name AS category_name, c.slug AS category_slug,
           p.preview_slug, p.preview_type,
           r.google_review_count, r.google_rating,
           r.review_quote_1, r.review_quote_1_author,
           r.competitor_name, r.competitor_google_rating, r.competitor_review_count,
           r.years_in_business, r.has_website, r.website_quality, r.verified_at
    FROM businesses b
    JOIN categories c ON c.id = b.category_id
    LEFT JOIN previews p ON p.business_id = b.id AND p.preview_status = 'ready'
    LEFT JOIN research_records r ON r.business_id = b.id
    WHERE b.id = ?
      AND b.pipeline_status IN ('preview_ready','enhancement_ready')
    LIMIT 1
");
$s->execute([$bizId]);
$biz = $s->fetch();
if (!$biz) {
    lp_out(404, ['ok' => false, 'error' => "Business {$bizId} not found or not pitch-ready."]);
}

$previewType = (string)($biz['preview_type'] ?? 'site_build');
$slug        = (string)($biz['preview_slug'] ?? '');
$kind        = $previewType === 'enhancement' ? 'enhancement' : 'site';
$siteBase    = trim(ho_get_setting($pdo, 'site_base'));
if ($siteBase === '') $siteBase = 'https://hoosieronline.com';
$previewUrl  = $siteBase . '/go/' . $slug;

$result = ho_llm_generate_pitch($pdo, $biz, $previewUrl, $kind);

$draft = [
    'biz_id'       => $bizId,
    'biz_name'     => (string)$biz['business_name'],
    'biz_email'    => (string)($biz['email_address'] ?? ''),
    'biz_slug'     => $slug,
    'biz_city'     => (string)($biz['location_city'] ?? ''),
    'category'     => (string)$biz['category_name'],
    'kind'         => $kind,
    'subject'      => $result['subject'],
    'body'         => $result['body'],
    'preview_url'  => $previewUrl,
    'fallback'     => $result['fallback'],
    'generated_at' => time(),
];
ho_set_setting($pdo, 'pitchdraft_' . $bizId, json_encode($draft));

lp_out(200, [
    'ok'       => true,
    'biz_id'   => $bizId,
    'biz_name' => $draft['biz_name'],
    'biz_email'=> $draft['biz_email'],
    'biz_city' => $draft['biz_city'],
    'category' => $draft['category'],
    'kind'     => $kind,
    'subject'  => $result['subject'],
    'body'     => $result['body'],
    'fallback' => $result['fallback'],
]);
