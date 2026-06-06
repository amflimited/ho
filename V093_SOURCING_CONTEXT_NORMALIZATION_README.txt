v093 Sourcing Context Normalization

Purpose:
Make business category behave like city/service area: useful sourcing context, not a hard eligibility gate.

User rule:
- Indiana relevance matters.
- Exact city/service-area confirmation does not.
- Category/business_type also should not be a hard gate because Hoosier Online will not stay lawn-care-only.

Changes:
1. Prompt scope:
- Adds Category rule to triage/setup prompt language.
- The current category is sourcing context only.
- Do not reject a business merely because it is not pure lawn care.
- Keep and route Indiana-relevant local service businesses, including adjacent categories like landscaping, outdoor services, property maintenance, tree work, snow removal, etc.

2. Setup prompt:
- Reinforces that Indiana is the only broad location gate.
- Adds category/business_type as sourcing context only.
- Removes hard “lawn care” gate language where possible.

3. Case File:
- Adds collapsed Sourcing Context note.
- Explains that city, service area, and category describe how the record was sourced, not hard gates.

4. UI copy:
- Replaces several visible “lawn care business/operator/services” phrases with broader “local service business/operator/services” language.

Scope:
- sales-portal-dashboard.php
- sales-business.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No import format changes.
