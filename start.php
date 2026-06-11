<?php
declare(strict_types=1);
/**
 * THE INBOUND ROCKET — public self-serve preview funnel.
 *
 * Any Indiana business owner types their name + town + trade and the existing
 * pipeline does the rest, live: Claude researches them (web search), the
 * preview generates, and ~60-90 seconds later they're looking at their own
 * personalized go.php page — the one with Stripe on it. No Adam involved.
 *
 * Flow:  GET            → landing form
 *        POST           → create business row (triaged=1, self-identified),
 *                         fire llm-research.php async, render building screen
 *        GET ?status=1  → JSON poll: building | ready | strong | queued
 *
 * Degrades gracefully: no LLM config / daily cap hit → lead is captured and
 * queued; the autopilot cron researches it and the autopitch emails the link.
 * Either way the lead is never lost.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

$pdo = null;
try { $pdo = ho_db(); } catch (Throwable) {}

$host = (string)($_SERVER['HTTP_HOST'] ?? 'hoosieronline.com');
$importKey = $pdo ? ho_get_setting($pdo, 'gpt_import_key') : '';

function inb_token(int $bizId, string $key): string {
    return substr(hash_hmac('sha256', 'inb' . $bizId, $key !== '' ? $key : 'inb-fallback'), 0, 24);
}

// ─── Poll endpoint ────────────────────────────────────────────────────────────
if (isset($_GET['status'])) {
    header('Content-Type: application/json');
    $bizId = (int)($_GET['biz'] ?? 0);
    $tok   = (string)($_GET['t'] ?? '');
    if ($pdo === null || $bizId === 0 || !hash_equals(inb_token($bizId, $importKey), $tok)) {
        echo json_encode(['state' => 'error']); exit;
    }
    $s = $pdo->prepare("
        SELECT b.pipeline_status, b.business_slug, b.exclusion_reason, p.preview_status
        FROM businesses b
        LEFT JOIN previews p ON p.business_id = b.id
        WHERE b.id = ?
    ");
    $s->execute([$bizId]);
    $r = $s->fetch();
    if (!$r) { echo json_encode(['state' => 'error']); exit; }
    $status = (string)$r['pipeline_status'];
    if (($r['preview_status'] ?? '') === 'ready' && in_array($status, ['preview_ready', 'enhancement_ready', 'pitched', 'needs_contact'], true)) {
        echo json_encode(['state' => 'ready', 'url' => '/go/' . $r['business_slug']]); exit;
    }
    if ($status === 'excluded' && (string)$r['exclusion_reason'] === 'has_good_website') {
        echo json_encode(['state' => 'strong']); exit;
    }
    echo json_encode(['state' => 'building']); exit;
}

// ─── Submit ───────────────────────────────────────────────────────────────────
$mode = 'form'; $err = ''; $bizId = 0; $bizSlug = ''; $leadEmail = '';

if ($pdo !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = mb_substr(trim((string)($_POST['biz_name'] ?? '')), 0, 120);
    $town  = mb_substr(trim((string)($_POST['town'] ?? '')), 0, 80);
    $catId = (int)($_POST['category_id'] ?? 0);
    $leadEmail = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 190);
    $phone = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
    if (!filter_var($leadEmail, FILTER_VALIDATE_EMAIL)) $leadEmail = '';

    // Honeypot + speed trap — bots fill hidden fields and submit instantly
    $trap  = trim((string)($_POST['website'] ?? ''));
    $ts    = (int)($_POST['ts'] ?? 0);
    $isBot = $trap !== '' || ($ts > 0 && (time() - $ts) < 3);

    if ($name === '' || $town === '' || $catId === 0) {
        $err = 'Business name, town, and trade are all needed — that\'s how I find your reviews.';
    } elseif ($isBot) {
        $mode = 'queued'; // silently swallow bots
    } else {
        // Existing business? If their preview is ready, walk them straight in.
        $slug = ho_slugify($name, $town);
        $chk = $pdo->prepare("
            SELECT b.id, b.business_slug, b.pipeline_status, b.email_address, p.preview_status
            FROM businesses b
            LEFT JOIN previews p ON p.business_id = b.id
            WHERE b.business_slug = ? OR (b.business_name = ? AND b.location_city = ?)
            LIMIT 1
        ");
        $chk->execute([$slug, $name, $town]);
        $existing = $chk->fetch();

        if ($existing && ($existing['preview_status'] ?? '') === 'ready') {
            header('Location: /go/' . $existing['business_slug']);
            exit;
        }

        if ($existing) {
            $bizId   = (int)$existing['id'];
            $bizSlug = (string)$existing['business_slug'];
            if ($leadEmail !== '' && (string)$existing['email_address'] === '') {
                $pdo->prepare("UPDATE businesses SET email_address = ?, best_contact_method = 'email', updated_at = NOW() WHERE id = ?")
                    ->execute([$leadEmail, $bizId]);
            }
        } else {
            $finalSlug = $slug; $i = 2;
            while (true) {
                $c = $pdo->prepare("SELECT id FROM businesses WHERE business_slug = ?");
                $c->execute([$finalSlug]);
                if (!$c->fetch()) break;
                $finalSlug = substr($slug, 0, 170) . '-' . $i++;
            }
            $best = $leadEmail !== '' ? 'email' : ($phone !== '' ? 'phone' : 'unknown');
            try {
                $pdo->prepare("
                    INSERT INTO businesses
                      (business_uid, business_slug, business_name, category_id,
                       location_city, location_state, phone_number, email_address,
                       best_contact_method, pipeline_status, triaged)
                    VALUES (?, ?, ?, ?, ?, 'IN', ?, ?, ?, 'identified', 1)
                ")->execute([ho_uid('biz'), $finalSlug, $name, $catId, $town, $phone, $leadEmail, $best]);
            } catch (PDOException) {
                // triaged column not migrated yet
                $pdo->prepare("
                    INSERT INTO businesses
                      (business_uid, business_slug, business_name, category_id,
                       location_city, location_state, phone_number, email_address,
                       best_contact_method, pipeline_status)
                    VALUES (?, ?, ?, ?, ?, 'IN', ?, ?, ?, 'identified')
                ")->execute([ho_uid('biz'), $finalSlug, $name, $catId, $town, $phone, $leadEmail, $best]);
            }
            $bizId   = (int)$pdo->lastInsertId();
            $bizSlug = $finalSlug;
        }

        // Channel attribution — ?src=fb-group etc., counted in app_settings
        $src = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($_POST['src'] ?? '')));
        if ($src !== '' && $bizId > 0) {
            $k = 'inb_src_' . substr($src, 0, 30);
            ho_set_setting($pdo, $k, (string)((int)ho_get_setting($pdo, $k) + 1));
        }

        if ($bizId > 0 && $mode !== 'queued') {
            // Instant build needs the LLM config, the import key, and cap headroom
            $cap        = max(1, (int)(ho_get_setting($pdo, 'inb_daily_cap') ?: '25'));
            $prevStatus = $existing ? (string)$existing['pipeline_status'] : 'identified';
            $canFire = is_file('/home1/spofnkte/llm-config.php')
                    && $importKey !== ''
                    && in_array($prevStatus, ['identified', 'researched'], true)
                    && ho_bump_daily_counter($pdo, 'inb_counter', $cap);
            if ($canFire) {
                $ch = curl_init('https://' . $host . '/llm-research.php');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode(['business_id' => $bizId]),
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Api-Key: ' . $importKey],
                    CURLOPT_TIMEOUT_MS     => 1500,
                    CURLOPT_NOSIGNAL       => true,
                ]);
                @curl_exec($ch);
                curl_close($ch);
                $mode = 'building';
            } else {
                $mode = 'queued'; // autopilot research + autopitch picks it up
            }
        } elseif ($mode !== 'queued') {
            $mode = 'queued';
        }
    }
}

$categories = $pdo ? ho_get_categories($pdo) : [];
$srcParam   = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)($_GET['src'] ?? '')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>See your business&rsquo;s new website — free, 60 seconds | Hoosier Online</title>
  <meta name="description" content="Type your business name and watch a personalized website preview build itself — free, no signup, built for Indiana service businesses.">
  <link rel="stylesheet" href="/assets/css/front-door.css?v=2">
</head>
<body class="front-door-preview-page">

<nav class="fd-nav">
  <a class="fd-nav-brand" href="/">HOOSIER ONLINE</a>
</nav>

<main class="fd-shell">

<?php if ($mode === 'building'): ?>
  <!-- ── BUILDING SCREEN ─────────────────────────────────────────────────── -->
  <section class="fd-card fd-start-build">
    <p class="fd-kicker">Hold tight</p>
    <h2>Building your page right now.</h2>
    <p class="fd-start-stage" id="stage">Looking up your business&hellip;</p>
    <div class="fd-start-bar"><div class="fd-start-bar-fill" id="bar"></div></div>
    <p class="fd-muted" id="hint">This usually takes about a minute. It&rsquo;s reading your real Google reviews, checking your competitors, and designing around what it finds.</p>
  </section>
  <script>
  var BIZ = <?= (int)$bizId ?>, TOK = <?= json_encode(inb_token($bizId, $importKey)) ?>;
  var stages = [
    'Looking up your business…',
    'Reading your Google reviews…',
    'Checking what your competitors look like online…',
    'Measuring what you might be missing…',
    'Designing your layout…',
    'Putting your best reviews front and center…',
    'Almost there — final touches…'
  ];
  var si = 0, pct = 4, done = false, started = Date.now();
  setInterval(function() {
    if (done) return;
    si = Math.min(si + 1, stages.length - 1);
    document.getElementById('stage').textContent = stages[si];
  }, 9000);
  setInterval(function() {
    if (done) return;
    pct = Math.min(pct + (96 - pct) * 0.06, 96);
    document.getElementById('bar').style.width = pct + '%';
  }, 800);
  function poll() {
    if (done) return;
    fetch('/start.php?status=1&biz=' + BIZ + '&t=' + TOK)
      .then(function(r){ return r.json(); })
      .then(function(d) {
        if (d.state === 'ready') {
          done = true;
          document.getElementById('bar').style.width = '100%';
          document.getElementById('stage').textContent = 'Done. Opening your page…';
          setTimeout(function(){ window.location.href = d.url; }, 900);
        } else if (d.state === 'strong') {
          done = true;
          document.getElementById('stage').textContent = 'Good news — your online presence already looks strong.';
          document.getElementById('hint').textContent = 'I couldn’t find quick wins worth charging you for. If you ever want a second pair of eyes anyway, email adam@hoosieronline.com.';
        } else if (Date.now() - started > 150000) {
          done = true;
          document.getElementById('stage').textContent = 'Taking longer than usual — I’ll finish it by hand.';
          document.getElementById('hint').textContent = <?= json_encode($leadEmail !== '' ? 'Your page link will land at ' . $leadEmail . ' shortly. No action needed.' : 'Check back here later today — your page will be ready.') ?>;
        } else {
          setTimeout(poll, 3500);
        }
      })
      .catch(function(){ setTimeout(poll, 5000); });
  }
  setTimeout(poll, 6000);
  </script>

<?php elseif ($mode === 'queued'): ?>
  <!-- ── QUEUED SCREEN ───────────────────────────────────────────────────── -->
  <section class="fd-card fd-start-build">
    <p class="fd-kicker">You&rsquo;re in</p>
    <h2>Your page is in the build queue.</h2>
    <p style="font-size:16px;line-height:1.6">I build each one personally against your real Google reviews and your local competition&mdash;<?= $leadEmail !== '' ? ' the link will land at <strong>' . ho_h($leadEmail) . '</strong> within a few hours.' : ' check back later today, or leave your email next time and I\'ll send it to you.' ?></p>
    <p class="fd-muted">No charge, no signup, no obligation. If it&rsquo;s not for you, delete the email and that&rsquo;s the end of it.</p>
  </section>

<?php else: ?>
  <!-- ── LANDING FORM ────────────────────────────────────────────────────── -->
  <section class="fd-card fd-start-hero">
    <p class="fd-kicker">Free &middot; no signup &middot; takes 60 seconds</p>
    <h1 class="fd-start-h1">Watch your business&rsquo;s new website build itself.</h1>
    <p style="font-size:16px;line-height:1.65">Type your business name. I&rsquo;ll read your real Google reviews, size up your local competition, and build you a personalized website preview &mdash; while you watch. Keep it for $199 flat if you love it. Walk away free if you don&rsquo;t.</p>
    <?php if ($err !== ''): ?><p class="fd-start-err"><?= ho_h($err) ?></p><?php endif; ?>
    <form method="POST" action="/start.php" class="fd-start-form" autocomplete="off">
      <input type="hidden" name="ts" value="<?= time() ?>">
      <input type="hidden" name="src" value="<?= ho_h($srcParam) ?>">
      <input type="text" name="website" value="" class="fd-start-trap" tabindex="-1" aria-hidden="true">
      <label>Your business name
        <input type="text" name="biz_name" required maxlength="120" placeholder="e.g. Miller Lawn &amp; Landscape" value="<?= ho_h((string)($_POST['biz_name'] ?? '')) ?>">
      </label>
      <label>Town
        <input type="text" name="town" required maxlength="80" placeholder="e.g. Kokomo" value="<?= ho_h((string)($_POST['town'] ?? '')) ?>">
      </label>
      <label>What you do
        <select name="category_id" required>
          <option value="">Choose your trade&hellip;</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= (int)$c['id'] ?>"<?= (int)($_POST['category_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= ho_h((string)$c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Email — where I send your link <span class="fd-start-opt">(recommended)</span>
        <input type="email" name="email" maxlength="190" placeholder="you@example.com" value="<?= ho_h((string)($_POST['email'] ?? '')) ?>">
      </label>
      <button type="submit" class="fd-btn fd-btn-primary fd-start-go">Build my page free &rarr;</button>
      <p class="fd-muted" style="text-align:center;margin-top:8px">No card. No account. No call. Just your page.</p>
    </form>
  </section>

  <section class="fd-card">
    <p class="fd-kicker">How it works</p>
    <div class="fd-start-steps">
      <div class="fd-start-step"><strong>1. It reads what&rsquo;s real.</strong> Your actual Google reviews, your rating, your years in business — pulled live, not invented.</div>
      <div class="fd-start-step"><strong>2. It builds around YOU.</strong> Your best customer quote goes front and center. Your services, your town, your story.</div>
      <div class="fd-start-step"><strong>3. You decide.</strong> Love it? $199 once, live in 48 hours, you own it forever — full refund if you&rsquo;re not happy. Don&rsquo;t? Close the tab, no hard feelings.</div>
    </div>
    <p class="fd-muted" style="margin-top:14px">Built by Adam Ferree in New Castle, Indiana &mdash; websites for Indiana&rsquo;s hardest-working businesses. One client closed a $15k job off a clean site and logo.</p>
  </section>
<?php endif; ?>

  <footer class="fd-footer">
    <strong><a href="/">Hoosier Online</a></strong><br>
    Front doors for Indiana&rsquo;s hardest-working businesses.<br>
    <span class="fd-footer-by">Built by Adam Ferree &middot; <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></span>
  </footer>
</main>
</body>
</html>
