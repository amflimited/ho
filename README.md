# Hoosier Online v2 — "Gemini"

The sales machine, rebuilt on one canonical schema. Read `CLAUDE.md` for the
constitution and `docs/SALVAGE.md` for the v1 autopsy.

## What's already built (and tested — `php tests/run.php`, 19/19)

- `schema/business.schema.json` — single source of truth, 78 fields
- `bin/codegen.php` — generates DDL + research prompt spec + Validator + Business class
- `migrations/` — core tables incl. the suppression table
- `src/Domain/` — Business (generated), Gaps (16-gap detector), Score (fit + routing)
- `src/Import/` — THE one importer, Validator (generated), JsonCleaner
- `src/Llm/` — Anthropic/Gemini client with 429 handling, prompt templating
- `src/Outreach/` — Gate (CAN-SPAM + caps + window + suppression + Truth Gate check), Suppression, Mailer
- `prompts/` — all six v1 prompts, ported, using the generated `{research_spec}`
- `bin/migrate-v1.php` — one-time ETL from the old database, seeds suppression

## Your 15 minutes

1. Push this repo to GitHub (`git remote add origin ... && git push`)
2. Create the `ho_v2` MySQL database on your host
3. Copy `config/db.example.php` → `config/db.php` with real creds (and `db-v1.php`
   pointing read-only at the old database)
4. `php bin/migrate.php` then `php bin/migrate-v1.php`
5. Spot-check the counts it prints, especially suppression

## Then hand the rest to Claude Code

Open Claude Code in this repo and say:

> Read CLAUDE.md and docs/SALVAGE.md. Milestone 1 is built and tested
> (tests/run.php). Verify the v1 data migration ran clean, then begin
> Milestone 2: Render/ (preview pages, pitch + follow-up generation with
> template fallback), the Truth Gate verify worker, bin/cron.php wiring,
> the paste-import endpoint, and the Stripe webhook port. Plan first.

## Daily ops once live

- Paste a Claude Max hunt batch → import endpoint → leads land scored
- Cron runs `bin/cron.php` → verify → personalize → gated sends → heat
- You: triage taps, replies, calls. That's the whole job.

## Rules that keep you out of trouble

- Never hand-edit `src/Import/Validator.php`, `src/Domain/Business.php`,
  `migrations/002_*` — edit the schema, run `php bin/codegen.php`
- The Gate and the verbatim-quote rule are legal walls, not refactor targets
- Every send passes Gate → Suppression → Truth Gate verified. No bypass path exists; don't build one.
