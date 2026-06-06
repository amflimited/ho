v100 Deterministic Copy Prompt Buttons

Problem:
Copy Prompt buttons still failed because the UI used a mix of anchors, inline onclick handlers, and mismatched prompt IDs.

Fix:
- Adds a single delegated .js-copy-prompt handler.
- Buttons use data-copy-target instead of brittle inline JavaScript.
- Adds navigator.clipboard path plus iPhone Safari fallback using a temporary textarea.
- Converts Next card prompt actions into real copy buttons when target is a prompt box.
- Converts pile prompt buttons into real copy buttons.
- Converts Case File primary copy buttons into real copy buttons.
- Adds Copied / Prompt Missing / Prompt Empty / Copy Failed feedback.
- Adds Case File prompt-id alias repair on page load.

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
