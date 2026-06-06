v121 Dashboard Hub Restore

Problem:
v120 fixed materialization identity, but it shipped a dashboard from a bad base and re-broke the downstream hub:
- counts showed 0
- button behavior was unreliable

Fix:
- Touches only:
  - sales-portal-dashboard.php
  - assets/css/admin.css
- Removes unreliable downstream count math from the dashboard hub.
- Restores buttons to actual standalone routes:
  - /sales-preview-package-workbench.php
  - /sales-marketing-desk.php
  - /sales-preview-package-system.php
- Keeps v120 workbench/materialization fixes untouched.

Important:
The dashboard hub is now navigation only. Live state belongs on the actual downstream pages.
