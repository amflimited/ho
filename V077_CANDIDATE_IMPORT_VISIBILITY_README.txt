v077 Candidate Import Visibility Fix

Problem:
- Sales Research still had validation trouble on pasted candidate JSON in some paths.
- Prospects could report import success while no new records appeared, especially when records were treated as skipped duplicates or low-visibility hold candidates.

Fixes:
1. Candidate-batch imports now enter as needs_review instead of hold.
   This makes new candidate records visible in the normal Prospects queue.
2. Candidate-batch marketing_clearance_score now starts at 35 instead of 0.
3. Exact duplicates validate as ok/skipped instead of failed.
4. Import result summary now separates:
   - created
   - skipped
   - failed
5. Prospects import feedback now explains whether records were created or all skipped.
6. Research page duplicate validation behavior is aligned with Prospects.

Scope:
- sales-research.php
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
