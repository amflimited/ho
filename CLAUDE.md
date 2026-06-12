# Hoosier Online — Claude Code Handoff

> Read automatically by Claude Code on session start.
> Rewritten 2026-06-12 after "Operation Frankenstein": the dead `sales-*`
> generation was demolished and the live system consolidated. Do NOT add
> credentials, API keys, or secret file paths here.

---

## Project Overview

**Hoosier Online** is a single-operator B2B lead-to-cash engine built by Adam
Ferree. It finds Indiana local-service businesses (lawn care, cleaning,
handyman, etc.) with a weak web presence, builds each a personalised "front
door" preview page, prices one offer, and pitches them — increasingly on
autopilot. Adam runs it from an **iPhone**: triage taps and replies only.

**Stack:** vanilla PHP 8.x · MySQL (PDO) · HostGator shared hosting · no
framework, no Composer · GitHub Actions FTP auto-deploy.

---

## Branches & Deploy (read before pushing)

- **Production deploy branch:** `claude/admin-site-assessment-4Zrig`.
  `SamKirkland/FTP-Deploy-Action@v4.3.5` deploys every push on it to
  production.
- **Operation Frankenstein lives on:** `claude/awesome-volta-03lqnp` — a
  sandbox that does NOT auto-deploy. The demolition + consolidation was done
  here so nothing reaches production until a deliberate merge.
- **⚠ Merge-day warning:** the FTP action MIRRORS deletions. When this branch
  merges to the deploy branch, every file deleted here is removed from
  production too. Secrets are protected by the workflow `exclude` list
  (`database.php`, `admin-secrets.php`, `stripe-config.php`,
  `porkbun-config.php`). The whole dead generation therefore only exists in
  git history before the merge commit.

---

## Security Constraints

These are about not leaking credentials — everything else is fair game.

- `database.php`, `admin-secrets.php`, `porkbun-config.php` — **NEVER commit**.
- `stripe-config.php` at `/home1/spofnkte/stripe-config.php` — **NEVER commit**.
- `/home1/spofnkte/llm-config.php` — **NEVER commit**.
- Never commit DB credentials, admin secrets, logs, backups, SQL dumps, or ZIPs.

Schema SQL: write it freely; Adam pastes into phpMyAdmin (no live DB connection available).

Adam is on **iPhone** — instructions that need a desktop/terminal won't land.

---

## Admin Authentication (the operator lock — added in Frankenstein)

`app.php`, `money.php`, and the `audit-*.php` fetch endpoints used to be
**publicly reachable**. They are now gated.

- `admin-auth.php` — session + signed remember-me cookie.
  `ho_admin_require_login()` (HTML redirect to `/admin-login.php`) for pages;
  `ho_admin_require_login_json()` (401 JSON) for fetch endpoints. A 60-day HMAC
  remember cookie (keyed on the private `session_key`) means Adam logs in once.
- `admin-login.php` — login form; when no secrets exist yet it shows a setup
  helper that takes a chosen password and PRINTS a ready-to-paste
  `admin-secrets.php` (hash + random session_key computed server-side).
- **One-time setup (Adam, cPanel File Manager from the phone):** open
  `/admin-login.php`, pick a password, copy the printed file into
  `/home1/spofnkte/hoosier-online-private/admin-secrets.php` (preferred, outside
  public_html) or `./admin-secrets.php` (fallback), reload, log in.
- Shape: `['username' => ..., 'password_hash' => ..., 'session_key' => '<64 hex>']`.
- **NOT gated (by design):** `go.php`, `rep.php`, `start.php`, `index.php`,
  `checkout.php` (public), `webhook.php` (Stripe sig), `status.php` (token),
  `cron.php` + `llm-research.php` (key auth — gating them breaks autopilot and
  the inbound funnel), `domain-check.php`.
- CSRF on app.php's POST actions is deferred: SameSite=Lax cookies block
  cross-site POSTs and it's a single-operator tool. `ho_admin_csrf_*` helpers
  exist if/when wanted.

---

## File Map (the surviving live system — ~24 root PHP files)

| File | Purpose |
|---|---|
| `ho-model.php` | The business brain — all DB/pipeline/research/routing/pricing/email/SMS/autopilot/reputation/capture logic (~4,500 lines). |
| `app.php` | Admin cockpit (Source / Research / Send / Sales tabs). Login-gated. |
| `money.php` | Mission Control — one-feed "moves" execution UI, Apollo console theme (station callsigns, T+ mission clock, "Eagle has landed" on first conversion). Login-gated. JS binds to `mf-*` classes/ids — keep them when restyling. |
| `go.php` | Public preview/pitch page (`/go/{slug}`), site-build + enhancement tracks. |
| `rep.php` | Public Review Concierge page (`/rep.php?slug=`). |
| `start.php` | Public self-serve inbound funnel (`/start.php`). |
| `checkout.php` / `webhook.php` | Stripe checkout session + fulfillment webhook. |
| `cron.php` | Autopilot heartbeat (15-min cron). |
| `llm-research.php` | Key-authed Claude+web-search research endpoint (uses `ho_llm_call`). |
| `fd-chrome.php` | Shared `ho_fd_nav()` / `ho_fd_footer()` for go/rep/start, plus `ho_fd_boot()` — the once-per-session build-console boot overlay on go + rep (real-data lines, tap-to-skip, reduced-motion/JS-off safe; never rendered on paid/error views). |
| `ho-enhancement-packages.php` | Enhancement bundle helpers (used by go + checkout). |
| `index.php` | Public marketing homepage. |
| `status.php` | Token-gated customer order-status page. |
| `audit-url.php` / `audit-domain.php` | Login-gated website/domain audit fetch endpoints. |
| `porkbun.php` / `domain-check.php` | Domain availability (Porkbun). |
| `admin-auth.php` / `admin-login.php` / `admin-logout.php` | Operator lock. |

**CSS (4 sheets):** `front-door.css` (public pages), `cockpit.css` (app.php),
`money.css` (money.php), `site.css` (index.php). `templates/previews/` holds
the category preview templates. `db/` keeps `schema.sql`, `schema_v2.sql`,
`install_preview_v044.sql`, `seed_categories.sql` as reference.

---

## Pipeline Architecture

```
identified (triaged=0) → [triage tap] → triaged=1
  → [research: ho_generate_research_prompt → ho_import_research_json]
    → researched → ho_auto_generate_preview()
      → decent site + gaps → enhancement_ready (preview_type='enhancement')
      → decent site, no gaps → excluded (has_good_website)  [rep inventory]
      → no/poor site → preview_ready (preview_type='site_build')
      → no contact → needs_contact
  → [pitch] → pitched → converted / not_a_fit / excluded
```

`pipeline_status` ENUM: `identified`, `researched`, `preview_ready`,
`enhancement_ready`, `pitched`, `converted`, `not_a_fit`, `needs_contact`,
`excluded`. `previews.preview_type`: `site_build`, `enhancement`.

**Two products + recurring + a second line:**
- Site build — flat **$199**, one matched design, one Stripe checkout.
- Enhancement — per-gap priced bundle (16 gaps, `ho_enhancement_gaps()` +
  `ho_gap_prices()`), one total.
- Review Concierge (`rep.php`) — **$99** catch-up + optional **$29/mo**.
- Keep-It-Running care plan — **$29/mo**, 30-day trial, default-checked on
  checkout. Reputation price + care terms centralized in
  `ho_reputation_price_cents()` / `ho_care_plan($pkg)` (checkout + webhook both
  read them — no drift).
- $50 referral loop; inbound self-serve funnel (`start.php`); live lead
  capture on every preview (`captured_leads`).

**Autopilot** (`cron.php`, toggles `ap_*`): digest, drip, hotstrike, verify
(truth gate), autopitch, research, repdraft, source. Every send passes
`ho_autopilot_gate()` (master on, postal address set, 8am–6pm window, daily
cap, email_log present).

---

## Key Conventions (post-consolidation)

- **Gap labels live in `ho-model.php`:** `ho_gap_label()` = sellable product
  name; `ho_gap_label_short()` = cockpit badge shorthand; `ho_gap_keys_ordered()`
  = canonical key order. go.php `$fixDefs` keeps long sales copy inline (it
  interpolates page vars) — keep it bound to `ho_gap_keys_ordered()`.
- **Message builders share `ho_msg_base($biz)`** for the common signal reads
  (name/city/category, review+rating numbers, competitor parsing, default-length
  quote, the verified-vs-floored "40+" count). The seven builders'
  **hook ladders are intentionally NOT merged** — they diverge in branches,
  thresholds, `noSite` definition, quote length, and channel-specific copy.
  Don't collapse them into one selector; it regresses live outreach.
- **LLM calls go through `ho_llm_call()` + `ho_llm_extract_json()`** in
  ho-model.php — provider-agnostic, web-search-grounded. Config resolves via
  `ho_llm_settings()`: a DB key (set in the cockpit, Send → Autopilot → AI
  engine: `llm_provider`/`llm_api_key`/`llm_model`) wins over the legacy
  `/home1/spofnkte/llm-config.php` file. Entry points seed it with
  `ho_llm_boot($pdo)`; `ho_llm_ready($pdo)` reports availability. Two providers:
  `anthropic` (Claude, paid, default model `claude-sonnet-4-6`, web_search tool)
  and `gemini` (Google free tier, no card, default `gemini-2.5-flash`,
  google_search grounding) — switch providers to dodge Anthropic's per-minute
  token rate limit (429s). The zero-touch batch in app.php paces calls and
  retries the same lead on a 429 instead of skipping it.
- **Sourcing is Claude-first (Adam's Max plan, zero API spend).** The DEEP
  HUNT is the default: `ho_generate_hunt_prompt()` (one Claude pass that
  sources AND fully researches) → paste → `ho_import_hunt_json()` creates
  businesses (triaged=1), routes entries through `ho_import_research_json()`
  (contacts, research record, preview) — leads land pitch-ready. JSON root key
  `hunt_results` routes the cockpit paste importer to `import_hunt`; the
  classic candidates-only sweep survives at `?tab=source&run_id=N&mode=sweep`.
  Both prompts share `ho_research_record_spec()` — extend the schema THERE so
  hunt and research never drift. A re-hunt never touches a business already in
  play (pitched/converted/excluded/not_a_fit). `cp_claude_row()` renders the
  one-tap claude.ai deep link (falls back to Copy for prompts > ~6KB).
- **Public-page chrome** is `ho_fd_nav()` / `ho_fd_footer()` in `fd-chrome.php`.
- `ho_is_lead_platform_url()` blocks Angi/Thumbtack/Yelp/etc. as contact paths.

---

## Schema State (migrations applied via phpMyAdmin)

The code degrades gracefully (try/catch + comments) before each migration runs.
Columns/tables the live system expects: `businesses.triaged`,
`businesses.website_verified`; `research_records` 37-field expansion +
`review_quote_1/2(_author/_date)` + `verified_at` + `verification_json`;
`outreach_log.touch_number`; tables `preview_visits`, `email_log`,
`captured_leads`, `review_replies`, `gap_prices`, `app_settings`. The exact
ALTER/CREATE statements are in this file's pre-Frankenstein git history if a
fresh environment needs them.

---

## Deliberately Deferred / Not Done (don't "fix" without reason)

- **Message-builder hook ladders not merged** (see Key Conventions).
- **app.php Send tab still has 4 card-rendering loops** — money.php has the
  clean unified version. Left separate: the loops encode genuinely different
  card types and the cockpit JS binds to their DOM/`data-*`. Merge only with a
  runtime check available.
- **front-door.css not yet flattened** — 8 layered version sections, ~50
  redefined selectors. Flattening is the last open Frankenstein step; it's the
  one change with no automated verification, so it wants a human eyeball on
  `/go/{slug}`, `/rep.php`, `/start.php` after.
- **Contact/enrichment importer functions kept** — still wired into app.php.

---

## Known Gotchas

- `'good'` website_quality is dead; importer accepts `none/poor/basic/decent`.
- iOS auto-detects phone numbers and overrides CSS — never style phone text inline.
- `front-door.css` `.fd-pc-row` grid: `130px 58px 1fr; gap:8px` (price alignment).
- Enhancement card left border = `cp-send-card-enhance`; rep = `cp-send-card-rep`.
