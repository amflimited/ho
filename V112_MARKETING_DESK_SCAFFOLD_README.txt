v112 Marketing Desk Intake Scaffold

Purpose:
Creates the first Marketing Desk page for ready_for_marketing packages.

New page:
- /sales-marketing-desk.php

What it does:
- Loads ready_for_marketing preview packages.
- Groups Marketing Desk piles:
  - Ready For Outreach Review
  - Draft Needed
  - Draft Ready
  - Paused / Manual Review
  - Sent Later
- Generates one copy/paste GPT prompt to create outreach draft cards.
- Shows package asset links:
  - hotlink_path
  - design_dashboard_path
  - sales_report_path
- Provides clear links back to Package Workbench and Work Queue.

Prompt boundaries:
- Return ONLY valid JSON.
- Do not send anything.
- No SMS.
- No AI calls.
- No guaranteed leads, rankings, sales, or performance claims.
- No fake familiarity.
- Use respectful short copy.
- Prefer public email where available.
- Pause/manual review if no usable contact path.

What v112 does NOT do:
- no automatic email sending
- no SMS
- no AI calls
- no CRM automation
- no domain purchasing
- no upstream workflow changes

Next recommended step:
v113 — Marketing Draft Intake + Manual Ready-To-Send Staging
