<?php
/**
 * tasks.php — focused Grocery/GM side-task assignment view.
 *
 * Now a thin layer over wmt-engine.php (no duplicate planner). It assigns the
 * legacy wmt_tasks list to the AP Service TA who owns the most coverage on each
 * side and saves to wmt_task_assignments. The fuller daily generator lives in
 * assignments.php; this page is kept for the quick side-by-side task view.
 */
error_reporting(E_ALL);
ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
ini_set('log_errors', '1');
session_start();
require_once __DIR__ . '/wmt-engine.php';

/** Legacy daily tasks, minus the report/triggered ones that are not side work. */
function tk_side_tasks($p)
{
    $out = array();
    foreach (wmt_task_rows($p) as $r) {
        $n = strtolower($r['name']);
        if (strpos($n, 'suspicious') !== false || strpos($n, 'inclement') !== false || strpos($n, 'report') !== false) {
            continue;
        }
        $out[] = $r;
    }
    return $out;
}

/** Assign each side task to the strongest same-side owner, balancing weight. */
function tk_assign($p, $d)
{
    $pl = wmt_plan($p, $d);
    $tasks = tk_side_tasks($p);
    $assign = array();
    foreach (array('Grocery', 'GM') as $side) {
        $owners = $pl['side_minutes'][$side];
        arsort($owners);
        $names = array_keys($owners);
        $load = array();
        foreach ($tasks as $t) {
            $chosen = 'Unassigned';
            if ($names) {
                $best = null;
                $score = 999999;
                foreach ($names as $n) {
                    $s = ($load[$n] ?? 0) - (($owners[$n] ?? 0) / 60);
                    if ($s < $score) {
                        $score = $s;
                        $best = $n;
                    }
                }
                $chosen = $best;
                $load[$chosen] = ($load[$chosen] ?? 0) + (int)$t['weight'];
            }
            $blocks = $chosen !== 'Unassigned' ? wmt_name_blocks($pl['rows'], wmt_side_field($side), $chosen) : array();
            $window = $blocks ? (wmt_nice($blocks[0]['start']) . '-' . wmt_nice($blocks[0]['end'])) : 'No clean 8-5 owner';
            $assign[] = array(
                'side' => $side, 'task' => $t['name'], 'priority' => $t['priority'], 'weight' => $t['weight'],
                'assigned_to' => $chosen, 'window' => $window,
                'notes' => $chosen === 'Unassigned'
                    ? 'No AP Service TA owns this side during the 8-5 planning window.'
                    : 'Owns ' . $side . ' coverage block; complete when traffic/coverage allows without leaving the post unsecured.',
            );
        }
    }
    return array($assign, $pl);
}

function tk_save($p, $d, $assign)
{
    $p->prepare('DELETE FROM wmt_task_assignments WHERE work_date=?')->execute(array($d));
    $q = $p->prepare('INSERT INTO wmt_task_assignments(work_date,side,task_name,assigned_to,priority,notes) VALUES(?,?,?,?,?,?)');
    foreach ($assign as $a) {
        $q->execute(array($d, $a['side'], $a['task'], $a['assigned_to'], $a['priority'], $a['notes'] . ' Suggested window: ' . $a['window']));
    }
    return count($assign);
}

try {
    $p = wmt_db();
    wmt_schema($p);
    wmt_require_login($p, 'tasks.php');
    wmt_auto_seed($p);
    $d = $_GET['date'] ?? date('Y-m-d');
    $msg = '';
    list($assign, $pl) = tk_assign($p, $d);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'save') {
        $msg = 'Saved ' . tk_save($p, $d, $assign) . ' task assignments for ' . $d . '.';
    }
    wmt_head('Door Tasks', 'Door Tasks');
    if ($msg) {
        echo '<div class="card"><b>' . wmt_e($msg) . '</b></div>';
    }
    echo '<div class="card no-print"><form style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input type="date" name="date" value="' . wmt_e($d) . '"><button>Load</button> <a class="btn" href="assignments.php?date=' . wmt_e($d) . '">Full daily plan</a></form>'
        . '<form method="post" style="margin-top:10px"><input type="hidden" name="form" value="save"><button>Save these assignments</button></form></div>';
    echo '<div class="card"><h1 class="print-title">Daily Door Task Assignments &mdash; ' . wmt_e($d) . '</h1>'
        . '<p class="muted">Each daily task is duplicated for Grocery and GM and assigned to the AP Service TA who owns the most coverage on that side, then balanced by task weight. These are ownership assignments, not permission to abandon a door.</p></div>';
    foreach (array('Grocery', 'GM') as $side) {
        echo '<div class="card"><h2>' . wmt_e($side) . ' side tasks</h2><table class="cards"><tr><th>Task</th><th>Assigned To</th><th>Priority</th><th>Suggested Window</th><th>Instruction</th></tr>';
        foreach ($assign as $a) {
            if ($a['side'] !== $side) {
                continue;
            }
            echo '<tr>';
            echo '<td data-label="Task"><b>' . wmt_e($a['task']) . '</b></td>';
            echo '<td data-label="Assigned To">' . wmt_e($a['assigned_to']) . '</td>';
            echo '<td data-label="Priority">' . wmt_e($a['priority']) . '</td>';
            echo '<td data-label="Window">' . wmt_e($a['window']) . '</td>';
            echo '<td data-label="Instruction" class="small">' . wmt_e($a['notes']) . '</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
    echo '<div class="card"><h2>Side ownership minutes</h2><table class="cards"><tr><th>Side</th><th>Associate</th><th>8-5 Owner Minutes</th></tr>';
    foreach ($pl['side_minutes'] as $side => $minlist) {
        arsort($minlist);
        foreach ($minlist as $n => $m) {
            echo '<tr><td data-label="Side">' . wmt_e($side) . '</td><td data-label="Associate">' . wmt_e($n) . '</td><td data-label="Minutes">' . wmt_e($m) . '</td></tr>';
        }
    }
    echo '</table></div>';
    wmt_foot();
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . wmt_theme_css() . '</style><div class="card" style="max-width:900px;margin:40px auto"><h1>Task setup error</h1><pre>' . wmt_e($e->getMessage()) . '</pre><p>Add <code>?debug=1</code> for details.</p></div>';
}
