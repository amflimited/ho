v094 Broad Local Service Scope Normalization

Purpose:
Fully normalize category/business_type as sourcing context, not a hard gate.

Rule:
- Indiana relevance is the broad gate.
- Exact city/service area is context only.
- Exact category/business_type is context only.
- Do not reject an Indiana-relevant local operator because it is not lawn care.

Explicitly allowed examples:
- cleaners
- handyman services
- photographers
- pressure washing
- junk removal
- mobile detailing
- pet grooming
- home repair
- small contractors
- landscaping
- tree work
- snow removal
- property maintenance
- local instructors/coaches
- event services

Reject/route away only if clearly:
- outside Indiana
- not a real/customer-facing business
- big chain/franchise where the offer makes no sense
- government/institutional
- pure online/non-local
- adult/regulated/high-risk
- duplicate/confused
- lacking any reasonable contact/setup path

Changes:
- Normalizes remaining prompt language from lawn-care-specific to local-service-specific.
- Adds explicit broad category rule to prompt contracts.
- Changes hardcoded category defaults from lawn_care to local_service where present.
- Updates Case File sourcing-context note.
- Includes an audit file showing remaining lines that contain “lawn” after normalization.

Scope:
- sales-portal-dashboard.php
- sales-research.php
- sales-business.php
- assets/css/admin.css
- V094_BROAD_LOCAL_SERVICE_SCOPE_AUDIT.txt

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
