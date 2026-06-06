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

// ─── Data helpers ─────────────────────────────────────────────────────────────
$name     = $row ? (string)$row['business_name']     : '';
$city     = $row ? (string)$row['location_city']     : '';
$catName  = $row ? (string)$row['category_name']     : '';
$headline = $row ? (string)$row['headline']          : '';
$subhead  = $row ? (string)$row['subheadline']       : '';
$opp      = $row ? (string)$row['opportunity_statement'] : '';
$package  = $row ? (string)$row['package_recommendation'] : 'standard';

$services = $row ? (array)json_decode((string)($row['services_display'] ?? '[]'), true) : [];
$strengths= $row ? (array)json_decode((string)($row['strengths']        ?? '[]'), true) : [];
$gaps     = $row ? (array)json_decode((string)($row['gaps']             ?? '[]'), true) : [];

$googleCount  = (int)($row['google_review_count'] ?? 0);
$googleRating = (float)($row['google_rating']     ?? 0);
$hasGoogle    = (bool)($row['has_google_business'] ?? false);
$hasWebsite   = (bool)($row['has_website']         ?? false);
$hasFacebook  = (bool)($row['has_facebook']        ?? false);
$websiteQ     = (string)($row['website_quality']   ?? 'none');
$fbActivity   = (string)($row['facebook_activity'] ?? 'none');

$serviceArea  = (string)($row['service_area_text'] ?? ($city !== '' ? $city . ' and surrounding area' : 'Indiana'));

$isManaged = $package === 'managed';

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

  <!-- ── HERO ─────────────────────────────────────────────────────────────── -->
  <section class="fd-card fd-hero">
    <p class="fd-kicker">Hoosier Online &mdash; Front Door Preview</p>
    <h1>We took a look at <?= ho_h($name) ?>.</h1>
    <p><?= ho_h($opp ?: $subhead) ?></p>
    <div class="fd-meta">
      <span><?= ho_h($catName) ?></span>
      <?php if ($city !== ''): ?><span><?= ho_h($city) ?>, IN</span><?php endif; ?>
      <?php if ($hasGoogle && $googleCount > 0): ?>
        <span><?= $googleCount ?> Google reviews<?= $googleRating > 0 ? ' &middot; ' . number_format($googleRating, 1) . ' ★' : '' ?></span>
      <?php endif; ?>
    </div>
  </section>

  <!-- ── ONLINE PRESENCE ──────────────────────────────────────────────────── -->
  <section class="fd-card">
    <p class="fd-kicker">What We Found Online</p>
    <h2>Here&rsquo;s where you stand right now</h2>
    <div class="fd-presence-grid">
      <div class="fd-presence-item <?= $hasWebsite && in_array($websiteQ, ['basic','decent'], true) ? 'fd-present' : 'fd-gap' ?>">
        <strong>Website</strong>
        <span><?php
          if (!$hasWebsite || $websiteQ === 'none')  echo 'No website found';
          elseif ($websiteQ === 'poor')               echo 'Site exists but outdated';
          elseif ($websiteQ === 'basic')              echo 'Basic site present';
          else                                        echo 'Decent website';
        ?></span>
      </div>
      <div class="fd-presence-item <?= $hasGoogle ? 'fd-present' : 'fd-gap' ?>">
        <strong>Google</strong>
        <span><?= $hasGoogle
          ? ($googleCount > 0 ? $googleCount . ' reviews' : 'Listed, no reviews')
          : 'Not on Google' ?></span>
      </div>
      <div class="fd-presence-item <?= ($hasFacebook && $fbActivity === 'active') ? 'fd-present' : 'fd-gap' ?>">
        <strong>Facebook</strong>
        <span><?php
          if (!$hasFacebook)                  echo 'No Facebook page';
          elseif ($fbActivity === 'dormant')  echo 'Page exists, not active';
          elseif ($fbActivity === 'active')   echo 'Active on Facebook';
          else                                echo 'No Facebook page';
        ?></span>
      </div>
    </div>
  </section>

  <!-- ── SERVICES ─────────────────────────────────────────────────────────── -->
  <?php if (!empty($services)): ?>
  <section class="fd-card">
    <p class="fd-kicker">Services</p>
    <h2>What you do for <?= ho_h($serviceArea) ?></h2>
    <ul class="fd-service-list">
      <?php foreach ($services as $svc): ?>
        <li><?= ho_h((string)$svc) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <!-- ── STRENGTHS ────────────────────────────────────────────────────────── -->
  <?php if (!empty($strengths)): ?>
  <section class="fd-section">
    <div class="fd-section-head">
      <p class="fd-kicker">Working in Your Favor</p>
      <h2>You&rsquo;re already doing some things right</h2>
    </div>
    <div class="fd-tag-list">
      <?php foreach ($strengths as $s): ?>
        <span class="fd-tag fd-tag-good"><?= ho_h((string)$s) ?></span>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── GAPS ─────────────────────────────────────────────────────────────── -->
  <?php if (!empty($gaps)): ?>
  <section class="fd-section">
    <div class="fd-section-head">
      <p class="fd-kicker">Where Customers Get Lost</p>
      <h2>A few things that might be costing you jobs</h2>
    </div>
    <div class="fd-tag-list">
      <?php foreach ($gaps as $g): ?>
        <span class="fd-tag fd-tag-gap"><?= ho_h((string)$g) ?></span>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ── OFFER ────────────────────────────────────────────────────────────── -->
  <section class="fd-card fd-offer">
    <p class="fd-kicker">The Offer</p>
    <?php if ($isManaged): ?>
      <h2>Managed Front Door &mdash; $999</h2>
      <p class="fd-offer-sub">Everything handled for you, updated quarterly.</p>
      <ul class="fd-offer-list">
        <li>Custom front door built for <?= ho_h($name) ?></li>
        <li>Your services, reviews, and contact path — all in one place</li>
        <li>We keep it current — 3 months included, then $250/quarter</li>
        <li>You stay focused on the work. We handle the online presence.</li>
      </ul>
    <?php else: ?>
      <h2>Standard Front Door &mdash; $499</h2>
      <p class="fd-offer-sub">One clean page. Up in days, not weeks.</p>
      <ul class="fd-offer-list">
        <li>Custom front door built for <?= ho_h($name) ?></li>
        <li>Your services, reviews, and contact path — clean and clear</li>
        <li>1 year hosting included. Renews at $250/year or $25/month.</li>
        <li>Simple. No contracts. No surprises.</li>
      </ul>
    <?php endif; ?>

    <div class="fd-actions">
      <a class="fd-btn fd-btn-primary"
         href="mailto:adam@hoosiersonline.com?subject=<?= rawurlencode('Front Door Preview — ' . $name) ?>&body=<?= rawurlencode("Hi Adam,\n\nI saw the preview for {$name} and I'm interested.\n\n") ?>">
        I&rsquo;m Interested &mdash; Email Us
      </a>
      <a class="fd-btn fd-btn-secondary"
         href="mailto:adam@hoosiersonline.com?subject=<?= rawurlencode('Question about my preview — ' . $name) ?>">
        I Have a Question
      </a>
    </div>
    <p class="fd-muted">No pressure. No commitment. Just a conversation about your front door.</p>
  </section>

  <!-- ── FOOTER ───────────────────────────────────────────────────────────── -->
  <footer class="fd-footer">
    <a href="/">Hoosier Online</a>
    &mdash; Building front doors for Indiana&rsquo;s local businesses.
  </footer>

<?php endif; ?>
</main>
</body>
</html>
