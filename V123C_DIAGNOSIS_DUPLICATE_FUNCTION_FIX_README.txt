v123c — Diagnosis Duplicate Function Fix

Current error_log showed:
PHP Fatal error: Cannot redeclare function ho_diag_clean_pasted_json()
previously declared in sales-diagnosis-workbench.php, redeclared in diagnosis-model.php.

Fix:
- diagnosis-model.php now owns ho_diag_clean_pasted_json().
- sales-diagnosis-workbench.php no longer declares a duplicate copy.
- The cleaner is guarded with function_exists().
- Smart/curly quote normalization is preserved.

Test after install:
1. Open /sales-diagnosis-workbench.php
2. It should not blank page.
3. Paste the previous JSON result again.
