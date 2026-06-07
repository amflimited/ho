<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
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
$name     = $row ? (string)$row['business_name']        : '';
$city     = $row ? (string)$row['location_city']        : '';
$catName  = $row ? (string)$row['category_name']        : '';
$opp      = $row ? (string)$row['opportunity_statement'] : '';
$subhead  = $row ? (string)$row['subheadline']          : '';
$package  = $row ? (string)$row['package_recommendation'] : 'standard';
$phone    = $row ? (string)($row['phone_number'] ?? '') : '';

$services = $row ? (array)json_decode((string)($row['services_display'] ?? '[]'), true) : [];
if (empty($services) && $row) {
    $services = (array)json_decode((string)($row['typical_services'] ?? '[]'), true);
}
$gaps     = $row ? (array)json_decode((string)($row['gaps'] ?? '[]'), true) : [];

$googleCount  = (int)($row['google_review_count'] ?? 0);
$googleRating = (float)($row['google_rating']     ?? 0);
$hasGoogle    = (bool)($row['has_google_business'] ?? false);

$serviceArea  = (string)($row['service_area_text'] ?? ($city !== '' ? $city . ' & surrounding area' : 'Indiana'));
$isManaged    = $package === 'managed';

$catSlug      = $row ? (string)($row['category_slug'] ?? '') : '';
$design       = $row ? ho_design_direction($catSlug) : ['key' => 'default', 'name' => '', 'feel' => ''];
$subdomain    = $row ? ho_suggest_subdomain($name) : '';
$modules      = ho_product_modules();
$features     = ho_product_features();
$angle        = $row ? ho_sales_angle($row) : '';

// Phone display + tel link
$telRaw = preg_replace('/\D/', '', $phone) ?? '';
$telDisplay = $phone;
if (strlen($telRaw) === 10) {
    $telDisplay = '(' . substr($telRaw, 0, 3) . ') ' . substr($telRaw, 3, 3) . '-' . substr($telRaw, 6);
}

$pageTitle = $name !== '' ? $name . ' — Hoosier Online Front Door Preview' : 'Hoosier Online';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= ho_h($pageTitle) ?></title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="/assets/css/front-door.css">
  <meta name="robots" content="noindex">
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

  <!-- ── INTRO LINE ───────────────────────────────────────────────────────── -->
  <p class="fd-intro-line">Hoosier Online built you a preview &mdash; here&rsquo;s what your front door could look like.</p>

  <!-- ══ THE MOCKUP — their actual front door ═══════════════════════════════ -->
  <?php
  $templateKey  = $design['key'] ?? 'default';
  $templateFile = __DIR__ . '/templates/previews/' . $templateKey . '.php';
  if (!is_file($templateFile)) {
      $templateFile = __DIR__ . '/templates/previews/default.php';
  }
  include $templateFile;
  ?>

  <!-- ── THE TURN ─────────────────────────────────────────────────────────── -->
  <section class="fd-turn">
    <p class="fd-turn-arrow">&uarr;</p>
    <h2>That&rsquo;s your new front door, <?= ho_h($name) ?>.</h2>
    <p>Clean, fast, and built for a phone &mdash; because that&rsquo;s where your customers are looking. No clutter. Just your name, your services, and an easy way to reach you.</p>
  </section>

  <!-- ── WHY WE REACHED OUT (doctrine angle + gaps) ───────────────────────── -->
  <section class="fd-card">
    <p class="fd-kicker">Why We Reached Out</p>
    <?php if ($angle !== ''): ?>
      <p class="fd-why"><?= ho_h($angle) ?></p>
    <?php endif; ?>
    <?php if (!empty($opp)): ?>
      <p class="fd-why"><?= ho_h($opp) ?></p>
    <?php endif; ?>
    <?php if (!empty($gaps)): ?>
      <ul class="fd-why-list">
        <?php foreach (array_slice($gaps, 0, 3) as $g): ?>
          <li><?= ho_h((string)$g) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <!-- ── RECOMMENDED DESIGN ───────────────────────────────────────────────── -->
  <?php if ($design['name'] !== ''): ?>
  <section class="fd-card fd-design">
    <p class="fd-kicker">Recommended Design</p>
    <h2><?= ho_h($design['name']) ?></h2>
    <p class="fd-design-feel"><?= ho_h($design['feel']) ?></p>
    <p class="fd-muted">Picked to fit a <?= ho_h(strtolower($catName)) ?> business. We can adjust the look to your taste before launch.</p>
  </section>
  <?php endif; ?>

  <!-- ── WHAT YOU GET (modules) ───────────────────────────────────────────── -->
  <section class="fd-section">
    <div class="fd-section-head">
      <p class="fd-kicker">What You Get</p>
      <h2>Your Front Door, module by module</h2>
    </div>
    <div class="fd-module-list">
      <?php foreach ($modules as $i => $m): ?>
        <div class="fd-module">
          <span class="fd-module-num"><?= $i + 1 ?></span>
          <div>
            <strong><?= ho_h($m['title']) ?></strong>
            <p><?= ho_h($m['desc']) ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── EVERYTHING INCLUDED (feature checklist) ──────────────────────────── -->
  <section class="fd-card">
    <p class="fd-kicker">Everything Included</p>
    <h2>No add-ons, no surprises</h2>
    <ul class="fd-feature-list">
      <?php foreach ($features as $f): ?>
        <li><?= ho_h($f) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- ── YOUR WEB ADDRESS ─────────────────────────────────────────────────── -->
  <section class="fd-card fd-domain">
    <p class="fd-kicker">Your Web Address</p>
    <h2>Live the day we launch</h2>
    <p class="fd-domain-name"><?= ho_h($subdomain) ?></p>
    <p class="fd-muted">Comes free with your Front Door. Want your own custom domain like <strong><?= ho_h(str_replace('.hoosieronline.com', '.com', $subdomain)) ?></strong>? We&rsquo;ll set that up for you too.</p>
  </section>

  <!-- ── OFFER ────────────────────────────────────────────────────────────── -->
  <section class="fd-card fd-offer">
    <?php if ($isManaged): ?>
      <p class="fd-kicker">The Offer</p>
      <h2>Managed Front Door</h2>
      <p class="fd-price">$999<span> &mdash; we handle everything</span></p>
      <ul class="fd-offer-list">
        <li>Built and launched for you, start to finish</li>
        <li>Your services, reviews, and contact path in one place</li>
        <li>We keep it current &mdash; 3 months included, then $250/quarter</li>
        <li>You focus on the work. We handle the online side.</li>
      </ul>
    <?php else: ?>
      <p class="fd-kicker">The Offer</p>
      <h2>Standard Front Door</h2>
      <p class="fd-price">$499<span> &mdash; up in days, not weeks</span></p>
      <ul class="fd-offer-list">
        <li>Built and launched for you</li>
        <li>Your services, reviews, and contact path &mdash; clean and clear</li>
        <li>1 year hosting included. Renews at $250/year or $25/month.</li>
        <li>No contracts. No surprises.</li>
      </ul>
    <?php endif; ?>

    <div class="fd-actions">
      <a class="fd-btn fd-btn-primary"
         href="mailto:adam@hoosiersonline.com?subject=<?= rawurlencode('Front Door for ' . $name) ?>&body=<?= rawurlencode("Hi Adam,\n\nI saw the preview for {$name} and I'd like to move forward.\n\n") ?>">
        Let&rsquo;s Do It
      </a>
      <a class="fd-btn fd-btn-secondary"
         href="mailto:adam@hoosiersonline.com?subject=<?= rawurlencode('Question about my preview — ' . $name) ?>">
        I Have a Question
      </a>
    </div>
    <p class="fd-muted">No pressure. Just a conversation about your front door.</p>
  </section>

  <footer class="fd-footer">
    <a href="/">Hoosier Online</a> &mdash; Front doors for Indiana&rsquo;s local businesses.
  </footer>

<?php endif; ?>
</main>
</body>
</html>
