<?php
/**
 * assignments.php — the source of truth for daily AP Services output.
 *
 * Builds on wmt-engine.php (shared planner). Views:
 *   ?v=day        daily plan: door posts, breaks, floater, side + general tasks
 *   ?v=week       imported-date overview + Generate Week / Generate All
 *   ?v=signoff    completion / sign-off for a saved day
 *   ?v=tasks      editable task definitions
 *   ?v=team       associate preferred-door editor
 *   ?v=packet     printable weekly packet
 */
error_reporting(E_ALL);
ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
ini_set('log_errors', '1');
session_start();
require_once __DIR__ . '/wmt-engine.php';

/* ---------- small render helpers ---------- */

function asg_win($st, $en)
{
    if ($st === null || $st === '') {
        return 'As able';
    }
    if (is_string($st)) {
        $st = wmt_mins(substr($st, 0, 5));
        $en = wmt_mins(substr($en, 0, 5));
    }
    return wmt_nice($st) . '-' . wmt_nice($en);
}

function asg_kpis($pl)
{
    echo '<div class="grid">';
    echo '<div class="kpi">Coverage<b class="' . ($pl['pct'] >= 95 ? 'ok' : ($pl['pct'] < 80 ? 'bad' : '')) . '">' . wmt_e($pl['pct']) . '%</b></div>';
    echo '<div class="kpi">Gap minutes<b class="' . ($pl['gap'] ? 'bad' : 'ok') . '">' . wmt_e($pl['gap']) . '</b></div>';
    echo '<div class="kpi">Floater minutes<b>' . wmt_e($pl['float']) . '</b></div>';
    echo '<div class="kpi">Scheduled<b>' . count($pl['shifts']) . '</b></div>';
    echo '</div>';
}

function asg_sanity_banner($pl)
{
    foreach (wmt_sanity($pl) as $f) {
        if ($f['level'] === 'critical') {
            echo '<div class="crit">CRITICAL: ' . wmt_e($f['msg']) . '</div>';
        } elseif ($f['level'] === 'warn') {
            echo '<div class="warn">' . wmt_e($f['msg']) . '</div>';
        } else {
            echo '<div class="warn" style="border-left-color:var(--success);background:#EAF6E6">' . wmt_e($f['msg']) . '</div>';
        }
    }
}

/** Rank people: door owners first, then floaters, then alpha. */
function asg_group_people($rows)
{
    $by = array();
    $rank = array();
    foreach ($rows as $r) {
        if ($r['assignment_type'] === 'Triggered') {
            continue;
        }
        $n = $r['associate_name'];
        if ($n === null || $n === '' || $n === 'Unassigned') {
            $n = 'Unassigned / no owner';
        }
        $by[$n][] = $r;
        $cur = $rank[$n] ?? 9;
        if ($r['assignment_type'] === 'Door Post') {
            $cur = min($cur, 0);
        } elseif ($r['assignment_type'] === 'Floater / Break Cover') {
            $cur = min($cur, 1);
        } else {
            $cur = min($cur, 2);
        }
        $rank[$n] = $cur;
    }
    uksort($by, function ($a, $b) use ($rank) {
        if (($rank[$a] ?? 9) !== ($rank[$b] ?? 9)) {
            return ($rank[$a] ?? 9) - ($rank[$b] ?? 9);
        }
        return strcmp($a, $b);
    });
    return $by;
}

function asg_type_pill($t)
{
    return '<span class="pill">' . wmt_e($t) . '</span>';
}

/** Render the per-person assignment cards (mobile-first table.cards). */
function asg_person_cards($rows)
{
    foreach (asg_group_people($rows) as $name => $list) {
        echo '<div class="card"><h2>' . wmt_e($name) . '</h2><table class="cards"><tr><th>Time</th><th>Type</th><th>Assignment</th><th>Instruction</th></tr>';
        foreach ($list as $r) {
            $rel = !empty($r['release_required']) ? ' <span class="pill pill-rel">release first</span>' : '';
            $st = $r['start'] ?? $r['start_time'] ?? null;
            $en = $r['end'] ?? $r['end_time'] ?? null;
            echo '<tr>';
            echo '<td data-label="Time" class="time">' . wmt_e(asg_win($st, $en)) . '</td>';
            echo '<td data-label="Type">' . asg_type_pill($r['assignment_type']) . '</td>';
            echo '<td data-label="Assignment"><b>' . wmt_e($r['item_name']) . '</b>' . $rel . ($r['side'] && $r['side'] !== 'Both' ? ' <span class="muted small">(' . wmt_e($r['side']) . ')</span>' : '') . '</td>';
            echo '<td data-label="Instruction" class="small">' . wmt_e($r['notes']) . '</td>';
            echo '</tr>';
        }
        echo '</table></div>';
    }
}

function asg_triggered_card($rows)
{
    $trg = array();
    foreach ($rows as $r) {
        if ($r['assignment_type'] === 'Triggered') {
            $trg[] = $r;
        }
    }
    if (!$trg) {
        return;
    }
    echo '<div class="card"><h2>Triggered tasks (only when the condition occurs)</h2><table class="cards"><tr><th>Trigger</th><th>Category</th><th>Response</th></tr>';
    foreach ($trg as $r) {
        echo '<tr><td data-label="Trigger"><b>' . wmt_e($r['item_name']) . '</b></td><td data-label="Category">' . wmt_e($r['category']) . '</td><td data-label="Response" class="small">' . wmt_e($r['notes']) . '</td></tr>';
    }
    echo '</table></div>';
}

/* ---------- views ---------- */

function asg_day_view($p, $d, $msg)
{
    $gen = wmt_generate_day($p, $d);
    $rows = $gen['rows'];
    $pl = $gen['plan'];
    $saved = wmt_day_has_saved($p, $d);
    wmt_head('Daily Assignments', 'Assignments');
    if ($msg) {
        echo '<div class="card"><b>' . wmt_e($msg) . '</b></div>';
    }
    echo '<div class="card no-print"><form style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input type="date" name="date" value="' . wmt_e($d) . '"><button>Load date</button>'
        . ' <a class="btn" href="?v=week">Week / Generate</a>'
        . ' <a class="btn" href="?v=signoff&date=' . wmt_e($d) . '">Sign-off</a>'
        . ' <a class="btn" href="?v=packet&start=' . wmt_e($d) . '">Print packet</a>'
        . '</form>'
        . '<form method="post" style="margin-top:10px"><input type="hidden" name="form" value="generate_day"><input type="hidden" name="date" value="' . wmt_e($d) . '">'
        . '<button>' . ($saved ? 'Regenerate &amp; overwrite this day' : 'Generate &amp; save this day') . '</button>'
        . ($saved ? ' <span class="muted small">Saved assignments exist for this date.</span>' : '') . '</form></div>';

    echo '<div class="card"><h1 class="print-title">Daily Assignment Plan &mdash; ' . wmt_e($d) . '</h1>';
    echo '<p class="muted">Full daily ownership: door posts, staggered breaks/lunches, floater &amp; break cover, Grocery and GM side tasks, and general tasks. Door coverage is protected before any task; choices balance against saved history so work spreads over time.</p></div>';

    asg_sanity_banner($pl);
    echo '<div class="card">';
    asg_kpis($pl);
    echo '</div>';
    asg_person_cards($rows);
    asg_triggered_card($rows);
    wmt_foot();
}

function asg_week_view($p, $msg)
{
    $dates = wmt_imported_dates($p);
    $monday = new DateTime($_GET['date'] ?? date('Y-m-d'));
    $monday->modify('monday this week');
    wmt_head('Generate Week', 'Assignments');
    if ($msg) {
        echo '<div class="card"><b>' . wmt_e($msg) . '</b></div>';
    }
    echo '<div class="card"><h1>Generate assignments</h1><p class="muted">After importing a schedule, generate the daily plans for every imported date and save them to history. Saved days are skipped unless you confirm overwrite.</p>'
        . '<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">'
        . '<input type="hidden" name="form" value="generate_all">'
        . '<label class="pill" style="cursor:pointer"><input type="checkbox" name="overwrite" value="1" style="width:auto;min-height:0;margin-right:6px">Overwrite existing</label>'
        . '<button>Generate ALL imported dates</button></form>'
        . '<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">'
        . '<input type="hidden" name="form" value="generate_week">'
        . '<input type="date" name="week_start" value="' . wmt_e($monday->format('Y-m-d')) . '">'
        . '<label class="pill" style="cursor:pointer"><input type="checkbox" name="overwrite" value="1" style="width:auto;min-height:0;margin-right:6px">Overwrite existing</label>'
        . '<button>Generate this week (7 days)</button></form></div>';

    echo '<div class="card"><h2>Imported dates</h2><table class="cards"><tr><th>Date</th><th>Day</th><th>Shifts</th><th>Saved plan</th><th>Coverage</th><th>Open</th></tr>';
    foreach ($dates as $d) {
        $sh = wmt_shifts($p, $d);
        $pl = wmt_plan_core($sh, wmt_settings_all($p), array(), $d);
        $savedN = wmt_day_has_saved($p, $d);
        $dow = date('D', strtotime($d));
        echo '<tr>';
        echo '<td data-label="Date"><b>' . wmt_e($d) . '</b></td>';
        echo '<td data-label="Day">' . wmt_e($dow) . '</td>';
        echo '<td data-label="Shifts">' . count($sh) . '</td>';
        echo '<td data-label="Saved plan">' . ($savedN ? '<span class="ok">saved</span>' : '<span class="muted">not generated</span>') . '</td>';
        echo '<td data-label="Coverage" class="' . ($pl['gap'] ? 'bad' : 'ok') . '">' . wmt_e($pl['pct']) . '%</td>';
        echo '<td data-label="Open"><a href="?date=' . wmt_e($d) . '">plan</a> &middot; <a href="?v=signoff&date=' . wmt_e($d) . '">sign-off</a></td>';
        echo '</tr>';
    }
    echo '</table></div>';
    wmt_foot();
}

function asg_signoff_view($p, $d, $msg)
{
    $rows = wmt_load_saved($p, $d);
    wmt_head('Sign-off', 'Assignments');
    if ($msg) {
        echo '<div class="card"><b>' . wmt_e($msg) . '</b></div>';
    }
    echo '<div class="card no-print"><form style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><input type="hidden" name="v" value="signoff"><input type="date" name="date" value="' . wmt_e($d) . '"><button>Load date</button></form></div>';
    echo '<div class="card"><h1 class="print-title">Daily Sign-off &mdash; ' . wmt_e($d) . '</h1>';
    if (!$rows) {
        echo '<p class="muted">No saved assignments for this date yet. Generate them from the <a href="?v=week">Week</a> tab or the day plan.</p></div>';
        wmt_foot();
        return;
    }
    echo '<p class="muted">Mark each assignment Completed, Missed, Deferred, or N/A. Completion feeds future historical balancing (repeatedly missed tasks are routed away from that associate).</p></div>';
    echo '<form method="post"><input type="hidden" name="form" value="signoff"><input type="hidden" name="date" value="' . wmt_e($d) . '">';
    echo '<div class="card"><table class="cards"><tr><th>Time</th><th>Assignment</th><th>Owner</th><th>Status</th><th>Completed by</th><th>Notes</th></tr>';
    $statuses = array('Assigned', 'Completed', 'Missed', 'Deferred', 'N/A');
    foreach ($rows as $r) {
        if ($r['assignment_type'] === 'Triggered') {
            continue;
        }
        $id = (int)$r['id'];
        $rel = !empty($r['release_required']) ? ' <span class="pill pill-rel">release first</span>' : '';
        echo '<tr>';
        echo '<td data-label="Time" class="time">' . wmt_e(asg_win($r['start_time'], $r['end_time'])) . '</td>';
        echo '<td data-label="Assignment"><b>' . wmt_e($r['item_name']) . '</b>' . $rel . '<br><span class="muted small">' . wmt_e($r['assignment_type'] . ($r['side'] ? ' · ' . $r['side'] : '')) . '</span></td>';
        echo '<td data-label="Owner">' . wmt_e($r['associate_name'] ?: '-') . '</td>';
        echo '<td data-label="Status"><select name="row[' . $id . '][status]">';
        foreach ($statuses as $s) {
            echo '<option' . ($r['status'] === $s ? ' selected' : '') . '>' . wmt_e($s) . '</option>';
        }
        echo '</select></td>';
        echo '<td data-label="Completed by"><input name="row[' . $id . '][by]" value="' . wmt_e($r['completed_by'] ?? '') . '"></td>';
        echo '<td data-label="Notes"><input name="row[' . $id . '][notes]" value="' . wmt_e($r['completion_notes'] ?? '') . '"></td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '<p>Manager sign-off <input name="mgr" placeholder="Manager name"></p>';
    echo '<button class="no-print">Save sign-off</button></div></form>';
    wmt_foot();
}

function asg_tasks_view($p, $msg)
{
    $items = wmt_items($p, false);
    $edit = null;
    if (isset($_GET['edit'])) {
        foreach ($items as $i) {
            if ((int)$i['id'] === (int)$_GET['edit']) {
                $edit = $i;
            }
        }
    }
    wmt_head('Task Definitions', 'Assignments');
    if ($msg) {
        echo '<div class="card"><b>' . wmt_e($msg) . '</b></div>';
    }
    echo '<div class="card"><h1>Task definitions</h1><p class="muted">These drive the daily generator. Scope decides which side(s) a task lands on; preferred role decides whether a door owner, floater, or any TA gets it.</p>'
        . '<table class="cards"><tr><th>Name</th><th>Scope</th><th>Role</th><th>Priority</th><th>Min</th><th>Freq</th><th>Active</th><th>Edit</th></tr>';
    foreach ($items as $i) {
        echo '<tr>';
        echo '<td data-label="Name"><b>' . wmt_e($i['name']) . '</b><br><span class="muted small">' . wmt_e($i['category']) . '</span></td>';
        echo '<td data-label="Scope">' . wmt_e($i['scope']) . '</td>';
        echo '<td data-label="Role">' . wmt_e($i['preferred_role']) . '</td>';
        echo '<td data-label="Priority">' . wmt_e($i['priority']) . '</td>';
        echo '<td data-label="Min">' . wmt_e($i['estimated_minutes']) . '</td>';
        echo '<td data-label="Freq">' . wmt_e($i['frequency'] ?? 'Daily') . '</td>';
        echo '<td data-label="Active">' . ((int)$i['active'] ? '<span class="ok">yes</span>' : '<span class="muted">no</span>') . '<form method="post" style="display:inline"><input type="hidden" name="form" value="item_toggle"><input type="hidden" name="id" value="' . (int)$i['id'] . '"><input type="hidden" name="active" value="' . ((int)$i['active'] ? '0' : '1') . '"><button class="btn small" style="padding:3px 8px">' . ((int)$i['active'] ? 'disable' : 'enable') . '</button></form></td>';
        echo '<td data-label="Edit"><a href="?v=tasks&edit=' . (int)$i['id'] . '">edit</a></td>';
        echo '</tr>';
    }
    echo '</table></div>';

    // add / edit form
    $f = $edit ?: array('id' => '', 'name' => '', 'scope' => 'general', 'category' => '', 'priority' => 'Medium', 'estimated_minutes' => 10, 'preferred_role' => 'floater', 'frequency' => 'Daily', 'instructions' => '');
    $scopes = array('grocery' => 'Grocery only', 'gm' => 'GM only', 'both' => 'Both sides', 'general' => 'General floater', 'triggered' => 'Triggered only');
    $roles = array('door_owner' => 'Door owner', 'floater' => 'Floater', 'any' => 'Any AP Service TA');
    $prios = array('Critical', 'High', 'Medium', 'Low');
    echo '<div class="card no-print"><h2>' . ($edit ? 'Edit task' : 'Add a task') . '</h2><form method="post">';
    echo '<input type="hidden" name="form" value="item_save"><input type="hidden" name="id" value="' . wmt_e($f['id']) . '">';
    echo '<p>Name<br><input name="name" value="' . wmt_e($f['name']) . '" placeholder="e.g. Go backs from Service Desk" style="width:100%"></p>';
    echo '<p>Scope<br><select name="scope">';
    foreach ($scopes as $k => $lbl) {
        echo '<option value="' . $k . '"' . ($f['scope'] === $k ? ' selected' : '') . '>' . wmt_e($lbl) . '</option>';
    }
    echo '</select></p>';
    echo '<p>Preferred role<br><select name="preferred_role">';
    foreach ($roles as $k => $lbl) {
        echo '<option value="' . $k . '"' . ($f['preferred_role'] === $k ? ' selected' : '') . '>' . wmt_e($lbl) . '</option>';
    }
    echo '</select></p>';
    echo '<p>Priority<br><select name="priority">';
    foreach ($prios as $pr) {
        echo '<option' . ($f['priority'] === $pr ? ' selected' : '') . '>' . wmt_e($pr) . '</option>';
    }
    echo '</select></p>';
    echo '<p>Category <input name="category" value="' . wmt_e($f['category']) . '"> &nbsp; Estimated minutes <input type="number" name="estimated_minutes" value="' . wmt_e($f['estimated_minutes']) . '" style="width:90px"> &nbsp; Frequency <input name="frequency" value="' . wmt_e($f['frequency'] ?? 'Daily') . '"></p>';
    echo '<p>Instructions<br><textarea name="instructions">' . wmt_e($f['instructions']) . '</textarea></p>';
    echo '<button>' . ($edit ? 'Save changes' : 'Add task') . '</button>' . ($edit ? ' <a class="btn" href="?v=tasks" style="background:#777;border-color:#777">Cancel</a>' : '') . '</form></div>';
    wmt_foot();
}

function asg_team_view($p, $msg)
{
    $assoc = wmt_associates($p);
    wmt_head('Team', 'Assignments');
    if ($msg) {
        echo '<div class="card"><b>' . wmt_e($msg) . '</b></div>';
    }
    echo '<div class="card"><h1>Associates &amp; preferred door</h1><p class="muted">Preferred door is favored by the planner when it does not hurt coverage. It is not overwritten when you re-import a weekly schedule.</p>'
        . '<table class="cards"><tr><th>Name</th><th>Team</th><th>Role</th><th>Can cover</th><th>Preferred door</th></tr>';
    foreach ($assoc as $a) {
        echo '<tr>';
        echo '<td data-label="Name"><b>' . wmt_e($a['name']) . '</b></td>';
        echo '<td data-label="Team">' . wmt_e($a['team']) . '</td>';
        echo '<td data-label="Role">' . wmt_e($a['role_type']) . '</td>';
        echo '<td data-label="Can cover">' . ((int)$a['can_cover'] ? 'yes' : 'no') . '</td>';
        echo '<td data-label="Preferred door"><form method="post" style="display:flex;gap:6px"><input type="hidden" name="form" value="assoc_pref"><input type="hidden" name="id" value="' . (int)$a['id'] . '"><select name="door">';
        foreach (array('Either', 'Grocery', 'GM') as $door) {
            echo '<option' . (($a['preferred_door'] ?? 'Either') === $door ? ' selected' : '') . '>' . $door . '</option>';
        }
        echo '</select><button class="btn small" style="padding:4px 10px">set</button></form></td>';
        echo '</tr>';
    }
    echo '</table></div>';
    wmt_foot();
}

function asg_packet_view($p, $start)
{
    $monday = new DateTime($start);
    $monday->modify('monday this week');
    $days = array();
    for ($i = 0; $i < 7; $i++) {
        $day = (clone $monday)->modify('+' . $i . ' day')->format('Y-m-d');
        if (wmt_date_has_shifts($p, $day)) {
            $days[] = $day;
        }
    }
    wmt_head('Weekly Packet', 'Assignments');
    echo '<div class="card no-print"><b>Printable weekly packet.</b> Use your browser Print to PDF. Week of ' . wmt_e($monday->format('Y-m-d')) . '. <a class="btn" href="?v=packet&start=' . wmt_e((clone $monday)->modify('-7 day')->format('Y-m-d')) . '">&larr; prev week</a> <a class="btn" href="?v=packet&start=' . wmt_e((clone $monday)->modify('+7 day')->format('Y-m-d')) . '">next week &rarr;</a></div>';
    if (!$days) {
        echo '<div class="card"><h1>No imported shifts for this week.</h1></div>';
        wmt_foot();
        return;
    }
    $weekGaps = array();
    foreach ($days as $d) {
        $gen = wmt_generate_day($p, $d);
        $pl = $gen['plan'];
        $rows = $gen['rows'];

        // 1. Management daily review
        echo '<div class="card page"><h1 class="print-title">Management Daily Review &mdash; ' . wmt_e($d) . ' (' . date('l', strtotime($d)) . ')</h1>';
        echo '<p><b>Coverage:</b> ' . wmt_e($pl['pct']) . '% &nbsp; <b>Gap minutes:</b> ' . wmt_e($pl['gap']) . ' &nbsp; <b>Floater minutes:</b> ' . wmt_e($pl['float']) . ' &nbsp; <b>Scheduled:</b> ' . count($pl['shifts']) . '</p>';
        echo '<p class="muted small">Rules: Services own doors. Breaks/lunches staggered coverage-first. Ops flex only with two Ops available and not 10AM&ndash;12PM. TL max 15 min/day. Investigator never flexes. One door covered beats both uncovered.</p>';
        foreach (wmt_sanity($pl) as $f) {
            if ($f['level'] === 'critical') {
                echo '<div class="crit">' . wmt_e($f['msg']) . '</div>';
                $weekGaps[] = $d . ': ' . $f['msg'];
            }
        }
        echo '</div>';

        // 2. Position sheets per AP Services associate
        $names = array();
        foreach ($pl['shifts'] as $s) {
            if (wmt_team_of($s) === 'Services') {
                $names[$s['associate_name']] = $s;
            }
        }
        foreach ($names as $name => $s) {
            echo '<div class="card page"><h1 class="print-title">Position Sheet &mdash; ' . wmt_e($name) . '</h1>';
            echo '<p><b>Date:</b> ' . wmt_e($d) . ' &nbsp; <b>Shift:</b> ' . wmt_e(substr($s['start_time'], 0, 5)) . '-' . wmt_e(substr($s['end_time'], 0, 5)) . '</p>';
            echo '<p><b>Do not leave your post until relieved or directed.</b> If relief does not arrive, contact leadership/AP.</p>';
            echo '<table><tr><th>Time</th><th>Position</th><th>Handoff</th><th>Notes</th></tr>';
            foreach (wmt_person_blocks($pl, $name) as $b) {
                echo '<tr><td class="time">' . wmt_nice($b['start']) . '-' . wmt_nice($b['end']) . '</td><td><b>' . wmt_e($b['pos']) . '</b></td><td>' . wmt_e($b['handoff']) . '</td><td>' . wmt_e($b['notes']) . '</td></tr>';
            }
            echo '</table></div>';
        }

        // 3. Daily task / sign-off sheet
        echo '<div class="card page"><h1 class="print-title">Daily Task &amp; Sign-off &mdash; ' . wmt_e($d) . '</h1><table><tr><th>Time</th><th>Owner</th><th>Task</th><th>Status</th><th>By</th><th>Mgr</th></tr>';
        foreach ($rows as $r) {
            if (!in_array($r['assignment_type'], array('Door Task', 'General Task'), true)) {
                continue;
            }
            echo '<tr><td class="time">' . wmt_e(asg_win($r['start'], $r['end'])) . '</td><td>' . wmt_e($r['associate_name']) . '</td><td><b>' . wmt_e($r['item_name']) . '</b> <span class="muted small">' . wmt_e($r['side']) . '</span></td><td style="min-width:90px">&nbsp;</td><td style="min-width:80px">&nbsp;</td><td style="min-width:60px">&nbsp;</td></tr>';
        }
        echo '</table></div>';
    }

    // 4. Weekly summary of unavoidable gaps + staffing needs
    echo '<div class="card page"><h1 class="print-title">Weekly Summary &mdash; Unavoidable Gaps &amp; Staffing Needs</h1>';
    if ($weekGaps) {
        echo '<p>The following windows could not be covered with the scheduled AP Services staff. These are staffing requests, not assignment errors:</p><ul>';
        foreach ($weekGaps as $g) {
            echo '<li>' . wmt_e($g) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p class="ok">No unavoidable both-door gaps this week. Coverage held across all imported days.</p>';
    }
    echo '<p class="muted small">Target: both doors 8AM&ndash;5PM. Gold standard: Grocery 6AM&ndash;11PM, GM 6AM&ndash;9PM. Each both-door gap above is a window where additional AP Services coverage is needed.</p></div>';
    wmt_foot();
}

/* ---------- POST handling ---------- */

function asg_handle_post($p)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return '';
    }
    $f = $_POST['form'] ?? '';
    if ($f === 'generate_day') {
        $d = $_POST['date'] ?? date('Y-m-d');
        $gen = wmt_generate_day($p, $d);
        $n = wmt_save_day($p, $d, $gen['rows']);
        return 'Saved ' . $n . ' assignments for ' . $d . '.';
    }
    if ($f === 'generate_all') {
        $dates = wmt_imported_dates($p);
        $made = wmt_generate_dates($p, $dates, !empty($_POST['overwrite']));
        return asg_made_summary($made);
    }
    if ($f === 'generate_week') {
        $monday = new DateTime($_POST['week_start'] ?? date('Y-m-d'));
        $monday->modify('monday this week');
        $dates = array();
        for ($i = 0; $i < 7; $i++) {
            $dates[] = (clone $monday)->modify('+' . $i . ' day')->format('Y-m-d');
        }
        $made = wmt_generate_dates($p, $dates, !empty($_POST['overwrite']));
        return asg_made_summary($made);
    }
    if ($f === 'signoff') {
        $d = $_POST['date'] ?? '';
        $mgr = trim($_POST['mgr'] ?? '');
        $n = 0;
        foreach ($_POST['row'] ?? array() as $id => $row) {
            wmt_update_completion($p, $id, $row['status'] ?? 'Assigned', trim($row['by'] ?? ''), trim($row['notes'] ?? ''), $mgr);
            $n++;
        }
        return 'Saved sign-off for ' . $n . ' assignments' . ($d ? ' on ' . $d : '') . '.';
    }
    if ($f === 'item_save') {
        return wmt_item_save($p, $_POST);
    }
    if ($f === 'item_toggle') {
        wmt_item_set_active($p, $_POST['id'] ?? 0, !empty($_POST['active']));
        return 'Task updated.';
    }
    if ($f === 'assoc_pref') {
        wmt_associate_set_pref($p, $_POST['id'] ?? 0, $_POST['door'] ?? 'Either');
        return 'Preferred door saved.';
    }
    return '';
}

function asg_made_summary($made)
{
    if (!$made) {
        return 'No imported dates with shifts to generate.';
    }
    $parts = array();
    foreach ($made as $d => $n) {
        $parts[] = $d . ' (' . ($n === 'skipped' ? 'skipped' : $n . ' rows') . ')';
    }
    return 'Generated: ' . implode(', ', $parts) . '.';
}

/* ---------- main ---------- */

try {
    $p = wmt_db();
    wmt_schema($p);
    wmt_require_login($p, 'assignments.php');
    wmt_auto_seed($p);
    $msg = asg_handle_post($p);
    $v = $_GET['v'] ?? 'day';
    $d = $_GET['date'] ?? date('Y-m-d');
    if ($v === 'week') {
        asg_week_view($p, $msg);
    } elseif ($v === 'signoff') {
        asg_signoff_view($p, $d, $msg);
    } elseif ($v === 'tasks') {
        asg_tasks_view($p, $msg);
    } elseif ($v === 'team') {
        asg_team_view($p, $msg);
    } elseif ($v === 'packet') {
        asg_packet_view($p, $_GET['start'] ?? $d);
    } else {
        asg_day_view($p, $d, $msg);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>' . wmt_theme_css() . '</style><body><div class="card" style="max-width:900px;margin:40px auto"><h1>Assignment engine error</h1><pre>' . wmt_e($e->getMessage()) . '</pre><p>Add <code>?debug=1</code> to the URL for details.</p></div>';
}
