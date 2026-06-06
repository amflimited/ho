v124 — Command Center State Audit

Goal:
Create a read-only truth audit that shows what the database actually believes across:
1. upstream lead/research/setup/contact pipeline
2. new sales-asset pipeline

New page:
- /sales-command-center-audit.php

Added:
- command-center-model.php
- read-only Next Move engine
- upstream pipeline buckets
- sales asset pipeline buckets
- data-problem buckets
- record lists behind every count
- debug/source-truth section
- dashboard link to audit page

No writes:
- no imports
- no updates
- no claim writes
- no deletes
- no outreach
- no sending
- no payment

Acceptance check:
Open /sales-command-center-audit.php and confirm:
1. Contact Ready count
2. Contact Ready without diagnosis count
3. Diagnosis Ready count
4. Diagnosis Ready without /go preview count
5. Data problem counts
6. Single recommended next move
7. Record list behind the next move
8. Debug source fields
