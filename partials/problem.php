<section class="section problem">
    <p class="eyebrow">The problem</p>
    <h2><?= e($section['headline'] ?? '') ?></h2>
    <div class="problem-grid">
        <?php foreach (($section['items'] ?? []) as $item): ?>
            <div class="problem-item"><?= e($item) ?></div>
        <?php endforeach; ?>
    </div>
</section>
