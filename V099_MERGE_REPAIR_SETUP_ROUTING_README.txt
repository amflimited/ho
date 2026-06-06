v099 Merge Repair: Setup Routing + Batch Copy

Problem:
v098 fixed copy buttons and reinforced batch-first workflow, but it was built from an older dashboard base and regressed part of the setup-result routing from v092/v093/v094.

Visible symptom:
- The page says "Run preview/contact setup" even though setup had already been run.
- Records that have setup_path/contact_readiness can still be counted as setup-ready.

Fix:
- Keeps v098 copy helper and batch-first workflow.
- Restores setup_result routing:
  - setup_path=research_with_website / website_fix -> Need Research
  - contact_readiness=needs_manual_check/manual_check -> Need Research
  - usable/ready contact signals -> Ready To Contact
  - blocked/do_not_proceed -> Blocked / Skip
- Excludes records with setup results from setup prompt candidates.
- Next card will not show Run preview/contact setup unless setup prompt candidate count > 0.
- Restores broad local-service sourcing prompt rule.

Scope:
- sales-portal-dashboard.php
- sales-business.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
