v079 Triage Update Not Duplicate Fix

Problem:
Bulk candidate triage results were being treated like new lead submissions. Because the businesses already existed, the duplicate guard skipped or blocked them instead of absorbing the triage data.

Correct behavior:
- New candidate/source_batch imports: duplicate guard applies.
- Bulk triage results: update/absorb into existing business records.
- Preview/contact setup results: update/absorb into existing business records.
- Full refinement results: update/absorb into existing business records.

Fix:
- Adds payload-mode detection.
- Duplicate guard only runs for new candidate submissions.
- Triage/setup/refinement payloads bypass duplicate blocking and use the normal import/update path.
- Messaging updated to clarify the difference.

Scope:
- sales-portal-dashboard.php
- sales-research.php

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
