v110 Domain Verification Intake + Static Design Dashboard/Report Skeleton

Purpose:
Accepts domain verification results, marks packages with 10 verified domains as package_ready, and adds a skeleton preview for later materialization.

New behavior:
1. Domain verification intake:
- Accepts JSON shaped as:
  package_batch.batch_type = domain_availability_verification
  domain_results[]
- Stores:
  verified_domain_options_json
  package_status
  package_warnings_json
  package_next_step

2. Package readiness:
- If verified_domain_options count >= 10 and no blocking status, package_status becomes package_ready.
- If fewer than 10 domains are verified, status becomes manual_package_review unless result says domain_check_needed/package_blocked.
- Adds ho_preview_package_validation() helper.

3. Materialization skeleton:
- Defines intended:
  /go/{short_slug}
  /design/{short_slug}
  /report/{short_slug}
- Shows collapsed skeleton payload for package_ready records.
- Does not generate public pages.

What v110 does NOT do:
- no customer-facing dashboard/report generation
- no outreach
- no sending
- no SMS
- no AI calls
- no domain purchase
- no live domain API
- no upstream workflow changes

Next recommended step:
v111 — Static Design Dashboard / Sales Report Generator
