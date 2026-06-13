<?php
declare(strict_types=1);
/**
 * The receptionist demo page: /listen/{slug} (business_slug).
 * Tap a scenario, hear the AI receptionist answer as the business.
 * Honesty rule: labeled a preview — never presented as a recording of a real call.
 */
require dirname(__DIR__) . '/bin/bootstrap.php';

use HoV2\Outreach\Heat;

$pdo = ho_pdo();
$slug = strtolower((string)preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['slug'] ?? '')));

$row = false;
if ($slug !== '') {
    $q = $pdo->prepare(
        'SELECT b.id, b.business_name, b.location_city, b.business_slug,
                p.google_rating, p.google_review_count, p.years_in_business
         FROM businesses b
         LEFT JOIN business_profile p ON p.business_id = b.id
         WHERE b.business_slug = ?'
    );
    $q->execute([$slug]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
}
if ($row === false) {
    http_response_code(404);
    echo '<!doctype html><meta name="robots" content="noindex"><title>Not found</title><p style="font-family:sans-serif;padding:2rem">This demo is no longer available.</p>';
    exit;
}

$d = $pdo->prepare('SELECT scenario, label, transcript, audio_path FROM call_demos WHERE business_id = ? ORDER BY id ASC');
$d->execute([(int)$row['id']]);
$demos = $d->fetchAll(PDO::FETCH_ASSOC);
if ($demos === []) {
    http_response_code(404);
    echo '<!doctype html><meta name="robots" content="noindex"><title>Not ready</title><p style="font-family:sans-serif;padding:2rem">This demo is not ready yet.</p>';
    exit;
}

// Demo visits feed the same heat the preview pages do (if a preview row exists).
try {
    $pv = $pdo->prepare('SELECT id FROM previews WHERE business_id = ? ORDER BY id DESC LIMIT 1');
    $pv->execute([(int)$row['id']]);
    $pvId = $pv->fetchColumn();
    if ($pvId !== false) { Heat::logVisit($pdo, (int)$pvId, (string)($_SERVER['REMOTE_ADDR'] ?? '')); }
} catch (Throwable) {}

$h = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES);
$price = max(1, (int)(ho_setting($pdo, 'rcpt_price_cents') ?: 14900));
$priceTxt = '$' . number_format($price / 100) . '/mo';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $h($row['business_name']) ?> — your phone, answered</title>
<style>
:root{--ink:#1c2430;--accent:#1f6f43;--accent2:#ef6c30;--bg:#f6f4ef;--card:#fff}
*{box-sizing:border-box;margin:0}
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--ink);line-height:1.55}
.wrap{max-width:560px;margin:0 auto;padding:1.25rem}
header{background:var(--accent);color:#fff;padding:2rem 0 1.8rem}
header .wrap{padding-top:0;padding-bottom:0}
h1{font-size:1.55rem;line-height:1.25;margin-bottom:.4rem}
.sub{opacity:.92}
.note{font-size:.85rem;opacity:.85;margin-top:.6rem}
.phone{background:#10141b;border-radius:26px;padding:1.1rem 1rem 1.3rem;margin:1.2rem 0;color:#e8e6e1;box-shadow:0 8px 28px rgba(0,0,0,.25)}
.phone .biz{text-align:center;padding:.4rem 0 .9rem}
.phone .biz b{font-size:1.05rem;display:block}
.phone .biz small{color:#8b93a1}
.scen{display:block;width:100%;text-align:left;background:#1a212c;border:1px solid #2a3342;color:#e8e6e1;border-radius:12px;padding:.8rem .9rem;font:inherit;font-weight:600;margin:.45rem 0}
.scen.on{border-color:var(--accent2)}
.scen small{display:block;font-weight:400;color:#8b93a1;font-size:.8rem}
audio{width:100%;margin:.6rem 0 .2rem}
.bubbles{margin-top:.5rem;display:none}
.bubbles.show{display:block}
.bub{max-width:85%;border-radius:14px;padding:.5rem .75rem;margin:.4rem 0;font-size:.9rem}
.bub.r{background:#1f6f43;color:#fff;border-bottom-left-radius:4px}
.bub.c{background:#2a3342;margin-left:auto;border-bottom-right-radius:4px}
.bub small{display:block;font-size:.68rem;opacity:.7;margin-bottom:.15rem}
.card{background:var(--card);border-radius:12px;padding:1.1rem 1.2rem;margin:1rem 0;box-shadow:0 1px 4px rgba(0,0,0,.08)}
h2{font-size:1.05rem;margin-bottom:.5rem}
.cta{display:block;text-align:center;background:var(--accent2);color:#fff;text-decoration:none;font-weight:700;font-size:1.1rem;border-radius:12px;padding:.95rem;margin:1.2rem 0}
ul{padding-left:1.2rem}li{margin:.3rem 0}
footer{text-align:center;font-size:.8rem;opacity:.6;padding:1.5rem 0 2.5rem}
</style>
</head>
<body>
<header><div class="wrap">
  <h1>Every call you miss, answered.</h1>
  <div class="sub"><?= $h($row['business_name']) ?> — this is what your phone sounds like with a receptionist on it.</div>
  <div class="note">Preview: a simulated call using your real business details — hear what your receptionist will sound like.</div>
</div></header>
<div class="wrap">

<div class="phone">
  <div class="biz"><b><?= $h($row['business_name']) ?></b><small><?= $h($row['location_city']) ?>, IN · incoming call</small></div>
  <?php foreach ($demos as $i => $demo):
      $lines = json_decode((string)$demo['transcript'], true) ?: []; ?>
  <div>
    <button class="scen" data-i="<?= $i ?>"><?= $h($demo['label']) ?><small>tap to listen</small></button>
    <?php if (!empty($demo['audio_path'])): ?>
      <audio id="au<?= $i ?>" preload="none" controls style="display:none" src="<?= $h('/' . ltrim((string)$demo['audio_path'], '/')) ?>"></audio>
    <?php endif; ?>
    <div class="bubbles" id="tx<?= $i ?>">
      <?php foreach ($lines as $l):
          $isR = strcasecmp(trim((string)($l['speaker'] ?? '')), 'caller') !== 0; ?>
        <div class="bub <?= $isR ? 'r' : 'c' ?>"><small><?= $isR ? 'Your receptionist' : 'Caller' ?></small><?= $h((string)($l['line'] ?? '')) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <h2>How it works</h2>
  <ul>
    <li>Your number stays the same. One dial code forwards only the calls you <em>don't</em> answer.</li>
    <li>It answers as <?= $h($row['business_name']) ?> — your services, your hours. It never makes prices up; it takes the message.</li>
    <li>Every caller's name, number, and job lands in your inbox. No more lost work.</li>
  </ul>
</div>

<div class="card">
  <h2>The deal</h2>
  <p><strong><?= $priceTxt ?></strong>, flat. No setup fee, no contract, cancel anytime — one dial code undoes it. One saved job a month pays for it several times over.</p>
</div>
<a class="cta" href="checkout.php?offer=receptionist&amp;biz=<?= $h($slug) ?>">Turn it on — <?= $priceTxt ?> &rarr;</a>

</div>
<footer>Built by Hoosier Online · Indiana</footer>
<script>
document.querySelectorAll('.scen').forEach(function(btn){
  btn.addEventListener('click', function(){
    var i = btn.dataset.i;
    document.querySelectorAll('.scen').forEach(function(b){ b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('audio').forEach(function(a){ a.pause(); a.style.display='none'; });
    document.querySelectorAll('.bubbles').forEach(function(t){ t.classList.remove('show'); });
    var au = document.getElementById('au'+i);
    if (au) { au.style.display='block'; au.play().catch(function(){}); }
    document.getElementById('tx'+i).classList.add('show');
  });
});
</script>
</body>
</html>
