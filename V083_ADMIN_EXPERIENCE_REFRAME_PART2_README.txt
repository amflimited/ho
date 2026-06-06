v083 Admin Experience Reframe — Part 2

Goal:
Complete the admin experience reframe across non-daily surfaces.

This package does not change backend workflow, schema, import formats, scraping, preview, payments, or outreach.
It changes page roles and operator framing so the admin feels like one mobile application.

Changes:

1. Business View -> Case File
- Page role is Case File.
- Adds current decision / next action framing.
- Adds Return to Work Queue and Paste in Intake Desk routes.
- Keeps Candidate Triage Prompt visible as the primary prompt.
- Collapses Preview/Contact Setup Prompt.
- Collapses Business Refinement Prompt.
- Collapses Claims and Evidence Sources where matching sections exist.
- Adds case-file styling.

2. Sales System -> Playbook
- Page role is Playbook.
- Marked as reference only, not daily work.
- Adds Return to Work Queue and Intake routes.
- Reduces daily-work prominence through reference-card styling.

3. Upload -> Tool: Upload Update
- Page role is Tool.
- Adds install-carefully framing.
- Adds Return to Work Queue and Intake routes.

4. Sitemap/Backup -> Tool: Backup Copy
- Page role is Tool.
- Adds backup-specific use-case framing.
- Adds Return to Work Queue and Intake routes.

5. DB Check -> Tool: System Check
- Page role is Tool.
- Adds diagnostic-only framing.
- Adds Return to Work Queue and Intake routes.

6. CSS
- Adds tool-page treatment.
- Adds case-file treatment.
- Adds collapsible prompt/details styling.
- Keeps utility pages lower-priority visually.

Files:
- sales-business.php
- sales-system.php
- upload.php
- sitemap.php
- sales-db-check.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
