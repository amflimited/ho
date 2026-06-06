v119 Downstream Runtime Signature Fix

Actual root cause found in uploaded backup error_log:

PHP Fatal error:
ho_salesportal_list_businesses_with_readiness(): Argument #1 ($status) must be of type ?string, array given

Bad call pattern:
ho_salesportal_list_businesses_with_readiness(['limit' => 500])
ho_salesportal_list_businesses_with_readiness(['limit' => 250])

Actual function signature in prospect-model.php:
ho_salesportal_list_businesses_with_readiness(?string $status = null, string $search = '')

Fix:
- sales-preview-package-workbench.php now calls ho_salesportal_list_businesses_with_readiness(null, '')
- sales-marketing-desk.php now calls ho_salesportal_list_businesses_with_readiness(null, '')
- sales-portal-dashboard.php downstream counts now call ho_salesportal_list_businesses_with_readiness(null, '')

This is a runtime repair only.
No feature changes.
No workflow changes.
No router hacks.
No sending.
No SMS.
No AI calls.
No domain purchase.

Expected after install:
- /sales-preview-package-workbench.php should no longer blank/fatal from this TypeError.
- /sales-marketing-desk.php should no longer blank/fatal from this TypeError.
- dashboard downstream counts should not fatal from this TypeError.
