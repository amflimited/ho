v128 — Marketing Desk Simplification Around /go Link

Goal:
Refactor Marketing Desk around one customer-facing link:
  /go.php?slug={business_slug}

New Marketing Desk behavior:
- Outreach Draft Needed
- Draft Ready
- Paused / Manual Review
- Sent Later placeholder

Adds:
- one batch prompt for /go-ready businesses
- draft JSON intake
- Draft Ready cards with To, Subject, Body, /go link
- Copy To / Copy Subject / Copy Body
- compliance checklist
- no send button

Stores claims:
- marketing_desk_status
- contact_method
- outreach_to
- outreach_subject
- outreach_body
- outreach_asset_url
- outreach_warnings_json
- outreach_next_step

No automatic email sending.
No SMS.
No AI calls.
No CRM automation.
No domain purchasing.
No payment.
No diagnosis import changes.
No /go renderer changes.
