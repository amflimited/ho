<?php
declare(strict_types=1);
/**
 * GPT task-fetch endpoint.
 *
 * The Custom GPT Action calls this to retrieve a task prompt by UID so Adam
 * only needs to type a tiny launcher instruction — no giant prompt in a URL.
 *
 * GET /gpt-task.php?task_uid=abc123   OR
 * POST {"task_uid":"abc123"}          — both accepted
 * GET /gpt-task.php                   — returns the newest 'ready' task
 *
 * Auth: X-Api-Key header (or Authorization: Bearer, or ?key=) matched against
 * app_settings row 'gpt_import_key'. Same key as gpt-import.php.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function gt_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = ho_db();
} catch (Throwable $e) {
    gt_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
}

// ── Auth ──────────────────────────────────────────────────────────────────────
$configuredKey = ho_get_setting($pdo, 'gpt_import_key');
if ($configuredKey === '') {
    gt_out(503, ['ok' => false, 'error' => 'Auto-import is not configured.']);
}
$givenKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($givenKey === '' && preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $givenKey = trim($m[1]);
}
if ($givenKey === '') $givenKey = trim((string)($_GET['key'] ?? ''));
if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
    gt_out(401, ['ok' => false, 'error' => 'Invalid or missing API key.']);
}

// ── Resolve task_uid ─────────────────────────────────────────────────────────
$taskUid = trim((string)($_GET['task_uid'] ?? ''));
if ($taskUid === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = (string)file_get_contents('php://input');
    if ($raw !== '') {
        try {
            $body = json_decode(ho_clean_json($raw), true, 16, JSON_THROW_ON_ERROR);
            if (is_array($body) && isset($body['task_uid'])) {
                $taskUid = trim((string)$body['task_uid']);
            }
        } catch (Throwable) {}
    }
}

// ── Fetch task ────────────────────────────────────────────────────────────────
if ($taskUid !== '') {
    $task = ho_get_gpt_task($pdo, $taskUid);
    if ($task === null) {
        gt_out(404, ['ok' => false, 'error' => "Task '{$taskUid}' not found."]);
    }
    if ($task['status'] === 'expired') {
        gt_out(410, ['ok' => false, 'error' => "Task '{$taskUid}' has expired."]);
    }
    ho_mark_gpt_task_fetched($pdo, $taskUid);
} else {
    // No UID supplied — return the newest ready task
    $task = ho_get_next_ready_task($pdo);
    if ($task === null) {
        gt_out(200, [
            'ok'      => true,
            'message' => 'No tasks are waiting. All caught up!',
            'task'    => null,
        ]);
    }
    ho_mark_gpt_task_fetched($pdo, (string)$task['task_uid']);
}

// Build the import URL dynamically
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'hoosieronline.com';
$importUrl = "{$scheme}://{$host}/gpt-import.php";

gt_out(200, [
    'ok'           => true,
    'task_uid'     => $task['task_uid'],
    'task_type'    => $task['task_type'],
    'expected_key' => $task['expected_key'],
    'import_action'=> $task['import_action'],
    'import_url'   => $importUrl,
    'prompt'       => $task['prompt'],
    'instructions' => "Complete the task described in 'prompt'. When done, POST the JSON result to '{$importUrl}' with the key '{$task['expected_key']}' plus 'task_uid': '{$task['task_uid']}' at the top level.",
]);
