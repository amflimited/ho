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
<link rel="stylesheet" href="/assets/css/front-door.css?v=<?= filemtime(__DIR__ . '/assets/css/front-door.css') ?>">
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

  <!-- ── THE TURN ─────────────────────────────────────────────────────────── -->
  <section class="fd-turn">
    <h2>This is your new front door, <?= ho_h($name) ?>&hellip;</h2>
    <p>Pick the style that feels right. We&rsquo;ll build it exactly that way &mdash; clean, fast, and made for a phone, because that&rsquo;s where your customers are looking.</p>
  </section>

  <!-- ══ DESIGN PREVIEW — phone frame + template picker ══════════════════════ -->
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
  <p class="fd-tpl-intro">We picked <strong><?= ho_h($design['name'] ?: 'Classic') ?></strong> for your category. Tap any style to see it on your page.</p>
  <?php else: ?>
  <p class="fd-tpl-intro">Pick the style that fits your brand. Tap any option to preview it.</p>
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
  <p class="fd-phone-hint">Scroll inside the preview &uarr;</p>

  <script>
  (function(){
    var tabs   = document.querySelectorAll('.fd-tpl-tab');
    var screen = document.getElementById('fd-phone-screen');
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
