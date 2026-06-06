v076 Unlimited Batch Import Fix

Problem:
Candidate import failed when GPT returned more than 25 records.

Decision:
Do not truncate. Do not fail. Process as many records as GPT returns.

Changes:
- Removes hard 25-record batch import failures.
- Candidate batches can include more than 25 records.
- Triage batches can include more than 25 records.
- Setup batches can include more than 25 records.
- Generic batch arrays can include more than 25 records.
- Duplicate guard from v075 remains active:
  - exact duplicates are skipped,
  - possible duplicates are flagged.

Note:
The lead-generation prompt can still ask GPT for 25 because that is practical for one pass, but the importer no longer cares if GPT returns more.

Scope:
- sales-research.php
- sales-portal-dashboard.php

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
