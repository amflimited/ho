v123d — Diagnosis Import Function Adapter

Problem:
Diagnosis Intake parsed JSON successfully but failed to save:

Diagnosis import complete. 0 ok, 10 failed.
First issue: Import function unavailable.

Cause:
sales-diagnosis-workbench.php called:
  ho_salesportal_import_business_payload()

But the real current codebase provides:
  ho_salesportal_import_payload()

Fix:
Adds ho_diag_import_payload() adapter:
1. Uses ho_salesportal_import_payload() when available.
2. Falls back to ho_salesportal_import_business_payload() if present.
3. Gives a clear error if neither exists.

No workflow changes.
No outreach.
No sending.
No SMS.
No AI calls.
No payment.
