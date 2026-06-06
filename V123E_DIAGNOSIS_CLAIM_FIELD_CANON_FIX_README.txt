v123e — Diagnosis Claim Field Canon Fix

Problem:
Diagnosis Intake reached the importer, but each diagnosis failed validation:

Diagnosis import complete. 0 ok, 10 failed.
First issue: Validation failed.

Cause:
The Sales Portal validator only allows field_key values listed in ho_salesportal_canon()['claim_fields'].
The new diagnosis fields were not yet in that canonical list.

Adds canonical claim fields:
- diagnosis_status
- strength_keys_json
- weakness_keys_json
- recommendation_keys_json
- primary_offer_path
- preview_direction_keys_json
- diagnosis_risk_flags_json
- front_door_preview_status

Files:
- prospect-model.php
- existing v123d diagnosis files for cumulative safety

No workflow changes.
No outreach.
No sending.
No SMS.
No AI calls.
No payment.
