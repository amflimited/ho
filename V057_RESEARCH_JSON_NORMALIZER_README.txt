v057 Research JSON Normalizer

Reason:
GPT/iPhone paste can produce smart quotes:
“business” instead of "business"

Strict JSON decoding rejects smart quotes with:
Invalid JSON: Syntax error

Change:
- Sales Research now normalizes smart double quotes to valid JSON quotes before json_decode.
- It also detects when a user accidentally pastes the prompt instead of the JSON answer.
- No schema changes.
- No database changes.
- No prospect-model changes.

Touched:
- sales-research.php
