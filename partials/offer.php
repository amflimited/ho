<section class="section offer" id="offer">
    <p class="eyebrow">The offer</p>
    <h2><?= e($section['headline'] ?? '') ?></h2>
    <p class="section-lede"><?= e($section['body'] ?? '') ?></p>
    <div class="card-grid">
        <?php foreach (($section['cards'] ?? []) as $card): ?>
            <article class="card">
                <h3><?= e($card['title'] ?? '') ?></h3>
                <p><?= e($card['text'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
