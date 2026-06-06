v097 Mobile Overflow Fix

Problem:
Case File rendered wider than the iPhone viewport. The page looked cut in half because prompt textareas / code / tables created horizontal overflow.

Fix:
- Hard-locks admin pages to max-width: 100vw.
- Disables body/page horizontal overflow.
- Forces cards to fit the viewport.
- Forces textareas, pre, code, prompt boxes, collapsed bodies, and tables to stay within cards.
- Long JSON/prompt/table values now wrap or scroll internally instead of widening the whole page.
- Adds inline Case File CSS to defeat stale Safari/admin.css cache.

Scope:
- sales-business.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
