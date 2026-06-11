# Hoosier Online — Claude Code Handoff

> This file is read automatically by Claude Code on session start.
> It captures the architectural state and pending work as of 2026-06-09.
> Do NOT include credentials, API keys, or secret file paths here.

---

## Project Overview

**Hoosier Online** is a B2B lead-generation and sales tool built by Adam Ferree.
It identifies Indiana local service businesses (lawn care, cleaning, handyman, etc.)
that lack a professional web presence, builds them a personalised preview page,
then pitches them a website build or enhancement service via email/phone.

**Stack:** PHP (no framework), MySQL, HostGator shared hosting, GitHub Actions FTP deploy.

**Branch:** All active work lives on `claude/admin-site-assessment-4Zrig`.
`SamKirkland/FTP-Deploy-Action@v4.3.5` deploys every push to this branch to production.

---

## Security Constraints (PERMANENT — never violate)

- `database.php` — live credentials — **NEVER commit**
- `admin-secrets.php` — **NEVER commit**
- `stripe-config.php` — lives at `/home1/spofnkte/stripe-config.php` (outside public_html) — **NEVER commit**
- `porkbun-config.php` — **NEVER commit**
- Database schema changes = **phpMyAdmin SQL tab only** (Adam runs them; do NOT repeat the reminder each session)
- Adam works exclusively on **iPhone** — never instruct file downloads, git pulls, terminal commands, or multi-step file operations on his end
- Code changes = push to `claude/admin-site-assessment-4Zrig` — never push to a different branch without explicit permission

---

## Key Files

| File | Purpose |
|---|---|
| `ho-model.php` | All DB/business logic — pipeline, research, preview, enhancement, emails |
| `app.php` | Admin cockpit UI (Research, Send, Orders tabs) |
| `go.php` | Public-facing preview/pitch page for each lead |
| `source-model.php` | GPT prompt builder for sourcing new leads |
| `audit-domain.php` | Technical domain/website audit tool |
| `assets/css/front-door.css` | Styles for go.php |

---

## Pipeline Architecture

```
identified
  → [GPT research] → researched
    → ho_auto_generate_preview()
      → has decent/good website → ho_route_to_enhancement()
          → has gaps → enhancement_ready (preview_type='enhancement')
          → no gaps  → excluded (reason='has_good_website')
      → no/poor website → preview_ready (preview_type='site_build')
  → no contact info found → needs_contact
pitched → converted / not_a_fit / excluded
```

**`pipeline_status` ENUM values:**
`identified`, `researched`, `preview_ready`, `pitched`, `converted`,
`not_a_fit`, `needs_contact`, `excluded`, `enhancement_ready`

**`previews.preview_type` ENUM:** `site_build`, `enhancement`

---

## Core Functions in ho-model.php

### Research
- `ho_generate_research_prompt(array $businesses): string`
  — Builds a GPT prompt capturing 69 fields per business in one pass.
  New as of 2026-06-09 — eliminates the need for re-queuing.
- `ho_import_research_json(PDO, string): array`
  — Upserts research_records with all 69 fields; auto-runs tech check; calls `ho_auto_generate_preview()`.
- `ho_generate_enrichment_prompt(array $businesses): string` / `ho_import_enrichment_json()`
  — Fills in missing fields on previously-researched leads.

### Gap Detection
- `ho_enhancement_gaps(array $row): array`
  — Returns sorted array of gap keys for a business that has a decent website.
  **16 gap types:** `tech_issues`, `contact_form`, `online_booking`, `site_outdated`,
  `paid_leads`, `google_business`, `gbp_incomplete`, `gbp_photos`, `stale_reviews`,
  `no_before_after`, `no_gallery`, `no_testimonials`, `dead_facebook`, `freemail`,
  `no_trust_signals`, `yelp_unclaimed`.
  Priority: `tech_issues` is #1 when both mobile AND SSL are broken; otherwise position 2.

### Routing
- `ho_auto_generate_preview(PDO, int $bizId): bool` — Routes a just-researched business.
- `ho_route_to_enhancement(PDO, int $bizId, array $row): bool`
  — Upserts an enhancement preview; sets `enhancement_ready` or `needs_contact`.
- `ho_is_lead_platform_url(string $url): bool`
  — Blocks Angi, Thumbtack, Yelp, HomeAdvisor, Houzz, Bark, Porch, Networx, HomeGuide URLs
  from being stored as contactable website URLs.

### Send Queues
- `ho_get_preview_ready(PDO): array` — Site-build leads ready to pitch.
- `ho_get_enhancement_ready(PDO): array` — Enhancement leads ready to pitch.
- `ho_count_no_contact_ready(PDO): int` / `ho_requeue_no_contact_leads(PDO): int`
  — Detect and re-queue stuck no-contact preview_ready leads.

### Email / Outreach
- `ho_pitch_message(array $biz, string $previewUrl): array` — Site-build outreach copy (`['subject','body']`). Single source of truth.
- `ho_pitch_message_enhancement(array $biz, string $previewUrl): array` — Enhancement outreach copy.
- `ho_pitch_mailto()` / `ho_pitch_mailto_enhancement()` — thin wrappers that encode the message into a `mailto:` link.
- `ho_quote_inline(string $raw): string` — cleans a verbatim review quote for inline use (whitespace, wrap-quote strip, word-boundary cap).
- Both message builders mirror go.php's personalization: lead with a real
  review quote → competitor scoreboard numbers → gap/strength hooks; both
  weave a conservative `ho_stakes_estimate()` dollar line. Send-queue cards
  expose a **Copy message** button (`copyMessage()` JS, card-scoped
  `.cp-msg-src` textarea) so the same copy can be pasted into a lead's own
  contact form — email and pasted message are byte-identical.
- The two send-queue SELECTs (`ho_get_preview_ready`, `ho_get_enhancement_ready`)
  pull `review_quote_1/_author` + `competitor_google_rating/_review_count`,
  with a try/catch fallback so the Send tab survives a pending quote migration.
- **Subject lines are hook-matched** (2026-06-10): each hook branch in both
  message builders sets its own subject (quote author's name, competitor name,
  review count, top gap…) so the inbox line and the email's first sentence
  tell one story. `"A quick note for {name}"` survives only as the
  no-signal fallback.

### Utilities
- `ho_is_freemail(string $email): bool` — Detects Gmail/Yahoo/Hotmail/etc. + pattern catch.
- `ho_pipeline_counts(PDO): array` — Badge counts for admin nav tabs.

---

## go.php Key Variables

```php
$isEnhancement  // true when preview_type='enhancement'
$ownerFirst     // owner first name from businesses.owner_first_name
$hi             // $ownerFirst if set, else $name (business name)
$services       // array from services_display or typical_services
$servicesList   // HTML <li> items for modules section
$subhead        // from previews.subheadline — used as design picker h2
$ratingNote     // contextualised review tier: exceptional/above average/room to grow
```

Enhancement track renders:
- WHY I REACHED OUT card (uses signal variables: `$notMobile`, `$noSsl`, `$hasAngi`, `$hasThumbtak`, `$bookingMethod`, `$gbpPhotos`, `$reviewAgeMonths`)
- Opportunity cards from `ho_enhancement_gaps()` — currently 4 cards max
- Direct-contact CTA (mailto + phone, no Stripe)

Site-build track renders:
- Phone-frame design picker
- Domain chooser
- Package/price configurator
- Stripe checkout form

---

## research_records Columns (as of 2026-06-09 schema migration)

**Must run in phpMyAdmin before new research imports work:**

```sql
ALTER TABLE research_records
  ADD COLUMN has_contact_form TINYINT(1) NULL AFTER has_ssl,
  ADD COLUMN has_online_booking TINYINT(1) NULL,
  ADD COLUMN has_photo_gallery TINYINT(1) NULL,
  ADD COLUMN has_about_page TINYINT(1) NULL,
  ADD COLUMN has_faq_page TINYINT(1) NULL,
  ADD COLUMN has_pricing_page TINYINT(1) NULL,
  ADD COLUMN has_video_on_site TINYINT(1) NULL,
  ADD COLUMN has_online_payment TINYINT(1) NULL,
  ADD COLUMN site_appears_outdated TINYINT(1) NULL,
  ADD COLUMN has_blog TINYINT(1) NULL,
  ADD COLUMN has_testimonials_section TINYINT(1) NULL,
  ADD COLUMN has_live_chat TINYINT(1) NULL,
  ADD COLUMN facebook_page_type VARCHAR(20) NULL,
  ADD COLUMN facebook_last_post_months INT NULL,
  ADD COLUMN facebook_follower_band VARCHAR(20) NULL,
  ADD COLUMN facebook_has_cta_button TINYINT(1) NULL,
  ADD COLUMN instagram_is_business TINYINT(1) NULL,
  ADD COLUMN instagram_follower_band VARCHAR(20) NULL,
  ADD COLUMN instagram_last_post_months INT NULL,
  ADD COLUMN has_gbp_posts TINYINT(1) NULL,
  ADD COLUMN gbp_services_listed TINYINT(1) NULL,
  ADD COLUMN gbp_hours_listed TINYINT(1) NULL,
  ADD COLUMN has_yelp TINYINT(1) NULL,
  ADD COLUMN yelp_claimed TINYINT(1) NULL,
  ADD COLUMN yelp_review_count SMALLINT NULL,
  ADD COLUMN yelp_rating DECIMAL(3,1) NULL,
  ADD COLUMN has_youtube TINYINT(1) NULL,
  ADD COLUMN has_nextdoor_listing TINYINT(1) NULL,
  ADD COLUMN has_bbb_listing TINYINT(1) NULL,
  ADD COLUMN logo_quality VARCHAR(20) NULL,
  ADD COLUMN has_before_after_photos TINYINT(1) NULL,
  ADD COLUMN has_professional_email TINYINT(1) NULL,
  ADD COLUMN is_licensed_insured_visible TINYINT(1) NULL,
  ADD COLUMN has_service_guarantee TINYINT(1) NULL,
  ADD COLUMN target_customer_type VARCHAR(20) NULL DEFAULT 'unknown',
  ADD COLUMN competitor_google_rating DECIMAL(3,1) NULL,
  ADD COLUMN competitor_review_count SMALLINT NULL;
```

---

## ⚠️ REQUIRED MIGRATION — research_records 37 columns

✅ CONFIRMED RUN 2026-06-10 — all 37 columns verified present via INFORMATION_SCHEMA.

---

## ⚠️ REQUIRED MIGRATION — review_quote columns (2026-06-10)

The trust/emotion upgrade adds 6 quote columns. Research and enrichment imports
will error on quote data until this runs. `ho_get_preview_by_slug()` has a
fallback so go.php stays up either way — but quotes won't render until the
ALTER runs and leads are re-enriched.

```sql
ALTER TABLE research_records
  ADD COLUMN review_quote_1        VARCHAR(400) NULL AFTER last_review_date,
  ADD COLUMN review_quote_1_author VARCHAR(60)  NULL AFTER review_quote_1,
  ADD COLUMN review_quote_1_date   VARCHAR(10)  NULL AFTER review_quote_1_author,
  ADD COLUMN review_quote_2        VARCHAR(400) NULL AFTER review_quote_1_date,
  ADD COLUMN review_quote_2_author VARCHAR(60)  NULL AFTER review_quote_2,
  ADD COLUMN review_quote_2_date   VARCHAR(10)  NULL AFTER review_quote_2_author;
```

---

## ⚠️ REQUIRED MIGRATION — data-quality reset (2026-06-10)

Two columns + one optional reset statement. The code degrades gracefully
before the ALTERs run (review queues just stay empty, research queue keeps
old behavior).

```sql
-- 1. Domain review queue (Keep/Clear UI in Research tab)
ALTER TABLE businesses
ADD COLUMN website_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER website_url;

-- 2. Triage gate — sourced leads wait for human confirmation before research
ALTER TABLE businesses
ADD COLUMN triaged TINYINT(1) NOT NULL DEFAULT 0 AFTER pipeline_status;

-- 3. OPTIONAL data reset — archives all unpitched leads (reversible).
--    Keeps pitched/converted history + blocklist. Run only when ready to
--    re-source through the new evidence-gated sourcing prompt.
UPDATE businesses
SET pipeline_status='excluded', exclusion_reason='pre_reset', updated_at=NOW()
WHERE pipeline_status IN ('identified','researched','needs_contact','preview_ready','enhancement_ready');
```

To undo the reset for a specific lead: set its `pipeline_status` back and
clear `exclusion_reason`.

**⚠️ Migration trap:** adding the `triaged` column (default 0) makes every
EXISTING `identified` lead untriaged — they leave the research queue and
flood the triage list. That's intentional if the reset (statement 3) runs
too. If keeping the old leads instead, backfill right after the ALTER:

```sql
-- Escape hatch: trust all pre-existing identified leads
UPDATE businesses SET triaged = 1 WHERE pipeline_status = 'identified';
```

## GPT auto-import (2026-06-10)

The copy/paste return trip choked on big JSON replies. Three return paths
now exist, best first:

1. **Action POST (zero-touch)** — `gpt-import.php`: key-authed endpoint
   (X-Api-Key / Bearer / ?key= vs `app_settings.gpt_import_key`, hash_equals).
   Detects payload by top-level key (research_results / contacts /
   enrichment_results / candidates) and calls the matching importer.
   Sourcing requires top-level `run_id` — now embedded in the sourcing
   prompt (`ho_generate_sourcing_prompt(..., int $runId = 0)`).
   Setup UI lives in Research tab → "⚙ Auto-import setup": SQL, key
   generator (`save_setting` action), GPT instructions + OpenAPI schema to
   paste into a Custom GPT. Saved `gpt_actions_url` redirects every
   "Ask ChatGPT" deep link to that GPT.
2. **File upload** — "Import a results.json file" button reads the file
   client-side (FileReader → `hoIngest()`, shared with paste) — no giant
   clipboard. Prompts now tell GPT to also save results.json when long.
3. **Paste** — unchanged fallback (`hoPaste` → `hoIngest`).

Migration: `CREATE TABLE IF NOT EXISTS app_settings (setting_key VARCHAR(60)
PRIMARY KEY, setting_value TEXT);`
Helpers: `ho_get_setting()` / `ho_set_setting()` (graceful pre-migration).

## ⚠️ GPT workflow reset (2026-06-11) — SUPERSEDES the multi-step flow above

The Research tab used to run THREE prompts back-to-back (Research → Contact →
Enrich) through one auto-advancing box, plus an optional Custom GPT webhook.
That drift (different delivery instructions per prompt, hidden sequencing,
silent failures, webhook-vs-paste ambiguity) made imports unreliable. Reset to
**one prompt, one paste, manual only:**

- **One merged research prompt.** `ho_generate_research_prompt()` now also
  captures contact info (`email`, `phone`, `website_url`, `website_confidence`)
  and already covered competitor + quote (ex-enrichment) fields — so a single
  pass fully qualifies a lead. `ho_import_research_json()` writes found contact
  into `businesses` (fill-empty only; rejects low-confidence + lead-platform
  URLs via `ho_is_lead_platform_url()`) before routing, so the needs_contact
  gate works without a separate step.
- **Unified research queue.** app.php builds `$researchBatch` =
  `$unresearched` + folded-in `needsContactBatch` (cap 19). The Research tab
  renders exactly one `$hoPrompts` entry. Contact/Enrich steps removed from UI.
- **Standardized delivery footer.** `ho_prompt_delivery_footer()` is the single
  source of truth for both sourcing + research prompts: "reply is raw JSON
  only, no summary sentence, no fences." Killed the old "save results.json +
  one-line summary" instruction that broke auto-paste.
- **Manual paste/file only.** Custom GPT "Auto-import setup" panel removed;
  `cp_gpt_row()` and the research deep link always use standard
  `chatgpt.com/?q=`. `gpt-import.php`, `llm-research.php`, and the
  contact/enrichment importer functions remain in place (dead-but-harmless,
  still callable) — no DB changes needed.

## Data-quality gates (2026-06-10)

Bad leads were entering at sourcing and wasting research cycles. Three gates
now exist, in pipeline order:

1. **Sourcing prompt** (`ho_generate_sourcing_prompt`) demands evidence:
   verifiable Google Maps/FB/website presence, at least one contact path,
   `found_via` + `confidence` per candidate, "return fewer rather than guess".
2. **Import gate** (`ho_import_sourcing_json`) rejects: low confidence,
   zero contact paths, lead-platform URLs as website_url.
3. **Triage queue** (Research tab, `ho_get_triage_batch`) — promoted leads sit
   at `identified`/`triaged=0` until a human taps Real ✓ (triaged=1) or
   Reject ✗ (`excluded`/`failed_triage`). `ho_triage_clause()` keeps
   untriaged leads out of `ho_get_unresearched_businesses` and the category
   counts. Plus: **domain review queue** (`ho_get_website_review_batch`) for
   `website_url` with `website_verified=0`; contact prompt now returns
   `website_confidence` (high=verified, medium=review queue, low=discarded).

## go.php Trust/Emotion Layer (2026-06-10)

Five blocks added to go.php (both tracks unless noted), all data-gated —
silently absent when data is missing:

1. **"In their own words" pull-quote** — `review_quote_1/2` rendered as
   blockquotes between WHY and the track fork. Gate: quote text non-empty.
2. **Competitor scoreboard** (`.fd-score`) — You vs `{competitor_name}` stars +
   review counts, inside WHY after the rating badge. Gate includes
   `googleRating >= compRating` — NEVER renders a board the lead is losing.
3. **Stakes block** (`.fd-stakes`) — `ho_stakes_estimate($catSlug)` →
   conservative "$X/year walking past you" before each track's money moment.
   `ho_category_avg_ticket()` map in ho-model.php; unknown slug → block absent.
   Annual floored to nearest $100; 1 job/mo claimed for tickets ≥ $400.
4. **Adam photo** — upload square `assets/img/adam.jpg` and it auto-replaces
   the "AF" avatar in WHO BUILT THIS (is_file gate, no code change needed).
5. **P.S. line** (`.fd-trust-ps`) — handwritten-style closer on the trust card;
   fact priority: quote author → years → review count → competitor. Also:
   Yelp cross-platform rating line (only when both ≥ 4.0) and professional-logo
   strength prepended to "Working in your favour".

## Three Game-Changer Features (2026-06-11)

### Feature 1: Lead Heat Tracking

Every go.php page visit is logged. Hot leads surface with 🔥 badges in the Send tab.

**SQL migration (run in phpMyAdmin):**
```sql
CREATE TABLE IF NOT EXISTS preview_visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  preview_id INT NOT NULL,
  business_id INT NOT NULL,
  visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_hash CHAR(64) NOT NULL DEFAULT '',
  INDEX idx_pv_business (business_id),
  INDEX idx_pv_visited (visited_at)
) ENGINE=InnoDB;
```

**Functions (ho-model.php):**
- `ho_log_preview_visit(PDO, previewId, bizId)` — inserts into preview_visits (silently skips if table missing)
- `ho_visit_stats_for_businesses(PDO, bizIds[])` — returns `[bizId => ['total', 'recent' (48h), 'last_at', 'is_hot']]`

**go.php:** logs every visit after `ho_get_preview_by_slug()` succeeds.

**app.php Send tab:** 🔥 HOT badge on hot cards, "X hot leads" strip at top, heat counts on badges.
`is_hot` = visited within 48h OR 2+ total visits.

---

### Feature 2: Follow-up Engine

Multi-touch sequence: touch 1 (existing), touch 2 (+3d), touch 3 (+10d), touch 4 breakup (+21d).
Pre-written copy shown inline with copy + mailto buttons in the follow-up queue.

**SQL migration:**
```sql
ALTER TABLE outreach_log ADD COLUMN touch_number TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER outcome;
```

**Functions (ho-model.php):**
- `ho_followup_message(biz, previewUrl, touch, visitStats)` — returns `['subject', 'body']` for touches 2–4.
  Personalizes based on visit data (🔥 HOT = different opener). Touch 4 is the breakup email.
- `ho_get_followup_due_full(PDO)` — like `ho_get_followup_due()` but includes `touch_number`,
  `email_address`, `phone_number`, `owner_first_name`, `sent_via`, `category_slug`.
  Gracefully falls back if `touch_number` column not yet migrated.
- `ho_record_followup_sent(PDO, logId, bizId, sentVia, sentTo, touch)` — closes current log row
  as `no_response`, creates next touch row with correct `follow_up_at` (touch→2: +3d, →3: +7d, →4: +11d).

**app.php:** New `record_followup_sent` POST action. Follow-up cards show:
- Pre-written touch N+1 message (expandable) with Subject + body + email link + copy button
- "✓ Sent touch N+1" button (calls `record_followup_sent`)
- Interested / Not Interested buttons (unchanged)
- Heat badge + visit count inline

---

### Feature 3: Zero-Touch Research (Claude API)

Replaces ChatGPT copy/paste for research. A single button in the Research tab researches
all queued leads one-by-one via the Anthropic API with web search.

**Config file (outside public_html — NEVER commit):**
```
/home1/spofnkte/llm-config.php
  define('LLM_API_KEY', 'sk-ant-...');
  define('LLM_MODEL', 'claude-sonnet-4-6');  // optional, defaults to claude-sonnet-4-6
```

**New file: `llm-research.php`** — POST endpoint
- Auth: same `gpt_import_key` as `gpt-import.php` (X-Api-Key header)
- Body: `{ "business_id": N }`
- Builds research prompt via `ho_generate_research_prompt([$biz])`, strips ChatGPT DELIVERY section,
  calls Anthropic Messages API with `web_search_20250305` tool.
- Extracts JSON from response, pipes through `ho_import_research_json()`.
- Returns `{ "ok": true, "updated": N, "message": "..." }`.

**app.php Research tab:** "Research with Claude" section appears when:
- `/home1/spofnkte/llm-config.php` exists AND `gpt_import_key` is configured.
- Shows button + stop button + progress bar + status line.
- JS `startLlmResearch()` / `llmNext()` self-chains: fetches next biz_id, POSTs to
  llm-research.php, waits 800ms, repeats. On error, logs and continues.

---

## DONE (2026-06-09 → continued session)

### ✅ 1. Per-gap computed pricing — SHIPPED
- `ho_gap_label()` — static label fallback for all 16 gaps
- `ho_gap_prices(PDO)` — reads `gap_prices` table, hardcoded fallback for all 16, request-cached
- `ho_build_package_items(PDO, gaps)` — priced bundle builder
- `ho_route_to_enhancement()` stores `package_items` JSON on the preview at routing time
- go.php enhancement page: each fix card shows its price + a flat one-time bundle total + total-led CTA
- app.php send queue: bundle total shown per card

### ✅ 2. All 16 gap types render & price — SHIPPED
- go.php `$fixDefs` now has personalized copy for all 16 gaps (was 6)
- app.php `$gapLabels` covers all 16
- The 10 newer gaps fire automatically once their research_records columns exist + have data

### ✅ 3. One-offer-per-lead — SHIPPED
- Enhancement: single line-item bundle + total, direct-contact CTA (no Stripe)
- Site-build: single $199 flat offer, single category-matched design (design PICKER removed —
  lead sees one look, pre-chosen), domain field kept (functional, not a package choice),
  single Stripe checkout carrying `template_key`

---

## Pending Tasks (priority order)

### 1. Re-route stuck decent-site leads
Businesses at `preview_ready` or `excluded/has_good_website` with `has_website=1, website_quality='decent'`
need a batch re-route button in app.php Research tab that calls `ho_route_to_enhancement()` on each.

### 2. Admin price editor UI
`gap_prices` is DB-backed and editable via SQL, but there's no in-app editor yet.
Add a small table editor in app.php so Adam can change gap prices without phpMyAdmin.

### 3. Verify the 10 newer gaps fire on real data
Once the research_records ALTER is run and a few leads are re-researched with the 69-field
prompt, confirm gaps like `no_before_after`, `dead_facebook`, `freemail` actually populate
and show on go.php with correct prices.

---

## CSS Notes (front-door.css)

- `.fd-pc-row` grid: `grid-template-columns: 130px 58px 1fr; gap: 8px` — fixed widths for price table alignment
- Enhancement track left border: `cp-send-card-enhance` class (amber left border)
- iOS phone number auto-detection: never put phone numbers in inline text — iOS overrides all CSS styling

---

## Known Gotchas

- `'good'` website_quality is dead code — import validator only accepts `['none','poor','basic','decent']`
- `ho_is_freemail()` covers 25+ explicit domains + pattern regex for Yahoo/Hotmail/Live variants
- The enrichment prompt is a supplement to the main research prompt — for leads that were researched
  before the new 69-field prompt was added, use enrichment to backfill missing fields
- `ho_count_no_contact_ready()` / amber banner in Send tab: shows when preview_ready leads have
  no contactable info — a re-queue button sends them back to needs_contact
- Platform URLs (Angi/Thumbtack/etc.) are filtered at every contact path decision via
  `ho_is_lead_platform_url()` — do not use these as website_url or outreach paths
