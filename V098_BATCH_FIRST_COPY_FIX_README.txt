v098 Batch-First Copy Fix

Problems:
1. Case File primary copy button did not copy reliably.
2. The workflow was drifting back into one-business-at-a-time work.

Fixes:
1. Robust copy helper:
- Adds hoCopyTextById() with navigator.clipboard and iPhone Safari fallback.
- Buttons show "Copied" feedback.
- Works for textarea prompt boxes.

2. Case File:
- Primary action is now an actual button when the state has a prompt.
- Need Research copies Business Refinement Prompt.
- Need Triage copies Candidate Triage Prompt.
- Ready For Setup copies Setup Prompt.
- Adds note: Case File is for exceptions/manual inspection.

3. Work Queue:
- Adds Research Prompt For Need Research Pile.
- Need Research pile gets Copy Research Prompt For This Pile.
- Triage and Setup pile buttons use the same robust copy helper.
- Row-level case buttons are renamed Inspect Case to discourage one-by-one processing.
- Adds Batch First note: use pile prompts first, open cases only for confusing/stuck/manual records.

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
