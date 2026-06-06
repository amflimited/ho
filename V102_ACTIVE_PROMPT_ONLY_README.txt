v102 Active Prompt Only

Based on:
- v100 Deterministic Copy Prompt Buttons
Not based on:
- v101 Nearest Textarea Copy

Problem:
The page showed empty Bulk Triage and Setup prompt boxes prominently, making copy look broken when there was no actual prompt content to copy.

Fix:
- Replaces the old "Copy When Needed" style prompt block with one Active Prompt section.
- Active Prompt follows the true next action:
  1. Need Triage -> Bulk Triage Prompt
  2. Need Research -> Research Prompt For Need Research Pile
  3. Setup candidates -> Preview / Contact Setup Prompt
- Empty triage/setup prompt statuses are demoted into a collapsed "Unavailable Right Now" drawer.
- Copy button appears only when a real textarea exists.
- Next card copy action points to the active prompt textarea.
- Keeps v100 deterministic copy system.

Expected behavior:
- If Need Research has records and Triage/Setup are empty, the visible prompt should be Research Prompt, not empty triage/setup boxes.
- Empty prompts should not dominate the page.
- Copy Active Prompt should copy the visible active prompt.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
