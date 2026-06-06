<?php
declare(strict_types=1);

require_once __DIR__ . '/prospect-model.php';

$registryLoadError = null;
try {
    if (file_exists(__DIR__ . '/diagnosis-model.php')) {
        require_once __DIR__ . '/diagnosis-model.php';
    }
    if (file_exists(__DIR__ . '/front-door-preview-model.php')) {
        require_once __DIR__ . '/front-door-preview-model.php';
    } else {
        $registryLoadError = 'Front Door Preview model is missing.';
    }
} catch (Throwable $e) {
    $registryLoadError = $e->getMessage();
}

function ho_go_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$slug = trim((string)($_GET['slug'] ?? ''));
$error = null;
$business = null;
$preview = null;

try {
    if ($registryLoadError) {
        throw new RuntimeException($registryLoadError);
    }
    if ($slug === '') {
        throw new RuntimeException('Missing preview slug.');
    }
    if (!function_exists('ho_front_find_business_by_slug')) {
        throw new RuntimeException('Front Door Preview loader is unavailable.');
    }

    $business = ho_front_find_business_by_slug($slug);
    if (!$business) {
        throw new RuntimeException('Preview not found for this slug.');
    }

    if (!ho_front_business_ready($business)) {
        throw new RuntimeException('This Front Door Preview is not ready yet.');
    }

    $preview = ho_front_assemble($business);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $preview ? ho_go_h($preview['business']['name'] . ' Front Door Preview') : 'Front Door Preview' ?> · Hoosier Online</title>
  <link rel="stylesheet" href="/assets/css/front-door.css">
</head>
<body class="front-door-preview-page">
  <main class="fd-shell">
    <?php if ($error): ?>
      <section class="fd-card fd-hero">
        <p class="fd-kicker">Hoosier Online</p>
        <h1>Preview not ready</h1>
        <p><?= ho_go_h($error) ?></p>
        <p class="fd-muted">This page is a preview route. If you expected to see a business preview, the record may still need diagnosis keys or a ready slug.</p>
        <a class="fd-btn fd-btn-secondary" href="/">Return to Hoosier Online</a>
      </section>
    <?php else: ?>
      <section class="fd-card fd-hero">
        <p class="fd-kicker">Hoosier Online Front Door Preview</p>
        <h1>We Took A Look At <?= ho_go_h($preview['business']['name']) ?></h1>
        <p><?= ho_go_h($preview['intro']) ?></p>
        <div class="fd-meta">
          <span><?= ho_go_h(ucwords($preview['business']['category'])) ?></span>
          <?php if ($preview['business']['location']): ?><span><?= ho_go_h($preview['business']['location']) ?></span><?php endif; ?>
        </div>
      </section>

      <section class="fd-card">
        <p class="fd-kicker">What We Noticed</p>
        <h2>A cleaner customer path would help</h2>
        <p><?= ho_go_h($preview['noticed']) ?></p>
      </section>

      <section class="fd-section">
        <div class="fd-section-head">
          <p class="fd-kicker">Already Helping You</p>
          <h2>What is working</h2>
        </div>
        <div class="fd-grid">
          <?php foreach ($preview['strengths'] as $block): ?>
            <article class="fd-card fd-mini-card">
              <span><?= ho_go_h($block['group']) ?></span>
              <h3><?= ho_go_h($block['headline']) ?></h3>
              <p><?= ho_go_h($block['body']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="fd-section">
        <div class="fd-section-head">
          <p class="fd-kicker">Could Be Costing You Customers</p>
          <h2>Where the path may be harder than it needs to be</h2>
        </div>
        <div class="fd-grid">
          <?php foreach ($preview['weaknesses'] as $block): ?>
            <article class="fd-card fd-mini-card">
              <span><?= ho_go_h($block['group']) ?></span>
              <h3><?= ho_go_h($block['headline']) ?></h3>
              <p><?= ho_go_h($block['body']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="fd-section">
        <div class="fd-section-head">
          <p class="fd-kicker">What We Would Fix First</p>
          <h2>A simple front door, not a giant project</h2>
        </div>
        <div class="fd-grid">
          <?php foreach ($preview['recommendations'] as $block): ?>
            <article class="fd-card fd-mini-card">
              <span><?= ho_go_h($block['group']) ?></span>
              <h3><?= ho_go_h($block['headline']) ?></h3>
              <p><?= ho_go_h($block['body']) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="fd-section">
        <div class="fd-section-head">
          <p class="fd-kicker">Preview Directions</p>
          <h2>Three simple ways this could feel</h2>
        </div>
        <div class="fd-direction-grid">
          <?php foreach ($preview['preview_directions'] as $direction): ?>
            <article class="fd-card fd-direction-card">
              <p class="fd-direction-business"><?= ho_go_h($preview['business']['name']) ?></p>
              <h3><?= ho_go_h($direction['label']) ?></h3>
              <p><?= ho_go_h($direction['description']) ?></p>
              <span><?= ho_go_h($direction['default_cta']) ?></span>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="fd-card fd-offer">
        <p class="fd-kicker">Simple Offer</p>
        <h2><?= ho_go_h($preview['offer']['label'] ?? 'Standard Front Door') ?></h2>
        <strong><?= ho_go_h($preview['offer']['price_label'] ?? '$499 setup') ?></strong>
        <p><?= ho_go_h($preview['offer']['summary'] ?? 'One clean customer-facing front door with services, trust sections, and a clear contact/request path.') ?></p>
        <div class="fd-actions">
          <a class="fd-btn fd-btn-primary" href="mailto:<?= ho_go_h($preview['cta']['email']) ?>?subject=<?= rawurlencode('Front Door Preview for ' . $preview['business']['name']) ?>"><?= ho_go_h($preview['cta']['primary']) ?></a>
          <a class="fd-btn fd-btn-secondary" href="mailto:<?= ho_go_h($preview['cta']['email']) ?>?subject=<?= rawurlencode('Question about my Hoosier Online preview') ?>"><?= ho_go_h($preview['cta']['secondary']) ?></a>
        </div>
        <p class="fd-muted">No pressure. This is just a simple look at how your online front door could be clearer.</p>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
