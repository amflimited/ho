<?php /* Junk Removal — Work Truck Dark */ ?>
<section class="fd-mock fd-jrt">

  <div class="fd-jrt-hero">
    <p class="fd-jrt-eyebrow"><?= ho_h($catName) ?><?= $city !== '' ? ' &middot; ' . ho_h($city) . ', IN' : '' ?></p>
    <h1 class="fd-jrt-name"><?= ho_h($name) ?></h1>
    <?php if ($hasGoogle && $googleRating > 0): ?>
      <p class="fd-jrt-rating">
        <span class="fd-stars"><?= str_repeat('★', (int)round($googleRating)) ?></span>
        <?= number_format($googleRating, 1) ?> rating
      </p>
    <?php endif; ?>
    <?php if ($telRaw !== ''): ?>
      <a class="fd-jrt-cta" href="tel:<?= ho_h($telRaw) ?>">Get a Free Estimate</a>
      <p class="fd-jrt-phone"><?= ho_h($telDisplay) ?></p>
    <?php else: ?>
      <a class="fd-jrt-cta" href="#">Get a Free Estimate</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($services)): ?>
  <div class="fd-jrt-body">
    <h2 class="fd-jrt-h2">What We Do</h2>
    <div class="fd-jrt-grid">
      <?php
      $cardBgs = [
        'linear-gradient(135deg,#2a2520,#1a1510)',
        'linear-gradient(135deg,#3a2a18,#241a0e)',
        'linear-gradient(135deg,#1e2530,#12181f)',
        'linear-gradient(135deg,#1e2a14,#121a0c)',
        'linear-gradient(135deg,#2a2015,#1a140d)',
        'linear-gradient(135deg,#1a2020,#101414)',
      ];
      $icons = ['🗑️','🪑','🔌','🌿','🏠','♻️'];
      foreach (array_slice($services, 0, 6) as $i => $svc):
      ?>
        <div class="fd-jrt-card" style="background:<?= $cardBgs[$i % 6] ?>">
          <span class="fd-jrt-icon"><?= $icons[$i % 6] ?></span>
          <span class="fd-jrt-card-name"><?= ho_h((string)$svc) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="fd-jrt-trust">
    <span>✓ Free Estimates</span>
    <span>✓ Same-Day Service</span>
    <span>✓ Licensed &amp; Insured</span>
  </div>

</section>
