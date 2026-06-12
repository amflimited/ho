<?php
declare(strict_types=1);

/**
 * Shared front-door chrome — the .fd-nav and .fd-footer that go.php, rep.php,
 * and start.php each used to hand-roll identically. One source so the brand
 * line, viral loop, and markup never drift between the three public pages.
 *
 * All callers already require ho-model.php (for ho_h); guard anyway.
 */

if (!function_exists('ho_h')) {
    function ho_h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

/**
 * Render the top nav.
 *   $opts['cta_href']  — if set (with cta_label), shows the right-side CTA link.
 *   $opts['cta_label'] — CTA text (a trailing " →" is appended).
 */
function ho_fd_nav(array $opts = []): void {
    $ctaHref  = (string)($opts['cta_href']  ?? '');
    $ctaLabel = (string)($opts['cta_label'] ?? '');
    ?>
<nav class="fd-nav">
  <a class="fd-nav-brand" href="/">HOOSIER ONLINE</a>
  <?php if ($ctaHref !== '' && $ctaLabel !== ''): ?><a class="fd-nav-cta" href="<?= ho_h($ctaHref) ?>"><?= ho_h($ctaLabel) ?> &rarr;</a><?php endif; ?>
</nav>
<?php
}

/**
 * Render the footer.
 *   $opts['tagline']    — brand line (default: the hardest-working tagline).
 *   $opts['email']      — contact email (default adam@hoosieronline.com).
 *   $opts['viral_src']  — if set, renders the "build your own page free" loop
 *                         with ?src={viral_src}.
 *   $opts['viral_lead'] — lead text before the link (default "Run a business yourself?").
 *   $opts['viral_link'] — link text (default "Watch your own page build itself free").
 */
function ho_fd_footer(array $opts = []): void {
    $tagline = (string)($opts['tagline'] ?? 'Front doors for Indiana&rsquo;s hardest-working businesses.');
    $email   = (string)($opts['email']   ?? 'adam@hoosieronline.com');
    $viralSrc = (string)($opts['viral_src'] ?? '');
    $viralLead = (string)($opts['viral_lead'] ?? 'Run a business yourself?');
    $viralLink = (string)($opts['viral_link'] ?? 'Watch your own page build itself free');
    ?>
  <footer class="fd-footer">
    <strong><a href="/">Hoosier Online</a></strong><br>
    <?= $tagline ?><br>
    <span class="fd-footer-by">Built by Adam Ferree &middot; <a href="mailto:<?= ho_h($email) ?>"><?= ho_h($email) ?></a></span>
    <?php if ($viralSrc !== ''): ?><span class="fd-footer-viral"><?= ho_h($viralLead) ?> <a href="/start.php?src=<?= ho_h($viralSrc) ?>"><?= ho_h($viralLink) ?> &rarr;</a></span><?php endif; ?>
  </footer>
<?php
}
