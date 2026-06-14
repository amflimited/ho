# AP Services Coverage & Assignment Tool

Deployed under `/wmt/`. PHP/MySQL, uses the Hoosier Online `ho_db()` connection
(`database.php`); no separate DB config.

## Pages
- `index.php` — dashboard, weekly summary, and print views (Management, TA
  Position Sheets, 15-minute grid, Turn-In).
- `assignments.php` — **source of truth** for daily output. Door posts,
  staggered breaks/lunches, floater/break cover, Grocery + GM side tasks,
  general floater tasks, and triggered tasks. Also Generate Week, sign-off,
  editable task definitions, preferred-door editor, and the printable packet.
- `tasks.php` — quick Grocery/GM side-task view (`wmt_task_assignments`).
- `import_html.php` — upload a saved WPP Scheduler HTML page to import shifts.

## One engine
All planning lives in **`wmt-engine.php`** (`wmt_*` functions). The three pages
are thin view layers over it, so the door/break/task logic exists once:
- `wmt_plan()` / `wmt_plan_core()` — 15-minute coverage planner with door
  continuity, Ops/TL flex rules, and staggered breaks.
- `wmt_build_assignments()` — full daily assignment list from a plan.
- `wmt_generate_dates()` — generate + save a set of dates (overwrite optional).
- `wmt_history()` — historical balancing inputs (door minutes, Grocery/GM split,
  task category + specific-task counts, missed/deferred "avoid" weight).
- `wmt_sanity()` — both-doors-uncovered (critical) + break-stagger warnings.

## Typical flow
1. First run: open `index.php`, set the admin password.
2. `import_html.php` (or Import on index) to load a week of shifts.
3. `assignments.php?v=week` → **Generate ALL imported dates** (saved to
   `wmt_auto_assignments`). Re-run with **Overwrite** to regenerate.
4. `assignments.php` to review the daily plan per associate.
5. `assignments.php?v=signoff&date=…` to mark Completed/Missed/Deferred/N/A —
   this feeds future balancing.
6. `assignments.php?v=packet&start=…` to print the weekly packet.

## Rules coded in
- AP Services owns the doors. One door covered beats both uncovered; door
  coverage beats tasks; tasks never imply abandoning a door.
- Short-term target: Grocery + GM covered 8AM–5PM. Gold standard later: Grocery
  6AM–11PM, GM 6AM–9PM (settings `*_gold_*`).
- AP Ops flexes only with two Ops available, never in the 10AM–12PM blackout.
- AP Team Lead flexes ≤ 15 min/day. AP Investigator never flexes.
- Breaks/lunches are staggered so two Services TAs are not off together when
  coverage could be preserved; if no clean window exists it is flagged.
- A task assigned while its owner is the only door owner (no floater) is marked
  "complete only if released".

## Editable task definitions (`assignments.php?v=tasks`)
Name · scope (Grocery only / GM only / Both / General / Triggered) · preferred
role (door owner / floater / any) · estimated minutes · priority · frequency ·
instructions. Add, edit, enable/disable.

## Tables
`wmt_settings`, `wmt_associates`, `wmt_shifts`, `wmt_tasks`, `wmt_turnins`,
`wmt_exceptions`, `wmt_html_imports`, `wmt_task_assignments`,
`wmt_assignment_items` (task definitions), `wmt_auto_assignments` (generated +
sign-off). Schema is created/migrated idempotently by `wmt_schema()`.

## JSON import format
```json
{
  "replace_schedule_dates": ["2026-06-15"],
  "associates": [
    {"name":"Example TA","team":"Services","role_type":"AP Service TA","preferred_door":"Grocery"}
  ],
  "schedule": [
    {"date":"2026-06-15","name":"Example TA","team":"Services","role_type":"AP Service TA","start":"08:00","end":"17:00","preferred_door":"Grocery"}
  ],
  "tasks": []
}
```
CSV header: `date,name,team,role_type,start,end,preferred_door,notes`.

## Notes
- Add `?debug=1` to any page to surface PHP errors (off by default).
- `index.php?export=json` exports the current data snapshot.
- Mobile uses card-style tables (`data-label`); desktop and print keep full
  tables. Planning is internally 15-minute; display compresses to change-only
  blocks.
