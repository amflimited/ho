v123b — Smart Quote JSON Normalizer

Purpose:
Fix Diagnosis Intake failures caused by pasted GPT JSON using curly/smart quotation marks.

Adds ho_diag_clean_pasted_json() to sales-diagnosis-workbench.php.

It normalizes:
- “ ” to "
- ‘ ’ to '
- « » to "
- en/em dashes to -
- non-breaking spaces to normal spaces
- UTF-8 BOM
- markdown fences
- surrounding text before/after JSON

Then it decodes the cleaned JSON.

This specifically addresses the recurring quotation-mark paste/import issue.

No workflow changes.
No outreach.
No sending.
No SMS.
No AI calls.
No payment.
