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

// start.php fires this endpoint async (1.5s curl timeout, result ignored) —
// keep running after the caller disconnects so the research completes.
ignore_user_abort(true);

header('Content-Type: application/json');

function lr_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    lr_out(405, ['ok' => false, 'error' => 'POST only.']);
}

// Legacy server config file (still honored); DB config is preferred and loaded
// via ho_llm_boot() once $pdo is available, just below.
$llmConfigPath = '/home1/spofnkte/llm-config.php';
if (is_file($llmConfigPath)) require_once $llmConfigPath;

try {
    $pdo = ho_db();
} catch (Throwable) {
    lr_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
}

// Seed the AI engine from DB settings, then confirm a provider is configured.
ho_llm_boot($pdo);
$llmCfg = ho_llm_settings();
if (($llmCfg['key'] ?? '') === '') {
    lr_out(503, ['ok' => false, 'error' => 'No AI engine configured. Add a key in the cockpit (Send → Autopilot → AI engine).']);
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
      AND b.pipeline_status NOT IN ('pitched','converted','not_a_fit','excluded')
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

@set_time_limit(180);

// Single source of truth for the Anthropic call lives in ho-model.php — the
// web-search messages call + JSON extraction are shared with autopilot.
$r = ho_llm_call(
    $prompt,
    'You are a business research assistant. Use web search to find accurate, current data. Return ONLY the JSON object requested — no explanation, no markdown, no preamble.'
);
if (!$r['ok']) {
    lr_out(502, ['ok' => false, 'error' => $r['error']]);
}
$jsonStr = ho_llm_extract_json($r['text']);
if ($jsonStr === null) {
    lr_out(502, ['ok' => false, 'error' => 'No JSON found in response.', 'snippet' => substr($r['text'], 0, 300)]);
}

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
