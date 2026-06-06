v123a — Diagnosis Intake JSON Cleanup Fix

Problem:
The Diagnosis Intake could show “Syntax error” when pasted GPT output included markdown fences, notes, or other wrapper text.

Fix:
- Adds ho_diag_clean_pasted_json()
- Adds ho_diag_decode_pasted_json()
- Diagnosis intake now extracts the JSON object from pasted text before decoding.
- Error message now explains what to paste if cleanup still fails.

This does not change the product model, outreach, sending, payment, or workflow logic.
