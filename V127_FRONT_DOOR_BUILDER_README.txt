v127 — Front Door Builder / Preview State Writer

Goal:
Create the admin bridge that takes diagnosis-ready businesses and marks them as having a usable /go preview path.

New page:
- /sales-front-door-builder.php

What it does:
- Loads diagnosis-ready records without go_slug/go_path.
- Assigns:
  - front_door_preview_status = go_ready
  - go_slug = existing business_slug
  - go_path = /go.php?slug={business_slug}
  - go_preview_version = front-door-preview-v126
  - outreach_asset_url = /go.php?slug={business_slug}

It does not require GPT.
The customer-facing page remains dynamic through /go.php.

Canonical fields added:
- go_slug
- go_path
- go_preview_version
- outreach_asset_url

No:
- no sending
- no SMS
- no AI calls
- no payment
- no outreach automation
- no domain purchasing
- no static file generation
- no 10-template dashboard
- no post-sale intake
- no diagnosis import changes
