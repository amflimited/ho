<?php
declare(strict_types=1);
/**
 * Zero-touch research endpoint.
 *
 * POST { "business_id": N }  with X-Api-Key matching app_settings.gpt_import_key.
 * Loads the business, generates a research prompt, calls the Anthropic Messages API
 * with the web_search tool, extracts the JSON response, and pipes it through
 * ho_import_research_json() — same importer used by gpt-import.php.
 *
 * Config: /home1/spofnkte/llm-config.php must define LLM_API_KEY and optionally LLM_MODEL.
 * This file is NEVER committed.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

header('Content-Type: application/json');

function lr_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lr_out(405, ['ok' => false, 'error' => 'POST only.']);
}

$llmConfigPath = '/home1/spofnkte/llm-config.php';
if (!is_file($llmConfigPath)) {
    lr_out(503, ['ok' => false, 'error' => 'LLM config not found.']);
}
require_once $llmConfigPath;
if (!defined('LLM_API_KEY') || LLM_API_KEY === '') {
    lr_out(503, ['ok' => false, 'error' => 'LLM API key not configured.']);
}

try {
    $pdo = ho_db();
} catch (Throwable) {
    lr_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
}

// Auth: same key as gpt-import.php
$configuredKey = ho_get_setting($pdo, 'gpt_import_key');
if ($configuredKey === '') {
    lr_out(503, ['ok' => false, 'error' => 'Import key not configured.']);
}
$givenKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($givenKey === '' && preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $givenKey = trim($m[1]);
}
if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
    lr_out(401, ['ok' => false, 'error' => 'Unauthorized.']);
}

$raw  = (string)file_get_contents('php://input');
$body = json_decode($raw, true);
$bizId = (int)($body['business_id'] ?? 0);
if ($bizId === 0) {
    lr_out(400, ['ok' => false, 'error' => 'business_id required.']);
}

// Load the business — must be in the research queue
$s = $pdo->prepare("
    SELECT b.*, c.name AS category_name, c.slug AS category_slug, c.typical_services
    FROM businesses b
    JOIN categories c ON c.id = b.category_id
    WHERE b.id = ?
      AND b.pipeline_status IN ('identified','researched')
    LIMIT 1
");
$s->execute([$bizId]);
$biz = $s->fetch();
if (!$biz) {
    lr_out(404, ['ok' => false, 'error' => "Business {$bizId} not found or not in research queue."]);
}

// Build research prompt for this one business, then strip the ChatGPT-specific
// DELIVERY section and replace it with a direct-JSON instruction for the API.
$prompt = ho_generate_research_prompt([$biz]);
$prompt = preg_replace(
    '/DELIVERY:.*$/s',
    'Return the complete JSON object starting with { "research_results": [...] } as your response text. No explanation, no markdown fences, no file saving — just the raw JSON.',
    $prompt
) ?? $prompt;

$model  = defined('LLM_MODEL') ? LLM_MODEL : 'claude-sonnet-4-6';
$apiKey = LLM_API_KEY;

@set_time_limit(120);

$requestPayload = json_encode([
    'model'      => $model,
    'max_tokens' => 8000,
    'system'     => 'You are a business research assistant. Use web search to find accurate, current data. Return ONLY the JSON object requested — no explanation, no markdown, no preamble.',
    'tools'      => [['type' => 'web_search_20250305', 'name' => 'web_search']],
    'messages'   => [['role' => 'user', 'content' => $prompt]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $requestPayload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
        'anthropic-beta: web-search-2025-03-05',
    ],
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_CONNECTTIMEOUT => 15,
]);
$resp     = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($resp === false || $resp === '') {
    lr_out(502, ['ok' => false, 'error' => 'cURL error: ' . $curlErr]);
}
if ($httpCode !== 200) {
    $apiErr = json_decode((string)$resp, true);
    lr_out(502, ['ok' => false, 'error' => 'Anthropic API error ' . $httpCode . ': ' . ($apiErr['error']['message'] ?? substr((string)$resp, 0, 200))]);
}

$apiResp = json_decode((string)$resp, true);
if (!is_array($apiResp)) {
    lr_out(502, ['ok' => false, 'error' => 'Anthropic API returned non-JSON.']);
}

// Extract all text blocks from the response (may be interleaved with tool_use blocks)
$textParts = [];
foreach ((array)($apiResp['content'] ?? []) as $block) {
    if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
        $textParts[] = $block['text'];
    }
}
$text = implode('', $textParts);

if ($text === '') {
    lr_out(502, ['ok' => false, 'error' => 'No text content in Anthropic response.']);
}

// Extract the JSON object from the response text
$jsonStart = strpos($text, '{');
$jsonEnd   = strrpos($text, '}');
if ($jsonStart === false || $jsonEnd === false || $jsonEnd <= $jsonStart) {
    lr_out(502, ['ok' => false, 'error' => 'No JSON found in response.', 'snippet' => substr($text, 0, 300)]);
}
$jsonStr = substr($text, $jsonStart, $jsonEnd - $jsonStart + 1);

try {
    $result = ho_import_research_json($pdo, $jsonStr);
    lr_out(200, [
        'ok'      => true,
        'updated' => $result['updated'],
        'errors'  => $result['errors'],
        'message' => "Researched {$biz['business_name']}." . ($result['updated'] > 0 ? ' Moved to Send tab.' : ''),
    ]);
} catch (Throwable $e) {
    lr_out(500, ['ok' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
}
