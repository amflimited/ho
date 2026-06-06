v111 Static Design Dashboard / Sales Report Generator

Purpose:
Generates static customer-facing preview artifacts for package_ready records:
- /go/{short_slug}/index.html
- /design/{short_slug}/index.html
- /report/{short_slug}/index.html

What it adds:
1. preview-materializer.php
- Renders hotlink package home.
- Renders Design Dashboard.
- Renders Sales Report.
- Writes static index.html files into /go, /design, and /report slug folders.
- Creates claim payload marking package_status=ready_for_marketing after successful generation.

2. Workbench materialization action:
- Package Ready records get Generate Static Package button.
- After generation, package moves to Ready For Marketing.
- Ready For Marketing section shows generated links.

3. Customer-facing static pages:
- Design Dashboard includes:
  - personalized business header
  - 10 website design options
  - 10 browser-font identity directions
  - 10 verified available domain options
  - placeholder choose buttons only
- Sales Report includes:
  - business snapshot headline
  - strength blocks
  - weakness blocks
  - recommendation blocks
  - no guaranteed lead claims
- /go/{slug} links to design and report.

What v111 does NOT do:
- no outreach
- no sending
- no SMS
- no AI calls
- no domain purchase
- no live domain API
- no upstream workflow changes
- no marketing desk automation

Next recommended step:
v112 — Marketing Desk Intake Scaffold
