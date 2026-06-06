v123f — Diagnosis SQL Enum Safety Fix

Problem:
Diagnosis import reached SQL, then failed:

SQLSTATE[01000]: Warning: 1265 Data truncated for column 'marketing_clearance_status'

This was not just one bad column.

Actual database enums:
- marketing_clearance_status:
  cleared, warm_clear, needs_review, hold, skip, blocked

- recommended_package:
  standard, managed, unknown

Bad diagnosis payload values:
- marketing_clearance_status = contact_ready
- recommended_package = standard_front_door

If only marketing_clearance_status were fixed, recommended_package would likely fail next.

Fix:
- Adds ho_diag_normalize_clearance_status()
- Adds ho_diag_offer_to_recommended_package()
- Adds ho_diag_safe_recommended_design()
- Diagnosis imports now write database-safe top-level business values:
  - diagnosis_ready/contact_ready/preview_ready -> cleared
  - diagnosis_review/manual_review -> needs_review
  - standard_front_door -> standard
  - managed_front_door -> managed
  - recommended_design trimmed to 120 characters

Preserves diagnosis claims:
- primary_offer_path still stores standard_front_door/managed_front_door as a claim
- front_door_preview_status still stores preview_ready as a claim

No workflow changes.
No outreach.
No sending.
No SMS.
No AI calls.
No payment.
