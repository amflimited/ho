v082 Admin Experience Reframe — Part 1

Goal:
Fix the daily admin flow at the system level, not just one page.

This package changes the primary admin experience from page-launching to workflow navigation.

What v082 changes:
1. Admin shell navigation becomes workflow-based:
   - Work
   - Paste
   - Find
   - Cases
   - Tools

2. Admin Home is demoted:
   - It is now Admin Tools, not the daily work hub.
   - Daily work points back to Work Queue.
   - Utilities are grouped under Tools.

3. Sales Work Queue remains the daily operating surface:
   - Dispatch Board
   - Intake Desk
   - Work Queues
   - Tools shelf
   - route to paste and prompts

4. Sales Research becomes Find Leads:
   - No fallback-page framing.
   - No paste/import UI.
   - One job: copy the lead generation prompt.
   - Returned JSON goes to Work Queue → Intake Desk.

5. CSS updates:
   - Workflow navigation styling.
   - Route hint styling.
   - Tool row styling.
   - Find Leads styling.

Scope:
- admin-core.php
- admin.php
- sales-portal-dashboard.php
- sales-research.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.

v083 Instructions:
Proceed with v083 only after v082 is installed and checked on iPhone Safari.

v083 should complete the admin experience reframe across the remaining surfaces:

Files to touch:
- sales-business.php
- sales-system.php
- upload.php
- sitemap.php
- sales-db-check.php
- assets/css/admin.css

Required v083 changes:
1. Business View becomes Case File:
   - visible page role: Case File
   - show current state and next decision first
   - primary prompt/action visible
   - secondary prompts collapsed
   - evidence, claims, scoring, and raw internals collapsed by default
   - add clear “Paste result in Intake Desk” route

2. Sales System becomes Playbook:
   - visible page role: Playbook
   - reference only, not a work surface
   - add “Return to Work Queue” at top
   - collapse doctrine/history sections by default
   - reduce giant page feel

3. Upload becomes Tool: Upload Update:
   - visible role: Tool
   - add warning/use-case text
   - primary action remains upload
   - add “Return to Work Queue”
   - reduce daily-work visual prominence

4. Sitemap/Backup becomes Tool: Backup:
   - visible role: Tool
   - clear use case: save or inspect site copy
   - add “Return to Work Queue”
   - dangerous/full backup actions should not look like daily workflow buttons

5. DB Check becomes Tool: System Check:
   - visible role: Tool
   - use only when something feels wrong
   - add “Return to Work Queue”
   - keep technical details but collapse lower if possible

6. CSS:
   - consistent tool-page treatment
   - compact mobile headings
   - lower-priority utility pages
   - case-file cards
   - collapsible details styling

Do not in v083:
- no schema changes
- no scraping
- no preview.php
- no payment
- no outreach automation
- no prompt format changes unless only label/routing text
