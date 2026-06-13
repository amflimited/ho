<?php
declare(strict_types=1);
/**
 * The cockpit: the operator's two jobs (triage taps, talking to humans) plus
 * paste-import, worker run buttons, settings, and the one-time v1 ETL.
 * Phone-first. Key-protected (admin_key setting, hashed).
 */
require dirname(__DIR__, 2) . '/bin/bootstrap.php';

use HoV2\Domain\Pipeline;
use HoV2\Import\Importer;
use HoV2\Import\V1Etl;
use HoV2\Outreach\Suppression;
use HoV2\Workers\Runner;

$pdo = ho_pdo();
session_start();

$keyHash = ho_setting($pdo, 'admin_key');
$msg = '';
$detail = '';

if (($_POST['action'] ?? '') === 'set_key' && $keyHash === '') {
    $k = (string)($_POST['key'] ?? '');
    if (strlen($k) >= 8) {
        ho_set_setting($pdo, 'admin_key', password_hash($k, PASSWORD_DEFAULT));
        $_SESSION['cockpit'] = 1;
        header('Location: index.php');
        exit;
    }
    $msg = 'Key must be at least 8 characters.';
}
if (($_POST['action'] ?? '') === 'login') {
    if ($keyHash !== '' && password_verify((string)($_POST['key'] ?? ''), $keyHash)) {
        $_SESSION['cockpit'] = 1;
        header('Location: index.php');
        exit;
    }
    $msg = 'Wrong key.';
}
if (($_GET['logout'] ?? '') === '1') {
    $_SESSION = [];
    session_destroy();
    header('Location: index.php');
    exit;
}

$authed = (int)($_SESSION['cockpit'] ?? 0) === 1;
$h = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES);

if (!$authed) { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Cockpit</title>
<style>body{font-family:-apple-system,sans-serif;background:#11151c;color:#e8e6e1;display:grid;place-items:center;height:100vh;margin:0}
form{background:#1a212c;padding:2rem;border-radius:14px;width:min(320px,90vw)}
input{width:100%;padding:.8rem;border-radius:8px;border:1px solid #333;background:#0d1117;color:#fff;margin:.6rem 0;font:inherit;box-sizing:border-box}
button{width:100%;padding:.8rem;border:0;border-radius:8px;background:#1f6f43;color:#fff;font-weight:700;font-size:1rem}
.err{color:#ff7b72;font-size:.9rem}</style></head><body>
<form method="post">
  <h2><?= $keyHash === '' ? 'Set your cockpit key' : 'Cockpit' ?></h2>
  <?php if ($msg !== ''): ?><p class="err"><?= $h($msg) ?></p><?php endif; ?>
  <input type="hidden" name="action" value="<?= $keyHash === '' ? 'set_key' : 'login' ?>">
  <input type="password" name="key" placeholder="<?= $keyHash === '' ? 'Choose a key (8+ chars)' : 'Key' ?>" autofocus required>
  <button type="submit"><?= $keyHash === '' ? 'Set key & enter' : 'Enter' ?></button>
</form></body></html>
<?php exit; }

$SETTINGS = [
    'ap_master', 'ap_postal', 'ap_from_email', 'ap_digest_email', 'ap_digest',
    'ap_daily_cap', 'ap_pitch_per_run', 'ap_research_per_run', 'ap_verify_per_run',
    'ap_voice_per_run', 'ap_source_per_run', 'ap_personalize_per_run',
    'ap_source_area_idx', 'ap_last_run', 'ap_last_run_summary',
    'ap_site_base', 'llm_provider', 'llm_api_key', 'llm_model',
    'tts_api_key', 'rcpt_price_cents',
    'stripe_secret_key', 'stripe_webhook_secret',
];

try {
    switch ($_POST['action'] ?? '') {
        case 'triage':
            $pdo->prepare('UPDATE businesses SET triaged = 1 WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
            $msg = 'Triaged.';
            break;
        case 'exclude':
            $id = (int)($_POST['id'] ?? 0);
            Pipeline::advance($pdo, $id, 'excluded');
            $pdo->prepare('UPDATE businesses SET triaged = 1 WHERE id = ?')->execute([$id]);
            $msg = 'Excluded.';
            break;
        case 'suppress':
            $id = (int)($_POST['id'] ?? 0);
            $s = $pdo->prepare('SELECT email_address FROM businesses WHERE id = ?');
            $s->execute([$id]);
            $email = trim((string)$s->fetchColumn());
            Suppression::add($pdo, $email !== '' ? $email : null, 'not_a_fit', $id, 'cockpit one-tap');
            Pipeline::advance($pdo, $id, 'not_a_fit');
            $msg = 'Suppressed — this business can never be emailed again.';
            break;
        case 'import':
            $r = (new Importer($pdo))->import((string)($_POST['payload'] ?? ''));
            $msg = "Imported {$r['imported']} new, updated {$r['updated']}, rejected " . count($r['rejected']) . '.';
            $detail = $r['rejected'] !== [] ? (string)json_encode($r['rejected'], JSON_PRETTY_PRINT) : '';
            break;
        case 'save_settings':
            foreach ($SETTINGS as $k) {
                if ($k === 'ap_master' || $k === 'ap_digest') {
                    ho_set_setting($pdo, $k, isset($_POST[$k]) ? '1' : '0');
                } elseif (array_key_exists($k, $_POST)) {
                    ho_set_setting($pdo, $k, trim((string)$_POST[$k]));
                }
            }
            $msg = 'Settings saved.';
            break;
        case 'run':
            $job = (string)($_POST['job'] ?? '');
            $out = Runner::run($pdo, $job);
            $msg = "Ran {$job}.";
            $detail = (string)json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            break;
        case 'etl':
            $v1file = dirname(__DIR__, 2) . '/config/db-v1.php';
            if (!is_file($v1file)) {
                $msg = 'Create config/db-v1.php first — copy db.php and change dbname to the old v1 database.';
                break;
            }
            $out = V1Etl::run($pdo, require $v1file);
            $msg = 'v1 ETL complete.';
            $detail = (string)json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            break;
    }
} catch (Throwable $e) {
    $msg = 'Error: ' . $e->getMessage();
}

/* ---- view data ---- */
$funnel = $pdo->query('SELECT pipeline_status, COUNT(*) c FROM businesses GROUP BY pipeline_status')->fetchAll(PDO::FETCH_KEY_PAIR);
$triage = $pdo->query(
    'SELECT id, business_name, location_city, fit_score, pipeline_status
     FROM businesses WHERE triaged = 0 ORDER BY fit_score DESC, id DESC LIMIT 30'
)->fetchAll(PDO::FETCH_ASSOC);
$unverified = (int)$pdo->query(
    "SELECT COUNT(*) FROM businesses b JOIN business_profile p ON p.business_id = b.id
     WHERE p.verified_at IS NULL AND b.triaged = 1
       AND b.pipeline_status IN ('researched','preview_ready','enhancement_ready')"
)->fetchColumn();
$ready = $pdo->query(
    "SELECT b.id, b.business_name, b.location_city, b.fit_score, b.business_slug, b.email_address, b.pipeline_status
     FROM businesses b JOIN business_profile p ON p.business_id = b.id
     WHERE p.verified_at IS NOT NULL AND b.triaged = 1
       AND b.pipeline_status IN ('preview_ready','enhancement_ready')
     ORDER BY b.fit_score DESC LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);
$pitched = $pdo->query(
    "SELECT b.id, b.business_name, b.business_slug, MAX(o.touch_number) touches, MAX(o.sent_at) last_sent
     FROM businesses b JOIN outreach_log o ON o.business_id = b.id
     WHERE b.pipeline_status = 'pitched'
     GROUP BY b.id, b.business_name, b.business_slug ORDER BY last_sent DESC LIMIT 20"
)->fetchAll(PDO::FETCH_ASSOC);
$sentToday = (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE ok = 1 AND kind = 'pitch' AND sent_at >= CURDATE()")->fetchColumn();
$visitsToday = (int)$pdo->query('SELECT COUNT(*) FROM preview_visits WHERE visited_at >= CURDATE()')->fetchColumn();
$leads = $pdo->query(
    'SELECT cl.name, cl.contact, cl.message, cl.created_at, b.business_name
     FROM captured_leads cl JOIN businesses b ON b.id = cl.business_id
     ORDER BY cl.id DESC LIMIT 10'
)->fetchAll(PDO::FETCH_ASSOC);
$base = rtrim(ho_setting($pdo, 'ap_site_base') ?: 'https://v2.hoosieronline.com', '/');
$set = static fn(string $k): string => ho_setting($pdo, $k);
$lastRun = $set('ap_last_run');
$lastSummary = $set('ap_last_run_summary');
$apMaster = $set('ap_master');
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>HO Cockpit</title>
<style>
:root{--bg:#11151c;--card:#1a212c;--ink:#e8e6e1;--mut:#8b93a1;--grn:#2ea36b;--org:#ef6c30;--red:#d9534f}
*{box-sizing:border-box}
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--ink);margin:0;padding:1rem;line-height:1.5;max-width:680px;margin:auto}
h1{font-size:1.25rem;margin:.5rem 0 1rem}
h2{font-size:1rem;margin:0 0 .6rem;color:var(--mut);text-transform:uppercase;letter-spacing:.05em}
.card{background:var(--card);border-radius:12px;padding:1rem;margin-bottom:1rem}
.msg{background:#1f3a2a;border:1px solid var(--grn);border-radius:8px;padding:.7rem;margin-bottom:1rem;font-size:.92rem}
pre{background:#0d1117;border-radius:8px;padding:.7rem;overflow-x:auto;font-size:.78rem;max-height:260px}
.row{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.45rem 0;border-bottom:1px solid #242c3a;flex-wrap:wrap}
.row:last-child{border-bottom:0}
.row .who{flex:1;min-width:0}.row .who small{color:var(--mut);display:block}
button{border:0;border-radius:8px;padding:.5rem .8rem;font-weight:600;font-size:.85rem;color:#fff;background:var(--grn)}
button.warn{background:var(--org)}button.bad{background:var(--red)}button.ghost{background:#2a3342}
form.inline{display:inline}
.stats{display:flex;flex-wrap:wrap;gap:.5rem}
.stat{background:#0d1117;border-radius:8px;padding:.5rem .8rem;font-size:.85rem}
.stat b{display:block;font-size:1.15rem}
textarea,input[type=text],input[type=password],select{width:100%;background:#0d1117;color:var(--ink);border:1px solid #333;border-radius:8px;padding:.6rem;font:inherit;margin:.25rem 0 .7rem}
label{font-size:.85rem;color:var(--mut)}
.runs{display:flex;flex-wrap:wrap;gap:.5rem}
a{color:#6cb6ff}
.tag{font-size:.7rem;background:#2a3342;border-radius:6px;padding:.1rem .4rem;color:var(--mut)}
.toggle{display:flex;align-items:center;gap:.5rem;margin:.4rem 0}
</style></head><body>

<h1>HO Cockpit <a style="float:right;font-size:.8rem" href="?logout=1">log out</a></h1>

<?php if ($msg !== ''): ?><div class="msg"><?= $h($msg) ?></div><?php endif; ?>
<?php if ($detail !== ''): ?><pre><?= $h($detail) ?></pre><?php endif; ?>

<div class="card">
  <h2>Funnel</h2>
  <div class="stats">
    <?php foreach (['identified','needs_contact','researched','preview_ready','enhancement_ready','pitched','converted','not_a_fit','excluded'] as $st): ?>
      <div class="stat"><b><?= (int)($funnel[$st] ?? 0) ?></b><?= $h(str_replace('_', ' ', $st)) ?></div>
    <?php endforeach; ?>
    <div class="stat"><b><?= $sentToday ?></b>sent today</div>
    <div class="stat"><b><?= $visitsToday ?></b>visits today</div>
    <div class="stat"><b><?= $unverified ?></b>awaiting truth gate</div>
  </div>
</div>

<div class="card">
  <h2>Autopilot</h2>
  <p class="mut" style="margin:.3rem 0 .7rem;color:var(--mut);font-size:.88rem">Last run: <?= $lastRun !== '' ? $h($lastRun) : 'never' ?> &middot; Master switch: <b><?= $apMaster === '1' ? 'ON (sending)' : 'OFF (pipeline only)' ?></b></p>
  <?php if ($lastSummary !== ''): ?>
    <details style="margin-bottom:.7rem"><summary style="cursor:pointer;font-size:.85rem;color:var(--mut)">Last run detail</summary><pre><?= $h($lastSummary) ?></pre></details>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="run">
    <input type="hidden" name="job" value="autopilot">
    <button>Run autopilot now</button>
  </form>
  <p style="font-size:.78rem;color:var(--mut);margin:.6rem 0 0">cPanel cron (8 am daily): <code>curl -s "<?= $h($base) ?>/cron.php?job=autopilot&amp;key=YOUR_KEY" &gt;/dev/null 2&gt;&amp;1</code></p>
</div>

<details>
<summary style="cursor:pointer;background:var(--card);border-radius:12px;padding:.75rem 1rem;margin-bottom:1rem;list-style:none;font-size:.85rem;color:var(--mut)">&#9881; Diagnostics (individual workers)</summary>
<div class="card" style="margin-top:.5rem">
  <div class="runs">
    <?php foreach (['migrate','source','research','verify','personalize','voice','send','heat','all','autopilot'] as $job): ?>
      <form class="inline" method="post"><input type="hidden" name="action" value="run"><input type="hidden" name="job" value="<?= $job ?>">
        <button type="submit" class="<?= $job === 'send' ? 'warn' : 'ghost' ?>"><?= $job ?></button></form>
    <?php endforeach; ?>
  </div>
</div>
</details>

<div class="card">
  <h2>Triage (<?= count($triage) ?>)</h2>
  <?php if ($triage === []): ?><p style="color:var(--mut)">Nothing waiting.</p><?php endif; ?>
  <?php foreach ($triage as $t): ?>
    <div class="row">
      <div class="who"><?= $h($t['business_name']) ?> <span class="tag"><?= (int)$t['fit_score'] ?></span><small><?= $h($t['location_city']) ?> · <?= $h($t['pipeline_status']) ?></small></div>
      <form class="inline" method="post"><input type="hidden" name="action" value="triage"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button>✓ go</button></form>
      <form class="inline" method="post"><input type="hidden" name="action" value="exclude"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button class="bad">✕</button></form>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Verified &amp; ready to pitch (<?= count($ready) ?>)</h2>
  <?php if ($ready === []): ?><p style="color:var(--mut)">Nothing verified yet — run verify.</p><?php endif; ?>
  <?php foreach ($ready as $r): ?>
    <div class="row">
      <div class="who"><?= $h($r['business_name']) ?> <span class="tag"><?= (int)$r['fit_score'] ?></span>
        <small><?= $h($r['location_city']) ?> · <?= $h($r['pipeline_status']) ?> · <?= $r['email_address'] ? $h($r['email_address']) : 'no email' ?></small></div>
      <a href="<?= $h($base . '/go/' . $r['business_slug']) ?>" target="_blank">preview ↗</a>
      <a href="<?= $h($base . '/listen/' . $r['business_slug']) ?>" target="_blank">listen ↗</a>
      <form class="inline" method="post"><input type="hidden" name="action" value="suppress"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="bad">not a fit</button></form>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>Pitched (<?= count($pitched) ?>)</h2>
  <?php foreach ($pitched as $p): ?>
    <div class="row">
      <div class="who"><?= $h($p['business_name']) ?><small>touch <?= (int)$p['touches'] ?> · last <?= $h((string)$p['last_sent']) ?></small></div>
      <a href="<?= $h($base . '/go/' . $p['business_slug']) ?>" target="_blank">preview ↗</a>
      <form class="inline" method="post"><input type="hidden" name="action" value="suppress"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="bad">unsub</button></form>
    </div>
  <?php endforeach; ?>
  <?php if ($pitched === []): ?><p style="color:var(--mut)">No active sequences.</p><?php endif; ?>
</div>

<?php if ($leads !== []): ?>
<div class="card">
  <h2>Captured leads</h2>
  <?php foreach ($leads as $l): ?>
    <div class="row"><div class="who"><?= $h($l['business_name']) ?>: <?= $h($l['contact']) ?>
      <small><?= $h((string)$l['name']) ?> · <?= $h(mb_substr((string)$l['message'], 0, 60)) ?> · <?= $h((string)$l['created_at']) ?></small></div></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Paste import (Claude Max hunt batch)</h2>
  <form method="post">
    <input type="hidden" name="action" value="import">
    <textarea name="payload" rows="5" placeholder='{"research_results": [ ... ]}'></textarea>
    <button type="submit">Import</button>
  </form>
</div>

<div class="card">
  <h2>Settings</h2>
  <form method="post">
    <input type="hidden" name="action" value="save_settings">
    <div class="toggle"><input type="checkbox" id="ap_master" name="ap_master" <?= $set('ap_master') === '1' ? 'checked' : '' ?>>
      <label for="ap_master"><b>Autopilot master switch</b> — nothing sends while this is off</label></div>
    <div class="toggle"><input type="checkbox" id="ap_digest" name="ap_digest" <?= $set('ap_digest') === '1' ? 'checked' : '' ?>>
      <label for="ap_digest">Daily digest email</label></div>
    <label>Postal address (CAN-SPAM, required before any send)</label>
    <input type="text" name="ap_postal" value="<?= $h($set('ap_postal')) ?>">
    <label>From email</label>
    <input type="text" name="ap_from_email" value="<?= $h($set('ap_from_email')) ?>" placeholder="adam@hoosieronline.com">
    <label>Digest / lead-alert email</label>
    <input type="text" name="ap_digest_email" value="<?= $h($set('ap_digest_email')) ?>">
    <label>Daily cap / per-run cap / research per run / verify per run</label>
    <div style="display:flex;gap:.5rem">
      <input type="text" name="ap_daily_cap" value="<?= $h($set('ap_daily_cap')) ?>" placeholder="30">
      <input type="text" name="ap_pitch_per_run" value="<?= $h($set('ap_pitch_per_run')) ?>" placeholder="5">
      <input type="text" name="ap_research_per_run" value="<?= $h($set('ap_research_per_run')) ?>" placeholder="3">
      <input type="text" name="ap_verify_per_run" value="<?= $h($set('ap_verify_per_run')) ?>" placeholder="3">
    </div>
    <label>Site base URL</label>
    <input type="text" name="ap_site_base" value="<?= $h($set('ap_site_base')) ?>" placeholder="https://v2.hoosieronline.com">
    <label>LLM provider / model / API key</label>
    <select name="llm_provider">
      <option value="anthropic" <?= $set('llm_provider') !== 'gemini' ? 'selected' : '' ?>>anthropic</option>
      <option value="gemini" <?= $set('llm_provider') === 'gemini' ? 'selected' : '' ?>>gemini</option>
    </select>
    <input type="text" name="llm_model" value="<?= $h($set('llm_model')) ?>" placeholder="claude-sonnet-4-6 / gemini-2.5-flash">
    <input type="password" name="llm_api_key" value="<?= $h($set('llm_api_key')) ?>" placeholder="API key">
    <label>Stripe secret key / webhook signing secret</label>
    <input type="password" name="stripe_secret_key" value="<?= $h($set('stripe_secret_key')) ?>" placeholder="sk_live_...">
    <input type="password" name="stripe_webhook_secret" value="<?= $h($set('stripe_webhook_secret')) ?>" placeholder="whsec_...">
    <button type="submit">Save settings</button>
  </form>
</div>

<div class="card">
  <h2>One-time: v1 data migration</h2>
  <p style="font-size:.85rem;color:var(--mut)">Needs <code>config/db-v1.php</code> (copy of db.php pointing at the old database). Carries businesses, research, pipeline status, and seeds the suppression list from every v1 opt-out.</p>
  <form method="post"><input type="hidden" name="action" value="etl"><button class="warn">Run v1 ETL</button></form>
</div>

</body></html>
