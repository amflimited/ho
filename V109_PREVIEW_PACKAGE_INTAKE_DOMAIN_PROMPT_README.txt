v109 Preview Package Intake + Domain Candidate Check Prompt

Purpose:
Allows Preview Package Workbench to accept package-generation JSON and stage packages for domain verification using existing claims/evidence import style.

New behavior:
1. Package intake:
- Accepts JSON shaped like package_batch + packages[].
- Converts each package to existing business import payload.
- Stores package metadata as claims:
  - package_status
  - short_slug
  - hotlink_path
  - design_dashboard_path
  - sales_report_path
  - recommended_template_key
  - web_design_options_json
  - logo_options_json
  - domain_candidates_json
  - verified_domain_options_json
  - sales_report_json
  - package_warnings_json
  - package_next_step

2. Domain check prompt:
- When package_status=domain_check_needed records exist, active prompt becomes Domain Availability Check Prompt.
- Prompt includes domain candidates for each package.
- It asks for up to/exactly 10 proven available domains where possible.
- It explicitly says not to claim availability unless checked.
- It does not purchase domains.

3. Status routing:
- package_status inferred from stored claims.
- candidate domains alone keep the package at domain_check_needed.
- 10 verified domains move package to package_ready in payload conversion.

What v109 does NOT do:
- no customer-facing dashboard/report generation
- no outreach
- no sending
- no SMS
- no AI calls
- no domain purchasing
- no live domain API
- no schema changes
- no upstream lead/research/setup routing changes

Next recommended step:
v110 — Domain Verification Intake + Static Design Dashboard/Report Skeleton
