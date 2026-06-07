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

// ── Stripe Payment Links ── replace these with your actual Stripe URLs ────
$stripeStandard = 'https://buy.stripe.com/REPLACE_ME_STANDARD';
$stripeManaged  = 'https://buy.stripe.com/REPLACE_ME_MANAGED';
// ── Your contact info ─────────────────────────────────────────────────────
$adamPhone = '(765) 443-4321'; // e.g. '(765) 555-0100' — set this to show on the preview page

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= ho_h($pageTitle) ?></title>
  <link rel="icon" href="/favicon.ico">
<link rel="stylesheet" href="/assets/css/front-door.css?v=<?= filemtime(__DIR__ . '/assets/css/front-door.css') ?>">
  <meta name="robots" content="noindex">
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

  <!-- ── THE TURN ─────────────────────────────────────────────────────────── -->
  <section class="fd-turn">
    <p class="fd-turn-eyebrow"><?= ho_h($catName) ?> &middot; <?= ho_h($city) ?>, IN</p>
    <h1 class="fd-turn-name"><?= ho_h($name) ?></h1>
    <p class="fd-turn-tag">This is your new front door.</p>
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

  <?php if (!$usingCatTpls): ?>
  <p class="fd-tpl-intro">We defaulted to <strong><?= ho_h($design['name'] ?: 'Classic') ?></strong> &mdash; tap any style to switch.</p>
  <?php endif; ?>

  <?php if (!empty($available)): ?>

  <div class="fd-tpl-picker">
    <?php foreach ($available as $k => $opt): ?>
      <button class="fd-tpl-tab<?= $k === $templateKey ? ' fd-tpl-tab--active' : '' ?>" data-tpl="<?= ho_h($k) ?>">
        <span class="fd-tpl-dot" style="background:<?= ho_h($opt['color']) ?>"></span>
        <?= ho_h($opt['label']) ?>
      </button>
    <?php endforeach; ?>
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
  <p class="fd-phone-hint">Scroll inside &uarr;</p>

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


  <!-- ── WHY THIS BUSINESS ───────────────────────────────────────────────── -->
  <section class="fd-card">
    <p class="fd-kicker">What we found</p>
    <?php if ($angle !== ''): ?>
      <p class="fd-why-angle"><?= ho_h($angle) ?></p>
    <?php endif; ?>
    <?php if (!empty($opp)): ?>
      <p class="fd-why"><?= ho_h($opp) ?></p>
    <?php endif; ?>
    <?php if (!empty($gaps)): ?>
      <div class="fd-gap-list">
        <?php foreach (array_slice($gaps, 0, 3) as $g): ?>
          <div class="fd-gap-item"><span class="fd-gap-marker"></span><?= ho_h((string)$g) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>


  <!-- ── WHO BUILT THIS ───────────────────────────────────────────────────── -->
  <section class="fd-card fd-trust" id="about">
    <p class="fd-kicker">Who&rsquo;s behind this</p>
    <h2>Adam F.</h2>
    <p>Web developer from Newcastle, Indiana. I find local service businesses that are doing good work but flying under the radar online &mdash; and I build them a real front door. No sales team, no cold-call scripts, no mystery pricing.</p>
    <ul class="fd-trust-signals">
      <li>Originally from Newcastle, Indiana</li>
      <li>Every preview researched and built personally</li>
      <li>Plain pricing. No surprise fees.</li>
    </ul>
    <div class="fd-trust-contact">
      <a href="mailto:adam@hoosiersonline.com">adam@hoosiersonline.com</a>
      <?php if ($adamPhone !== ''): ?>
        <span class="fd-trust-sep">&middot;</span>
        <a href="tel:<?= ho_h(preg_replace('/\D/', '', $adamPhone)) ?>"><?= ho_h($adamPhone) ?></a>
      <?php endif; ?>
    </div>
  </section>

  <!-- ── WHAT YOU GET (modules) ───────────────────────────────────────────── -->
  <section class="fd-section" id="services">
    <div class="fd-section-head">
      <p class="fd-kicker">What We Build</p>
      <h2>Five things, done right.</h2>
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
    <p class="fd-kicker">Every Front Door Includes</p>
    <h2>The full build. Nothing held back.</h2>
    <ul class="fd-feature-list">
      <?php foreach ($features as $f): ?>
        <li><?= ho_h($f) ?></li>
      <?php endforeach; ?>
    </ul>
  </section>

  <!-- ── YOUR WEB ADDRESS ─────────────────────────────────────────────────── -->
  <section class="fd-card fd-domain">
    <p class="fd-kicker">Your Web Address</p>
    <h2>Yours the day we launch.</h2>
    <p class="fd-domain-name"><?= ho_h($subdomain) ?></p>
    <p class="fd-muted">Included with your Front Door. Want your own domain &mdash; like <strong><?= ho_h(str_replace('.hoosieronline.com', '.com', $subdomain)) ?></strong>? We handle that too.</p>
  </section>

  <!-- ── PACKAGE CONFIGURATOR ────────────────────────────────────────────── -->
  <section class="fd-card fd-offer" id="pricing">
    <p class="fd-kicker">Build Your Package</p>
    <h2>Pick what you need.</h2>

    <div class="fd-pkg-builder">

      <!-- Base options -->
      <div class="fd-pkg-options">
        <label class="fd-pkg-option<?= !$isManaged ? ' is-selected' : '' ?>">
          <input type="radio" name="pkg" value="499"<?= !$isManaged ? ' checked' : '' ?> onchange="fdUpdatePkg()">
          <div class="fd-pkg-option-body">
            <div class="fd-pkg-option-head">
              <strong>Front Door</strong>
              <span class="fd-pkg-price-tag">$499</span>
            </div>
            <p>Built and launched. Your site goes live within a week. 1 year of hosting included.</p>
          </div>
        </label>
        <label class="fd-pkg-option<?= $isManaged ? ' is-selected' : '' ?>">
          <input type="radio" name="pkg" value="999"<?= $isManaged ? ' checked' : '' ?> onchange="fdUpdatePkg()">
          <div class="fd-pkg-option-body">
            <div class="fd-pkg-option-head">
              <strong>Managed Front Door</strong>
              <span class="fd-pkg-price-tag">$999</span>
            </div>
            <p>Everything in Front Door, plus we keep it current &mdash; 3 months of updates included, then $250/quarter.</p>
          </div>
        </label>
      </div>

      <!-- Add-ons -->
      <p class="fd-addon-label">Add-ons &mdash; bolt on what you want:</p>
      <div class="fd-addon-list">
        <label class="fd-addon-item">
          <input type="checkbox" data-price="75" onchange="fdUpdatePkg()">
          <div class="fd-addon-body">
            <strong>Custom Domain</strong> <span class="fd-addon-price">+$75/yr</span>
            <p>Your own .com (e.g., <?= ho_h(str_replace('.hoosieronline.com', '.com', $subdomain)) ?>) instead of .hoosieronline.com</p>
          </div>
        </label>
        <label class="fd-addon-item">
          <input type="checkbox" data-price="75" onchange="fdUpdatePkg()">
          <div class="fd-addon-body">
            <strong>Google Business Setup</strong> <span class="fd-addon-price">+$75</span>
            <p>Full profile optimization &amp; verification &mdash; done right, once.</p>
          </div>
        </label>
        <label class="fd-addon-item">
          <input type="checkbox" data-price="150" onchange="fdUpdatePkg()">
          <div class="fd-addon-body">
            <strong>Logo / Wordmark</strong> <span class="fd-addon-price">+$150</span>
            <p>Simple, clean logo design. Yours to keep forever.</p>
          </div>
        </label>
        <label class="fd-addon-item">
          <input type="checkbox" data-price="75" onchange="fdUpdatePkg()">
          <div class="fd-addon-body">
            <strong>Social Profile Setup</strong> <span class="fd-addon-price">+$75</span>
            <p>Facebook &amp; Instagram &mdash; properly branded and linked to your site.</p>
          </div>
        </label>
      </div>

      <!-- Running total -->
      <div class="fd-total-row">
        <span>Your total:</span>
        <strong id="fd-pkg-total"><?= $isManaged ? '$999' : '$499' ?></strong>
      </div>
      <p class="fd-total-note" id="fd-addon-note" style="display:none">Add-ons are invoiced separately after your Front Door launches.</p>

      <!-- CTAs -->
      <a class="fd-btn fd-btn-primary fd-stripe-btn" id="fd-buy-btn"
         href="<?= ho_h($isManaged ? $stripeManaged : $stripeStandard) ?>">
        Get Started &mdash; Pay Now &rarr;
      </a>
      <a class="fd-btn fd-btn-secondary"
         href="mailto:adam@hoosiersonline.com?subject=<?= rawurlencode('Question about my preview — ' . $name) ?>">
        Have Questions?
      </a>

    </div>

    <p class="fd-offer-guarantee">Not happy after we launch? Full refund within 30 days &mdash; no questions.</p>
  </section>

  <script>
  var FD_STRIPE_STANDARD = <?= json_encode($stripeStandard) ?>;
  var FD_STRIPE_MANAGED  = <?= json_encode($stripeManaged) ?>;
  function fdUpdatePkg() {
    var pkg = document.querySelector('input[name="pkg"]:checked');
    var base = pkg ? parseInt(pkg.value, 10) : 499;
    var addons = 0;
    document.querySelectorAll('.fd-addon-list input[type="checkbox"]').forEach(function(cb) {
      if (cb.checked) addons += parseInt(cb.dataset.price || '0', 10);
    });
    document.getElementById('fd-pkg-total').textContent = '$' + (base + addons).toLocaleString();
    document.getElementById('fd-buy-btn').href = base >= 999 ? FD_STRIPE_MANAGED : FD_STRIPE_STANDARD;
    var note = document.getElementById('fd-addon-note');
    if (note) note.style.display = addons > 0 ? '' : 'none';
    document.querySelectorAll('.fd-pkg-option').forEach(function(el) {
      var r = el.querySelector('input[type="radio"]');
      el.classList.toggle('is-selected', !!(r && r.checked));
    });
  }
  </script>

  <footer class="fd-footer">
    <strong><a href="/">Hoosier Online</a></strong><br>
    Front doors for Indiana&rsquo;s hardest-working businesses.<br>
    <span class="fd-footer-by">Built by Adam F. &middot; <a href="mailto:adam@hoosiersonline.com">adam@hoosiersonline.com</a></span>
  </footer>

<?php endif; ?>
</main>
</body>
</html>
