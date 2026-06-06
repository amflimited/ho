v104 Research Completion Routing

Problem:
Research output was accepted, but records stayed in Need Research because the import did not mark the research step as complete and old website/manual-check signals kept routing the same records back into the Need Research pile.

Fix:
- Research/refinement imports now write:
  - research_completed = yes
  - setup_path = research_completed_ready_for_setup, unless GPT provides a stronger setup_path/blocked result
- Queue routing reads research_completed.
- research_completed + blocked/do_not_proceed/bad_fit/skip -> Blocked / Skip
- research_completed + ready_to_contact/contact_ready/contactable -> Ready To Contact
- research_completed + needs_more_research/still_need_research -> Need Research
- research_completed default -> Ready For Setup
- research_completed_ready_for_setup is not treated as an already-run setup result, so it can enter setup prompt candidates.

Expected:
After a bulk_business_refinement import is accepted, those records should leave Need Research unless the research output explicitly says they still need more research.

Scope:
- sales-portal-dashboard.php
- assets/css/admin.css

No schema changes.
No scraping.
No preview.php.
No payment.
No outreach automation.
No prompt format changes.
