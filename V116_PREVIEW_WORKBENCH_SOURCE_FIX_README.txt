v116 Preview Workbench Source Fix

Problem:
The main dashboard showed Contact Ready records, but /sales-preview-package-workbench.php could still appear empty.

Cause:
The workbench was not using robust enough source detection. It depended on a weaker fallback instead of treating blank package_status + contact-ready signals as Package Needed.

Fix:
- Adds ho_preview_package_truthy_contact_ready().
- Adds ho_preview_package_partition_businesses().
- Workbench now uses the shared partition helper.
- Dashboard downstream counts now use the same helper.
- Workbench shows a Workbench Source card:
  - Total Loaded
  - Package Needed
  - Already Packaged
  - Ignored Here

Expected:
If dashboard Contact Ready is 22, the workbench Package Needed count should now also show those records unless they already have package_status claims.

No workflow logic change.
No outreach/send.
No SMS.
No AI calls.
No domain purchase.
