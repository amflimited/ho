<?php /* Junk Removal — Green Work Truck */ ?>
<?php
function jrt_badge(string $name): string {
    $n = strtolower($name);
    if (preg_match('/furni|sofa|couch|chair|table|desk/', $n))        return '🛋';
    if (preg_match('/applian|washer|dryer|fridge|refrig|stove|oven/', $n)) return '🔌';
    if (preg_match('/yard|garden|brush|lawn|tree|shrub|limb/', $n))   return '🌿';
    if (preg_match('/demo|destruct|drywall|brick|concrete|tear/', $n)) return '🏗';
    if (preg_match('/recycl|metal|scrap|copper|iron|steel/', $n))     return '♻️';
    if (preg_match('/electron|tv|computer|tech|e-waste/', $n))        return '📺';
    if (preg_match('/mattress|bed|box spring/', $n))                   return '🛏';
    if (preg_match('/estate|cleanout|hoard/', $n))                     return '🏠';
    if (preg_match('/hot tub|spa|pool/', $n))                          return '🛁';
    if (preg_match('/haul|pickup|truck|transport/', $n))               return '🚛';
    return '🗑';
}
function jrt_photo(string $name): string {
    $b = 'https://images.unsplash.com/';
    $n = strtolower($name);
    if (preg_match('/furni|sofa|couch|chair|table|desk/', $n))
        return $b.'photo-1555041469-a586c61ea9bc?w=400&h=240&fit=crop&auto=format&q=75';
    if (preg_match('/applian|washer|dryer|fridge|refrig|stove|oven/', $n))
        return $b.'photo-1556909114-f6e7ad7d3136?w=400&h=240&fit=crop&auto=format&q=75';
    if (preg_match('/yard|garden|brush|lawn|tree|shrub|limb/', $n))
        return $b.'photo-1416879595882-3373a0480b5b?w=400&h=240&fit=crop&auto=format&q=75';
    if (preg_match('/demo|destruct|drywall|brick|concrete|tear/', $n))
        return $b.'photo-1504307651254-35680f356dfd?w=400&h=240&fit=crop&auto=format&q=75';
    if (preg_match('/recycl|metal|scrap|copper|iron|steel/', $n))
        return $b.'photo-1532996122724-e3c354a0b15b?w=400&h=240&fit=crop&auto=format&q=75';
    if (preg_match('/electron|tv|computer|tech/', $n))
        return $b.'photo-1518770660439-4636190af475?w=400&h=240&fit=crop&auto=format&q=75';
    if (preg_match('/mattress|bed/', $n))
        return $b.'photo-1505693416388-ac5ce068fe85?w=400&h=240&fit=crop&auto=format&q=75';
    if (preg_match('/estate|cleanout|hoard/', $n))
        return $b.'photo-1560448204-e02f11c3d0e2?w=400&h=240&fit=crop&auto=format&q=75';
    return $b.'photo-1530587191325-3db32d826c18?w=400&h=240&fit=crop&auto=format&q=75';
}
?>
<section class="fd-mock fd-jrt">

  <div class="fd-jrt-hero">
    <div class="fd-jrt-hero-photo"></div>

    <div class="fd-jrt-topbar">
      <span class="fd-jrt-loc">
        <svg width="9" height="12" viewBox="0 0 10 13" fill="currentColor"><path d="M5 0C2.24 0 0 2.24 0 5c0 3.75 5 8 5 8s5-4.25 5-8C10 2.24 7.76 0 5 0zm0 6.5C4.17 6.5 3.5 5.83 3.5 5S4.17 3.5 5 3.5 6.5 4.17 6.5 5 5.83 6.5 5 6.5z"/></svg>
        JUNK REMOVAL<?= $city !== '' ? ', ' . ho_h(strtoupper($city)) : ', IN' ?>
      </span>
      <?php if ($telRaw !== ''): ?>
        <a class="fd-jrt-phone-btn" href="tel:<?= ho_h($telRaw) ?>" aria-label="Call">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.58.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1C10.61 21 3 13.39 3 4c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.23 1.01L6.6 10.8z"/></svg>
        </a>
      <?php else: ?>
        <span class="fd-jrt-phone-btn" aria-hidden="true">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.58.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1C10.61 21 3 13.39 3 4c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.23 1.01L6.6 10.8z"/></svg>
        </span>
      <?php endif; ?>
    </div>

    <div class="fd-jrt-content">
      <h1 class="fd-jrt-name"><?= ho_h($name) ?></h1>
      <p class="fd-jrt-tagline">Fast. Reliable. Stress-Free. We Haul It All.</p>
      <?php if ($hasGoogle && $googleRating > 0): ?>
        <p class="fd-jrt-rating">
          <span class="fd-stars"><?= str_repeat('★', (int)round($googleRating)) ?></span>
          <span class="fd-jrt-rnum"><?= number_format($googleRating, 1) ?></span>
        </p>
      <?php endif; ?>
      <?php if ($telRaw !== ''): ?>
        <a class="fd-jrt-cta" href="tel:<?= ho_h($telRaw) ?>">Get a Free Estimate &rarr;</a>
      <?php else: ?>
        <a class="fd-jrt-cta" href="#">Get a Free Estimate &rarr;</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($services)): ?>
  <div class="fd-jrt-body">
    <h2 class="fd-jrt-h2">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
      What We Do
    </h2>
    <div class="fd-jrt-grid">
      <?php foreach (array_slice($services, 0, 6) as $svc):
        $photo = jrt_photo((string)$svc);
        $badge = jrt_badge((string)$svc);
      ?>
        <div class="fd-jrt-card" style="background-image:url('<?= $photo ?>')">
          <span class="fd-jrt-badge"><?= $badge ?></span>
          <span class="fd-jrt-card-name"><?= ho_h((string)$svc) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="fd-jrt-trust">
    <div class="fd-jrt-trust-item">
      <span class="fd-jrt-trust-icon">✓</span>
      Free Estimates
    </div>
    <div class="fd-jrt-trust-item">
      <span class="fd-jrt-trust-icon">⚡</span>
      Same-Day Service
    </div>
    <div class="fd-jrt-trust-item">
      <span class="fd-jrt-trust-icon">🛡</span>
      Licensed &amp; Insured
    </div>
  </div>

</section>
