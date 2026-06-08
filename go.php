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
$opp        = $row ? (string)$row['opportunity_statement']  : '';
$subhead    = $row ? (string)$row['subheadline']            : '';
$package    = $row ? (string)$row['package_recommendation'] : 'standard';
$phone      = $row ? (string)($row['phone_number'] ?? '')   : '';
$ownerFirst = $row ? trim((string)($row['owner_first_name'] ?? '')) : '';

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
  <div class="fd-nav-links">
    <a href="#preview">Preview</a>
    <a href="#about">About</a>
    <a href="#services">Services</a>
    <a href="#pricing">Pricing</a>
    <a class="fd-nav-cta" href="#pricing">Get Started</a>
  </div>
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
    <p class="fd-turn-tag"><?= $ownerFirst !== '' ? ho_h($ownerFirst) . ' &mdash; I built this for you.' : 'I built this for you.' ?></p>
    <?php if ($angle !== ''): ?>
      <p class="fd-turn-angle"><?= ho_h($angle) ?></p>
    <?php endif; ?>
  </section>
  <div class="fd-scroll-hint" aria-hidden="true">
    <span class="fd-scroll-arrow">↓</span>
    <span>Keep reading</span>
  </div>

  <!-- ── WHY I REACHED OUT ─────────────────────────────────────────────────── -->
  <section class="fd-card fd-why-card fd-reveal">
    <p class="fd-kicker">Why I reached out</p>
    <?php if ($googleCount > 0): ?>
    <div class="fd-rating-badge">
      <span class="fd-stars"><?= str_repeat('★', min(5, (int)round($googleRating))) . str_repeat('☆', max(0, 5 - (int)round($googleRating))) ?></span>
      <strong><?= number_format($googleRating, 1) ?></strong>
      <span class="fd-rating-count">(<?= number_format($googleCount) ?> Google reviews)</span>
    </div>
    <?php endif; ?>
    <?php if (!empty($opp)): ?>
      <p class="fd-why"><?= ho_h($opp) ?></p>
    <?php endif; ?>
    <?php if (!empty($gaps)): ?>
      <p class="fd-gap-intro">Here&rsquo;s what&rsquo;s costing you customers right now:</p>
      <div class="fd-gap-list">
        <?php foreach (array_slice($gaps, 0, 3) as $g): ?>
          <div class="fd-gap-item"><span class="fd-gap-marker" aria-hidden="true">✗</span><?= ho_h((string)$g) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <p class="fd-why-scroll">Scroll down to see what I built. &darr;</p>
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

  <div class="fd-design-chooser">
    <p class="fd-kicker">Choose your design</p>
    <h2 class="fd-design-title">This is your actual site &mdash; pick the look you want.</h2>
    <p class="fd-design-sub">Tap a style below. What you see is what we build.</p>
    <div class="fd-design-exclusive">
      <span class="fd-excl-badge">100% original</span>
      Every design is built from scratch for your business. The one you choose will never be used for another <?= ho_h(strtolower($catName)) ?> company in <?= ho_h($city) ?> &mdash; or anywhere else.
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

  <div class="fd-phone-frame">
    <div class="fd-phone-screen" id="fd-phone-screen">
      <?php foreach ($available as $k => $opt): ?>
        <div class="fd-tpl-pane" id="tpl-<?= ho_h($k) ?>"<?= $k !== $templateKey ? ' hidden' : '' ?>>
          <?php include $opt['file']; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="fd-phone-hint">Scroll inside the phone to explore &uarr; &nbsp;&middot;&nbsp; your choice carries through to checkout.</p>

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

  <!-- ── CHOOSE YOUR ADDRESS ───────────────────────────────────────────────── -->
  <?php $ownDotCom = str_replace('.hoosieronline.com', '.com', $subdomain); ?>
  <div class="fd-addr-chooser fd-reveal">
    <p class="fd-kicker">Choose your address</p>
    <h2 class="fd-design-title">Where customers will find you.</h2>
    <p class="fd-design-sub">Both options go live the day your site launches. You can always upgrade later.</p>

    <div class="fd-addr-options">
      <label class="fd-addr-card is-selected" id="fd-addr-sub-card">
        <input type="radio" name="addr_choice" value="sub" checked>
        <div class="fd-addr-body">
          <div class="fd-addr-head">
            <div class="fd-addr-url"><?= ho_h($subdomain) ?></div>
            <span class="fd-addr-tag fd-addr-tag-free">Included free</span>
          </div>
          <p>Hosted on hoosieronline.com. Live within a week, no annual fee, no renewals to worry about.</p>
        </div>
      </label>

      <label class="fd-addr-card" id="fd-addr-com-card">
        <input type="radio" name="addr_choice" value="com">
        <div class="fd-addr-body">
          <div class="fd-addr-head">
            <div class="fd-addr-url fd-addr-url-com"><?= ho_h($ownDotCom) ?></div>
            <span class="fd-addr-tag fd-addr-tag-addon">+$25/yr</span>
          </div>
          <p>Your own .com &mdash; looks more professional, easier to hand out. We register it and handle renewals. Included at no extra cost in <strong>Launch Ready</strong> &amp; <strong>Complete</strong>.</p>
        </div>
      </label>
    </div>
    <p class="fd-addr-note">If <?= ho_h($ownDotCom) ?> isn&rsquo;t available, I&rsquo;ll find the closest option and confirm with you before anything is registered.</p>
  </div>

  <script>
  (function(){
    var subCard = document.getElementById('fd-addr-sub-card');
    var comCard = document.getElementById('fd-addr-com-card');
    var subRadio = subCard ? subCard.querySelector('input') : null;
    var comRadio = comCard ? comCard.querySelector('input') : null;
    function syncDomainAddon(wantCom) {
      var cb  = document.querySelector('.fd-addon-list input[data-addon="domain"]');
      var hid = document.getElementById('fd-h-domain');
      if (cb)  { cb.checked = wantCom; }
      if (hid) { hid.disabled = !wantCom; if (wantCom) hid.value = 'domain'; }
      if (typeof fdUpdateTotal === 'function') fdUpdateTotal();
      if (subCard) subCard.classList.toggle('is-selected', !wantCom);
      if (comCard) comCard.classList.toggle('is-selected', wantCom);
    }
    if (subRadio) subRadio.addEventListener('change', function(){ if (this.checked) syncDomainAddon(false); });
    if (comRadio) comRadio.addEventListener('change', function(){ if (this.checked) syncDomainAddon(true); });
  })();
  </script>

  <!-- WHY section moved above mockup — see below -->


  <!-- ── WHO BUILT THIS ───────────────────────────────────────────────────── -->
  <section class="fd-card fd-trust fd-reveal" id="about">
    <p class="fd-kicker">Who sent this</p>
    <div class="fd-trust-inner">
      <div class="fd-trust-avatar" aria-hidden="true">A</div>
      <div>
        <h2>Adam Ferree</h2>
        <p class="fd-trust-location">New Castle, Indiana</p>
      </div>
    </div>
    <p>I research <?= ho_h(strtolower($catName)) ?> businesses across Indiana and build preview sites for the ones I think deserve better visibility online. I do this myself &mdash; no sales team, no outsourcing. If you got this link, I looked you up personally and thought it was worth my time to build.</p>
    <ul class="fd-trust-signals">
      <li>Indiana-based, not a national agency</li>
      <li>Every preview personally researched &amp; built</li>
      <li>Flat pricing. No contracts. No surprises.</li>
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
      <h2>Five things, done right.</h2>
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
    <h2>The full build. Nothing held back.</h2>
    <ul class="fd-feature-list">
      <?php foreach ($features as $f): ?>
        <li><?= ho_h($f) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- ── PACKAGE CONFIGURATOR ────────────────────────────────────────────── -->
  <?php
  $pkgCatalog    = ho_package_catalog();
  $addonCatalog  = ho_addon_catalog();
  $bundles       = ho_bundle_presets();
  $priceMap      = ho_addon_price_map();
  $defaultBundle = $isManaged ? 'managed' : 'launch';
  $defaultBData  = $bundles[$defaultBundle];
  $defaultPrice  = ho_bundle_price($defaultBundle);
  ?>
  <section class="fd-card fd-offer fd-reveal" id="pricing">
    <p class="fd-kicker">Ready to launch</p>
    <h2>Your site. Your price.</h2>
    <p class="fd-offer-intro">Pick the option that fits. No contract, no monthly fees &mdash; you own it the day it goes live.</p>

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

    <!-- Customize toggle -->
    <button type="button" class="fd-customize-btn" id="fd-customize-btn"
            onclick="fdToggleCustomize()">+ Customize or add more items</button>

    <!-- Full add-on list — hidden by default -->
    <div id="fd-addon-area" hidden>
      <p class="fd-addon-label" style="margin-top:4px">Base package:</p>
      <div class="fd-pkg-options">
        <?php foreach ($pkgCatalog as $pkgKey => $pkgData):
          $pkgSel = $pkgKey === $defaultBData['pkg'];
        ?>
        <label class="fd-pkg-option<?= $pkgSel ? ' is-selected' : '' ?>">
          <input type="radio" name="pkg_display" value="<?= ho_h($pkgKey) ?>"
                 data-price="<?= (int)$pkgData['price'] ?>"
                 <?= $pkgSel ? 'checked' : '' ?> onchange="fdUpdateTotal()">
          <div class="fd-pkg-option-body">
            <div class="fd-pkg-option-head">
              <strong><?= ho_h($pkgData['label']) ?></strong>
              <span class="fd-pkg-price-tag">$<?= number_format($pkgData['price']) ?></span>
            </div>
            <p><?= ho_h($pkgData['desc']) ?></p>
          </div>
        </label>
        <?php endforeach; ?>
      </div>

      <p class="fd-addon-label" style="margin-top:16px">Add-ons:</p>
      <div class="fd-addon-list">
        <?php foreach ($addonCatalog as $cat): ?>
        <p class="fd-addon-cat"><?= ho_h($cat['label']) ?></p>
        <?php foreach ($cat['items'] as $addonKey => $addon):
          $isChecked = in_array($addonKey, $defaultBData['addons'], true);
          $desc = $addon['desc'];
          if ($addonKey === 'domain') {
              $ownDomain = str_replace('.hoosieronline.com', '.com', $subdomain);
              $desc = 'Your own .com (e.g., ' . ho_h($ownDomain) . ') instead of .hoosieronline.com — we register it for you.';
          }
        ?>
        <label class="fd-addon-item">
          <input type="checkbox" data-price="<?= (int)$addon['price'] ?>"
                 data-addon="<?= ho_h($addonKey) ?>"
                 <?= $isChecked ? 'checked' : '' ?> onchange="fdUpdateTotal()">
          <div class="fd-addon-body">
            <strong><?= ho_h($addon['label']) ?></strong>
            <span class="fd-addon-price">+$<?= number_format($addon['price']) ?><?= ho_h($addon['note']) ?></span>
            <p><?= $desc ?></p>
          </div>
        </label>
        <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Total + form -->
    <div class="fd-total-row">
      <span>Your total:</span>
      <strong id="fd-pkg-total">$<?= number_format($defaultPrice) ?></strong>
    </div>

    <p class="fd-kicker" style="margin-top:24px;margin-bottom:10px">What happens next</p>
    <ol class="fd-offer-steps">
      <li>You say yes &mdash; takes about 2 minutes to check out</li>
      <li>I build your site and send you the link within a week</li>
      <li>You go live &mdash; customers can find and contact you</li>
    </ol>

    <div class="fd-guarantee-box">
      <strong>30-day money-back guarantee.</strong>
      If you&rsquo;re not happy after launch, I&rsquo;ll refund you in full. No questions, no back-and-forth.
    </div>

    <form method="POST" action="/checkout.php" class="fd-checkout-form">
      <input type="hidden" name="slug"         value="<?= ho_h($slug) ?>">
      <input type="hidden" name="pkg"          id="fd-h-pkg"      value="<?= ho_h($defaultBData['pkg']) ?>">
      <input type="hidden" name="template_key" id="fd-h-template" value="<?= ho_h($templateKey) ?>">
      <input type="hidden" name="subdomain"                       value="<?= ho_h($subdomain) ?>">
      <?php foreach ($addonCatalog as $cat): foreach ($cat['items'] as $addonKey => $addon):
        $isChecked = in_array($addonKey, $defaultBData['addons'], true);
      ?>
      <input type="hidden" name="addons[]" id="fd-h-<?= ho_h($addonKey) ?>"
             value="<?= ho_h($addonKey) ?>"<?= !$isChecked ? ' disabled' : '' ?>>
      <?php endforeach; endforeach; ?>
      <button type="submit" class="fd-btn fd-btn-primary fd-stripe-btn">
        Yes, I Want This &rarr;
      </button>
    </form>
    <p class="fd-secure-note">Stripe &middot; 256-bit SSL &middot; pay in 2 minutes</p>

    <a class="fd-btn fd-btn-secondary fd-questions-btn"
       href="mailto:adam@hoosieronline.com?subject=<?= rawurlencode('Question about my preview — ' . $name) ?>&body=<?= rawurlencode("Hi Adam,\n\nI have a question about the preview you built for " . $name . ".\n\n") ?>">
      Not ready? Just reply to my email.
    </a>

    <p class="fd-scarcity">I only take one <?= ho_h(strtolower($catName)) ?> site at a time in <?= ho_h($city) ?>.</p>
  </section>

  <script>
  function fdSelectBundle(card) {
    var addons = JSON.parse(card.dataset.addons || '[]');
    var pkg    = card.dataset.pkg || 'standard';

    document.querySelectorAll('.fd-bundle-card').forEach(function(c) { c.classList.remove('is-selected'); });
    card.classList.add('is-selected');

    var pkgRadio = document.querySelector('input[name="pkg_display"][value="' + pkg + '"]');
    if (pkgRadio) pkgRadio.checked = true;
    var pkgHid = document.getElementById('fd-h-pkg');
    if (pkgHid) pkgHid.value = pkg;

    document.querySelectorAll('.fd-pkg-option').forEach(function(el) {
      var r = el.querySelector('input[type="radio"]'); el.classList.toggle('is-selected', !!(r && r.checked));
    });
    document.querySelectorAll('.fd-addon-list input[type="checkbox"]').forEach(function(cb) {
      var key = cb.dataset.addon;
      cb.checked = addons.indexOf(key) !== -1;
      var hid = document.getElementById('fd-h-' + key);
      if (hid) { hid.disabled = !cb.checked; if (cb.checked) hid.value = key; }
    });

    fdUpdateTotal();
  }

  function fdToggleCustomize() {
    var area = document.getElementById('fd-addon-area');
    var btn  = document.getElementById('fd-customize-btn');
    if (!area) return;
    area.hidden = !area.hidden;
    if (area.hidden) {
      btn.textContent = '+ Customize or add more items';
    } else {
      fdUpdateCustomizeBtn();
    }
  }

  function fdUpdateCustomizeBtn() {
    var btn  = document.getElementById('fd-customize-btn');
    var area = document.getElementById('fd-addon-area');
    if (!btn || !area || area.hidden) return;
    var count = document.querySelectorAll('.fd-addon-list input[type="checkbox"]:checked').length;
    btn.textContent = count > 0
      ? '− ' + count + ' add-on' + (count !== 1 ? 's' : '') + ' selected · hide'
      : '− Hide customization';
  }

  function fdUpdateTotal() {
    var pkg  = document.querySelector('input[name="pkg_display"]:checked');
    var base = pkg ? parseInt(pkg.dataset.price, 10) : <?= $pkgCatalog[$defaultBData['pkg']]['price'] ?>;
    var addons = 0;
    document.querySelectorAll('.fd-addon-list input[type="checkbox"]').forEach(function(cb) {
      if (cb.checked) addons += parseInt(cb.dataset.price || '0', 10);
      var hid = document.getElementById('fd-h-' + (cb.dataset.addon || ''));
      if (hid) { hid.disabled = !cb.checked; if (cb.checked) hid.value = cb.dataset.addon; }
    });
    var totalEl = document.getElementById('fd-pkg-total');
    if (totalEl) {
      totalEl.textContent = '$' + (base + addons).toLocaleString();
      totalEl.classList.remove('fd-total-flash');
      void totalEl.offsetWidth;
      totalEl.classList.add('fd-total-flash');
    }
    var pkgHid = document.getElementById('fd-h-pkg');
    if (pkgHid && pkg) pkgHid.value = pkg.value;
    document.querySelectorAll('.fd-pkg-option').forEach(function(el) {
      var r = el.querySelector('input[type="radio"]'); el.classList.toggle('is-selected', !!(r && r.checked));
    });
    fdUpdateCustomizeBtn();
  }
  </script>

  <footer class="fd-footer">
    <strong><a href="/">Hoosier Online</a></strong><br>
    Front doors for Indiana&rsquo;s hardest-working businesses.<br>
    <span class="fd-footer-by">Built by Adam Ferree &middot; <a href="mailto:adam@hoosieronline.com">adam@hoosieronline.com</a></span>
  </footer>

  <!-- ── STICKY BOTTOM CTA ──────────────────────────────────────────────── -->
  <div class="fd-sticky-bar" id="fd-sticky-bar" hidden>
    <div class="fd-sticky-inner">
      <div>
        <strong><?= ho_h($name) ?></strong>
        <span>Your total: <span id="fd-sticky-total">$<?= number_format($defaultPrice) ?></span></span>
      </div>
      <a href="#pricing" class="fd-btn fd-btn-primary fd-sticky-btn">Get Started &rarr;</a>
    </div>
  </div>
  <script>
  (function(){
    var bar    = document.getElementById('fd-sticky-bar');
    var offer  = document.getElementById('pricing');
    if (!bar || !offer) return;
    var shown = false;
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(e){
        if (e.isIntersecting) {
          bar.hidden = true; shown = false;
        } else if (shown) {
          bar.hidden = false;
        }
      });
    }, {threshold: 0});
    io.observe(offer);
    window.addEventListener('scroll', function(){
      if (!shown && window.scrollY > 300) { shown = true; bar.hidden = offer.getBoundingClientRect().top < window.innerHeight; }
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
