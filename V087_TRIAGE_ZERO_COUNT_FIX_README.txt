v087 Triage Zero Count Fix

Problem:
After the queue-specific prompt fix, the visible Need Triage count could still show 1 even when the copied triage prompt had no real records left.

Cause:
The visible Work Queue and the prompt generator were still allowed to disagree about what counts as a triage candidate.

Fix:
- Adds one shared eligibility rule for bulk triage candidates.
- Need Triage queue uses the same eligibility as the copied triage prompt.
- Records with existing triage decisions are no longer kept in Need Triage.
- Records that are cleared/blocked/skipped are no longer kept in Need Triage.
- If there are zero triage candidates, both the pile and prompt area show zero/empty.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
