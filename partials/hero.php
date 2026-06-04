<section class="hero section">
    <div class="hero-copy">
        <p class="eyebrow"><?= e($section['eyebrow'] ?? '') ?></p>
        <h1><?= e($section['headline'] ?? '') ?></h1>
        <p><?= e($section['body'] ?? '') ?></p>
        <div class="cta-row">
            <a class="button primary" href="<?= e($section['primary_href'] ?? '#') ?>"><?= e($section['primary_cta'] ?? 'Start') ?></a>
            <a class="button secondary" href="<?= e($section['secondary_href'] ?? '#') ?>"><?= e($section['secondary_cta'] ?? 'Learn more') ?></a>
        </div>
    </div>
    <div class="hero-panel">
        <span>Quote-ready link</span>
        <strong>Local customer → clear request → booked follow-up</strong>
        <p>No bloated funnel. No fake agency voice. Just the shortest path from interest to action.</p>
    </div>
</section>
