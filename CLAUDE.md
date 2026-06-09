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

### Email
- `ho_pitch_mailto(array $biz, string $previewUrl): string` — Site-build pitch mailto link.
- `ho_pitch_mailto_enhancement(array $biz, string $previewUrl): string` — Enhancement pitch mailto.

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

## Pending Tasks (priority order)

### 1. Per-gap computed pricing for enhancement track
User confirmed: "computed per gap makes most sense."
Each of the 16 gap types needs a default price Adam can set in the admin.
The bundle total is computed from whichever gaps apply to that specific lead.
Implementation approach:
- New `gap_prices` table: `gap_key VARCHAR(40) PK, default_price DECIMAL(8,2), label VARCHAR(100)`
- Seed with defaults for all 16 gap types
- Admin UI in app.php to override prices per gap
- `ho_compute_enhancement_bundle(array $gaps, PDO): array` — returns itemised price list + total

### 2. Personalised one-offer-per-lead go.php
User quote: "every person that we can outreach needs to already have defined a page at Go
that only lists one package for them to be sold. Not options. Not choices."

For enhancement leads: package = their specific gaps + computed prices, shown as a line-item bundle.
For site-build leads: package = personalised deliverables based on research data (services included,
owner name, category-specific features), single price.

Implementation approach:
- Add `package_items JSON` column to `previews` table
- Populate at routing time (site-build: `ho_auto_generate_preview`, enhancement: `ho_route_to_enhancement`)
- go.php renders the pre-built bundle — no choices for the lead to make
- Single Stripe checkout with the pre-computed price (site-build only; enhancement uses direct contact)

### 3. Gap label display in send queue + go.php
The 16 gap types need human-readable labels and amber chip badges:
- In app.php send queue: amber chips on each enhancement card
- In go.php opportunity cards: icon + title + one-liner + indicative price

Gap label map needed:
```
tech_issues      → "Site has technical issues (mobile/SSL)"
contact_form     → "No contact or quote form"
online_booking   → "No online booking system"
site_outdated    → "Site looks outdated"
paid_leads       → "Paying Angi / Thumbtack per lead"
google_business  → "Not on Google Maps"
gbp_incomplete   → "Google Business profile incomplete"
gbp_photos       → "Too few photos on Google"
stale_reviews    → "No recent reviews"
no_before_after  → "No before/after photos"
no_gallery       → "No photo gallery"
no_testimonials  → "No testimonials section"
dead_facebook    → "Facebook page is inactive"
freemail         → "Using personal email (Gmail/Yahoo)"
no_trust_signals → "No license/insurance info visible"
yelp_unclaimed   → "Yelp listing unclaimed"
```

### 4. Re-route stuck decent-site leads
Businesses at `preview_ready` or `excluded/has_good_website` with `has_website=1, website_quality='decent'`
need a batch re-route button in app.php Research tab that calls `ho_route_to_enhancement()` on each.

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
