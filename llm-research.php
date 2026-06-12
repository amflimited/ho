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

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'POST' && $method !== 'GET') {
    lr_out(405, ['ok' => false, 'error' => 'GET or POST only.']);
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

// Auth: same key as gpt-import.php. Header for POST; ?key= allowed for the GET poll.
$configuredKey = ho_get_setting($pdo, 'gpt_import_key');
if ($configuredKey === '') {
    lr_out(503, ['ok' => false, 'error' => 'Import key not configured.']);
}
$givenKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($givenKey === '' && preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $givenKey = trim($m[1]);
}
if ($givenKey === '' && $method === 'GET') {
    $givenKey = trim((string)($_GET['key'] ?? ''));
}
if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
    lr_out(401, ['ok' => false, 'error' => 'Unauthorized.']);
}

// ── GET ?check=BIZID — lightweight status poll (no AI work) ──────────────────
// The browser fires the slow research as fire-and-forget, then polls here. We
// stash each run's outcome in app_settings (key llmres_<id>) so the poll can
// report the real result — including errors — even though the work ran after
// the client's connection closed. Falls back to the research-record state.
if ($method === 'GET') {
    $checkId = (int)($_GET['check'] ?? 0);
    if ($checkId === 0) lr_out(400, ['ok' => false, 'error' => 'check id required.']);

    $stash = ho_get_setting($pdo, 'llmres_' . $checkId);
    if ($stash !== '') {
        $d = json_decode($stash, true);
        if (is_array($d) && !empty($d['done'])) {
            lr_out(200, ['ok' => true, 'done' => true, 'result_ok' => (bool)($d['ok'] ?? false), 'message' => (string)($d['msg'] ?? '')]);
        }
    }
    // Fallback: consider it done if the research record is now complete.
    $st = $pdo->prepare("
        SELECT b.pipeline_status, r.id AS rid, r.has_contact_form
        FROM businesses b
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.id = ? LIMIT 1
    ");
    $st->execute([$checkId]);
    $row = $st->fetch();
    if (!$row) lr_out(404, ['ok' => false, 'error' => 'Business not found.', 'done' => true]);
    $done = ($row['rid'] !== null && $row['has_contact_form'] !== null)
         || in_array((string)$row['pipeline_status'], ['pitched','converted','not_a_fit','excluded','preview_ready','enhancement_ready','needs_contact'], true);
    lr_out(200, ['ok' => true, 'done' => $done, 'message' => $done ? 'Done.' : 'Working…']);
}

// ── POST — start a research run ──────────────────────────────────────────────
// Seed the AI engine from DB settings, then confirm a provider is configured.
ho_llm_boot($pdo);
$llmCfg = ho_llm_settings();
if (($llmCfg['key'] ?? '') === '') {
    lr_out(503, ['ok' => false, 'error' => 'No AI engine configured. Add a key in the cockpit (Send → Autopilot → AI engine).']);
}

$raw   = (string)file_get_contents('php://input');
$body  = json_decode($raw, true);
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

// Mark in-progress (clears any prior stashed outcome so the poll waits for THIS run).
ho_set_setting($pdo, 'llmres_' . $bizId, json_encode(['done' => false, 'at' => time()]));

// Respond to the browser NOW and finish the HTTP request, then keep researching.
// Shared hosting severs long connections; this hands the work to the background
// so the phone never waits on a 30–90s call.
$startedPayload = json_encode([
    'ok'      => true,
    'started' => true,
    'message' => "Researching {$biz['business_name']}…",
]);
while (ob_get_level() > 0) { @ob_end_clean(); }
header('Content-Type: application/json');
header('Content-Length: ' . strlen($startedPayload));
header('Connection: close');
echo $startedPayload;
@ob_flush();
@flush();
if (function_exists('fastcgi_finish_request'))      { fastcgi_finish_request(); }
elseif (function_exists('litespeed_finish_request')) { litespeed_finish_request(); }

// ── Background work (client already has its response) ─────────────────────────
@set_time_limit(420); // 280 work + up to 2×62s Gemini rate-limit waits

$prompt = ho_generate_research_prompt([$biz]);
$prompt = preg_replace(
    '/DELIVERY:.*$/s',
    'Return the complete JSON object starting with { "research_results": [...] } as your response text. No explanation, no markdown fences, no file saving — just the raw JSON.',
    $prompt
) ?? $prompt;

$ok = false;
$msg = '';
try {
    $r = ho_llm_call(
        $prompt,
        'You are a business research assistant. Use web search to find accurate, current data. Return ONLY the JSON object requested — no explanation, no markdown, no preamble.'
    );
    if (!$r['ok']) {
        $msg = $r['error'];
    } else {
        $jsonStr = ho_llm_extract_json($r['text']);
        if ($jsonStr === null) {
            $msg = 'No JSON found in AI response.';
        } else {
            $result = ho_import_research_json($pdo, $jsonStr);
            $ok  = ($result['updated'] ?? 0) > 0;
            $msg = $ok
                ? "Researched {$biz['business_name']} — moved to Send tab."
                : ('Imported but nothing updated' . (!empty($result['errors']) ? ': ' . implode('; ', (array)$result['errors']) : '.'));
        }
    }
} catch (Throwable $e) {
    $msg = 'Failed: ' . $e->getMessage();
}

// Stash the outcome for the status poll to read.
ho_set_setting($pdo, 'llmres_' . $bizId, json_encode(['done' => true, 'ok' => $ok, 'msg' => $msg, 'at' => time()]));
exit;
