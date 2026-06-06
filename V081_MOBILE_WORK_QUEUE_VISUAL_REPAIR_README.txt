v081 Mobile Work Queue Visual Repair

Purpose:
v080 had the right work-state idea but the mobile presentation became ugly:
- giant headings
- raw-looking queue summaries
- cramped/mashed queue rows
- tools dominated the bottom of the page
- some CSS looked stale on iPhone Safari

Fix:
- Adds critical inline CSS on Sales Work Queue to beat stale admin.css cache.
- Shrinks page/card headings.
- Makes queue counts real compact chips.
- Makes queue summaries readable two-line rows with count badges.
- Collapses Tools into one "Open tools and filters" panel.
- Tightens Dispatch Board and Intake Desk spacing.
- Keeps the v080 work-state model intact.
- Adds small visual repair to Business Case File view.

Scope:
- sales-portal-dashboard.php
- sales-business.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
