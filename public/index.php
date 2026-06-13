<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hoosier Online — Digital for Indiana</title>
<meta name="description" content="We build websites, AI receptionists, and online presence for Indiana small businesses.">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ink: #0e0f11;
    --fog: #f3f1ee;
    --mid: #888;
    --accent: #1a6b3c;
    --accent2: #e8f0eb;
  }

  html { font-size: 16px; scroll-behavior: smooth; }

  body {
    font-family: -apple-system, "Segoe UI", Helvetica Neue, sans-serif;
    background: var(--fog);
    color: var(--ink);
    line-height: 1.6;
    overflow-x: hidden;
  }

  /* ── NAV ── */
  nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    display: flex; align-items: center; justify-content: space-between;
    padding: 1.1rem 2rem;
    background: rgba(243,241,238,.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(0,0,0,.06);
  }
  .logo { font-weight: 700; font-size: 1rem; letter-spacing: -.01em; }
  .logo span { color: var(--accent); }
  nav a { color: var(--ink); text-decoration: none; font-size: .88rem; margin-left: 1.6rem; }
  nav a:hover { color: var(--accent); }

  /* ── HERO ── */
  .hero {
    min-height: 100vh;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 0 2rem 5rem;
    position: relative;
    overflow: hidden;
  }

  .hero-bg {
    position: absolute; inset: 0; z-index: 0;
    background:
      radial-gradient(ellipse 120% 80% at 60% 30%, #c8e6d0 0%, transparent 60%),
      radial-gradient(ellipse 80% 60% at 20% 80%, #dde9e0 0%, transparent 50%),
      var(--fog);
  }

  /* floating ring ornament */
  .ring {
    position: absolute; top: 12%; right: 6%;
    width: min(460px, 50vw); aspect-ratio: 1;
    border-radius: 50%;
    border: 1px solid rgba(26,107,60,.18);
    box-shadow: inset 0 0 80px rgba(26,107,60,.06);
    z-index: 0;
  }
  .ring::after {
    content: '';
    position: absolute; inset: 14%;
    border-radius: 50%;
    border: 1px solid rgba(26,107,60,.12);
  }

  .hero-content { position: relative; z-index: 1; max-width: 780px; }

  .eyebrow {
    font-size: .78rem; font-weight: 600; letter-spacing: .12em;
    text-transform: uppercase; color: var(--accent); margin-bottom: 1.2rem;
  }

  h1 {
    font-size: clamp(2.6rem, 6vw, 4.8rem);
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -.03em;
    margin-bottom: 1.4rem;
  }

  h1 em { font-style: normal; color: var(--accent); }

  .hero p {
    font-size: clamp(1rem, 2vw, 1.2rem);
    color: #444;
    max-width: 500px;
    margin-bottom: 2.2rem;
  }

  .btn {
    display: inline-flex; align-items: center; gap: .5rem;
    padding: .85rem 1.8rem;
    border-radius: 100px;
    font-weight: 700; font-size: .92rem;
    text-decoration: none;
    transition: transform .15s, box-shadow .15s;
  }
  .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
  .btn-primary { background: var(--ink); color: #fff; }
  .btn-ghost { background: transparent; color: var(--ink); border: 1.5px solid rgba(0,0,0,.2); margin-left: .6rem; }

  .scroll-hint {
    position: absolute; bottom: 2rem; left: 2rem; z-index: 1;
    font-size: .75rem; color: var(--mid); letter-spacing: .06em;
    display: flex; align-items: center; gap: .5rem;
  }
  .scroll-hint::before { content: ''; width: 30px; height: 1px; background: currentColor; }

  /* ── TICKER ── */
  .ticker-wrap {
    overflow: hidden;
    background: var(--ink); color: var(--fog);
    padding: .9rem 0;
    font-size: .8rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase;
  }
  .ticker { display: flex; gap: 3rem; width: max-content; animation: ticker 22s linear infinite; }
  .ticker span { white-space: nowrap; }
  .ticker span::before { content: '✦'; margin-right: 1rem; color: var(--accent); }
  @keyframes ticker { from { transform: translateX(0) } to { transform: translateX(-50%) } }

  /* ── SERVICES ── */
  section { padding: 5rem 2rem; max-width: 900px; margin: auto; }

  .section-label {
    font-size: .75rem; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--accent); margin-bottom: 2.5rem;
  }

  .services {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1px;
    background: rgba(0,0,0,.08);
    border: 1px solid rgba(0,0,0,.08);
    border-radius: 16px;
    overflow: hidden;
  }

  .svc {
    background: var(--fog);
    padding: 2rem 1.6rem;
    transition: background .2s;
  }
  .svc:hover { background: var(--accent2); }

  .svc-num {
    font-size: .72rem; font-weight: 700; color: var(--accent);
    letter-spacing: .1em; margin-bottom: 1rem;
  }
  .svc h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: .5rem; }
  .svc p { font-size: .88rem; color: #555; line-height: 1.5; }

  /* ── DIVIDER QUOTE ── */
  .pullquote {
    background: var(--ink); color: var(--fog);
    padding: 5rem 2rem;
    text-align: center;
  }
  .pullquote blockquote {
    max-width: 680px; margin: auto;
    font-size: clamp(1.3rem, 3vw, 2rem);
    font-weight: 700; line-height: 1.25;
    letter-spacing: -.02em;
  }
  .pullquote cite { display: block; margin-top: 1.2rem; font-size: .82rem; font-style: normal; color: #888; }

  /* ── WHY ── */
  .why-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    margin-top: 2.5rem;
  }
  .why-item h4 { font-size: .95rem; font-weight: 700; margin-bottom: .3rem; }
  .why-item p { font-size: .84rem; color: #555; }

  /* ── CTA ── */
  .cta-section {
    padding: 5rem 2rem 7rem;
    max-width: 900px; margin: auto;
    text-align: center;
  }
  .cta-section h2 {
    font-size: clamp(2rem, 4vw, 3rem);
    font-weight: 800; letter-spacing: -.03em;
    margin-bottom: 1rem;
  }
  .cta-section p { color: #555; font-size: 1rem; margin-bottom: 2rem; }

  /* ── FOOTER ── */
  footer {
    border-top: 1px solid rgba(0,0,0,.08);
    padding: 2rem;
    font-size: .8rem; color: var(--mid);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: .5rem;
  }

  @media (max-width: 600px) {
    nav a { display: none; }
    .ring { display: none; }
    .hero { padding-bottom: 4rem; }
    .btn-ghost { display: none; }
  }
</style>
</head>
<body>

<nav>
  <div class="logo">Hoosier<span>Online</span></div>
  <div>
    <a href="#services">Services</a>
    <a href="#why">Why us</a>
    <a href="#contact">Contact</a>
  </div>
</nav>

<!-- HERO -->
<div class="hero">
  <div class="hero-bg"></div>
  <div class="ring"></div>
  <div class="hero-content">
    <p class="eyebrow">Indiana&rsquo;s digital partner</p>
    <h1>Your business,<br><em>found online.</em></h1>
    <p>Websites, AI receptionists, and online presence built for Indiana owner-operators who are too busy to miss a call.</p>
    <div>
      <a href="#contact" class="btn btn-primary">Get started &rarr;</a>
      <a href="#services" class="btn btn-ghost">See what we do</a>
    </div>
  </div>
  <div class="scroll-hint">scroll</div>
</div>

<!-- TICKER -->
<div class="ticker-wrap">
  <div class="ticker">
    <span>Websites</span><span>AI Receptionist</span><span>Online Presence</span>
    <span>Google Listings</span><span>Lead Capture</span><span>Indiana Small Business</span>
    <span>Websites</span><span>AI Receptionist</span><span>Online Presence</span>
    <span>Google Listings</span><span>Lead Capture</span><span>Indiana Small Business</span>
  </div>
</div>

<!-- SERVICES -->
<section id="services">
  <p class="section-label">What we build</p>
  <div class="services">
    <div class="svc">
      <p class="svc-num">01</p>
      <h3>Professional Website</h3>
      <p>A real site — not a template. Built around your services, your area, your customers. One flat fee, yours to keep.</p>
    </div>
    <div class="svc">
      <p class="svc-num">02</p>
      <h3>AI Receptionist</h3>
      <p>Answers your phone when you can't. Books jobs, fields quote requests, and takes messages — 24 hours a day, $149/mo.</p>
    </div>
    <div class="svc">
      <p class="svc-num">03</p>
      <h3>Online Presence</h3>
      <p>Google Business Profile, reviews, citations. Show up when someone nearby searches for exactly what you do.</p>
    </div>
  </div>
</section>

<!-- PULLQUOTE -->
<div class="pullquote">
  <blockquote>
    &ldquo;A phone call you miss is a job someone else gets.&rdquo;
    <cite>— The problem we fix</cite>
  </blockquote>
</div>

<!-- WHY -->
<section id="why">
  <p class="section-label">Why Hoosier Online</p>
  <div class="why-grid">
    <div class="why-item">
      <h4>Indiana-only focus</h4>
      <p>We work with local owner-operators. We know the market, the areas, the customers.</p>
    </div>
    <div class="why-item">
      <h4>No long contracts</h4>
      <p>Month-to-month on the receptionist. One-time on websites. No lock-in.</p>
    </div>
    <div class="why-item">
      <h4>Real results, not reports</h4>
      <p>We measure calls answered, jobs booked, and leads captured — not clicks and impressions.</p>
    </div>
    <div class="why-item">
      <h4>Built for the trade</h4>
      <p>Junk removal, lawn care, cleaning, handyman, pressure washing — we know your customers.</p>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-section" id="contact">
  <h2>Ready to stop missing calls?</h2>
  <p>Tell us your business name and number. We&rsquo;ll show you exactly what we&rsquo;d build.</p>
  <a href="mailto:hello@hoosieronline.com" class="btn btn-primary" style="font-size:1rem;padding:1rem 2.2rem">
    Get in touch &rarr;
  </a>
</section>

<!-- FOOTER -->
<footer>
  <span>&copy; <?= date('Y') ?> Hoosier Online &mdash; Indiana</span>
  <span>hello@hoosieronline.com</span>
</footer>

</body>
</html>
