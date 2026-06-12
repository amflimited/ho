<?php
declare(strict_types=1);
/**
 * Autopilot Console status endpoint.
 * GET ?key=IMPORT_KEY  — returns source + research state, queue count, next recommendation.
 * Polled every 5–12s by the console panel in app.php.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

header('Content-Type: application/json');

function aps_out(int $code, array $data): never {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

try { $pdo = ho_db(); } catch (Throwable) { aps_out(503, ['ok' => false]); }

$key   = ho_get_setting($pdo, 'gpt_import_key');
$given = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? ''));
if ($key === '' || $given === '' || !hash_equals($key, $given)) {
    aps_out(401, ['ok' => false]);
}

// ── Read a stashed active-job key; treat as idle after 10 min ────────────────
function aps_stash(PDO $pdo, string $k): array {
    $raw = ho_get_setting($pdo, $k);
    if ($raw === '') return [];
    $d = json_decode($raw, true);
    if (!is_array($d)) return [];
    if (isset($d['at']) && (time() - (int)$d['at']) > 600) return [];
    return $d;
}

$src = aps_stash($pdo, 'llmsrc_active');
$res = aps_stash($pdo, 'llmres_active');

// ── Research queue (leads waiting to be researched) ───────────────────────────
$resQueue = 0;
try {
    $sq = $pdo->query("
        SELECT COUNT(*) FROM businesses b
        WHERE b.pipeline_status IN ('preview_ready','enhancement_ready','needs_contact')
          AND b.triaged = 1
          AND NOT EXISTS (
              SELECT 1 FROM research_records r
              WHERE r.business_id = b.id AND r.research_status = 'complete'
          )
    ");
    $resQueue = (int)$sq->fetchColumn();
} catch (Throwable) {}

// ── Smart next source recommendation ─────────────────────────────────────────
$next = null;
try {
    $cats     = ho_get_categories($pdo);
    $coverage = ho_source_coverage($pdo);
    $regions  = array_keys(ho_indiana_regions());

    // Filter to categories that have a preview template
    $tplCats = array_values(array_filter($cats, function($c) {
        return ho_template_dir_for_slug((string)($c['slug'] ?? '')) !== '';
    }));

    // Coverage lookup + region sort by fewest total runs
    $covMap   = [];
    $runCount = [];
    foreach ($coverage as $row) {
        $covMap[(string)$row['category_name']][(string)$row['area_query']] = true;
        $r = (string)$row['area_query'];
        $runCount[$r] = ($runCount[$r] ?? 0) + (int)($row['run_count'] ?? 1);
    }
    usort($regions, fn($a, $b) => ($runCount[$a] ?? 0) <=> ($runCount[$b] ?? 0));

    // First untouched cat+region
    foreach ($tplCats as $cat) {
        foreach ($regions as $reg) {
            if (!isset($covMap[(string)$cat['name']][$reg])) {
                $next = ['cat_id' => (int)$cat['id'], 'cat' => (string)$cat['name'], 'area' => $reg, 'fresh' => true];
                break 2;
            }
        }
    }
    // Fallback: least-run cat+region
    if ($next === null && !empty($tplCats)) {
        $minRuns = PHP_INT_MAX;
        foreach ($tplCats as $cat) {
            foreach ($regions as $reg) {
                $rc = $runCount[$reg] ?? 0;
                if ($rc < $minRuns) {
                    $minRuns = $rc;
                    $next = ['cat_id' => (int)$cat['id'], 'cat' => (string)$cat['name'], 'area' => $reg, 'fresh' => false];
                }
            }
        }
    }
} catch (Throwable) {}

// ── Cron last ran ─────────────────────────────────────────────────────────────
$cronRanAt = ho_get_setting($pdo, 'ap_last_run');
$cronSecs  = ($cronRanAt !== '') ? max(0, time() - (int)strtotime($cronRanAt)) : null;

aps_out(200, [
    'ok'        => true,
    'source'    => $src ?: null,
    'research'  => $res ?: null,
    'res_queue' => $resQueue,
    'next'      => $next,
    'cron_secs' => $cronSecs,
]);
