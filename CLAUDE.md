# Hoosier Online — Project Rules

## Operator

Adam is the sole operator. He works exclusively on an iPhone. There is no desktop, no terminal, no git CLI.

## Instruction rules — non-negotiable

- **Never** tell Adam to pull, clone, download a file, run a terminal command, or manage files manually.
- **Never** send Adam through a multi-step file process when the same result can be a single paste.
- If a fix requires a **database change**: provide copy-paste SQL he can paste directly into phpMyAdmin's SQL tab and click Go. One paste, one click.
- If a fix requires a **code change**: make it, commit it, push it silently. Adam does not deploy — the push is the deployment.
- If something needs to happen on the server, find a way to do it through the browser (phpMyAdmin SQL tab, cPanel file manager) with the fewest possible taps — or don't ask Adam to do it at all.

## Stack

- PHP 8.5, strict types, `ho_` prefix convention
- MySQL/InnoDB via PDO, `spofnkte_db` database, utf8mb4
- cPanel shared hosting at HostGator
- File-based routing — no framework
- `database.php` contains live credentials — never commit it (already in .gitignore)

## Architecture

- `/app.php` — the one-page cockpit (Source / Research / Send)
- `/go.php` — public preview/sales page, routed via `/go/{slug}`
- `/ho-model.php` — all model functions for v2 schema
- `/db/schema_v2.sql` — 7-table schema
- `/db/seed_categories.sql` — 27 categories

## Pipeline

identified → researched → preview_ready → pitched → converted / not_a_fit

Research import auto-generates previews. No manual build step.
