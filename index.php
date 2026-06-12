<?php
// Hoosier Online public homepage.
// Trust-first, porch-warm, and deliberately substantial. Most visitors arrive from a
// custom preview link already weighing a purchase, so the page builds trust with depth:
// who we are, how we think, what we won't promise, and the questions a buyer is already asking.
// Self-contained; uses shared design tokens from /assets/css/site.css.
// All claims trace to product.php (real pricing, ownership, performance terms). Nothing
// here promises leads, total ownership, or anything the policies don't actually allow.

$email = 'adam@hoosieronline.com';
$mailto = 'mailto:' . $email . '?subject=' . rawurlencode('Hello from a preview');

function ho_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Who we are — warm, true facts.
$chips = [
    'Indiana-based. For real.',
    'No computer science degree needed on your part.',
    'No sales floor, no runaround.',
    'Your preview is customized around your business.',
];

// Straight talk — every promise traces to a real policy in product.php.
$promises = [
    ['h' => 'If you don’t really need us, we’ll say so.',
     't' => 'We’d rather lose the sale than talk you into something you’ll regret. A clean front door isn’t the right move for everybody, and if you’re one of the folks who’d be just fine without us, we’ll tell you that to your face instead of taking your money.'],
    ['h' => 'Your stuff stays yours.',
     't' => 'Your photos, your words, your logo, your customer information — that’s yours, today and always. Your domain too, if it’s registered in your name. The front-door page itself runs on our system, so if you ever decide to move on, we’ll sort out the hand-off together, like neighbors, not like a cell-phone contract.'],
    ['h' => 'The price is the price.',
     't' => 'No surprise invoices, no “oh, that part’s extra,” no creeping monthly fees you didn’t agree to. You’ll know the whole number before you ever say yes, and it’ll still be that number afterward.'],
    ['h' => 'We can’t make your phone ring — and we won’t pretend we can.',
     't' => 'There’s no fake “leads guaranteed,” no magic growth engine, no search-ranking promises we’d have to cross our fingers behind our backs to make. What we can do is make sure that when somebody looks you up, you come across as the real, capable operator you already are. The rest is your good work doing what it already does.'],
    ['h' => 'You’ll always see it before you pay.',
     't' => 'You already did — that preview wasn’t a one-off trick to get your attention. Showing you the actual thing, in plain sight, before any money changes hands, is simply how we do business. If you don’t like what you see, you walk, no hard feelings.'],
];

// What we're honestly not — straight from product doctrine.
$nots = [
    ['Not a giant agency project.', 'No twelve-tab website, no six-week timeline, no committee meetings about your hero image.'],
    ['Not paid advertising.', 'We don’t run ads or charge you per click. This is your front door, not a billboard rental.'],
    ['Not a guaranteed lead machine.', 'Anybody promising you a flood of customers is either guessing or fibbing. We won’t do either.'],
    ['Not unlimited marketing support.', 'We do one thing — your online front door — and we try to do it really well, instead of ten things poorly.'],
    ['Not a replacement for the work.', 'You still answer the phone, quote the job, and do right by folks. We just make sure they can find and trust you first.'],
];

// What lives behind the door — the 7 benefits, fuller this time.
$door = [
    ['Find you',      'Show up when a neighbor goes looking on Google or Facebook, and land them in one tidy place instead of a trail of dead links. No more “I couldn’t find you online, so I called the other guy.”'],
    ['Trust you',     'Look active, real, and current — not abandoned, not slapped together at midnight in 2015. The kind of presence that makes a stranger comfortable handing you their project.'],
    ['See your work',  'Your services, your offers, and your photos laid out so a customer understands what you do and whether you’re right for them inside of a few seconds.'],
    ['Reach you',     'One obvious way to call, message, or send a request — not a scavenger hunt across three platforms and an email you haven’t checked since spring.'],
    ['Book you',      'A simple path for folks to ask for a quote, request an estimate, or grab a time, with the details you actually need to answer them well.'],
    ['Pay you',       'Take a deposit or a payment when it makes sense for the job, without the awkward “so, uh, how do you want me to pay you?” dance.'],
    ['Tidy the mess', 'Clean up the obvious broken links, stale posts, wrong hours, and scattered profiles that have been quietly making you look worse than you are.'],
];

// What to expect — fuller walkthrough.
$steps = [
    ['1', 'We have a real conversation',
     'No pitch deck. We talk about your trade, your town, the jobs you actually want more of, and the ones you’d happily never take again. If you came from a preview, we’ve already done a good chunk of the homework on you — so mostly we’re just making sure we got you right.'],
    ['2', 'We build your front door',
     'You’ve seen the preview, so you already know the shape of it. We turn that into the real thing, get the details honest and accurate, and show it to you again before a single customer ever lays eyes on it. You tell us what to fix; we fix it.'],
    ['3', 'You share one link',
     'When you’re happy, you get one clean link to put wherever folks already find you — your Facebook page, your Google profile, the bottom of a text, the back of a business card. That’s the entire chore list. Then you go back to running your business.'],
];

// Honest FAQ — answers grounded in real policy.
$faqs = [
    ['Do I actually own this?',
     'You own your content outright — your photos, your words, your logo, your customer information. Your domain is yours too, as long as it’s registered in your name. The front-door page itself runs on the Hoosier Online system, the same way your shop might run on rented space rather than land you bought. If you ever want to take things further or move them elsewhere, we’ll talk it through honestly.'],
    ['What if I decide to leave someday?',
     'Then you leave, and you take your content with you — it was always yours. Because the page runs on our system, we’d work out any export or hand-off together rather than holding your stuff hostage. We’d rather you stay because it’s genuinely working for you, not because leaving is a headache.'],
    ['Will this actually get me more customers?',
     'We won’t promise that, because nobody honest can. We don’t control whether someone needs your service this week, and we’d be lying if we guaranteed leads, sales, or a spot at the top of Google. What we can promise is that when a potential customer does look you up, you’ll look like someone worth hiring — clear, current, and easy to reach. That’s the part that was costing you jobs.'],
    ['I’m not a tech person. Is that going to be a problem?',
     'Not even a little. That’s the entire reason this exists. You don’t need to learn a website builder, manage hosting, or fuss with settings. We handle the technical end; you end up with one link to share. If you can send a text message, you can use what we build you.'],
    ['Why is it so affordable? What’s the catch?',
     'No catch — it’s affordable because it’s focused. We’re not building a sprawling custom agency site with a price tag to match; we’re building one tidy front door, the part that actually matters for a local operator. Doing one thing well, for a lot of folks, lets us keep the number fair.'],
    ['Do I have to keep paying forever?',
     'Standard includes a full year, then renews at $250 a year or $25 a month — that keeps your page hosted and looked after. Managed includes three months of hands-on service, then renews quarterly or yearly. You’ll always know the renewal number up front, and you can step down or move on. No auto-traps.'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Hoosier Online — Pull up a chair</title>
  <meta name="description" content="The folks behind your preview. Hoosier Online is a small Indiana outfit that builds a clean, honest online front door for local businesses. No hard sell, no surprise invoices, no fake lead promises — and we’ll show you the thing before you pay.">
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="/assets/css/site.css?v=012-mobile-fix">
  <style>
    .ho-home { --maxw: 1140px; }
    .ho-wrap { width: min(var(--maxw), calc(100% - 40px)); margin-inline: auto; }
    .ho-narrow { width: min(820px, calc(100% - 40px)); margin-inline: auto; }
    .ho-section { padding: clamp(52px, 8vw, 104px) 0; }
    .ho-eyebrow { margin: 0 0 14px; color: var(--ho-primary); font-weight: 900; font-size: 12px; letter-spacing: .2em; text-transform: uppercase; }
    .ho-h2 { margin: 0; font-family: var(--ho-font-display); text-transform: uppercase; font-size: clamp(32px, 4.8vw, 56px); line-height: .94; letter-spacing: .02em; }
    .ho-lede { max-width: 60ch; margin: 18px 0 0; color: var(--ho-muted); font-size: clamp(17px, 1.5vw, 20px); }

    body.ho-home {
      background:
        radial-gradient(circle at 12% -4%, rgba(242,176,30,.22), transparent 34rem),
        radial-gradient(circle at 92% 4%, rgba(46,91,52,.10), transparent 26rem),
        var(--ho-bg);
    }

    /* Header */
    .ho-head { position: sticky; top: 0; z-index: 40; backdrop-filter: saturate(140%) blur(10px); background: rgba(247,243,232,.8); border-bottom: 1px solid rgba(216,199,178,.6); }
    .ho-head-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; height: 68px; }
    .ho-brand img { height: 34px; width: auto; display: block; }
    .ho-nav { display: flex; align-items: center; gap: 8px; }
    .ho-nav a { color: var(--ho-text); text-decoration: none; font-weight: 750; font-size: 15px; padding: 8px 12px; border-radius: 999px; }
    .ho-nav a:hover { background: rgba(255,255,255,.7); color: var(--ho-text); }
    .ho-nav .ho-nav-cta { background: var(--ho-secondary); color: #fff; font-weight: 850; border: 1px solid var(--ho-secondary); }
    .ho-nav .ho-nav-cta:hover { filter: brightness(1.08); color: #fff; }
    .ho-nav-links { display: contents; }

    /* Buttons */
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 52px; padding: 0 24px; border-radius: 999px; font: inherit; font-weight: 850; font-size: 16px; text-decoration: none; cursor: pointer; border: 1px solid transparent; transition: transform 160ms var(--ho-ease), box-shadow 160ms var(--ho-ease), background 160ms var(--ho-ease), filter 160ms var(--ho-ease); }
    .btn-primary { background: var(--ho-secondary); color: #fff; border-color: var(--ho-secondary); box-shadow: 0 12px 30px rgba(46,91,52,.22); }
    .btn-primary:hover { filter: brightness(1.08); color: #fff; transform: translateY(-2px); box-shadow: 0 16px 38px rgba(46,91,52,.28); }
    .btn-ghost { background: rgba(255,255,255,.74); color: var(--ho-text); border-color: var(--ho-border); }
    .btn-ghost:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(24,22,19,.10); color: var(--ho-text); }
    .btn-accent { background: var(--ho-accent); color: var(--ho-text); border-color: var(--ho-accent); box-shadow: 0 14px 34px rgba(0,0,0,.16); }
    .btn-accent:hover { transform: translateY(-2px); filter: brightness(1.04); color: var(--ho-text); }
    .btn .arrow { transition: transform 160ms var(--ho-ease); }
    .btn:hover .arrow { transform: translateX(3px); }

    /* Hero */
    .ho-hero-grid { display: block; max-width: 760px; }
    .ho-hero h1 { margin: 0; font-family: var(--ho-font-display); text-transform: uppercase; font-size: clamp(52px, 8.4vw, 104px); line-height: .86; letter-spacing: .02em; }
    .ho-hero h1 em { color: var(--ho-primary); font-style: normal; }
    .ho-hero-lede { max-width: 50ch; margin: 22px 0 0; color: var(--ho-muted); font-size: clamp(18px, 1.6vw, 21px); }
    .ho-hero-cta { margin-top: 30px; display: flex; flex-wrap: wrap; gap: 12px; }

    /* Hero note card */
    .ho-note { position: relative; border-radius: 24px; padding: clamp(26px, 3vw, 36px); background: linear-gradient(160deg, #fffdf5, #fff7e6); border: 1px solid var(--ho-border); box-shadow: 0 26px 70px rgba(24,22,19,.13); }
    .ho-note::before { content: ""; position: absolute; top: 18px; left: 22px; right: 22px; height: 3px; border-radius: 999px; background: linear-gradient(90deg, var(--ho-accent), var(--ho-primary), var(--ho-secondary)); }
    .ho-note .light { display: inline-flex; align-items: center; gap: 9px; margin-bottom: 14px; color: var(--ho-secondary); font-weight: 800; font-size: 14px; }
    .ho-note .bulb { width: 11px; height: 11px; border-radius: 999px; background: var(--ho-accent); box-shadow: 0 0 0 5px rgba(242,176,30,.22), 0 0 16px rgba(242,176,30,.7); }
    .ho-note p { margin: 0 0 13px; font-size: 17px; line-height: 1.55; }
    .ho-note p:last-of-type { margin-bottom: 0; }
    .ho-note .sig { margin-top: 18px; font-family: var(--ho-font-display); font-size: 26px; letter-spacing: .02em; color: var(--ho-text); }

    /* Who we are — prose */
    .ho-who { background: linear-gradient(180deg, transparent, rgba(255,253,245,.7)); }
    .ho-prose { max-width: 64ch; margin-top: clamp(20px, 3vw, 30px); }
    .ho-prose p { margin: 0 0 18px; font-size: clamp(17px, 1.4vw, 19px); line-height: 1.62; color: #353433; }
    .ho-prose p:last-child { margin-bottom: 0; }
    .ho-chips { margin-top: clamp(26px, 3.5vw, 40px); display: flex; flex-wrap: wrap; gap: 12px; }
    .ho-chip { display: inline-flex; align-items: center; gap: 9px; padding: 12px 18px; border-radius: 999px; background: var(--ho-surface); border: 1px solid var(--ho-border); font-weight: 750; box-shadow: 0 8px 22px rgba(24,22,19,.05); }
    .ho-chip::before { content: ""; width: 9px; height: 9px; border-radius: 999px; background: var(--ho-secondary); }

    /* Straight talk / promises */
    .ho-promises { margin-top: clamp(30px, 4vw, 46px); display: grid; gap: 14px; }
    .ho-promise { display: grid; grid-template-columns: 44px minmax(0,1fr); gap: 18px; align-items: start; padding: 24px; border-radius: 20px; background: var(--ho-surface); border: 1px solid var(--ho-border); box-shadow: 0 12px 40px rgba(24,22,19,.05); }
    .ho-promise .mark { width: 44px; height: 44px; border-radius: 13px; display: grid; place-items: center; background: rgba(46,91,52,.12); color: var(--ho-secondary); font-weight: 900; font-size: 22px; }
    .ho-promise h3 { margin: 0 0 6px; font-size: 20px; letter-spacing: -.01em; }
    .ho-promise p { margin: 0; color: var(--ho-muted); font-size: 16px; line-height: 1.58; }

    /* What we're not */
    .ho-not { background: linear-gradient(180deg, rgba(255,253,245,.7), transparent); }
    .ho-notgrid { margin-top: clamp(28px, 4vw, 44px); display: grid; gap: 12px; }
    .ho-notrow { display: grid; grid-template-columns: minmax(0, 320px) minmax(0, 1fr); gap: 8px 28px; align-items: baseline; padding: 20px 4px; border-bottom: 1px solid rgba(216,199,178,.7); }
    .ho-notrow:last-child { border-bottom: 0; }
    .ho-notrow h3 { margin: 0; font-family: var(--ho-font-display); font-size: 24px; text-transform: uppercase; letter-spacing: .02em; color: var(--ho-text); }
    .ho-notrow p { margin: 0; color: var(--ho-muted); font-size: 16px; line-height: 1.55; }

    /* Behind the door */
    .ho-doorgrid { margin-top: clamp(28px, 4vw, 44px); display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 14px; }
    .ho-feat { padding: 24px; border-radius: 18px; background: rgba(255,255,255,.72); border: 1px solid var(--ho-border); }
    .ho-feat span { display: block; margin-bottom: 10px; font-family: var(--ho-font-display); font-size: 25px; letter-spacing: .03em; text-transform: uppercase; color: var(--ho-primary-deep); }
    .ho-feat p { margin: 0; color: var(--ho-muted); font-size: 15px; line-height: 1.56; }

    /* Pricing */
    .ho-price { background: linear-gradient(180deg, rgba(255,253,245,.7), transparent); }
    .ho-price-grid { margin-top: clamp(28px, 4vw, 44px); display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 18px; align-items: start; }
    .ho-plan { position: relative; border-radius: 24px; padding: 30px; background: var(--ho-surface); border: 1px solid var(--ho-border); box-shadow: 0 16px 54px rgba(24,22,19,.07); }
    .ho-plan-tag { display: inline-block; margin-bottom: 12px; padding: 5px 13px; border-radius: 999px; background: rgba(46,91,52,.12); color: var(--ho-secondary); font-size: 12px; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; }
    .ho-plan h3 { margin: 0; font-family: var(--ho-font-display); font-size: 30px; text-transform: uppercase; letter-spacing: .03em; }
    .ho-plan .price { display: flex; align-items: baseline; gap: 8px; margin: 10px 0 4px; }
    .ho-plan .price b { font-family: var(--ho-font-display); font-size: 52px; line-height: 1; }
    .ho-plan .price span { color: var(--ho-muted); font-weight: 700; }
    .ho-plan .renew { margin: 0 0 16px; color: var(--ho-muted); font-size: 14px; }
    .ho-plan .best { margin: 0 0 18px; font-weight: 650; line-height: 1.5; }
    .ho-plan ul { list-style: none; margin: 0 0 24px; padding: 0; display: grid; gap: 10px; }
    .ho-plan li { position: relative; padding-left: 27px; font-size: 15px; }
    .ho-plan li::before { content: "✓"; position: absolute; left: 0; top: 0; color: var(--ho-secondary); font-weight: 900; }
    .ho-plan .btn { width: 100%; }
    .ho-price-foot { margin-top: 20px; text-align: center; color: var(--ho-muted); font-weight: 650; }

    /* Process */
    .ho-steps { margin-top: clamp(28px, 4vw, 44px); display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 16px; }
    .ho-step { padding: 28px 26px; border-radius: 20px; background: rgba(255,255,255,.74); border: 1px solid var(--ho-border); }
    .ho-step .num { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 13px; margin-bottom: 16px; background: var(--ho-secondary); color: #fff; font-family: var(--ho-font-display); font-size: 24px; }
    .ho-step h3 { margin: 0 0 10px; font-size: 22px; }
    .ho-step p { margin: 0; color: var(--ho-muted); font-size: 16px; line-height: 1.58; }

    /* FAQ */
    .ho-faq-list { margin-top: clamp(28px, 4vw, 44px); display: grid; gap: 14px; }
    .ho-faq { padding: 26px 28px; border-radius: 20px; background: var(--ho-surface); border: 1px solid var(--ho-border); box-shadow: 0 12px 40px rgba(24,22,19,.05); }
    .ho-faq h3 { margin: 0 0 10px; font-size: 20px; letter-spacing: -.01em; }
    .ho-faq p { margin: 0; color: var(--ho-muted); font-size: 16px; line-height: 1.62; }

    /* Porch-light CTA */
    .ho-cta { padding-bottom: clamp(60px, 9vw, 116px); }
    .ho-cta-card { position: relative; overflow: hidden; border-radius: 30px; padding: clamp(38px, 6vw, 76px); text-align: center;
      background: radial-gradient(circle at 50% -10%, rgba(242,176,30,.5), transparent 52%), linear-gradient(160deg, #2f5e36, #234a29); color: #fff;
      box-shadow: 0 30px 90px rgba(35,74,41,.34); }
    .ho-cta-card .porch { display: inline-flex; align-items: center; gap: 10px; margin-bottom: 16px; font-weight: 800; color: #ffe9b8; }
    .ho-cta-card .porch .bulb { width: 12px; height: 12px; border-radius: 999px; background: var(--ho-accent); box-shadow: 0 0 0 6px rgba(242,176,30,.25), 0 0 22px rgba(242,176,30,.85); }
    .ho-cta-card h2 { margin: 0; font-family: var(--ho-font-display); text-transform: uppercase; font-size: clamp(34px, 5.6vw, 62px); line-height: .92; letter-spacing: .02em; }
    .ho-cta-card p { max-width: 56ch; margin: 16px auto 28px; font-size: clamp(17px, 1.6vw, 20px); opacity: .95; line-height: 1.6; }

    /* Footer */
    .ho-foot { border-top: 1px solid var(--ho-border); }
    .ho-foot-inner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 28px 0; color: var(--ho-muted); font-size: 14px; }
    .ho-foot a { color: var(--ho-muted); text-decoration: none; font-weight: 700; }
    .ho-foot a:hover { color: var(--ho-primary); }
    .ho-foot .staff { color: rgba(78,78,78,.4); font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }

    @media (max-width: 920px) {
      .ho-hero-grid { grid-template-columns: 1fr; }
      .ho-doorgrid { grid-template-columns: repeat(2, minmax(0,1fr)); }
      .ho-steps { grid-template-columns: 1fr; }
      .ho-notrow { grid-template-columns: 1fr; gap: 6px; }
    }
    @media (max-width: 680px) {
      .ho-nav-links { display: none; }
      .ho-doorgrid { grid-template-columns: 1fr; }
      .ho-price-grid { grid-template-columns: 1fr; }
      .ho-promise { grid-template-columns: 1fr; gap: 12px; }
      .btn, .ho-hero-cta .btn { width: 100%; }
    }

    /* v067 mobile correction */
    .ho-preview-guide { padding: 0 0 clamp(32px, 6vw, 60px); }
    .ho-guide-card {
      padding: clamp(22px, 4vw, 30px);
      border-radius: 24px;
      background: rgba(255,255,255,.74);
      border: 1px solid var(--ho-border);
      box-shadow: 0 14px 42px rgba(24,22,19,.06);
    }
    .ho-guide-card h2 {
      margin: 0;
      font-family: var(--ho-font-display);
      text-transform: uppercase;
      font-size: clamp(28px, 4vw, 40px);
      line-height: .95;
      letter-spacing: .02em;
    }
    .ho-guide-steps {
      margin-top: 16px;
      display: grid;
      grid-template-columns: repeat(3, minmax(0,1fr));
      gap: 10px;
    }
    .ho-guide-steps span {
      display: flex;
      align-items: center;
      gap: 9px;
      padding: 12px 14px;
      border-radius: 16px;
      background: #fffdf5;
      border: 1px solid var(--ho-border);
      color: var(--ho-muted);
      font-weight: 800;
      line-height: 1.25;
    }
    .ho-guide-steps b {
      display: grid;
      place-items: center;
      flex: 0 0 28px;
      width: 28px;
      height: 28px;
      border-radius: 9px;
      background: var(--ho-secondary);
      color: #fff;
      font-family: var(--ho-font-display);
      font-size: 18px;
      line-height: 1;
    }

    @media (max-width: 680px) {
      .ho-wrap,
      .ho-narrow {
        width: min(100% - 32px, var(--maxw));
      }

      .ho-section {
        padding: 48px 0;
      }

      .ho-head-inner {
        height: 58px;
      }

      .ho-brand img {
        height: 28px;
      }

      .ho-nav {
        gap: 6px;
      }

      .ho-nav .ho-nav-cta {
        font-size: 14px;
        padding: 8px 14px;
      }

      .ho-hero {
        padding-top: 38px;
      }

      .ho-hero h1 {
        font-size: clamp(44px, 15vw, 66px);
        line-height: .9;
        letter-spacing: .005em;
      }

      .ho-h2,
      .ho-cta-card h2 {
        font-size: clamp(30px, 10vw, 44px);
        line-height: .98;
        letter-spacing: .005em;
      }

      .ho-notrow h3,
      .ho-feat span {
        letter-spacing: .02em;
      }

      .ho-hero-lede,
      .ho-lede,
      .ho-prose p,
      .ho-note p,
      .ho-step p,
      .ho-faq p,
      .ho-promise p,
      .ho-notrow p {
        font-size: 17px;
        line-height: 1.55;
      }

      .ho-note {
        padding: 26px 24px 28px;
        border-radius: 22px;
      }

      .ho-note .sig {
        font-size: 23px;
        line-height: 1.1;
      }

      .ho-preview-guide {
        padding-top: 0;
        padding-bottom: 36px;
      }

      .ho-guide-steps {
        grid-template-columns: 1fr;
      }

      .ho-guide-steps span {
        min-height: 52px;
      }

      .ho-chips {
        gap: 10px;
      }

      .ho-chip {
        width: 100%;
        justify-content: flex-start;
        padding: 13px 16px;
      }

      .ho-promises {
        gap: 12px;
      }

      .ho-promise {
        padding: 20px;
        border-radius: 18px;
      }

      .ho-promise .mark {
        width: 38px;
        height: 38px;
        border-radius: 12px;
      }

      .ho-notgrid {
        gap: 12px;
      }

      .ho-notrow {
        display: block;
        padding: 20px;
        border: 1px solid var(--ho-border);
        border-radius: 18px;
        background: rgba(255,255,255,.58);
      }

      .ho-notrow h3 {
        font-size: 23px;
        line-height: 1.03;
        margin-bottom: 10px;
      }

      .ho-doorgrid {
        gap: 12px;
      }

      .ho-feat {
        padding: 20px;
      }

      .ho-feat span {
        font-size: 23px;
        line-height: 1.05;
      }

      .ho-price-grid {
        gap: 16px;
      }

      .ho-plan {
        padding: 24px 22px;
        border-radius: 22px;
      }

      .ho-plan h3 {
        font-size: 28px;
        line-height: .98;
      }

      .ho-plan .price {
        display: block;
        margin-top: 12px;
      }

      .ho-plan .price b {
        display: block;
        font-size: 48px;
      }

      .ho-plan .price span {
        display: block;
        margin-top: 4px;
        font-size: 16px;
      }

      .ho-plan .renew {
        margin-top: 8px;
        font-size: 15px;
        line-height: 1.45;
      }

      .ho-plan .best {
        font-size: 16px;
      }

      .ho-plan ul {
        gap: 9px;
      }

      .ho-plan li {
        font-size: 15px;
        line-height: 1.42;
      }

      .ho-steps {
        gap: 12px;
      }

      .ho-step {
        padding: 22px 20px;
      }

      .ho-faq {
        padding: 22px 20px;
      }

      .ho-cta-card {
        border-radius: 24px;
        padding: 34px 22px;
      }

      .ho-cta-card p {
        font-size: 17px;
      }

      .ho-foot-inner {
        display: grid;
        gap: 10px;
        padding-bottom: calc(28px + env(safe-area-inset-bottom, 0px));
      }
    }

    @media (max-width: 430px) {
      .ho-wrap,
      .ho-narrow {
        width: calc(100% - 28px);
      }

      .ho-head-inner {
        height: 56px;
      }

      .ho-brand img {
        height: 26px;
      }

      .ho-nav .ho-nav-cta {
        padding: 8px 13px;
      }

      .ho-hero h1 {
        font-size: clamp(42px, 14vw, 58px);
      }

      .ho-h2,
      .ho-cta-card h2 {
        font-size: clamp(28px, 9.7vw, 38px);
      }

      .ho-eyebrow {
        letter-spacing: .16em;
      }
    }


    /* v068 porch overlays */
    .ho-bottom-trigger {
      height: 1px;
      margin-top: -1px;
    }

    .ho-overlay-backdrop {
      position: fixed;
      inset: 0;
      z-index: 100;
      display: grid;
      place-items: center;
      padding: max(18px, env(safe-area-inset-top, 0px)) 18px max(18px, env(safe-area-inset-bottom, 0px));
      background: rgba(24, 22, 19, .28);
      opacity: 0;
      pointer-events: none;
      transition: opacity 240ms var(--ho-ease);
    }

    .ho-overlay-backdrop.is-visible {
      opacity: 1;
      pointer-events: auto;
    }

    .ho-overlay-card {
      position: relative;
      width: min(620px, 100%);
      max-height: min(760px, calc(100svh - 36px));
      overflow: auto;
      -webkit-overflow-scrolling: touch;
      transform: translateY(14px) scale(.985);
      transition: transform 260ms var(--ho-ease), opacity 260ms var(--ho-ease);
      opacity: 0;
    }

    .ho-overlay-backdrop.is-visible .ho-overlay-card {
      opacity: 1;
      transform: translateY(0) scale(1);
    }

    .ho-overlay-close {
      position: absolute;
      top: 14px;
      right: 14px;
      z-index: 2;
      display: grid;
      place-items: center;
      width: 42px;
      height: 42px;
      border: 1px solid rgba(216,199,178,.78);
      border-radius: 999px;
      background: rgba(255,255,255,.82);
      color: var(--ho-text);
      font-size: 28px;
      line-height: 1;
      font-weight: 700;
      cursor: pointer;
      -webkit-tap-highlight-color: transparent;
    }

    .ho-note-overlay {
      padding: clamp(28px, 4vw, 42px);
      border-radius: 28px;
      background: linear-gradient(160deg, #fffdf5, #fff7e6);
      border: 1px solid var(--ho-border);
      box-shadow: 0 28px 88px rgba(24,22,19,.28);
    }

    .ho-note-overlay::before {
      content: "";
      position: absolute;
      top: 20px;
      left: 24px;
      right: 78px;
      height: 3px;
      border-radius: 999px;
      background: linear-gradient(90deg, var(--ho-accent), var(--ho-primary), var(--ho-secondary));
    }

    .ho-note-overlay .light,
    .ho-cta-overlay .porch {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 16px;
      font-weight: 850;
    }

    .ho-note-overlay .light {
      color: var(--ho-secondary);
    }

    .ho-note-overlay .bulb,
    .ho-cta-overlay .bulb {
      width: 12px;
      height: 12px;
      border-radius: 999px;
      background: var(--ho-accent);
      box-shadow: 0 0 0 6px rgba(242,176,30,.22), 0 0 20px rgba(242,176,30,.82);
    }

    .ho-note-overlay h2 {
      margin: 22px 0 16px;
      font-size: clamp(28px, 4vw, 40px);
      line-height: 1;
    }

    .ho-note-overlay p {
      margin: 0 0 14px;
      font-size: 17px;
      line-height: 1.55;
    }

    .ho-note-overlay .sig {
      margin-top: 18px;
      font-family: var(--ho-font-display);
      font-size: 26px;
      letter-spacing: .02em;
      color: var(--ho-text);
    }

    .ho-cta-overlay {
      padding: clamp(34px, 6vw, 58px);
      border-radius: 30px;
      text-align: center;
      background: radial-gradient(circle at 50% -10%, rgba(242,176,30,.5), transparent 52%), linear-gradient(160deg, #2f5e36, #234a29);
      color: #fff;
      box-shadow: 0 30px 90px rgba(35,74,41,.42);
    }

    .ho-cta-overlay .porch {
      color: #ffe9b8;
    }

    .ho-cta-overlay h2 {
      margin: 0;
      font-family: var(--ho-font-display);
      text-transform: uppercase;
      font-size: clamp(32px, 5.6vw, 56px);
      line-height: .94;
      letter-spacing: .02em;
    }

    .ho-cta-overlay p {
      max-width: 56ch;
      margin: 16px auto 26px;
      font-size: clamp(17px, 1.6vw, 20px);
      opacity: .95;
      line-height: 1.6;
    }

    @media (max-width: 680px) {
      .ho-overlay-card {
        width: min(100%, 620px);
        max-height: min(76svh, 700px);
      }

      .ho-note-overlay,
      .ho-cta-overlay {
        border-radius: 24px;
      }

      .ho-note-overlay {
        padding: 30px 22px 24px;
      }

      .ho-note-overlay::before {
        top: 17px;
        left: 22px;
        right: 74px;
      }

      .ho-note-overlay h2 {
        font-size: 30px;
      }

      .ho-note-overlay .sig {
        font-size: 23px;
      }

      .ho-cta-overlay {
        padding: 34px 22px 24px;
      }

      .ho-cta-overlay h2 {
        font-size: clamp(28px, 9vw, 38px);
      }

      .ho-cta-overlay .btn {
        width: 100%;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      .ho-overlay-backdrop,
      .ho-overlay-card {
        transition: none;
      }
    }


    /* v069 overlay centering + shared font normalization */
    .ho-overlay-backdrop,
    .ho-overlay-backdrop.is-visible {
      align-items: center !important;
      justify-items: center !important;
      place-items: center !important;
      padding:
        max(18px, env(safe-area-inset-top, 0px))
        18px
        max(18px, env(safe-area-inset-bottom, 0px)) !important;
    }

    .ho-overlay-card {
      margin: auto;
      font-family: var(--ho-font-body, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
    }

    .btn,
    .ho-nav a,
    .ho-chip,
    .ho-plan-tag,
    .ho-price-foot,
    .ho-guide-steps span,
    .ho-note-overlay .light,
    .ho-cta-overlay .porch,
    .ho-overlay-close {
      font-family: var(--ho-font-body, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif) !important;
      letter-spacing: normal;
    }

    .ho-h2,
    .ho-hero h1,
    .ho-guide-card h2,
    .ho-note-overlay h2,
    .ho-cta-overlay h2,
    .ho-plan h3,
    .ho-feat span,
    .ho-notrow h3,
    .ho-step .num,
    .ho-note-overlay .sig {
      font-family: var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif);
    }

    .ho-promise,
    .ho-feat,
    .ho-plan,
    .ho-step,
    .ho-faq,
    .ho-guide-card,
    .ho-notrow,
    .ho-note-overlay,
    .ho-cta-overlay {
      font-family: var(--ho-font-body, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
    }

    .ho-cta-overlay .btn,
    .ho-plan .btn,
    .ho-hero-cta .btn {
      font-family: var(--ho-font-body, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif) !important;
      font-weight: 850;
      letter-spacing: normal;
    }

    .ho-overlay-close {
      font-size: 26px;
      font-weight: 800;
      line-height: 1;
    }

    @media (max-width: 680px) {
      .ho-overlay-backdrop,
      .ho-overlay-backdrop.is-visible {
        align-items: center !important;
        justify-items: center !important;
        place-items: center !important;
        padding:
          max(14px, env(safe-area-inset-top, 0px))
          14px
          max(14px, env(safe-area-inset-bottom, 0px)) !important;
      }

      .ho-overlay-card {
        width: min(100%, 620px);
        max-height: min(76svh, 700px);
      }

      .ho-note-overlay,
      .ho-cta-overlay {
        transform-origin: center center;
      }

      .btn,
      .ho-nav a,
      .ho-chip,
      .ho-guide-steps span {
        font-size: 16px;
      }
    }


    /* v070 public homepage type consistency
       Use the same Hoosier display treatment for major buttons and preview guide boxes. */
    .ho-home .btn,
    .ho-home .ho-nav a,
    .ho-home .ho-guide-steps span,
    .ho-home .ho-chip,
    .ho-home .ho-plan-tag {
      font-family: var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif) !important;
      text-transform: none;
      letter-spacing: .015em;
      font-weight: 700;
      font-synthesis: none;
    }

    .ho-home .btn,
    .ho-home .ho-nav-cta {
      font-size: 18px;
      line-height: 1;
    }

    .ho-home .ho-guide-steps span {
      font-size: 21px;
      line-height: 1.05;
      color: var(--ho-text);
    }

    .ho-home .ho-guide-steps b {
      font-family: var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif) !important;
      font-weight: 700;
      font-size: 20px;
    }

    .ho-home .ho-chip {
      font-size: 20px;
      line-height: 1.05;
      color: var(--ho-text);
    }

    .ho-home .ho-plan-tag,
    .ho-home .ho-eyebrow {
      font-family: var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif) !important;
      letter-spacing: .18em;
      font-weight: 700;
    }

    .ho-home .ho-overlay-close {
      font-family: var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif) !important;
    }

    @media (max-width: 680px) {
      .ho-home .btn,
      .ho-home .ho-nav-cta {
        font-size: 17px;
      }

      .ho-home .ho-guide-steps span {
        font-size: 20px;
      }

      .ho-home .ho-chip {
        font-size: 19px;
      }
    }

    @media (max-width: 430px) {
      .ho-home .ho-guide-steps span,
      .ho-home .ho-chip {
        font-size: 18px;
      }
    }


    /* v071 definitive public homepage type system
       Rule:
       - Display font = all headings, labels, nav, buttons, tags, chips, cards, pricing numbers, overlay labels.
       - Body font = readable paragraphs, descriptions, FAQ answers, list body copy.
       This block intentionally sits last and uses high specificity to override earlier pasted CSS. */

    body.ho-home {
      --ho-display-stack: var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", "Roboto Condensed", sans-serif);
      --ho-body-stack: var(--ho-font-body, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif);
      font-family: var(--ho-body-stack) !important;
    }

    body.ho-home h1,
    body.ho-home h2,
    body.ho-home h3,
    body.ho-home .ho-h2,
    body.ho-home .ho-eyebrow,
    body.ho-home .ho-brand,
    body.ho-home .ho-nav a,
    body.ho-home .btn,
    body.ho-home .ho-chip,
    body.ho-home .ho-guide-card h2,
    body.ho-home .ho-guide-steps span,
    body.ho-home .ho-guide-steps b,
    body.ho-home .ho-promise h3,
    body.ho-home .ho-promise .mark,
    body.ho-home .ho-notrow h3,
    body.ho-home .ho-feat span,
    body.ho-home .ho-plan-tag,
    body.ho-home .ho-plan h3,
    body.ho-home .ho-plan .price b,
    body.ho-home .ho-step .num,
    body.ho-home .ho-step h3,
    body.ho-home .ho-faq h3,
    body.ho-home .ho-cta-card h2,
    body.ho-home .ho-cta-card .porch,
    body.ho-home .ho-note .light,
    body.ho-home .ho-note .sig,
    body.ho-home .ho-note-overlay .light,
    body.ho-home .ho-note-overlay h2,
    body.ho-home .ho-note-overlay .sig,
    body.ho-home .ho-cta-overlay .porch,
    body.ho-home .ho-cta-overlay h2,
    body.ho-home .ho-overlay-close,
    body.ho-home .ho-foot .staff {
      font-family: var(--ho-display-stack) !important;
      font-synthesis: none;
    }

    body.ho-home p,
    body.ho-home li,
    body.ho-home .ho-hero-lede,
    body.ho-home .ho-lede,
    body.ho-home .ho-prose,
    body.ho-home .ho-prose p,
    body.ho-home .ho-promise p,
    body.ho-home .ho-notrow p,
    body.ho-home .ho-feat p,
    body.ho-home .ho-plan .renew,
    body.ho-home .ho-plan .best,
    body.ho-home .ho-plan li,
    body.ho-home .ho-plan .price span,
    body.ho-home .ho-step p,
    body.ho-home .ho-faq p,
    body.ho-home .ho-cta-card p,
    body.ho-home .ho-note p,
    body.ho-home .ho-note-overlay p,
    body.ho-home .ho-cta-overlay p,
    body.ho-home .ho-foot,
    body.ho-home .ho-foot a:not(.staff) {
      font-family: var(--ho-body-stack) !important;
      font-synthesis: none;
    }

    /* Display-treatment normalization */
    body.ho-home .ho-nav a,
    body.ho-home .btn,
    body.ho-home .ho-chip,
    body.ho-home .ho-guide-steps span,
    body.ho-home .ho-plan-tag,
    body.ho-home .ho-cta-card .porch,
    body.ho-home .ho-note .light,
    body.ho-home .ho-note-overlay .light,
    body.ho-home .ho-cta-overlay .porch {
      font-weight: 700 !important;
      letter-spacing: .015em !important;
      line-height: 1 !important;
      text-transform: none !important;
    }

    body.ho-home .ho-eyebrow,
    body.ho-home .ho-plan-tag,
    body.ho-home .ho-foot .staff {
      letter-spacing: .18em !important;
      text-transform: uppercase !important;
      font-weight: 700 !important;
    }

    body.ho-home .ho-nav a,
    body.ho-home .btn {
      font-size: 18px !important;
    }

    body.ho-home .ho-guide-steps span {
      font-size: 21px !important;
      color: var(--ho-text) !important;
    }

    body.ho-home .ho-chip {
      font-size: 20px !important;
      color: var(--ho-text) !important;
    }

    body.ho-home .ho-promise h3,
    body.ho-home .ho-step h3,
    body.ho-home .ho-faq h3 {
      font-size: 24px !important;
      line-height: 1.05 !important;
      letter-spacing: .015em !important;
    }

    body.ho-home .ho-notrow h3,
    body.ho-home .ho-feat span {
      letter-spacing: .035em !important;
      line-height: 1.04 !important;
      font-weight: 700 !important;
    }

    body.ho-home .ho-overlay-close {
      font-size: 28px !important;
      line-height: 1 !important;
      letter-spacing: 0 !important;
      text-transform: none !important;
    }

    @media (max-width: 680px) {
      body.ho-home .ho-nav a,
      body.ho-home .btn {
        font-size: 17px !important;
      }

      body.ho-home .ho-guide-steps span {
        font-size: 20px !important;
      }

      body.ho-home .ho-chip {
        font-size: 19px !important;
      }

      body.ho-home .ho-promise h3,
      body.ho-home .ho-step h3,
      body.ho-home .ho-faq h3 {
        font-size: 22px !important;
      }

      body.ho-home .ho-notrow h3,
      body.ho-home .ho-feat span {
        font-size: 24px !important;
      }
    }

    @media (max-width: 430px) {
      body.ho-home .ho-guide-steps span,
      body.ho-home .ho-chip {
        font-size: 18px !important;
      }

      body.ho-home .ho-nav a,
      body.ho-home .btn {
        font-size: 16px !important;
      }
    }


    /* v072 hero cleanup */
    body.ho-home .ho-hero-lede {
      max-width: 58ch;
    }

    body.ho-home .ho-hero-grid {
      padding-bottom: clamp(14px, 3vw, 24px);
    }


    /* v073 homepage cleanup: remove drift between sections */
    body.ho-home {
      --ho-title-color: var(--ho-text);
      --ho-label-color: var(--ho-primary);
      --ho-card-bg: rgba(255,255,255,.72);
      --ho-card-border: var(--ho-border);
    }

    body.ho-home .ho-head-inner {
      justify-content: space-between;
    }

    body.ho-home .ho-nav {
      justify-content: flex-end;
    }

    body.ho-home .ho-nav-links a {
      color: var(--ho-text) !important;
      background: transparent !important;
      border: 0 !important;
    }

    body.ho-home .ho-eyebrow {
      color: var(--ho-label-color) !important;
    }

    body.ho-home .ho-promise h3,
    body.ho-home .ho-notrow h3,
    body.ho-home .ho-feat span,
    body.ho-home .ho-plan h3,
    body.ho-home .ho-step h3,
    body.ho-home .ho-faq h3 {
      color: var(--ho-title-color) !important;
      font-family: var(--ho-display-stack, var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif)) !important;
      font-weight: 700 !important;
      letter-spacing: .025em !important;
      line-height: 1.04 !important;
      text-transform: uppercase !important;
    }

    body.ho-home .ho-promise p,
    body.ho-home .ho-notrow p,
    body.ho-home .ho-feat p,
    body.ho-home .ho-plan .renew,
    body.ho-home .ho-plan .best,
    body.ho-home .ho-plan li,
    body.ho-home .ho-step p,
    body.ho-home .ho-faq p,
    body.ho-home .ho-lede,
    body.ho-home .ho-hero-lede,
    body.ho-home .ho-prose p {
      color: var(--ho-muted) !important;
      font-family: var(--ho-body-stack, var(--ho-font-body, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif)) !important;
      letter-spacing: normal !important;
      text-transform: none !important;
    }

    body.ho-home .ho-promise,
    body.ho-home .ho-notrow,
    body.ho-home .ho-feat,
    body.ho-home .ho-plan,
    body.ho-home .ho-step,
    body.ho-home .ho-faq {
      background: var(--ho-card-bg) !important;
      border: 1px solid var(--ho-card-border) !important;
      border-radius: 20px !important;
      box-shadow: 0 12px 36px rgba(24,22,19,.045) !important;
    }

    body.ho-home .ho-notgrid,
    body.ho-home .ho-doorgrid,
    body.ho-home .ho-promises,
    body.ho-home .ho-steps,
    body.ho-home .ho-faq-list {
      gap: 14px !important;
    }

    body.ho-home .ho-notrow,
    body.ho-home .ho-feat {
      padding: 24px !important;
    }

    body.ho-home .ho-notrow {
      display: block !important;
      border-bottom: 1px solid var(--ho-card-border) !important;
    }

    body.ho-home .ho-notrow h3,
    body.ho-home .ho-feat span {
      display: block;
      margin: 0 0 10px !important;
      font-size: 25px !important;
    }

    body.ho-home .btn {
      font-family: var(--ho-display-stack, var(--ho-font-display, Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif)) !important;
      font-weight: 700 !important;
      letter-spacing: .015em !important;
    }

    body.ho-home .ho-preview-guide,
    body.ho-home .ho-guide-card {
      display: none !important;
    }

    @media (max-width: 680px) {
      body.ho-home .ho-nav-links {
        display: none !important;
      }

      body.ho-home .ho-head-inner {
        height: 56px !important;
      }

      body.ho-home .ho-brand img {
        height: 27px !important;
      }

      body.ho-home .ho-hero {
        padding-top: 42px !important;
      }

      body.ho-home .ho-notrow,
      body.ho-home .ho-feat,
      body.ho-home .ho-promise,
      body.ho-home .ho-plan,
      body.ho-home .ho-step,
      body.ho-home .ho-faq {
        padding: 22px !important;
        border-radius: 20px !important;
      }

      body.ho-home .ho-notrow h3,
      body.ho-home .ho-feat span {
        font-size: 24px !important;
      }
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
          <a href="#who">Who we are</a>
          <a href="#straight">Straight talk</a>
          <a href="#faq">Questions</a>
        </span>
      </nav>
    </div>
  </header>

  <main>
    <!-- HERO -->
    <section class="ho-hero ho-section">
      <div class="ho-wrap ho-hero-grid">
        <div>
          <p class="ho-eyebrow">You probably came in from your preview</p>
          <h1>Pull up<br>a <em>chair.</em></h1>
          <p class="ho-hero-lede">
            You already saw the thing. Not a vague “schedule a call and we’ll circle back” thing —
            the actual direction we’d build. This page is just where you decide whether we seem
            competent, local, and reasonably unlikely to make your life worse. No fake countdown,
            no lead-machine fairy tale, no guy in a headset calling your mowing business a “brand ecosystem.”
          </p>
        </div>      </div>
    </section>
    <!-- WHO WE ARE -->
    <section class="ho-who ho-section" id="who">
      <div class="ho-wrap">
        <p class="ho-eyebrow">Who you’re actually dealing with</p>
        <h2 class="ho-h2">We’re from here.<br>That’s sort of the whole point.</h2>
        <div class="ho-prose">
          <p>
            Hoosier Online is a small Indiana outfit — not a big agency with a sales floor in some
            other state, and not a faceless template factory churning out the same five pages for
            everybody. We’re people, here, who answer our own phone.
          </p>
          <p>
            We started this because we kept seeing the same thing happen to good local operators —
            the folks who show up on time, do honest work, and treat customers right. They’d either
            have nothing online at all, or some dusty old site that made them look closed, or they’d
            been talked into a sprawling six-week website project they didn’t need and couldn’t keep
            up with. Meanwhile the actual work was excellent. The front door was just a mess, and it
            was quietly costing them jobs.
          </p>
          <p>
            So we built something simpler, and we’ve kept it simple on purpose. The whole idea is to
            take the uncertainty out of getting online: we show you exactly what we’d make
            <em>before</em> you spend a dime, in plain language, with a price you can actually see.
            No jargon, no pressure, no “growth hacking.” Just a clean, honest front door for a
            business worth knocking on.
          </p>
        </div>
        <div class="ho-chips">
          <?php foreach ($chips as $c): ?>
            <span class="ho-chip"><?= ho_e($c) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- STRAIGHT TALK -->
    <section class="ho-section" id="straight">
      <div class="ho-wrap">
        <p class="ho-eyebrow">Straight talk</p>
        <h2 class="ho-h2">A few things we’ll always tell you straight.</h2>
        <p class="ho-lede">No fine print, no asterisks doing the heavy lifting down at the bottom of the page. Here’s how we actually do business.</p>
        <div class="ho-promises">
          <?php foreach ($promises as $p): ?>
            <article class="ho-promise">
              <div class="mark">✓</div>
              <div>
                <h3><?= ho_e($p['h']) ?></h3>
                <p><?= ho_e($p['t']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- WHAT WE'RE NOT -->
    <section class="ho-not ho-section" id="not">
      <div class="ho-wrap">
        <p class="ho-eyebrow">And while we’re being honest</p>
        <h2 class="ho-h2">Here’s what this is not.</h2>
        <p class="ho-lede">
          Plenty of folks in this line of work are happy to let you believe they do everything.
          We’d rather be clear about the edges, so you know exactly what you’re getting — and what
          you’re not.
        </p>
        <div class="ho-notgrid">
          <?php foreach ($nots as $n): ?>
            <div class="ho-notrow">
              <h3><?= ho_e($n[0]) ?></h3>
              <p><?= ho_e($n[1]) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- WHAT THIS IS -->
    <section class="ho-section" id="what">
      <div class="ho-wrap">
        <p class="ho-eyebrow">So what do you actually get</p>
        <h2 class="ho-h2">It’s one tidy front door for your business.</h2>
        <p class="ho-lede">
          Nothing fancy — a clean spot online that does the handful of things a local customer
          needs, so you never have to become “the website person.” Here’s what lives behind that door:
        </p>
        <div class="ho-doorgrid">
          <?php foreach ($door as $d): ?>
            <article class="ho-feat">
              <span><?= ho_e($d[0]) ?></span>
              <p><?= ho_e($d[1]) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- THE NUMBER -->
    <section class="ho-price ho-section" id="price">
      <div class="ho-wrap">
        <p class="ho-eyebrow">The honest number</p>
        <h2 class="ho-h2">Here’s the price. No homework, no haggling.</h2>
        <p class="ho-lede">
          You’ll know what it costs before you ever say yes, and it stays that number. Most folks
          start with Standard — you can always step up to Managed later, and we’ll still be here
          when you do.
        </p>

        <div class="ho-price-grid">
          <article class="ho-plan">
            <span class="ho-plan-tag">Most folks start here</span>
            <h3>Standard Front Door</h3>
            <div class="price"><b>$499</b><span>one-time setup</span></div>
            <p class="renew">Includes a full year of service. Renews at $250/year or $25/month after year one.</p>
            <p class="best">A clean online front door without a lot of ongoing fuss — set it up right, share the link, get back to work.</p>
            <ul>
              <li>Hosted business page built for your trade</li>
              <li>Services, offers, work, and a photo gallery</li>
              <li>Contact &amp; quote-request form, click-to-call</li>
              <li>Google / Facebook / social links tied together</li>
              <li>Booking or request path, payment link when needed</li>
              <li>Cleanup of the obvious old, broken, out-of-date stuff</li>
              <li>Mobile-friendly, hosting included for the year</li>
            </ul>
            <a class="btn btn-primary" href="<?= ho_e($mailto) ?>">This sounds about right <span class="arrow">→</span></a>
          </article>

          <article class="ho-plan">
            <span class="ho-plan-tag">If you’d rather not think about it</span>
            <h3>Managed Front Door</h3>
            <div class="price"><b>$999</b><span>setup + 3 months managed service</span></div>
            <p class="renew">Includes three months of managed service. Renews at $99/month after the first three months.</p>
            <p class="best">For folks who’d rather we keep the whole thing current and handle the changes as life and seasons happen.</p>
            <ul>
              <li>Everything in Standard, with more hands-on setup</li>
              <li>More photos, services, and offers loaded for you</li>
              <li>We make your updates and edits</li>
              <li>We keep info, hours, and offers current</li>
              <li>Ongoing cleanup as things drift over time</li>
              <li>Seasonal offer changes and priority fixes</li>
            </ul>
            <a class="btn btn-ghost" href="<?= ho_e($mailto) ?>">Tell me more about this one</a>
          </article>
        </div>
        <p class="ho-price-foot">Genuinely unsure which one fits? Tell us your trade and we’ll point you to the simpler one.</p>
      </div>
    </section>

    <!-- WHAT TO EXPECT -->
    <section class="ho-section" id="how">
      <div class="ho-wrap">
        <p class="ho-eyebrow">If you decide we’re your people</p>
        <h2 class="ho-h2">Here’s exactly what to expect.</h2>
        <p class="ho-lede">No mystery, no runaround. Three steps, and we carry the heavy part.</p>
        <div class="ho-steps">
          <?php foreach ($steps as $s): ?>
            <article class="ho-step">
              <div class="num"><?= ho_e($s[0]) ?></div>
              <h3><?= ho_e($s[1]) ?></h3>
              <p><?= ho_e($s[2]) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section class="ho-section" id="faq">
      <div class="ho-narrow">
        <p class="ho-eyebrow">Questions you’re probably already asking</p>
        <h2 class="ho-h2">The stuff you’d ask if we were sitting across the table.</h2>
        <div class="ho-faq-list">
          <?php foreach ($faqs as $f): ?>
            <article class="ho-faq">
              <h3><?= ho_e($f[0]) ?></h3>
              <p><?= ho_e($f[1]) ?></p>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
    <!-- BOTTOM PORCH OVERLAY TRIGGER -->
    <div id="porch-bottom-trigger" class="ho-bottom-trigger" aria-hidden="true"></div>
  </main>


  <div class="ho-overlay-backdrop" id="helloOverlay" aria-hidden="true">
    <section class="ho-overlay-card ho-note-overlay" role="dialog" aria-modal="true" aria-labelledby="helloOverlayTitle">
      <button class="ho-overlay-close" type="button" data-close-overlay="helloOverlay" aria-label="Close note">×</button>
      <span class="light"><span class="bulb"></span> The porch light’s on</span>
      <h2 id="helloOverlayTitle">Hey there —</h2>
      <p>
        You found the homepage. Most folks land here from the preview we put together for
        their business, just to see who’s behind it.
      </p>
      <p>
        No robot wrote that preview, and no robot wrote this. Look around as long as you like.
        When you’re ready, we’ll be right here.
      </p>
      <p class="sig">— The Hoosier Online folks</p>
    </section>
  </div>

  <div class="ho-overlay-backdrop" id="porchOverlay" aria-hidden="true">
    <section class="ho-overlay-card ho-cta-overlay" role="dialog" aria-modal="true" aria-labelledby="porchOverlayTitle">
      <button class="ho-overlay-close" type="button" data-close-overlay="porchOverlay" aria-label="Close contact prompt">×</button>
      <span class="porch"><span class="bulb"></span> We’ll Leave The Porch Light On</span>
      <h2 id="porchOverlayTitle">No rush. Come find us when you’re ready.</h2>
      <p>
        When you’re ready to move forward, you already have the link to get started.
        Once you finalize the details on your project, we will get working on it within
        a few hours and have a real deliverable for you within 48 hours guaranteed.
      </p>
    </section>
  </div>

  <script>
    (function () {
      var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      function openOverlay(id) {
        var overlay = document.getElementById(id);
        if (!overlay || overlay.dataset.dismissed === '1') return;
        overlay.classList.add('is-visible');
        overlay.setAttribute('aria-hidden', 'false');
      }

      function closeOverlay(id) {
        var overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.dataset.dismissed = '1';
      }

      document.addEventListener('click', function (event) {
        var close = event.target.closest('[data-close-overlay]');
        if (close) {
          closeOverlay(close.getAttribute('data-close-overlay'));
          return;
        }

        if (event.target.classList && event.target.classList.contains('ho-overlay-backdrop')) {
          closeOverlay(event.target.id);
        }
      });

      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        closeOverlay('helloOverlay');
        closeOverlay('porchOverlay');
      });

      window.setTimeout(function () {
        openOverlay('helloOverlay');
      }, prefersReducedMotion ? 0 : 1000);

      var bottomTrigger = document.getElementById('porch-bottom-trigger');
      if ('IntersectionObserver' in window && bottomTrigger) {
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              openOverlay('porchOverlay');
              observer.disconnect();
            }
          });
        }, { threshold: 0.2 });
        observer.observe(bottomTrigger);
      } else {
        window.addEventListener('scroll', function () {
          if ((window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 220)) {
            openOverlay('porchOverlay');
          }
        }, { passive: true });
      }
    })();
  </script>

  <footer class="ho-foot">
    <div class="ho-wrap ho-foot-inner">
      <span>&copy; <?= date('Y') ?> Hoosier Online — Local roots. Local pros. Local pride.</span>
      <span>
        <a href="<?= ho_e($mailto) ?>"><?= ho_e($email) ?></a>
        &nbsp;·&nbsp;
        <a class="staff" href="/money.php">Staff</a>
      </span>
    </div>
  </footer>

</body>
</html>
