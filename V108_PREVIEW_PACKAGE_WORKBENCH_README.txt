v108 Preview Package Workbench

Purpose:
Creates the admin workbench for Contact Ready -> Preview Package manufacturing.

New page:
- /sales-preview-package-workbench.php

What it does:
- Loads Contact Ready businesses from existing sales records.
- Groups records into package piles:
  - Package Needed
  - Package Drafted
  - Domain Check Needed
  - Package Ready
  - Ready For Marketing
  - Manual Package Review
  - Package Blocked
- Generates one active copy/paste GPT prompt for Package Needed records.
- Includes locked registries from v107:
  - 10 website design styles
  - 10 browser-font identity/logo directions
  - slug rules
  - domain candidate rules
  - sales report block registry
  - package JSON contract
  - readiness criteria

Prompt requirements:
- Select/personalize all 10 locked website design options per business.
- Select/personalize all 10 locked identity/logo directions per business.
- Generate shortest safe hotlink slug candidates.
- Generate 20 domain candidates only.
- Do not claim availability.
- Generate personalized sales report using block keys.
- Set package_status=domain_check_needed unless blocked/manual review.
- Return ONLY valid JSON.

What v108 does NOT do:
- no package import
- no customer-facing page generation
- no domain checking
- no domain purchasing
- no outreach
- no sending
- no schema changes
- no changes to upstream lead/research/setup/contact-ready routing

Next recommended step:
v109 — Preview Package Intake + Domain Candidate Check Prompt
