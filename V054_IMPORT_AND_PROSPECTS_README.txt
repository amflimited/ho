v054 Import Auto-Processing + Simplified Prospects

Scope:
- Simplifies Prospects page to an admin/operator queue.
- Makes successful Research import automatically attempt preview readiness and option assignment.
- Keeps Business View available for inspection/troubleshooting only.
- No schema changes.
- No scraping.
- No security changes.
- No preview.php.
- No payment or outreach automation.

Behavior change:
- After Validate + Import succeeds, sales-research.php attempts:
  1. ho_salesportal_evaluate_preview_readiness(business_id, true)
  2. ho_salesportal_assign_preview_options(business_id, true)

Reason:
- If the JSON validated and imported, the system should assume the reviewed payload is acceptable enough to complete internal backend processing.
- Admin should not have to open Business View just to trigger readiness/option assignment.
