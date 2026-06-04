v059 Candidate Triage Layer

Purpose:
Adds a lightweight interstitial stage before full research.

New workflow:
1. Import candidate batch.
2. Open a candidate.
3. Copy Candidate Triage Prompt.
4. Paste triage JSON into Sales Research.
5. Import triage result.
6. Only use full Business Refinement Prompt if candidate is research_ready.

Why:
The full refinement prompt is too expensive for candidates with no public web presence.
Triage answers only:
- Is this real/identifiable?
- Is it local/right category?
- Is there a usable contact path?
- Is there enough public surface for full research?
- Should we full_research, manual_check, or skip?

Scope:
- sales-business.php
- sales-research.php
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
