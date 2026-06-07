<?php /* Lawn Mowing — Work Truck Dark */ ?>
<section class="fd-mock fd-wtd">

  <div class="fd-wtd-hero">
    <p class="fd-wtd-eyebrow"><?= ho_h($catName) ?><?= $city !== '' ? ' &middot; ' . ho_h($city) . ', IN' : '' ?></p>
    <h1 class="fd-wtd-name"><?= ho_h($name) ?></h1>
    <?php if ($hasGoogle && $googleRating > 0): ?>
      <p class="fd-wtd-rating">
        <span class="fd-stars"><?= str_repeat('★', (int)round($googleRating)) ?></span>
        <?= number_format($googleRating, 1) ?> rating
      </p>
    <?php endif; ?>
    <?php if ($telRaw !== ''): ?>
      <a class="fd-wtd-cta" href="tel:<?= ho_h($telRaw) ?>">Get a Free Estimate</a>
      <p class="fd-wtd-phone"><?= ho_h($telDisplay) ?></p>
    <?php else: ?>
      <a class="fd-wtd-cta" href="#">Get a Free Estimate</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($services)): ?>
  <div class="fd-wtd-body">
    <h2 class="fd-wtd-h2">What We Do</h2>
    <div class="fd-wtd-grid">
      <?php
      $cardBgs = [
        'linear-gradient(160deg,#1e3a1e,#0d1f0d)',
        'linear-gradient(160deg,#2d3a16,#1a2208)',
        'linear-gradient(160deg,#1a3024,#0d1f14)',
        'linear-gradient(160deg,#3a2e10,#211a06)',
        'linear-gradient(160deg,#2a2d16,#181a08)',
        'linear-gradient(160deg,#1e2e1e,#0f1a0f)',
      ];
      foreach (array_slice($services, 0, 6) as $i => $svc):
      ?>
        <div class="fd-wtd-card" style="background:<?= $cardBgs[$i % 6] ?>">
          <span class="fd-wtd-card-name"><?= ho_h((string)$svc) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="fd-wtd-trust">
    <span>Licensed</span>
    <span>Local</span>
    <span>Fast Quotes</span>
  </div>

</section>
