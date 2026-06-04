# Hoosier Online — Unified Project Detail Sheet
**Generated:** 2026-06-04  
**Current patch:** v064  
**Source:** All available README files, SQL schema files, version constants in source code, and review documents.

---

## How to read this sheet

Each entry is tagged with its evidence quality:

- **[README]** — explicit README or review .txt file present
- **[CODE]** — inferred from a version constant inside a source file
- **[SCHEMA]** — inferred from a SQL file
- **[REF]** — referenced in another file (e.g., sales-system.php mentions the patch by name)
- **[UNDOCUMENTED]** — patch number exists in the sequence but no artifact explains it; do not guess

---

## Patch History

---

### v001–v004 — [UNDOCUMENTED]
No README, no version constant, no code comment, no SQL file refers to these patches.  
Assumed to be initial project scaffolding based on README_INSTALL.txt mentioning "v005" as the first named design package, but the content of v001–v004 is unknown.

---

### v005 — Admin Design Package [README]
**File:** `README_INSTALL.txt`  
**Type:** Deployment / Admin shell

- First named admin design package.
- Files delivered: `admin.php`, `index.php`, `assets/css/site.css`, `uploads/` directory.
- No deploy manifest required at this stage.
- ZIP extracted into site root, overwrites matching files, deletes uploaded ZIP after extraction.
- Displays links to unpacked files after install.
- Visible admin instructions removed from UI; machine instructions embedded in admin page JSON metadata.

---

### v006–v027 — [UNDOCUMENTED]
No README files, no explicit version constants in code, no SQL files cover this range.  
Version constants for `product.php` (HO-PRODUCT-028), `buildsystem.php` (HO-BUILD-SYSTEM-028), and `salesphilosophy.php` (HO-SALES-PHILOSOPHY-030) suggest that by ~v028–v030 the core business doctrine was being locked in. What happened in v006–v027 is unknown — likely iterative development of the sales philosophy, product definitions, and early admin UI, but this cannot be confirmed.

---

### v028 — Product & Build System Canon Lock [CODE]
**Files:** `product.php` (HO-PRODUCT-028), `buildsystem.php` (HO-BUILD-SYSTEM-028)  
**Type:** Business doctrine / reference

State frozen at this version (inferred from constants, no changelog):

**Product definition** (`product.php`):
- Canonical product, pricing, scope, renewal, delivery, ownership definition.
- Two products: Standard Front Door ($499, 1-year included) and Managed Front Door ($999, 3 months managed included).
- Explicit list of what the product is NOT (no paid ads, no lead-gen guarantees, no agency scope).
- Renewals: $250/yr or $25/mo (Standard); $250/quarter or $750/yr (Managed).

**Build system** (`buildsystem.php`):
- Defines fulfillment flow after customer chooses and pays.
- Modular/templated assembly from customer inputs and preview choices.
- Core flow: receive order → confirm package → collect inputs → choose template/modules → assemble → connect contact/request/payment → QC → send for approval.

---

### v029 — [UNDOCUMENTED]
No artifact found. Patch number inferred from the sequence between v028 and v030.

---

### v030 — Sales Philosophy Lock [CODE]
**File:** `salesphilosophy.php` (HO-SALES-PHILOSOPHY-030)  
**Type:** Business doctrine / reference

State frozen at this version (inferred from constant, no changelog):

- Core thesis: "Sell by reducing uncertainty before the first conversation."
- Operating principle: research first, personalize the link, show the problem, show the fix, let the prospect choose.
- Defines "successful sale": customer saw preview, believed it was specific to them, understood the problem, selected options, paid or requested invoice, provided enough info to start build.
- Sales machine goal: research → outreach → preview page → choices → telemetry → build handoff.
- Mirrors the same product pricing as v028 (Standard $499, Managed $999).

---

### v031 — [UNDOCUMENTED]
No artifact found.

---

### v032 — Sales Portal Canon + Database Schema [CODE] [SCHEMA]
**Files:** `salesportal.php` (HO-SALES-PORTAL-032), `db/schema.sql` (v032), `db/install_salesportal.sql`, all seed files.  
**Type:** Database schema + data model doctrine

This is the first major system checkpoint with full schema and canon:

**13 core database tables created:**
1. `businesses` — prospect records (slug, name, type, location, status, marketing_clearance_status, recommended_package)
2. `me_categories` — 7 categories: find_me, trust_me, contact_me, show_me, book_me, pay_me, fix_me
3. `me_requirements` — 23 weighted requirements across the 7 categories
4. `evidence_sources` — links to website, Google, Facebook, etc.
5. `business_claims` — confidence-scored field-level facts
6. `business_requirement_scores` — per-requirement scoring
7. `business_me_scores` — per-category scoring
8. `prospect_previews` — preview instances (not yet built)
9. `preview_events` — customer interaction tracking
10. `outreach_events` — contact attempt log
11. `preview_choices` — customer selections
12. `build_handoffs` — project delivery spec
13. `salesportal_reference` — reference data (claim fields, thresholds, clearance statuses)

**Canon lock includes:**
- 67 allowed `claim_field` keys (business identity, location, public presence, contact, service offer, proof/trust, booking, payment, fix/cleanup, recommendation)
- Confidence levels: confirmed, likely, inferred, weak_inference, missing, conflicting, rejected
- Marketing clearance statuses: cleared, warm_clear, needs_review, hold, skip, blocked
- Minimum field gates for cleared (score ≥75) and warm_clear (score ≥60)
- Outreach confidence thresholds by field
- Source confidence defaults (website 90, Google 85, Facebook 80, Instagram/TikTok 70, directory 55)

**Sales portal system purpose (rollup chain):**
Claims → Requirement Scores → Me Category Scores → Marketing Clearance Score → Marketing Clearance Status → Preview / Outreach / Build Handoff

---

### v033–v040 — [UNDOCUMENTED]
No artifact found for any of these patches. Admin Core version constant lands at v041, so something happened between schema lock (v032) and the admin design pass (v041). Likely iterative admin UI work, but content is unknown.

---

### v041 — Admin Core Design Pass [CODE]
**File:** `admin-core.php` (HO-ADMIN-CORE-041)  
**Type:** Admin UI / shell

State frozen at this version (inferred from constant, no changelog):

- Defines `ho_admin_config()` with 6-item nav: Dashboard, Research, Prospects, Upload, Systems, Backup.
- `ho_admin_render_start()` / `ho_admin_render_end()` shared page shell.
- CSS versioned as `admin.css?v=041-admin-design-fix`.
- Viewport set (later updated in v063).
- Machine-readable JSON metadata embedded in every admin page.
- Shared helpers: `ho_h()` (HTML escaping), path safety (`ho_admin_safe_target_path()`), human file size, doc list renderer.
- `future_auth.enabled_now = false`, `future_auth.planned = true` noted in config.

---

### v042–v043 — [UNDOCUMENTED]
No artifact found.

---

### v044 — Preview Schema & Seeds [SCHEMA] [REF]
**Files:** `db/install_preview_v044.sql`, `db/preview_schema_additions.sql`, `db/seed_preview_options.sql`, `db/seed_preview_reference.sql`  
**Referenced in:** `sales-system.php` current_system_state  
**Type:** Database schema expansion

Adds 5 new tables (run after v032 base schema):

1. `preview_readiness` — readiness evaluation per business (ready / soft_ready / needs_more_research / manual_review / blocked)
2. `preview_option_groups` — design option catalog groupings (general, handyman, lawn/cleaning)
3. `preview_design_options` — 8 seeded design choices (Simple Service Card, Local Pro, Quote Request, Before/After Proof, Mobile Call Now, Neighborhood Handyman, Repair Estimate, Recurring Service)
4. `preview_address_options` — domain/subdomain suggestions per prospect
5. `preview_customer_choices` — captures design + address + package selection
6. `preview_build_handoff_links` — links choices to build handoff records

**Doctrine inserts:**
- `no_preview_php_yet`: "v044 only prepares schema and seeds. Customer-facing preview.php remains intentionally unbuilt."
- `no_scraping_yet`: bulk scraping remains blocked until preview/payment/build handoff is proven manually.

---

### v045 — Preview Readiness Evaluator [REF]
**Referenced in:** `sales-system.php` current_system_state: "v045 added internal Preview Readiness Evaluator"  
**No README file present.**  
**Type:** Backend logic

- Added internal logic to evaluate whether a business is ready for preview generation.
- Function: `ho_salesportal_evaluate_preview_readiness()` (present in `prospect-model.php`).
- Writes to `preview_readiness` table added in v044.
- Exact scoring algorithm and threshold rules not separately documented.

---

### v046 — Preview Option Assignment [REF]
**Referenced in:** `sales-system.php` current_system_state: "v046 added internal Preview Option Assignment"  
**No README file present.**  
**Type:** Backend logic

- Added internal logic to assign preview design options to a business based on business type and claim data.
- Function: `ho_salesportal_assign_preview_options()` (present in `prospect-model.php`).
- Writes to `preview_design_options` / `preview_address_options` tables.
- Assignment rules not separately documented beyond what is in prospect-model.php code.

---

### v047 — [UNDOCUMENTED]
No artifact found. Exists in sequence between v046 (option assignment) and v048 (UI review).

---

### v048 — UI / Operator Process Review [README]
**File:** `v048-ui-process-review.txt`  
**Type:** Review + safe fixes

**Scope reviewed:** Admin Hub, Sales System, Research, Prospects, Business View, Upload/Sitemap.

**Changes made:**
- Added manual-test mode note to Admin Hub.
- Tightened Research page wording around one-prospect-at-a-time workflow.
- Clarified validate-before-import behavior.
- Clarified Prospects is an internal manual queue (not customer-facing).
- Clarified Preview Readiness is internal only.
- Clarified Business View is not customer-facing.
- Added reusable admin CSS utilities: process notes, empty states, checklists, mobile action rows.
- Added v048 review/deferred-issues section to Sales System.

**Deferred to v050:**
- Whether Business View should auto-write readiness/option/address records or require explicit action.
- Whether Sales System should preserve cumulative canon history.
- Whether old split pages (Sales Philosophy, Sales Portal Canon, etc.) should remain.
- Whether all inline styling must be eliminated before preview.php.
- Whether preview.php is allowed only after 3–5 manual prospects pass.

---

### v049 — Backend / Code Functionality Review [README]
**File:** `v049-backend-code-review.txt`  
**Type:** Review + safe fixes  
**Files touched:** `prospect-model.php`, `sales-portal-dashboard.php`, `sales-business.php`, `sales-system.php`

**Changes made:**
- Added table-existence helpers to prevent fatal errors when v044 preview SQL is missing.
- Added preview schema status helper.
- Added `unavailable` fallback result for `preview_readiness` when table is missing.
- Added `unavailable` fallback for preview option assignment when option/address tables are missing.
- Replaced unsafe JSON encode use with safe helper for `missing_inputs_json`.
- Changed dashboard assignment summary to avoid writing address suggestions during queue rendering.
- Added clearer missing-schema notes in Prospects and Business View.
- Added v049 backend review/deferred risk section to Sales System.

**Deferred to v050:**
- Decide if Business View should continue auto-writing readiness/address suggestions.
- Decide if Sales System should preserve cumulative canon history separately.
- Validate readiness thresholds against 3–5 real prospects.
- Validate keyword-based business type classification.
- Confirm v044 SQL and seeds are installed live before any preview.php work.

---

### v050 — Business Review Lock [CODE] [REF]
**File:** `sales-system.php` (HO-BUSINESS-REVIEW-LOCK-050)  
**Type:** Doctrine / operating lock

Formal system stop-point. Locked scope in `sales-system.php`:

**Explicitly blocked at v050:**
- No preview.php
- No customer-facing preview links
- No scraping
- No outreach automation
- No payment integration

**Accepted manual workflow (locked canon):**
1. Start at Admin Hub → Open Research → Select one local operator manually
2. Run locked GPT research prompt
3. Paste one valid JSON response → Validate → Import
4. Open Prospects → Open Business View
5. Review evidence, claims, readiness, option assignment
6. Manually draft outreach outside the system
7. Repeat for 3–5 prospects only

**Final decisions from v048/v049 reviews:**
- Business View may auto-write preview_readiness and preview_address_options during manual testing (temporary).
- Sales System may remain current-release summary (no full cumulative canon required yet).
- Old split pages (salesphilosophy.php, salesportal.php, etc.) kept as reference/legacy only.
- No new page may invent layout styles inline; preview.php must use shared CSS.
- preview.php blocked until 3–5 manual prospect test passes.
- Readiness scores and keyword-based classification are test assumptions until validated.

---

### v051 — Security / Auth Changes (Rolled Back) [REF]
**Referenced in:** `ROLLBACK_TO_V050_README.txt`  
**No dedicated README for v051 itself.**  
**Type:** Security attempt → rollback

- Introduced admin-auth.php, admin-login.php, admin-logout.php, admin-hardening.php, admin-secrets.php, and private/ directory examples.
- Was rolled back via a recovery package because it broke admin.php and upload.php.
- Rollback restored v049 UI/backend review state and preserved v050 business review lock.
- Security was explicitly not fixed by the rollback; rollback was a recovery package only.

---

### v052 — Final Hardening [README]
**File:** `SECURITY_NOTES.txt`  
**Files:** `admin-auth.php` (HO-ADMIN-AUTH-052), `admin-secrets.php`, `admin-login.php`, `admin-logout.php`, `admin-hardening.php`, `private/` examples  
**Type:** Security hardening

**Deploy contract locked (10 rules for all future packages):**
1. Every package must include `deploy-manifest.json` at ZIP root.
2. Site files at ZIP root paths matching install locations.
3. No root junk files.
4. No credentials, secrets, logs, backups, SQL dumps, or generated ZIPs in package.
5. Prefer narrow packages (only files required for the task).
6. Include purpose/version in `deploy-manifest.json`.
7. Run PHP syntax checks before packaging.
8. Keep admin/system pages behind admin-core.php / admin-auth.php.
9. No customer-facing UI, scraping, outreach automation, or payment unless explicitly approved.
10. upload.php requires `deploy-manifest.json`.

**Security infrastructure added:**
- `admin-auth.php` (HO-ADMIN-AUTH-052): session hardening (HttpOnly, SameSite=Lax, Secure on HTTPS), security headers (X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy).
- `ho_admin_is_logged_in()`, `ho_admin_require_login()`, `ho_admin_csrf_token()`, `ho_admin_verify_csrf()`, `ho_admin_csrf_input()`, `ho_admin_logout()` implemented.
- Private directory pattern: secrets stored outside webroot at `../hoosier-online-private/`.
- Password stored as bcrypt hash (12-round cost); session key as 64-char hex.
- `admin-secrets.php` marked as public fallback only.

**Post-install hardening steps (manual, not automated):**
- Create private directory at `/home1/spofnkte/hoosier-online-private`.
- Move secrets and DB credentials there.
- Confirm `/sales-db-check.php` works while logged in.
- Remove or neuter public `admin-secrets.php`.

**Manual test blocked until:** login works, upload is protected, backup is protected, DB check is protected, private secrets configured.

> **Current status:** As of v064, the auth functions exist in `admin-auth.php` but `admin-login.php` still has unresolved function calls that will cause errors. No admin page calls `ho_admin_require_login()`. Private directory setup is not confirmed complete.

---

### v053 — Mobile / CSS Design Fix [README]
**File:** `V053_DESIGN_FIX_README.txt`  
**Files:** `assets/css/admin.css`  
**Type:** CSS only — no PHP, no DB changes

- Prevented horizontal page blowout on mobile.
- Tightened Business View and admin cards on narrow screens.
- Made nav, stats, data rows, workflow strips, and tables mobile-safe.
- Reduced oversized spacing/card expansion on narrow screens.
- Install: upload via uploader or extract `assets/css/admin.css` manually. Hard refresh Safari after install.

---

### v054 — Import Auto-Processing + Simplified Prospects [README]
**File:** `V054_IMPORT_AND_PROSPECTS_README.txt`  
**Files:** `sales-research.php`, `prospect-model.php`  
**Type:** Workflow logic — no schema changes

- Simplified Prospects page to an admin/operator queue (Business View kept for inspection/troubleshooting only).
- After a successful Research import, system now automatically attempts:
  1. `ho_salesportal_evaluate_preview_readiness(business_id, true)`
  2. `ho_salesportal_assign_preview_options(business_id, true)`
- Rationale: if JSON validated and imported, payload is acceptable enough to complete backend processing without requiring manual Business View open.

---

### v055 — Business Refinement Prompt [README]
**File:** `V055_BUSINESS_REFINEMENT_PROMPT_README.txt`  
**Files:** `prospect-model.php`, `sales-business.php`, `sales-research.php`, `assets/css/admin.css`  
**Type:** Operator workflow feature — no schema changes

- Adds a per-business refinement prompt to Business View.
- Uses existing business, evidence, and claims to create a paste-ready GPT prompt.
- Prompt asks GPT to: confirm known truths, find missing public details, explicitly mark things as missing when they don't appear to exist.
- Returned JSON still goes through the existing Sales Research validate/import flow.
- Workflow: Import rough business → open Business View → copy refinement prompt → run GPT → paste JSON into Research → validate and import → v054 auto-processes readiness/options.

---

### v055a — Refinement Prompt Visibility Fix [README]
**File:** `V055A_REFINEMENT_PROMPT_VISIBILITY_FIX.txt`  
**Files:** `sales-business.php`, `deploy-manifest.json`  
**Type:** Bug fix — no schema, no backend logic changes

- v055 may not have shown the prompt if the insertion point didn't match the installed Business View layout.
- v055a moves the prompt near the top of Business View, immediately before Workflow Position.

---

### v055b — Self-Contained Refinement Prompt Fix [README]
**File:** `V055B_SELF_CONTAINED_REFINEMENT_FIX.txt`  
**Files:** `sales-business.php`, `assets/css/admin.css`  
**Type:** Bug fix — no schema, no prospect-model.php changes

- v055/v055a depended on `prospect-model.php` helper availability at runtime.
- Live Business View showed the helper was unavailable.
- v055b puts the prompt generator directly inside `sales-business.php` so it renders even without the prospect-model.php helper.

---

### v056 — Refinement Prompt Efficiency [README]
**File:** `V056_REFINEMENT_PROMPT_EFFICIENCY_README.txt`  
**Files:** `sales-business.php`  
**Type:** Prompt tuning — no schema, no DB changes

- v055b prompt asked GPT to search too many detailed fields, causing GPT to lock up or waste effort.
- Prompt now works in tiers:
  1. Confirm identity and contact first.
  2. Only inspect public surfaces that actually exist.
  3. Stop when system can decide: identifiable? contactable? possible Front Door prospect?
- If no website exists: don't deeply inspect website-only fields.
- If Facebook exists: inspect for broad activity/proof/contact signs only.
- If Google exists: inspect for broad review/photo/contact signs only.
- Booking/payment fields only checked if a relevant surface exists.
- Preferred claim count limited so output stays importable.

---

### v057 — Research JSON Normalizer [README]
**File:** `V057_RESEARCH_JSON_NORMALIZER_README.txt`  
**Files:** `sales-research.php`  
**Type:** Input handling fix — no schema, no DB changes

- iPhone/GPT paste produces smart quotes (`"` / `"`) instead of straight JSON quotes.
- Strict `json_decode()` rejects smart quotes with `Syntax error`.
- v057 normalizes smart double quotes and smart single quotes to valid JSON characters before decode.
- Also detects when a user accidentally pastes the GPT prompt instead of the JSON response and shows an appropriate error.

---

### v058 — Batch Research Import [README]
**File:** `V058_BATCH_RESEARCH_IMPORT_README.txt`  
**Files:** `sales-research.php`, `prospect-model.php`  
**Type:** Importer feature — no schema changes

Sales Research now accepts three input formats:
1. One normal importable business payload (existing behavior).
2. A batch wrapper with `businesses` / `prospects` / `items` array.
3. A candidate sourcing batch with `source_batch` + `candidates` structure.

- Batch limit: 25 records.
- Candidate batches converted into `hold`-status business records with minimal identity/contact claims.
- Full refinement expected from Business View after initial import.

---

### v059 — Candidate Triage Layer [README]
**File:** `V059_CANDIDATE_TRIAGE_LAYER_README.txt`  
**Files:** `sales-business.php`, `sales-research.php`, `sales-portal-dashboard.php`, `assets/css/admin.css`  
**Type:** Workflow feature — no schema changes

- Adds a lightweight triage stage between candidate import and full research.
- Triage prompt is cheaper than full refinement; answers only: real/identifiable? local/right category? usable contact path? enough public surface? → full_research / manual_check / skip?

**New workflow:**
1. Import candidate batch.
2. Open a candidate in Business View.
3. Copy Candidate Triage Prompt.
4. Paste triage JSON into Sales Research.
5. Import triage result.
6. Only use full Business Refinement Prompt if candidate is `research_ready`.

---

### v060 — Bulk Candidate Triage [README]
**File:** `V060_BULK_CANDIDATE_TRIAGE_README.txt`  
**Files:** `sales-portal-dashboard.php`, `sales-research.php`, `prospect-model.php`  
**Type:** Workflow feature — no schema changes

- Adds a Bulk Candidate Triage prompt to the main Prospects page (no need to open each business one at a time).
- Prompt includes the next 25 available candidate/prospect records.
- GPT classifies each into exactly one of three categories:
  1. `research_with_website`
  2. `proceed_no_website`
  3. `do_not_proceed`

**Import mapping:**
- `research_with_website` → `warm_clear` / `full_research`
- `proceed_no_website` → `hold` / `simple_front_door_path`
- `do_not_proceed` → `skip`

---

### v060a — Bulk Triage Field Fix [README]
**File:** `V060A_TRIAGE_FIELD_FIX_README.txt`  
**Files:** `sales-research.php`  
**Type:** Bug fix — no schema changes

- v060 attempted to store triage category in a new claim field `triage_status`.
- The existing validator correctly rejected it because `triage_status` is not an allowed `field_key`.
- Fix: removes `triage_status` claim; stores triage category inside existing allowed fields: `marketing_clearance_status`, `primary_sales_angle`, `notes`.
- No schema change. No validator change. No new `field_key` values.

---

### v061 — iPhone Operator Flow [README]
**File:** `V061_IPHONE_OPERATOR_FLOW_README.txt`  
**Files:** `sales-portal-dashboard.php`, `sales-research.php`, `assets/css/admin.css`  
**Type:** UX workflow change — no schema changes

- Removes the unnecessary step of navigating to the Research page to paste JSON.
- Prospects page now has a **Paste Results Here** panel.
- Candidate batches, bulk triage results, and full business research JSON can all be pasted directly on Prospects.
- Validate/import happens on the Prospects page.
- Bulk triage prompt now points to the on-page paste area.
- Research page (`sales-research.php`) remains as fallback/reference only.

---

### v062 — Admin iPhone UX Pass [README]
**File:** `V062_ADMIN_IPHONE_UX_README.txt`  
**Files:** `assets/css/admin.css`, `admin.php`, `sales-portal-dashboard.php`, `sales-business.php`, `sales-research.php`, `upload.php`  
**Type:** UX / CSS — no schema, no backend logic changes

Admin-wide iPhone 17 Pro / Safari operator experience improvements:

**Design rules locked:**
- Admin optimized for one operator on iPhone Safari.
- Prospects = primary work surface.
- Research = fallback/reference.
- Business View = inspection/prompt generation.
- Upload = package install only.
- Reference pages demoted with clearer context.

**Changes:**
- Shared CSS: iPhone/Safari touch targets, Safari zoom prevention, sticky action docks, mobile-safe cards, quick flow strips, safe-area bottom padding.
- Admin Hub: added Today's Fast Path.
- Prospects: added Operator Rhythm and bottom action dock.
- Business View: added Business Operator Steps and bottom action dock.
- Research page: marked as fallback, points to Prospects paste area.
- Upload/reference pages: clearer operator context added.

---

### v063 — Safari Input Zoom Fix [README]
**File:** `V063_SAFARI_INPUT_ZOOM_FIX_README.txt`  
**Files:** `admin-core.php`, `assets/css/admin.css`  
**Type:** CSS / viewport fix — no backend or DB changes

- iOS Safari zooms on input focus when a form control computes under 16px.
- Some admin textarea/input rules still resolved small on mobile after v062.

**Fixes:**
- `admin-core.php` viewport updated to: `width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover`
- All admin form controls forced to minimum 16px on iOS/Safari.
- Textareas and prompt boxes explicitly set to 16px.
- Touch targets maintained at minimum 48px height.

---

### v064 — Lead Generation Prompt [README]
**File:** `V064_LEAD_GENERATION_PROMPT_README.txt`  
**Files:** `sales-research.php`  
**Type:** Prompt update — no schema, no DB, no business logic changes

- Updates the generic prompt on `sales-research.php` to generate new candidate leads instead of asking for deep business research.
- Workflow now starts with lead generation/candidate sourcing, then bulk triage, then full research only for worthy prospects.

**Changes:**
- The promptBox now asks GPT for up to 25 lawn-care/exterior-service candidate businesses.
- Output structure: `source_batch` + `candidates`.
- Candidate JSON is directly pasteable into Prospects → Paste Results Here (or the fallback Sales Research importer).
- Page copy updated to describe lead generation.

---

## System State Summary at v064

| Component | Version | Notes |
|---|---|---|
| Admin Core shell | HO-ADMIN-CORE-041 | Stable since v041, viewport updated in v063 |
| Admin Auth | HO-ADMIN-AUTH-052 | Functions implemented but login page not fully wired |
| Database schema (base) | v032 | 13 tables |
| Database schema (preview) | v044 | 5 additional tables; not confirmed installed live |
| Product definition | HO-PRODUCT-028 | Stable |
| Build system | HO-BUILD-SYSTEM-028 | Stable |
| Sales philosophy | HO-SALES-PHILOSOPHY-030 | Stable |
| Sales portal canon | HO-SALES-PORTAL-032 | Stable |
| Prospect model | inline (comment ref: v055) | 1,557 lines, 150+ functions |
| Operating lock | HO-BUSINESS-REVIEW-LOCK-050 | Preview/scraping/payment still blocked |

---

## Features Present in Code (Confirmed at v064)

- Admin Hub dashboard with Today's Fast Path (v062)
- Prospects page with Paste Results Here panel (v061)
- Bulk Candidate Triage prompt (v060)
- Candidate Triage per-business prompt (v059)
- Business Refinement Prompt (v055b/v056)
- Lead Generation prompt for sourcing new candidates (v064)
- JSON import: single payload, batch, or candidate batch up to 25 records (v058)
- Smart quote normalization + prompt-vs-JSON detection (v057)
- Auto preview readiness evaluation on import (v054)
- Auto preview option assignment on import (v054)
- ZIP package installer with manifest validation (upload.php, v052 contract)
- Sitemap/file browser and backup download (sitemap.php)
- Database connection validator (sales-db-check.php)

---

## Features Explicitly Blocked (as of v050, still applies at v064)

- preview.php (customer-facing preview)
- Scraping
- Outreach automation
- Payment integration
- Customer-facing preview links

---

## Known Open Issues at v064

1. **Authentication not enforced** — `admin-login.php` cannot function as written; no admin page calls `ho_admin_require_login()`.
2. **Credentials exposed** — `database.php` contains plaintext DB credentials; private directory setup unconfirmed.
3. **v044 schema not confirmed live** — preview tables may not be installed on the live database.
4. **Readiness/classification algorithms unvalidated** — marked as test assumptions until 3–5 real prospects are tested.
5. **No automated tests** — manual testing only; no PHPUnit, no CI test suite.
6. **Patches 001–004, 006–027, 029, 031, 033–040, 042–043, 047 undocumented** — 23 patch slots with no surviving artifact.
