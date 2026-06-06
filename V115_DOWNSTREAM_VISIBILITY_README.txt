v115 Downstream Visibility + Cumulative Admin Links

Problem:
The downstream build existed, but it was not obvious from the admin panel. Also later packages were not fully cumulative enough for visibility/reference pages.

Fix:
- Adds a prominent Downstream Sales Assets card to sales-portal-dashboard.php.
- Adds direct buttons:
  - Open Preview Package Workbench
  - Open Marketing Desk
  - Package System
- Shows downstream counts:
  - Contact Ready
  - Package Needed
  - Domain Check
  - Package Ready
  - Ready For Marketing
  - Draft Ready
- Includes all downstream files in one cumulative package:
  - sales-preview-package-system.php
  - sales-preview-package-workbench.php
  - sales-marketing-desk.php
  - preview-package-model.php
  - preview-materializer.php
  - sales-portal-dashboard.php
  - assets/css/admin.css

No workflow logic change.
No outreach/send.
No SMS.
No AI calls.
No domain purchase.
