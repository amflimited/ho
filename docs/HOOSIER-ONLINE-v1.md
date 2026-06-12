# Hoosier Online — v1 Technical Analysis

*Written in the first person, as the software itself.*

---

## 0. What I Am

I am **Hoosier Online** — a single-operator sales engine that finds Indiana
local-service businesses with a weak online presence, builds each one a
personalized "front door" preview page, prices a specific offer for them, and
hands my operator (Adam) everything he needs to pitch and close — from his
iPhone, one tap at a time.

I am not a website builder, a CRM, or a marketing suite. I am a **lead-to-cash
pipeline** with a human in exactly one loop: the GPT research step. Everything
before and after that step, I do myself.

My design center is a single constraint that shapes every screen I render:
**my operator works exclusively on an iPhone.** No file downloads, no terminal,
no multi-step desktop flows. If a task can't be done with a thumb, it isn't done.

**Stack:** Vanilla PHP 8.x · MySQL (PDO) · HostGator shared hosting · zero
framework, zero Composer dependencies · GitHub Actions FTP auto-deploy.

**Surface area:** ~4,640 lines across my three core organs —
`ho-model.php` (2,197), `app.php` (1,430), `go.php` (1,013) — plus integration
endpoints, a template system, and three CSS design languages.

---

## 1. The Pipeline — My Spine

Every business that enters me moves through a single `pipeline_status` state
machine. This column on the `businesses` table is the source of truth for where
anyone stands, and almost every query I run filters on it.

```
identified
   │  (GPT research pass)
   ▼
researched ──► ho_auto_generate_preview()
   │
   ├─ no / poor website ─────────► preview_ready      (preview_type = site_build)
   ├─ decent website + gaps ─────► enhancement_ready  (preview_type = enhancement)
   ├─ decent website, no gaps ───► excluded           (reason: has_good_website)
   └─ zero contact info ─────────► needs_contact
                                        │
   (operator pitches) ──────────► pitched
                                        │
                          ┌─────────────┼─────────────┐
                          ▼             ▼             ▼
                      converted     not_a_fit      excluded
```

**`pipeline_status` ENUM:** `identified`, `researched`, `preview_ready`,
`enhancement_ready`, `pitched`, `converted`, `not_a_fit`, `needs_contact`,
`excluded`.

The routing decision — the single most important branch in me — lives in
`ho_auto_generate_preview()` (`ho-model.php:720`). It reads a freshly-researched
business and decides which of two products it qualifies for. That decision is
permanent until re-research overrides it.

---

## 2. My Two Products

I sell exactly two things, and I decide which one each lead sees. Neither
presents a menu. **Every lead gets one offer — not options, not choices.**

### 2a. Site Build — `preview_type = site_build`

For businesses with **no website or a poor one.** I build them a real,
category-matched website preview and sell it as a single flat offer:

- **One price: $199**, one-time, owned forever — no monthly fees, no platform.
- **One design.** I match a visual template to their trade
  (`ho_design_direction()`, `ho-model.php:1519`) and show it as *their* site,
  already built. The old four-way "pick your look" picker is gone — a lead
  deciding whether to trust me with money should not also be asked to art-direct.
- **One domain**, their `.com`, checked live for availability against Porkbun
  and included free.
- **One checkout** — a single Stripe button carrying the matched `template_key`.

### 2b. Enhancement — `preview_type = enhancement`

For businesses that **already have a decent website** but are leaving money on
the table. Instead of selling them a site they don't need, I diagnose specific
gaps and price a custom bundle to close them. This is contact-first — no Stripe,
a direct "call or email me" CTA — because these are consultative sells.

The enhancement track is powered by my gap engine (Section 4) and my pricing
engine (Section 5).

---

## 3. My Memory — The Data Model

I persist everything in ten MySQL tables. PDO, prepared statements everywhere,
no ORM.

| Table | What it holds |
|---|---|
| `businesses` | The lead. Identity, contact info, `pipeline_status`, `owner_first_name`, category FK. The spine. |
| `research_records` | Everything GPT learned — **69 fields** per business across website, GBP, social, reputation, branding, and competitor intelligence. One row per business. |
| `previews` | The rendered offer. `preview_slug`, `preview_type`, headline/subheadline, and `package_items` (the pre-computed priced bundle, JSON). |
| `categories` | The ~30 service trades I target, each with typical services and a template family. |
| `gap_prices` | The 16 gap types and their editable prices. The pricing brain. |
| `source_runs` / `source_candidates` | A sourcing session and the raw GPT-suggested businesses before promotion. |
| `outreach_log` | Every pitch sent — channel, recipient, timestamp, outcome. Drives follow-ups. |
| `orders` | A closed sale — package, chosen domain, design, Stripe token, fulfillment status. |
| `business_exclusions` | A normalized blocklist so a rejected business never re-enters me. |

### The 69-Field Research Record

The single richest thing about me is how much I know about each lead before I
ever pitch. One GPT pass fills all of it — eliminating the old habit of
re-queuing the same business repeatedly for one missing fact. The fields group
into:

- **Website depth** — `has_contact_form`, `has_online_booking`,
  `has_photo_gallery`, `has_testimonials_section`, `site_appears_outdated`,
  `has_ssl`, `mobile_friendly`, and a dozen more. (All null when no website.)
- **Google Business Profile** — review count, rating, photo count,
  `has_gbp_posts`, `gbp_services_listed`, `gbp_hours_listed`,
  `last_review_date`, `responds_to_reviews`. (All null when no GBP.)
- **Social** — Facebook/Instagram presence, activity, page type, follower band,
  last-post recency.
- **Reputation & branding** — Yelp claimed/unclaimed, BBB, logo quality,
  `has_before_after_photos`, `is_licensed_insured_visible`,
  `has_professional_email`.
- **Business intelligence** — booking method, years in business, owner first
  name, owner age band, target customer type, franchise flag.
- **Competitor intel** — primary local competitor name, website, rating, and
  review count.

Null semantics are deliberate: a field is `null` when the parent doesn't exist
(no website → website fields null) and `false`/`0` when it exists but lacks the
feature. That distinction is what lets my gap engine fire precisely.

---

## 4. The Gap Engine — How I Diagnose

`ho_enhancement_gaps()` (`ho-model.php:871`) is the diagnostic core of the
enhancement product. It reads a research record and returns an ordered list of
**16 possible gaps**, each a concrete, sellable problem:

| Gap key | Fires when… |
|---|---|
| `tech_issues` | site not mobile-friendly OR no SSL |
| `contact_form` | booking is phone/Facebook/email only — no web form |
| `online_booking` | `has_online_booking = 0` |
| `site_outdated` | `site_appears_outdated = 1` |
| `paid_leads` | listed on Angi or Thumbtack (renting leads) |
| `google_business` | no Google Business Profile |
| `gbp_incomplete` | GBP missing posts, services, or hours |
| `gbp_photos` | fewer than 10 photos on GBP |
| `stale_reviews` | newest review 6+ months old (with ≥3 reviews) |
| `no_before_after` | `has_before_after_photos = 0` |
| `no_gallery` | `has_photo_gallery = 0` |
| `no_testimonials` | `has_testimonials_section = 0` |
| `dead_facebook` | page exists but dormant / 3+ months silent |
| `freemail` | using a personal Gmail/Yahoo address |
| `no_trust_signals` | no license/insurance shown |
| `yelp_unclaimed` | Yelp listing exists but unclaimed |

Gaps are **priority-sorted** so the most persuasive problem leads the pitch.
`contact_form` leads by default; `tech_issues` jumps to #1 when *both* mobile and
SSL are broken, because that's an active Google ranking penalty I can point to.

If a business has a decent site and **zero** gaps, it isn't a target — I exclude
it honestly rather than invent a reason to sell.

Every gap is defensively guarded (`isset()` on its source column), so a gap
simply stays dormant until its research field exists and carries data. That
property let me ship the full 16-gap engine before every lead had been
re-researched — new gaps light up automatically as the data arrives.

---

## 5. The Pricing Engine — How I Quote

Three functions turn a list of gaps into a priced, line-itemized offer.

- **`ho_gap_prices(PDO)`** (`ho-model.php`) — loads all 16 prices from the
  `gap_prices` table, request-cached, with a complete hardcoded fallback so I
  never fail to quote even if the table is empty or missing.
- **`ho_gap_label(string)`** — a static label for every gap, DB-independent.
- **`ho_build_package_items(PDO, gaps)`** — assembles the priced bundle:
  `[{gap_key, label, price}, …]`.

The current price book (operator-editable via the `gap_prices` table):

| Gap | Price | Gap | Price |
|---|---|---|---|
| Mobile & SSL Fix | $249 | GBP Profile Completion | $99 |
| Online Booking System | $199 | Facebook Page & Content | $99 |
| Contact & Quote Form | $99 | Review Request Campaign | $49 |
| Site Redesign / Refresh | $99 | Before & After Photos | $49 |
| Lead Capture Landing Page | $99 | Photo Gallery | $49 |
| Google Business Setup | $99 | Testimonials Section | $49 |
| Photo Shoot & GBP Upload | $99 | Professional Email Setup | $49 |
| | | License & Insurance Display | $49 |
| | | Claim & Optimize Yelp | $49 |

When a business routes to the enhancement track, I compute its bundle **once**
and freeze it into `previews.package_items` (JSON). The lead's page and my
operator's send queue both render the same numbers — no drift, no recompute.
For any preview created before the pricing engine existed, I fall back to a live
computation, so old leads price correctly too.

---

## 6. The Front Door — `go.php`

This is the only thing a prospect ever sees: a single, mobile-first page at
`/go/{slug}`. It detects its mode from `preview_type` and renders one of two
experiences from the same file.

**Shared opening** — a personalized hero ("Hey {owner} —"), then a
**"Why I reached out"** card built entirely from structured research signals
(not free text): their mobile/SSL flags, whether they're renting Angi leads,
their review recency, their photo count. Every claim is backed by a field.

**Site-build mode** then renders: the live design preview in a phone frame
(one matched template), the domain confirmation, the $199 offer with a
competitor price comparison (Wix/GoDaddy vs. owned-forever), a 24-hour
live-or-free guarantee, and the single Stripe checkout.

**Enhancement mode** instead renders: the **"What I'd fix"** card — one
priced line item per detected gap, each with copy written against *their* data
(the freemail card suggests an address at their own domain; the tech card
varies by whether one flag or both are broken) — a flat one-time **bundle
total**, and a total-led "call or email me" CTA. No mockup, no Stripe.

---

## 7. The Cockpit — `app.php`

My operator's entire workspace is one mobile page with tabs that follow the
work: **Source → Research → Send → Sales.** A "current job" indicator
(`ho_current_job()`) reads the pipeline counts and tells him what to do next, so
he's never staring at a blank dashboard wondering where to start.

- **Source** — start a sourcing run for a category + area; I generate the GPT
  prompt and ingest the candidates.
- **Research** — the heart. I show the batch of unresearched (and stale) leads,
  generate the 69-field research prompt, and ingest the result. This tab also
  holds my **audit tools**: a live website checker, a hidden-website finder, and
  the **re-route decent-site leads** button that sweeps stuck leads into the
  enhancement track and builds their priced offers in bulk.
- **Send** — the unified outreach queue. Site-build and enhancement leads each
  get a card with one-tap pitch links, gap chips, the bundle total, Verify-on-
  Google and Preview links, and Mark-Sent. Leads with no contact info are held
  back and surfaced for a re-queue rather than wasting a slot.
- **Sales** — pending orders, fulfillment status, and post-payment status
  updates to send the customer.

---

## 8. The GPT Round Trip — My One Human Loop

I don't call the OpenAI API directly; my operator carries each prompt to ChatGPT
and brings back the JSON. There are four such loops — **sourcing, research,
contact, enrichment** — each with a generator (`ho_generate_*_prompt`) and an
importer (`ho_import_*_json`). Every importer runs through `ho_clean_json()`,
which strips code fences and chatter before parsing, and matches results back to
businesses by echoed ID first, name second.

**This was the slowest, most tap-heavy part of me — so I rebuilt it.** The new
one-tap round trip:

- **Outbound — "Ask ChatGPT":** every prompt box now has a deep link
  (`chatgpt.com/?q=…`, search-hint on) that opens ChatGPT with the entire prompt
  *already typed into the composer.* My operator just hits send. If a batch ever
  encodes past a safe URL length, the button degrades to copy-only guidance so a
  prompt is never silently truncated.
- **Inbound — "Paste & Import":** every import form has a one-tap button
  (`hoPasteImport`) that reads the clipboard, strips GPT's fences and prose,
  finds the JSON, validates it, **reports the count** ("✓ 8 results found —
  importing…"), fills the textarea, and submits itself. Invalid or empty
  clipboard degrades gracefully to the manual flow.

What was a ~10-touch dance of scrolling, selecting, copying, app-switching, and
pasting is now **four taps with zero text selection.**

---

## 9. My Integrations

| Service | Role | Touchpoint |
|---|---|---|
| **OpenAI / ChatGPT** | All research & sourcing intelligence (human-relayed) | `ho_generate_*_prompt` / `ho_import_*_json` |
| **Stripe** | Site-build checkout & payment | `checkout.php`, `webhook.php` |
| **Porkbun** | Live `.com` domain availability | `porkbun.php`, `domain-check.php` |
| **Live HTTP probes** | SSL + mobile-viewport tech check | `ho_website_tech_check()`, `audit-url.php`, `audit-domain.php` |
| **GitHub Actions** | FTP auto-deploy on push | `SamKirkland/FTP-Deploy-Action@v4.3.5` |

On a paid Stripe event, `webhook.php` creates the order, flips the business to
`converted`, and emails both parties — the full close, automated.

---

## 10. My Defenses

- **Platform-URL hygiene.** `ho_is_lead_platform_url()` blocks Angi, Thumbtack,
  Yelp, HomeAdvisor, Houzz, Bark, Porch, Networx, and HomeGuide URLs from ever
  being stored as a contactable website or used as an outreach path. A lead-rental
  listing is a *gap*, never a contact.
- **Honest data over invented sells.** I filter AI-generated gap text that
  contradicts my structured fields, exclude genuine non-targets instead of
  forcing a pitch, and make no performance promises.
- **No-contact containment.** Leads with zero reachable contact info are held
  out of the send queue and routed to contact-research, never shown as pitchable.
- **Blocklist permanence.** Excluded businesses are normalized into
  `business_exclusions` so they can't slip back in under a slightly different name.
- **Secret discipline.** Credentials (`database.php`, `admin-secrets.php`,
  `porkbun-config.php`) and `stripe-config.php` (stored outside the web root) are
  never committed. Schema changes are applied by the operator via phpMyAdmin, never
  by automated migration.

---

## 11. What Shipped in This Cycle

This release turned me from a lead-finder into a closer:

1. **69-field research** in a single pass — I learn everything about a lead at
   once instead of re-queuing for one fact at a time.
2. **16-gap diagnostic engine** — up from 6 — with every gap rendered and priced.
3. **A pricing brain** — `gap_prices` + frozen `package_items`, so every
   enhancement lead carries a real, itemized, operator-editable quote.
4. **One offer per lead, both tracks** — site-build collapsed to a single
   $199/one-design offer; enhancement shows a single priced bundle. No menus.
5. **Re-route button** — stuck decent-site leads swept into the enhancement track
   with full gap detection in one tap.
6. **One-tap GPT round trip** — the human loop cut from ~10 touches to 4.

---

## 12. Where I'm Headed (v1.1 backlog)

- **In-app gap price editor** — edit the 16 prices from the cockpit, no phpMyAdmin.
- **Real-data validation** — confirm the 10 newer gaps populate and price
  correctly once a batch is re-researched through the 69-field prompt.
- **The first real closes** — the build is done; the next thing that moves me
  forward is live businesses flowing through the pipeline end to end.

---

*Hoosier Online v1 — built by Adam Ferree. One operator, one iPhone, one offer
per lead. Front doors for Indiana's hardest-working businesses.*
