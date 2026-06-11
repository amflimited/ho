<?php
declare(strict_types=1);
/**
 * GPT auto-import endpoint.
 *
 * A Custom GPT Action (or anything with the key) POSTs result JSON here and
 * it lands in the pipeline directly — no copy/paste round trip. The payload
 * type is detected from its top-level key:
 *
 *   research_results   → research import (+ preview generation)
 *   contacts           → contact-research import
 *   enrichment_results → enrichment import
 *   candidates         → sourcing import (requires top-level run_id)
 *
 * Auth: X-Api-Key header (or Authorization: Bearer, or ?key=) matched against
 * the app_settings row 'gpt_import_key'. No key configured = endpoint off.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

header('Content-Type: application/json');

function gi_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function gi_log(PDO $pdo, string $type, int $count): void {
    try {
        ho_set_setting($pdo, 'last_import_at',
            date('Y-m-d H:i:s') . ' — ' . $type . ' — ' . $count . ' record' . ($count !== 1 ? 's' : ''));
    } catch (\Throwable $e) {}
}

function gi_log_attempt(PDO $pdo, int $httpCode, string $detail): void {
    try {
        $entry = date('Y-m-d H:i:s') . ' HTTP ' . $httpCode . ' — ' . $detail;
        $prev  = ho_get_setting($pdo, 'last_request_log');
        $lines = $prev !== '' ? explode("\n", $prev) : [];
        array_unshift($lines, $entry);
        ho_set_setting($pdo, 'last_request_log', implode("\n", array_slice($lines, 0, 10)));
    } catch (\Throwable $e) {}
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    gi_out(405, ['ok' => false, 'error' => 'POST only.']);
}

try {
    $pdo = ho_db();
} catch (Throwable $e) {
    gi_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
}

// ── Auth ─────────────────────────────────────────────────────────────────────
$configuredKey = ho_get_setting($pdo, 'gpt_import_key');
if ($configuredKey === '') {
    gi_out(503, ['ok' => false, 'error' => 'Auto-import is not configured.']);
}
$givenKey = trim((string)($_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($givenKey === '' && preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $givenKey = trim($m[1]);
}
if ($givenKey === '') $givenKey = trim((string)($_GET['key'] ?? ''));
if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
    gi_log_attempt($pdo, 401, 'Bad key — given: ' . (strlen($givenKey) > 0 ? substr($givenKey, 0, 6) . '…' : 'none'));
    gi_out(401, ['ok' => false, 'error' => 'Invalid or missing API key.']);
}

// ── Payload ──────────────────────────────────────────────────────────────────
$raw = (string)file_get_contents('php://input');
if ($raw === '') gi_out(400, ['ok' => false, 'error' => 'Empty request body.']);

try {
    $data = json_decode(ho_clean_json($raw), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    gi_out(400, ['ok' => false, 'error' => 'Body is not valid JSON: ' . $e->getMessage()]);
}
if (!is_array($data)) gi_out(400, ['ok' => false, 'error' => 'JSON must be an object.']);

// Actions sometimes wrap the payload one level deep (e.g. {"data": {...}})
if (!isset($data['research_results']) && !isset($data['contacts'])
    && !isset($data['enrichment_results']) && !isset($data['candidates'])
    && isset($data['data']) && is_array($data['data'])) {
    $data = $data['data'];
}

// ── Task-UID resolution ───────────────────────────────────────────────────────
$taskUid  = trim((string)($data['task_uid'] ?? ''));
$taskRow  = null;
if ($taskUid !== '') {
    $taskRow = ho_get_gpt_task($pdo, $taskUid);
    if ($taskRow !== null) {
        // For sourcing tasks: pull run_id from the task if not in payload
        if (isset($data['candidates']) && empty($data['run_id']) && !empty($taskRow['source_run_id'])) {
            $data['run_id'] = $taskRow['source_run_id'];
        }
    }
}

@set_time_limit(180);

// ── Dispatch by payload key ──────────────────────────────────────────────────
try {
    if (isset($data['research_results'])) {
        gi_log_attempt($pdo, 200, 'research — ' . count($data['research_results']) . ' entries received');
        $result = ho_import_research_json($pdo, json_encode($data));
        gi_log($pdo, 'research', $result['updated']);
        if ($taskRow !== null) ho_mark_gpt_task_imported($pdo, $taskUid);
        gi_out(200, [
            'ok' => true, 'type' => 'research',
            'updated' => $result['updated'], 'errors' => $result['errors'],
            'message' => "Imported research for {$result['updated']} businesses.",
        ]);
    }

    if (isset($data['contacts'])) {
        gi_log_attempt($pdo, 200, 'contacts — ' . count($data['contacts']) . ' entries received');
        $result = ho_import_contact_json($pdo, json_encode($data));
        gi_log($pdo, 'contacts', $result['updated']);
        if ($taskRow !== null) ho_mark_gpt_task_imported($pdo, $taskUid);
        gi_out(200, [
            'ok' => true, 'type' => 'contacts',
            'updated' => $result['updated'], 'errors' => $result['errors'],
            'message' => "Updated contact info for {$result['updated']} businesses.",
        ]);
    }

    if (isset($data['enrichment_results'])) {
        gi_log_attempt($pdo, 200, 'enrichment — ' . count($data['enrichment_results']) . ' entries received');
        $result = ho_import_enrichment_json($pdo, json_encode($data));
        gi_log($pdo, 'enrichment', $result['updated']);
        if ($taskRow !== null) ho_mark_gpt_task_imported($pdo, $taskUid);
        gi_out(200, [
            'ok' => true, 'type' => 'enrichment',
            'updated' => $result['updated'], 'errors' => $result['errors'],
            'message' => "Enriched {$result['updated']} businesses.",
        ]);
    }

    if (isset($data['candidates'])) {
        $runId = (int)($data['run_id'] ?? 0);
        if ($runId === 0) {
            gi_out(400, ['ok' => false, 'error' => 'candidates payload needs a top-level run_id (it is included in the sourcing prompt).']);
        }
        $chk = $pdo->prepare("SELECT id FROM source_runs WHERE id = ?");
        $chk->execute([$runId]);
        if (!$chk->fetch()) {
            gi_out(400, ['ok' => false, 'error' => "Source run {$runId} not found."]);
        }
        $result   = ho_import_sourcing_json($pdo, $runId, json_encode($data));
        $promoted = ho_promote_candidates($pdo, $runId);
        gi_log($pdo, 'sourcing', $result['imported']);
        if ($taskRow !== null) ho_mark_gpt_task_imported($pdo, $taskUid);
        gi_out(200, [
            'ok' => true, 'type' => 'sourcing',
            'imported' => $result['imported'], 'skipped' => $result['skipped'],
            'promoted' => $promoted, 'dead_urls' => $result['dead_urls'] ?? 0,
            'message' => "Imported {$result['imported']} candidates, {$promoted} promoted to pipeline.",
        ]);
    }
} catch (Throwable $e) {
    if ($taskRow !== null) {
        try {
            $pdo->prepare("UPDATE gpt_tasks SET status='failed', last_error=? WHERE task_uid=?")
                ->execute([$e->getMessage(), $taskUid]);
        } catch (Throwable) {}
    }
    gi_out(500, ['ok' => false, 'error' => 'Import failed: ' . $e->getMessage()]);
}

gi_out(400, ['ok' => false, 'error' => 'No recognized payload key. Expected one of: research_results, contacts, enrichment_results, candidates.']);
