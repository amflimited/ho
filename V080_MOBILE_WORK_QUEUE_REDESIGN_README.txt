v080 Mobile Work Queue Redesign Test

Purpose:
Shift the admin experience away from page-navigation and toward mobile work-state navigation.

Applied model:
- Dispatch Board: what needs work now
- Intake Desk: one place to paste GPT output and see an operational receipt
- Work Queues: records grouped by current job/state
- Tools: old pages demoted to lower-priority utilities
- Case File: Business View starts with the current decision and primary prompts

Main dashboard changes:
- Adds Dispatch Board with queue counts and suggested next move.
- Replaces scattered paste framing with Intake Desk.
- Shows receipt counts: received, created, updated, skipped, failed.
- Moves prompt tools into collapsible current-tool sections.
- Replaces generic prospect list with work queues:
  Need Triage, Need Research, Proceed No Website, Ready For Setup, Ready To Contact, Blocked / Skip.
- Demotes old filters/page links into Tools.

Business View changes:
- Adds Case File framing.
- Shows next decision first.
- Shows clearance/claims/evidence/flags as compact status blocks.
- Keeps existing triage, setup, and refinement prompt behavior.

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
