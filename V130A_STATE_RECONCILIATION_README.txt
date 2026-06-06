v130a — Command Center / Diagnosis Workbench State Reconciliation

Problem:
Dashboard said:
  Next Move: Run Diagnosis Batch
  count affected: 16

But Diagnosis Workbench said:
  Diagnosis Needed: 0
  Preview Ready: 10

Cause:
Command Center classified old Contact Ready records as needing diagnosis using:
  contact_ready && !has_diagnosis

That ignored downstream sales-asset states already present on records, such as:
- front_door_preview_status = preview_ready/go_ready
- go_slug
- go_path
- outreach_asset_url
- package_status
- marketing_desk_status
- outreach_subject/body

Fix:
- Adds ho_command_has_downstream_sales_asset_state()
- Adds ho_command_needs_diagnosis()
- Contact Ready without Diagnosis now excludes records that already have diagnosis/preview/go/marketing state.
- Next Move should advance to Build /go Front Door Preview Pages when diagnosis workbench is complete.
- Adds CSS stat readability fix for labels like “0Diagnosis Needed”.

No new features.
No sending.
No SMS.
No AI calls.
No payment.
No scraping.
No domain purchasing.
No diagnosis import changes.
No /go renderer changes.
