v095 Action-First Case + Queue Cleanup

Purpose:
Fix screenshots showing that Case File exposes too much raw machinery and still treats Candidate Triage as the main action after a record has moved beyond triage.

Fixes:
1. Case File primary action follows state:
- Need Research -> Copy Research Prompt
- Need Triage -> Copy Triage Prompt
- Ready For Setup -> Copy Setup Prompt
- Ready To Contact -> Review Contact Path
- Blocked / Skip -> Review Block Reason

2. Candidate Triage Prompt is collapsed unless the case state is Need Triage.

3. Business Refinement Prompt opens automatically when the case state is Need Research.

4. Preview/Contact Setup Prompt opens automatically only when the case state is Ready For Setup.

5. Raw sections are collapsed:
- Workflow Position
- Best Signals
- Main Problem
- Claims by Category
- Find Me/Fix Me/Trust Me/Show Me/Contact Me
- Raw Claims
- Evidence Sources
- Requirement/ME Scores
- Preview Readiness / Option Assignment
- table-heavy sections

6. Work Queue cleanup:
- Prompt tools are collapsed by default.
- Low-priority truth/context notes are visually demoted.
- Queue buttons are clearer for Need Research.

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
