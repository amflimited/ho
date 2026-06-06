v105 Research Completion Inference

Problem:
v104 marks future research imports with research_completed=yes, but records already accepted under v103 do not have that marker.
Those records can still appear in Need Research even though their bulk_business_refinement result was accepted.

Fix:
- ho_salesportal_ui_research_completed now also infers completed research from v103 import footprints:
  - source_label contains "Bulk business refinement result"
  - evidence_note contains "bulk research/refinement"
  - evidence_note contains "research/refinement result"
  - evidence_note contains "bulk refinement update"
  - refinement-sourced website/contact claims
- These inferred completed records route like explicit research_completed records:
  - blocked/do_not_proceed -> Blocked / Skip
  - ready/contactable -> Ready To Contact
  - needs_more_research/still_need_research -> Need Research
  - default -> Ready For Setup

Expected:
The six records accepted by v103 should leave Need Research without needing to paste the same result again.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No prompt format changes.
