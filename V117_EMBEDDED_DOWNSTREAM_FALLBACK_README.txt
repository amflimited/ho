v117 Embedded Downstream Workbench Fallback

Problem:
Standalone /sales-preview-package-workbench.php returned 404, which means the uploader/server did not create the new page file even though dashboard edits deployed.

Fix:
This patch depends only on existing files:
- sales-portal-dashboard.php
- assets/css/admin.css

It embeds downstream views inside the existing dashboard route:
- /sales-portal-dashboard.php?view=preview_package_workbench
- /sales-portal-dashboard.php?view=marketing_desk
- /sales-portal-dashboard.php?view=package_system

The main dashboard buttons now point to those embedded routes instead of missing standalone files.

No sending.
No SMS.
No AI calls.
No domain purchase.
No upstream workflow change.
