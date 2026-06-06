v136 — Sales Prep Module: Combined Diagnosis + Outreach Draft

New page:
- /sales-prep.php

Adds:
- prep-model.php

What it does:
- Loads prep-eligible/contact-ready businesses that do not already have complete SalesPrep/outreach draft data
- Computes preview URL as /go.php?slug={business_slug}
- Generates one combined Sales Prep prompt
- Prompt asks for:
  diagnosis keys
  personalization_summary
  outreach_to
  outreach_contact_method
  outreach_subject
  outreach_body
  warnings
  next_step = send_tray

No Front Door Builder.
No go_path/go_slug writes required.
No SalesPrep durable writes in v136.
No sending.
No SMS.
No AI calls.
No scraping automation.
No payments.
No domain purchasing.
