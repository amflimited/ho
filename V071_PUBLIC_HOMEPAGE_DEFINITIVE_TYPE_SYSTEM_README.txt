v071 Public Homepage Definitive Type System

Scope:
- index.php only.

Purpose:
Fix remaining font inconsistencies across the public homepage.

Rule now enforced at the end of the homepage CSS:
- Hoosier display font:
  headings, section labels, nav, buttons, chips, guide boxes, card titles,
  plan tags, pricing numbers, overlay labels, close button, and staff label.
- Body font:
  paragraph copy, descriptions, FAQ answers, plan body text, list body copy,
  footer contact text.

This final CSS layer intentionally uses body.ho-home specificity and !important
to override earlier pasted CSS and prevent individual components from drifting.

Preserved:
- All copy.
- Pricing.
- Overlays.
- Layout.
- Admin and backend unchanged.

No database changes.
No preview.php.
No scraping/payment/outreach changes.
