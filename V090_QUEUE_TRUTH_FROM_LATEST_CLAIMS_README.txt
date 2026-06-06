v090 Queue Truth From Latest Claims

Finding from uploaded SQL audit:
- The visible business table status/score often does not match the latest imported triage claims.
- The dashboard list query did not attach the current claims to each business row.
- Queue logic was therefore routing from stale business columns and fallback guesses.
- This is why records could repeat in triage or sit in the wrong pile.

Fix:
1. prospect-model.php now attaches latest active claims to each listed business:
   - _claims
   - _latest_claims_by_key

2. sales-portal-dashboard.php now uses latest claim values first for:
   - marketing_clearance_status
   - marketing_clearance_score
   - primary_sales_angle
   - triage_completed
   - triage_result_status
   - triage_next_step

3. Stale business table columns are now fallback only.

4. Adds a small collapsed Queue Truth note explaining this.

Expected current routing from uploaded SQL:
- Need Triage: 0
- Need Research: 13
- Proceed No Website: 15
- Blocked / Skip: 8

Scope:
- prospect-model.php
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
