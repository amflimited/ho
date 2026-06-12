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
 * THE BOOT SEQUENCE — a 2.5-second build-console overlay that types out what
 * the machine actually did for this business (real review counts, the real
 * competitor, their town) before the page lifts into view. The prospect's
 * first impression is the machine working for them, live.
 *
 * Plays once per browser session per $key (sessionStorage). Self-disables for
 * repeat views, JS-off (markup is hidden until JS reveals it), and
 * prefers-reduced-motion. A tap skips it instantly. Callers must NOT render
 * it on paid/error views — never stand between a buyer and the page.
 *
 *   $lines — console lines in order; empty/blank entries are dropped.
 *   $key   — sessionStorage discriminator, usually the business slug.
 */
function ho_fd_boot(array $lines, string $key): void {
    $lines = array_values(array_filter(array_map('strval', $lines), fn($l) => trim($l) !== ''));
    if (empty($lines)) return;
    $storeKey = 'fdBoot-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    ?>
<div class="fd-boot" id="fdBoot" hidden>
  <div class="fd-boot-inner">
    <p class="fd-boot-brand">&#x2B22; HOOSIER ONLINE &middot; BUILD CONSOLE</p>
    <div class="fd-boot-lines" id="fdBootLines"></div>
    <p class="fd-boot-skip">tap anywhere to skip</p>
  </div>
</div>
<script>
(function(){
  var KEY = <?= json_encode($storeKey) ?>;
  try { if (sessionStorage.getItem(KEY)) return; } catch (e) { return; }
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  var boot = document.getElementById('fdBoot');
  if (!boot) return;
  var LINES = <?= json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  boot.hidden = false;
  document.documentElement.classList.add('fd-boot-lock');
  var box = document.getElementById('fdBootLines'), i = 0, finished = false;
  function fin() {
    if (finished) return;
    finished = true;
    try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
    boot.classList.add('fd-boot-done');
    document.documentElement.classList.remove('fd-boot-lock');
    document.documentElement.classList.add('fd-landed');
    setTimeout(function(){ boot.remove(); }, 700);
  }
  function step() {
    if (finished) return;
    if (i >= LINES.length) { setTimeout(fin, 480); return; }
    var p = document.createElement('p');
    p.className = 'fd-boot-line';
    p.textContent = LINES[i];
    box.appendChild(p);
    i++;
    setTimeout(step, i === LINES.length ? 560 : 400);
  }
  boot.addEventListener('click', fin);
  setTimeout(step, 280);
  setTimeout(fin, 7000); // hard ceiling — never trap anyone
})();
</script>
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
