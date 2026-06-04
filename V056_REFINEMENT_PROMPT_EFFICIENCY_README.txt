v056 Refinement Prompt Efficiency

Reason:
The v055b prompt asked GPT to search too many detailed fields. That can lock up GPT or waste effort.

Change:
The Business Refinement Prompt now works in tiers:
1. Confirm identity and contact first.
2. Only inspect public surfaces that actually exist.
3. Stop when the system can decide whether the business is identifiable, contactable, and a possible Front Door prospect.

Key behavior:
- If no website appears to exist, the prompt tells GPT not to deeply inspect website-only fields.
- If Facebook exists, inspect Facebook only for broad activity/proof/contact signs.
- If Google exists, inspect Google only for broad review/photo/contact signs.
- Booking/payment fields are only checked if a relevant surface exists.
- Preferred claim count is limited so output stays useful and importable.

Scope:
- sales-business.php
- this note

No database changes.
No schema changes.
No upload/security changes.
No preview.php.
