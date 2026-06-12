<?php
declare(strict_types=1);
/**
 * Zero-touch sourcing endpoint.
 *
 * POST { "category_id": N, "area": "...", "count": N }  with X-Api-Key header.
 * GET  ?check=RUNID&key=KEY  — lightweight status poll.
 *
 * Fires the Deep Hunt (sources + researches in one AI pass), imports results,
 * and stashes the outcome in app_settings (key llmsrc_<runId>) for the poll.
 * Outcome survives connection close — same fire-and-forget pattern as llm-research.php.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

ignore_user_abort(true);

header('Content-Type: application/json');

function ls_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method !== 'POST' && $method !== 'GET') {
    ls_out(405, ['ok' => false, 'error' => 'GET or POST only.']);
}

$llmConfigPath = '/home1/spofnkte/llm-config.php';
if (is_file($llmConfigPath)) require_once $llmConfigPath;

try {
    $pdo = ho_db();
} catch (Throwable) {
    ls_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
}

// Auth: same key as llm-research.php
$configuredKey = ho_get_setting($pdo, 'gpt_import_key');
if ($configuredKey === '') {
    ls_out(503, ['ok' => false, 'error' => 'Import key not configured.']);
}
$givenKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($givenKey === '' && preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $givenKey = trim($m[1]);
}
if ($givenKey === '' && $method === 'GET') {
    $givenKey = trim((string)($_GET['key'] ?? ''));
}
if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
    ls_out(401, ['ok' => false, 'error' => 'Unauthorized.']);
}

// ── GET ?check=RUNID — status poll ───────────────────────────────────────────
if ($method === 'GET') {
    $checkId = (int)($_GET['check'] ?? 0);
    if ($checkId === 0) ls_out(400, ['ok' => false, 'error' => 'check id required.']);

    $stash = ho_get_setting($pdo, 'llmsrc_' . $checkId);
    if ($stash !== '') {
        $d = json_decode($stash, true);
        if (is_array($d) && !empty($d['done'])) {
            ls_out(200, [
                'ok'        => true,
                'done'      => true,
                'result_ok' => (bool)($d['ok'] ?? false),
                'message'   => (string)($d['msg'] ?? ''),
                'created'   => (int)($d['created'] ?? 0),
                'skipped'   => (int)($d['skipped'] ?? 0),
            ]);
        }
    }
    // Fallback: check the source_run status directly
    $st = $pdo->prepare("SELECT status, businesses_found FROM source_runs WHERE id = ? LIMIT 1");
    $st->execute([$checkId]);
    $row = $st->fetch();
    if (!$row) ls_out(404, ['ok' => false, 'error' => 'Run not found.', 'done' => true]);
    $done = in_array((string)$row['status'], ['sourced', 'imported'], true);
    ls_out(200, ['ok' => true, 'done' => $done, 'message' => $done ? 'Done.' : 'Working…']);
}

// ── POST — start a sourcing run ──────────────────────────────────────────────
ho_llm_boot($pdo);
$llmCfg = ho_llm_settings();
if (($llmCfg['key'] ?? '') === '') {
    ls_out(503, ['ok' => false, 'error' => 'No AI engine configured. Add a key in the cockpit (Send → Autopilot → AI engine).']);
}

$raw   = (string)file_get_contents('php://input');
$body  = json_decode($raw, true);
$catId = (int)($body['category_id'] ?? 0);
$area  = trim((string)($body['area'] ?? ''));
$count = max(5, min(12, (int)($body['count'] ?? 8)));

if ($catId === 0 || $area === '') {
    ls_out(400, ['ok' => false, 'error' => 'category_id and area required.']);
}

$cs = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND active = 1 LIMIT 1");
$cs->execute([$catId]);
$category = $cs->fetch();
if (!$category) {
    ls_out(404, ['ok' => false, 'error' => 'Category not found or inactive.']);
}

$validRegions = array_keys(ho_indiana_regions());
if (!in_array($area, $validRegions, true)) {
    ls_out(400, ['ok' => false, 'error' => 'Invalid region.']);
}

// Create the source_run record; mark in-progress for the poll and the console
$runId = ho_create_source_run($pdo, $catId, $area, $count);
ho_set_setting($pdo, 'llmsrc_' . $runId, json_encode(['done' => false, 'at' => time()]));
ho_set_setting($pdo, 'llmsrc_active', json_encode(['run_id' => $runId, 'cat' => $category['name'], 'area' => $area, 'done' => false, 'at' => time()]));

// Respond to the browser NOW then research in the background
$startedPayload = json_encode([
    'ok'      => true,
    'started' => true,
    'run_id'  => $runId,
    'message' => "Sourcing {$category['name']} in {$area}…",
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

// ── Background work (client already has its response) ────────────────────────
@set_time_limit(420); // 280 work + up to 2×62s Gemini rate-limit waits

$ok        = false;
$msg       = '';
$created   = 0;
$skipped   = 0;

try {
    $exclusions = ho_get_known_business_names($pdo, $catId, $area);
    $prompt = ho_generate_hunt_prompt(
        ['name' => (string)$category['name'], 'typical_services' => (string)($category['typical_services'] ?? '')],
        $area,
        $count,
        $exclusions,
        $runId
    );
    $prompt = preg_replace(
        '/DELIVERY:.*$/s',
        'Return the complete JSON object starting with { "hunt_results": [...] } as your response text. No explanation, no markdown fences, no file saving — just the raw JSON.',
        $prompt
    ) ?? $prompt;

    $r = ho_llm_call(
        $prompt,
        'You are a business sourcing assistant. Use web search to find real, verifiable local businesses. Return ONLY the JSON object requested — no explanation, no markdown, no preamble.'
    );

    if (!$r['ok']) {
        $msg = $r['error'];
    } else {
        $jsonStr = ho_llm_extract_json($r['text']);
        if ($jsonStr === null) {
            $msg = 'No JSON found in AI response.';
        } else {
            $result    = ho_import_hunt_json($pdo, $runId, $jsonStr);
            $created   = (int)($result['created']   ?? 0);
            $refreshed = (int)($result['refreshed'] ?? 0);
            $skipped   = (int)($result['skipped']   ?? 0);
            $ok        = ($created + $refreshed) > 0;
            if ($ok) {
                $parts = [];
                if ($created)   $parts[] = "{$created} new";
                if ($refreshed) $parts[] = "{$refreshed} refreshed";
                $leadWord = ($created + $refreshed === 1) ? 'lead' : 'leads';
                $msg = implode(' + ', $parts) . " {$leadWord} in {$category['name']} / {$area}." .
                       ($skipped > 0 ? " ({$skipped} skipped as dupes or low-confidence)" : '') .
                       ' Refresh to see them.';
            } else {
                $errSuffix = !empty($result['errors']) ? ': ' . implode('; ', (array)$result['errors']) : '.';
                $msg = "No new leads found{$errSuffix} Try a different region or category.";
            }
        }
    }
} catch (Throwable $e) {
    $msg = 'Failed: ' . $e->getMessage();
}

$donePayload = ['done' => true, 'ok' => $ok, 'msg' => $msg, 'created' => $created, 'skipped' => $skipped, 'at' => time()];
ho_set_setting($pdo, 'llmsrc_' . $runId, json_encode($donePayload));
// Update the console stash with cat/area so the activity row stays readable
ho_set_setting($pdo, 'llmsrc_active', json_encode(array_merge($donePayload, ['cat' => $category['name'], 'area' => $area])));
exit;
