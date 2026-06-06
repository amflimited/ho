v088 Triage Repeat Loop Fix

Problem:
After triage results were imported, the same businesses could still show up in Need Triage and be prompted for triage again.

Cause:
The Need Triage eligibility check relied too much on a narrow claim lookup. Some triage results update the business clearance status/score even when the dashboard list does not expose the triage claim in the way the queue checker expected.

Fix:
- Adds clearance-score awareness to triage completion.
- Treats warm_clear with score >= 45 as already triaged and routes it to Need Research.
- Treats hold with score >= 45 as already triaged and routes it to Proceed No Website.
- Keeps low-score hold/needs_review records eligible for triage.
- Removes warm_clear records from bulk triage eligibility.
- Keeps skip/blocked/cleared out of Need Triage.
- Need Triage now only includes records that truly still need first-pass triage.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
