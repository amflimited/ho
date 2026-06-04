v055 Business Refinement Prompt

Scope:
- Adds a per-business refinement prompt to Business View.
- Uses existing business, evidence, and claims to create a paste-ready GPT prompt.
- The prompt asks GPT to confirm known truths, find missing public details, and explicitly mark things as missing when they do not appear to exist.
- Returned JSON still goes through the existing Sales Research validate/import flow.

Touched:
- prospect-model.php
- sales-business.php
- sales-research.php
- assets/css/admin.css

Not included:
- no database schema changes
- no scraping
- no preview.php
- no payment
- no outreach automation
- no security changes

Workflow:
1. Import rough business.
2. Open Business View.
3. Copy Business Refinement Prompt.
4. Run GPT.
5. Paste returned JSON into Sales Research.
6. Validate and import.
7. Readiness/options auto-process from v054.
