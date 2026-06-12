<?php
declare(strict_types=1);
/**
 * Live Site Renderer — site.php
 *
 * GET  /site/{slug}           Full page
 * GET  /site/{slug}?skin=     Override skin
 * GET  /site/{slug}?embed=1   Stripped iframe (inside go.php phone frame)
 * GET  /site/{slug}?fresh=1   Recompose site JSON (admin only)
 *
 * POST action=request_quote   Lead capture (same semantics as go.php:27-45)
 */

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/ho-model.php';
require_once __DIR__ . '/admin-auth.php';

// ── Slug ────────────────────────────────────────────────────────────────────
$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '' && isset($_SERVER['REQUEST_URI'])) {
    // support ?slug= fallback
    $slug = trim((string)($_GET['slug'] ?? ''));
}
if ($slug === '') {
    http_response_code(404);
    echo '<!doctype html><html><body>Not found.</body></html>';
    exit;
}

// ── DB ──────────────────────────────────────────────────────────────────────
try {
    $pdo = ho_db();
} catch (Throwable) {
    http_response_code(503);
    echo '<!doctype html><html><body>Temporarily unavailable.</body></html>';
    exit;
}

// ── Row (same join as ho_get_preview_by_slug + research fields) ─────────────
$s = $pdo->prepare("
    SELECT b.id, b.business_name, b.location_city, b.owner_first_name,
           b.email_address, b.phone_number, b.website_url, b.pipeline_status,
           c.name AS category_name, c.slug AS category_slug,
           p.id AS preview_id, p.preview_slug, p.preview_type,
           p.selected_template,
           r.google_review_count, r.google_rating,
           r.review_quote_1, r.review_quote_1_author, r.review_quote_1_date,
           r.review_quote_2, r.review_quote_2_author, r.review_quote_2_date,
           r.years_in_business, r.service_area_text,
           r.services_display, r.typical_services,
           r.strengths
    FROM businesses b
    JOIN categories c ON c.id = b.category_id
    JOIN previews   p ON p.business_id = b.id AND p.preview_status = 'ready' AND p.preview_slug = ?
    LEFT JOIN research_records r ON r.business_id = b.id
    LIMIT 1
");
$s->execute([$slug]);
$row = $s->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head>'
       . '<body style="font-family:sans-serif;padding:3rem;text-align:center"><h1>Page not found.</h1></body></html>';
    exit;
}

$bizId   = (int)$row['id'];
$name    = (string)$row['business_name'];
$city    = (string)($row['location_city'] ?? '');
$catSlug = (string)($row['category_slug'] ?? '');
$catName = (string)($row['category_name'] ?? '');
$phone   = (string)($row['phone_number']  ?? '');
$email   = (string)($row['email_address'] ?? '');
$status  = (string)($row['pipeline_status'] ?? '');

// ── Lead capture (POST, same as go.php:27-45) ────────────────────────────────
$captureState = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_quote') {
    $cTrap  = trim((string)($_POST['company'] ?? '')); // honeypot
    $cName  = trim((string)($_POST['c_name']  ?? ''));
    $cPhone = trim((string)($_POST['c_phone'] ?? ''));
    $cEmail = trim((string)($_POST['c_email'] ?? ''));
    $cJob   = trim((string)($_POST['c_job']   ?? ''));
    if (!filter_var($cEmail, FILTER_VALIDATE_EMAIL)) $cEmail = '';
    if ($cTrap !== '') {
        $captureState = 'ok';
    } elseif ($cName === '' || ($cPhone === '' && $cEmail === '')) {
        $captureState = 'err';
    } else {
        $capId = ho_capture_lead($pdo, $bizId, (int)$row['preview_id'], $cName, $cPhone, $cEmail, $cJob);
        if ($capId !== null) {
            try { ho_forward_captured_lead($pdo, $capId); } catch (Throwable) {}
        }
        $captureState = 'ok';
    }
}

// ── Mode: preview vs delivered ───────────────────────────────────────────────
$isDelivered = false;
if ($status === 'converted') {
    $isDelivered = true;
} else {
    try {
        $os = $pdo->prepare("SELECT id FROM orders WHERE business_id = ? LIMIT 1");
        $os->execute([$bizId]);
        if ($os->fetch()) $isDelivered = true;
    } catch (Throwable) {}
}

// ── Embed / fresh flags ──────────────────────────────────────────────────────
$embed = ((string)($_GET['embed'] ?? '')) === '1';
$fresh = ((string)($_GET['fresh'] ?? '')) === '1' && ho_admin_is_logged_in();

// ── Site content ─────────────────────────────────────────────────────────────
ho_llm_boot($pdo);
try {
    $site = ho_site_ensure($pdo, $row, $fresh);
} catch (Throwable) {
    $site = ho_site_fallback_content($row);
}

// ── Skin ─────────────────────────────────────────────────────────────────────
$skinRequest = trim((string)($_GET['skin'] ?? ''));
$skin        = ho_site_resolve_skin($skinRequest !== '' ? $skinRequest : null, $row);
$skins       = ho_site_skins();
$skinData    = $skins[$skin] ?? $skins[ho_site_default_skin($catSlug)];

// ── Visit logging (skip when embed to avoid double-count) ───────────────────
if (!$embed) {
    try {
        $li = $pdo->prepare("INSERT INTO preview_visits (preview_id, visited_at, ip_hash) VALUES (?, NOW(), ?)");
        $li->execute([(int)$row['preview_id'], hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''))]);
    } catch (Throwable) {}
}

// ── Helper ───────────────────────────────────────────────────────────────────
function lsH(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function ls_star_html(float $rating): string {
    $full = min(5, max(0, (int)round($rating)));
    $empty = 5 - $full;
    return str_repeat('★', $full) . str_repeat('☆', $empty);
}

// ── Data shortcuts ────────────────────────────────────────────────────────────
$googleCount  = (int)($row['google_review_count'] ?? 0);
$googleRating = (float)($row['google_rating']     ?? 0);
$yearsInBiz   = (int)($row['years_in_business']   ?? 0);

$quoteMonth = function (string $d): string {
    if (!preg_match('/^\d{4}-\d{2}$/', $d)) return '';
    $ts = strtotime($d . '-01');
    return $ts !== false ? date('F Y', $ts) : '';
};
$quote1       = trim((string)($row['review_quote_1']        ?? ''));
$quote1Author = trim((string)($row['review_quote_1_author'] ?? ''));
$quote1When   = $quoteMonth(trim((string)($row['review_quote_1_date'] ?? '')));
$quote2       = trim((string)($row['review_quote_2']        ?? ''));
$quote2Author = trim((string)($row['review_quote_2_author'] ?? ''));
$quote2When   = $quoteMonth(trim((string)($row['review_quote_2_date'] ?? '')));
$hasReviews   = $quote1 !== '';

$siteBase   = rtrim(trim(ho_get_setting($pdo, 'site_base')), '/');
if ($siteBase === '') $siteBase = 'https://hoosieronline.com';
$goUrl      = $siteBase . '/go/' . $slug;

$meta   = (array)($site['meta']    ?? []);
$hero   = (array)($site['hero']    ?? []);
$about  = (array)($site['about']   ?? []);
$faqs   = (array)($site['faq']     ?? []);
$area   = (array)($site['service_area'] ?? []);
$cta    = (array)($site['cta']     ?? []);
$svcs   = (array)($site['services'] ?? []);

$pageTitle = (string)($meta['title'] ?? ($name . ' — ' . $catName . ' in ' . ($city ?: 'Indiana')));
$pageDesc  = (string)($meta['description'] ?? (($city ?: 'Indiana') . ' ' . strtolower($catName) . ' — ' . $name));

$heroHeadline = (string)($hero['headline'] ?? $name);
$heroSub      = (string)($hero['sub']      ?? ($catName . ' serving ' . ($city ?: 'Indiana')));
$heroCta      = (string)($hero['cta_label'] ?? 'Get a Free Quote');

$aboutHeading = (string)($about['heading'] ?? ('About ' . $name));
$aboutBody    = (string)($about['body']    ?? '');
$aboutYears   = (string)($about['years_line'] ?? '');

$areaHeading  = (string)($area['heading']  ?? 'Service Area');
$areaBlurb    = (string)($area['blurb']    ?? ($city !== '' ? 'Proudly serving ' . $city . ' and surrounding communities in Indiana.' : 'Serving Indiana communities.'));

$ctaHeadline  = (string)($cta['headline'] ?? ('Ready to get started?'));
$ctaSub       = (string)($cta['sub']      ?? 'Contact us today for a free quote.');

// ── Page type for data-type attr ─────────────────────────────────────────────
$fontType = ($skinData['font_type'] ?? 'sans');

// ── JSON-LD ───────────────────────────────────────────────────────────────────
$ld = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $name];
if ($city !== '') $ld['address'] = ['@type' => 'PostalAddress', 'addressLocality' => $city, 'addressRegion' => 'IN', 'addressCountry' => 'US'];
if ($phone !== '') $ld['telephone'] = $phone;
if ($email !== '') $ld['email'] = $email;
if ($catName !== '') $ld['description'] = $catName . ' serving ' . ($city ?: 'Indiana');
if ($googleRating > 0 && $googleCount > 0) {
    $ld['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => round($googleRating, 1), 'reviewCount' => $googleCount, 'bestRating' => 5, 'worstRating' => 1];
}
$ldJson = json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

// ── Trust bar items ───────────────────────────────────────────────────────────
$trustItems = [];
if ($googleRating > 0 && $googleCount > 0) {
    $trustItems[] = '<span class="ls-trust-stars">' . ls_star_html($googleRating) . '</span> ' . number_format($googleRating, 1) . ' on Google (' . $googleCount . ' reviews)';
}
if ($yearsInBiz > 0) {
    $trustItems[] = $yearsInBiz . '+ years in business';
}
$trustItems[] = 'Locally owned &amp; operated';
?>
<!doctype html>
<html lang="en" data-skin="<?= lsH($skin) ?>" data-type="<?= lsH($fontType) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= lsH($pageTitle) ?></title>
<?php if (!$isDelivered || $embed): ?>
  <meta name="robots" content="noindex,nofollow">
<?php else: ?>
  <meta name="description" content="<?= lsH($pageDesc) ?>">
<?php endif; ?>
  <link rel="stylesheet" href="/assets/css/live-site.css?v=<?= filemtime(__DIR__ . '/assets/css/live-site.css') ?>">
  <script type="application/ld+json"><?= $ldJson ?></script>
</head>
<body class="<?= $embed ? 'ls-embed' : '' ?>">

<?php /* ── Preview ribbon (hidden in embed and delivered) ── */ ?>
<?php if (!$isDelivered && !$embed): ?>
<div class="ls-ribbon" role="banner">
  This is <?= lsH($name) ?>'s real website — built &amp; ready.
  <a href="<?= lsH($goUrl) ?>#pricing">Claim it&nbsp;→</a>
</div>
<?php endif; ?>

<?php /* ── Sticky header ── */ ?>
<header class="ls-header" role="banner">
  <span class="ls-header-name"><?= lsH($name) ?></span>
  <?php if ($phone !== ''): ?>
  <a class="ls-header-tel" href="tel:<?= lsH(preg_replace('/[^+\d]/', '', $phone)) ?>"><?= lsH($phone) ?></a>
  <?php endif; ?>
</header>

<?php /* ── Hero ── */ ?>
<section class="ls-hero" aria-label="Hero">
  <h1 class="ls-hero-headline"><?= lsH($heroHeadline) ?></h1>
  <p class="ls-hero-sub"><?= lsH($heroSub) ?></p>
  <a class="ls-hero-cta" href="#contact"><?= lsH($heroCta) ?></a>
</section>

<?php /* ── Trust bar ── */ ?>
<?php if (!empty($trustItems)): ?>
<div class="ls-trust" aria-label="Trust indicators">
  <div class="ls-trust-inner">
    <?php foreach ($trustItems as $ti): ?>
    <span class="ls-trust-item"><?= $ti ?></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php /* ── Services ── */ ?>
<?php if (!empty($svcs)): ?>
<section class="ls-section-alt" aria-labelledby="svc-heading">
  <div class="ls-section-inner">
    <h2 class="ls-section-title" id="svc-heading">Our Services</h2>
    <div class="ls-services-grid">
      <?php foreach ($svcs as $svc):
        $svcName = (string)($svc['name'] ?? '');
        $svcDesc = (string)($svc['desc'] ?? '');
        if ($svcName === '') continue;
      ?>
      <div class="ls-service-card">
        <div class="ls-service-name"><?= lsH($svcName) ?></div>
        <?php if ($svcDesc !== ''): ?>
        <div class="ls-service-desc"><?= lsH($svcDesc) ?></div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── About ── */ ?>
<?php if ($aboutBody !== ''): ?>
<section class="ls-section" aria-labelledby="about-heading">
  <h2 class="ls-section-title" id="about-heading"><?= lsH($aboutHeading) ?></h2>
  <p class="ls-about-body"><?= lsH($aboutBody) ?></p>
  <?php if ($aboutYears !== ''): ?>
  <span class="ls-about-years"><?= lsH($aboutYears) ?></span>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php /* ── Reviews (real DB quotes only — AI never touches these) ── */ ?>
<?php if ($hasReviews): ?>
<section class="ls-section-alt" aria-labelledby="rev-heading">
  <div class="ls-section-inner">
    <h2 class="ls-section-title" id="rev-heading">What Customers Say</h2>
    <div class="ls-reviews-grid">
      <div class="ls-review-card">
        <div class="ls-review-stars" aria-label="5 stars">★★★★★</div>
        <blockquote class="ls-review-quote">&ldquo;<?= lsH($quote1) ?>&rdquo;</blockquote>
        <cite class="ls-review-author">
          — <?= lsH($quote1Author ?: 'Google Reviewer') ?>
          <?= $quote1When !== '' ? '<span style="font-weight:400;opacity:.7"> · ' . lsH($quote1When) . '</span>' : '' ?>
        </cite>
      </div>
      <?php if ($quote2 !== ''): ?>
      <div class="ls-review-card">
        <div class="ls-review-stars" aria-label="5 stars">★★★★★</div>
        <blockquote class="ls-review-quote">&ldquo;<?= lsH($quote2) ?>&rdquo;</blockquote>
        <cite class="ls-review-author">
          — <?= lsH($quote2Author ?: 'Google Reviewer') ?>
          <?= $quote2When !== '' ? '<span style="font-weight:400;opacity:.7"> · ' . lsH($quote2When) . '</span>' : '' ?>
        </cite>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php /* ── FAQ ── */ ?>
<?php if (!empty($faqs)): ?>
<section class="ls-section" aria-labelledby="faq-heading">
  <h2 class="ls-section-title" id="faq-heading">Frequently Asked Questions</h2>
  <div class="ls-faq-list">
    <?php foreach ($faqs as $faq):
      $fq = (string)($faq['q'] ?? '');
      $fa = (string)($faq['a'] ?? '');
      if ($fq === '' || $fa === '') continue;
    ?>
    <details class="ls-faq-item">
      <summary><?= lsH($fq) ?></summary>
      <div class="ls-faq-answer"><?= lsH($fa) ?></div>
    </details>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php /* ── Service area ── */ ?>
<section class="ls-section-alt" aria-labelledby="area-heading">
  <div class="ls-section-inner">
    <h2 class="ls-section-title" id="area-heading"><?= lsH($areaHeading) ?></h2>
    <p class="ls-area-blurb"><?= lsH($areaBlurb) ?></p>
  </div>
</section>

<?php /* ── Contact form ── */ ?>
<section class="ls-contact-wrap" id="contact" aria-labelledby="contact-heading">
  <div class="ls-contact-inner">
    <h2 class="ls-contact-title" id="contact-heading"><?= lsH($ctaHeadline) ?></h2>
    <p class="ls-contact-sub"><?= lsH($ctaSub) ?></p>
    <?php if ($captureState === 'ok'): ?>
    <div class="ls-form-success" style="display:block">
      Thanks! We'll be in touch shortly.
    </div>
    <?php elseif ($captureState === 'err'): ?>
    <p class="ls-form-msg" style="color:#ffd080">Please fill in your name and a way to reach you.</p>
    <?php endif; ?>
    <?php if ($captureState !== 'ok'): ?>
    <form class="ls-form" method="post" action="#contact">
      <input type="hidden" name="action" value="request_quote">
      <div class="ls-form-row">
        <input type="text" name="c_name" placeholder="Your name" required autocomplete="name">
        <input type="tel" name="c_phone" placeholder="Phone number" autocomplete="tel">
      </div>
      <input type="email" name="c_email" placeholder="Email address (optional)" autocomplete="email">
      <input type="text" name="c_job" placeholder="What can we help with?">
      <input class="ls-form-hp" type="text" name="company" tabindex="-1" autocomplete="off">
      <button class="ls-form-submit" type="submit">Send Message</button>
    </form>
    <?php endif; ?>
  </div>
</section>

<?php /* ── HO buy-CTA (preview only, not embed) ── */ ?>
<?php if (!$isDelivered && !$embed): ?>
<div class="ls-buy-cta">
  <p class="ls-buy-cta-text">
    Love your new site? <strong>Claim it today</strong> and go live this week.
  </p>
  <a class="ls-buy-cta-btn" href="<?= lsH($goUrl) ?>#pricing">See Pricing&nbsp;→</a>
</div>
<?php endif; ?>

<?php /* ── Footer ── */ ?>
<footer class="ls-footer">
  <div><?= lsH($name) ?><?= $city !== '' ? ' · ' . lsH($city) . ', IN' : '' ?>
  <?php if ($phone !== ''): ?>
    · <a href="tel:<?= lsH(preg_replace('/[^+\d]/', '', $phone)) ?>"><?= lsH($phone) ?></a>
  <?php endif; ?>
  </div>
  <?php if ($isDelivered): ?>
  <div class="ls-footer-credit">Site by <a href="https://hoosieronline.com" rel="noopener">Hoosier Online</a></div>
  <?php endif; ?>
</footer>

</body>
</html>
