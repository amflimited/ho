v096 Case Raw Details Collapse

Purpose:
v095 fixed the Case File primary action, but raw diagnostic machinery still spilled open below the active prompt.

Fix:
- Adds server-side collapse for obvious raw case sections.
- Adds client-side safety net to collapse table-heavy/raw sections that markup changes may miss.
- Leaves these visible:
  - Case summary
  - Next action
  - active prompt
  - collapsed secondary prompts
- Collapses:
  - Workflow Position
  - Best Signals
  - Main Problems
  - Claims by Category
  - Find Me / Fix Me / Trust Me / Show Me / Contact Me
  - Evidence Sources
  - Raw/Latest/All Claims
  - Requirement/ME scores
  - Preview Readiness
  - Option Assignment
  - table-heavy diagnostic sections

Scope:
- sales-business.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
