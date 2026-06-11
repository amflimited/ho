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
    ho_log_preview_visit($pdo, (int)$row['preview_id'], (int)$row['id']);
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
$hi = $ownerFirst !== '' ? $ownerFirst : $name;

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

// ── Trust & emotion data ─────────────────────────────────────────────────────
$compRating  = isset($row['competitor_google_rating']) && $row['competitor_google_rating'] !== null ? (float)$row['competitor_google_rating'] : null;
$compReviews = isset($row['competitor_review_count'])  && $row['competitor_review_count']  !== null ? (int)$row['competitor_review_count']   : null;
$hasYelp     = (bool)($row['has_yelp'] ?? false);
$yelpRating  = isset($row['yelp_rating'])       && $row['yelp_rating']       !== null ? (float)$row['yelp_rating']      : null;
$yelpCount   = isset($row['yelp_review_count']) && $row['yelp_review_count'] !== null ? (int)$row['yelp_review_count']  : null;
$logoQuality = (string)($row['logo_quality'] ?? 'none');

// YYYY-MM → "March 2026", empty-safe
$quoteMonth = function (string $d): string {
    if (!preg_match('/^\d{4}-\d{2}$/', $d)) return '';
    $ts = strtotime($d . '-01');
    return $ts !== false ? date('F Y', $ts) : '';
};
$quote1       = $row ? trim((string)($row['review_quote_1']        ?? '')) : '';
$quote1Author = $row ? trim((string)($row['review_quote_1_author'] ?? '')) : '';
$quote1When   = $row ? $quoteMonth(trim((string)($row['review_quote_1_date'] ?? ''))) : '';
$quote2       = $row ? trim((string)($row['review_quote_2']        ?? '')) : '';
$quote2Author = $row ? trim((string)($row['review_quote_2_author'] ?? '')) : '';
$quote2When   = $row ? $quoteMonth(trim((string)($row['review_quote_2_date'] ?? ''))) : '';

$adamPhotoFile = __DIR__ . '/assets/img/adam.jpg';
$hasAdamPhoto  = is_file($adamPhotoFile);

$email        = $row ? trim((string)($row['email_address'] ?? '')) : '';
$catSlug      = $row ? (string)($row['category_slug'] ?? '') : '';
$seasonalNote = $row ? ho_seasonal_urgency_note($catSlug) : '';
$stakes       = $row ? ho_stakes_estimate($catSlug) : null;
$isEnhancement = $row && isset($row['preview_type']) && $row['preview_type'] === 'enhancement';
$enhancementGaps = ($isEnhancement && $row) ? ho_enhancement_gaps($row) : [];

// Priced package for enhancement leads. Prefer the stored package_items
// (computed at routing time); fall back to a live compute from current gaps.
$packageItems = [];
if ($isEnhancement) {
    if (!empty($row['package_items'])) {
        $packageItems = (array)json_decode((string)$row['package_items'], true);
    }
    if (empty($packageItems) && !empty($enhancementGaps) && isset($pdo)) {
        try { $packageItems = ho_build_package_items($pdo, $enhancementGaps); } catch (Throwable) {}
    }
}
$priceByGap  = [];
foreach ($packageItems as $pi) { $priceByGap[(string)$pi['gap_key']] = (float)($pi['price'] ?? 0); }
$bundleTotal = array_sum(array_column($packageItems, 'price'));
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
    <p class="fd-turn-tag"><?= $ownerFirst !== '' ? 'Hey ' . ho_h($ownerFirst) . ' &mdash; I built you a website.' : 'I built you a website.' ?><?= ($ownerAgeBand === '55plus') ? ' I handle everything &mdash; you don&rsquo;t touch a thing.' : '' ?></p>
    <?php
    // Personalization hook — use specific research data to make the hero feel earned
    $heroDetail = '';
    if ($yearsInBiz >= 5 && $googleCount >= 10) {
        $heroDetail = $yearsInBiz . ' years of work and ' . number_format($googleCount) . ' reviews — completely invisible online. That\'s what this fixes.';
    } elseif ($yearsInBiz >= 5 && !$hasWebsite) {
        $heroDetail = $yearsInBiz . ' years in business with nothing to show online. Time to change that.';
    } elseif ($googleCount >= 15 && !$hasWebsite) {
        $heroDetail = number_format($googleCount) . ' Google reviews and no website. Every one of those reviews is a customer you almost lost before they found you.';
    } elseif ($compHasSite && $compName !== '' && !$hasWebsite) {
        $heroDetail = ho_h($compName) . ' already has a site. You don\'t. That\'s the gap this fixes.';
    }
    ?>
    <?php if ($heroDetail !== ''): ?>
    <p class="fd-turn-detail"><?= $heroDetail ?></p>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($angle !== ''): ?>
      <p class="fd-turn-angle"><?= ho_h($angle) ?></p>
    <?php endif; ?>
    <div class="fd-turn-actions">
      <?php if ($isEnhancement): ?>
      <a href="#what-i-can-add" class="fd-btn fd-btn-primary fd-turn-cta">See What I Found &darr;</a>
      <?php else: ?>
      <a href="#preview" class="fd-btn fd-btn-primary fd-turn-cta">See what it looks like online &darr;</a>
      <?php endif; ?>
    </div>
    <div class="fd-trust-strip">
      <a href="tel:7654434321" class="fd-ts-item">📞 (765) 443-4321</a>
      <?php if (!$isEnhancement): ?>
      <span class="fd-ts-item">⚡ Live in 24 hours — guaranteed</span>
      <span class="fd-ts-item">No monthly fees &middot; you own it forever</span>
      <span class="fd-ts-item">✓ 30-day money-back</span>
      <?php else: ?>
      <span class="fd-ts-item">Quote same day</span>
      <span class="fd-ts-item">Flat price &middot; no monthly fees</span>
      <span class="fd-ts-item">No contracts</span>
      <?php endif; ?>
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
      <p class="fd-why" style="color:var(--fd-ink);font-weight:500;margin-bottom:14px"><?= $ownerFirst !== '' ? ho_h($ownerFirst) . ', you' : 'You' ?> already have a website &mdash; I looked it over. Here&rsquo;s what I noticed.</p>
    <?php endif; ?>

    <?php if ($googleCount > 0): ?>
    <?php
    $ratingNote = '';
    if ($googleRating >= 4.7 && $googleCount >= 20) {
        $ratingNote = 'That&rsquo;s an exceptional rating &mdash; top-tier for a ' . ho_h(strtolower($catName)) . ' business in Indiana.';
    } elseif ($googleRating >= 4.5 && $googleCount >= 10) {
        $ratingNote = 'Well above average. That reputation deserves to be the first thing customers see &mdash; not buried inside a Google listing.';
    } elseif ($googleRating < 4.0 && $googleCount >= 5) {
        $ratingNote = 'Room to grow &mdash; the next customers who find you will shape the next five reviews. A site makes it easy to send happy ones straight to Google.';
    }
    // Cross-platform proof — only when both numbers are worth bragging about
    $crossPlatform = '';
    if ($hasYelp && $yelpRating !== null && $yelpRating >= 4.0 && ($yelpCount ?? 0) >= 3 && $googleRating >= 4.0) {
        $crossPlatform = number_format($googleRating, 1) . '★ on Google, ' . number_format($yelpRating, 1) . '★ on Yelp &mdash; your reputation is real on every platform that matters.';
    }
    ?>
    <div class="fd-rating-block">
      <div class="fd-rating-badge">
        <span class="fd-stars"><?= str_repeat('★', min(5, (int)round($googleRating))) . str_repeat('☆', max(0, 5 - (int)round($googleRating))) ?></span>
        <strong><?= number_format($googleRating, 1) ?></strong>
        <span class="fd-rating-count"><?= number_format($googleCount) ?> Google reviews</span>
      </div>
      <p class="fd-rating-source">Your live rating pulled directly from Google.<?= $crossPlatform !== '' ? ' ' . $crossPlatform : '' ?><?= $ratingNote !== '' ? ' ' . $ratingNote : '' ?></p>
    </div>
    <?php endif; ?>

    <?php // ── Competitor scoreboard — only render a board they're winning ──── ?>
    <?php if ($googleRating > 0 && $googleCount > 0 && $compName !== ''
              && $compRating !== null && $compReviews !== null && $googleRating >= $compRating):
      $scoreNote = ($googleCount < $compReviews)
          ? 'You&rsquo;re winning on quality and losing on visibility. ' . ho_h($compName) . ' isn&rsquo;t better &mdash; they just show up in more places. That&rsquo;s the part I can fix.'
          : 'You&rsquo;re ahead on both. The job now is making sure every single search shows it &mdash; before ' . ho_h($compName) . ' catches up.';
    ?>
    <div class="fd-score">
      <div class="fd-score-col fd-score-you">
        <span class="fd-score-name">You</span>
        <strong><?= number_format($googleRating, 1) ?>★</strong>
        <span class="fd-score-count"><?= number_format($googleCount) ?> reviews</span>
      </div>
      <div class="fd-score-vs" aria-hidden="true">vs</div>
      <div class="fd-score-col">
        <span class="fd-score-name"><?= ho_h($compName) ?></span>
        <strong><?= number_format($compRating, 1) ?>★</strong>
        <span class="fd-score-count"><?= number_format($compReviews) ?> reviews</span>
      </div>
    </div>
    <p class="fd-score-note"><?= $scoreNote ?></p>
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

    <?php if ($logoQuality === 'professional') array_unshift($strengths, 'A clean, professional logo — your branding already looks the part. Most local competitors can\'t say that.'); ?>
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

    <?php // ── Competitive pressure — below strengths/gaps so it reads as context, not accusation ?>
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

    <?php if (!$isEnhancement): ?>
    <p class="fd-why-scroll">I built something to fix that. See it below &darr;</p>
    <?php endif; ?>
  </section>

  <?php if ($quote1 !== ''): ?>
  <!-- ── IN THEIR OWN WORDS — their customers' actual review text ─────────── -->
  <section class="fd-card fd-quote-card fd-reveal">
    <p class="fd-kicker">Your customers already said it best</p>
    <h2>I didn&rsquo;t write this. <?= $quote1Author !== '' ? ho_h($quote1Author) . ' did.' : 'Your customers did.' ?></h2>
    <blockquote class="fd-quote">
      <p>&ldquo;<?= ho_h($quote1) ?>&rdquo;</p>
      <cite>&mdash; <?= $quote1Author !== '' ? ho_h($quote1Author) . ' &middot; ' : '' ?>Google review<?= $quote1When !== '' ? ', ' . ho_h($quote1When) : '' ?></cite>
    </blockquote>
    <?php if ($quote2 !== ''): ?>
    <blockquote class="fd-quote">
      <p>&ldquo;<?= ho_h($quote2) ?>&rdquo;</p>
      <cite>&mdash; <?= $quote2Author !== '' ? ho_h($quote2Author) . ' &middot; ' : '' ?>Google review<?= $quote2When !== '' ? ', ' . ho_h($quote2When) : '' ?></cite>
    </blockquote>
    <?php endif; ?>
    <p class="fd-quote-frame"><?php if ($isEnhancement): ?>
      That&rsquo;s the kind of thing that wins jobs &mdash; and right now a customer has to go digging through Google to find it. Part of what I&rsquo;d fix below is putting words like these where every visitor sees them first.
    <?php else: ?>
      That&rsquo;s the kind of thing that wins jobs &mdash; and right now it&rsquo;s buried in a Google listing nobody scrolls. The site I built puts words like these front and center, where every new customer sees them before they ever call.
    <?php endif; ?></p>
  </section>
  <?php endif; ?>

  <?php if ($isEnhancement): ?>
  <!-- ── WHAT I'D FIX (enhancement only) — contact-first, no checkout ─────── -->
  <?php
  $platform = $hasAngi ? 'Angi' : ($hasThumbtak ? 'Thumbtack' : 'a lead platform');

  // Fix card definitions keyed by gap — copy respects the business's actual data
  $fixDefs = [
      'contact_form' => [
          'icon'  => '📬',
          'title' => 'Add a way to reach you in writing',
          'body'  => $googleCount >= 20
              ? 'You have ' . number_format($googleCount) . ' Google reviews — people clearly find you and trust you. But there\'s no way to reach you in writing. Anyone who found you outside business hours and didn\'t want to call just left. A contact form captures that job instead.'
              : 'Right now anyone who doesn\'t want to call has no way to send you the job. The 11pm searcher, the person who wants to put their request in writing — they leave. A simple form sends those leads straight to your inbox.',
      ],
      'tech_issues' => [
          'icon'  => '🛠️',
          'title' => 'Fix what\'s hurting your Google ranking',
          'body'  => ($notMobile && $noSsl)
              ? 'Your site isn\'t mobile-friendly and has no SSL. Google penalises both, and every major browser warns visitors "Not Secure" before they read a word. Competitors are ranking above you right now because of these two flags — not because they\'re better.'
              : ($notMobile
                  ? 'Your site isn\'t mobile-friendly, and over 70% of local searches happen on a phone. Google actively ranks mobile-ready sites higher, which means competitors appear above you in search every day this isn\'t fixed.'
                  : 'Your site has no SSL certificate. Every major browser flags it as "Not Secure" before a visitor reads a single word. That warning alone sends most people back to search results.'),
      ],
      'paid_leads' => [
          'icon'  => '💸',
          'title' => 'Own the customer — not just a listing on ' . ho_h($platform),
          'body'  => 'You\'re listed on ' . ho_h($platform) . '. Customers who find you there go through ' . ho_h($platform) . ' first — ' . ho_h($platform) . ' controls whether you\'re visible and whether your price competes. A contact form on your own site means they reach you directly, no platform in the way.',
      ],
      'google_business' => [
          'icon'  => '📍',
          'title' => 'Get on Google Maps',
          'body'  => 'You\'re not showing in Maps for ' . ho_h(strtolower($catName)) . ' in ' . ho_h($city) . ' right now. That\'s the search that turns into calls. Getting your Google Business profile verified usually takes one afternoon and puts you in front of people actively looking.',
      ],
      'gbp_photos' => [
          'icon'  => '📸',
          'title' => 'Show your work on Google',
          'body'  => 'Listings with 20+ photos get significantly more clicks than those with a handful. Customers want to see the work before they commit. I\'d help you get real job photos onto your listing so you\'re giving them a reason to choose you.',
      ],
      'stale_reviews' => [
          'icon'  => '⭐',
          'title' => 'Get fresh reviews coming in',
          'body'  => 'Your most recent review is ' . ($reviewAgeMonths !== null ? $reviewAgeMonths . ' months' : 'a while') . ' old. Customers read recency as a signal that you\'re still active and taking jobs. A simple follow-up system keeps new reviews coming without you having to chase them.',
      ],
      'online_booking' => [
          'icon'  => '📅',
          'title' => 'Let people book you without the phone tag',
          'body'  => 'Right now booking means a call back and forth to find a time. Half the people who\'d book never get past that. A simple "pick a time" button on your site lets them lock in a slot while they\'re still thinking about it — and it shows up on your calendar automatically.',
      ],
      'site_outdated' => [
          'icon'  => '✨',
          'title' => 'Bring the look up to date',
          'body'  => 'Your site works, but it reads a few years behind — and customers judge whether you\'re still sharp by how it looks. A clean refresh on what\'s already there makes ' . ho_h($name) . ' feel current and trustworthy without rebuilding from scratch.',
      ],
      'gbp_incomplete' => [
          'icon'  => '📋',
          'title' => 'Finish filling out your Google profile',
          'body'  => 'Your Google Business profile is missing pieces — services, hours, or regular posts. Google rewards complete profiles with better placement, and customers skip listings that look half-finished. Filling it in is quick and pushes you up in the local results.',
      ],
      'no_before_after' => [
          'icon'  => '🔁',
          'title' => 'Show the before & after',
          'body'  => 'For ' . ho_h(strtolower($catName)) . ', the transformation is the sell. People want to see the messy "before" turn into the clean "after" — it\'s the most convincing thing you can show. A handful of real job photos side by side does more than any sales pitch.',
      ],
      'no_gallery' => [
          'icon'  => '🖼️',
          'title' => 'Put your work on display',
          'body'  => 'There\'s nowhere on your site to actually see your work. A simple photo gallery of real jobs lets a stranger picture you doing theirs — that\'s what turns a browser into a call. You\'ve done the work; this just shows it off.',
      ],
      'no_testimonials' => [
          'icon'  => '💬',
          'title' => 'Put your happy customers up front',
          'body'  => ($googleCount >= 10
              ? 'You\'ve clearly got customers who\'d vouch for you — but a visitor on your site never sees a word of it. '
              : 'A few words from past customers is the cheapest trust you can buy. ')
              . 'Pulling real quotes onto your site reassures the person on the fence right when they\'re deciding.',
      ],
      'dead_facebook' => [
          'icon'  => '📘',
          'title' => 'Wake your Facebook back up',
          'body'  => 'Your Facebook page has gone quiet, and a lot of people check it to see if you\'re still active before they reach out. A dormant page reads like a closed business. A light, steady posting rhythm keeps it looking alive — without it becoming another job for you.',
      ],
      'freemail' => [
          'icon'  => '✉️',
          'title' => 'Get an email that matches your business',
          'body'  => 'You\'re running on a personal email address. A professional one' . ($ownDotCom !== '' ? ' like you@' . ho_h($ownDotCom) : ' on your own domain') . ' looks more established the second a customer sees it — and it keeps your business mail separate from your personal inbox.',
      ],
      'no_trust_signals' => [
          'icon'  => '🛡️',
          'title' => 'Show that you\'re licensed & insured',
          'body'  => 'Nowhere on your site does it say you\'re licensed, insured, or bonded — and for someone letting a stranger onto their property, that\'s the first thing they look for. Displaying it plainly removes the biggest hesitation before they call.',
      ],
      'yelp_unclaimed' => [
          'icon'  => '🔖',
          'title' => 'Claim your Yelp listing',
          'body'  => 'There\'s a Yelp listing for you that you don\'t control — which means you can\'t respond to reviews, fix wrong info, or add photos. Claiming it puts you back in charge of what people see when your name comes up there.',
      ],
  ];

  // Build fix items in the priority order gaps were sorted into, attaching the
  // per-gap price. Prefer the stored package order; fall back to gap order.
  $gapOrder = !empty($packageItems) ? array_column($packageItems, 'gap_key') : $enhancementGaps;
  $fixItems = [];
  foreach ($gapOrder as $gk) {
      if (!isset($fixDefs[$gk])) continue;
      $fixItems[] = array_merge($fixDefs[$gk], [
          'gap_key' => $gk,
          'price'   => (float)($priceByGap[$gk] ?? 0),
      ]);
  }
  if (empty($fixItems)) {
      $fixItems[] = ['icon'=>'📬','title'=>'A few things worth tightening up','body'=>'I looked over your site and spotted specific things that quietly cost you jobs. Easiest to walk through them on a quick call.','gap_key'=>'','price'=>0.0];
  }
  $fixItemsTotal = array_sum(array_column($fixItems, 'price'));
  ?>
  <section class="fd-card fd-reveal" id="what-i-can-add">
    <p class="fd-kicker">What I&rsquo;d do</p>
    <h2>Here&rsquo;s what I&rsquo;d fix for <?= ho_h($name) ?>.</h2>
    <p style="font-size:16px;line-height:1.6;margin-bottom:18px">No new website. No moving anything you&rsquo;ve already got. I&rsquo;d work with what&rsquo;s there and tighten up the spots that are costing you jobs &mdash; here&rsquo;s where I&rsquo;d start.</p>

    <div class="fd-app-engine-what">
      <?php foreach ($fixItems as $item): ?>
      <div class="fd-ae-item">
        <span class="fd-ae-icon"><?= ho_h($item['icon']) ?></span>
        <div class="fd-ae-body">
          <strong><?= ho_h($item['title']) ?></strong>
          <p><?= $item['body'] ?></p>
        </div>
        <?php if (!empty($item['price']) && $item['price'] > 0): ?>
        <span class="fd-ae-price">$<?= number_format((float)$item['price']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($fixItemsTotal > 0): ?>
    <div class="fd-ae-total">
      <span class="fd-ae-total-label"><?= count($fixItems) > 1 ? 'Everything above, done' : 'Done' ?></span>
      <span class="fd-ae-total-price">$<?= number_format($fixItemsTotal) ?></span>
    </div>
    <p class="fd-muted" style="margin-top:8px;text-align:center">Flat, one-time. No monthly fees, no contract. Pick all of it or just the part you want.</p>
    <?php endif; ?>
  </section>

  <?php if ($stakes !== null): ?>
  <!-- ── WHAT THIS COSTS YOU (enhancement) ─────────────────────────────── -->
  <section class="fd-card fd-stakes fd-reveal">
    <p class="fd-kicker">What this actually costs you</p>
    <h2>Run the math with me.</h2>
    <p>The average <?= ho_h(strtolower($catName)) ?> job runs around
       <span class="fd-stakes-num">$<?= number_format($stakes['ticket']) ?></span>.
       The gaps above lose jobs the same quiet way &mdash; nobody tells you they almost called.
       Even at just <?= $stakes['jobs_per_month'] ?> missed job<?= $stakes['jobs_per_month'] > 1 ? 's' : '' ?> a month,
       that&rsquo;s about <span class="fd-stakes-num">$<?= number_format($stakes['annual']) ?> a year</span>.</p>
    <p class="fd-stakes-honest">That&rsquo;s an estimate, not a promise &mdash; your real number could be lower or higher. But it isn&rsquo;t zero.<?= $fixItemsTotal > 0 ? ' Everything above, fixed for good, is $' . number_format($fixItemsTotal) . ' &mdash; once.' : '' ?></p>
  </section>
  <?php endif; ?>

  <!-- ── CONTACT CTA (enhancement only) ────────────────────────────────── -->
  <section class="fd-card fd-offer fd-reveal" id="pricing">
    <p class="fd-kicker">Let&rsquo;s talk it through</p>
    <?php if ($fixItemsTotal > 0): ?>
    <h2><?= count($fixItems) > 1 ? 'All of it' : 'This' ?> for $<?= number_format($fixItemsTotal) ?> &mdash; flat, one-time.</h2>
    <p style="font-size:16px;line-height:1.6">No obligation, no pressure, no monthly anything. Want just one piece? That&rsquo;s fine too &mdash; each line above stands on its own. Call or send a note and I&rsquo;ll get started.</p>
    <?php else: ?>
    <h2>Tell me what you want fixed &mdash; I&rsquo;ll quote it same day.</h2>
    <p style="font-size:16px;line-height:1.6">No obligation, no pressure. Most of these are a flat one-time fix &mdash; no monthly anything. Call me or send a note and I&rsquo;ll tell you exactly what it takes and what it costs.</p>
    <?php endif; ?>
    <div style="display:flex;flex-direction:column;gap:10px;margin-top:20px">
      <a class="fd-btn fd-btn-primary fd-checkout-main-btn" href="tel:7654434321">📞 Call me &mdash; (765) 443-4321</a>
      <a class="fd-btn fd-btn-secondary" href="mailto:adam@hoosieronline.com?subject=<?= rawurlencode('Website help for ' . $name) ?>&body=<?= rawurlencode("Hi Adam — I saw the page you put together for " . $name . ". I'd like to talk about:") ?>">Email me instead &rarr;</a>
    </div>
    <p class="fd-muted" style="margin-top:16px">Adam Ferree &middot; Hoosier Online &middot; New Castle, Indiana &mdash; you talk to me, not a call centre.</p>
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

  <?php
    // Honest demo gallery: these are template designs with a placeholder
    // business on them. The lead picks the style they like; Adam builds
    // their real site in that style. Choice is recorded at checkout.
    $showKey     = isset($available[$templateKey]) ? $templateKey : array_key_first($available);
    $templateKey = $showKey; // keep the checkout hidden input in sync with what's shown
  ?>
  <section class="fd-card fd-reveal">
    <p class="fd-kicker">Design styles &mdash; you pick</p>
    <h2 class="fd-design-title"><?= $hasWebsite ? 'Pick the style your new site gets built in.' : 'Pick the style. I build ' . ho_h($name) . '&rsquo;s site in it.' ?></h2>
    <p class="fd-design-sub">These are demo designs from my shop &mdash; the business on them isn&rsquo;t real. Yours gets built in the style you pick, with your name, your number, your services<?= $googleCount >= 3 ? ', your real reviews' : '' ?>. Tap through and see which one feels like <?= ho_h($name) ?>.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 14px">
      <span style="font-size:12px;font-weight:700;color:var(--fd-green);background:rgba(47,94,54,.1);border:1px solid rgba(47,94,54,.2);padding:4px 10px;border-radius:20px">⚡ Live in 24 hours</span>
      <span style="font-size:12px;font-weight:700;color:#7a4800;background:rgba(184,112,32,.1);border:1px solid rgba(184,112,32,.2);padding:4px 10px;border-radius:20px"><?= count($available) ?> style<?= count($available) !== 1 ? 's' : '' ?> &mdash; your call</span>
    </div>

    <?php if (count($available) > 1): ?>
    <div class="fd-tpl-picker">
      <?php foreach ($available as $k => $opt): ?>
      <button type="button" class="fd-tpl-tab<?= $k === $showKey ? ' fd-tpl-tab--active' : '' ?>"
              data-tpl="<?= ho_h($k) ?>" onclick="fdPickTpl(this)">
        <span class="fd-tpl-dot" style="background:<?= ho_h((string)$opt['color']) ?>"></span><?= ho_h((string)$opt['label']) ?>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="fd-phone-stage">
    <div class="fd-phone-frame">
      <div class="fd-phone-screen" id="fd-phone-screen">
        <?php foreach ($available as $k => $opt):
          ob_start();
          include $opt['file'];
          $paneHtml = ob_get_clean();
          // Preview images are 2MB+ each — lazy-load so hidden styles cost nothing
          $paneHtml = str_replace('<img ', '<img loading="lazy" decoding="async" ', $paneHtml);
        ?>
        <div class="fd-tpl-pane" data-tpl-pane="<?= ho_h($k) ?>"<?= $k === $showKey ? '' : ' hidden' ?>><?= $paneHtml ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    </div><!-- /.fd-phone-stage -->

    <p class="fd-excl-note">Whichever style you pick, I customize it for <?= ho_h($name) ?> &mdash; and I won&rsquo;t sell the same look to another <?= ho_h(strtolower($catName)) ?> company<?= $city !== '' ? ' in ' . ho_h($city) : '' ?>.</p>
  </section>

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
      <?php if ($hasAdamPhoto): ?>
      <img class="fd-trust-avatar fd-trust-photo" src="/assets/img/adam.jpg?v=<?= filemtime($adamPhotoFile) ?>" alt="Adam Ferree" width="52" height="52">
      <?php else: ?>
      <div class="fd-trust-avatar" aria-hidden="true">AF</div>
      <?php endif; ?>
      <div>
        <h2>Adam Ferree</h2>
        <p class="fd-trust-location">New Castle, Indiana &mdash; not a call centre, not an agency</p>
      </div>
    </div>
    <?php
    // Build a list of specific facts to prove the research was real (both tracks)
    $adamKnows = [];
    if ($yearsInBiz >= 3) $adamKnows[] = 'you&rsquo;ve been running ' . ho_h($name) . ' for ' . $yearsInBiz . ' years';
    if ($googleCount >= 5) $adamKnows[] = 'you have ' . number_format($googleCount) . ' Google reviews';
    if ($compHasSite && $compName !== '') $adamKnows[] = ho_h($compName) . ' already has a site and is outranking you';
    if (!$hasWebsite && $phone !== '') $adamKnows[] = 'customers can only reach you by calling ' . ho_h($telDisplay);

    // Handwritten P.S. — strongest single fact, no phone numbers (iOS rule)
    $psFact = '';
    if ($quote1Author !== '')   $psFact = 'I read what ' . ho_h($quote1Author) . ' wrote about you before I ever wrote a word of this page.';
    elseif ($yearsInBiz >= 3)   $psFact = $yearsInBiz . ' years of work deserves better than being invisible online.';
    elseif ($googleCount >= 5)  $psFact = 'Anyone who earns ' . number_format($googleCount) . ' reviews is doing the work right.';
    elseif ($compName !== '')   $psFact = 'I checked what ' . ho_h($compName) . ' is doing too. You should be ahead of them.';
    ?>
    <?php if (!$isEnhancement): ?>
    <p>I build websites for Indiana service businesses &mdash; that&rsquo;s the whole business. <?= $ownerFirst !== '' ? ho_h($ownerFirst) . ', before' : 'Before' ?> I reached out, I already knew: <?php if (!empty($adamKnows)): ?><?= implode('; ', $adamKnows) ?>. <?php endif; ?>I built this preview before sending a single message. I only do that when I think it&rsquo;s worth it.</p>
    <p style="margin-top:10px">When you say yes, you&rsquo;re not entering a queue &mdash; I start the same day. <?= ho_h($name) ?>&rsquo;s site is live within 24 hours. That&rsquo;s a guarantee, not a target.</p>
    <?php else: ?>
    <p>I build websites for Indiana service businesses. That&rsquo;s the whole business. I looked at your <?= $hasWebsite && $websiteUrl !== '' ? 'site' : ($hasGoogle ? 'Google listing' : ($hasFacebook ? 'Facebook page' : 'online presence')) ?>, noted what could be better, and put this together before reaching out. I only send these when I think the business is worth it.</p>
    <p style="margin-top:10px">No queue, no agency runaround. You tell me what you want fixed and I&rsquo;ll tell you exactly what it takes &mdash; same day. Most of it&rsquo;s a flat one-time fix.</p>
    <?php endif; ?>
    <ul class="fd-trust-signals">
      <li>Indiana-based &mdash; you can call me directly</li>
      <li>Flat price, no contract, no monthly fees — ever</li>
      <?php if ($isEnhancement): ?>
      <li>I work with the site you&rsquo;ve got &mdash; no rebuild, no disruption</li>
      <?php else: ?>
      <li>30-day money-back if you&rsquo;re not happy after launch</li>
      <?php endif; ?>
      <li>Every site researched and worked on personally — not outsourced</li>
    </ul>
    <div class="fd-trust-contact">
      <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a>
      <?php if ($adamPhone !== ''): ?>
        <span class="fd-trust-sep">&middot;</span>
        <a href="tel:<?= ho_h(preg_replace('/\D/', '', $adamPhone)) ?>"><?= ho_h($adamPhone) ?></a>
      <?php endif; ?>
    </div>
    <?php if ($psFact !== ''): ?>
    <p class="fd-trust-ps">P.S. <?= $ownerFirst !== '' ? ho_h($ownerFirst) . ' &mdash; ' : '' ?><?= $psFact ?> This page wasn&rsquo;t mass-mailed. It only exists for <?= ho_h($name) ?>.</p>
    <?php endif; ?>
  </section>

  <?php if (!$isEnhancement): ?>
  <!-- ── WHAT YOU GET (modules) ───────────────────────────────────────────── -->
  <?php
  $servicesClean = array_values(array_filter(array_map('trim', array_map('strval', $services))));
  $servicesList = !empty($servicesClean) ? implode(', ', array_slice($servicesClean, 0, 5)) : '';
  ?>
  <section class="fd-section fd-reveal" id="services">
    <div class="fd-section-head">
      <p class="fd-kicker">What We Build</p>
      <h2>Every page. What it does. Why it matters.</h2>
      <p class="fd-design-sub" style="margin-top:-4px;margin-bottom:8px">Built in the style you pick, filled with <?= ho_h($name) ?>&rsquo;s real details &mdash; and you own it the day it goes live.</p>
    </div>
    <div class="fd-module-list">
      <?php foreach ($modules as $i => $m): ?>
        <div class="fd-module fd-reveal" style="--reveal-delay:<?= $i * 80 ?>ms">
          <span class="fd-module-icon"><?= ho_h($m['icon'] ?? '◆') ?></span>
          <div>
            <strong><?= ho_h($m['title']) ?></strong>
            <?php if ($m['title'] === 'Your Services' && $servicesList !== ''): ?>
            <p><?= ho_h($servicesList) ?> &mdash; every service laid out clearly with what customers should expect. When they can picture the job before they call, they call already sold.</p>
            <?php elseif ($m['title'] === 'Your Front Page' && $name !== ''): ?>
            <p><?= ho_h($name) ?><?= $city !== '' ? ' &middot; ' . ho_h($catName) . ' &middot; ' . ho_h($city) . ', IN' : '' ?> &mdash; visible the moment someone lands. One clear call-to-action. Most customers decide in under 5 seconds. This page wins those 5 seconds.</p>
            <?php else: ?>
            <p><?= ho_h($m['desc']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <?php if ($stakes !== null): ?>
  <!-- ── WHAT THIS COSTS YOU (site-build) ─────────────────────────────────── -->
  <section class="fd-card fd-stakes fd-reveal">
    <p class="fd-kicker">What this actually costs you</p>
    <h2>Run the math with me.</h2>
    <p>The average <?= ho_h(strtolower($catName)) ?> job runs around
       <span class="fd-stakes-num">$<?= number_format($stakes['ticket']) ?></span>.
       If being invisible online costs you just <?= $stakes['jobs_per_month'] ?> job<?= $stakes['jobs_per_month'] > 1 ? 's' : '' ?> a month
       &mdash; the 11pm searcher, the person who called whoever Google showed first &mdash;
       that&rsquo;s about <span class="fd-stakes-num">$<?= number_format($stakes['annual']) ?> a year</span> walking past you.</p>
    <p class="fd-stakes-honest">That&rsquo;s an estimate, not a promise &mdash; your real number could be lower or higher. But it isn&rsquo;t zero. And the fix is $199, once.</p>
  </section>
  <?php endif; ?>

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

    <div class="fd-price-compare">
      <div class="fd-pc-row fd-pc-other">
        <span class="fd-pc-name">Wix (3 yrs)</span>
        <span class="fd-pc-cost"><s>$576+</s></span>
        <span class="fd-pc-note">monthly fees forever, you never own it</span>
      </div>
      <div class="fd-pc-row fd-pc-other">
        <span class="fd-pc-name">GoDaddy (3 yrs)</span>
        <span class="fd-pc-cost"><s>$420+</s></span>
        <span class="fd-pc-note">monthly fees forever, you never own it</span>
      </div>
      <div class="fd-pc-row fd-pc-ours">
        <span class="fd-pc-name">Hoosier Online</span>
        <span class="fd-pc-cost">$199</span>
        <span class="fd-pc-note">once &mdash; yours forever, no renewal, no platform</span>
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
      <li>✓&ensp;Click-to-call button &amp; contact form &mdash; customers reach you without friction</li>
      <li>✓&ensp;Mobile-optimized &amp; SSL secured from day one</li>
      <?php if ($googleCount > 0): ?>
      <li>✓&ensp;Your <?= number_format($googleCount) ?> Google review<?= $googleCount !== 1 ? 's' : '' ?> pulled in automatically &mdash; front and center, not buried in a listing</li>
      <?php else: ?>
      <li>✓&ensp;Your Google reviews pulled in automatically as they come in</li>
      <?php endif; ?>
      <li>✓&ensp;<?= $hasExistingDomain ? ho_h($ownDotCom) . ' connected — no new domain needed' : 'Your .com domain registered &amp; renewals handled — free' ?></li>
      <li>✓&ensp;Built exclusively for <?= ho_h($name) ?> &mdash; this design is never used for another <?= ho_h(strtolower($catName)) ?> in <?= ho_h($city) ?></li>
    </ul>

    <div class="fd-live-guarantee">
      <strong>⚡ Live by <?= date('l, F j') === date('l, F j', strtotime('tomorrow')) ? date('F j', strtotime('tomorrow')) : date('F j', strtotime('tomorrow')) ?> &mdash; or you pay nothing.</strong>
      Most web agencies quote 4&ndash;8 weeks. <?= ho_h($name) ?>&rsquo;s site is live within 24 hours of checkout &mdash; real, live, at your web address. That&rsquo;s the actual contract: not a target, not a goal. If it&rsquo;s not live in time, I refund every cent.
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
      <a href="tel:7654434321" class="fd-btn fd-btn-primary fd-sticky-btn">📞 Call Adam</a>
    </div>
  </div>
  <script>
  (function(){
    var bar = document.getElementById('fd-sticky-bar');
    var offer = document.getElementById('pricing');
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
  // Style picker: swap visible template pane, mark active tab,
  // carry the chosen style into checkout via the hidden input.
  function fdPickTpl(btn) {
    var key = btn.getAttribute('data-tpl');
    document.querySelectorAll('.fd-tpl-tab').forEach(function(b){
      b.classList.toggle('fd-tpl-tab--active', b === btn);
    });
    document.querySelectorAll('[data-tpl-pane]').forEach(function(p){
      p.hidden = p.getAttribute('data-tpl-pane') !== key;
    });
    var h = document.getElementById('fd-h-template');
    if (h) h.value = key;
  }
  </script>

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
