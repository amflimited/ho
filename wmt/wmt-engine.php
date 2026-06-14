<?php
/**
 * wmt-engine.php
 * Shared planning + assignment engine for the Walmart AP Services tool.
 *
 * index.php, tasks.php and assignments.php all build on this file so the
 * door / break / task planning logic lives in exactly one place instead of
 * being copied three times with three different function names.
 *
 * Conventions
 *  - Every function is prefixed wmt_ so the three entry points can include
 *    this without colliding with their own (legacy) helper names.
 *  - "Pure" helpers (time, role, break, coverage, balancing, sanity) take
 *    plain arrays and never touch the database, so they can be unit tested.
 *  - DB helpers are thin wrappers around the pure functions.
 *  - PHP 7.x compatible: no enums, match, named args or constructor promotion.
 */

if (!defined('WMT_ENGINE_VERSION')) {
    define('WMT_ENGINE_VERSION', '2026-06-14');

/* ------------------------------------------------------------------ */
/* Section 1: database + schema                                        */
/* ------------------------------------------------------------------ */

function wmt_db()
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    foreach (array(__DIR__ . '/../../database.php', __DIR__ . '/../database.php') as $f) {
        if (is_file($f)) {
            require_once $f;
            break;
        }
    }
    if (function_exists('ho_db')) {
        $pdo = ho_db();
    } elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
    } else {
        throw new Exception('No database connection found. Expected database.php with ho_db().');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

/** Run a migration-style statement, ignoring "already exists" style errors. */
function wmt_try($p, $sql)
{
    try {
        $p->exec($sql);
    } catch (Exception $e) {
        /* idempotent: column/table already present */
    }
}

function wmt_schema($p)
{
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_settings(k VARCHAR(80) PRIMARY KEY,v LONGTEXT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_associates(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(120) NOT NULL UNIQUE,team VARCHAR(40) NOT NULL DEFAULT 'Services',role_type VARCHAR(80) NOT NULL DEFAULT 'AP Service TA',can_cover TINYINT(1) NOT NULL DEFAULT 1,preferred_door VARCHAR(20) NOT NULL DEFAULT 'Either',active TINYINT(1) NOT NULL DEFAULT 1,reliability DECIMAL(5,2) NOT NULL DEFAULT 3.00,notes TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_shifts(id INT AUTO_INCREMENT PRIMARY KEY,work_date DATE NOT NULL,associate_id INT NULL,associate_name VARCHAR(120) NOT NULL,team VARCHAR(40) NOT NULL DEFAULT 'Services',role_type VARCHAR(80) NOT NULL DEFAULT 'AP Service TA',start_time TIME NOT NULL,end_time TIME NOT NULL,notes TEXT,source_file VARCHAR(255),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY work_date(work_date),KEY associate_id(associate_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    wmt_try($p, "ALTER TABLE wmt_shifts ADD COLUMN source_file VARCHAR(255) NULL");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_tasks(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(180) NOT NULL UNIQUE,category VARCHAR(60) NOT NULL DEFAULT 'Entrance Readiness',priority VARCHAR(20) NOT NULL DEFAULT 'Medium',weight INT NOT NULL DEFAULT 2,frequency VARCHAR(40) NOT NULL DEFAULT 'Daily',active TINYINT(1) NOT NULL DEFAULT 1,notes TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_turnins(id INT AUTO_INCREMENT PRIMARY KEY,work_date DATE NOT NULL,task_name VARCHAR(180) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'Not Started',completed_by VARCHAR(120),notes TEXT,manager_signoff VARCHAR(120),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY work_date(work_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_exceptions(id INT AUTO_INCREMENT PRIMARY KEY,work_date DATE NOT NULL,type VARCHAR(80) NOT NULL,post VARCHAR(20),start_time TIME,end_time TIME,associate_name VARCHAR(120),severity VARCHAR(20) NOT NULL DEFAULT 'Medium',notes TEXT,reported_to VARCHAR(120),created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY work_date(work_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_html_imports(id INT AUTO_INCREMENT PRIMARY KEY,filename VARCHAR(255),imported_rows INT NOT NULL DEFAULT 0,week_start DATE NULL,date_min DATE NULL,date_max DATE NULL,message TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_task_assignments(id INT AUTO_INCREMENT PRIMARY KEY,work_date DATE NOT NULL,side VARCHAR(20) NOT NULL,task_name VARCHAR(180) NOT NULL,assigned_to VARCHAR(120),priority VARCHAR(20),status VARCHAR(30) NOT NULL DEFAULT 'Assigned',notes TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY one_daily_task(work_date,side,task_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_assignment_items(id INT AUTO_INCREMENT PRIMARY KEY,code VARCHAR(80) NOT NULL UNIQUE,name VARCHAR(180) NOT NULL,scope VARCHAR(20) NOT NULL DEFAULT 'both',category VARCHAR(80) NOT NULL DEFAULT 'Task',priority VARCHAR(20) NOT NULL DEFAULT 'Medium',estimated_minutes INT NOT NULL DEFAULT 10,preferred_role VARCHAR(30) NOT NULL DEFAULT 'door_owner',frequency VARCHAR(40) NOT NULL DEFAULT 'Daily',active TINYINT(1) NOT NULL DEFAULT 1,instructions TEXT,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    wmt_try($p, "ALTER TABLE wmt_assignment_items ADD COLUMN frequency VARCHAR(40) NOT NULL DEFAULT 'Daily'");
    $p->exec("CREATE TABLE IF NOT EXISTS wmt_auto_assignments(id INT AUTO_INCREMENT PRIMARY KEY,work_date DATE NOT NULL,assignment_type VARCHAR(50) NOT NULL,side VARCHAR(20),associate_name VARCHAR(120),start_time TIME NULL,end_time TIME NULL,item_code VARCHAR(80),item_name VARCHAR(180),category VARCHAR(80) NULL,est_minutes INT NULL,release_required TINYINT(1) NOT NULL DEFAULT 0,target_associate VARCHAR(120),status VARCHAR(30) NOT NULL DEFAULT 'Assigned',source VARCHAR(40) NOT NULL DEFAULT 'auto',notes TEXT,completed_by VARCHAR(120) NULL,completed_at DATETIME NULL,completion_notes TEXT NULL,manager_signoff VARCHAR(120) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY d(work_date),KEY assoc(associate_name),KEY item(item_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach (array(
        "ALTER TABLE wmt_auto_assignments ADD COLUMN category VARCHAR(80) NULL",
        "ALTER TABLE wmt_auto_assignments ADD COLUMN est_minutes INT NULL",
        "ALTER TABLE wmt_auto_assignments ADD COLUMN release_required TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE wmt_auto_assignments ADD COLUMN completed_by VARCHAR(120) NULL",
        "ALTER TABLE wmt_auto_assignments ADD COLUMN completed_at DATETIME NULL",
        "ALTER TABLE wmt_auto_assignments ADD COLUMN completion_notes TEXT NULL",
        "ALTER TABLE wmt_auto_assignments ADD COLUMN manager_signoff VARCHAR(120) NULL",
    ) as $sql) {
        wmt_try($p, $sql);
    }
    wmt_seed_settings($p);
    wmt_seed_tasks($p);
    wmt_seed_items($p);
}

/* ------------------------------------------------------------------ */
/* Section 2: settings                                                 */
/* ------------------------------------------------------------------ */

function wmt_setting($p, $k, $d = null)
{
    try {
        $s = $p->prepare('SELECT v FROM wmt_settings WHERE k=?');
        $s->execute(array($k));
        $v = $s->fetchColumn();
        return $v === false ? $d : $v;
    } catch (Exception $e) {
        return $d;
    }
}

function wmt_set_setting($p, $k, $v)
{
    $p->prepare('INSERT INTO wmt_settings(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')
      ->execute(array($k, (string)$v));
}

function wmt_settings_all($p)
{
    $out = array();
    try {
        foreach ($p->query('SELECT k,v FROM wmt_settings')->fetchAll() as $r) {
            $out[$r['k']] = $r['v'];
        }
    } catch (Exception $e) {
        /* table may not exist yet */
    }
    return $out;
}

function wmt_seed_settings($p)
{
    $def = array(
        'triage_start' => '08:00', 'triage_end' => '17:00', 'slot_minutes' => '15',
        'ops_blackout_start' => '10:00', 'ops_blackout_end' => '12:00',
        'ops_flex_per_person' => '30', 'tl_flex_per_day' => '15',
        'grocery_gold_start' => '06:00', 'grocery_gold_end' => '23:00',
        'gm_gold_start' => '06:00', 'gm_gold_end' => '21:00',
        'investigator_flex' => 'never',
    );
    foreach ($def as $k => $v) {
        if (wmt_setting($p, $k) === null) {
            wmt_set_setting($p, $k, $v);
        }
    }
}

function wmt_seed_tasks($p)
{
    $tasks = array(
        array('Dust EAS pedestals', 'Entrance Readiness', 'Medium', 2, 'Daily'),
        array('Cart wipes dispenser cleaned', 'Entrance Readiness', 'High', 3, 'Daily'),
        array('Benches cleaned', 'Entrance Readiness', 'Medium', 2, 'Daily'),
        array('Windows cleaned', 'Entrance Readiness', 'High', 3, 'Daily'),
        array('Doors cleaned', 'Entrance Readiness', 'High', 3, 'Daily'),
        array('Door track swept', 'Entrance Readiness', 'Medium', 2, 'Daily'),
        array('Closets NCO', 'Organization', 'Low', 1, 'Daily/Weekly'),
        array('Bottom of Mart Carts wiped', 'Entrance Readiness', 'High', 4, 'Daily'),
        array('Vestibules clean and clear of spills/trash', 'Safety', 'Critical', 5, 'Continuous'),
        array('Inclement weather response', 'Safety', 'Critical', 5, 'Triggered'),
        array('Front entrance safety/security observation', 'Coverage', 'Critical', 5, 'Continuous'),
        array('Greet, help, and thank customers', 'Service', 'High', 3, 'Continuous'),
        array('Visual theft deterrence', 'Coverage', 'High', 4, 'Continuous'),
        array('Merchandise protection device support', 'Merchandise Protection', 'Medium', 3, 'As Needed'),
        array('Suspicious activity report/escalation', 'Reporting', 'High', 2, 'As Needed'),
    );
    $q = $p->prepare('INSERT IGNORE INTO wmt_tasks(name,category,priority,weight,frequency) VALUES(?,?,?,?,?)');
    foreach ($tasks as $t) {
        $q->execute($t);
    }
}

function wmt_seed_items($p)
{
    $items = array(
        array('SIDE_VESTIBULE', 'Vestibule clean and clear of spills/trash', 'both', 'Safety', 'Critical', 10, 'door_owner', 'Continuous', 'Complete for assigned side. Keep path clear; report anything that cannot be corrected immediately.'),
        array('SIDE_CART_WIPES', 'Cart wipes dispenser cleaned/stocked', 'both', 'Entrance Readiness', 'High', 8, 'door_owner', 'Daily', 'Check and clean the dispenser area for assigned side.'),
        array('SIDE_BENCHES', 'Benches cleaned', 'both', 'Entrance Readiness', 'Medium', 8, 'door_owner', 'Daily', 'Clean benches on assigned side.'),
        array('SIDE_WINDOWS', 'Windows cleaned', 'both', 'Entrance Readiness', 'High', 15, 'door_owner', 'Daily', 'Clean visible customer-facing windows on assigned side.'),
        array('SIDE_DOORS', 'Doors cleaned', 'both', 'Entrance Readiness', 'High', 15, 'door_owner', 'Daily', 'Clean handles/glass/visible smudges on assigned side.'),
        array('SIDE_TRACK', 'Door track swept', 'both', 'Entrance Readiness', 'Medium', 10, 'door_owner', 'Daily', 'Sweep door track on assigned side when traffic allows.'),
        array('SIDE_EAS', 'Dust EAS pedestals', 'both', 'Entrance Readiness', 'Medium', 8, 'door_owner', 'Daily', 'Dust EAS pedestals on assigned side.'),
        array('SIDE_MART_CARTS', 'Bottom of Mart Carts wiped', 'both', 'Entrance Readiness', 'High', 20, 'floater', 'Daily', 'Complete for assigned side. Floater preferred; door owner only when coverage allows.'),
        array('SIDE_PROTECTION_DEVICES', 'Protection devices returned/organized check', 'both', 'Merchandise Protection', 'Medium', 10, 'door_owner', 'Daily', 'Organize/return protection devices for assigned side as applicable.'),
        array('SIDE_EQUIPMENT', 'Door/vestibule equipment issue check', 'both', 'Safety', 'Medium', 6, 'door_owner', 'Daily', 'Check for door, mat, cone, cart wipe, or equipment issues on assigned side.'),
        array('GEN_GO_BACKS', 'Go backs / front-end returns support', 'general', 'Front End Support', 'Medium', 20, 'floater', 'As Needed', 'Floater task only after doors, breaks, and lunches are protected.'),
        array('GEN_CLOSETS', 'Closets NCO', 'general', 'Organization', 'Low', 15, 'floater', 'Daily/Weekly', 'Keep AP/vestibule storage neat, clean, and organized.'),
        array('GEN_HANDOFF', 'AP Services handoff note', 'general', 'Communication', 'Medium', 8, 'floater', 'Daily', 'Document open issues, missed tasks, weather/safety concerns, and coverage gaps.'),
        array('TRG_WEATHER', 'Inclement weather vestibule response', 'triggered', 'Safety', 'Critical', 20, 'floater', 'Triggered', 'Triggered task. Mats/cones/residual water response overrides lower-priority tasks.'),
        array('TRG_SUSPICIOUS', 'Suspicious activity report/escalation', 'triggered', 'Reporting', 'High', 5, 'door_owner', 'Triggered', 'Triggered task. Observe/report only; do not investigate or detain.'),
    );
    $q = $p->prepare('INSERT IGNORE INTO wmt_assignment_items(code,name,scope,category,priority,estimated_minutes,preferred_role,frequency,instructions) VALUES(?,?,?,?,?,?,?,?,?)');
    foreach ($items as $i) {
        $q->execute($i);
    }
}

/* ------------------------------------------------------------------ */
/* Section 3: pure time + role helpers                                 */
/* ------------------------------------------------------------------ */

function wmt_e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** "HH:MM" -> minutes since midnight. */
function wmt_mins($t)
{
    if (!preg_match('/^(\d{1,2}):(\d{2})/', (string)$t, $m)) {
        return 0;
    }
    return ((int)$m[1] * 60) + (int)$m[2];
}

/** minutes -> "7:45AM" */
function wmt_nice($m)
{
    $m = (($m % 1440) + 1440) % 1440;
    $h = (int)floor($m / 60);
    $ap = $h >= 12 ? 'PM' : 'AM';
    $hh = $h % 12;
    if (!$hh) {
        $hh = 12;
    }
    return $hh . ':' . sprintf('%02d', $m % 60) . $ap;
}

/** minutes -> "07:45:00" for TIME columns. */
function wmt_hhmm($m)
{
    $m = (($m % 1440) + 1440) % 1440;
    return sprintf('%02d:%02d:00', (int)floor($m / 60), $m % 60);
}

function wmt_team_of($r)
{
    $x = strtolower(($r['team'] ?? '') . ' ' . ($r['role_type'] ?? ''));
    if (strpos($x, 'invest') !== false) {
        return 'Investigator';
    }
    if (strpos($x, 'lead') !== false || strpos($x, 'tl') !== false) {
        return 'Team Lead';
    }
    if (strpos($x, 'ops') !== false || strpos($x, 'operation') !== false) {
        return 'Ops';
    }
    return 'Services';
}

function wmt_priority_num($p)
{
    $p = strtolower((string)$p);
    if ($p === 'critical') {
        return 1;
    }
    if ($p === 'high') {
        return 2;
    }
    if ($p === 'medium') {
        return 3;
    }
    return 4;
}

/* ------------------------------------------------------------------ */
/* Section 4: pure shift + break helpers                               */
/* ------------------------------------------------------------------ */

function wmt_shift_span($s)
{
    $st = wmt_mins(substr($s['start_time'], 0, 5));
    $en = wmt_mins(substr($s['end_time'], 0, 5));
    if ($en <= $st) {
        $en += 1440;
    }
    return array($st, $en, $en - $st);
}

function wmt_covers($s, $a, $b)
{
    $x = wmt_shift_span($s);
    return $a >= $x[0] && $b <= $x[1];
}

/** Rough ideal break placement for a shift (used for non-Services staff). */
function wmt_raw_breaks($s, $slot = 15)
{
    $x = wmt_shift_span($s);
    $st = $x[0];
    $dur = $x[2];
    $out = array();
    if ($dur >= 480) {
        $q = $dur / 4;
        $out[] = array('Break 1', (int)(round(($st + $q - 8) / $slot) * $slot), 15);
        $out[] = array('Lunch', (int)(round(($st + 2 * $q - 30) / $slot) * $slot), 60);
        $out[] = array('Break 2', (int)(round(($st + 3 * $q - 8) / $slot) * $slot), 15);
    } elseif ($dur >= 360) {
        $out[] = array('Break', (int)(round(($st + $dur / 3 - 8) / $slot) * $slot), 15);
        $out[] = array('Lunch', (int)(round(($st + $dur * 2 / 3 - 15) / $slot) * $slot), 30);
    } elseif ($dur >= 240) {
        $out[] = array('Break', (int)(round(($st + $dur / 2 - 8) / $slot) * $slot), 15);
    }
    return $out;
}

/** Break "needs" with feasible windows, for the staggering planner. */
function wmt_break_needs($s, $slot)
{
    $x = wmt_shift_span($s);
    $st = $x[0];
    $en = $x[1];
    $dur = $x[2];
    $n = $s['associate_name'];
    $needs = array();
    if ($dur >= 480) {
        $q = $dur / 4;
        $needs[] = array('associate' => $n, 'type' => 'Break 1', 'duration' => 15, 'ideal' => (int)(round(($st + $q - 8) / $slot) * $slot), 'earliest' => $st + 75, 'latest' => min($st + 240, $en - 240), 'order' => 1);
        $needs[] = array('associate' => $n, 'type' => 'Lunch', 'duration' => 60, 'ideal' => (int)(round(($st + 2 * $q - 30) / $slot) * $slot), 'earliest' => $st + 210, 'latest' => $en - 180, 'order' => 2);
        $needs[] = array('associate' => $n, 'type' => 'Break 2', 'duration' => 15, 'ideal' => (int)(round(($st + 3 * $q - 8) / $slot) * $slot), 'earliest' => max($st + 300, $en - 240), 'latest' => $en - 45, 'order' => 3);
    } elseif ($dur >= 360) {
        $needs[] = array('associate' => $n, 'type' => 'Break', 'duration' => 15, 'ideal' => (int)(round(($st + $dur / 3 - 8) / $slot) * $slot), 'earliest' => $st + 90, 'latest' => $en - 210, 'order' => 1);
        $needs[] = array('associate' => $n, 'type' => 'Lunch', 'duration' => 30, 'ideal' => (int)(round(($st + $dur * 2 / 3 - 15) / $slot) * $slot), 'earliest' => $st + 180, 'latest' => $en - 90, 'order' => 2);
    } elseif ($dur >= 240) {
        $needs[] = array('associate' => $n, 'type' => 'Break', 'duration' => 15, 'ideal' => (int)(round(($st + $dur / 2 - 8) / $slot) * $slot), 'earliest' => $st + 90, 'latest' => $en - 45, 'order' => 1);
    }
    foreach ($needs as &$b) {
        if ($b['latest'] < $b['earliest']) {
            $b['earliest'] = $st + 30;
            $b['latest'] = $en - $b['duration'];
        }
        $b['earliest'] = (int)(ceil($b['earliest'] / $slot) * $slot);
        $b['latest'] = (int)(floor($b['latest'] / $slot) * $slot);
    }
    unset($b);
    return $needs;
}

/** How many Services TAs can hold a door across [a,b), excluding one name. */
function wmt_available_services($sh, $a, $b, $breakSlots, $exclude = '')
{
    $n = 0;
    foreach ($sh as $s) {
        if (wmt_team_of($s) !== 'Services' || !(int)($s['can_cover'] ?? 1)) {
            continue;
        }
        if (!wmt_covers($s, $a, $b)) {
            continue;
        }
        $name = $s['associate_name'];
        if ($name === $exclude) {
            continue;
        }
        if (isset($breakSlots[$a]) && in_array($name, $breakSlots[$a], true)) {
            continue;
        }
        $n++;
    }
    return $n;
}

/**
 * Stagger Services breaks/lunches so coverage is preserved. Each break is
 * placed in the slot that does the least damage to door coverage; if no clean
 * window exists, the least-bad slot is chosen and a warning is recorded. This
 * is the "choose the better option" sanity rule for break placement.
 */
function wmt_build_break_plan($sh, $slot)
{
    $needs = array();
    foreach ($sh as $s) {
        if (wmt_team_of($s) === 'Services' && (int)($s['can_cover'] ?? 1)) {
            foreach (wmt_break_needs($s, $slot) as $b) {
                $needs[] = $b;
            }
        }
    }
    usort($needs, function ($a, $b) {
        if ($a['duration'] === $b['duration']) {
            return $a['ideal'] - $b['ideal'];
        }
        return $b['duration'] - $a['duration'];
    });
    $breakSlots = array();
    $events = array();
    $warnings = array();
    foreach ($needs as $b) {
        $spread = 150;
        $lo = max($b['earliest'], $b['ideal'] - $spread);
        $hi = min($b['latest'], $b['ideal'] + $spread);
        if ($lo > $hi) {
            $lo = $b['earliest'];
            $hi = $b['latest'];
        }
        $best = $b['ideal'];
        $bestScore = 999999999;
        $bestImpact = 0;
        for ($cand = $lo; $cand <= $hi; $cand += $slot) {
            $score = abs($cand - $b['ideal']);
            $impact = 0;
            foreach ($events as $ev) {
                if ($ev['associate'] === $b['associate']) {
                    if ($b['order'] > $ev['order'] && $cand < $ev['end'] + 60) {
                        $score += 50000;
                    }
                    if ($b['order'] < $ev['order'] && $cand + $b['duration'] + 60 > $ev['start']) {
                        $score += 50000;
                    }
                }
            }
            for ($t = $cand; $t < $cand + $b['duration']; $t += $slot) {
                $already = count($breakSlots[$t] ?? array());
                if ($already > 0) {
                    $score += 5000 * $already;
                }
                $after = wmt_available_services($sh, $t, $t + $slot, $breakSlots, $b['associate']);
                if ($after <= 0) {
                    $score += 100000;
                    $impact += 2;
                } elseif ($after == 1) {
                    $score += 900;
                    $impact += 1;
                } else {
                    $score -= 200;
                }
            }
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $cand;
                $bestImpact = $impact;
            }
        }
        $b['start'] = $best;
        $b['end'] = $best + $b['duration'];
        $b['impact'] = $bestImpact;
        if ($bestImpact > 0) {
            $warnings[] = $b['associate'] . ' ' . $b['type'] . ' has a coverage impact; no cleaner stagger window existed.';
        }
        $events[] = $b;
        for ($t = $b['start']; $t < $b['end']; $t += $slot) {
            if (!isset($breakSlots[$t])) {
                $breakSlots[$t] = array();
            }
            $breakSlots[$t][] = $b['associate'];
        }
    }
    usort($events, function ($a, $b) {
        if ($a['start'] === $b['start']) {
            return strcmp($a['associate'], $b['associate']);
        }
        return $a['start'] - $b['start'];
    });
    $labels = array();
    foreach ($events as $ev) {
        for ($t = $ev['start']; $t < $ev['end']; $t += $slot) {
            $labels[$ev['associate']][$t] = $ev['type'];
        }
    }
    return array('events' => $events, 'labels' => $labels, 'warnings' => $warnings);
}

/** Off-post label for a shift at a given slot (Services use the staggered plan). */
function wmt_off_label($bp, $s, $slot)
{
    $name = $s['associate_name'];
    if (wmt_team_of($s) === 'Services') {
        return $bp['labels'][$name][$slot] ?? '';
    }
    foreach (wmt_raw_breaks($s, 15) as $b) {
        if ($slot >= $b[1] && $slot < $b[1] + $b[2]) {
            return $b[0];
        }
    }
    return '';
}

/* ------------------------------------------------------------------ */
/* Section 5: door-coverage planner                                    */
/* ------------------------------------------------------------------ */

/**
 * Score a Services TA for a door. Lower = better pick. Balances the current
 * day, long-run door minutes, side history, and honours preferred door.
 */
function wmt_door_score($n, $door, $r, $hist, $day)
{
    $s = 0;
    // Note: this only runs when a door needs a *fresh* owner (start of day or a
    // hand-off), never every slot — continuity is handled in wmt_plan_core. So
    // we balance on fairness (who has held doors least today) and long-run
    // history, not on per-slot churn.
    $s += (($day[$n]['Grocery'] ?? 0) + ($day[$n]['GM'] ?? 0)) / 30;    // today, total door load
    $s += ($hist[$n]['side_' . $door] ?? 0) / 120;                     // history, this side minutes
    $s += ($hist[$n]['door_minutes'] ?? 0) / 480;                      // history, total door minutes
    $pref = $r['preferred_door'] ?? 'Either';
    if ($pref === $door) {
        $s -= 8;
    } elseif ($pref === 'Either') {
        $s -= 2;
    } else {
        $s += 2;
    }
    $s -= (float)($r['reliability'] ?? 3);
    return $s;
}

function wmt_pick_service($cands, $door, $used, $hist, $day)
{
    $best = null;
    $bs = 1.0e12;
    foreach ($cands as $r) {
        $n = $r['associate_name'];
        if (isset($used[$n])) {
            continue;
        }
        $s = wmt_door_score($n, $door, $r, $hist, $day);
        if ($s < $bs) {
            $bs = $s;
            $best = $r;
        }
    }
    return $best;
}

/** Find runs of slots where both doors are empty (hard sanity flag). */
function wmt_uncovered_windows($rows)
{
    $out = array();
    $cur = null;
    foreach ($rows as $r) {
        $both = ($r['grocery'] === '' && $r['gm'] === '');
        if ($both) {
            if ($cur === null) {
                $cur = array('start' => $r['start'], 'end' => $r['end']);
            } else {
                $cur['end'] = $r['end'];
            }
        } elseif ($cur !== null) {
            $out[] = $cur;
            $cur = null;
        }
    }
    if ($cur !== null) {
        $out[] = $cur;
    }
    return $out;
}

/**
 * Pure coverage planner. Walks the day in $slot-minute steps and assigns
 * Grocery + GM doors, Ops/TL flex (within the rules), and a floater. Returns a
 * rich structure consumed by every page and by the assignment generator.
 */
function wmt_plan_core($sh, $settings, $hist, $date)
{
    $slot = (int)($settings['slot_minutes'] ?? 15);
    if ($slot < 5) {
        $slot = 15;
    }
    $start = wmt_mins($settings['triage_start'] ?? '08:00');
    $end = wmt_mins($settings['triage_end'] ?? '17:00');
    $opsB = wmt_mins($settings['ops_blackout_start'] ?? '10:00');
    $opsE = wmt_mins($settings['ops_blackout_end'] ?? '12:00');
    $opsMax = (int)($settings['ops_flex_per_person'] ?? 30);
    $tlMax = (int)($settings['tl_flex_per_day'] ?? 15);

    $bp = wmt_build_break_plan($sh, $slot);
    $rows = array();
    $dayDoor = array();
    $opsUsed = array();
    $tlUsed = 0;
    $sideMin = array('Grocery' => array(), 'GM' => array());
    $floaterMin = array();
    $doorMin = array();
    $gap = 0;
    $cov = 0;
    $req = 0;
    $float = 0;
    $cur = array('Grocery' => null, 'GM' => null); // current Services door owners

    for ($t = $start; $t < $end; $t += $slot) {
        $avail = array();
        $offList = array();
        $offStr = array();
        foreach ($sh as $s) {
            if (!wmt_covers($s, $t, $t + $slot)) {
                continue;
            }
            $o = wmt_off_label($bp, $s, $t);
            if ($o) {
                $offList[] = array('name' => $s['associate_name'], 'label' => $o);
                $offStr[] = $s['associate_name'] . ' ' . $o;
            } else {
                $avail[] = $s;
            }
        }
        $svc = array();
        $ops = array();
        $tls = array();
        foreach ($avail as $x) {
            $tm = wmt_team_of($x);
            if ($tm === 'Services' && (int)($x['can_cover'] ?? 1)) {
                $svc[] = $x;
            } elseif ($tm === 'Ops') {
                $ops[] = $x;
            } elseif ($tm === 'Team Lead') {
                $tls[] = $x;
            }
        }
        $used = array();
        $door_take = array('Grocery' => null, 'GM' => null);
        // Pass 1: keep the incumbent on each door if they are still clocked in
        // and not on break — a TA holds their post, they do not swap doors.
        foreach (array('Grocery', 'GM') as $door) {
            $inc = $cur[$door];
            if ($inc === null) {
                continue;
            }
            foreach ($svc as $x) {
                if ($x['associate_name'] === $inc && !isset($used[$inc])) {
                    $door_take[$door] = $x;
                    $used[$inc] = 1;
                    break;
                }
            }
            if ($door_take[$door] === null) {
                $cur[$door] = null; // incumbent left or went on break
            }
        }
        // Pass 2: fill any still-open door with the most balanced fresh pick.
        foreach (array('Grocery', 'GM') as $door) {
            if ($door_take[$door] !== null) {
                continue;
            }
            $pick = wmt_pick_service($svc, $door, $used, $hist, $dayDoor);
            if ($pick) {
                $door_take[$door] = $pick;
                $used[$pick['associate_name']] = 1;
                $cur[$door] = $pick['associate_name'];
            }
        }
        $g = $door_take['Grocery'];
        $gm = $door_take['GM'];
        if ($g) {
            $n = $g['associate_name'];
            $dayDoor[$n]['Grocery'] = ($dayDoor[$n]['Grocery'] ?? 0) + $slot;
            $sideMin['Grocery'][$n] = ($sideMin['Grocery'][$n] ?? 0) + $slot;
            $doorMin[$n] = ($doorMin[$n] ?? 0) + $slot;
        }
        if ($gm) {
            $n = $gm['associate_name'];
            $dayDoor[$n]['GM'] = ($dayDoor[$n]['GM'] ?? 0) + $slot;
            $sideMin['GM'][$n] = ($sideMin['GM'][$n] ?? 0) + $slot;
            $doorMin[$n] = ($doorMin[$n] ?? 0) + $slot;
        }
        $flex = array();
        foreach (array('Grocery', 'GM') as $door) {
            $covered = ($door === 'Grocery') ? $g : $gm;
            if ($covered) {
                continue;
            }
            $assigned = null;
            if (count($ops) >= 2 && !($t < $opsE && $t + $slot > $opsB)) {
                foreach ($ops as $o) {
                    $n = $o['associate_name'];
                    if (!isset($used[$n]) && (($opsUsed[$n] ?? 0) + $slot) <= $opsMax) {
                        $assigned = $o;
                        $used[$n] = 1;
                        $opsUsed[$n] = ($opsUsed[$n] ?? 0) + $slot;
                        $flex[] = $n . ' Ops flex';
                        break;
                    }
                }
            }
            if (!$assigned && $tlUsed + $slot <= $tlMax && $tls) {
                foreach ($tls as $tl) {
                    $n = $tl['associate_name'];
                    if (!isset($used[$n])) {
                        $assigned = $tl;
                        $used[$n] = 1;
                        $tlUsed += $slot;
                        $flex[] = $n . ' TL flex';
                        break;
                    }
                }
            }
            if ($assigned) {
                if ($door === 'Grocery') {
                    $g = $assigned;
                } else {
                    $gm = $assigned;
                }
            }
        }
        $fl = '';
        foreach ($svc as $s) {
            $n = $s['associate_name'];
            if (!isset($used[$n])) {
                $fl = $n;
                $floaterMin[$n] = ($floaterMin[$n] ?? 0) + $slot;
                break;
            }
        }
        $note = array();
        $req += 2 * $slot;
        if ($g) {
            $cov += $slot;
        } else {
            $gap += $slot;
            $note[] = 'Grocery gap';
        }
        if ($gm) {
            $cov += $slot;
        } else {
            $gap += $slot;
            $note[] = 'GM gap';
        }
        if ($fl) {
            $float += $slot;
        }
        $rows[] = array(
            'start' => $t, 'end' => $t + $slot,
            'grocery' => $g ? $g['associate_name'] : '',
            'gm' => $gm ? $gm['associate_name'] : '',
            'floater' => $fl,
            'off' => implode(', ', $offStr),
            'off_list' => $offList,
            'flex' => implode(', ', $flex),
            'watch' => implode('; ', $note),
        );
    }
    return array(
        'date' => $date, 'shifts' => $sh, 'rows' => $rows,
        'gap' => $gap, 'cov' => $cov, 'req' => $req,
        'pct' => $req ? round($cov / $req * 100, 1) : 0,
        'float' => $float, 'opsUsed' => $opsUsed, 'tlUsed' => $tlUsed,
        'break_plan' => $bp, 'side_minutes' => $sideMin,
        'floater_minutes' => $floaterMin, 'door_minutes' => $doorMin,
        'hist' => $hist, 'uncovered' => wmt_uncovered_windows($rows),
        'settings' => $settings, 'slot' => $slot,
    );
}

/* ------------------------------------------------------------------ */
/* Section 6: data loaders                                             */
/* ------------------------------------------------------------------ */

function wmt_shifts($p, $d)
{
    $q = $p->prepare('SELECT s.*,a.preferred_door,a.can_cover,a.reliability FROM wmt_shifts s LEFT JOIN wmt_associates a ON a.id=s.associate_id WHERE work_date=? ORDER BY start_time,associate_name');
    $q->execute(array($d));
    return $q->fetchAll();
}

/**
 * Long-run, per-associate balancing history from saved assignments before $d.
 * Captures door minutes, Grocery/GM split, task category counts, specific task
 * counts, and an "avoid" weight (Missed/Deferred tasks count extra so we stop
 * handing someone the same disliked task).
 */
function wmt_history($p, $beforeDate)
{
    $out = array();
    try {
        $q = $p->prepare("SELECT associate_name n, side, assignment_type t, item_code code, category cat, status, COUNT(*) c, COALESCE(SUM(TIMESTAMPDIFF(MINUTE,start_time,end_time)),0) mins FROM wmt_auto_assignments WHERE work_date<? AND associate_name IS NOT NULL AND associate_name<>'' GROUP BY associate_name,side,assignment_type,item_code,category,status");
        $q->execute(array($beforeDate));
        foreach ($q->fetchAll() as $r) {
            $n = $r['n'];
            if (!isset($out[$n])) {
                $out[$n] = array('total' => 0, 'door_minutes' => 0, 'side_Grocery' => 0, 'side_GM' => 0, 'task_total' => 0);
            }
            $c = (int)$r['c'];
            $out[$n]['total'] += $c;
            if ($r['t'] === 'Door Post') {
                $out[$n]['door_minutes'] += (int)$r['mins'];
                if ($r['side'] === 'Grocery') {
                    $out[$n]['side_Grocery'] += (int)$r['mins'];
                } elseif ($r['side'] === 'GM') {
                    $out[$n]['side_GM'] += (int)$r['mins'];
                }
            }
            if (!empty($r['code'])) {
                $out[$n]['task_total'] += $c;
                $out[$n]['item_' . $r['code']] = ($out[$n]['item_' . $r['code']] ?? 0) + $c;
                $w = $c;
                if ($r['status'] === 'Missed' || $r['status'] === 'Deferred') {
                    $w += 2 * $c;
                }
                $out[$n]['avoid_' . $r['code']] = ($out[$n]['avoid_' . $r['code']] ?? 0) + $w;
            }
            if (!empty($r['cat'])) {
                $out[$n]['cat_' . $r['cat']] = ($out[$n]['cat_' . $r['cat']] ?? 0) + $c;
            }
        }
    } catch (Exception $e) {
        /* no history table yet */
    }
    return $out;
}

function wmt_plan($p, $d)
{
    return wmt_plan_core(wmt_shifts($p, $d), wmt_settings_all($p), wmt_history($p, $d), $d);
}

function wmt_items($p, $activeOnly = true)
{
    $sql = "SELECT * FROM wmt_assignment_items" . ($activeOnly ? " WHERE active=1" : "") . " ORDER BY FIELD(priority,'Critical','High','Medium','Low'),estimated_minutes DESC,name";
    return $p->query($sql)->fetchAll();
}

function wmt_task_rows($p)
{
    $rows = $p->query('SELECT * FROM wmt_tasks WHERE active=1')->fetchAll();
    usort($rows, function ($a, $b) {
        $pa = wmt_priority_num($a['priority']);
        $pb = wmt_priority_num($b['priority']);
        if ($pa === $pb) {
            return (int)$b['weight'] - (int)$a['weight'];
        }
        return $pa - $pb;
    });
    return $rows;
}

/* ------------------------------------------------------------------ */
/* Section 7: assignment generation                                    */
/* ------------------------------------------------------------------ */

function wmt_add(&$a, $type, $side, $name, $st, $en, $code, $item, $cat, $notes, $release, $est)
{
    $a[] = array(
        'assignment_type' => $type, 'side' => $side, 'associate_name' => $name,
        'start' => $st, 'end' => $en, 'item_code' => $code, 'item_name' => $item,
        'category' => $cat, 'notes' => $notes, 'release_required' => $release ? 1 : 0,
        'est_minutes' => $est,
    );
}

/** Compress per-slot rows into contiguous blocks where $field === $name. */
function wmt_name_blocks($rows, $field, $name)
{
    $b = array();
    foreach ($rows as $r) {
        if (($r[$field] ?? '') !== $name) {
            continue;
        }
        $i = count($b) - 1;
        if ($i >= 0 && $b[$i]['end'] === $r['start']) {
            $b[$i]['end'] = $r['end'];
        } else {
            $b[] = array('start' => $r['start'], 'end' => $r['end']);
        }
    }
    return $b;
}

function wmt_side_field($side)
{
    return $side === 'Grocery' ? 'grocery' : ($side === 'GM' ? 'gm' : null);
}

/** Two plan rows share a block if all $keys match (used to compress the grid). */
function wmt_same_block($a, $b, $keys)
{
    foreach ($keys as $k) {
        if (($a[$k] ?? '') !== ($b[$k] ?? '')) {
            return false;
        }
    }
    return true;
}

/** Collapse the 15-minute grid into change-only blocks for display/print. */
function wmt_compress_rows($rows, $keys)
{
    $blocks = array();
    foreach ($rows as $r) {
        $i = count($blocks) - 1;
        if ($i >= 0 && $blocks[$i]['end'] === $r['start'] && wmt_same_block($blocks[$i], $r, $keys)) {
            $blocks[$i]['end'] = $r['end'];
        } else {
            $blocks[] = $r;
        }
    }
    return $blocks;
}

/** Per-associate position blocks (door / floater / break) for a position sheet. */
function wmt_person_blocks($pl, $name)
{
    $items = array();
    foreach ($pl['rows'] as $r) {
        $pos = '';
        $handoff = '';
        if ($r['grocery'] === $name) {
            $pos = 'Grocery Door';
            $handoff = 'Wait for assigned relief / leadership direction.';
        } elseif ($r['gm'] === $name) {
            $pos = 'GM Door';
            $handoff = 'Wait for assigned relief / leadership direction.';
        } elseif ($r['floater'] === $name) {
            $pos = 'Task/Floater';
            $handoff = 'Stay available for door relief first; tasks second.';
        } elseif (strpos($r['off'], $name) !== false) {
            $pos = 'Break/Lunch';
            $handoff = 'Return to assigned post at end of window.';
        }
        if ($pos) {
            $items[] = array('start' => $r['start'], 'end' => $r['end'], 'pos' => $pos, 'handoff' => $handoff, 'notes' => $r['watch']);
        }
    }
    return wmt_compress_rows($items, array('pos', 'handoff', 'notes'));
}

function wmt_all_service_names($pl)
{
    $out = array();
    foreach ($pl['shifts'] as $s) {
        if (wmt_team_of($s) === 'Services' && (int)($s['can_cover'] ?? 1)) {
            $out[$s['associate_name']] = 1;
        }
    }
    return array_keys($out);
}

/** Lower = better owner for a task. Spreads task/category/specific history. */
function wmt_task_score($n, $item, $side, $pl, $load, $hist)
{
    $s = ($load[$n] ?? 0) / 10;                                  // today's task load
    $s += ($hist[$n]['item_' . $item['code']] ?? 0) * 4;        // specific task history
    $s += ($hist[$n]['avoid_' . $item['code']] ?? 0) * 2;      // disliked / missed repeat
    $s += ($hist[$n]['cat_' . ($item['category'] ?? '')] ?? 0); // category history
    $s += ($hist[$n]['task_total'] ?? 0) / 4;                  // overall task fairness
    if ($side) {
        $s -= (($pl['side_minutes'][$side][$n] ?? 0)) / 120;    // prefer the strong side owner
    } else {
        $s -= (($pl['floater_minutes'][$n] ?? 0)) / 120;
    }
    return $s;
}

function wmt_choose_task_owner($item, $side, $pl, &$load)
{
    $hist = $pl['hist'];
    $owners = $side ? array_keys($pl['side_minutes'][$side]) : array();
    $floats = array_keys($pl['floater_minutes']);
    $role = $item['preferred_role'];
    if ($role === 'floater') {
        $pool = $floats ? $floats : $owners;
    } elseif ($role === 'door_owner') {
        $pool = $owners ? $owners : $floats;
    } else {
        $pool = array_values(array_unique(array_merge($owners, $floats)));
    }
    if (!$pool) {
        $pool = wmt_all_service_names($pl);
    }
    if (!$pool) {
        return 'Unassigned';
    }
    $best = 'Unassigned';
    $bs = 1.0e12;
    foreach ($pool as $n) {
        $s = wmt_task_score($n, $item, $side, $pl, $load, $hist);
        if ($s < $bs) {
            $bs = $s;
            $best = $n;
        }
    }
    if ($best !== 'Unassigned') {
        $load[$best] = ($load[$best] ?? 0) + (int)$item['estimated_minutes'];
    }
    return $best;
}

/** Suggested window (first owned block) for a task owner. */
function wmt_task_window($pl, $side, $name)
{
    if ($name === 'Unassigned') {
        return array(null, null, 'No AP Service TA owns this during the planning window');
    }
    if ($side) {
        $b = wmt_name_blocks($pl['rows'], wmt_side_field($side), $name);
        if ($b) {
            return array($b[0]['start'], $b[0]['end'], wmt_nice($b[0]['start']) . '-' . wmt_nice($b[0]['end']));
        }
    }
    $b = wmt_name_blocks($pl['rows'], 'floater', $name);
    if ($b) {
        return array($b[0]['start'], $b[0]['end'], wmt_nice($b[0]['start']) . '-' . wmt_nice($b[0]['end']));
    }
    return array(null, null, 'During assigned coverage when traffic allows');
}

/** Is a floater available the whole window so the owner can be released? */
function wmt_has_relief($pl, $name, $st, $en)
{
    if ($st === null || $en === null) {
        return true;
    }
    foreach ($pl['rows'] as $r) {
        if ($r['end'] <= $st || $r['start'] >= $en) {
            continue;
        }
        if ($r['floater'] === '') {
            return false;
        }
    }
    return true;
}

/**
 * Build the full daily assignment list from a plan + task definitions.
 * Pure (no DB) so it can be tested directly. Order of operations encodes the
 * operating model: doors first, then break cover, then breaks, then tasks.
 */
function wmt_build_assignments($pl, $items)
{
    $out = array();

    // 1. Door posts (the doors own the day).
    foreach (array('Grocery', 'GM') as $side) {
        $field = wmt_side_field($side);
        foreach (array_keys($pl['side_minutes'][$side]) as $n) {
            foreach (wmt_name_blocks($pl['rows'], $field, $n) as $b) {
                wmt_add($out, 'Door Post', $side, $n, $b['start'], $b['end'], null, $side . ' Door', 'Coverage', 'Hold the ' . $side . ' door. Do not leave the post until relieved or directed by leadership.', 0, 0);
            }
        }
    }

    // 2. Flex coverage (Ops / Team Lead filling a door).
    foreach ($pl['rows'] as $r) {
        if ($r['flex'] === '') {
            continue;
        }
    }

    // 3. Floater / break cover.
    foreach (array_keys($pl['floater_minutes']) as $n) {
        foreach (wmt_name_blocks($pl['rows'], 'floater', $n) as $b) {
            wmt_add($out, 'Floater / Break Cover', 'Both', $n, $b['start'], $b['end'], null, 'Floater / Break Cover', 'Coverage', 'Cover door breaks and lunches first; general tasks only when both doors are protected.', 0, 0);
        }
    }

    // 4. Breaks / lunches (already staggered by the planner).
    foreach ($pl['break_plan']['events'] as $ev) {
        wmt_add($out, 'Break/Lunch', null, $ev['associate'], $ev['start'], $ev['end'], null, $ev['type'], 'Break', 'Scheduled ' . $ev['type'] . '. Hand off your post before leaving and return promptly.', 0, 0);
    }

    // 5. Side + general tasks, balanced across owners/floaters.
    $load = array();
    foreach ($items as $item) {
        $scope = $item['scope'];
        if ($scope === 'triggered') {
            continue;
        }
        if ($scope === 'both') {
            $sides = array('Grocery', 'GM');
        } elseif ($scope === 'grocery') {
            $sides = array('Grocery');
        } elseif ($scope === 'gm') {
            $sides = array('GM');
        } else {
            $sides = array(null);
        }
        foreach ($sides as $side) {
            $owner = wmt_choose_task_owner($item, $side, $pl, $load);
            $win = wmt_task_window($pl, $side, $owner);
            $release = 0;
            $rnote = '';
            if ($owner !== 'Unassigned' && $side !== null && !wmt_has_relief($pl, $owner, $win[0], $win[1])) {
                $release = 1;
                $rnote = ' Complete only if released: you are the only ' . $side . ' door owner during this window.';
            }
            $type = $side === null ? 'General Task' : 'Door Task';
            $sideLabel = $side === null ? 'Both' : $side;
            wmt_add($out, $type, $sideLabel, $owner, $win[0], $win[1], $item['code'], $item['name'], $item['category'], trim($item['instructions'] . ' Suggested: ' . $win[2] . $rnote), $release, (int)$item['estimated_minutes']);
        }
    }

    // 6. Triggered standing instructions (no owner / time).
    foreach ($items as $item) {
        if ($item['scope'] !== 'triggered') {
            continue;
        }
        wmt_add($out, 'Triggered', null, null, null, null, $item['code'], $item['name'], $item['category'], $item['instructions'], 0, (int)$item['estimated_minutes']);
    }

    return $out;
}

function wmt_generate_day($p, $d)
{
    $pl = wmt_plan($p, $d);
    return array('rows' => wmt_build_assignments($pl, wmt_items($p, true)), 'plan' => $pl);
}

/* ------------------------------------------------------------------ */
/* Section 8: persistence of generated assignments                     */
/* ------------------------------------------------------------------ */

function wmt_day_has_saved($p, $d)
{
    try {
        $q = $p->prepare('SELECT COUNT(*) FROM wmt_auto_assignments WHERE work_date=?');
        $q->execute(array($d));
        return (int)$q->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function wmt_date_has_shifts($p, $d)
{
    $q = $p->prepare('SELECT COUNT(*) FROM wmt_shifts WHERE work_date=?');
    $q->execute(array($d));
    return (int)$q->fetchColumn() > 0;
}

function wmt_imported_dates($p)
{
    try {
        return $p->query('SELECT DISTINCT work_date FROM wmt_shifts ORDER BY work_date')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return array();
    }
}

function wmt_save_day($p, $d, $rows)
{
    $p->prepare('DELETE FROM wmt_auto_assignments WHERE work_date=?')->execute(array($d));
    $q = $p->prepare('INSERT INTO wmt_auto_assignments(work_date,assignment_type,side,associate_name,start_time,end_time,item_code,item_name,category,est_minutes,release_required,target_associate,status,source,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($rows as $r) {
        $st = $r['start'] === null ? null : wmt_hhmm($r['start']);
        $en = $r['end'] === null ? null : wmt_hhmm($r['end']);
        $q->execute(array(
            $d, $r['assignment_type'], $r['side'], $r['associate_name'], $st, $en,
            $r['item_code'], $r['item_name'], $r['category'], $r['est_minutes'],
            $r['release_required'], null, 'Assigned', 'auto', $r['notes'],
        ));
    }
    return count($rows);
}

function wmt_load_saved($p, $d)
{
    $q = $p->prepare("SELECT * FROM wmt_auto_assignments WHERE work_date=? ORDER BY (associate_name IS NULL OR associate_name=''),associate_name,FIELD(assignment_type,'Door Post','Floater / Break Cover','Break/Lunch','Door Task','General Task','Triggered'),start_time");
    $q->execute(array($d));
    return $q->fetchAll();
}

/** Generate + save a set of dates. Skips a date that already has saved rows
 *  unless $overwrite is set. Returns date => count|'skipped'. */
function wmt_generate_dates($p, $dates, $overwrite)
{
    $made = array();
    foreach ($dates as $day) {
        if (!wmt_date_has_shifts($p, $day)) {
            continue;
        }
        if ($overwrite || !wmt_day_has_saved($p, $day)) {
            $gen = wmt_generate_day($p, $day);
            $made[$day] = wmt_save_day($p, $day, $gen['rows']);
        } else {
            $made[$day] = 'skipped';
        }
    }
    return $made;
}

function wmt_update_completion($p, $id, $status, $by, $notes, $mgr)
{
    $valid = array('Assigned', 'Completed', 'Missed', 'Deferred', 'N/A');
    if (!in_array($status, $valid, true)) {
        $status = 'Assigned';
    }
    $completedAt = ($status === 'Completed') ? date('Y-m-d H:i:s') : null;
    $p->prepare('UPDATE wmt_auto_assignments SET status=?,completed_by=?,completion_notes=?,manager_signoff=?,completed_at=COALESCE(?,completed_at) WHERE id=?')
      ->execute(array($status, $by !== '' ? $by : null, $notes !== '' ? $notes : null, $mgr !== '' ? $mgr : null, $completedAt, (int)$id));
}

/* ------------------------------------------------------------------ */
/* Section 9: task-definition CRUD + associates                        */
/* ------------------------------------------------------------------ */

function wmt_item_scope_ok($scope)
{
    return in_array($scope, array('grocery', 'gm', 'both', 'general', 'triggered'), true);
}

function wmt_item_role_ok($role)
{
    return in_array($role, array('door_owner', 'floater', 'any'), true);
}

function wmt_item_save($p, $post)
{
    $name = trim($post['name'] ?? '');
    if ($name === '') {
        return 'No task name entered.';
    }
    $scope = $post['scope'] ?? 'general';
    if (!wmt_item_scope_ok($scope)) {
        $scope = 'general';
    }
    $role = $post['preferred_role'] ?? 'floater';
    if (!wmt_item_role_ok($role)) {
        $role = 'floater';
    }
    $cat = trim($post['category'] ?? 'Custom');
    $prio = $post['priority'] ?? 'Medium';
    $mins = (int)($post['estimated_minutes'] ?? 10);
    $freq = trim($post['frequency'] ?? 'Daily');
    $instr = trim($post['instructions'] ?? '');
    $id = (int)($post['id'] ?? 0);
    if ($id > 0) {
        $p->prepare('UPDATE wmt_assignment_items SET name=?,scope=?,category=?,priority=?,estimated_minutes=?,preferred_role=?,frequency=?,instructions=? WHERE id=?')
          ->execute(array($name, $scope, $cat, $prio, $mins, $role, $freq, $instr, $id));
        return 'Updated task definition: ' . $name;
    }
    $code = 'CUSTOM_' . strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper(substr($name, 0, 40)))) . '_' . date('His');
    $p->prepare('INSERT INTO wmt_assignment_items(code,name,scope,category,priority,estimated_minutes,preferred_role,frequency,instructions) VALUES(?,?,?,?,?,?,?,?,?)')
      ->execute(array($code, $name, $scope, $cat, $prio, $mins, $role, $freq, $instr));
    return 'Added task definition: ' . $name;
}

function wmt_item_set_active($p, $id, $active)
{
    $p->prepare('UPDATE wmt_assignment_items SET active=? WHERE id=?')->execute(array($active ? 1 : 0, (int)$id));
}

function wmt_associates($p)
{
    try {
        return $p->query('SELECT * FROM wmt_associates ORDER BY team,name')->fetchAll();
    } catch (Exception $e) {
        return array();
    }
}

function wmt_associate_set_pref($p, $id, $door)
{
    if (!in_array($door, array('Grocery', 'GM', 'Either'), true)) {
        $door = 'Either';
    }
    $p->prepare('UPDATE wmt_associates SET preferred_door=? WHERE id=?')->execute(array($door, (int)$id));
}

/** Insert/refresh an associate. Does NOT overwrite a manually set preferred
 *  door on re-import, so operator preferences survive weekly schedule loads. */
function wmt_up_assoc($p, $r)
{
    $n = trim($r['name'] ?? $r['associate_name'] ?? '');
    if ($n === '') {
        return 0;
    }
    $team = $r['team'] ?? 'Services';
    $role = $r['role_type'] ?? ($r['role'] ?? 'AP Service TA');
    $pref = $r['preferred_door'] ?? 'Either';
    if (!in_array($pref, array('Grocery', 'GM', 'Either'), true)) {
        $pref = 'Either';
    }
    $can = (wmt_team_of(array('team' => $team, 'role_type' => $role)) === 'Investigator') ? 0 : 1;
    $p->prepare('INSERT INTO wmt_associates(name,team,role_type,can_cover,preferred_door,notes) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE team=VALUES(team),role_type=VALUES(role_type),can_cover=VALUES(can_cover),notes=VALUES(notes)')
      ->execute(array($n, $team, $role, $can, $pref, $r['notes'] ?? null));
    $id = (int)$p->lastInsertId();
    if (!$id) {
        $q = $p->prepare('SELECT id FROM wmt_associates WHERE name=?');
        $q->execute(array($n));
        $id = (int)$q->fetchColumn();
    }
    return $id;
}

/* ------------------------------------------------------------------ */
/* Section 10: JSON / CSV import (shared)                              */
/* ------------------------------------------------------------------ */

function wmt_import_payload($p, $j)
{
    if (!is_array($j)) {
        return 'Bad JSON';
    }
    $count = 0;
    foreach ($j['settings'] ?? array() as $k => $v) {
        if (is_scalar($v)) {
            wmt_set_setting($p, $k, $v);
        }
    }
    foreach ($j['replace_schedule_dates'] ?? array() as $d) {
        $p->prepare('DELETE FROM wmt_shifts WHERE work_date=?')->execute(array($d));
    }
    foreach ($j['associates'] ?? array() as $a) {
        wmt_up_assoc($p, $a);
    }
    foreach ($j['tasks'] ?? array() as $t) {
        if (empty($t['name'])) {
            continue;
        }
        $p->prepare('INSERT INTO wmt_tasks(name,category,priority,weight,frequency,notes) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE category=VALUES(category),priority=VALUES(priority),weight=VALUES(weight),frequency=VALUES(frequency),notes=VALUES(notes),active=1')
          ->execute(array($t['name'], $t['category'] ?? 'Entrance Readiness', $t['priority'] ?? 'Medium', (int)($t['weight'] ?? 2), $t['frequency'] ?? 'Daily', $t['notes'] ?? null));
    }
    foreach ($j['schedule'] ?? array() as $s) {
        $d = $s['date'] ?? $s['work_date'] ?? '';
        $n = $s['name'] ?? $s['associate_name'] ?? '';
        $start = $s['start'] ?? $s['start_time'] ?? '';
        $end = $s['end'] ?? $s['end_time'] ?? '';
        if (!$d || !$n || !$start || !$end) {
            continue;
        }
        $id = wmt_up_assoc($p, array('name' => $n, 'team' => $s['team'] ?? 'Services', 'role_type' => $s['role_type'] ?? 'AP Service TA', 'preferred_door' => $s['preferred_door'] ?? 'Either', 'notes' => $s['notes'] ?? null));
        $p->prepare('INSERT INTO wmt_shifts(work_date,associate_id,associate_name,team,role_type,start_time,end_time,notes,source_file) VALUES(?,?,?,?,?,?,?,?,?)')
          ->execute(array($d, $id, $n, $s['team'] ?? 'Services', $s['role_type'] ?? 'AP Service TA', $start, $end, $s['notes'] ?? null, $s['source_file'] ?? null));
        $count++;
    }
    return 'Imported ' . $count . ' schedule rows';
}

function wmt_import_current($p)
{
    $file = __DIR__ . '/current-week-import.json';
    if (!is_file($file)) {
        return 'Current-week import file not found.';
    }
    return wmt_import_payload($p, json_decode(file_get_contents($file), true));
}

function wmt_auto_seed($p)
{
    if (wmt_setting($p, 'current_week_auto_seeded') === '1') {
        return;
    }
    $cnt = (int)$p->query('SELECT COUNT(*) FROM wmt_shifts')->fetchColumn();
    if ($cnt === 0 && is_file(__DIR__ . '/current-week-import.json')) {
        wmt_import_current($p);
        wmt_set_setting($p, 'current_week_auto_seeded', '1');
    }
}

/* ------------------------------------------------------------------ */
/* Section 11: sanity checks                                           */
/* ------------------------------------------------------------------ */

/** Returns a list of {level,msg}. level: critical | warn | info. */
function wmt_sanity($pl)
{
    $flags = array();
    foreach ($pl['uncovered'] as $w) {
        $flags[] = array('level' => 'critical', 'msg' => 'Both doors uncovered ' . wmt_nice($w['start']) . '-' . wmt_nice($w['end']) . '. Unavoidable with the current schedule — escalate for staffing.');
    }
    foreach (array_unique($pl['break_plan']['warnings']) as $w) {
        $flags[] = array('level' => 'warn', 'msg' => $w);
    }
    if (!$flags && !empty($pl['rows'])) {
        $flags[] = array('level' => 'info', 'msg' => 'Both doors covered for the full planning window with staggered breaks.');
    }
    return $flags;
}

/* ------------------------------------------------------------------ */
/* Section 12: shared chrome (theme + nav)                             */
/* ------------------------------------------------------------------ */

function wmt_theme_css()
{
    return ':root{--blue:#0071DC;--blue2:#0053E2;--deep:#004F9A;--yellow:#FFC220;--bg:#F8F8F8;--panel:#FFFFFF;--border:#E3E4E5;--text:#000000;--muted:#515357;--soft:#E6F1FC;--hover:#F1F1F2;--success:#2A8703;--error:#DE1C24;--warn:#FFF8E1}body{margin:0;background:var(--bg);color:var(--text);font-family:system-ui,-apple-system,Segoe UI,sans-serif}.top{background:var(--blue);color:#fff;border-bottom:4px solid var(--yellow)}.wrap{max-width:1220px;margin:auto;padding:18px}.nav a,.top a{color:#fff;margin-right:14px;font-weight:800;text-decoration:none}.card{background:var(--panel);border:1px solid var(--border);border-radius:16px;padding:16px;margin:14px 0;box-shadow:0 1px 2px rgba(0,0,0,.05)}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.kpi{background:var(--soft);border:1px solid #B9D7F5;border-radius:12px;padding:12px}.kpi b{font-size:28px;display:block;color:var(--deep)}.bad{color:var(--error);font-weight:900}.ok{color:var(--success);font-weight:900}.muted{color:var(--muted)}.warn{background:var(--warn);border-left:5px solid var(--yellow);padding:10px;margin:8px 0}.crit{background:#FDECEA;border-left:5px solid var(--error);padding:10px;margin:8px 0;font-weight:700}table{width:100%;border-collapse:collapse;background:white}td,th{border:1px solid var(--border);padding:7px;text-align:left;vertical-align:top}th{background:var(--blue);color:#fff;font-size:12px;text-transform:uppercase}tr:nth-child(even) td{background:#FAFBFC}textarea{width:100%;min-height:120px;box-sizing:border-box}input,select,button,.btn{padding:9px;border-radius:9px;border:1px solid #BABBBD}button,.btn{background:var(--blue);color:#fff;font-weight:800;text-decoration:none;display:inline-block;border-color:var(--blue);cursor:pointer}button:hover,.btn:hover{background:var(--blue2);border-color:var(--blue2)}input:focus,select:focus,textarea:focus{outline:3px solid #B9D7F5;border-color:var(--blue)}.print-title{font-size:28px}.page{break-before:page}.small{font-size:12px}.time{white-space:nowrap;font-weight:800}.pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800;background:var(--soft);color:var(--deep)}.pill-rel{background:#FDECEA;color:var(--error)}@media(max-width:800px){.grid{grid-template-columns:1fr 1fr}}@media print{.top,.no-print{display:none}.wrap{max-width:none;padding:0}.card{border:0;margin:0 0 10px;padding:0;break-inside:avoid;box-shadow:none}body{background:white;font-size:12px}td,th{padding:5px}th,.kpi,.warn,.crit{-webkit-print-color-adjust:exact;print-color-adjust:exact}.page{break-before:page}.print-title{font-size:22px}}';
}

function wmt_nav_links()
{
    return array(
        'index.php' => 'Dashboard',
        'assignments.php' => 'Assignments',
        'tasks.php' => 'Door Tasks',
        'index.php?v=week' => 'Week',
        'import_html.php' => 'Import',
        'index.php?v=turnin' => 'Turn-In',
        'index.php?export=json' => 'Export',
        'index.php?logout=1' => 'Logout',
    );
}

function wmt_head($title, $active = '')
{
    echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . wmt_e($title) . '</title><style>' . wmt_theme_css() . '</style><link rel="stylesheet" href="walmart-theme.css"><div class="top no-print"><div class="wrap"><b>AP Services Tool</b><div class="nav">';
    foreach (wmt_nav_links() as $href => $label) {
        $u = ($active !== '' && $active === $label) ? ' style="text-decoration:underline"' : '';
        echo '<a href="' . wmt_e($href) . '"' . $u . '>' . wmt_e($label) . '</a>';
    }
    echo '</div></div></div><main class="wrap">';
}

function wmt_foot()
{
    echo '</main>';
}

/** Minimal login gate shared by the assignment pages (password set in index). */
function wmt_require_login($p, $self)
{
    $hash = wmt_setting($p, 'admin_hash');
    if (!$hash) {
        header('Location:index.php');
        exit;
    }
    if (isset($_GET['logout'])) {
        unset($_SESSION['wmt']);
        header('Location:index.php');
        exit;
    }
    if (($_POST['form'] ?? '') === 'login' && password_verify($_POST['password'] ?? '', $hash)) {
        $_SESSION['wmt'] = 1;
        header('Location:' . $self);
        exit;
    }
    if (empty($_SESSION['wmt'])) {
        echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login</title><style>' . wmt_theme_css() . '</style><link rel="stylesheet" href="walmart-theme.css"><div class="card" style="max-width:420px;margin:12vh auto"><h1>AP Services Login</h1><form method="post"><input type="hidden" name="form" value="login"><input type="password" name="password" placeholder="Password" style="width:100%"><button style="width:100%;margin-top:10px">Continue</button></form></div>';
        exit;
    }
}

} // end WMT_ENGINE_VERSION guard
