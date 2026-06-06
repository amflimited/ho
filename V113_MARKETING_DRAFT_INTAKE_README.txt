v113 Marketing Draft Intake + Manual Ready-To-Send Staging

Purpose:
Allows Marketing Desk to accept outreach draft JSON and stage records as Draft Ready / Ready To Send Manually.

New behavior:
1. Marketing draft intake:
- Accepts JSON shaped as:
  marketing_batch.batch_type = marketing_desk_outreach_drafts
  drafts[]
- Stores draft metadata as claims:
  - marketing_desk_status
  - contact_method
  - outreach_to
  - outreach_subject
  - outreach_body
  - outreach_asset_links_json
  - outreach_warnings_json
  - outreach_next_step

2. Routing:
- draft_ready when subject/body/contact exists and no warnings.
- paused_manual_review when contact, subject, body, or warnings require review.
- sent_later remains placeholder only.

3. UI:
- Shows Draft Ready cards.
- Each card shows business name, contact method/to, subject, body, and asset links.
- Adds Copy Subject and Copy Email Body buttons.
- No send button.

What v113 does NOT do:
- no automatic email sending
- no SMS
- no AI calls
- no CRM automation
- no domain purchasing
- no upstream workflow changes

Next recommended step:
v114 — Manual Send Checklist / Compliance Review Layer
