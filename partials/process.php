<section class="section process">
    <p class="eyebrow">Process</p>
    <h2><?= e($section['headline'] ?? '') ?></h2>
    <div class="steps">
        <?php foreach (($section['steps'] ?? []) as $step): ?>
            <article class="step">
                <span><?= e($step['label'] ?? '') ?></span>
                <h3><?= e($step['title'] ?? '') ?></h3>
                <p><?= e($step['text'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>
