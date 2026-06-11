<?php
declare(strict_types=1);
/**
 * Autopilot heartbeat. Run every 15 minutes via cPanel cron:
 *
 *   /usr/bin/curl -s "https://hoosieronline.com/cron.php?key=YOUR_IMPORT_KEY" >/dev/null 2>&1
 *
 * Auth: ?key= / X-Api-Key matching app_settings.gpt_import_key, or CLI.
 * Each run executes only the features enabled in the Autopilot panel (Send tab).
 * Every outreach email passes ho_autopilot_gate(): daily cap, 8am-6pm window,
 * CAN-SPAM postal set, email_log present. Safe to run as often as you like.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

header('Content-Type: application/json');
date_default_timezone_set('America/Indiana/Indianapolis');
@set_time_limit(280);

function cron_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = ho_db();
} catch (Throwable) {
    cron_out(503, ['ok' => false, 'error' => 'Database unavailable.']);
}

// ── Auth ─────────────────────────────────────────────────────────────────────
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    $configuredKey = ho_get_setting($pdo, 'gpt_import_key');
    if ($configuredKey === '') cron_out(503, ['ok' => false, 'error' => 'Import key not configured.']);
    $givenKey = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($givenKey === '' || !hash_equals($configuredKey, $givenKey)) {
        cron_out(401, ['ok' => false, 'error' => 'Unauthorized.']);
    }
}

// LLM config (outside public_html) powers auto-research + auto-source when present
$llmConfigPath = '/home1/spofnkte/llm-config.php';
if (is_file($llmConfigPath)) require_once $llmConfigPath;

$out = ['ok' => true, 'ran_at' => date('Y-m-d H:i:s')];

if (ho_get_setting($pdo, 'ap_master') !== '1') {
    $out['note'] = 'Autopilot master switch is off — nothing to do.';
    ho_set_setting($pdo, 'ap_last_run', $out['ran_at']);
    cron_out(200, $out);
}

// ── Tasks, cheapest first. Each wrapped so one failure never blocks the rest. ─
$tasks = [
    'digest'    => fn() => ho_send_daily_digest($pdo),
    'drip'      => fn() => ho_run_followup_drip($pdo, 10),
    'hotstrike' => fn() => ho_run_hot_strikes($pdo, 5),
    'autopitch' => fn() => ho_run_auto_pitch($pdo),
    'research'  => fn() => ho_run_auto_research($pdo, 1),
    'source'    => fn() => ho_run_auto_source($pdo),
];

foreach ($tasks as $name => $task) {
    if (!ho_autopilot_on($pdo, $name)) { $out[$name] = 'off'; continue; }
    try {
        $out[$name] = $task();
    } catch (Throwable $e) {
        $out[$name] = ['error' => $e->getMessage()];
    }
}

ho_set_setting($pdo, 'ap_last_run', $out['ran_at']);
ho_set_setting($pdo, 'ap_last_log', json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

cron_out(200, $out);
