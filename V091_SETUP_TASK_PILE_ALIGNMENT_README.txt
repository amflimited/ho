v091 Setup Task Pile Alignment

Problem:
The Work Queue showed separate piles:
- Proceed No Website: 1
- Ready For Setup: 20

But the Next card said:
- Run preview/contact setup: 21 records

That was mathematically correct but operationally unclear because setup is a combined task across two visible piles.

Fix:
- Defines Setup Task Pile = Proceed No Website + Ready For Setup.
- Next card now explains the exact breakdown.
- Setup prompt is generated from the exact combined setup task pile.
- Setup prompt summary shows the exact count and breakdown.
- Pile-level Copy Setup Prompt button copies the exact setup prompt directly.
- Adds collapsed Setup Task note explaining the combined pile.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
