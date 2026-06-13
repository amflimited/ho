# CLAUDE.md — Hoosier Online v2 ("Gemini")

This file is the constitution for the `ho-v2` repository. Every Claude Code session
starts here. If code and this file disagree, this file wins — or this file gets
amended explicitly. No silent drift.

---

## SESSION HANDOFF — read this first, then the rest of the file

**Branch:** `v2` in `amflimited/ho`. Deploy target: `v2.hoosieronline.com` (document root `public_html/ho-v2/public`). GitHub Actions FTP deploys on every push to `v2`.

**Milestone 1** (schema, codegen, importer, migrations 001–002): DONE and deployed.
**Milestone 2** (workers, renderers, billing, public endpoints): code DONE, server bootstrapped (admin_key live, settings configured).
**Receptionist track** (June 2026): the second offer — $149/mo AI receptionist with
audio demos. Read `docs/RECEPTIONIST.md` (product, economics, fulfillment runbook).
Code DONE: `migrations/004_receptionist.sql`, `prompts/callscripts.md`,
`src/Llm/Tts.php` (Gemini multi-speaker TTS), `src/Workers/Voice.php` (job `voice`),
`public/listen.php` (`/listen/{slug}`), subscription checkout
(`checkout.php?offer=receptionist&biz={business_slug}`), webhook `package=receptionist`
+ operator order notification. Needs on live server: run `migrate` job (applies 004),
set `tts_api_key` (a Gemini key) in cockpit settings.

### First thing to do in this session

Check whether `config/db.php` exists. If it does not, create it:

```php
<?php
return [
    'host'   => 'localhost',
    'dbname' => '<ask Adam>',
    'user'   => '<ask Adam>',
    'pass'   => '<ask Adam>',
];
```

Then run `php bin/setup.php` — it will apply `migrations/003_milestone2.sql`, set a fresh `admin_key` in `app_settings`, and print the key. Keep that key; it authenticates `cron.php?job=…&key=…`.

### What is built (all committed, all in `src/`)

| File | Purpose |
|---|---|
| `src/Domain/Business.php` | Typed canonical record (generated) |
| `src/Domain/Pipeline.php` | Forward-only state machine |
| `src/Domain/Score.php` | Fit score + route decision |
| `src/Import/Importer.php` | THE only door for business data |
| `src/Import/Validator.php` | Generated enum/null/clamp rules |
| `src/Import/JsonCleaner.php` | LLM JSON repair |
| `src/Import/V1Etl.php` | One-time v1→v2 migration |
| `src/Llm/Client.php` | Anthropic + Gemini, 429 retry |
| `src/Render/Preview.php` | Preview page rows + pricing |
| `src/Render/Pitch.php` | Touch-1 email AI + fallback |
| `src/Render/FollowUp.php` | Touches 2–4 |
| `src/Workers/Research.php` | Batch LLM research worker |
| `src/Workers/Verify.php` | Truth Gate batch worker |
| `src/Workers/Personalize.php` | Preview + pitch draft per lead |
| `src/Workers/Runner.php` | Dispatcher: all workers |
| `src/Outreach/Sender.php` | Touch sequencer + gate enforcement |
| `src/Outreach/Gate.php` | CAN-SPAM: master switch, window, cap, suppression |
| `src/Outreach/Heat.php` | Visit logging + daily digest |
| `src/Outreach/Mailer.php` | php mail() with logging |
| `src/Outreach/Notify.php` | Operator-only transactional mail |
| `src/Billing/StripeWebhook.php` | Webhook verify + order creation |
| `public/go.php` | Preview pitch page + heat tracking |
| `public/checkout.php` | Stripe Checkout Session creator |
| `public/status.php` | Order status page |
| `public/webhook.php` | Stripe webhook endpoint |
| `public/cron.php` | Web worker trigger (phone-accessible) |
| `bin/cron.php` | CLI worker trigger |
| `bin/setup.php` | One-time bootstrap (run once per environment) |

### What is NOT built yet (next priorities)

1. **Cockpit UI** (`public/cockpit/`) — phone-first operator interface: triage queue, paste import box, worker run buttons, settings panel. This is the main thing blocking Adam from using the system day-to-day.
2. **Source worker** (`src/Workers/Source.php`) — autopilot daily sourcing: picks category + area, calls LLM with `prompts/sourcing.md`, pipes result through Importer.
3. **V1 ETL run** — needs `config/db-v1.php` pointing at the old database, then `php bin/etl-v1.php`.
4. **Live settings** in `app_settings`: `llm_api_key`, `ap_from_email`, `ap_postal`, `ap_digest_email`.

### Known issue (fixed in code, not yet live)

`migrations/003_milestone2.sql` has not been applied to the live DB yet. `php bin/setup.php` handles this.

---

## Companion document: `/docs/SALVAGE.md` — the full autopsy of v1. Read §6 (the
connector map) before writing any code. It is the list of mistakes you are
forbidden to repeat.

---

## Mission

Local-business leads in one end → **booked sales conversations and Stripe checkouts**
out the other, with near-zero operator attention per lead. The operator (Adam) does
two jobs only: one-tap triage of sourced leads, and talking to humans who replied or
booked. Everything else is the machine.

v1 proved every capability: sourcing, research, verification, scoring, previews,
pitching, heat tracking, checkout. v2 exists to make those capabilities run on a
single canonical representation so the system can evolve without breaking itself.
Judge every task against: does this increase revenue per operator-hour?

## The Verdict on v1 (what we keep, what dies)

**Ports forward, logic verbatim (from SALVAGE.md):**
- Pipeline state machine (§1, `businesses` table) — proven, never-backward
- Fit score (§3, `ho_fit_score()`) — port exactly, then iterate by version
- 16-gap detector with priority order and the dual-broken tech_issues edge case (§3)
- Package routing rules (§3)
- Truth Gate adversarial verification — prompt and correction behavior (§2 Prompt 5).
  This is the only wall between hallucination and a cold email. It is sacred.
- All seven prompts (§2) — moved into `/prompts/*.md`, parameterized
- CAN-SPAM enforcement + autopilot gate + daily caps + send window (§5 #4, #5)
- Verbatim-quote rule for reviews (legal boundary — never paraphrase)
- The Claude Max paste path (§4) — zero-API-cost acquisition stays first-class
- JSON-cleaning logic from `ho_clean_json()` (§5 #1) — reimplement in v2 style
- Provider-agnostic LLM caller with Anthropic/Gemini switch and 429 handling (§5 #2)
- URL liveness checking and lead-platform URL blocking (§4 quality gates)
- Franchise auto-exclusion, confidence gating, freemail detection

**Dies with v1 (§6 — the connector map):**
- Multiple import root keys (`candidates` / `hunt_results` / `research_results`)
  each with its own importer → ONE import endpoint, ONE schema
- Hand-maintained parallel representations of the research spec (prompt JSON,
  DB columns, importer validation, preview builder inputs) → generated from
  one schema definition
- `app_settings` stash keys as a shadow database (`pitchdraft_{id}`,
  `sitejson_{id}`) → real columns/tables
- Contact/enrichment importers that overlap research import → gone; one path
- Any function whose job is reshaping internal format A into internal format B

## The One Rule

**`schema/business.schema.json` is the single source of truth.** One JSON Schema
file defines every field of the canonical Business record: name, type, enum values,
nullability rules (e.g., website sub-fields null when `has_website=false`), and
per-field notes (e.g., "VERBATIM, ≤40 words").

A generator (`bin/codegen.php`) produces from it:
1. **SQL migrations** — the `business_profile` DDL
2. **The research spec block** — the exact JSON skeleton injected into the
   sourcing/hunt/research prompts (replaces hand-maintained `ho_research_record_spec()`)
3. **The import validator** — enum checks, clamps (rating 0.0–5.0), null rules,
   franchise exclusion, confidence gating
4. **The typed `Business` class** — typed getters, no raw array access anywhere
   outside the import/persistence layer

To add a research field: edit the schema file, run codegen, write the migration
note. The prompt, validator, DB, and class update together. Drift is now impossible.
This is the entire reason v2 exists.

If you ever find yourself writing a converter between two internal shapes, STOP —
both sides must consume the canonical Business object. Renderers (Business →
email text, Business → preview HTML, Business → site JSON) are pure functions of
the canonical object and are fine. Importers other than THE importer are not.

## Stack (deliberately boring)

- **PHP 8.3**, `declare(strict_types=1)` in every file, PSR-12, Composer autoload
  (`HoV2\` namespace). No framework. Same hosting environment as v1 — Stripe
  webhooks, cron, custom-domain dispatch, and DNS already work there. We are not
  re-solving deployment.
- **MySQL** via PDO, prepared statements only. Migrations as numbered SQL files in
  `/migrations` with a tiny runner (`bin/migrate.php`) and a `schema_migrations` table.
- **Secrets** stay outside public_html exactly as v1 did (database.php,
  stripe-config.php, admin-secrets.php pattern). Never in the repo.
- **Email:** keep the v1 mailer pattern (postal footer required, every send logged)
  behind a `MailerInterface` so PHP `mail()` can be swapped for a transactional
  API provider by changing one class when deliverability data says so.
- **LLM:** one client (`src/Llm/Client.php`) with Anthropic + Gemini drivers ported
  from SALVAGE §5 #2, search and no-search modes, 429 retry. All prompts in
  `/prompts/*.md` with placeholder syntax `{like_this}` — never inline in code.

## Layout

```
ho-v2/
  CLAUDE.md                 ← this file
  docs/SALVAGE.md           ← the autopsy
  schema/business.schema.json
  bin/codegen.php  bin/migrate.php  bin/cron.php  bin/setup.php
  migrations/
  prompts/        sourcing.md hunt.md research.md verify.md pitch.md repdraft.md
  src/
    Domain/       Business.php (generated) Pipeline.php Score.php Gaps.php
    Import/       Importer.php Validator.php (generated) JsonCleaner.php V1Etl.php
    Llm/          Client.php
    Render/       Preview.php Pitch.php FollowUp.php Site.php ReviewReply.php
    Workers/      Runner.php Research.php Verify.php Personalize.php
    Outreach/     Sender.php Gate.php Suppression.php Heat.php Notify.php Mailer.php
    Billing/      StripeWebhook.php Orders.php
  public/         index.php go.php checkout.php status.php webhook.php cron.php cockpit/
  tests/
```

Amendment (Milestone 2): the operator is phone-only, so every worker is also
triggerable over the web — `public/cron.php?job=…&key=…` (admin key) and the
cockpit run buttons. `bin/cron.php` remains for real cron. `Notify` carries
operator/transactional mail only (digest, captured-lead forwarding) and must
never send cold outreach — that path is Mailer → Gate, no exceptions.

## Pipeline (unchanged from v1 — it was right)

```
identified (triaged=0)
  → [operator one-tap] triaged=1
    → [research + truth gate] researched (verified_at stamped)
      → no/poor site            → preview_ready      (site_build, $199)
      → decent site + gaps      → enhancement_ready  (priced gap bundle)
      → decent site, no gaps    → excluded → rep inventory (Review Concierge)
      → no contact found        → needs_contact
    → [pitch: touches 1–4 at +0/+3/+7/+11 days] pitched
      → converted / not_a_fit / excluded
```

Plus a `suppression` table (new): email/domain, reason (unsub, bounce, complaint,
manual), created_at. Checked by the Gate before EVERY send, no exceptions.
"Reply unsubscribe" replies get recorded here the same day they arrive.

## Workers (each = one cron-able command via bin/cron.php, all idempotent)

1. **source** — autopilot daily sourcing (port v1 area-rotation + least-covered
   category logic) AND the paste endpoint for Claude Max output. One importer.
2. **research** — batch research via LLM client with search; through the validator;
   UPSERT canonical records.
3. **verify** — Truth Gate on every record before it may be pitched. Unverified
   records cannot enter the send queue. Corrections applied as v1 did (wrong count
   → fix; unconfirmed quote → blank; missing competitor → clear).
4. **score** — fit score (versioned), rank the queue.
5. **personalize** — generate preview page + pitch draft (AI with template
   fallback, exactly as v1's hook-ladder fallback worked).
6. **send** — gate check (master switch, postal, window 8am–6pm, daily cap,
   suppression) → send next due touches → log.
7. **heat** — preview visits, hot-strike detection, outcome updates, digest email.
8. **report** — the funnel: sourced → triaged → researched → verified → pitched →
   viewed → replied → converted, by category/area/sequence-version. This table is
   how all Milestone-3 decisions get made. Numbers, not vibes.

## Data Migration (Milestone 1 deliverable — do not skip)

v1's database is live capital: researched records, pitch history, outcomes, and —
critically — everyone who unsubscribed or said not_a_fit. One-time ETL script
(`bin/migrate-v1.php`): map v1 tables → canonical schema, carry pipeline status,
seed the suppression table from v1 unsubs/not_a_fit/excluded outcomes. Re-pitching
someone who already opted out is the one mistake the new machine must be incapable
of on day one.

## Build Order

**Milestone 1 — The Spine.** Schema file + codegen + migrations + importer +
v1 data migration + score. Done when: every v1 business exists as a canonical
record, the paste path imports a fresh Claude Max batch end-to-end, and codegen
round-trips (edit schema → regenerate → tests pass).

**Milestone 2 — The Weapon.** Renderers (preview, pitch, follow-ups) + verify +
send + gate + suppression + heat + Stripe webhook port. Done when: one real
gated send day completes — touch 1 out to verified leads, preview visits tracked,
caps and suppression enforced, a test checkout lands an order row.

**Milestone 3 — Yield.** Iterate prompts, scoring weights, and touch timing purely
on `report` numbers, A/B by sequence_version. Nothing new gets built until the
funnel says where the leak is. Live-site rendering and Review Concierge port in
this phase, demand-driven, not before.

## Working Rules for Claude Code Sessions

1. Spec wins; amend it explicitly or follow it.
2. Plan before code for anything touching >2 files: list files, schema changes,
   tests, then execute.
3. Tests on what can lose money or trust: importer/validator (every enum, every
   null rule), suppression + gate (prove a suppressed address cannot be emailed),
   state machine (prove it never moves backward), idempotency (running a worker
   twice = once). UI gets no tests; the data layer gets all of them.
4. Every session ends with migrations applied and `bin/cron.php` runnable. Never
   leave the repo mid-surgery.
5. Generated files are never hand-edited. Edit the schema, regenerate.
6. New Composer dependency requires a one-line justification in the commit.
7. The verbatim-quote rule and CAN-SPAM gate are load-bearing legal walls. They are
   not refactoring targets. If a change touches them, stop and ask the operator.

## Definition of Winning

`report` shows checkout conversions, and Adam's week is: triage taps, replies,
calls. When something needs his keyboard for any other reason, that's a bug in
the machine — file it as one.
