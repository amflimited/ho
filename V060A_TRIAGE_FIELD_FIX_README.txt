v060a Bulk Triage Field Fix

Reason:
v060 attempted to store triage category in a new claim field:
- triage_status

The existing validator correctly rejected it because triage_status is not an allowed field_key.

Fix:
- Removes triage_status claim.
- Stores triage category inside existing allowed fields:
  - marketing_clearance_status
  - primary_sales_angle
  - notes
- No schema change.
- No validator change.
- No new field_key values.

After install:
- Re-run Validate on the same triage_results JSON.
