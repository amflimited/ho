<?php
declare(strict_types=1);
/**
 * MISSION CONTROL — the room Adam runs the program from.
 *
 * One feed of "moves", sorted by expected dollars. Every card is a flight
 * station (CAPCOM, FIDO, GUIDO, BOOSTER, RECOVERY, SURGEON) calling FLIGHT
 * with a GO: the message already written, one primary tap. No tabs, no
 * filters, no management — only execution. app.php remains the back office.
 * Mission: first revenue. The Eagle lands on the first conversion.
 *
 * Move types, highest priority first:
 *   close     — a lead marked Interested: closing message, ready to send
 *   hot       — pitched lead who visited their preview in the last 48h
 *   followup  — touch 2-4 due, prewritten, one tap + auto-record
 *   pitch     — fresh preview/enhancement ready lead, best fit first
 *   triage    — rapid-fire Real/Reject, embedded as one card
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

require_once __DIR__ . '/admin-auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ho_admin_require_login_json();   // 401 JSON for the fetch-first handlers
} else {
    ho_admin_require_login();        // HTML redirect for the page itself
}

$pdo = null;
try { $pdo = ho_db(); } catch (Throwable $e) { $dbError = $e->getMessage(); }

// ─── Fetch-first POST handlers — return JSON, never redirect ─────────────────
if ($pdo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = trim((string)($_POST['action'] ?? ''));
    $bizId  = (int)($_POST['business_id'] ?? 0);
    try {
        switch ($action) {
            case 'mark_sent':
                ho_mark_sent($pdo, $bizId, trim((string)($_POST['sent_via'] ?? 'email')), trim((string)($_POST['sent_to'] ?? '')));
                echo json_encode(['ok' => true]); exit;

            case 'record_followup':
                ho_record_followup_sent(
                    $pdo,
                    (int)($_POST['log_id'] ?? 0),
                    $bizId,
                    trim((string)($_POST['sent_via'] ?? 'email')),
                    trim((string)($_POST['sent_to'] ?? '')),
                    (int)($_POST['touch'] ?? 2)
                );
                echo json_encode(['ok' => true]); exit;

            case 'log_strike': // hot strike sent manually — log for the 7-day suppressor
                try {
                    $pdo->prepare("INSERT INTO email_log (business_id, kind, touch, sent_to, subject, ok)
                                   VALUES (?, 'hotstrike', 1, ?, 'manual strike', 1)")
                        ->execute([$bizId, trim((string)($_POST['sent_to'] ?? ''))]);
                } catch (PDOException) { /* email_log not migrated — client suppresses via localStorage */ }
                echo json_encode(['ok' => true]); exit;

            case 'triage_keep':
                $pdo->prepare("UPDATE businesses SET triaged=1, updated_at=NOW() WHERE id=?")->execute([$bizId]);
                echo json_encode(['ok' => true]); exit;

            case 'triage_reject':
                ho_mark_excluded($pdo, $bizId, 'failed_triage');
                echo json_encode(['ok' => true]); exit;

            case 'mark_forwarded': // customer lead delivered to the owner by hand
                try {
                    $pdo->prepare("UPDATE captured_leads SET forwarded_at = NOW() WHERE id = ?")
                        ->execute([(int)($_POST['capture_id'] ?? 0)]);
                } catch (PDOException) {}
                echo json_encode(['ok' => true]); exit;

            case 'mark_outcome':
                ho_mark_outcome($pdo, (int)($_POST['log_id'] ?? 0), trim((string)($_POST['outcome'] ?? '')));
                echo json_encode(['ok' => true]); exit;
        }
        echo json_encode(['ok' => false, 'error' => 'Unknown action.']); exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit;
    }
}

// ─── Build the move deck ─────────────────────────────────────────────────────
$moves = [];           // each: [priority, type, html-building data]
$pipelineValue = 0.0;  // dollars currently sitting on the floor
$hotCount = 0;
$siteBase = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'hoosieronline.com');

$buildReady = []; $enhReady = []; $repReady = []; $followups = []; $hotLeads = []; $interested = []; $triage = [];
$struckRecently = []; $captures = [];

if ($pdo !== null) {
    try { $buildReady = ho_get_preview_ready($pdo); }     catch (Throwable) {}
    try { $enhReady   = ho_get_enhancement_ready($pdo); } catch (Throwable) {}
    try { $followups  = ho_get_followup_due_full($pdo, 20); } catch (Throwable) {}
    try { $triage     = ho_get_triage_batch($pdo, 40); }  catch (Throwable) {}
    try { $captures   = ho_get_unforwarded_captures($pdo, 10); } catch (Throwable) {}
    try { $repReady    = ho_get_reputation_ready($pdo); } catch (Throwable) {}

    // Hot: pitched leads who visited in the last 48h
    try {
        $hotLeads = $pdo->query("
            SELECT b.id, b.business_name, b.owner_first_name, b.email_address, b.phone_number,
                   b.location_city, c.slug AS category_slug, c.name AS category_name,
                   p.preview_slug,
                   MAX(pv.visited_at) AS last_visit, COUNT(pv.id) AS visits48
            FROM preview_visits pv
            JOIN businesses b ON b.id = pv.business_id
            JOIN previews p   ON p.business_id = b.id
            JOIN categories c ON c.id = b.category_id
            WHERE pv.visited_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
              AND b.pipeline_status = 'pitched'
            GROUP BY b.id
            ORDER BY last_visit DESC
            LIMIT 10
        ")->fetchAll();
    } catch (PDOException) {}

    // Suppress hot cards already struck in the last 7 days
    try {
        $struckRecently = $pdo->query("
            SELECT DISTINCT business_id FROM email_log
            WHERE kind='hotstrike' AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ")->fetchAll(PDO::FETCH_COLUMN);
        $struckRecently = array_map('intval', $struckRecently);
    } catch (PDOException) {}

    // Interested: they raised their hand — the closest thing to money there is
    try {
        $interested = $pdo->query("
            SELECT b.id, b.business_name, b.owner_first_name, b.email_address, b.phone_number,
                   b.location_city, c.name AS category_name, p.preview_slug,
                   MAX(ol.sent_at) AS flagged_at
            FROM outreach_log ol
            JOIN businesses b ON b.id = ol.business_id
            JOIN categories c ON c.id = b.category_id
            LEFT JOIN previews p ON p.business_id = b.id
            WHERE ol.outcome = 'interested'
              AND b.pipeline_status NOT IN ('converted','not_a_fit','excluded')
            GROUP BY b.id
            ORDER BY flagged_at DESC
            LIMIT 10
        ")->fetchAll();
    } catch (PDOException) {}
}

// Pipeline value = what's ready to close + what's in play
$pipelineValue += count($buildReady) * 199;
$pipelineValue += count($repReady) * 99;
foreach ($enhReady as $b) $pipelineValue += (float)($b['bundle_total'] ?? 0) ?: 199;
$inPlay = count($followups) + count($hotLeads) + count($interested);

// Sent today (manual + autopilot) — momentum metric
$sentToday = 0;
if ($pdo !== null) {
    try { $sentToday += (int)$pdo->query("SELECT COUNT(*) FROM outreach_log WHERE sent_at >= CURDATE()")->fetchColumn(); } catch (PDOException) {}
    try { $sentToday += (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE kind != 'digest' AND ok=1 AND sent_at >= CURDATE()")->fetchColumn(); } catch (PDOException) {}
}
$dailyGoal = 10;

// ── Mission telemetry — the clock starts when the first lead entered the
// machine; the mission ends when the Eagle lands (first conversion).
$convertedCount = 0; $missionStart = null;
if ($pdo !== null) {
    try { $convertedCount = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE pipeline_status='converted'")->fetchColumn(); } catch (PDOException) {}
    try { $missionStart = (string)($pdo->query("SELECT MIN(created_at) FROM businesses")->fetchColumn() ?: ''); } catch (PDOException) {}
}
$missionEpoch = $missionStart ? (strtotime($missionStart) ?: time()) : time();
$eagleLanded  = $convertedCount > 0;

// Flight controller callsign per move type — every card is a station calling
// FLIGHT with a GO.
$stations = [
    'capture'  => 'RECOVERY',
    'close'    => 'CAPCOM',
    'hot'      => 'FIDO',
    'followup' => 'GUIDO',
    'pitch'    => 'BOOSTER',
    'triage'   => 'SURGEON',
];

// ── CUSTOMER CAUGHT (priority 2000) — the near-guaranteed close ──────────────
// A real customer tried to reach this business through the page. Deliver the
// lead, free — then the keep-the-site ask rides on loss aversion, not hope.
foreach ($captures as $c) {
    $slug    = (string)($c['preview_slug'] ?? '');
    $pageUrl = $slug !== '' ? $siteBase . '/go/' . $slug : $siteBase;
    $ageHrs  = max(0, (int)floor((time() - strtotime((string)$c['created_at'])) / 3600));
    $body    = ho_capture_delivery_message($c, $pageUrl);
    $moves[] = [
        'prio' => 2000 - min(500, $ageHrs), 'type' => 'capture', 'biz' => $c,
        'tag' => "\u{1F4B0} CUSTOMER CAUGHT", 'value' => '',
        'why' => 'Their page caught a real inquiry from ' . ho_h((string)($c['customer_name'] ?: 'a customer'))
               . ($ageHrs < 1 ? ' within the hour' : ' ' . $ageHrs . 'h ago')
               . (trim((string)$c['job_description']) !== '' ? ' — “' . ho_h(mb_substr(trim((string)$c['job_description']), 0, 90)) . '”' : '')
               . '. Deliver it free. The keep-the-site ask closes itself.',
        'subject' => 'A customer came through your new site',
        'body' => $body,
        'capture_id' => (int)$c['capture_id'],
    ];
}

// ── CLOSE moves (priority 900) ───────────────────────────────────────────────
foreach ($interested as $b) {
    $first    = trim((string)$b['owner_first_name']);
    $greeting = $first !== '' ? "Hi {$first}," : 'Hi,';
    $name     = (string)$b['business_name'];
    $slug     = (string)($b['preview_slug'] ?? '');
    $url      = $slug !== '' ? $siteBase . '/go/' . $slug : '';
    $subject  = "Let\u{2019}s get {$name} live";
    $body     = "{$greeting}\n\nYou said this looked interesting \u{2014} so let\u{2019}s make it real. Reply \u{201C}go\u{201D} and I\u{2019}ll have everything live within 48 hours. Want something changed first? Tell me, it takes minutes."
              . ($url !== '' ? "\n\nThe page is still up:\n\n{$url}" : '')
              . "\n\nFull refund if you don\u{2019}t love it \u{2014} you risk nothing.\n\n\u{2014} Adam Ferree\nHoosier Online\nadam@hoosieronline.com";
    $moves[] = [
        'prio' => 900, 'type' => 'close', 'biz' => $b,
        'tag' => '🤝 THEY\'RE INTERESTED', 'value' => '',
        'why' => 'Marked interested ' . date('M j', strtotime((string)$b['flagged_at'])) . ' — strike while it’s warm.',
        'subject' => $subject, 'body' => $body,
    ];
}

// ── HOT moves (priority 800 + recency) ───────────────────────────────────────
foreach ($hotLeads as $b) {
    if (in_array((int)$b['id'], $struckRecently, true)) continue;
    $hotCount++;
    $url = $siteBase . '/go/' . $b['preview_slug'];
    $msg = ho_hot_strike_message($b, $url);
    $hrsAgo = max(0, (int)floor((time() - strtotime((string)$b['last_visit'])) / 3600));
    $moves[] = [
        'prio' => 800 + max(0, 48 - $hrsAgo), 'type' => 'hot', 'biz' => $b,
        'tag' => '🔥 READING IT RIGHT NOW', 'value' => '',
        'why' => 'Opened their page ' . ($hrsAgo === 0 ? 'within the hour' : $hrsAgo . 'h ago')
               . ((int)$b['visits48'] > 1 ? ' — ' . (int)$b['visits48'] . ' visits in 48h' : '')
               . '. Hottest a lead ever gets.',
        'subject' => $msg['subject'], 'body' => $msg['body'],
    ];
}

// ── FOLLOW-UP moves (priority 500 + overdue) ─────────────────────────────────
$fuHeat = [];
if ($pdo !== null && !empty($followups)) {
    try { $fuHeat = ho_visit_stats_for_businesses($pdo, array_map(fn($r) => (int)$r['business_id'], $followups)); } catch (Throwable) {}
}
foreach ($followups as $fu) {
    $slug = (string)($fu['preview_slug'] ?? '');
    if ($slug === '') continue;
    $touch   = min((int)$fu['touch_number'] + 1, 4);
    $url     = $siteBase . '/go/' . $slug;
    $msg     = ho_followup_message($fu, $url, $touch, $fuHeat);
    $overdue = max(0, (int)floor((time() - strtotime((string)$fu['follow_up_at'])) / 86400));
    $bizRow  = ['id' => (int)$fu['business_id'], 'business_name' => $fu['business_name'],
                'location_city' => $fu['location_city'], 'category_name' => (string)($fu['category_name'] ?? ''),
                'email_address' => (string)($fu['email_address'] ?? ''), 'phone_number' => (string)($fu['phone_number'] ?? ''),
                'preview_slug' => $slug];
    $moves[] = [
        'prio' => 500 + min(90, $overdue * 3), 'type' => 'followup', 'biz' => $bizRow,
        'tag' => '⏰ TOUCH ' . $touch . ' DUE', 'value' => '',
        'why' => 'Most deals land on touch 3-5. ' . ($overdue > 0 ? $overdue . 'd overdue — ' : '') . 'this one’s written and ready.',
        'subject' => $msg['subject'], 'body' => $msg['body'],
        'log_id' => (int)$fu['log_id'], 'touch' => $touch,
    ];
}

// ── PITCH moves (priority fit-based) ─────────────────────────────────────────
$pitchMoves = [];
foreach ($buildReady as $b) {
    $url = $siteBase . '/go/' . $b['business_slug'];
    $msg = ho_pitch_message($b, $url);
    $pitchMoves[] = [
        'prio' => 200 + (int)$b['fit_score'] * 10 + ((string)$b['email_address'] !== '' ? 50 : 0),
        'type' => 'pitch', 'biz' => $b,
        'tag' => '✨ FRESH — NEW SITE', 'value' => '$199',
        'why' => ho_money_pitch_why($b),
        'subject' => $msg['subject'], 'body' => $msg['body'],
    ];
}
foreach ($enhReady as $b) {
    $url = $siteBase . '/go/' . $b['business_slug'];
    $msg = ho_pitch_message_enhancement($b, $url);
    $bundle = (float)($b['bundle_total'] ?? 0);
    $pitchMoves[] = [
        'prio' => 200 + min(100, (int)($bundle / 10)) + ((string)$b['email_address'] !== '' ? 50 : 0),
        'type' => 'pitch', 'biz' => $b,
        'tag' => '✨ FRESH — UPGRADE', 'value' => $bundle > 0 ? '$' . number_format($bundle) : 'quote',
        'why' => ho_money_pitch_why($b),
        'subject' => $msg['subject'], 'body' => $msg['body'],
    ];
}
foreach ($repReady as $b) {
    $url = $siteBase . '/rep.php?slug=' . $b['business_slug'];
    $msg = ho_pitch_message_reputation($b, $url);
    $worstBit = ((int)$b['worst_rating'] > 0 && (int)$b['worst_rating'] <= 3)
        ? ' · worst: ' . (int)$b['worst_rating'] . '★ unanswered' . ((string)$b['worst_author'] !== '' ? ' from ' . ho_h((string)$b['worst_author']) : '')
        : '';
    $pitchMoves[] = [
        'prio' => 200 + min(60, (int)$b['draft_count'] * 5)
                + ((string)$b['email_address'] !== '' ? 50 : 0)
                + ((int)$b['worst_rating'] > 0 && (int)$b['worst_rating'] <= 2 ? 40 : 0),
        'type' => 'pitch', 'biz' => $b,
        'tag' => '✍️ REVIEWS IGNORED', 'value' => '$99 + $29/mo',
        'why' => (int)$b['draft_count'] . ' replies already written' . $worstBit . ' · the work is done — just send.',
        'subject' => $msg['subject'], 'body' => $msg['body'],
    ];
}
usort($pitchMoves, fn($a, $b) => $b['prio'] <=> $a['prio']);
$pitchOverflow = max(0, count($pitchMoves) - 12);
$moves = array_merge($moves, array_slice($pitchMoves, 0, 12));

usort($moves, fn($a, $b) => $b['prio'] <=> $a['prio']);

/** One-line dollar/credibility hook for a pitch card. */
function ho_money_pitch_why(array $b): string {
    $reviews = (int)($b['google_review_count'] ?? 0);
    $rating  = (float)($b['google_rating'] ?? 0);
    $stakes  = ho_stakes_estimate((string)($b['category_slug'] ?? ''));
    $bits = [];
    if ($reviews >= 10) $bits[] = $reviews . ' reviews at ' . number_format($rating, 1) . '★';
    if ($stakes !== null) $bits[] = '≈ $' . number_format($stakes['annual']) . '/yr on their table';
    if (empty($bits)) $bits[] = 'Researched, page built, message written — just send.';
    $bits[] = !empty($b['verified_at']) ? '✓ fact-checked' : '⚠ unverified';
    return implode(' · ', $bits);
}

/** Best channel for a card: [kind, label, href-or-empty]. */
function ho_money_channel(array $b, string $subject, string $body): array {
    $email = trim((string)($b['email_address'] ?? ''));
    $phone = trim((string)($b['phone_number'] ?? ''));
    $site  = trim((string)($b['website_url'] ?? ''));
    $fb    = trim((string)($b['facebook_url'] ?? ''));
    if ($email !== '') {
        return ['email', '✉ Send it', 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode($body), $email];
    }
    if ($site !== '' && !ho_is_lead_platform_url($site)) {
        return ['site', '⧉ Copy + open their contact form', $site, $site];
    }
    if ($fb !== '') {
        return ['fb', '⧉ Copy + open Messenger', $fb, $fb];
    }
    if ($phone !== '') {
        return ['sms', '📱 Copy text + open Messages', 'sms:' . $phone, $phone];
    }
    return ['none', 'No contact channel', '', ''];
}

$movesLeft = count($moves) + (count($triage) > 0 ? 1 : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <title>Mission Control — Hoosier Online</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;900&family=IBM+Plex+Mono:wght@400;600&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/money.css?v=<?= filemtime(__DIR__ . '/assets/css/money.css') ?>">
</head>
<body class="mf">

<header class="mf-top">
  <div class="mf-brand"><span class="mf-live-dot"></span>MISSION CONTROL</div>
  <div class="mf-clock" id="mfClock">T+ --:--:--:--</div>
  <a class="mf-cockpit-link" href="/app.php">cockpit →</a>
</header>

<div class="mf-missionline">
  <?php if ($eagleLanded): ?>
    <span class="mf-mission-eagle">🦅 The Eagle has landed &middot; <?= $convertedCount ?> converted &middot; next: orbit</span>
  <?php else: ?>
    <span>Mission: first revenue</span>
    <span class="mf-go">Status: GO</span>
  <?php endif; ?>
</div>

<?php if (!empty($dbError)): ?>
  <div class="mf-shell"><div class="mf-card"><p><?= ho_h($dbError) ?></p></div></div>
<?php else: ?>

<section class="mf-score">
  <div class="mf-score-main">
    <span class="mf-score-num">$<?= number_format($pipelineValue) ?></span>
    <span class="mf-score-lbl">payload on the pad</span>
  </div>
  <div class="mf-score-row">
    <span class="mf-stat"><em id="mfSent"><?= $sentToday ?></em> transmissions</span>
    <span class="mf-stat"><em><?= $hotCount ?></em> 🔥 hot</span>
    <span class="mf-stat"><em><?= $inPlay ?></em> in play</span>
  </div>
  <div class="mf-goal">
    <div class="mf-goal-bar"><div class="mf-goal-fill" id="mfGoalFill" style="width:<?= min(100, (int)($sentToday / $dailyGoal * 100)) ?>%"></div></div>
    <span class="mf-goal-lbl" id="mfGoalLbl"><?= $sentToday >= $dailyGoal ? '🚀 Full burn complete — all engines fired' : ($dailyGoal - $sentToday) . ' burns to a full launch' ?></span>
  </div>
</section>

<main class="mf-shell">

  <?php if (empty($moves) && empty($triage)): ?>
    <div class="mf-card mf-empty">
      <div class="mf-empty-icon">🌙</div>
      <h2>All systems nominal.</h2>
      <p>No station needs FLIGHT. The machine is hunting on its own — fresh leads land after the next sourcing run, and follow-ups fire when they&rsquo;re due. Go live your life; Houston has the watch.</p>
      <a class="mf-btn mf-btn-ghost" href="/app.php?tab=send">open the cockpit anyway →</a>
    </div>
  <?php endif; ?>

  <?php $idx = 0; foreach ($moves as $m):
    $b = $m['biz'];
    [$chKind, $chLabel, $chHref, $chTo] = ho_money_channel($b, $m['subject'], $m['body']);
    $idx++;
  ?>
  <article class="mf-card mf-type-<?= $m['type'] ?><?= $idx === 1 ? ' mf-first' : '' ?>" data-biz="<?= (int)$b['id'] ?>">
    <span class="mf-next-flag">▶ Next action</span>
    <div class="mf-card-tag-row">
      <span class="mf-station"><?= $stations[$m['type']] ?? 'FLIGHT' ?></span>
      <span class="mf-tag mf-tag-<?= $m['type'] ?>"><?= $m['tag'] ?></span>
      <?php if ($m['value'] !== ''): ?><span class="mf-value"><?= ho_h($m['value']) ?></span><?php endif; ?>
    </div>
    <h2 class="mf-card-name"><?= ho_h((string)$b['business_name']) ?></h2>
    <p class="mf-card-sub"><?= ho_h((string)($b['category_name'] ?? '')) ?> · <?= ho_h((string)($b['location_city'] ?? '')) ?></p>
    <p class="mf-card-why"><?= $m['why'] ?></p>

    <details class="mf-msg-peek">
      <summary>see the message ▾</summary>
      <div class="mf-msg-subject">Subject: <?= ho_h($m['subject']) ?></div>
      <pre class="mf-msg-body"><?= ho_h($m['body']) ?></pre>
    </details>
    <textarea class="mf-msg-src" hidden><?= ho_h($m['body']) ?></textarea>

    <?php if ($chKind === 'none'): ?>
      <p class="mf-no-channel">No contact channel — handle in the cockpit.</p>
    <?php else: ?>
    <div class="mf-actions">
      <?php
        // What recording the move means, per type:
        $recAttrs = match ($m['type']) {
            'followup' => "data-action=\"record_followup\" data-extra='" . json_encode(['log_id' => $m['log_id'], 'touch' => $m['touch']]) . "'",
            'hot'      => 'data-action="log_strike"',
            'close'    => 'data-action="none"',
            'capture'  => "data-action=\"mark_forwarded\" data-extra='" . json_encode(['capture_id' => $m['capture_id'] ?? 0]) . "'",
            default    => 'data-action="mark_sent"',
        };
        $via = ['email' => 'email', 'site' => 'website_form', 'fb' => 'facebook_dm', 'sms' => 'sms'][$chKind];
      ?>
      <?php if ($chKind === 'email'): ?>
        <a class="mf-btn mf-btn-go" href="<?= ho_h($chHref) ?>" <?= $recAttrs ?> data-via="<?= $via ?>" data-to="<?= ho_h($chTo) ?>" onclick="moveDone(this)"><?= $chLabel ?></a>
      <?php elseif ($chKind === 'sms'): ?>
        <a class="mf-btn mf-btn-go" href="<?= ho_h($chHref) ?>" <?= $recAttrs ?> data-via="<?= $via ?>" data-to="<?= ho_h($chTo) ?>" onclick="copyMsg(this);moveDone(this)"><?= $chLabel ?></a>
      <?php else: ?>
        <button type="button" class="mf-btn mf-btn-go" <?= $recAttrs ?> data-via="<?= $via ?>" data-to="<?= ho_h($chTo) ?>" data-open="<?= ho_h($chHref) ?>" onclick="copyOpen(this);moveDone(this)"><?= $chLabel ?></button>
      <?php endif; ?>
      <button type="button" class="mf-btn mf-btn-skip" onclick="skipMove(this)">later</button>
    </div>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>

  <?php if (!empty($triage)): ?>
  <article class="mf-card mf-type-triage" id="mfTriageCard">
    <div class="mf-card-tag-row">
      <span class="mf-station"><?= $stations['triage'] ?></span>
      <span class="mf-tag mf-tag-triage">👁 QUALITY GATE</span>
      <span class="mf-value" id="mfTriageCount"><?= count($triage) ?> waiting</span>
    </div>
    <h2 class="mf-card-name" id="mfTriageName"></h2>
    <p class="mf-card-sub" id="mfTriageSub"></p>
    <p class="mf-card-why" id="mfTriageWhy"></p>
    <a class="mf-triage-verify" id="mfTriageVerify" href="#" target="_blank" rel="noopener">verify on Google ↗</a>
    <div class="mf-actions">
      <button type="button" class="mf-btn mf-btn-go"   onclick="triageGo(true)">Real ✓</button>
      <button type="button" class="mf-btn mf-btn-skip" onclick="triageGo(false)">Reject ✗</button>
    </div>
  </article>
  <?php endif; ?>

  <?php if ($pitchOverflow > 0): ?>
    <p class="mf-overflow">+<?= $pitchOverflow ?> more fresh leads queued behind these — clear the deck and they rise.</p>
  <?php endif; ?>

  <p class="mf-footer">Flight, we are GO. Top card first — every transmission is free fuel.</p>
</main>

<script>
var TRIAGE = <?= json_encode(array_map(fn($t) => [
    'id'   => (int)$t['id'],
    'name' => (string)$t['business_name'],
    'sub'  => trim((string)$t['category_name'] . ' · ' . (string)$t['location_city']),
    'why'  => trim(((string)($t['found_via'] ?? '') !== '' ? 'Found via ' . $t['found_via'] : 'No source note')
            . ((string)($t['confidence'] ?? '') !== '' ? ' · confidence: ' . $t['confidence'] : '')),
    'g'    => 'https://www.google.com/search?q=' . rawurlencode('"' . $t['business_name'] . '" ' . $t['location_city'] . ' Indiana'),
], $triage), JSON_UNESCAPED_SLASHES) ?>;
var tIdx = 0, movesDone = 0;
var SENT = <?= (int)$sentToday ?>, GOAL = <?= (int)$dailyGoal ?>;

// Mission clock — T+ since the first lead entered the machine, live.
var T0 = <?= (int)$missionEpoch ?> * 1000;
function pad2(n){ return (n < 10 ? '0' : '') + n; }
function mfTick() {
  var el = document.getElementById('mfClock');
  if (!el) return;
  var s = Math.max(0, Math.floor((Date.now() - T0) / 1000));
  var d = Math.floor(s / 86400);
  el.textContent = 'T+ ' + d + ':' + pad2(Math.floor(s % 86400 / 3600)) + ':' + pad2(Math.floor(s % 3600 / 60)) + ':' + pad2(s % 60);
}
setInterval(mfTick, 1000); mfTick();

function post(params) {
  try {
    fetch('/money.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams(params).toString(),
      keepalive: true
    }).catch(function(){});
  } catch (e) {}
}
function bumpScore() {
  SENT++; movesDone++;
  var el = document.getElementById('mfSent');
  if (el) el.textContent = SENT;
  var fill = document.getElementById('mfGoalFill');
  if (fill) fill.style.width = Math.min(100, Math.round(SENT / GOAL * 100)) + '%';
  var lbl = document.getElementById('mfGoalLbl');
  if (lbl) lbl.textContent = SENT >= GOAL ? '🚀 Full burn complete — all engines fired' : (GOAL - SENT) + ' burns to a full launch';
}
function slideOut(card) {
  if (!card) return;
  card.classList.add('mf-out');
  setTimeout(function() {
    card.remove();
    var next = document.querySelector('.mf-card:not(.mf-empty)');
    if (next) next.classList.add('mf-first');
  }, 420);
}
function moveDone(btn) {
  var card = btn.closest('.mf-card');
  var action = btn.getAttribute('data-action');
  if (action && action !== 'none') {
    var params = {action: action, business_id: card.getAttribute('data-biz'),
                  sent_via: btn.getAttribute('data-via') || 'email',
                  sent_to: btn.getAttribute('data-to') || ''};
    var extra = btn.getAttribute('data-extra');
    if (extra) { var ex = JSON.parse(extra); for (var k in ex) params[k] = ex[k]; }
    post(params);
  }
  if (card) card.classList.add('mf-sent'); // ✓ TRANSMITTED stamp
  bumpScore();
  setTimeout(function(){ slideOut(card); }, 900);
}
function skipMove(btn) { slideOut(btn.closest('.mf-card')); }
function copyMsg(btn) {
  var src = btn.closest('.mf-card').querySelector('.mf-msg-src');
  if (src && navigator.clipboard) navigator.clipboard.writeText(src.value).catch(function(){});
}
function copyOpen(btn) {
  copyMsg(btn);
  var url = btn.getAttribute('data-open');
  if (url) setTimeout(function(){ window.open(url, '_blank', 'noopener,noreferrer'); }, 350);
}
function triageRender() {
  var card = document.getElementById('mfTriageCard');
  if (!card) return;
  if (tIdx >= TRIAGE.length) {
    card.querySelector('.mf-actions').innerHTML = '<p class="mf-card-why">✨ Quality gate clear — every lead in the machine is real.</p>';
    document.getElementById('mfTriageName').textContent = 'All checked.';
    document.getElementById('mfTriageSub').textContent = '';
    document.getElementById('mfTriageWhy').textContent = '';
    document.getElementById('mfTriageVerify').style.display = 'none';
    setTimeout(function(){ slideOut(card); }, 1400);
    return;
  }
  var t = TRIAGE[tIdx];
  document.getElementById('mfTriageName').textContent = t.name;
  document.getElementById('mfTriageSub').textContent = t.sub;
  document.getElementById('mfTriageWhy').textContent = t.why;
  document.getElementById('mfTriageVerify').href = t.g;
  document.getElementById('mfTriageCount').textContent = (TRIAGE.length - tIdx) + ' waiting';
}
function triageGo(keep) {
  var t = TRIAGE[tIdx];
  if (!t) return;
  post({action: keep ? 'triage_keep' : 'triage_reject', business_id: t.id});
  tIdx++;
  triageRender();
}
triageRender();
</script>
<?php endif; ?>
</body>
</html>
