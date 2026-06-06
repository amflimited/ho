v085 Triage Action Flow Fix

Problem:
The simplified UI showed "Need Triage" but did not carry the operator into the triage action.
Queue rows opened generic cases, and Case File showed a vague/empty State box instead of the actual state and next action.

Fix:
1. Work Queue:
- Need Triage pile now has a direct "Copy Triage Prompt For This Pile" action.
- Need Triage pile has a direct "Paste Triage Result" action.
- Ready Setup / Proceed No Website piles get setup prompt actions when available.
- Queue rows use context-aware action labels instead of always "Open Case".
- Queue rows show queue-specific next-action text.

2. Case File:
- Adds case state detection from current business/claims.
- Replaces vague State area with actual state and next action text.
- Primary action title uses the actual case state.
- Need Triage case button says "Copy Triage Prompt".
- Candidate Triage Prompt explains what decision it is for.

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
