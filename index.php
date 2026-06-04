<?php
// Hoosier Online public homepage.
// Self-contained marketing page using shared design tokens from /assets/css/site.css.
// Copy is grounded in content/home.php, product.php, and the salesphilosophy "plain pitch".
// No fabricated testimonials or stats: the business is pre-launch.

$email = 'hello@hoosieronline.com';
$mailto = 'mailto:' . $email . '?subject=' . rawurlencode('Hoosier Online Front Door');

// The 7 customer-facing benefits mirror the system's me_categories model.
$frontDoor = [
    ['k' => 'Find you',          't' => 'Show up when a local customer searches, and give them one clean place to land.'],
    ['k' => 'Trust you',         't' => 'Look active, real, and worth hiring — not abandoned or amateur.'],
    ['k' => 'See what you do',    't' => 'Your services, work, and photos laid out so people get it in seconds.'],
    ['k' => 'Reach you',         't' => 'One obvious way to call, message, or send a request. No guessing.'],
    ['k' => 'Book you',          't' => 'A simple path to request a quote, an estimate, or a time that works.'],
    ['k' => 'Pay you',           't' => 'Take a deposit or payment when it makes sense, without the awkward back-and-forth.'],
    ['k' => 'Clean up the mess', 't' => 'Fix the dead links, stale posts, and scattered profiles dragging you down.'],
];

$problems = [
    'Customers ask a vague question, then quietly disappear.',
    'Calls, texts, and Facebook messages get scattered everywhere.',
    'An old website looks dead — or makes people work too hard.',
    'Good operators lose jobs because the next step isn’t obvious.',
];

$steps = [
    ['n' => '1', 'h' => 'We clarify your offer', 't' => 'What jobs you want, where you work, and what a customer needs to know first.'],
    ['n' => '2', 'h' => 'We build your front door', 't' => 'A clean page with the right questions, trust points, and a clear call to action.'],
    ['n' => '3', 'h' => 'You share one link', 't' => 'Put it on Facebook, your Google profile, business cards, and text replies.'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Hoosier Online — A clean online front door for local Indiana businesses</title>
  <meta name="description" content="Hoosier Online builds a simple, trustworthy online front door for local Indiana service businesses — so customers can find you, trust you, contact you, book you, and pay you. Flat pricing, no agency runaround.">
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="/assets/css/site.css?v=012-mobile-fix">
  <style>
    /* ---- Page scaffold ---- */
    .ho-home { --maxw: 1140px; }
    .ho-wrap { width: min(var(--maxw), calc(100% - 40px)); margin-inline: auto; }
    .ho-section { padding: clamp(56px, 9vw, 116px) 0; }
    .ho-eyebrow {
      margin: 0 0 14px; color: var(--ho-primary);
      font-weight: 900; font-size: 12px; letter-spacing: .2em; text-transform: uppercase;
    }
    .ho-h2 {
      margin: 0; font-family: var(--ho-font-display); text-transform: uppercase;
      font-size: clamp(34px, 5.2vw, 60px); line-height: .92; letter-spacing: .02em;
    }
    .ho-lede { max-width: 58ch; margin: 18px 0 0; color: var(--ho-muted); font-size: clamp(17px, 1.5vw, 20px); }

    /* ---- Header ---- */
    .ho-head {
      position: sticky; top: 0; z-index: 40;
      backdrop-filter: saturate(140%) blur(10px);
      background: rgba(247,243,232,.78);
      border-bottom: 1px solid rgba(216,199,178,.6);
    }
    .ho-head-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; height: 68px; }
    .ho-brand { display: inline-flex; align-items: center; }
    .ho-brand img { height: 34px; width: auto; display: block; }
    .ho-nav { display: flex; align-items: center; gap: 8px; }
    .ho-nav a {
      color: var(--ho-text); text-decoration: none; font-weight: 750; font-size: 15px;
      padding: 8px 12px; border-radius: 999px;
    }
    .ho-nav a:hover { background: rgba(255,255,255,.7); color: var(--ho-text); }
    .ho-nav .ho-nav-cta {
      background: var(--ho-primary); color: #fff; font-weight: 850;
      border: 1px solid var(--ho-primary);
    }
    .ho-nav .ho-nav-cta:hover { background: var(--ho-primary-deep); color: #fff; }
    .ho-nav-links { display: contents; }

    /* ---- Buttons ---- */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 9px;
      min-height: 52px; padding: 0 24px; border-radius: 999px;
      font: inherit; font-weight: 850; font-size: 16px; letter-spacing: .01em;
      text-decoration: none; cursor: pointer; border: 1px solid transparent;
      transition: transform 160ms var(--ho-ease), box-shadow 160ms var(--ho-ease), background 160ms var(--ho-ease);
    }
    .btn-primary { background: var(--ho-primary); color: #fff; border-color: var(--ho-primary); box-shadow: 0 12px 30px rgba(177,18,23,.22); }
    .btn-primary:hover { background: var(--ho-primary-deep); color: #fff; transform: translateY(-2px); box-shadow: 0 16px 38px rgba(177,18,23,.28); }
    .btn-ghost { background: rgba(255,255,255,.72); color: var(--ho-text); border-color: var(--ho-border); }
    .btn-ghost:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(24,22,19,.10); color: var(--ho-text); }
    .btn .arrow { transition: transform 160ms var(--ho-ease); }
    .btn:hover .arrow { transform: translateX(3px); }

    /* ---- Hero ---- */
    .ho-hero { position: relative; overflow: hidden; }
    .ho-hero-grid {
      display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(0, .85fr);
      gap: clamp(28px, 5vw, 64px); align-items: center;
    }
    .ho-hero h1 {
      margin: 0; font-family: var(--ho-font-display); text-transform: uppercase;
      font-size: clamp(48px, 8vw, 96px); line-height: .86; letter-spacing: .02em;
    }
    .ho-hero h1 em { color: var(--ho-primary); font-style: normal; }
    .ho-hero-lede { max-width: 52ch; margin: 22px 0 0; color: var(--ho-muted); font-size: clamp(18px, 1.7vw, 21px); }
    .ho-hero-cta { margin-top: 30px; display: flex; flex-wrap: wrap; gap: 12px; }
    .ho-hero-note { margin: 20px 0 0; display: inline-flex; align-items: center; gap: 9px; color: var(--ho-secondary); font-weight: 750; font-size: 15px; }
    .ho-hero-note .dot { width: 9px; height: 9px; border-radius: 999px; background: var(--ho-secondary); box-shadow: 0 0 0 4px rgba(46,91,52,.16); }

    /* Hero visual: a stylized "front door" card */
    .ho-door {
      position: relative; border-radius: 28px; padding: 24px;
      background:
        radial-gradient(circle at 50% 0%, rgba(242,176,30,.20), transparent 60%),
        linear-gradient(160deg, rgba(255,255,255,.96), rgba(255,253,245,.86));
      border: 1px solid var(--ho-border); box-shadow: 0 30px 80px rgba(24,22,19,.14);
    }
    .ho-door-top { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
    .ho-door-top span { width: 11px; height: 11px; border-radius: 999px; background: var(--ho-border); }
    .ho-door-top span.r { background: var(--ho-primary); }
    .ho-door-top span.g { background: var(--ho-accent); }
    .ho-door-top span.b { background: var(--ho-secondary); }
    .ho-door-url { margin-left: auto; font-size: 12px; font-weight: 800; color: var(--ho-muted); letter-spacing: .02em; }
    .ho-door-screen { border-radius: 18px; border: 1px solid var(--ho-border); background: #fff; overflow: hidden; }
    .ho-door-banner {
      padding: 22px 20px; color: #fff;
      background: linear-gradient(135deg, var(--ho-primary), var(--ho-primary-deep));
    }
    .ho-door-banner b { display: block; font-family: var(--ho-font-display); font-size: 30px; line-height: .95; letter-spacing: .03em; text-transform: uppercase; }
    .ho-door-banner span { font-size: 14px; opacity: .92; }
    .ho-door-rows { padding: 16px 18px 20px; display: grid; gap: 10px; }
    .ho-door-row { display: flex; align-items: center; gap: 12px; }
    .ho-door-row i {
      flex: none; width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center;
      background: rgba(46,91,52,.12); color: var(--ho-secondary); font-style: normal; font-weight: 900; font-size: 15px;
    }
    .ho-door-row p { margin: 0; font-size: 14px; font-weight: 650; }
    .ho-door-btn {
      margin: 4px 18px 18px; text-align: center; padding: 13px; border-radius: 12px;
      background: var(--ho-accent); color: var(--ho-text); font-weight: 900; font-size: 15px;
      letter-spacing: .02em;
    }

    /* ---- Problem ---- */
    .ho-problem { background: linear-gradient(180deg, transparent, rgba(255,253,245,.6)); }
    .ho-problem-grid { margin-top: clamp(28px, 4vw, 44px); display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 14px; }
    .ho-problem-item {
      padding: 22px 22px 22px 56px; position: relative; border-radius: 18px;
      background: rgba(255,255,255,.7); border: 1px solid var(--ho-border); font-size: 17px; font-weight: 600;
    }
    .ho-problem-item::before {
      content: "✕"; position: absolute; left: 20px; top: 21px;
      color: var(--ho-primary); font-weight: 900; font-size: 18px;
    }

    /* ---- Front door (what you get) ---- */
    .ho-doorgrid { margin-top: clamp(30px, 4vw, 48px); display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; }
    .ho-feat {
      padding: 26px 22px; border-radius: 22px; background: var(--ho-surface);
      border: 1px solid var(--ho-border); box-shadow: 0 14px 44px rgba(24,22,19,.06);
      transition: transform 180ms var(--ho-ease), box-shadow 180ms var(--ho-ease);
    }
    .ho-feat:hover { transform: translateY(-4px); box-shadow: 0 22px 56px rgba(24,22,19,.12); }
    .ho-feat-tag {
      display: inline-flex; align-items: center; justify-content: center; height: 38px; padding: 0 14px; margin-bottom: 14px;
      border-radius: 999px; background: rgba(242,176,30,.18); color: var(--ho-primary-deep);
      font-family: var(--ho-font-display); font-size: 22px; letter-spacing: .03em; text-transform: uppercase;
    }
    .ho-feat p { margin: 0; color: var(--ho-muted); font-size: 16px; }
    .ho-feat.span2 { grid-column: span 1; }

    /* ---- Pricing ---- */
    .ho-price { background: linear-gradient(180deg, rgba(255,253,245,.7), transparent); }
    .ho-price-grid { margin-top: clamp(30px, 4vw, 48px); display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 18px; align-items: start; }
    .ho-plan {
      position: relative; border-radius: 26px; padding: 30px;
      background: var(--ho-surface); border: 1px solid var(--ho-border);
      box-shadow: 0 18px 60px rgba(24,22,19,.08);
    }
    .ho-plan.featured { border-color: var(--ho-primary); box-shadow: 0 24px 70px rgba(177,18,23,.16); }
    .ho-plan-flag {
      position: absolute; top: -13px; right: 26px; padding: 6px 14px; border-radius: 999px;
      background: var(--ho-primary); color: #fff; font-size: 12px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase;
    }
    .ho-plan h3 { margin: 0; font-family: var(--ho-font-display); font-size: 32px; text-transform: uppercase; letter-spacing: .03em; }
    .ho-plan .price { display: flex; align-items: baseline; gap: 8px; margin: 12px 0 4px; }
    .ho-plan .price b { font-family: var(--ho-font-display); font-size: 56px; line-height: 1; color: var(--ho-text); }
    .ho-plan .price span { color: var(--ho-muted); font-weight: 700; }
    .ho-plan .renew { margin: 0 0 18px; color: var(--ho-muted); font-size: 14px; }
    .ho-plan .best { margin: 0 0 18px; font-weight: 650; }
    .ho-plan ul { list-style: none; margin: 0 0 24px; padding: 0; display: grid; gap: 11px; }
    .ho-plan li { position: relative; padding-left: 28px; font-size: 15px; }
    .ho-plan li::before {
      content: "✓"; position: absolute; left: 0; top: 0; color: var(--ho-secondary); font-weight: 900;
    }
    .ho-plan .btn { width: 100%; }
    .ho-price-foot { margin-top: 20px; text-align: center; color: var(--ho-muted); font-weight: 650; }

    /* ---- Process ---- */
    .ho-steps { margin-top: clamp(30px, 4vw, 48px); display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; counter-reset: step; }
    .ho-step { padding: 26px 24px; border-radius: 22px; background: rgba(255,255,255,.74); border: 1px solid var(--ho-border); }
    .ho-step .num {
      display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px; margin-bottom: 16px;
      background: var(--ho-secondary); color: #fff; font-family: var(--ho-font-display); font-size: 26px;
    }
    .ho-step h3 { margin: 0 0 8px; font-family: var(--ho-font-display); font-size: 26px; text-transform: uppercase; letter-spacing: .02em; }
    .ho-step p { margin: 0; color: var(--ho-muted); font-size: 16px; }

    /* ---- Final CTA ---- */
    .ho-cta { padding-bottom: clamp(64px, 9vw, 120px); }
    .ho-cta-card {
      position: relative; overflow: hidden; border-radius: 32px; padding: clamp(36px, 6vw, 72px);
      text-align: center; color: #fff;
      background:
        radial-gradient(circle at 18% 12%, rgba(242,176,30,.34), transparent 42%),
        radial-gradient(circle at 88% 88%, rgba(46,91,52,.40), transparent 46%),
        linear-gradient(140deg, var(--ho-primary), var(--ho-primary-deep));
      box-shadow: 0 30px 90px rgba(177,18,23,.26);
    }
    .ho-cta-card h2 { margin: 0; font-family: var(--ho-font-display); text-transform: uppercase; font-size: clamp(36px, 6vw, 68px); line-height: .9; letter-spacing: .02em; }
    .ho-cta-card p { max-width: 50ch; margin: 16px auto 28px; font-size: clamp(17px, 1.6vw, 20px); opacity: .94; }
    .ho-cta-card .btn-accent { background: var(--ho-accent); color: var(--ho-text); border-color: var(--ho-accent); box-shadow: 0 14px 34px rgba(0,0,0,.18); }
    .ho-cta-card .btn-accent:hover { transform: translateY(-2px); filter: brightness(1.04); color: var(--ho-text); }

    /* ---- Footer ---- */
    .ho-foot { border-top: 1px solid var(--ho-border); }
    .ho-foot-inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 28px 0; color: var(--ho-muted); font-size: 14px; }
    .ho-foot a { color: var(--ho-muted); text-decoration: none; font-weight: 700; }
    .ho-foot a:hover { color: var(--ho-primary); }
    .ho-foot .staff { color: rgba(78,78,78,.4); font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }

    /* ---- Responsive ---- */
    @media (max-width: 920px) {
      .ho-hero-grid { grid-template-columns: 1fr; }
      .ho-door { order: -1; }
      .ho-doorgrid { grid-template-columns: repeat(2, minmax(0,1fr)); }
      .ho-steps { grid-template-columns: 1fr; }
    }
    @media (max-width: 680px) {
      .ho-nav-links { display: none; }
      .ho-problem-grid { grid-template-columns: 1fr; }
      .ho-doorgrid { grid-template-columns: 1fr; }
      .ho-price-grid { grid-template-columns: 1fr; }
      .ho-plan-flag { right: 50%; transform: translateX(50%); }
      .btn { width: 100%; }
      .ho-hero-cta .btn { width: 100%; }
    }
  </style>
</head>
<body class="ho-home">

  <header class="ho-head">
    <div class="ho-wrap ho-head-inner">
      <a class="ho-brand" href="/" aria-label="Hoosier Online home">
        <img src="/assets/brand/logo_primary.png" alt="Hoosier Online">
      </a>
      <nav class="ho-nav" aria-label="Primary">
        <span class="ho-nav-links">
          <a href="#included">What you get</a>
          <a href="#pricing">Pricing</a>
          <a href="#how">How it works</a>
        </span>
        <a class="ho-nav-cta" href="#start">Get started</a>
      </nav>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section class="ho-hero ho-section">
      <div class="ho-wrap ho-hero-grid">
        <div>
          <p class="ho-eyebrow">Built in Indiana for the people who do the work</p>
          <h1>You do the work.<br>We’ll handle the <em>front door.</em></h1>
          <p class="ho-hero-lede">
            Hoosier Online builds a clean, trustworthy online home for local service businesses —
            so customers can find you, trust you, see what you do, reach you, book you, and pay you.
            No bloated website. No agency runaround.
          </p>
          <div class="ho-hero-cta">
            <a class="btn btn-primary" href="#start">Start your front door <span class="arrow">→</span></a>
            <a class="btn btn-ghost" href="#included">See what’s included</a>
          </div>
          <p class="ho-hero-note"><span class="dot"></span> Now taking a small first round of Indiana businesses.</p>
        </div>

        <!-- Stylized "front door" preview -->
        <div class="ho-door" aria-hidden="true">
          <div class="ho-door-top">
            <span class="r"></span><span class="g"></span><span class="b"></span>
            <span class="ho-door-url">yourbusiness.hoosieronline.com</span>
          </div>
          <div class="ho-door-screen">
            <div class="ho-door-banner">
              <b>Your Business</b>
              <span>Serving your town &amp; the surrounding area</span>
            </div>
            <div class="ho-door-rows">
              <div class="ho-door-row"><i>★</i><p>What you do — clear and easy to read</p></div>
              <div class="ho-door-row"><i>✓</i><p>Photos that prove the work is real</p></div>
              <div class="ho-door-row"><i>☎</i><p>One obvious way to get in touch</p></div>
            </div>
            <div class="ho-door-btn">Request a Quote →</div>
          </div>
        </div>
      </div>
    </section>

    <!-- PROBLEM -->
    <section class="ho-problem ho-section">
      <div class="ho-wrap">
        <p class="ho-eyebrow">The problem</p>
        <h2 class="ho-h2">You don’t need more noise.<br>You need fewer missed jobs.</h2>
        <div class="ho-problem-grid">
          <?php foreach ($problems as $p): ?>
            <div class="ho-problem-item"><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- WHAT YOU GET -->
    <section class="ho-section" id="included">
      <div class="ho-wrap">
        <p class="ho-eyebrow">What you get</p>
        <h2 class="ho-h2">One front door that does the whole job.</h2>
        <p class="ho-lede">Everything a local customer needs to go from “maybe” to a booked job — in one clean place you control.</p>
        <div class="ho-doorgrid">
          <?php foreach ($frontDoor as $f): ?>
            <article class="ho-feat">
              <span class="ho-feat-tag"><?= htmlspecialchars($f['k'], ENT_QUOTES, 'UTF-8') ?></span>
              <p><?= htmlspecialchars($f['t'], ENT_QUOTES, 'UTF-8') ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- PRICING -->
    <section class="ho-price ho-section" id="pricing">
      <div class="ho-wrap">
        <p class="ho-eyebrow">Straight pricing</p>
        <h2 class="ho-h2">One flat price. No surprise invoices.</h2>
        <p class="ho-lede">You’ll know the cost before you say yes. Pick the level of help you want — that’s it.</p>

        <div class="ho-price-grid">
          <article class="ho-plan">
            <h3>Standard Front Door</h3>
            <div class="price"><b>$499</b><span>one-time setup</span></div>
            <p class="renew">Includes 1 year of service. Renews at $250/year or $25/month after year one.</p>
            <p class="best">Best for a clean online front door without heavy ongoing changes.</p>
            <ul>
              <li>Hosted business page built for your trade</li>
              <li>Services, work, and photo gallery</li>
              <li>Contact &amp; quote-request form, click-to-call</li>
              <li>Google / Facebook / social links tied together</li>
              <li>Booking or request path, payment link when needed</li>
            </ul>
            <a class="btn btn-ghost" href="#start">Start with Standard</a>
          </article>

          <article class="ho-plan featured">
            <span class="ho-plan-flag">Most hands-off</span>
            <h3>Managed Front Door</h3>
            <div class="price"><b>$999</b><span>setup + 3 months managed</span></div>
            <p class="renew">Renews at $250/quarter or $750/year after the first 3 months.</p>
            <p class="best">Best if you’d rather we keep it current and handle the changes for you.</p>
            <ul>
              <li>Everything in Standard</li>
              <li>We make your updates and edits</li>
              <li>We keep info, hours, and offers current</li>
              <li>Ongoing cleanup of the online mess</li>
              <li>A real person who knows your setup</li>
            </ul>
            <a class="btn btn-primary" href="#start">Start with Managed <span class="arrow">→</span></a>
          </article>
        </div>
        <p class="ho-price-foot">Not sure which fits? Tell us your trade and we’ll point you to the simpler one.</p>
      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="ho-section" id="how">
      <div class="ho-wrap">
        <p class="ho-eyebrow">How it works</p>
        <h2 class="ho-h2">Three steps. We do the heavy part.</h2>
        <div class="ho-steps">
          <?php foreach ($steps as $s): ?>
            <article class="ho-step">
              <div class="num"><?= htmlspecialchars($s['n'], ENT_QUOTES, 'UTF-8') ?></div>
              <h3><?= htmlspecialchars($s['h'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p><?= htmlspecialchars($s['t'], ENT_QUOTES, 'UTF-8') ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- FINAL CTA -->
    <section class="ho-cta" id="start">
      <div class="ho-wrap">
        <div class="ho-cta-card">
          <h2>Start with one clean front door.</h2>
          <p>Tell us what service you sell and where you sell it. We’ll map the simplest path to booked jobs — and show you the page before you commit.</p>
          <a class="btn btn-accent" href="<?= htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') ?>">Email Hoosier Online <span class="arrow">→</span></a>
        </div>
      </div>
    </section>
  </main>

  <footer class="ho-foot">
    <div class="ho-wrap ho-foot-inner">
      <span>&copy; <?= date('Y') ?> Hoosier Online — Local roots. Local pros. Local pride.</span>
      <span>
        <a href="<?= htmlspecialchars($mailto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></a>
        &nbsp;·&nbsp;
        <a class="staff" href="/admin.php">Staff</a>
      </span>
    </div>
  </footer>

</body>
</html>
