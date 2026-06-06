v120 Materialization Identity Fix

Problem:
Generate Static Package could fail with:
  Materialization failed: Package record not found.

The page showed dummy/placeholder identity, which means the materialization button either submitted a fake id or the imported package result kept placeholder values such as:
- business_id: 0
- business_slug: existing-business-slug
- short_slug: shortslug/dummy

Fix:
- Materialization no longer relies only on business_id.
- It resolves the target by:
  1. business_id
  2. business_slug
  3. short_slug
- It blocks materialization for dummy/placeholder identities.
- Generate Static Package forms now submit id + business_slug + short_slug.
- Dummy packages show a disabled button requiring re-import.

Important:
If a package imported with dummy fields, re-run/re-import the package JSON with the real business_id and business_slug preserved from the prompt.

No sending.
No SMS.
No AI calls.
No domain purchase.
No upstream routing change.
