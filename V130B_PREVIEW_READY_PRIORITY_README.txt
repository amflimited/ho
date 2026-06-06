v130b — Preview Ready Priority Reconciliation

Problem after v130a:
Dashboard still showed:
  Run Diagnosis Batch — 16 affected

Diagnosis Workbench showed:
  0 Diagnosis Needed
  10 Preview Ready

Cause:
Command Center still did not treat front_door_preview_status = preview_ready as enough to enter the /go builder queue.
It also prioritized remaining diagnosis work before moving already-preview-ready records forward.

Fix:
- Adds front-door preview status helpers.
- Treats front_door_preview_status = preview_ready as diagnosis/preview ready for Command Center buckets.
- Adds preview-ready records without go_path/go_slug into:
  diagnosis_ready_without_go_preview
  go_page_missing
- Prioritizes Build /go Front Door Preview Pages before Run Diagnosis Batch when preview-ready records exist.
- Adds hard CSS fix for Diagnosis Workbench stat labels.

Expected after install:
Dashboard next move should become:
  Build /go Front Door Preview Pages
when Diagnosis Workbench shows Preview Ready > 0 and Diagnosis Needed = 0.

No new features.
No sending.
No SMS.
No AI calls.
No payment.
No scraping.
No domain purchasing.
No diagnosis import changes.
No /go renderer changes.
