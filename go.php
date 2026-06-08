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

$catSlug      = $row ? (string)($row['category_slug'] ?? '') : '';
$design       = $row ? ho_design_direction($catSlug) : ['key' => 'default', 'name' => '', 'feel' => ''];
$subdomain    = $row ? ho_suggest_subdomain($name) : '';
$ownDotCom    = $subdomain !== '' ? str_replace('.hoosieronline.com', '.com', $subdomain) : '';
$modules      = ho_product_modules();
$features     = ho_product_features();
$angle        = $row ? ho_sales_angle($row) : '';

// ── Porkbun domain availability check ────────────────────────────────────
$domainCheck = null;
if ($ownDotCom !== '' && $row) {
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

$pageTitle = $name !== '' ? $name . ' — Hoosier Online Front Door Preview' : 'Hoosier Online';

// OG / social preview
$ogTitle = $name !== ''
    ? $name . ' — See Your Hoosier Online Preview'
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

// ── Record template choice on successful payment ──────────────────────────
if ($paid && $row && $pdo !== null) {
    $chosenTpl = trim((string)($_GET['tpl'] ?? ''));
    if ($chosenTpl !== '') {
        try {
            $pdo->prepare("UPDATE previews SET selected_template = ? WHERE business_id = ?")
                ->execute([substr($chosenTpl, 0, 80), (int)$row['id']]);
        } catch (Throwable) {}
    }
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

<nav class="fd-nav">
  <a class="fd-nav-brand" href="/">Hoosier Online</a>
  <a class="fd-nav-cta" href="#preview">See Preview</a>
</nav>

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
    <p>Your site will be live within 24 hours. Watch your email for your URL. Check your Stripe receipt for a payment confirmation.</p>
    <p class="fd-muted">Questions in the meantime? <a href="tel:7654434321">(765) 443-4321</a> or <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></p>
  </section>
  <?php endif; ?>

  <!-- ── THE TURN ─────────────────────────────────────────────────────────── -->
  <section class="fd-turn">
<p class="fd-turn-eyebrow"><?= ho_h($catName) ?> &middot; <?= ho_h($city) ?>, IN</p>
    <h1 class="fd-turn-name"><?= ho_h($name) ?></h1>
    <p class="fd-turn-tag"><?= $ownerFirst !== '' ? 'Hey ' . ho_h($ownerFirst) . ' &mdash; I built a website preview for your business.' : 'I built a website preview for your business.' ?></p>
    <?php if ($angle !== ''): ?>
      <p class="fd-turn-angle"><?= ho_h($angle) ?></p>
    <?php endif; ?>
    <div class="fd-turn-actions">
      <a href="#preview" class="fd-btn fd-btn-primary fd-turn-cta">View Your Preview &darr;</a>
    </div>
  </section>

  <!-- ── WHY I REACHED OUT ─────────────────────────────────────────────────── -->
  <section class="fd-card fd-why-card fd-reveal">
    <p class="fd-kicker">Why I reached out</p>

    <?php
    // Build the "we looked up" source chips
    $sources = [];
    if ($hasWebsite && $websiteUrl !== '') {
        $domain = parse_url($websiteUrl, PHP_URL_HOST) ?: $websiteUrl;
        $domain = ltrim($domain, 'www.');
        $sources[] = ['href' => $websiteUrl, 'label' => ho_h($domain), 'class' => 'fd-rs-site'];
    }
    if ($hasFacebook && $fbUrl !== '') {
        $sources[] = ['href' => $fbUrl, 'label' => 'Facebook page', 'class' => 'fd-rs-fb'];
    }
    if ($hasGoogle) {
        $gQuery = rawurlencode($name . ' ' . $city . ' Indiana');
        $sources[] = ['href' => 'https://www.google.com/search?q=' . $gQuery, 'label' => 'Google Business', 'class' => 'fd-rs-google'];
    }
    ?>
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

    <?php if ($googleCount > 0): ?>
    <div class="fd-rating-block">
      <div class="fd-rating-badge">
        <span class="fd-stars"><?= str_repeat('★', min(5, (int)round($googleRating))) . str_repeat('☆', max(0, 5 - (int)round($googleRating))) ?></span>
        <strong><?= number_format($googleRating, 1) ?></strong>
        <span class="fd-rating-count"><?= number_format($googleCount) ?> Google reviews</span>
      </div>
      <p class="fd-rating-source">Your live rating pulled from Google.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($opp)): ?>
      <p class="fd-why"><?= ho_h($opp) ?></p>
    <?php endif; ?>

    <?php if (!empty($strengths)): ?>
      <p class="fd-str-intro">What you already have going for you:</p>
      <div class="fd-str-list">
        <?php foreach (array_slice($strengths, 0, 3) as $s): ?>
          <div class="fd-str-item"><span class="fd-str-marker" aria-hidden="true">✓</span><?= ho_h((string)$s) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($gaps)): ?>
      <p class="fd-gap-intro">What&rsquo;s holding you back right now:</p>
      <div class="fd-gap-list">
        <?php foreach (array_slice($gaps, 0, 3) as $g): ?>
          <div class="fd-gap-item"><span class="fd-gap-marker" aria-hidden="true">✗</span><?= ho_h((string)$g) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p class="fd-why-scroll">I built something to fix that. See it below &darr;</p>
  </section>

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
    <div class="fd-design-chooser">
      <p class="fd-kicker">Your website &mdash; live preview</p>
      <h2 class="fd-design-title">This is the real thing. Pick your look.</h2>
      <p class="fd-design-sub">Tap any style. The one you pick is the one we build.</p>
      <div class="fd-design-exclusive">
        <span class="fd-excl-badge">100% original</span>
        <span class="fd-excl-text">Every design is built from scratch for your business. The one you choose will never be used for another <?= ho_h(strtolower($catName)) ?> company in <?= ho_h($city) ?> &mdash; or anywhere else.</span>
      </div>
    </div>

    <div class="fd-tpl-picker">
      <?php foreach ($available as $k => $opt): ?>
        <button class="fd-tpl-tab<?= $k === $templateKey ? ' fd-tpl-tab--active' : '' ?>" data-tpl="<?= ho_h($k) ?>" data-label="<?= ho_h($opt['label']) ?>">
          <span class="fd-tpl-dot" style="background:<?= ho_h($opt['color']) ?>"></span>
          <?= ho_h($opt['label']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="fd-chosen-label">
      <span>Your design:</span>
      <strong id="fd-chosen-tpl"><?= ho_h($available[$templateKey]['label'] ?? '') ?></strong>
      <span class="fd-chosen-check">✓ Selected</span>
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
    <div class="fd-phone-hint">This choice carries into checkout &mdash; pick the one you want to launch with.</div>
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
        // Update sticky bar label
        var tplLabel = document.getElementById('fd-chosen-tpl');
        if (tplLabel) tplLabel.textContent = tab.textContent.trim();
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



  <!-- ── WHO BUILT THIS ───────────────────────────────────────────────────── -->
  <section class="fd-card fd-trust fd-reveal" id="about">
    <p class="fd-kicker">Who built this</p>
    <div class="fd-trust-inner">
      <div class="fd-trust-avatar" aria-hidden="true">AF</div>
      <div>
        <h2>Adam Ferree</h2>
        <p class="fd-trust-location">New Castle, Indiana</p>
      </div>
    </div>
    <p>Building websites for Indiana service businesses is my full-time work &mdash; not a side project, not a template mill. I found your business online, looked at what you had (or didn&rsquo;t have), and decided it was worth a few hours of my time to build something real. If I sent this to you, I mean it.</p>
    <ul class="fd-trust-signals">
      <li>Indiana-based &mdash; not a national agency</li>
      <li>Every preview researched and built personally</li>
      <li>Flat price, no contract, no monthly fees</li>
    </ul>
    <div class="fd-trust-contact">
      <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a>
      <?php if ($adamPhone !== ''): ?>
        <span class="fd-trust-sep">&middot;</span>
        <a href="tel:<?= ho_h(preg_replace('/\D/', '', $adamPhone)) ?>"><?= ho_h($adamPhone) ?></a>
      <?php endif; ?>
    </div>
  </section>

  <!-- ── WHAT YOU GET (modules) ───────────────────────────────────────────── -->
  <section class="fd-section fd-reveal" id="services">
    <div class="fd-section-head">
      <p class="fd-kicker">What We Build</p>
      <h2>Everything a customer needs to hire you.</h2>
    </div>
    <div class="fd-module-list">
      <?php foreach ($modules as $i => $m): ?>
        <div class="fd-module fd-reveal" style="--reveal-delay:<?= $i * 80 ?>ms">
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
  <section class="fd-card fd-reveal">
    <p class="fd-kicker">Every Front Door Includes</p>
    <h2>Full build. Nothing extra to buy.</h2>
    <ul class="fd-feature-list">
      <?php foreach ($features as $f): ?>
        <li><?= ho_h($f) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- ── YOUR DOMAIN ───────────────────────────────────────────────────────── -->
  <?php
  $initAvailClass = '';
  $initAvailText  = '';
  if ($domainCheck !== null) {
      if ($domainCheck['available']) {
          $initAvailClass = 'fd-avail-yes';
          $initAvailText  = '✓ Available';
      } else {
          $initAvailClass = 'fd-avail-no';
          $initAvailText  = '✗ Taken';
      }
  }
  $domainInputVal = preg_replace('/\.com$/i', '', $ownDotCom);
  ?>
  <section class="fd-card fd-addr-chooser fd-reveal">
    <p class="fd-kicker">Your web address</p>
    <h2>Where customers will find you.</h2>
    <p class="fd-design-sub">Included free. We register it and handle renewals.</p>

    <div class="fd-addr-domain">
      <div class="fd-addr-url fd-addr-url-com" id="fd-com-display"><?= ho_h($ownDotCom) ?></div>
      <div class="fd-addr-badges">
        <span class="fd-addr-tag fd-addr-tag-free">Included free</span>
        <span class="fd-avail-badge <?= ho_h($initAvailClass) ?>" id="fd-com-avail-badge"
              <?= $initAvailText === '' ? 'hidden' : '' ?>><?= ho_h($initAvailText) ?></span>
      </div>
    </div>

    <div class="fd-domain-search">
      <p class="fd-design-sub">Want a different name? Check availability below.</p>
      <div class="fd-domain-input-row">
        <input type="text" id="fd-domain-input"
               class="fd-domain-input"
               value="<?= ho_h($domainInputVal) ?>"
               placeholder="yourbusiness"
               maxlength="63"
               onkeydown="if(event.key==='Enter'){event.preventDefault();fdCheckDomain();}">
        <span class="fd-domain-tld">.com</span>
        <button type="button" class="fd-domain-check-btn"
                onclick="fdCheckDomain()">Check</button>
      </div>
      <div class="fd-domain-hint" id="fd-domain-hint"><?php
        if ($domainCheck !== null && !$domainCheck['available']) {
            echo 'That name is taken &mdash; try a variation above.';
        } else {
            echo 'Want a different name? Type it and tap Check.';
        }
      ?></div>
    </div>
  </section>

  <script>
  function fdCheckDomain() {
    var input     = document.getElementById('fd-domain-input');
    var badge     = document.getElementById('fd-com-avail-badge');
    var display   = document.getElementById('fd-com-display');
    var hint      = document.getElementById('fd-domain-hint');
    var chosenHid = document.getElementById('fd-h-chosen-com');
    if (!input) return;

    var raw = input.value.trim().toLowerCase().replace(/\.com$/i, '').replace(/[^a-z0-9\-]/g, '');
    if (raw.length < 2) { if (hint) hint.textContent = 'Enter at least 2 characters.'; return; }
    var domain = raw + '.com';

    if (display) display.textContent = domain;
    if (badge)   { badge.className = 'fd-avail-badge fd-avail-checking'; badge.textContent = 'Checking…'; badge.hidden = false; }
    if (hint)    hint.textContent = '';

    var fd = new FormData();
    fd.append('domain', raw);
    fetch('/domain-check.php', {method: 'POST', body: fd})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.error) {
          if (badge) { badge.hidden = true; }
          if (hint)  hint.textContent = '⚠ ' + data.error;
          return;
        }
        if (data.available) {
          if (badge) { badge.className = 'fd-avail-badge fd-avail-yes'; badge.textContent = '✓ Available'; }
          if (hint)  hint.textContent = 'Great — that name is available.';
          if (chosenHid) chosenHid.value = domain;
        } else {
          if (badge) { badge.className = 'fd-avail-badge fd-avail-no'; badge.textContent = '✗ Taken'; }
          if (hint)  hint.textContent = 'That name is taken — try a variation above.';
        }
      })
      .catch(function(){
        if (badge) { badge.className = 'fd-avail-badge fd-avail-no'; badge.textContent = 'Check failed — try again'; }
      });
  }
  </script>

  <!-- ── PACKAGE CONFIGURATOR ────────────────────────────────────────────── -->
  <?php
  $bundles       = ho_bundle_presets();
  $defaultBundle = $isManaged ? 'managed' : 'launch';
  $defaultBData  = $bundles[$defaultBundle];
  $defaultPrice  = ho_bundle_price($defaultBundle);
  ?>
  <section class="fd-card fd-offer fd-reveal" id="pricing">
    <p class="fd-kicker">Ready to launch</p>
    <h2>Launch your site. One flat price.</h2>
    <p class="fd-offer-intro">Built for <?= ho_h($name) ?>, serving <?= ho_h($serviceArea) ?>. You own it the day it goes live &mdash; no contracts, no monthly fees, no surprises.</p>

    <!-- Bundle cards -->
    <div class="fd-bundle-grid">
      <?php foreach ($bundles as $bKey => $b):
        $bPrice   = ho_bundle_price($bKey);
        $selected = $bKey === $defaultBundle;
      ?>
      <label class="fd-bundle-card<?= $selected ? ' is-selected' : '' ?>"
             data-pkg="<?= ho_h($b['pkg']) ?>"
             data-addons="<?= ho_h(json_encode($b['addons'])) ?>">
        <input type="radio" name="bundle_display" value="<?= ho_h($bKey) ?>"
               <?= $selected ? 'checked' : '' ?> onchange="fdSelectBundle(this.closest('.fd-bundle-card'))">
        <?php if ($b['badge'] !== ''): ?>
          <span class="fd-bundle-badge"><?= ho_h($b['badge']) ?></span>
        <?php endif; ?>
        <div class="fd-bundle-head">
          <strong class="fd-bundle-name"><?= ho_h($b['label']) ?></strong>
          <span class="fd-bundle-price">$<?= number_format($bPrice) ?></span>
        </div>
        <ul class="fd-bundle-items">
          <?php foreach ($b['items'] as $item): ?>
            <li><?= ho_h($item) ?></li>
          <?php endforeach; ?>
        </ul>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- Total + form -->
    <div class="fd-total-row">
      <span>Your total:</span>
      <strong id="fd-pkg-total">$<?= number_format($defaultPrice) ?></strong>
    </div>

    <p class="fd-kicker" style="margin-top:24px;margin-bottom:10px">What happens next</p>
    <ol class="fd-offer-steps">
      <li>You say yes &mdash; takes about 2 minutes to check out</li>
      <li>I build <?= ho_h($name) ?>&rsquo;s site &mdash; live within 24 hours, guaranteed</li>
      <li><?= $ownDotCom !== '' ? ho_h($ownDotCom) . ' goes live' : ho_h($name) . ' goes live' ?> &mdash; customers can find and hire you</li>
    </ol>

    <div class="fd-guarantee-box">
      <strong>30-day money-back guarantee.</strong>
      If you&rsquo;re not happy after launch, I&rsquo;ll refund you in full. No questions, no back-and-forth.
    </div>

    <form method="POST" action="/checkout.php" class="fd-checkout-form">
      <input type="hidden" name="slug"         value="<?= ho_h($slug) ?>">
      <input type="hidden" name="pkg"          id="fd-h-pkg"      value="<?= ho_h($defaultBData['pkg']) ?>">
      <input type="hidden" name="template_key" id="fd-h-template" value="<?= ho_h($templateKey ?? '') ?>">
      <input type="hidden" name="chosen_com"  id="fd-h-chosen-com" value="<?= ho_h($ownDotCom) ?>">
      <button type="submit" class="fd-btn fd-btn-primary fd-stripe-btn">
        Yes, Build This &rarr;
      </button>
    </form>
    <div class="fd-secure-note">Stripe &middot; 256-bit SSL &middot; pay in 2 minutes</div>

    <a class="fd-btn fd-btn-secondary fd-questions-btn"
       href="mailto:adam@hoosieronline.com?subject=<?= rawurlencode('Question about my preview — ' . $name) ?>&body=<?= rawurlencode("Hi Adam,\n\nI have a question about the preview you built for " . $name . ".\n\n") ?>">
      Have a question first? Email me &mdash; no pressure.
    </a>

    <div class="fd-scarcity">I build one <?= ho_h(strtolower($catName)) ?> site per city &mdash; whoever says yes first in <?= ho_h($city) ?> gets it.</div>
  </section>

  <script>
  var FD_PRICES = {standard:<?= ho_bundle_price('standard') ?>,launch:<?= ho_bundle_price('launch') ?>,managed:<?= ho_bundle_price('managed') ?>};

  function fdSelectBundle(card) {
    var pkg = card.dataset.pkg || 'standard';
    document.querySelectorAll('.fd-bundle-card').forEach(function(c) { c.classList.remove('is-selected'); });
    card.classList.add('is-selected');
    var pkgHid = document.getElementById('fd-h-pkg');
    if (pkgHid) pkgHid.value = pkg;
    fdUpdateTotal();
  }

  function fdUpdateTotal() {
    var pkgHid = document.getElementById('fd-h-pkg');
    var pkg    = pkgHid ? pkgHid.value : '<?= ho_h($defaultBundle) ?>';
    var base   = FD_PRICES[pkg] || <?= $defaultPrice ?>;

    var total = base;
    var totalEl = document.getElementById('fd-pkg-total');
    if (totalEl) {
      totalEl.textContent = '$' + total.toLocaleString();
      totalEl.classList.remove('fd-total-flash');
      void totalEl.offsetWidth;
      totalEl.classList.add('fd-total-flash');
    }
  }
  </script>

  <footer class="fd-footer">
    <strong><a href="/">Hoosier Online</a></strong><br>
    Front doors for Indiana&rsquo;s hardest-working businesses.<br>
    <span class="fd-footer-by">Built by Adam Ferree &middot; <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></span>
  </footer>

  <!-- ── STICKY BOTTOM CTA ──────────────────────────────────────────────── -->
  <div class="fd-sticky-bar" id="fd-sticky-bar" hidden>
    <div class="fd-sticky-inner" id="fd-sticky-pre">
      <span class="fd-sticky-biz"><?= ho_h($name) ?></span>
      <a href="#pricing" class="fd-btn fd-btn-secondary fd-sticky-btn">See Launch Options &rarr;</a>
    </div>
    <div class="fd-sticky-inner" id="fd-sticky-post" hidden>
      <div>
        <strong><?= ho_h($name) ?></strong>
        <span>Total: <span id="fd-sticky-total">$<?= number_format($defaultPrice) ?></span></span>
      </div>
      <a href="#pricing" class="fd-btn fd-btn-primary fd-sticky-btn">Yes, Build This &rarr;</a>
    </div>
  </div>
  <script>
  (function(){
    var bar      = document.getElementById('fd-sticky-bar');
    var offer    = document.getElementById('pricing');
    var preEl    = document.getElementById('fd-sticky-pre');
    var postEl   = document.getElementById('fd-sticky-post');
    if (!bar || !offer) return;
    var shown = false, pricingSeen = false;
    function showBar() {
      bar.hidden = false;
      if (preEl)  preEl.hidden  = pricingSeen;
      if (postEl) postEl.hidden = !pricingSeen;
    }
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting) {
          pricingSeen = true;
          bar.hidden = true; shown = false;
        } else if (shown) {
          showBar();
        }
      });
    }, {threshold: 0});
    io.observe(offer);
    window.addEventListener('scroll', function(){
      if (!shown && window.scrollY > 300) {
        shown = true;
        if (offer.getBoundingClientRect().top >= window.innerHeight) showBar();
      }
    }, {passive: true});
    var totalEl = document.getElementById('fd-pkg-total');
    var stickyTotal = document.getElementById('fd-sticky-total');
    if (totalEl && stickyTotal) {
      var mo = new MutationObserver(function(){ stickyTotal.textContent = totalEl.textContent; });
      mo.observe(totalEl, {childList:true, characterData:true, subtree:true});
    }
  })();
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
