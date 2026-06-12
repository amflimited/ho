<?php
declare(strict_types=1);
/**
 * REVIEW CONCIERGE — the second product's front door.
 *
 * /rep.php?slug={business_slug} shows a business owner their real unanswered
 * Google reviews with the replies already written, in their voice. The worst
 * review's reply is the showpiece; the rest are teased. $99 catch-up (every
 * reply posted/handed over) + optional $29/mo concierge (every new review
 * answered within 24h) — both through the existing checkout.php/Stripe rails.
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';
require_once __DIR__ . '/fd-chrome.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$row  = null; $drafts = []; $err = null;

try {
    if ($slug === '') throw new RuntimeException('no-slug');
    $pdo = ho_db();
    $s = $pdo->prepare("
        SELECT b.id, b.business_name, b.business_slug, b.location_city, b.owner_first_name,
               b.phone_number, c.name AS category_name,
               r.google_review_count, r.google_rating
        FROM businesses b
        JOIN categories c ON c.id = b.category_id
        LEFT JOIN research_records r ON r.business_id = b.id
        WHERE b.business_slug = ?
        LIMIT 1
    ");
    $s->execute([$slug]);
    $row = $s->fetch() ?: null;
    if (!$row) throw new RuntimeException('not-found');
    $drafts = ho_rep_get_drafts($pdo, (int)$row['id']);
    if (empty($drafts)) throw new RuntimeException('not-found');
    try { ho_log_preview_visit($pdo, 0, (int)$row['id']); } catch (Throwable) {}
} catch (Throwable $e) {
    $err = $e->getMessage();
}

$paid = isset($_GET['paid']);

$name       = $row ? (string)$row['business_name'] : '';
$city       = $row ? (string)$row['location_city'] : '';
$catName    = $row ? (string)$row['category_name'] : '';
$ownerFirst = $row ? trim((string)($row['owner_first_name'] ?? '')) : '';
$hi         = $ownerFirst !== '' ? $ownerFirst : $name;
$rating     = $row ? (float)($row['google_rating'] ?? 0) : 0;
$revCount   = $row ? (int)($row['google_review_count'] ?? 0) : 0;
$nDrafts    = count($drafts);
$shown      = array_slice($drafts, 0, 2);
$teased     = max(0, $nDrafts - 2);
$worst      = $drafts[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $row ? ho_h($name) . ' — your reviews, answered' : 'Hoosier Online' ?></title>
  <link rel="stylesheet" href="/assets/css/front-door.css?v=<?= filemtime(__DIR__ . '/assets/css/front-door.css') ?>">
</head>
<body class="front-door-preview-page">

<?php ho_fd_nav($row ? ['cta_href' => '#offer', 'cta_label' => 'Get them posted'] : []); ?>

<main class="fd-shell">
<?php if (!$row): ?>
  <section class="fd-card"><h2>Page not found.</h2><p class="fd-muted">This link may have expired. <a href="/start.php">Build your business a free website preview instead &rarr;</a></p></section>
<?php else: ?>

  <?php if ($paid): ?>
  <section class="fd-card fd-paid-banner">
    <p class="fd-kicker">Payment received</p>
    <h2>You&rsquo;re in. Here&rsquo;s what happens now.</h2>
    <p>Pick whichever path fits &mdash; both get all <?= $nDrafts ?> replies live this week.</p>
    <div style="margin:14px 0;padding:14px;background:rgba(255,255,255,.06);border-radius:12px;border-left:3px solid var(--fd-green)">
      <strong style="display:block;margin-bottom:8px">Option A &mdash; I post every reply for you (recommended)</strong>
      <ol style="margin:0;padding-left:20px;font-size:15px;line-height:1.8">
        <li>Go to <a href="https://business.google.com" target="_blank" rel="noopener" style="color:var(--fd-green)">business.google.com</a> and open your business.</li>
        <li>Tap the three-dot menu &rarr; <strong>Business Profile settings</strong> &rarr; <strong>Managers</strong>.</li>
        <li>Click <strong>Add</strong>, enter <code style="background:rgba(255,255,255,.12);padding:1px 5px;border-radius:4px">adam@hoosieronline.com</code>, set role to <strong>Manager</strong> (not Owner), click <strong>Invite</strong>.</li>
        <li>I&rsquo;ll accept within the hour and start posting. Done.</li>
      </ol>
    </div>
    <div style="margin:14px 0;padding:14px;background:rgba(255,255,255,.04);border-radius:12px;border-left:3px solid rgba(255,255,255,.15)">
      <strong>Option B &mdash; copy-paste pack</strong><br>
      <span style="font-size:15px">Reply to your confirmation email asking for the pack. I&rsquo;ll send a doc with every review quoted and your reply written below it &mdash; you paste each one into Google yourself.</span>
    </div>
    <p class="fd-referral-note">🤝 Know another business drowning in unanswered reviews? $50 for every referral that signs up. Just have them mention <?= ho_h($name) ?>.</p>
    <p class="fd-muted">Questions? <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a> &middot; <a href="tel:+17654434321">(765) 443-4321</a></p>
  </section>
  <?php endif; ?>

  <!-- ── THE TURN ──────────────────────────────────────────────────────────── -->
  <section class="fd-turn">
    <p class="fd-turn-eyebrow"><?= ho_h($catName) ?> &middot; <?= ho_h($city) ?>, IN</p>
    <h1 class="fd-turn-name"><?= ho_h($name) ?></h1>
    <p class="fd-rep-headline">Your customers are talking.<br><strong>Nobody&rsquo;s answering.</strong></p>
    <div class="fd-rep-stats">
      <?php if ($rating > 0): ?><span class="fd-rep-stat"><em><?= number_format($rating, 1) ?>&#9733;</em>Google rating</span><?php endif; ?>
      <?php if ($revCount > 0): ?><span class="fd-rep-stat"><em><?= $revCount ?></em>reviews</span><?php endif; ?>
      <span class="fd-rep-stat fd-rep-stat-bad"><em><?= $nDrafts ?></em>unanswered</span>
    </div>
  </section>

  <!-- ── WHY THIS MATTERS — short and honest ──────────────────────────────── -->
  <section class="fd-card fd-reveal">
    <p class="fd-kicker">Why I&rsquo;m showing you this</p>
    <h2>Every silent review is read by your next customer.</h2>
    <p style="font-size:16px;line-height:1.65">Hi <?= ho_h($hi) ?> &mdash; Adam Ferree, from New Castle. People check your reviews before they call; that&rsquo;s just how it works now. When they see the owner answering &mdash; thanking the happy ones, standing tall on the rough ones &mdash; you read as a business that shows up. Silence reads like nobody&rsquo;s home. So instead of telling you that, I just wrote the replies. All <?= $nDrafts ?> of them, in your voice. Read them below.</p>
  </section>

  <!-- ── THE WORK — real reviews, replies already written ─────────────────── -->
  <section class="fd-card fd-reveal">
    <p class="fd-kicker">Already written &mdash; read them free</p>
    <h2><?= $worst && (int)$worst['review_rating'] <= 3 ? 'Your toughest review, handled first.' : 'Your reviews, answered.' ?></h2>

    <?php foreach ($shown as $d): ?>
    <div class="fd-rep-pair">
      <div class="fd-rep-review">
        <div class="fd-rep-review-head">
          <span class="fd-rep-stars"><?= str_repeat('&#9733;', max(1, (int)$d['review_rating'])) . str_repeat('&#9734;', 5 - max(1, (int)$d['review_rating'])) ?></span>
          <span class="fd-rep-meta"><?= ho_h((string)$d['review_author'] ?: 'A customer') ?><?= (string)$d['review_date'] !== '' ? ' · ' . ho_h((string)$d['review_date']) : '' ?> · no reply from owner</span>
        </div>
        <p>&ldquo;<?= ho_h((string)$d['review_text']) ?>&rdquo;</p>
      </div>
      <div class="fd-rep-reply">
        <div class="fd-rep-reply-tag">✍️ The reply I wrote for you</div>
        <p><?= nl2br(ho_h((string)$d['drafted_reply'])) ?></p>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if ($teased > 0): ?>
    <div class="fd-rep-teaser">
      <strong><?= $teased ?> more repl<?= $teased === 1 ? 'y is' : 'ies are' ?> written and waiting</strong> &mdash; one for every unanswered review on your profile. They&rsquo;re all included below.
    </div>
    <?php endif; ?>
  </section>

  <!-- ── THE OFFER ─────────────────────────────────────────────────────────── -->
  <section class="fd-card fd-offer fd-reveal" id="offer">
    <p class="fd-kicker">One decision</p>
    <h2>Every review answered &mdash; this week.</h2>
    <div class="fd-offer-price-block">
      <span class="fd-offer-amount">$99</span>
      <div class="fd-offer-terms">
        <strong>One-time catch-up. All <?= $nDrafts ?> replies handled.</strong>
        <span>Add me as a manager on your Google profile (two taps, I send instructions) and I post every one &mdash; or I hand you the full pack to paste yourself.</span>
      </div>
    </div>
    <form method="POST" action="/checkout.php" class="fd-checkout-form">
      <input type="hidden" name="slug" value="<?= ho_h((string)$row['business_slug']) ?>">
      <input type="hidden" name="pkg"  value="reputation">
      <label class="fd-care-opt">
        <input type="checkbox" name="care" value="1" checked>
        <span><span class="fd-care-tag">OPTIONAL</span> <strong>Review Concierge &mdash; $29/mo, first 30 days free.</strong> From now on, every new review gets a thoughtful reply within 24 hours &mdash; good ones thanked, rough ones defused before they cost you customers. Cancel anytime with one email, or uncheck it now. $0 extra today either way.</span>
      </label>
      <button type="submit" class="fd-btn fd-btn-primary fd-stripe-btn fd-checkout-main-btn">
        Yes &mdash; answer my reviews &rarr; $99
      </button>
    </form>
    <div class="fd-guarantee">💯 Don&rsquo;t love the replies? Full refund &mdash; you risk nothing.</div>
    <div class="fd-secure-note">Stripe &middot; 256-bit SSL &middot; pay in 2 minutes</div>
    <div class="fd-phone-fallback">Questions first? <a href="tel:+17654434321">Call me: (765) 443-4321</a></div>
  </section>

  <!-- ── WHO ──────────────────────────────────────────────────────────────── -->
  <section class="fd-card fd-reveal">
    <p class="fd-kicker">Who wrote these</p>
    <h2>A real person, up the road.</h2>
    <p style="font-size:15px;line-height:1.65">Adam Ferree, Hoosier Online, New Castle. I build websites and online presences for Indiana service businesses &mdash; one client closed a $15k job off a clean site and logo. Reviews are the same game: trust, visible. If the replies above don&rsquo;t sound like you, tell me what to change &mdash; takes me minutes.</p>
  </section>

<?php ho_fd_footer([
    'viral_src'  => 'rep',
    'viral_link' => 'Watch your own website build itself free',
  ]); ?>
<?php endif; ?>
</main>
</body>
</html>
