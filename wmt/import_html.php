<?php
/**
 * import_html.php — upload a saved WPP Scheduler HTML page and import AP shifts.
 *
 * The HTML parser is import-specific and stays here; everything else (DB,
 * schema, auth, associate upsert, chrome) now comes from wmt-engine.php.
 */
error_reporting(E_ALL);
ini_set('display_errors', isset($_GET['debug']) ? '1' : '0');
ini_set('log_errors', '1');
session_start();
require_once __DIR__ . '/wmt-engine.php';

function ih_clean($html)
{
    $s = html_entity_decode(preg_replace('/<[^>]+>/', ' ', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $s));
}
function ih_mon($m)
{
    $map = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12);
    return $map[strtolower(substr($m, 0, 3))] ?? 0;
}
function ih_role_team($role)
{
    return wmt_team_of(array('role_type' => $role));
}
function ih_start_date($html, $heads, $override)
{
    if ($override && preg_match('/^\d{4}-\d{2}-\d{2}$/', $override)) {
        return $override;
    }
    if (!$heads) {
        return date('Y-m-d');
    }
    $day = $heads[0][0];
    $dow = $heads[0][1];
    $txt = ih_clean($html);
    $year = (int)date('Y');
    if (preg_match('/\b[A-Za-z]{3,9}\s+(20\d{2})\b/', $txt, $y)) {
        $year = (int)$y[1];
    }
    if (preg_match_all('/\b(Sat|Sun|Mon|Tue|Wed|Thu|Fri),\s*([A-Za-z]{3})\s*(\d{1,2})\s*-\s*(Sat|Sun|Mon|Tue|Wed|Thu|Fri),\s*([A-Za-z]{3})\s*(\d{1,2})/i', $txt, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) {
            if ((int)$m[3] === $day && ucfirst(strtolower($m[1])) === $dow) {
                $mo = ih_mon($m[2]);
                if ($mo) {
                    return sprintf('%04d-%02d-%02d', $year, $mo, $day);
                }
            }
        }
    }
    $best = '';
    $bd = 999999;
    $now = strtotime(date('Y-m-d'));
    for ($y = $year - 1; $y <= $year + 1; $y++) {
        for ($mo = 1; $mo <= 12; $mo++) {
            if (!checkdate($mo, $day, $y)) {
                continue;
            }
            $ts = strtotime(sprintf('%04d-%02d-%02d', $y, $mo, $day));
            if (date('D', $ts) === $dow && abs($ts - $now) < $bd) {
                $bd = abs($ts - $now);
                $best = date('Y-m-d', $ts);
            }
        }
    }
    return $best ?: date('Y-m-d');
}
function ih_plus($date, $i)
{
    $d = new DateTime($date);
    if ($i) {
        $d->modify("+$i days");
    }
    return $d->format('Y-m-d');
}
function ih_t24($hm, $ap = '')
{
    preg_match('/^(\d{1,2}):(\d{2})$/', trim($hm), $m);
    $h = (int)$m[1];
    $mi = (int)$m[2];
    $ap = strtolower($ap);
    if ($ap === 'pm' && $h !== 12) {
        $h += 12;
    }
    if ($ap === 'am' && $h === 12) {
        $h = 0;
    }
    return sprintf('%02d:%02d', $h, $mi);
}
function ih_parse($html, $fn, $override = '')
{
    $rows = array();
    $sk = 0;
    if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $trs)) {
        return array(array(), date('Y-m-d'), 'No table rows found.');
    }
    preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', $trs[1][0], $hc);
    $heads = array();
    for ($i = 2; $i < count($hc[1]); $i++) {
        if (preg_match('/\b(\d{1,2})\s+(Sat|Sun|Mon|Tue|Wed|Thu|Fri)\b/i', ih_clean($hc[1][$i]), $m)) {
            $heads[] = array((int)$m[1], ucfirst(strtolower($m[2])));
        }
    }
    $start = ih_start_date($html, $heads, $override);
    for ($r = 1; $r < count($trs[1]); $r++) {
        preg_match_all('/<td\b[^>]*>(.*?)<\/td>/is', $trs[1][$r], $td);
        if (count($td[1]) < 3) {
            continue;
        }
        $first = ih_clean($td[1][0]);
        if (!preg_match('/^(.+?)\s+AP\s+/i', $first, $nm)) {
            continue;
        }
        $name = trim($nm[1]);
        $max = min(count($heads), count($td[1]) - 2);
        for ($i = 0; $i < $max; $i++) {
            $txt = ih_clean($td[1][$i + 2]);
            if (!preg_match('/\b(AP\s+(?:Service TA|Operations TA|Team Lead|Investigator))(?:\s+\d+-\d+-\d+)?\b/i', $txt, $rm)) {
                $sk++;
                continue;
            }
            $role = trim($rm[1]);
            if (!preg_match('/(\d{1,2}:\d{2})\s*(am|pm)?\s*(?:–|-|to)\s*(\d{1,2}:\d{2})\s*(am|pm)?/i', $txt, $tm)) {
                $sk++;
                continue;
            }
            $rows[] = array(
                'date' => ih_plus($start, $i), 'name' => $name, 'team' => ih_role_team($role),
                'role' => $role, 'start' => ih_t24($tm[1], $tm[2] ?? ''), 'end' => ih_t24($tm[3], $tm[4] ?? ''),
                'notes' => 'Imported from ' . $fn,
            );
        }
    }
    return array($rows, $start, 'Parsed ' . count($rows) . ' scheduled shifts from ' . $fn . '. Week start ' . $start . '. Skipped non-shift cells: ' . $sk . '.');
}

function ih_render($msg = '', $prev = array())
{
    wmt_head('HTML Import', 'Import');
    if ($msg) {
        echo '<div class="card"><b>Result:</b> ' . wmt_e($msg) . '</div>';
    }
    echo '<div class="card"><h1>Upload WPP Scheduler HTML</h1><p>Upload a saved Scheduler page. It imports AP scheduled shift cards and ignores Available/Unavailable/PTO cells. After importing, open <a href="assignments.php?v=week">Assignments &rarr; Week</a> to generate the daily plans.</p>'
        . '<form method="post" enctype="multipart/form-data"><input type="hidden" name="form" value="upload">'
        . '<p><input type="file" name="htmlfile" accept=".html,.htm,text/html" required></p>'
        . '<p>Week start override <input type="date" name="week_start"> <span class="muted small">Leave blank unless dates parse wrong.</span></p>'
        . '<p><label><input type="checkbox" name="replace_dates" value="1" checked> Replace existing shifts for imported dates</label></p>'
        . '<button>Import HTML schedule</button></form></div>';
    if ($prev) {
        echo '<div class="card"><h2>Preview</h2><table class="cards"><tr><th>Date</th><th>Name</th><th>Team</th><th>Role</th><th>Shift</th></tr>';
        foreach (array_slice($prev, 0, 80) as $r) {
            echo '<tr><td data-label="Date">' . wmt_e($r['date']) . '</td><td data-label="Name">' . wmt_e($r['name']) . '</td><td data-label="Team">' . wmt_e($r['team']) . '</td><td data-label="Role">' . wmt_e($r['role']) . '</td><td data-label="Shift">' . wmt_e($r['start'] . '-' . $r['end']) . '</td></tr>';
        }
        echo '</table></div>';
    }
    wmt_foot();
}

try {
    $p = wmt_db();
    wmt_schema($p);
    wmt_require_login($p, 'import_html.php');
    $msg = '';
    $prev = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'upload') {
        if (empty($_FILES['htmlfile']) || $_FILES['htmlfile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No HTML file uploaded.');
        }
        $fn = basename($_FILES['htmlfile']['name']);
        $html = file_get_contents($_FILES['htmlfile']['tmp_name']);
        list($rows, $start, $msg) = ih_parse($html, $fn, trim($_POST['week_start'] ?? ''));
        if ($rows) {
            $dates = array();
            foreach ($rows as $r) {
                $dates[$r['date']] = 1;
            }
            if (!empty($_POST['replace_dates'])) {
                foreach (array_keys($dates) as $d) {
                    $p->prepare('DELETE FROM wmt_shifts WHERE work_date=?')->execute(array($d));
                }
            }
            foreach ($rows as $r) {
                $id = wmt_up_assoc($p, array('name' => $r['name'], 'role_type' => $r['role'], 'team' => $r['team'], 'notes' => $r['notes']));
                $p->prepare('INSERT INTO wmt_shifts(work_date,associate_id,associate_name,team,role_type,start_time,end_time,notes,source_file) VALUES(?,?,?,?,?,?,?,?,?)')
                  ->execute(array($r['date'], $id, $r['name'], $r['team'], $r['role'], $r['start'], $r['end'], $r['notes'], $fn));
            }
            $ks = array_keys($dates);
            sort($ks);
            $p->prepare('INSERT INTO wmt_html_imports(filename,imported_rows,week_start,date_min,date_max,message) VALUES(?,?,?,?,?,?)')
              ->execute(array($fn, count($rows), $start, $ks[0], end($ks), $msg));
            $msg .= ' Imported dates ' . implode(', ', $ks) . '.';
            $prev = $rows;
        } else {
            $msg .= ' No schedules imported. This may be an Availability view.';
        }
    }
    ih_render($msg, $prev);
} catch (Throwable $e) {
    http_response_code(500);
    ih_render('Error: ' . $e->getMessage());
}
