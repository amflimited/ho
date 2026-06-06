v126 — Front Door Preview Renderer

Goal:
Build the actual customer-facing pre-sale page model:
  /go.php?slug={business_slug}

Adds:
- front-door-preview-model.php
- go.php
- assets/css/front-door.css

What /go.php does:
- Loads a business by business_slug.
- Reads diagnosis claims.
- Assembles the page from diagnosis registries.
- Renders:
  - personalized intro
  - what we noticed
  - strengths
  - weaknesses
  - recommendations
  - exactly 3 preview directions when possible
  - simple offer
  - CTA

No:
- no sending
- no SMS
- no AI calls
- no payment
- no outreach automation
- no domain purchase
- no static file generation
- no 10-template dashboard
- no post-sale intake
- no diagnosis import changes

Test:
Open:
  /go.php?slug={business_slug}

For a diagnosis-ready business, it should show a full Front Door Preview.
If not ready, it shows a friendly “Preview not ready” page instead of blank page.
