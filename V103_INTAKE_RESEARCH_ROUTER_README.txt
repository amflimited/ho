v103 Intake Research Router Fix

Problem:
When a Research Prompt result was pasted, Intake treated it like a new lead candidate batch.
That caused this false error:
"Candidate is missing candidate_name/business_name."

Cause:
The generic intake router mapped top-level businesses/items arrays to candidate import unless each item already had a nested business payload.

Fix:
- Adds explicit research/refinement detection:
  - research_goal = bulk_business_refinement
  - business_updates
  - research_results
  - updates
- Routes those payloads through refinement/update conversion, not candidate conversion.
- Keeps candidate validation only for source_batch + candidates/prospects style lead imports.
- For research outputs with business_id/business_slug/business_name, builds a normal importable update payload.
- Supports explicit claims/evidence_sources when GPT returns them.
- Converts common top-level research fields into claims.

Expected:
A pasted bulk_business_refinement JSON should import/update existing records instead of failing candidate_name/business_name validation.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No prompt format changes.
