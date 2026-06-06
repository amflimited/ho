v092 Indiana Scope Normalization + Setup Result Routing Fix

Combines:
1. Indiana Scope Normalization
2. Setup Result Routing Fix

Problems:
- Setup prompts/results still treated New Castle service-area confirmation as a gate.
- City/service_area were being used as if they were hard qualification rules instead of sourcing context.
- After setup_results came back, records could stay in Ready For Setup and keep showing Copy Setup Prompt.
- setup_path/contact_readiness were not treated as the next-stage truth.

Fixes:
1. Indiana Scope Normalization
- Setup prompt language treats Indiana as the only location gate.
- New Castle is sourcing context only.
- Setup prompt no longer requires proof that a business serves New Castle.
- Only clear outside-Indiana signals should create a location problem.
- Case File location display is normalized so it does not falsely imply New Castle when a stronger identity/location exists.

2. Setup Result Routing
- Reads latest setup_path claim.
- Reads latest contact_readiness claim.
- Reads best_contact_method, preview_approach, and must_verify_before_contact as setup-result signals.
- setup_path=research_with_website or website_fix_preview routes to Need Research.
- contact_readiness=needs_manual_check routes to Need Research.
- do_not_proceed/blocked routes to Blocked / Skip.
- usable/ready contact signals route to Ready To Contact.
- Records with setup results are excluded from setup prompt candidates so they do not loop through setup again.
- Case File state and next-action text now explain setup-result routing.

Expected behavior:
- Zach's Lawncare with setup_path=research_with_website and contact_readiness=needs_manual_check should show Need Research, not Ready For Setup.
- It should not keep appearing in the setup prompt.
- must_verify_before_contact should not contain “confirm New Castle service area” unless manually provided by older data; future prompt output should ask only for Indiana relevance when location matters.

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
