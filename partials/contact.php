<section class="section contact" id="contact">
    <p class="eyebrow">First move</p>
    <h2><?= e($section['headline'] ?? '') ?></h2>
    <p><?= e($section['body'] ?? '') ?></p>
    <a class="button primary" href="<?= e($section['href'] ?? '#') ?>"><?= e($section['button'] ?? 'Contact') ?></a>
</section>
