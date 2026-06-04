v064 Lead Generation Prompt

Purpose:
Updates the generic prompt on sales-research.php so it generates new candidate leads instead of asking for deep business research.

Reason:
The workflow now starts with lead generation/candidate sourcing, then bulk triage, then full research only for worthy prospects.

Changes:
- The promptBox now asks GPT for up to 25 lawn-care/exterior-service candidate businesses.
- Output structure is source_batch + candidates.
- Candidate JSON is directly pasteable into Prospects → Paste Results Here or the fallback Sales Research importer.
- Page copy is updated to describe lead generation.

No database changes.
No schema changes.
No business logic changes.
