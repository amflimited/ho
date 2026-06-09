<?php
declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$row  = null;
$err  = null;

try {
    if ($slug === '') throw new RuntimeException('no-slug');
    $pdo = ho_db();
    $row = ho_get_preview_by_slug($pdo, $slug);
    if (!$row) throw new RuntimeException('not-found');
} catch (Throwable $e) {
    $err = $e->getMessage();
}

// ─── Data ───────────────────────────────────────────────────────────────────
$name       = $row ? (string)$row['business_name']          : '';
$city       = $row ? (string)$row['location_city']          : '';
$catName    = $row ? (string)$row['category_name']          : '';
$opp        = $row ? ho_why_text($row) : '';
$subhead    = $row ? (string)$row['subheadline']            : '';
$package    = $row ? (string)$row['package_recommendation'] : 'standard';
$phone      = $row ? (string)($row['phone_number'] ?? '')   : '';
$ownerFirst = $row ? trim((string)($row['owner_first_name'] ?? '')) : '';

$services = $row ? (array)json_decode((string)($row['services_display'] ?? '[]'), true) : [];
if (empty($services) && $row) {
    $services = (array)json_decode((string)($row['typical_services'] ?? '[]'), true);
}
$gaps      = $row ? (array)json_decode((string)($row['gaps']      ?? '[]'), true) : [];
$strengths = $row ? (array)json_decode((string)($row['strengths'] ?? '[]'), true) : [];

// Filter gap items that contradict structured data fields
$gaps = array_values(array_filter($gaps, function(mixed $g) use ($row): bool {
    $txt = strtolower((string)$g);
    // Structured field says they have a website — drop "no website" AI gap text
    if ((bool)($row['has_website'] ?? false) && (
        str_contains($txt, 'no website') ||
        str_contains($txt, 'no standalone website') ||
        str_contains($txt, 'no web presence') ||
        str_contains($txt, 'without a website') ||
        str_contains($txt, 'doesn\'t have a website') ||
        str_contains($txt, 'does not have a website')
    )) return false;
    return true;
}));

$websiteUrl  = $row ? trim((string)($row['website_url']   ?? '')) : '';
$fbUrl       = $row ? trim((string)($row['facebook_url']  ?? '')) : '';
$hasWebsite  = $row ? (bool)($row['has_website']          ?? false) : false;
$websiteQ    = $row ? (string)($row['website_quality']    ?? 'none') : 'none';
$hasFacebook = $row ? (bool)($row['has_facebook']         ?? false) : false;
$fbActivity  = $row ? (string)($row['facebook_activity']  ?? 'none') : 'none';

$googleCount  = (int)($row['google_review_count'] ?? 0);
$googleRating = (float)($row['google_rating']     ?? 0);
$hasGoogle    = (bool)($row['has_google_business'] ?? false);

$serviceArea  = (string)($row['service_area_text'] ?? ($city !== '' ? $city . ' & surrounding area' : 'Indiana'));
$isManaged    = $package === 'managed';

// ── New research fields ──────────────────────────────────────────────────────
$compHasSite  = (bool)($row['competitor_has_website'] ?? false);
$compName     = trim((string)($row['competitor_name']    ?? ''));
$compWebsite  = trim((string)($row['competitor_website'] ?? ''));
$bookingMethod= (string)($row['booking_method']    ?? 'unknown');
$yearsInBiz   = (int)($row['years_in_business']    ?? 0);
$hasAngi      = (bool)($row['has_angi']            ?? false);
$hasThumbtak  = (bool)($row['has_thumbtack']       ?? false);
$gbpPhotos       = isset($row['gbp_photo_count']) && $row['gbp_photo_count'] !== null ? (int)$row['gbp_photo_count'] : null;
$notMobile       = isset($row['mobile_friendly']) && (string)$row['mobile_friendly'] === '0';
$noSsl           = isset($row['has_ssl'])         && (string)$row['has_ssl']         === '0';
$lastReviewDate  = $row ? trim((string)($row['last_review_date']   ?? '')) : '';
$respondsReviews = $row ? (bool)($row['responds_to_reviews']       ?? false) : false;
$ownerAgeBand    = $row ? trim((string)($row['owner_age_band']     ?? '')) : '';
$reviewAgeMonths = null;
if ($lastReviewDate !== '' && preg_match('/^(\d{4})-(\d{2})$/', $lastReviewDate, $lrdm)) {
    $reviewAgeMonths = ((int)date('Y') - (int)$lrdm[1]) * 12 + ((int)date('n') - (int)$lrdm[2]);
}

$email        = $row ? trim((string)($row['email_address'] ?? '')) : '';
$catSlug      = $row ? (string)($row['category_slug'] ?? '') : '';
$seasonalNote = $row ? ho_seasonal_urgency_note($catSlug) : '';
$isEnhancement = $row && isset($row['preview_type']) && $row['preview_type'] === 'enhancement';
$enhancementGaps = ($isEnhancement && $row) ? ho_enhancement_gaps($row) : [];
$design       = $row ? ho_design_direction($catSlug) : ['key' => 'default', 'name' => '', 'feel' => ''];
$subdomain    = $row ? ho_suggest_subdomain($name) : '';
$suggestedCom = $subdomain !== '' ? str_replace('.hoosieronline.com', '.com', $subdomain) : '';
$modules      = ho_product_modules();
$features     = ho_product_features();
$angle        = $row ? ho_sales_angle($row) : '';

// ── Detect custom domain email (they already own a domain) ────────────────
$existingDomain = '';
if ($email !== '' && !ho_is_freemail($email)) {
    $parts = explode('@', strtolower($email));
    $existingDomain = $parts[1] ?? '';
}
$hasExistingDomain = $existingDomain !== '';
$ownDotCom = $hasExistingDomain ? $existingDomain : $suggestedCom;

// ── Porkbun domain availability check (skip if they already have one) ─────
$domainCheck = null;
if (!$hasExistingDomain && $ownDotCom !== '' && $row) {
    try {
        require_once __DIR__ . '/porkbun.php';
        $domainCheck = ho_porkbun_check($ownDotCom);
    } catch (Throwable) {}
}

// Phone display + tel link
$telRaw = preg_replace('/\D/', '', $phone) ?? '';
$telDisplay = $phone;
if (strlen($telRaw) === 10) {
    $telDisplay = '(' . substr($telRaw, 0, 3) . ') ' . substr($telRaw, 3, 3) . '-' . substr($telRaw, 6);
}

$pageTitle = $name !== ''
    ? ($isEnhancement ? $name . ' — A Few Things Worth Sharing' : $name . ' — Hoosier Online Front Door Preview')
    : 'Hoosier Online';

// OG / social preview
$ogTitle = $name !== ''
    ? ($isEnhancement ? $name . ' — A Few Things Worth Sharing' : $name . ' — See Your Hoosier Online Preview')
    : 'Hoosier Online — Indiana Web Sites for Local Businesses';

$ogDesc = '';
if ($opp !== '') {
    $ogDesc = strlen($opp) > 160 ? substr($opp, 0, 157) . '…' : $opp;
} elseif ($name !== '') {
    $ogDesc = "A custom front-door website built for {$name} in {$city}, Indiana.";
}

$ogUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

// ── Your contact info ─────────────────────────────────────────────────────
$adamPhone  = '(765) 443-4321';
$paid       = isset($_GET['paid']);
$errCode    = trim((string)($_GET['err'] ?? ''));
$stripeErr  = $errCode !== '';

// ── Record template choice + create order on successful payment ──────────────
$statusToken = '';
if ($paid && $row && $pdo !== null) {
    $chosenTpl = substr(trim((string)($_GET['tpl'] ?? '')), 0, 80);
    $paidPkg   = substr(trim((string)($_GET['pkg'] ?? '')), 0, 50);
    $paidDom   = substr(trim((string)($_GET['dom'] ?? '')), 0, 200);

    if ($chosenTpl !== '') {
        try {
            $pdo->prepare("UPDATE previews SET selected_template = ? WHERE business_id = ?")
                ->execute([$chosenTpl, (int)$row['id']]);
        } catch (Throwable) {}
    }

    try {
        $orderResult = ho_create_order(
            $pdo,
            (int)$row['id'],
            isset($row['preview_id']) ? (int)$row['preview_id'] : null,
            $slug,
            $paidPkg !== '' ? $paidPkg : $package,
            $chosenTpl,
            $paidDom !== '' ? $paidDom : $ownDotCom
        );
        $statusToken = $orderResult['token'];
    } catch (Throwable) {}
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= ho_h($pageTitle) ?></title>
  <link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="/assets/css/front-door.css?v=<?= filemtime(__DIR__ . '/assets/css/front-door.css') ?>">
  <meta name="robots" content="noindex">
  <meta property="og:type"        content="website">
  <meta property="og:site_name"   content="Hoosier Online">
  <meta property="og:title"       content="<?= ho_h($ogTitle) ?>">
  <meta property="og:description" content="<?= ho_h($ogDesc) ?>">
  <meta property="og:url"         content="<?= ho_h($ogUrl) ?>">
  <meta name="twitter:card"       content="summary">
  <meta name="twitter:title"      content="<?= ho_h($ogTitle) ?>">
  <meta name="twitter:description" content="<?= ho_h($ogDesc) ?>">
  <script>document.documentElement.classList.add('fd-js')</script>
</head>
<body class="front-door-preview-page">



<main class="fd-shell">

<?php if ($err !== null): ?>

  <section class="fd-card fd-hero">
    <p class="fd-kicker">Hoosier Online</p>
    <h1>This preview isn't ready yet.</h1>
    <p>If someone sent you this link, it may still be in progress. Check back soon — or reach out directly.</p>
    <div class="fd-actions" style="margin-top:24px;">
      <a class="fd-btn fd-btn-secondary" href="/">Back to Hoosier Online</a>
    </div>
  </section>

<?php else: ?>

  <?php if ($stripeErr): ?>
  <section class="fd-card" style="border-left:4px solid var(--fd-red);margin-bottom:12px">
    <p class="fd-kicker" style="color:var(--fd-red)">Checkout error</p>
    <?php if ($errCode === 'stripe'): ?>
      <p style="margin:0;font-size:15px">Online checkout isn&rsquo;t configured yet &mdash; reach out directly: <a href="tel:7654434321">(765) 443-4321</a> or <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></p>
    <?php else: ?>
      <p style="margin:0;font-size:15px">Something went wrong<?= ($errCode !== 'checkout_failed' && $errCode !== '1') ? ': <strong>' . ho_h(urldecode($errCode)) . '</strong>' : '' ?> &mdash; reach out directly: <a href="tel:7654434321">(765) 443-4321</a> or <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($paid): ?>
  <section class="fd-card fd-paid-banner">
    <p class="fd-kicker">Payment received</p>
    <h2>You&rsquo;re in.</h2>
    <p>Your site will be live within 24 hours. I&rsquo;ll send you the link when it&rsquo;s ready. Check your Stripe receipt for a payment confirmation.</p>
    <?php if ($statusToken !== ''): ?>
    <div class="fd-status-link-box">
      <p class="fd-status-link-label">Track your build progress:</p>
      <a class="fd-status-link" href="/status.php?token=<?= ho_h($statusToken) ?>">
        hoosiersonline.com/status.php?token=<?= ho_h(substr($statusToken, 0, 8)) ?>&hellip;
      </a>
      <p class="fd-muted">This link stays active for 30 days. Bookmark it to check progress.</p>
    </div>
    <?php endif; ?>
    <p class="fd-muted">Questions? <a href="tel:7654434321">(765) 443-4321</a> &middot; <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></p>
  </section>
  <?php endif; ?>

  <!-- ── THE TURN ─────────────────────────────────────────────────────────── -->
  <section class="fd-turn">
    <p class="fd-turn-eyebrow"><?= ho_h($catName) ?> &middot; <?= ho_h($city) ?>, IN</p>
    <h1 class="fd-turn-name"><?= ho_h($name) ?></h1>
    <?php if ($isEnhancement): ?>
    <p class="fd-turn-tag"><?= $ownerFirst !== '' ? 'Hey ' . ho_h($ownerFirst) . ' &mdash; I looked over your site and found a few things worth fixing.' : 'I looked over your site and found a few things worth fixing.' ?></p>
    <?php else: ?>
    <p class="fd-turn-tag"><?= $ownerFirst !== '' ? 'Hey ' . ho_h($ownerFirst) . ' &mdash; I built a website for ' . ho_h($name) . '.' : 'I built a website for ' . ho_h($name) . '.' ?><?= ($ownerAgeBand === '55plus') ? ' I handle everything &mdash; you don&rsquo;t touch a thing.' : '' ?></p>
    <?php endif; ?>
    <?php if ($angle !== ''): ?>
      <p class="fd-turn-angle"><?= ho_h($angle) ?></p>
    <?php endif; ?>
    <div class="fd-turn-actions">
      <?php if ($isEnhancement): ?>
      <a href="#what-i-can-add" class="fd-btn fd-btn-primary fd-turn-cta">See What I Found &darr;</a>
      <?php else: ?>
      <a href="#preview" class="fd-btn fd-btn-primary fd-turn-cta">See what <?= ho_h($name) ?> looks like online &darr;</a>
      <?php endif; ?>
    </div>
    <div class="fd-trust-strip">
      <a href="tel:7654434321" class="fd-ts-item">📞 (765) 443-4321</a>
      <?php if (!$isEnhancement): ?>
      <span class="fd-ts-item">⚡ Live in 24 hours — guaranteed</span>
      <span class="fd-ts-item">$199 flat &middot; you own it forever</span>
      <?php else: ?>
      <span class="fd-ts-item">Quote same day</span>
      <span class="fd-ts-item">No monthly fees</span>
      <?php endif; ?>
      <span class="fd-ts-item">✓ 30-day money-back</span>
    </div>
  </section>

  <!-- ── WHY I REACHED OUT ─────────────────────────────────────────────────── -->
  <section class="fd-card fd-why-card fd-reveal">
    <p class="fd-kicker">Why I reached out</p>

    <?php
    // Build the "we looked up" source chips
    $sources = [];
    if ($hasWebsite && $websiteUrl !== '') {
        $wHost = strtolower(ltrim(parse_url($websiteUrl, PHP_URL_HOST) ?: $websiteUrl, 'www.'));
        $suggestedHost = strtolower(ltrim($ownDotCom, 'www.'));
        if ($wHost !== $suggestedHost) {
            $sources[] = ['href' => $websiteUrl, 'label' => ho_h($wHost), 'class' => 'fd-rs-site'];
        }
    }
    if ($hasFacebook && $fbUrl !== '') {
        $sources[] = ['href' => $fbUrl, 'label' => 'Facebook page', 'class' => 'fd-rs-fb'];
    }
    if ($hasGoogle) {
        $gQuery = rawurlencode($name . ' ' . $city . ' Indiana');
        $sources[] = ['href' => 'https://www.google.com/search?q=' . $gQuery, 'label' => 'Google Business', 'class' => 'fd-rs-google'];
    }
    ?>
    <?php // Personal hook FIRST — the specific reason we reached out ?>
    <?php if (!empty($opp)): ?>
      <p class="fd-why" style="font-size:17px;line-height:1.55;color:var(--fd-ink);margin-bottom:14px"><?= ho_h($opp) ?></p>
    <?php endif; ?>

    <?php if ($isEnhancement): ?>
      <p class="fd-why" style="color:var(--fd-ink);font-weight:500;margin-bottom:14px">You already have a website — I looked it over. Here&rsquo;s what I noticed.</p>
    <?php endif; ?>

    <?php if ($googleCount > 0): ?>
    <div class="fd-rating-block">
      <div class="fd-rating-badge">
        <span class="fd-stars"><?= str_repeat('★', min(5, (int)round($googleRating))) . str_repeat('☆', max(0, 5 - (int)round($googleRating))) ?></span>
        <strong><?= number_format($googleRating, 1) ?></strong>
        <span class="fd-rating-count"><?= number_format($googleCount) ?> Google reviews</span>
      </div>
      <p class="fd-rating-source">Your live rating pulled directly from Google.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($sources)): ?>
    <div class="fd-research-sources">
      <span class="fd-rs-label">We reviewed:</span>
      <?php foreach ($sources as $src): ?>
        <?php if ($src['href'] !== null): ?>
          <a href="<?= ho_h($src['href']) ?>" target="_blank" rel="noopener" class="fd-rs-chip <?= $src['class'] ?>"><?= $src['label'] ?></a>
        <?php else: ?>
          <span class="fd-rs-chip <?= $src['class'] ?>"><?= $src['label'] ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php // ── Years in business credibility (site-build only) ─────────────── ?>
    <?php if (!$isEnhancement && $yearsInBiz >= 5 && (!$hasWebsite || $websiteQ === 'none')): ?>
    <div class="fd-signal fd-signal-cred">
      <span class="fd-signal-icon" aria-hidden="true">📅</span>
      <div>
        <strong><?= $yearsInBiz ?> years in business.</strong>
        That track record is completely invisible online right now. A website makes your experience the first thing a customer sees — not an afterthought buried three clicks deep in a Google listing.
      </div>
    </div>
    <?php endif; ?>

    <?php // ── Review equity — locked in Google (site-build only) ──────────── ?>
    <?php if (!$isEnhancement && $googleCount >= 10 && (!$hasWebsite || $websiteQ === 'none')): ?>
    <div class="fd-signal fd-signal-cred">
      <span class="fd-signal-icon" aria-hidden="true">🔒</span>
      <div>
        <strong>Your <?= number_format($googleCount) ?> reviews are locked inside Google.</strong>
        Right now that proof only surfaces when someone searches your exact name. Put a website in front of it and those reviews become your opening argument on every page — every search, every quote, every estimate you send.
      </div>
    </div>
    <?php endif; ?>

    <?php // ── Technical issues on existing site ──────────────────────────── ?>
    <?php if ($hasWebsite && ($notMobile || $noSsl)): ?>
    <div class="fd-signal fd-signal-tech">
      <span class="fd-signal-icon" aria-hidden="true">⚡</span>
      <div>
        <?php if ($notMobile && $noSsl): ?>
          <strong>Your site isn&rsquo;t mobile-friendly and has no SSL.</strong>
          Over 70% of local searches happen on phones. Google is actively penalising your site in search results because of both issues, and every major browser warns visitors &ldquo;Not Secure&rdquo; before they read a word. That warning alone loses you jobs.
        <?php elseif ($notMobile): ?>
          <strong>Your site isn&rsquo;t mobile-friendly.</strong>
          Over 70% of local searches happen on phones. Google actively penalises sites that aren&rsquo;t optimised — meaning competitors rank above you even if you&rsquo;ve been in business longer and have better reviews.
        <?php else: ?>
          <strong>Your site has no SSL certificate.</strong>
          Every major browser flags your site as &ldquo;Not Secure&rdquo; before a visitor reads a single word. That warning alone is enough to send most people straight back to the search results.
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php // ── GBP photo count ─────────────────────────────────────────────── ?>
    <?php if ($gbpPhotos !== null && $gbpPhotos < 10): ?>
    <div class="fd-signal fd-signal-friction">
      <span class="fd-signal-icon" aria-hidden="true">📷</span>
      <div>
        <strong>Only <?= $gbpPhotos ?> photo<?= $gbpPhotos !== 1 ? 's' : '' ?> on your Google listing.</strong>
        Google&rsquo;s own data shows businesses with 20+ photos get significantly more profile visits and direct calls. Customers want to see the work before they commit. <?= ($gbpPhotos === 0) ? 'Right now there&rsquo;s nothing for them to look at.' : 'You&rsquo;re not giving them enough to make the call.' ?>
      </div>
    </div>
    <?php endif; ?>

    <?php // ── Stale reviews recency ────────────────────────────────────────── ?>
    <?php if ($reviewAgeMonths !== null && $reviewAgeMonths >= 6 && $googleCount >= 3): ?>
    <div class="fd-signal fd-signal-friction">
      <span class="fd-signal-icon" aria-hidden="true">🕐</span>
      <div>
        <strong>Your most recent review was <?= $reviewAgeMonths ?> month<?= $reviewAgeMonths !== 1 ? 's' : '' ?> ago.</strong>
        Customers look at recency, not just the star count. A review from <?= $reviewAgeMonths >= 12 ? 'over a year ago' : 'several months ago' ?> reads as &ldquo;are they still taking jobs?&rdquo; Without fresh activity, you&rsquo;re losing work to businesses that just look more active — even if they&rsquo;re not better.
      </div>
    </div>
    <?php endif; ?>

    <?php // ── Phone-only booking friction ─────────────────────────────────── ?>
    <?php if ($bookingMethod === 'phone' && $googleCount >= 5): ?>
    <div class="fd-signal fd-signal-friction">
      <span class="fd-signal-icon" aria-hidden="true">📞</span>
      <div>
        <strong>Phone-only means silent lost jobs.</strong>
        The 11pm searcher. The person who hates making calls. The customer who wants to send details in writing first. All of those people found you — and then left. A contact form captures that job instead of handing it to a competitor.
      </div>
    </div>
    <?php endif; ?>

    <?php // ── Angi / Thumbtack listing ──────────────────────────────────────── ?>
    <?php if ($hasAngi || $hasThumbtak): ?>
    <?php $platform = $hasAngi ? 'Angi' : 'Thumbtack'; ?>
    <div class="fd-signal fd-signal-roi">
      <span class="fd-signal-icon" aria-hidden="true">$</span>
      <div>
        <strong>You&rsquo;re listed on <?= ho_h($platform) ?>.</strong>
        Many businesses on that platform pay per lead or per connection. Whether you do or not, a contact form on your own site captures the same search traffic permanently — no platform taking a cut, no competing with other bids on the same job.
      </div>
    </div>
    <?php endif; ?>

    <?php // ── Competitive pressure — site-build only, moved to end ────────── ?>
    <?php if (!$isEnhancement && $compHasSite && $compName !== ''): ?>
    <div class="fd-signal fd-signal-comp">
      <span class="fd-signal-icon" aria-hidden="true">⚠</span>
      <div>
        <strong><?= ho_h($compName) ?> has a website.</strong>
        <?php if ($compWebsite !== ''): ?>
          When someone searches for <?= ho_h(strtolower($catName)) ?> in <?= ho_h($city) ?>, they find
          <a href="<?= ho_h($compWebsite) ?>" target="_blank" rel="noopener"><?= ho_h(parse_url($compWebsite, PHP_URL_HOST) ?: $compName) ?></a>
          at the top of the results. You don&rsquo;t show up at all. That customer made their choice before they knew you existed.
        <?php else: ?>
          When someone searches for <?= ho_h(strtolower($catName)) ?> in <?= ho_h($city) ?>, they find <?= ho_h($compName) ?> at the top of the results. You don&rsquo;t show up. That customer made their choice before they knew you existed.
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($strengths)): ?>
      <p class="fd-str-intro">Working in your favour:</p>
      <div class="fd-str-list">
        <?php foreach ($strengths as $s): ?>
          <div class="fd-str-item"><span class="fd-str-marker" aria-hidden="true">✓</span><?= ho_h((string)$s) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($gaps)): ?>
      <p class="fd-gap-intro">What&rsquo;s worth fixing:</p>
      <div class="fd-gap-list">
        <?php foreach ($gaps as $g): ?>
          <div class="fd-gap-item"><span class="fd-gap-marker" aria-hidden="true">✗</span><?= ho_h((string)$g) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$isEnhancement): ?>
    <p class="fd-why-scroll">I built something to fix that. See it below &darr;</p>
    <?php endif; ?>
  </section>

  <?php if ($isEnhancement): ?>
  <!-- ── APP ENGINE OFFER (enhancement only) ─────────────────────────────── -->
  <section class="fd-card fd-offer fd-reveal" id="what-i-can-add">
    <p class="fd-kicker">What I can build for you</p>
    <h2>App Engine for <?= ho_h($name) ?>.</h2>

    <p style="font-size:16px;line-height:1.6;margin-bottom:4px">Your existing site stays exactly as it is. I build a dedicated panel at <strong><?= ho_h($subdomain) ?></strong> — contact form, booking requests, job quotes. Customers use it; you get the message. No changes to your current website needed.</p>

    <?php
    $aeItems = [];
    if (in_array('contact_form', $enhancementGaps, true)) {
        $aeItems[] = ['icon'=>'📋','title'=>'Contact & job request form','body'=>'The 11pm searcher who won\'t call, the person who wants to put it in writing — they fill out a form. You wake up to a new job request instead of a missed opportunity.'];
    }
    if (in_array('paid_leads', $enhancementGaps, true)) {
        $aeItems[] = ['icon'=>'💸','title'=>'Stop paying per lead','body'=>'Customers who find you through Angi or Thumbtack can reach out directly through your App Engine — same enquiry, no platform cut, no competing bids on the same job.'];
    }
    if (in_array('tech_issues', $enhancementGaps, true)) {
        $aeItems[] = ['icon'=>'📱','title'=>'Mobile-ready from day one','body'=>'App Engine is fully mobile-optimised and SSL-secured. Loads fast on every phone, no browser warnings — even if your main site has issues.'];
    }
    if (in_array('google_business', $enhancementGaps, true)) {
        $aeItems[] = ['icon'=>'📍','title'=>'A real URL to link from Google','body'=>'Your Google Business profile can link to your App Engine address — gives searchers somewhere to go beyond your phone number.'];
    }
    if (empty($aeItems)) {
        $aeItems[] = ['icon'=>'📋','title'=>'Contact & booking form','body'=>'Put a contact form in front of customers who find you online but don\'t want to call. Every job starts with that first message.'];
        $aeItems[] = ['icon'=>'📅','title'=>'Appointment & quote requests','body'=>'Let customers request a time, a quote, or a callback. You get the details in writing — no phone tag.'];
    }
    ?>
    <div class="fd-app-engine-what">
      <?php foreach ($aeItems as $item): ?>
      <div class="fd-ae-item">
        <span class="fd-ae-icon"><?= ho_h($item['icon']) ?></span>
        <div class="fd-ae-body">
          <strong><?= ho_h($item['title']) ?></strong>
          <p><?= ho_h($item['body']) ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="background:rgba(47,94,54,.06);border:1px solid rgba(47,94,54,.18);border-radius:14px;padding:12px 16px;margin:4px 0 16px;display:flex;align-items:center;gap:12px">
      <span style="font-size:20px">🌐</span>
      <div>
        <strong style="display:block;font-size:14px;font-weight:900;color:var(--fd-green)"><?= ho_h($subdomain) ?></strong>
        <span style="font-size:12px;color:var(--fd-muted)">Your dedicated App Engine address — included</span>
      </div>
    </div>

    <div class="fd-offer-price-block">
      <span class="fd-offer-amount">$99</span>
      <div class="fd-offer-terms">
        <strong>One-time. No monthly fees.</strong>
        <span>No contract. No subscriptions.</span>
        <span>No changes to your existing website.</span>
      </div>
    </div>

    <ul class="fd-offer-includes">
      <li>✓&ensp;Contact &amp; job request form — live at <?= ho_h($subdomain) ?></li>
      <li>✓&ensp;Appointment &amp; quote request page</li>
      <li>✓&ensp;Mobile-optimised &amp; SSL-secured from day one</li>
      <li>✓&ensp;Your phone, email &amp; services — all in one place</li>
      <li>✓&ensp;No changes to your existing website — ever</li>
      <li>✓&ensp;Hosted &amp; maintained by me, not Wix or GoDaddy</li>
    </ul>

    <div class="fd-live-guarantee">
      <strong>⚡ Live in 24 hours — or you don&rsquo;t pay.</strong>
      From the moment you check out to a real live panel at your address. If it takes longer, I refund you in full.
    </div>

    <div class="fd-guarantee-box">
      <strong>30-day money-back guarantee.</strong>
      Not useful after launch? Full refund, no questions asked.
    </div>

    <form method="POST" action="/checkout.php" class="fd-checkout-form">
      <input type="hidden" name="slug" value="<?= ho_h($slug) ?>">
      <input type="hidden" name="pkg"  value="app_engine">
      <button type="submit" class="fd-btn fd-btn-primary fd-stripe-btn fd-checkout-main-btn">
        Add App Engine to <?= ho_h($name) ?> &rarr; $99
      </button>
    </form>
    <div class="fd-secure-note">Stripe &middot; 256-bit SSL &middot; pay in 2 minutes</div>
    <div class="fd-phone-fallback">Rather talk first? <a href="tel:7654434321">Call me: (765) 443-4321</a></div>
  </section>

  <?php endif; ?>

  <?php if (!$isEnhancement): ?>
  <!-- ══ DESIGN PREVIEW — phone frame + template picker ══════════════════════ -->
  <div id="preview"></div>
  <?php
  $templateKey = $design['key'] ?? 'default';

  // Resolve template directory — DB slug may differ from directory name
  $tplDirName     = ho_template_dir_for_slug($catSlug);
  $catTemplateDir = __DIR__ . '/templates/previews/' . $tplDirName . '/';
  $catIndexFile   = $catTemplateDir . 'index.json';
  $available      = [];
  $usingCatTpls   = false;

  if ($tplDirName !== '' && is_file($catIndexFile)) {
      $catIndex = json_decode(file_get_contents($catIndexFile), true) ?? [];
      foreach ($catIndex as $entry) {
          $k = $entry['key'] ?? '';
          $f = $catTemplateDir . $k . '.php';
          if ($k !== '' && is_file($f) && is_readable($f)) {
              $available[$k] = ['label' => $entry['label'] ?? $k, 'color' => $entry['color'] ?? '#2f5e36', 'file' => $f];
          }
      }
      if (!empty($available)) { $templateKey = array_key_first($available); $usingCatTpls = true; }
  }

  // Fall back to generic design-family templates
  if (empty($available)) {
      $genericOptions = [
          'default'           => ['label' => 'Classic',      'color' => '#2f5e36'],
          'bold_work_truck'   => ['label' => 'Work Truck',   'color' => '#e07b12'],
          'clean_local_pro'   => ['label' => 'Clean Pro',    'color' => '#1e3a5f'],
          'warm_neighborhood' => ['label' => 'Neighborhood', 'color' => '#6b3a2a'],
          'sharp_modern'      => ['label' => 'Sharp Modern', 'color' => '#2563eb'],
      ];
      foreach ($genericOptions as $k => $opt) {
          $f = __DIR__ . '/templates/previews/' . $k . '.php';
          if (is_file($f) && is_readable($f)) $available[$k] = array_merge($opt, ['file' => $f]);
      }
  }
  ?>

  <?php if (!empty($available)): ?>

  <section class="fd-card fd-reveal">
    <p class="fd-kicker">Your website &mdash; live preview</p>
    <h2 class="fd-design-title">This is exactly what we build. Pick your look.</h2>
    <p class="fd-design-sub">Not a mockup. Not a template. This is the real <?= ho_h($name) ?> site — built and ready to go live the moment you say yes.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 14px">
      <span style="font-size:12px;font-weight:700;color:var(--fd-green);background:rgba(47,94,54,.1);border:1px solid rgba(47,94,54,.2);padding:4px 10px;border-radius:20px">⚡ Live in 24 hours</span>
      <span style="font-size:12px;font-weight:700;color:#7a4800;background:rgba(184,112,32,.1);border:1px solid rgba(184,112,32,.2);padding:4px 10px;border-radius:20px">One <?= ho_h(strtolower($catName)) ?> site in <?= ho_h($city) ?></span>
    </div>

    <div class="fd-tpl-picker">
      <?php foreach ($available as $k => $opt): ?>
        <button class="fd-tpl-tab<?= $k === $templateKey ? ' fd-tpl-tab--active' : '' ?>" data-tpl="<?= ho_h($k) ?>" data-label="<?= ho_h($opt['label']) ?>">
          <span class="fd-tpl-dot" style="background:<?= ho_h($opt['color']) ?>"></span>
          <?= ho_h($opt['label']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="fd-phone-stage">
    <div class="fd-phone-frame">
      <div class="fd-phone-screen" id="fd-phone-screen">
        <?php foreach ($available as $k => $opt): ?>
          <div class="fd-tpl-pane" id="tpl-<?= ho_h($k) ?>"<?= $k !== $templateKey ? ' hidden' : '' ?>>
            <?php include $opt['file']; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    </div><!-- /.fd-phone-stage -->

    <p class="fd-excl-note">Every design is built from scratch for <?= ho_h($name) ?>. The style you choose will never be used for another <?= ho_h(strtolower($catName)) ?> company in <?= ho_h($city) ?>.</p>
    <p class="fd-phone-hint">Your choice carries into checkout &mdash; pick the one you want to launch with.</p>
  </section>

  <script>
  (function(){
    var tabs   = document.querySelectorAll('.fd-tpl-tab');
    var screen = document.getElementById('fd-phone-screen');
    var picker = document.querySelector('.fd-tpl-picker');
    if (picker) picker.scrollLeft = 0;
    tabs.forEach(function(tab){
      tab.addEventListener('click', function(){
        var key = tab.dataset.tpl;
        tabs.forEach(function(t){ t.classList.remove('fd-tpl-tab--active'); });
        document.querySelectorAll('.fd-tpl-pane').forEach(function(p){ p.hidden = true; });
        tab.classList.add('fd-tpl-tab--active');
        var pane = document.getElementById('tpl-' + key);
        if (pane) pane.hidden = false;
        if (screen) screen.scrollTop = 0;
        // Record selection in checkout form
        var tplInput = document.getElementById('fd-h-template');
        if (tplInput) tplInput.value = key;
        // Update order summary design row
        var osDesign = document.getElementById('fd-os-design');
        if (osDesign) osDesign.textContent = tab.dataset.label || tab.textContent.trim();
      });
    });
  })();
  </script>

  <?php else: ?>

  <section class="fd-mock">
    <div class="fd-mock-hero">
      <p class="fd-mock-eyebrow"><?= ho_h($catName) ?><?= $city !== '' ? ' &middot; ' . ho_h($city) . ', IN' : '' ?></p>
      <h1 class="fd-mock-name"><?= ho_h($name) ?></h1>
      <p class="fd-mock-area">Serving <?= ho_h($serviceArea) ?></p>
      <?php if ($telRaw !== ''): ?>
        <a class="fd-mock-cta" href="tel:<?= ho_h($telRaw) ?>">Call for a Free Quote</a>
        <p class="fd-mock-phone"><?= ho_h($telDisplay) ?></p>
      <?php else: ?>
        <a class="fd-mock-cta" href="#">Request a Free Quote</a>
      <?php endif; ?>
    </div>
  </section>

  <?php endif; ?>

  <?php endif; // !$isEnhancement ?>

  <!-- ── WHO BUILT THIS ───────────────────────────────────────────────────── -->
  <section class="fd-card fd-trust fd-reveal" id="about">
    <p class="fd-kicker">Who built this</p>
    <div class="fd-trust-inner">
      <div class="fd-trust-avatar" aria-hidden="true">AF</div>
      <div>
        <h2>Adam Ferree</h2>
        <p class="fd-trust-location">New Castle, Indiana &mdash; not a call centre, not an agency</p>
      </div>
    </div>
    <p>I build websites for Indiana service businesses. That&rsquo;s the whole business. I researched <?= ho_h($name) ?> personally &mdash; I looked at your <?= $hasGoogle ? 'Google listing' : ($hasFacebook ? 'Facebook page' : 'online presence') ?>, noted what was missing, and built this preview before reaching out. I only send these when I think the business is worth it.</p>
    <p style="margin-top:10px">When you pay, you&rsquo;re not entering a queue. I start the same day. The site is live within 24 hours &mdash; that&rsquo;s a guarantee, not a target.</p>
    <ul class="fd-trust-signals">
      <li>Indiana-based &mdash; you can call me directly</li>
      <li>Flat price, no contract, no monthly fees — ever</li>
      <li>30-day money-back if you&rsquo;re not happy after launch</li>
      <li>Every site researched and built personally — not outsourced</li>
    </ul>
    <div class="fd-trust-contact">
      <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a>
      <?php if ($adamPhone !== ''): ?>
        <span class="fd-trust-sep">&middot;</span>
        <a href="tel:<?= ho_h(preg_replace('/\D/', '', $adamPhone)) ?>"><?= ho_h($adamPhone) ?></a>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!$isEnhancement): ?>
  <!-- ── WHAT YOU GET (modules) ───────────────────────────────────────────── -->
  <section class="fd-section fd-reveal" id="services">
    <div class="fd-section-head">
      <p class="fd-kicker">What We Build</p>
      <h2>Every page. What it does. Why it matters.</h2>
      <p class="fd-design-sub" style="margin-top:-4px;margin-bottom:8px">Built specifically for <?= ho_h($name) ?> &mdash; not a template, not a placeholder. You own it the day it goes live.</p>
    </div>
    <div class="fd-module-list">
      <?php foreach ($modules as $i => $m): ?>
        <div class="fd-module fd-reveal" style="--reveal-delay:<?= $i * 80 ?>ms">
          <span class="fd-module-icon"><?= ho_h($m['icon'] ?? '◆') ?></span>
          <div>
            <strong><?= ho_h($m['title']) ?></strong>
            <p><?= ho_h($m['desc']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── THE OFFER ────────────────────────────────────────────────────────── -->
  <?php
  $initAvailClass = '';
  $initAvailText  = '';
  if ($domainCheck !== null) {
      if ($domainCheck['available']) { $initAvailClass = 'fd-avail-yes'; $initAvailText = '✓ Available'; }
      else                           { $initAvailClass = 'fd-avail-no';  $initAvailText = '✗ Taken'; }
  }
  $domainInputVal = preg_replace('/\.com$/i', '', $ownDotCom);
  ?>
  <section class="fd-card fd-offer fd-reveal" id="pricing">
    <p class="fd-kicker">One decision</p>
    <h2><?= ho_h($name) ?>&rsquo;s site &mdash; live in 24 hours.</h2>

    <div class="fd-offer-price-block">
      <span class="fd-offer-amount">$199</span>
      <div class="fd-offer-terms">
        <strong>One-time. You own it forever.</strong>
        <span>No monthly fee. No contract. No renewal bill.</span>
        <span>No Wix. No GoDaddy. Just your site.</span>
      </div>
    </div>

    <?php // Domain — folded into offer card ?>
    <div style="margin:4px 0 16px">
      <p class="fd-kicker" style="margin-bottom:6px">Your web address</p>
      <div class="fd-addr-domain">
        <div class="fd-addr-url fd-addr-url-com" id="fd-com-display"><?= ho_h($ownDotCom) ?></div>
        <div class="fd-addr-badges">
          <?php if ($hasExistingDomain): ?>
            <span class="fd-addr-tag" style="background:rgba(47,94,54,.12);color:var(--fd-green)">Your existing domain</span>
          <?php else: ?>
            <span class="fd-addr-tag fd-addr-tag-free">Included free</span>
            <span class="fd-avail-badge <?= ho_h($initAvailClass) ?>" id="fd-com-avail-badge"
                  <?= $initAvailText === '' ? 'hidden' : '' ?>><?= ho_h($initAvailText) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="fd-domain-search" style="margin-top:8px">
        <div class="fd-domain-input-row">
          <input type="text" id="fd-domain-input" class="fd-domain-input"
                 value="<?= ho_h($domainInputVal) ?>" placeholder="yourbusiness" maxlength="63"
                 onkeydown="if(event.key==='Enter'){event.preventDefault();fdCheckDomain();}">
          <span class="fd-domain-tld">.com</span>
          <button type="button" class="fd-domain-check-btn" onclick="fdCheckDomain()">Check</button>
        </div>
        <div class="fd-domain-hint" id="fd-domain-hint"><?php
          if ($domainCheck !== null && !$domainCheck['available']) echo 'That name is taken &mdash; try a variation above.';
          elseif ($hasExistingDomain) echo 'Using a different address? Type it above.';
          else echo 'Want a different name? Type it and tap Check.';
        ?></div>
      </div>
    </div>

    <ul class="fd-offer-includes">
      <li>✓&ensp;Every page customers need to find and hire you</li>
      <li>✓&ensp;Click-to-call + contact form — works the moment it&rsquo;s live</li>
      <li>✓&ensp;Mobile-optimized &amp; SSL secured from day one</li>
      <li>✓&ensp;Your Google reviews pulled in automatically</li>
      <li>✓&ensp;<?= $hasExistingDomain ? ho_h($ownDotCom) . ' connected — no new domain needed' : 'Your .com domain registered &amp; renewals handled — free' ?></li>
      <li>✓&ensp;Exclusive to <?= ho_h($name) ?> — this design is never used for another <?= ho_h(strtolower($catName)) ?> in <?= ho_h($city) ?></li>
    </ul>

    <div class="fd-live-guarantee">
      <strong>⚡ Live in 24 hours — or you don&rsquo;t pay.</strong>
      From the moment you check out to a real live site at your address. If it takes longer, I refund you in full.
    </div>

    <p class="fd-kicker" style="margin-top:20px;margin-bottom:8px">What happens next</p>
    <ol class="fd-offer-steps">
      <li>You say yes &mdash; 2 minutes to check out below</li>
      <li>I build <?= ho_h($name) ?>&rsquo;s site today &mdash; live in under 24 hours</li>
      <li><?= $hasExistingDomain ? ho_h($ownDotCom) : ho_h($ownDotCom ?: $name) ?> goes live &mdash; customers can find and call you</li>
    </ol>

    <?php if ($seasonalNote !== ''): ?>
    <div class="fd-seasonal-note"><span aria-hidden="true">📅</span> <?= ho_h($seasonalNote) ?></div>
    <?php endif; ?>

    <div class="fd-scarcity">I build one <?= ho_h(strtolower($catName)) ?> site in <?= ho_h($city) ?> &mdash; whoever says yes first locks it in.</div>

    <div class="fd-guarantee-box">
      <strong>30-day money-back guarantee.</strong>
      Not happy after launch? Full refund, no questions, no back-and-forth.
    </div>

    <form method="POST" action="/checkout.php" class="fd-checkout-form">
      <input type="hidden" name="slug"         value="<?= ho_h($slug) ?>">
      <input type="hidden" name="pkg"          value="standard">
      <input type="hidden" name="template_key" id="fd-h-template"  value="<?= ho_h($templateKey ?? '') ?>">
      <input type="hidden" name="chosen_com"   id="fd-h-chosen-com" value="<?= ho_h($ownDotCom) ?>">
      <button type="submit" class="fd-btn fd-btn-primary fd-stripe-btn fd-checkout-main-btn">
        Yes &mdash; build <?= ho_h($name) ?>&rsquo;s site &rarr; $199
      </button>
    </form>
    <div class="fd-secure-note">Stripe &middot; 256-bit SSL &middot; pay in 2 minutes</div>
    <div class="fd-phone-fallback">Not ready to pay online? <a href="tel:7654434321">Call me: (765) 443-4321</a></div>
  </section>

  <script>
  function fdCheckDomain() {
    var input     = document.getElementById('fd-domain-input');
    var badge     = document.getElementById('fd-com-avail-badge');
    var display   = document.getElementById('fd-com-display');
    var hint      = document.getElementById('fd-domain-hint');
    var chosenHid = document.getElementById('fd-h-chosen-com');
    if (!input) return;
    var raw = input.value.trim().toLowerCase().replace(/\.com$/i,'').replace(/[^a-z0-9\-]/g,'');
    if (raw.length < 2) { if (hint) hint.textContent = 'Enter at least 2 characters.'; return; }
    var domain = raw + '.com';
    if (display) display.textContent = domain;
    if (badge)   { badge.className = 'fd-avail-badge fd-avail-checking'; badge.textContent = 'Checking…'; badge.hidden = false; }
    if (hint)    hint.textContent = '';
    var fd = new FormData();
    fd.append('domain', raw);
    fetch('/domain-check.php', {method:'POST', body:fd})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.error) { if (badge) badge.hidden = true; if (hint) hint.textContent = '⚠ ' + data.error; return; }
        if (data.available) {
          if (badge)    { badge.className = 'fd-avail-badge fd-avail-yes'; badge.textContent = '✓ Available'; }
          if (hint)     hint.textContent = 'Great — that name is available.';
          if (chosenHid) chosenHid.value = domain;
        } else {
          if (badge) { badge.className = 'fd-avail-badge fd-avail-no'; badge.textContent = '✗ Taken'; }
          if (hint)  hint.textContent = 'That name is taken — try a variation above.';
        }
      })
      .catch(function(){ if (badge) { badge.className = 'fd-avail-badge fd-avail-no'; badge.textContent = 'Check failed — try again'; } });
  }
  </script>

  <?php endif; // !$isEnhancement ?>

  <footer class="fd-footer">
    <strong><a href="/">Hoosier Online</a></strong><br>
    <?php if ($isEnhancement): ?>
    Helping Indiana service businesses grow online.<br>
    <?php else: ?>
    Front doors for Indiana&rsquo;s hardest-working businesses.<br>
    <?php endif; ?>
    <span class="fd-footer-by">Built by Adam Ferree &middot; <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></span>
  </footer>

  <!-- ── STICKY BOTTOM CTA ──────────────────────────────────────────────── -->
  <?php if ($isEnhancement): ?>
  <div class="fd-sticky-bar" id="fd-sticky-bar" hidden>
    <div class="fd-sticky-inner">
      <span class="fd-sticky-biz"><?= ho_h($name) ?></span>
      <a href="#what-i-can-add" class="fd-btn fd-btn-primary fd-sticky-btn">Add App Engine &rarr; $99</a>
    </div>
  </div>
  <script>
  (function(){
    var bar = document.getElementById('fd-sticky-bar');
    var offer = document.getElementById('what-i-can-add');
    if (!bar) return;
    var shown = false;
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){ if (e.isIntersecting) { bar.hidden = true; shown = false; } else if (shown) { bar.hidden = false; } });
    }, {threshold: 0});
    if (offer) io.observe(offer);
    window.addEventListener('scroll', function(){
      if (!shown && window.scrollY > 300) { shown = true; if (!offer || offer.getBoundingClientRect().top >= window.innerHeight) bar.hidden = false; }
    }, {passive:true});
  })();
  </script>
  <?php else: ?>
  <div class="fd-sticky-bar" id="fd-sticky-bar" hidden>
    <div class="fd-sticky-inner" id="fd-sticky-pre">
      <span class="fd-sticky-biz"><?= ho_h($name) ?></span>
      <a href="#pricing" class="fd-btn fd-btn-secondary fd-sticky-btn">See $199 Offer &rarr;</a>
    </div>
    <div class="fd-sticky-inner" id="fd-sticky-post" hidden>
      <div>
        <strong><?= ho_h($name) ?></strong>
        <span>$199 one-time</span>
      </div>
      <a href="#pricing" class="fd-btn fd-btn-primary fd-sticky-btn">Yes, Build This &rarr;</a>
    </div>
  </div>
  <script>
  (function(){
    var bar    = document.getElementById('fd-sticky-bar');
    var offer  = document.getElementById('pricing');
    var preEl  = document.getElementById('fd-sticky-pre');
    var postEl = document.getElementById('fd-sticky-post');
    if (!bar || !offer) return;
    var shown = false, pricingSeen = false;
    function showBar() {
      bar.hidden = false;
      if (preEl)  preEl.hidden  = pricingSeen;
      if (postEl) postEl.hidden = !pricingSeen;
    }
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting) { pricingSeen = true; bar.hidden = true; shown = false; }
        else if (shown) { showBar(); }
      });
    }, {threshold: 0});
    io.observe(offer);
    window.addEventListener('scroll', function(){
      if (!shown && window.scrollY > 300) { shown = true; if (offer.getBoundingClientRect().top >= window.innerHeight) showBar(); }
    }, {passive: true});
  })();
  </script>
  <?php endif; ?>

  <script>
  (function(){
    if (!('IntersectionObserver' in window)) {
      document.querySelectorAll('.fd-reveal').forEach(function(el){ el.classList.add('fd-visible'); });
      return;
    }
    var obs = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting){ e.target.classList.add('fd-visible'); obs.unobserve(e.target); }
      });
    }, {threshold:0.06, rootMargin:'0px 0px -30px 0px'});
    document.querySelectorAll('.fd-reveal').forEach(function(el){ obs.observe(el); });
  })();
  </script>

<?php endif; ?>
</main>
</body>
</html>
