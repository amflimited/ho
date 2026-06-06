v106 Contact Ready + Manual Review State

Purpose:
Remove the vague Ready To Contact review step.

Change:
- Ready To Contact is renamed Contact Ready.
- Contact Ready is a holding/output state, not a required next action.
- The Next card skips Contact Ready unless a concrete contact-prep/outreach task exists.
- Adds Needs Manual Review as the only review-like pile.

Manual Review catches:
- identity mismatch/conflict
- contact conflict/warning
- risk warning
- duplicate warning
- outside-Indiana uncertainty
- must_verify_before_contact
- contact_readiness=needs_manual_check/manual_check

Routing:
- clean usable contact path -> Contact Ready
- warning/conflict -> Needs Manual Review
- do_not_proceed/blocked -> Blocked / Skip
- No generic review is required for Contact Ready records.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No prompt format changes.
