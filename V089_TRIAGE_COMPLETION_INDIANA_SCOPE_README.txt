v089 Triage Completion + Indiana Scope Fix

Problems:
1. The same records could keep reappearing in the same triage prompt.
2. The triage prompt asked GPT to verify New Castle service area, which is too narrow. The user only cares that the business is Indiana-relevant.

Fixes:
1. Triage import now writes explicit routing markers:
   - triage_completed = yes
   - triage_result_status = raw triage category
   - triage_next_step = raw next-step recommendation

2. Need Triage eligibility now checks those explicit markers.
   If a business has triage_completed or a stored triage result, it is no longer eligible for first-pass triage.

3. Queue routing now uses triage_result_status and triage_next_step:
   - research_with_website / research_ready -> Need Research
   - proceed_no_website / quick_hold / no_public_surface / needs_identity_check -> Proceed No Website
   - do_not_proceed / bad_fit / exclude / duplicate_or_confused -> Blocked / Skip

4. Triage prompt scope changed:
   - no New Castle service-area proof required
   - Indiana relevance is enough
   - only flag location if clearly outside Indiana

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
