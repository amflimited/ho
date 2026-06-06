v134 — Intake Module: Candidate Lead Preview

New page:
- /sales-intake.php

Adds:
- intake-model.php

What it does:
- Accepts pasted Source JSON shaped candidate_batch.batch_type = source_candidates and candidates[]
- Maps candidates into proposed business fields
- Runs preview-only dedupe and decision logic
- Groups candidates:
  new_business
  update_existing
  possible_duplicate
  needs_review
  reject
- Shows decision reasons and counts

No durable import/write in v134.
No sending.
No SMS.
No AI calls.
No scraping automation.
No payments.
No domain purchasing.
