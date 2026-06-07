<?php /* Junk Removal — Work Truck Dark */ ?>
<section class="fd-mock fd-jrt">

  <div class="fd-jrt-hero">
    <svg class="fd-jrt-truck" aria-hidden="true" viewBox="0 0 240 120" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="4" y="42" width="138" height="62" rx="5" fill="rgba(255,255,255,.055)"/>
      <rect x="142" y="54" width="78" height="50" rx="5" fill="rgba(255,255,255,.075)"/>
      <rect x="148" y="60" width="64" height="30" rx="3" fill="rgba(255,255,255,.04)"/>
      <rect x="4" y="60" width="138" height="3" fill="rgba(224,123,18,.40)"/>
      <circle cx="38"  cy="107" r="10" stroke="rgba(255,255,255,.14)" stroke-width="2.5" fill="rgba(0,0,0,.35)"/>
      <circle cx="38"  cy="107" r="4"  fill="rgba(255,255,255,.12)"/>
      <circle cx="112" cy="107" r="10" stroke="rgba(255,255,255,.14)" stroke-width="2.5" fill="rgba(0,0,0,.35)"/>
      <circle cx="112" cy="107" r="4"  fill="rgba(255,255,255,.12)"/>
      <circle cx="186" cy="107" r="9"  stroke="rgba(255,255,255,.14)" stroke-width="2.5" fill="rgba(0,0,0,.35)"/>
      <circle cx="186" cy="107" r="3.5" fill="rgba(255,255,255,.12)"/>
      <rect x="138" y="28" width="6" height="28" rx="3" fill="rgba(255,255,255,.07)"/>
      <circle cx="220" cy="78" r="5" fill="rgba(255,210,120,.15)"/>
    </svg>
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
      $seeds = ['junk-pile','couch-sofa','laundry-appliance','garden-yard','demolition-house','trash-bin'];
      $icons = ['🗑','🛋','🔌','🌿','🏠','♻'];
      foreach (array_slice($services, 0, 6) as $i => $svc):
        $seed = $seeds[$i % 6];
      ?>
        <div class="fd-jrt-card" style="background-image:url('https://picsum.photos/seed/<?= $seed ?>/300/200?grayscale');background-size:cover;background-position:center">
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
