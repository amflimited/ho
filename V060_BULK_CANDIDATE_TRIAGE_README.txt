v060 Bulk Candidate Triage

Purpose:
Adds a bulk triage prompt to the main Prospects page so the operator does not need to open each business one at a time.

New Prospects behavior:
- Shows Bulk Candidate Triage prompt.
- Prompt includes the next 25 available candidate/prospect records.
- GPT classifies each into exactly one of three categories:
  1. research_with_website
  2. proceed_no_website
  3. do_not_proceed

Research import behavior:
- Sales Research accepts this triage_results JSON.
- research_with_website maps to warm_clear/full_research.
- proceed_no_website maps to hold/simple_front_door_path.
- do_not_proceed maps to skip.

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
