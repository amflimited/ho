<?php
// Hoosier Online coming-soon index.
// Public-facing placeholder until the full sales page is approved.
// Admin link remains present but intentionally visually quiet.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hoosier Online</title>
  <meta name="description" content="Hoosier Online is almost ready. We're late, but in a very human way.">
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="/assets/css/site.css?v=012-mobile-fix">
  <style>
    html,
    body {
      overflow-x: hidden;
    }

    .coming-soon-page {
      min-height: 100vh;
      display: grid;
      align-items: center;
      padding: 34px 16px 18px;
    }

    .coming-wrap {
      width: min(1120px, 100%);
      margin: 0 auto;
      display: grid;
      gap: 18px;
    }

    .coming-card {
      position: relative;
      overflow: hidden;
      min-height: min(720px, calc(100vh - 72px));
      display: grid;
      align-items: center;
      background:
        radial-gradient(circle at 50% 18%, rgba(242,176,30,.18), transparent 24rem),
        radial-gradient(circle at 86% 15%, rgba(177,18,23,.09), transparent 23rem),
        radial-gradient(circle at 12% 82%, rgba(46,91,52,.08), transparent 24rem),
        linear-gradient(135deg, rgba(255,255,255,.94), rgba(255,253,245,.80)),
        var(--ho-surface);
      border: 1px solid var(--ho-border);
      border-radius: 32px;
      box-shadow: 0 24px 88px rgba(24,22,19,.12);
    }

    .coming-card::before {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
      background-image:
        linear-gradient(rgba(15,17,19,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(15,17,19,.016) 1px, transparent 1px);
      background-size: 38px 38px;
      opacity: .72;
    }

    .coming-card::after {
      content: "LOCAL ROOTS • LOCAL PROS • LOCAL PRIDE";
      position: absolute;
      left: 50%;
      bottom: 20px;
      transform: translateX(-50%);
      font-family: var(--ho-font-display);
      font-size: clamp(42px, 6.6vw, 82px);
      line-height: .82;
      letter-spacing: .06em;
      color: rgba(15,17,19,.045);
      white-space: nowrap;
      pointer-events: none;
    }

    .coming-inner {
      position: relative;
      z-index: 1;
      padding: clamp(28px, 5.5vw, 66px);
      display: grid;
      gap: clamp(26px, 4vw, 42px);
      align-items: start;
    }

    .coming-logo-wrap {
      display: flex;
      justify-content: center;
      padding-bottom: clamp(6px, 1.6vw, 18px);
    }

    .coming-logo {
      width: min(640px, 100%);
      height: auto;
      display: block;
      filter: drop-shadow(0 18px 26px rgba(24,22,19,.09));
    }

    .coming-content-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(280px, .72fr);
      gap: clamp(22px, 4.5vw, 52px);
      align-items: start;
    }

    .coming-main-copy {
      max-width: 660px;
    }

    .coming-kicker {
      margin: 0 0 12px;
      color: var(--ho-primary);
      text-transform: uppercase;
      letter-spacing: .18em;
      font-size: 12px;
      font-weight: 950;
    }

    .coming-title {
      margin: 0;
      font-family: var(--ho-font-display);
      font-size: clamp(58px, 9vw, 116px);
      line-height: .84;
      letter-spacing: .03em;
      text-transform: uppercase;
    }

    .coming-title em {
      color: var(--ho-primary);
      font-style: normal;
    }

    .coming-lead {
      max-width: 54ch;
      margin: 20px 0 0;
      color: var(--ho-muted);
      font-size: clamp(18px, 2vw, 21px);
    }

    .coming-proof-row {
      margin-top: 28px;
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      max-width: 690px;
    }

    .coming-pill {
      padding: 13px 14px;
      border: 1px solid var(--ho-border);
      border-radius: 16px;
      background: rgba(255,255,255,.70);
    }

    .coming-pill strong {
      display: block;
      color: var(--ho-secondary);
      font-family: var(--ho-font-display);
      font-size: 28px;
      line-height: .9;
      letter-spacing: .035em;
      text-transform: uppercase;
      margin-bottom: 5px;
    }

    .coming-side {
      display: grid;
      gap: 14px;
      align-self: center;
      padding-top: 0;
    }

    .coming-badge {
      position: relative;
      padding: 22px;
      border: 1px solid var(--ho-border);
      border-radius: 24px;
      background: rgba(255,255,255,.74);
      box-shadow: 0 14px 44px rgba(24,22,19,.07);
      overflow: hidden;
    }

    .coming-badge::before {
      content: "";
      position: absolute;
      top: 0;
      left: 18px;
      right: 18px;
      height: 3px;
      background: linear-gradient(90deg, var(--ho-primary), var(--ho-accent), var(--ho-secondary));
      border-radius: 0 0 999px 999px;
    }

    .coming-badge h2 {
      margin: 0 0 8px;
      font-family: var(--ho-font-display);
      font-size: 38px;
      line-height: .92;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .coming-badge p {
      margin: 0;
      color: var(--ho-muted);
    }

    .coming-stamp {
      display: inline-flex;
      margin-top: 18px;
      padding: 8px 13px;
      border: 2px solid currentColor;
      border-radius: 999px;
      color: var(--ho-secondary);
      font-family: var(--ho-font-display);
      font-weight: 900;
      letter-spacing: .08em;
      text-transform: uppercase;
      transform: rotate(-2deg);
    }

    .quiet-admin {
      position: relative;
      z-index: 2;
      display: flex;
      justify-content: flex-end;
      padding: 0 10px;
      min-height: 20px;
    }

    .quiet-admin a {
      color: rgba(78,78,78,.34);
      font-size: 11px;
      text-decoration: none;
      letter-spacing: .05em;
      text-transform: uppercase;
    }

    .quiet-admin a:hover {
      color: rgba(177,18,23,.72);
      text-decoration: underline;
      text-underline-offset: 3px;
    }

    @media (max-width: 900px) {
      .coming-soon-page {
        align-items: start;
        padding: 18px 10px 14px;
      }

      .coming-wrap {
        width: 100%;
      }

      .coming-card {
        width: 100%;
        min-height: auto;
        border-radius: 24px;
      }

      .coming-inner {
        padding: 24px 18px 96px;
        gap: 22px;
      }

      .coming-logo-wrap {
        justify-content: center;
        overflow: hidden;
        padding-bottom: 4px;
      }

      .coming-logo {
        width: min(420px, 96%);
        max-width: 100%;
        height: auto;
      }

      .coming-content-grid {
        grid-template-columns: 1fr;
        gap: 22px;
      }

      .coming-main-copy {
        max-width: none;
      }

      .coming-kicker {
        font-size: 11px;
        letter-spacing: .14em;
      }

      .coming-title {
        font-size: clamp(48px, 14vw, 76px);
        line-height: .86;
        max-width: 100%;
      }

      .coming-lead {
        font-size: 18px;
        line-height: 1.52;
        max-width: 100%;
      }

      .coming-proof-row {
        grid-template-columns: 1fr;
        gap: 10px;
        max-width: none;
      }

      .coming-pill {
        padding: 14px 16px;
      }

      .coming-side {
        align-self: stretch;
        gap: 12px;
      }

      .coming-badge {
        padding: 20px;
        border-radius: 20px;
      }

      .coming-badge h2 {
        font-size: 34px;
      }

      .coming-card::after {
        white-space: normal;
        left: 18px;
        right: 18px;
        bottom: 18px;
        transform: none;
        text-align: center;
        font-size: 34px;
        line-height: .86;
      }

      .quiet-admin {
        padding-right: 14px;
      }
    }

    @media (max-width: 430px) {
      .coming-soon-page {
        padding-left: 8px;
        padding-right: 8px;
      }

      .coming-inner {
        padding-left: 14px;
        padding-right: 14px;
      }

      .coming-logo {
        width: min(360px, 94%);
      }

      .coming-title {
        font-size: clamp(44px, 15vw, 64px);
      }

      .coming-lead {
        font-size: 17px;
      }

      .coming-pill strong {
        font-size: 25px;
      }
    }
  </style>
</head>
<body>
  <main class="coming-soon-page">
    <div class="coming-wrap">
      <section class="coming-card">
        <div class="coming-inner">
          <div class="coming-logo-wrap">
            <img class="coming-logo" src="/assets/brand/logo_primary.png" alt="Hoosier Online">
          </div>

          <div class="coming-content-grid">
            <section class="coming-main-copy">
              <p class="coming-kicker">We’re late, but in a very human way.</p>
              <h1 class="coming-title">Almost Online. <em>Somehow.</em></h1>
              <p class="coming-lead">
                The site is almost ready. We could launch it half-finished, but then we’d have to pretend
                that was a strategy, and nobody has the energy for that.
              </p>

              <div class="coming-proof-row" aria-label="Brand pillars">
                <div class="coming-pill">
                  <strong>Local</strong>
                  <span>Indiana. On purpose.</span>
                </div>
                <div class="coming-pill">
                  <strong>Useful</strong>
                  <span>Not another “growth engine.”</span>
                </div>
                <div class="coming-pill">
                  <strong>Soon-ish</strong>
                  <span>A coward’s deadline, but accurate.</span>
                </div>
              </div>
            </section>

            <aside class="coming-side">
              <article class="coming-badge">
                <h2>What is this?</h2>
                <p>
                  A simple place for Indiana businesses to get online without turning it into a six-week hostage situation.
                </p>
                <span class="coming-stamp">In Progress</span>
              </article>

              <article class="coming-badge">
                <h2>Why wait?</h2>
                <p>
                  Because we’re trying to make it useful before we make it public. Radical, apparently.
                </p>
                <span class="coming-stamp">Back Soon</span>
              </article>
            </aside>
          </div>
        </div>
      </section>

      <div class="quiet-admin">
        <a href="/admin.php" aria-label="Admin">staff</a>
      </div>
    </div>
  </main>
</body>
</html>
