v118 Hard Dashboard View Router

Problem:
The v117 query fallback still rendered the normal Work Queue at:
  /sales-portal-dashboard.php?view=preview_package_workbench

Fix:
v118 inserts a hard router before the normal dashboard render:
- preview_package_workbench
- marketing_desk
- package_system

If a valid view is present, it renders only that downstream view and returns before the regular dashboard.

Only existing files are touched:
- sales-portal-dashboard.php
- assets/css/admin.css

No new route files.
No workflow change.
No sending.
No SMS.
No AI calls.
No domain purchase.
