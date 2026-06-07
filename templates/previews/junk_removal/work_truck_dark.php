<?php /* Junk Removal — Work Truck Dark (image + overlay) */ ?>
<section class="fd-mock fd-jrt">
  <div class="fd-jrt-wrap">
    <img src="/assets/img/tpl/jrt-work-truck.png" class="fd-jrt-img" alt="Junk removal website preview">

    <?php if ($city !== ''): ?>
      <div class="fd-jrt-ov fd-jrt-city"><?= ho_h(strtoupper($city)) ?>, IN</div>
    <?php endif; ?>

    <div class="fd-jrt-ov fd-jrt-name"><?= ho_h($name) ?></div>

    <?php if ($hasGoogle && $googleRating > 0): ?>
      <div class="fd-jrt-ov fd-jrt-rating"><?= number_format($googleRating, 1) ?></div>
    <?php endif; ?>

    <?php if ($telRaw !== ''): ?>
      <a class="fd-jrt-ov fd-jrt-tel" href="tel:<?= ho_h($telRaw) ?>"><?= ho_h($telDisplay) ?></a>
    <?php endif; ?>

    <?php foreach (array_slice($services, 0, 6) as $i => $svc): ?>
      <div class="fd-jrt-ov fd-jrt-svc fd-jrt-svc-<?= $i ?>"><?= ho_h((string)$svc) ?></div>
    <?php endforeach; ?>
  </div>
</section>
