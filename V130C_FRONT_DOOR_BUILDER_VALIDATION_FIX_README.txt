v130c — Front Door Builder Validation Fix

Problem:
Front Door Builder reached the importer but failed all records:

Front Door Preview batch complete. 0 ready, 10 failed.
First issue: Validation failed.

Likely cause:
The builder payload used claim support metadata that may not be accepted by the current Sales Portal validator,
or the /go claim fields were not guaranteed in canonical claim_fields on the deployed site.

Fix:
- Ensures canonical claim fields exist:
  - go_slug
  - go_path
  - go_preview_version
  - outreach_asset_url
- Makes Front Door Builder claims use safer support category/key values:
  - supports_me_category = contact_me
  - supports_requirement_key = contact_me.clear_next_step
- Improves builder error reporting by surfacing returned details/errors when the importer provides them.
- Keeps business-table enum values safe:
  - marketing_clearance_status = cleared
  - recommended_package = standard

No new features.
No sending.
No SMS.
No AI calls.
No payment.
No scraping.
No domain purchasing.
No diagnosis import changes.
No /go renderer changes.
