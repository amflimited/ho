v086 Queue-Specific Triage Prompt Fix

Problem:
The Need Triage pile could show only 3 records left, but "Copy Triage Prompt" still pointed to the older general 25-record bulk prompt.

Fix:
- Bulk triage candidate selection now uses the same Need Triage queue logic as the visible pile.
- The visible Bulk Triage Prompt is regenerated from the exact Need Triage pile.
- The Need Triage pile action copies the actual prompt field directly.
- Prompt language no longer says "up to 25" as if it is a generic batch; it refers to the exact task batch.
- Button label includes the visible pile count.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
