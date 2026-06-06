v075 Lead Generation Exclusion Guard

Purpose:
Prevents lead generation and candidate import from filling the system with duplicates.

Changes:
1. sales-research.php lead-generation prompt now includes a Known Businesses to Exclude JSON list.
2. Exclusion list is compact:
   - business name
   - slug
   - city/state
   - identifiers such as website, Facebook, Google profile, phone, email, address
3. Prompt tells GPT to exclude close matches rather than duplicate them.
4. prospect-model.php adds duplicate helpers:
   - same slug
   - same normalized business name + city
   - same website/Facebook/Google/phone/email/address claims
   - similar business name + city possible-duplicate warning
5. Sales Research and Prospects paste/import now:
   - block validation on exact duplicates
   - skip exact duplicates during import
   - flag possible duplicates for review

Scope:
- prospect-model.php
- sales-research.php
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
