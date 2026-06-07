<?php /* Template: Sharp Modern — Deep navy + electric blue */ ?>
<section class="fd-mock fd-mock--sm">
  <div class="fd-mock-badge">Preview<?= $design['name'] !== '' ? ' &middot; ' . ho_h($design['name']) : '' ?></div>

  <div class="fd-mock-hero">
    <p class="fd-mock-eyebrow"><?= ho_h($catName) ?><?= $city !== '' ? ' &middot; ' . ho_h($city) . ', IN' : '' ?></p>
    <h1 class="fd-mock-name"><?= ho_h($name) ?></h1>

    <?php if ($hasGoogle && $googleRating > 0): ?>
      <div class="fd-mock-stars">
        <span class="fd-stars"><?= str_repeat('★', (int)round($googleRating)) . str_repeat('☆', 5 - (int)round($googleRating)) ?></span>
        <span class="fd-mock-rating"><?= number_format($googleRating, 1) ?><?= $googleCount > 0 ? ' &middot; ' . $googleCount . ' reviews' : '' ?></span>
      </div>
    <?php endif; ?>

    <p class="fd-mock-area">Serving <?= ho_h($serviceArea) ?></p>

    <?php if ($telRaw !== ''): ?>
      <a class="fd-mock-cta" href="tel:<?= ho_h($telRaw) ?>">Book Your Appointment</a>
      <p class="fd-mock-phone"><?= ho_h($telDisplay) ?></p>
    <?php else: ?>
      <a class="fd-mock-cta" href="#">Schedule a Consultation</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($services)): ?>
  <div class="fd-mock-services">
    <h2 class="fd-mock-h2">What We Do</h2>
    <div class="fd-mock-service-grid">
      <?php foreach (array_slice($services, 0, 6) as $svc): ?>
        <div class="fd-mock-service"><?= ho_h((string)$svc) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="fd-mock-trust">
    <span>Premium Finish</span>
    <span>Detail-Oriented</span>
    <span>Indiana Proud</span>
  </div>
</section>
