<?php /* Junk Removal — Work Truck Dark */ ?>
<?php
function jrt_svc_style(string $name): array {
    $n = strtolower($name);
    if (preg_match('/furni|sofa|couch|chair|table|desk|loveseat/', $n))
        return ['icon'=>'🛋','bg'=>'linear-gradient(150deg,#3d2a14 0%,#1e1408 100%)','glow'=>'rgba(200,100,10,.28)'];
    if (preg_match('/applian|washer|dryer|fridge|refrig|stove|oven|dishwash|microwave/', $n))
        return ['icon'=>'🔌','bg'=>'linear-gradient(150deg,#18253a 0%,#0e1620 100%)','glow'=>'rgba(40,100,220,.24)'];
    if (preg_match('/yard|garden|brush|lawn|tree|shrub|limb|branch|green waste/', $n))
        return ['icon'=>'🌿','bg'=>'linear-gradient(150deg,#1a2e14 0%,#0e1c09 100%)','glow'=>'rgba(40,150,40,.22)'];
    if (preg_match('/demo|destruct|construct|drywall|brick|concrete|tear/', $n))
        return ['icon'=>'🏗','bg'=>'linear-gradient(150deg,#2e2018 0%,#1a130a 100%)','glow'=>'rgba(200,120,20,.24)'];
    if (preg_match('/recycl|metal|scrap|copper|iron|steel|aluminum/', $n))
        return ['icon'=>'♻️','bg'=>'linear-gradient(150deg,#182828 0%,#0e1a1a 100%)','glow'=>'rgba(20,160,110,.20)'];
    if (preg_match('/electron|tv|computer|monitor|tech|phone|e-waste/', $n))
        return ['icon'=>'📺','bg'=>'linear-gradient(150deg,#182035 0%,#0f1525 100%)','glow'=>'rgba(50,90,220,.22)'];
    if (preg_match('/mattress|bed|sleep|box spring/', $n))
        return ['icon'=>'🛏','bg'=>'linear-gradient(150deg,#2e2520 0%,#1b1710 100%)','glow'=>'rgba(160,90,20,.22)'];
    if (preg_match('/estate|cleanout|clean|hoard/', $n))
        return ['icon'=>'🏠','bg'=>'linear-gradient(150deg,#1c2820 0%,#111a14 100%)','glow'=>'rgba(30,140,70,.20)'];
    if (preg_match('/haul|pickup|truck|transport/', $n))
        return ['icon'=>'🚛','bg'=>'linear-gradient(150deg,#28201a 0%,#181410 100%)','glow'=>'rgba(220,110,10,.22)'];
    if (preg_match('/hot tub|spa|pool/', $n))
        return ['icon'=>'🛁','bg'=>'linear-gradient(150deg,#182030 0%,#0f151e 100%)','glow'=>'rgba(20,120,200,.20)'];
    return ['icon'=>'🗑','bg'=>'linear-gradient(150deg,#2a2520 0%,#181510 100%)','glow'=>'rgba(224,123,18,.18)'];
}
?>
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
      <?php foreach (array_slice($services, 0, 6) as $svc):
        $s = jrt_svc_style((string)$svc);
      ?>
        <div class="fd-jrt-card" style="background:<?= $s['bg'] ?>">
          <div class="fd-jrt-card-glow" style="background:<?= $s['glow'] ?>"></div>
          <span class="fd-jrt-icon"><?= $s['icon'] ?></span>
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
