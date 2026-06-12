<?php
declare(strict_types=1);
/** The preview page: /go/{slug}. Visits feed heat; the quote form captures leads. */
require dirname(__DIR__) . '/bin/bootstrap.php';

use HoV2\Outreach\Heat;
use HoV2\Outreach\Notify;

$pdo = ho_pdo();
$slug = strtolower((string)preg_replace('/[^a-z0-9-]/i', '', (string)($_GET['slug'] ?? '')));

$row = false;
if ($slug !== '') {
    $q = $pdo->prepare(
        "SELECT pv.id AS preview_id, pv.preview_type, pv.headline, pv.subheadline, pv.services_display,
                pv.opportunity_statement, pv.package_items,
                b.id AS biz_id, b.business_name, b.location_city, b.email_address, b.business_slug,
                p.google_rating, p.google_review_count, p.review_quote_1, p.review_quote_1_author,
                p.review_quote_2, p.review_quote_2_author, p.years_in_business, p.service_area_text
         FROM previews pv
         JOIN businesses b ON b.id = pv.business_id
         LEFT JOIN business_profile p ON p.business_id = b.id
         WHERE pv.preview_slug = ? AND pv.preview_status IN ('ready','sent')"
    );
    $q->execute([$slug]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
}
if ($row === false) {
    http_response_code(404);
    echo '<!doctype html><meta name="robots" content="noindex"><title>Not found</title><p style="font-family:sans-serif;padding:2rem">This preview is no longer available.</p>';
    exit;
}

$thanks = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_quote') {
    if (trim((string)($_POST['company'] ?? '')) === '') { // honeypot
        $name    = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 190);
        $contact = mb_substr(trim((string)($_POST['contact'] ?? '')), 0, 190);
        $message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 2000);
        if ($contact !== '') {
            $pdo->prepare('INSERT INTO captured_leads (business_id, name, contact, message) VALUES (?,?,?,?)')
                ->execute([(int)$row['biz_id'], $name !== '' ? $name : null, $contact, $message !== '' ? $message : null]);
            $note = "New quote request for {$row['business_name']}:\nName: {$name}\nContact: {$contact}\nMessage: {$message}";
            $digestTo = trim(ho_setting($pdo, 'ap_digest_email'));
            if ($digestTo !== '') {
                Notify::send($pdo, $digestTo, "Lead captured — {$row['business_name']}", $note, 'capture', (int)$row['biz_id']);
            }
            if (!empty($row['email_address'])) {
                Notify::send(
                    $pdo, (string)$row['email_address'],
                    "A customer asked for a quote — {$row['business_name']}",
                    "Someone just asked for a quote through your new website preview.\n\n{$note}\n\nThis lead is yours, free.\n\n— Adam, Hoosier Online",
                    'capture', (int)$row['biz_id']
                );
            }
        }
    }
    $thanks = true;
} else {
    try { Heat::logVisit($pdo, (int)$row['preview_id'], (string)($_SERVER['REMOTE_ADDR'] ?? '')); } catch (Throwable) {}
}

$h = static fn(?string $s): string => htmlspecialchars((string)$s, ENT_QUOTES);
$services = json_decode((string)($row['services_display'] ?? '[]'), true) ?: [];
$items = json_decode((string)($row['package_items'] ?? '[]'), true) ?: [];
$total = (int)array_sum(array_map(static fn($i) => (int)($i['price_cents'] ?? 0), $items));
$rating = (float)($row['google_rating'] ?? 0);
$count  = (int)($row['google_review_count'] ?? 0);
$stars  = str_repeat('★', max(0, min(5, (int)round($rating)))) . str_repeat('☆', 5 - max(0, min(5, (int)round($rating))));
$isEnh  = $row['preview_type'] === 'enhancement';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $h($row['headline']) ?></title>
<style>
:root{--ink:#1c2430;--accent:#1f6f43;--accent2:#ef6c30;--bg:#f6f4ef;--card:#fff}
*{box-sizing:border-box;margin:0}
body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--ink);line-height:1.55}
.wrap{max-width:640px;margin:0 auto;padding:1.25rem}
header{background:var(--accent);color:#fff;padding:2.2rem 0 2rem}
header .wrap{padding-top:0;padding-bottom:0}
h1{font-size:1.7rem;line-height:1.2;margin-bottom:.4rem}
.sub{opacity:.92}
.card{background:var(--card);border-radius:12px;padding:1.1rem 1.2rem;margin:1rem 0;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.stars{color:#e8a410;font-size:1.15rem;letter-spacing:2px}
.quote{font-style:italic;border-left:4px solid var(--accent);padding-left:.8rem;margin:.7rem 0}
.quote small{display:block;font-style:normal;opacity:.7;margin-top:.25rem}
.chips{display:flex;flex-wrap:wrap;gap:.5rem}
.chip{background:#e9f2ec;color:var(--accent);border-radius:999px;padding:.35rem .8rem;font-size:.9rem}
.item{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px dashed #ddd}
.item:last-child{border-bottom:0}
.total{display:flex;justify-content:space-between;font-weight:700;padding-top:.6rem}
.cta{display:block;text-align:center;background:var(--accent2);color:#fff;text-decoration:none;font-weight:700;font-size:1.1rem;border-radius:12px;padding:.95rem;margin:1.2rem 0}
form input,form textarea{width:100%;padding:.7rem;border:1px solid #ccc;border-radius:8px;margin:.3rem 0 .7rem;font:inherit}
form button{width:100%;background:var(--accent);color:#fff;border:0;border-radius:8px;padding:.85rem;font-size:1rem;font-weight:600}
.hp{position:absolute;left:-9999px}
.thanks{background:#e9f2ec;border:1px solid var(--accent);border-radius:8px;padding:.8rem;margin-bottom:1rem}
footer{text-align:center;font-size:.8rem;opacity:.6;padding:1.5rem 0 2.5rem}
h2{font-size:1.05rem;margin-bottom:.5rem}
</style>
</head>
<body>
<header><div class="wrap">
  <h1><?= $h($row['headline']) ?></h1>
  <div class="sub"><?= $h($row['subheadline']) ?></div>
</div></header>
<div class="wrap">

<?php if ($count > 0): ?>
<div class="card">
  <span class="stars"><?= $stars ?></span>
  <strong><?= number_format($rating, 1) ?></strong> · <?= $count ?> Google reviews
  <?php if (!empty($row['review_quote_1'])): ?>
    <div class="quote">&ldquo;<?= $h($row['review_quote_1']) ?>&rdquo;
      <?php if (!empty($row['review_quote_1_author'])): ?><small>— <?= $h($row['review_quote_1_author']) ?>, Google review</small><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($row['review_quote_2'])): ?>
    <div class="quote">&ldquo;<?= $h($row['review_quote_2']) ?>&rdquo;
      <?php if (!empty($row['review_quote_2_author'])): ?><small>— <?= $h($row['review_quote_2_author']) ?>, Google review</small><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($services !== []): ?>
<div class="card">
  <h2>Services</h2>
  <div class="chips"><?php foreach ($services as $s): ?><span class="chip"><?= $h((string)$s) ?></span><?php endforeach; ?></div>
</div>
<?php endif; ?>

<?php if (!empty($row['opportunity_statement'])): ?>
<div class="card"><h2>Why this matters</h2><p><?= $h($row['opportunity_statement']) ?></p></div>
<?php endif; ?>

<?php if ($isEnh && $items !== []): ?>
<div class="card">
  <h2>The fix list</h2>
  <?php foreach ($items as $i): ?>
    <div class="item"><span><?= $h((string)($i['label'] ?? '')) ?></span><span>$<?= number_format(((int)($i['price_cents'] ?? 0)) / 100) ?></span></div>
  <?php endforeach; ?>
  <div class="total"><span>All-in</span><span>$<?= number_format($total / 100) ?></span></div>
</div>
<a class="cta" href="checkout.php?slug=<?= $h($slug) ?>">Fix these — $<?= number_format($total / 100) ?> &rarr;</a>
<?php else: ?>
<div class="card">
  <h2>The deal</h2>
  <p>This website — design, copy, your real reviews — built and live this week. One payment, no subscription, no contract.</p>
</div>
<a class="cta" href="checkout.php?slug=<?= $h($slug) ?>">Claim this site — $199 &rarr;</a>
<?php endif; ?>

<div class="card" id="quote">
  <h2>Try it: request a quote from <?= $h($row['business_name']) ?></h2>
  <?php if ($thanks): ?><div class="thanks">Sent! <?= $h($row['business_name']) ?> will get back to you.</div><?php endif; ?>
  <form method="post" action="#quote">
    <input type="hidden" name="action" value="request_quote">
    <input class="hp" type="text" name="company" tabindex="-1" autocomplete="off">
    <input type="text" name="name" placeholder="Your name">
    <input type="text" name="contact" placeholder="Phone or email" required>
    <textarea name="message" rows="3" placeholder="What do you need done?"></textarea>
    <button type="submit">Send quote request</button>
  </form>
</div>

</div>
<footer>Built by Hoosier Online · Indiana</footer>
</body>
</html>
