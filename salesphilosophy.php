<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$sales = json_decode(<<<'JSON'
{
  "schema": "hoosier_online.sales_philosophy.v3",
  "version": "HO-SALES-PHILOSOPHY-030",
  "title": "Pre-Researched Front Door Preview Sales System",
  "status": "working_locked",
  "core_thesis": "Hoosier Online sells by reducing uncertainty before the first conversation. The prospect should not receive a vague pitch. They should receive a custom Front Door Preview that makes the problem visible, makes the solution concrete, and lets them make guided choices before buying.",
  "operating_principle": "Do not sell from a blank page. Research first, personalize the link, show what is working, show where customers may be getting stuck, show the recommended Front Door, let the prospect choose, then build fast from the selected package and modules.",
  "successful_sale_definition": "A successful sale means the customer saw the preview, believed it was specific to them, understood the problem, trusted the fix, selected options, paid or requested invoice, and gave enough information to start the build quickly.",
  "sales_machine_goal": "The research, outreach, preview page, choices, telemetry, and build handoff should all point toward one outcome: make the sale and first build feel like they already started before the customer paid.",
  "business_offer": {
    "name": "Business Front Door",
    "plain_pitch": "You do the work. Hoosier Online handles the online front door: customers can find you, trust you, see what you offer, contact you, request work, book time, and pay you.",
    "products": [
      {
        "name": "Standard Front Door",
        "price": "$499",
        "included": "setup plus 1 year of service",
        "renewal": "$250/year or $25/month after year one"
      },
      {
        "name": "Managed Front Door",
        "price": "$999",
        "included": "setup plus 3 months of managed service",
        "renewal": "$250/quarter or $750/year after the first 3 months"
      }
    ],
    "speed_positioning": "Because the preview pre-selects the likely package, design direction, and feature set, Hoosier Online can move from purchase to first build quickly, often in less than 48 hours once the customer provides the needed information. No instant delivery guarantee."
  },
  "data_philosophy": {
    "summary": "Pre-research is not only for better outreach. It should power the outreach email, the preview page, the close, and the eventual build handoff.",
    "three_jobs": [
      "Make the outreach feel specific enough to earn the click.",
      "Make the preview feel researched enough to earn trust.",
      "Make the selected choices complete enough to start the build quickly."
    ],
    "preview_rule": "Previews should be structurally consistent but data-personalized. The shell can stay the same; the business data, strengths, weaknesses, current customer path, recommendation, and selected defaults make it feel custom."
  },
  "prospect_master_record": {
    "purpose": "Every business should eventually have one master record that moves from prospect to preview to sale to build.",
    "sections": [
      "business identity",
      "current customer path",
      "strengths",
      "weaknesses",
      "Me scores",
      "primary sales angle",
      "recommended package",
      "recommended design",
      "recommended features",
      "outreach activity",
      "preview telemetry",
      "submitted choices",
      "payment/sale status",
      "build modules",
      "missing inputs",
      "launch URL"
    ],
    "schema_example": {
      "business": {
        "name": "",
        "type": "",
        "location": "",
        "service_area": "",
        "phone": "",
        "email": "",
        "owner_name": "",
        "website": "",
        "facebook": "",
        "google_profile": "",
        "instagram": ""
      },
      "research": {
        "customer_path": "",
        "current_landing_place": "",
        "strength_tags": [],
        "weakness_tags": [],
        "strength_notes": [],
        "weakness_notes": [],
        "me_scores": {
          "find_me": 0,
          "trust_me": 0,
          "contact_me": 0,
          "show_me": 0,
          "book_me": 0,
          "pay_me": 0,
          "fix_me": 0
        },
        "primary_sales_angle": "",
        "top_leaks": [],
        "customer_consequence": ""
      },
      "recommendation": {
        "package": "standard",
        "design_direction": "",
        "features": [],
        "reason": ""
      },
      "preview": {
        "slug": "",
        "url": "",
        "status": "created",
        "created_at": "",
        "last_viewed_at": "",
        "events": []
      },
      "outreach": {
        "channel": "",
        "message_version": "",
        "sent_at": "",
        "followups": []
      },
      "choices": {
        "selected_package": "",
        "selected_design": "",
        "selected_features": [],
        "business_goal": "",
        "notes": ""
      },
      "sale": {
        "status": "",
        "paid_at": "",
        "amount": "",
        "invoice_id": ""
      },
      "build": {
        "status": "",
        "modules": [],
        "missing_inputs": [],
        "launch_url": ""
      }
    }
  },
  "research_data_requirements": {
    "identity_data": [
      "Business name",
      "Business type/category",
      "Location",
      "Service area",
      "Phone",
      "Email/contact method",
      "Owner/contact name if public",
      "Current website",
      "Facebook page",
      "Google Business Profile",
      "Instagram/TikTok if relevant"
    ],
    "current_customer_path": [
      "Where a customer likely lands first",
      "Best current contact method",
      "Current call-to-action",
      "Whether services are clear",
      "Whether work/photos are visible",
      "Whether quote/request is structured",
      "Whether booking exists",
      "Whether payment/deposit exists",
      "Whether customer has to use Facebook Messenger",
      "Whether customer has to scroll posts to understand the business"
    ],
    "strength_tags": [
      "active_business",
      "clear_service_type",
      "visible_phone_number",
      "good_reviews",
      "good_photos",
      "good_before_after_examples",
      "strong_facebook_activity",
      "recognizable_brand",
      "clear_service_area",
      "good_local_reputation",
      "good_existing_customer_interest",
      "good_response_signals",
      "good_brand_name",
      "good_offer_clarity"
    ],
    "weakness_tags": [
      "facebook_only",
      "no_real_website",
      "unclear_services",
      "no_request_form",
      "no_booking_path",
      "no_payment_path",
      "weak_photos",
      "no_work_examples",
      "no_service_area",
      "bad_mobile_experience",
      "broken_links",
      "old_hours_or_phone",
      "too_much_scrolling",
      "messy_customer_path",
      "no_single_place_to_send_customers",
      "domain_or_login_confusion",
      "outdated_branding",
      "weak_trust_signals",
      "no_review_path",
      "no_confirmation_path"
    ],
    "weakness_translation_rule": "Do not only store the weakness tag. Store the customer consequence. Example: no_request_form means customers have to manually message and explain what they need, creating friction before the business can respond."
  },
  "preview_personalization_fields": {
    "most_important_for_outreach": [
      "business name",
      "primary weakness in plain English",
      "one strength if possible",
      "preview link",
      "business type"
    ],
    "most_important_for_preview": [
      "business name",
      "current customer path",
      "strength notes",
      "top 2–3 leaks",
      "Me score summary",
      "recommended package",
      "recommended design",
      "recommended features",
      "price",
      "next step"
    ],
    "most_important_for_fulfillment": [
      "selected package",
      "selected design",
      "selected features",
      "business type",
      "service list",
      "service area",
      "contact info",
      "photos/assets",
      "payment/booking links",
      "form destination",
      "missing inputs"
    ]
  },
  "prospect_workflow": {
    "one_line": "Find active local businesses, inspect their current customer path, score the Me categories, identify strengths and leaks, generate a custom Front Door Preview, send a short message, let the preview page handle diagnosis/choices/pricing, then build from the selected package and modules.",
    "ideal_prospect_profile": "Good enough to pay, rough enough to need us, simple enough to build quickly.",
    "steps": [
      {
        "step": "Choose one target lane",
        "purpose": "Research one vertical at a time so the weakness patterns repeat.",
        "details": [
          "Lawn care / landscaping",
          "House cleaning",
          "Handyman / small construction",
          "Pressure washing",
          "Mobile detailing",
          "Small food vendor",
          "Local shop / product seller"
        ]
      },
      {
        "step": "Build the prospect list",
        "purpose": "Create a list of businesses that can receive personalized preview links.",
        "sources": [
          "Google Maps",
          "Facebook local search",
          "local Facebook groups",
          "Chamber/business directories",
          "yard signs / trucks / word of mouth",
          "Craigslist / Marketplace service posts",
          "local event/vendor pages",
          "referrals"
        ],
        "fields": [
          "Business name",
          "Business type",
          "Location/service area",
          "Current website",
          "Facebook",
          "Google Business Profile",
          "Phone",
          "Email/contact method",
          "Owner name if public",
          "Prospect status",
          "Preview URL",
          "Primary weakness",
          "Recommended package",
          "Notes"
        ]
      },
      {
        "step": "Quick qualify",
        "purpose": "Avoid wasting time auditing businesses that are not a fit.",
        "good_signals": [
          "They appear active.",
          "They probably sell real work/products.",
          "They have some public presence.",
          "They are not already polished online.",
          "They likely have enough revenue to pay $499 or $999.",
          "They serve local customers.",
          "Their customer path matters."
        ],
        "bad_signals": [
          "Completely inactive.",
          "No signs of current work.",
          "Extremely tiny hobby with no money.",
          "Already has a polished site/system.",
          "Franchise/corporate business.",
          "Hard to identify what they sell.",
          "High-risk or sketchy business.",
          "No public way to contact them."
        ],
        "decision": [
          "Research",
          "Skip",
          "Maybe Later"
        ]
      },
      {
        "step": "Inspect the customer path",
        "purpose": "View the business the way a customer would.",
        "guiding_question": "If I wanted to hire or buy from this business today, what would I do?",
        "inspect": [
          "Google result",
          "Google Business Profile",
          "Facebook page",
          "website if present",
          "Instagram/TikTok if relevant",
          "contact buttons",
          "reviews/photos",
          "service/product information",
          "booking/request/payment path",
          "mobile experience"
        ]
      },
      {
        "step": "Score the Me categories",
        "purpose": "Create a consistent diagnosis for the preview.",
        "scale": {
          "0": "missing / broken",
          "1": "present but poor",
          "2": "usable but weak",
          "3": "acceptable",
          "4": "strong",
          "5": "excellent"
        },
        "note": "For Fix Me, a high score means more cleanup is needed, not better."
      },
      {
        "step": "Identify strengths first",
        "purpose": "Make the preview feel fair instead of insulting.",
        "strength_tags": [
          "active_business",
          "clear_service_type",
          "visible_phone_number",
          "good_reviews",
          "good_photos",
          "good_before_after_examples",
          "strong_facebook_activity",
          "recognizable_brand",
          "clear_service_area",
          "good_local_reputation",
          "good_existing_customer_interest"
        ]
      },
      {
        "step": "Tag weaknesses",
        "purpose": "Use reusable weakness tags so previews can be generated quickly.",
        "weakness_tags": [
          "hard_to_find",
          "no_real_website",
          "facebook_only",
          "bad_mobile_experience",
          "unclear_services",
          "no_service_area",
          "no_clear_contact_path",
          "no_request_form",
          "no_booking_path",
          "no_payment_path",
          "old_hours_or_phone",
          "weak_photos",
          "no_work_examples",
          "outdated_branding",
          "broken_links",
          "messy_customer_path",
          "domain_or_login_confusion",
          "too_much_scrolling",
          "no_single_place_to_send_customers"
        ]
      },
      {
        "step": "Choose the primary sales angle",
        "purpose": "Do not pitch everything. Lead with the strongest customer-path leak.",
        "angles": [
          "Find-first: Customers may have a hard time finding one clear place for your business online.",
          "Trust-first: Your business looks active, but the online presentation may not match the quality of the work.",
          "Contact-first: People can find you, but the next step is not as clear as it should be.",
          "Show-first: You have work worth showing, but customers do not have one clean place to see it.",
          "Book-first: Customers have no simple way to request a job, estimate, appointment, or time.",
          "Pay-first: There is no clean payment or deposit path for customers who are ready.",
          "Fix-first: The business appears active, but the online path is scattered and needs cleaned up."
        ]
      },
      {
        "step": "Recommend package",
        "purpose": "Do not ask the prospect to decide cold. Recommend the most likely fit.",
        "standard_when": [
          "The business needs a clean Front Door.",
          "The scope is simple.",
          "They do not need frequent updates.",
          "They mostly need find/trust/contact/show.",
          "They are price-sensitive."
        ],
        "managed_when": [
          "They have lots of services/products/photos.",
          "Their offers change often.",
          "They need more cleanup.",
          "They need more hand-holding.",
          "They have a bigger or messier business.",
          "They need more form/workflow refinement."
        ],
        "default": "Recommend Standard unless Managed is obviously justified."
      },
      {
        "step": "Select design direction",
        "purpose": "Preselect a visual direction to reduce decision friction.",
        "directions": [
          "Clean Local Pro — cleaners, home services, general local services",
          "Bold Work Truck — lawn, construction, handyman, pressure washing, trades",
          "Warm Neighborhood — family businesses, small shops, community businesses",
          "Sharp Modern — detailers, photographers, premium/specialty services",
          "Simple Menu Board — food, products, menus, shops, service lists"
        ]
      },
      {
        "step": "Select default features",
        "purpose": "Make the preview a pre-build form, not just a sales page.",
        "examples": {
          "lawn_care": [
            "services section",
            "service area",
            "photo gallery",
            "quote request form",
            "call button",
            "Google/Facebook links",
            "recurring service option",
            "payment/deposit link optional"
          ],
          "cleaning": [
            "residential/commercial services",
            "trust proof",
            "request estimate form",
            "recurring cleaning option",
            "photo/gallery section",
            "contact path"
          ],
          "food_vendor": [
            "menu/offer display",
            "location/events/hours",
            "photos",
            "order/catering request",
            "payment/deposit link",
            "social links"
          ]
        }
      },
      {
        "step": "Generate custom preview page",
        "purpose": "Create a personalized link that functions as the full sales package.",
        "url_patterns": [
          "/preview.php?b=business-slug",
          "/p/business-slug"
        ],
        "sections": [
          "Front Door Preview for [Business Name]",
          "What we know",
          "What looks good",
          "Where customers may get stuck",
          "Recommended Front Door",
          "Recommended design direction",
          "Recommended features",
          "Choose package",
          "Choose design",
          "Choose features",
          "Start / ask question"
        ]
      },
      {
        "step": "Send outreach",
        "purpose": "Keep the message short and let the preview link sell.",
        "message": "I looked at [Business Name] online and made a quick Front Door preview. Good sign: [strength]. Biggest gap I noticed: [primary weakness]. The preview shows what I’d build so customers can find you, see what you offer, request work, book time, or pay cleanly: [link]"
      },
      {
        "step": "Close through the preview",
        "purpose": "Move from interest to purchase through guided choices.",
        "close_flow": [
          "Read diagnosis.",
          "See recommended package.",
          "Choose Standard or Managed.",
          "Choose design direction.",
          "Select must-have features.",
          "Add notes.",
          "Submit choices.",
          "Pay or request invoice.",
          "Receive next-step checklist."
        ],
        "ctas": [
          "Start My Front Door",
          "Send My Choices",
          "Build This Front Door",
          "Ask a Question First"
        ]
      },
      {
        "step": "Follow up",
        "purpose": "Keep follow-up practical without begging or spamming.",
        "sequence": [
          "2 days later: check that they saw the preview and restate the main weakness.",
          "5–7 days later: remind them Standard is $499 and includes the first year.",
          "Later: closing-loop message that the preview will remain available."
        ]
      }
    ]
  },
  "business_type_defaults": {
    "lawn_care": {
      "expected_sections": [
        "Hero",
        "Services",
        "Service area",
        "Photo gallery",
        "Quote request",
        "Recurring service option",
        "Call button",
        "Google/Facebook links",
        "Payment/deposit optional"
      ],
      "likely_features": [
        "Quote form",
        "Service area",
        "Before/after gallery",
        "Recurring service"
      ]
    },
    "cleaning": {
      "expected_sections": [
        "Residential/commercial",
        "Services/packages",
        "Trust proof",
        "Request estimate",
        "Recurring cleaning option",
        "Photos",
        "Contact"
      ],
      "likely_features": [
        "Estimate form",
        "Trust/reviews",
        "Recurring option",
        "Photo proof"
      ]
    },
    "handyman_construction": {
      "expected_sections": [
        "Services",
        "Project examples",
        "Before/after",
        "Request estimate",
        "Photo upload",
        "Service area",
        "Trust proof"
      ],
      "likely_features": [
        "Request estimate",
        "Photo upload",
        "Gallery",
        "Service area"
      ]
    }
  },
  "telemetry": {
    "sales_pipeline_events": [
      "Found",
      "Qualified",
      "Researched",
      "Preview Created",
      "Contacted",
      "Opened",
      "Viewed Preview",
      "Clicked CTA",
      "Selected Options",
      "Started Checkout / Requested Invoice",
      "Paid",
      "Build Started",
      "Launched",
      "Closed Lost"
    ],
    "timestamps": [
      "date found",
      "date researched",
      "date preview created",
      "date contacted",
      "date first opened",
      "date responded",
      "date choices submitted",
      "date paid",
      "date launched"
    ],
    "preview_events": [
      "preview_viewed",
      "cta_clicked",
      "package_selected",
      "standard_selected",
      "managed_selected",
      "design_changed",
      "features_selected",
      "choices_submitted",
      "payment_clicked",
      "returned_later"
    ],
    "outreach_events": [
      "outreach channel",
      "message version",
      "sent date/time",
      "opened if possible",
      "clicked preview",
      "replied",
      "follow-up sent",
      "unsubscribed / declined"
    ],
    "diagnostic_logic": [
      "If they do not click, outreach is weak.",
      "If they click but do not act, preview or offer is weak.",
      "If they submit but do not pay, close or payment is weak."
    ]
  },
  "preview_to_build_handoff": {
    "submitted_choices_needed": [
      "selected package",
      "selected design direction",
      "selected features",
      "business goal",
      "notes",
      "preferred contact method"
    ],
    "pre_research_needed": [
      "business data",
      "strengths",
      "weaknesses",
      "Me scores",
      "recommended package",
      "recommended template",
      "recommended features",
      "current customer path"
    ],
    "missing_input_checklist_examples": [
      "logo",
      "photos",
      "payment link",
      "service area",
      "reviews",
      "preferred form destination email"
    ],
    "build_map_example": {
      "business_type": "lawn care",
      "design": "Bold Work Truck",
      "package": "Standard",
      "features": [
        "services section",
        "quote request",
        "gallery",
        "service area",
        "payment link"
      ],
      "build_modules": [
        "Hero",
        "Services",
        "Gallery",
        "Quote Form",
        "Service Area",
        "Payment CTA",
        "Footer"
      ]
    }
  },
  "me_scorecard": {
    "Find Me": {
      "question": "Can customers discover the business?",
      "scale": {
        "0": "hard to find, unclear business identity",
        "1": "only scattered social/profile presence",
        "2": "findable, but weak or inconsistent",
        "3": "acceptable Google/Facebook presence",
        "4": "clear presence with good business info",
        "5": "strong website/profile/search/map presence"
      }
    },
    "Trust Me": {
      "question": "Does the business look real, current, and worth hiring?",
      "scale": {
        "0": "looks abandoned or sketchy",
        "1": "very little proof or outdated info",
        "2": "some proof, but weak presentation",
        "3": "acceptable legitimacy",
        "4": "good photos/reviews/business info",
        "5": "highly trustworthy and polished"
      }
    },
    "Contact Me": {
      "question": "Can customers reach out easily?",
      "scale": {
        "0": "no clear contact path",
        "1": "contact exists but is buried/confusing",
        "2": "phone/message only, no structured request",
        "3": "clear call/message path",
        "4": "multiple clean contact options",
        "5": "excellent request/contact flow with confirmation"
      }
    },
    "Show Me": {
      "question": "Can customers see what they offer?",
      "scale": {
        "0": "no clear services/products/work shown",
        "1": "vague or scattered posts only",
        "2": "some examples but hard to understand",
        "3": "acceptable service/product display",
        "4": "good photos/services/examples",
        "5": "strong gallery/catalog/portfolio/menu presentation"
      }
    },
    "Book Me": {
      "question": "Can customers request a time, job, estimate, appointment, or consultation?",
      "scale": {
        "0": "no booking/request path",
        "1": "only message me or call me",
        "2": "vague request path",
        "3": "usable appointment/request option",
        "4": "clear booking/request form",
        "5": "strong booking/request workflow"
      }
    },
    "Pay Me": {
      "question": "Can customers pay, deposit, or understand payment?",
      "scale": {
        "0": "no payment path or instructions",
        "1": "payment unclear",
        "2": "payment only after manual conversation",
        "3": "acceptable payment instructions/link",
        "4": "clear payment/deposit path",
        "5": "strong payment/checkout/deposit flow"
      }
    },
    "Fix Me": {
      "question": "How much existing mess needs cleanup?",
      "scale": {
        "0": "no obvious cleanup needed",
        "1": "minor cleanup",
        "2": "some outdated/confusing info",
        "3": "several visible issues",
        "4": "messy across multiple places",
        "5": "broken, outdated, conflicting, or embarrassing online setup"
      },
      "note": "For Fix Me, high score means more cleanup needed."
    }
  },
  "what_not_to_do": [
    "Do not send generic I build websites messages.",
    "Do not lead with AI, SEO, automation, or tech tools.",
    "Do not attack the business owner.",
    "Do not promise leads or revenue.",
    "Do not imply the business is bad.",
    "Do not create unlimited custom design work before payment.",
    "Do not make the prospect invent the project from scratch.",
    "Do not overbuild a complex CRM before the preview system proves demand."
  ]
}
JSON, true);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($sales, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return $sales;
}

ho_admin_render_start(
    'sales',
    'Hoosier Online Sales Philosophy',
    'Sales philosophy',
    'Sales <em>System</em>',
    'Refined prospect workflow, preview data, telemetry, and build handoff doctrine.'
);
?>
<script type="application/json" id="ho-sales-machine"><?= ho_h(json_encode($sales, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></script>


<section class="admin-operator-banner">
  <div>
    <strong>Reference philosophy</strong>
    <span>This page supports the operator workflow. Use Prospects as the main working surface unless this page is needed for reference or maintenance.</span>
  </div>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
</section>

<section class="admin-card">
  <h2>Core Thesis</h2>
  <p><?= ho_h($sales['core_thesis']) ?></p>
  <p><strong>Operating principle:</strong> <?= ho_h($sales['operating_principle']) ?></p>
  <p><strong>Successful sale:</strong> <?= ho_h($sales['successful_sale_definition']) ?></p>
  <p><strong>Machine goal:</strong> <?= ho_h($sales['sales_machine_goal']) ?></p>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Data Philosophy</h2>
  <p><?= ho_h($sales['data_philosophy']['summary']) ?></p>
  <?= ho_admin_doc_list($sales['data_philosophy']['three_jobs']) ?>
  <p><strong>Preview rule:</strong> <?= ho_h($sales['data_philosophy']['preview_rule']) ?></p>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Business Offer</h2>
  <p><?= ho_h($sales['business_offer']['plain_pitch']) ?></p>
  <p><strong>Speed:</strong> <?= ho_h($sales['business_offer']['speed_positioning']) ?></p>
  <div class="admin-grid" style="margin-top:18px;">
    <?php foreach ($sales['business_offer']['products'] as $product): ?>
      <article>
        <h3><?= ho_h($product['name']) ?></h3>
        <p><strong><?= ho_h($product['price']) ?></strong></p>
        <p><?= ho_h($product['included']) ?></p>
        <p><?= ho_h($product['renewal']) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Prospect Master Record</h2>
  <p><?= ho_h($sales['prospect_master_record']['purpose']) ?></p>
  <?= ho_admin_doc_list($sales['prospect_master_record']['sections']) ?>
</section>

<div class="admin-grid" style="margin-top:18px;">
  <section class="admin-card">
    <h2>Identity Data</h2>
    <?= ho_admin_doc_list($sales['research_data_requirements']['identity_data']) ?>
  </section>
  <section class="admin-card">
    <h2>Customer Path</h2>
    <?= ho_admin_doc_list($sales['research_data_requirements']['current_customer_path']) ?>
  </section>
</div>

<div class="admin-grid" style="margin-top:18px;">
  <section class="admin-card">
    <h2>Strength Tags</h2>
    <?= ho_admin_doc_list($sales['research_data_requirements']['strength_tags']) ?>
  </section>
  <section class="admin-card">
    <h2>Weakness Tags</h2>
    <?= ho_admin_doc_list($sales['research_data_requirements']['weakness_tags']) ?>
    <p><strong>Translation rule:</strong> <?= ho_h($sales['research_data_requirements']['weakness_translation_rule']) ?></p>
  </section>
</div>

<section class="admin-card" style="margin-top:18px;">
  <h2>Personalization Fields</h2>
  <div class="admin-grid-three">
    <article>
      <h3>Outreach</h3>
      <?= ho_admin_doc_list($sales['preview_personalization_fields']['most_important_for_outreach']) ?>
    </article>
    <article>
      <h3>Preview</h3>
      <?= ho_admin_doc_list($sales['preview_personalization_fields']['most_important_for_preview']) ?>
    </article>
    <article>
      <h3>Fulfillment</h3>
      <?= ho_admin_doc_list($sales['preview_personalization_fields']['most_important_for_fulfillment']) ?>
    </article>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Workflow</h2>
  <p><strong><?= ho_h($sales['prospect_workflow']['one_line']) ?></strong></p>
  <p><?= ho_h($sales['prospect_workflow']['ideal_prospect_profile']) ?></p>
</section>

<?php foreach ($sales['prospect_workflow']['steps'] as $index => $step): ?>
  <section class="admin-card" style="margin-top:18px;">
    <h2><?= ho_h(str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT)) ?> — <?= ho_h($step['step']) ?></h2>
    <p><?= ho_h($step['purpose']) ?></p>

    <?php foreach ($step as $key => $value): ?>
      <?php if (in_array($key, ['step', 'purpose'], true)) continue; ?>
      <h3><?= ho_h(str_replace('_', ' ', $key)) ?></h3>
      <?php if (is_array($value)): ?>
        <?php if (array_is_list($value)): ?>
          <?= ho_admin_doc_list($value) ?>
        <?php else: ?>
          <pre><?= ho_h(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        <?php endif; ?>
      <?php else: ?>
        <p><?= ho_h((string)$value) ?></p>
      <?php endif; ?>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<section class="admin-card" style="margin-top:18px;">
  <h2>Business Type Defaults</h2>
  <div class="admin-grid">
    <?php foreach ($sales['business_type_defaults'] as $type => $defaults): ?>
      <article>
        <h3><?= ho_h(str_replace('_', ' ', $type)) ?></h3>
        <p><strong>Expected sections</strong></p>
        <?= ho_admin_doc_list($defaults['expected_sections']) ?>
        <p><strong>Likely features</strong></p>
        <?= ho_admin_doc_list($defaults['likely_features']) ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Telemetry</h2>
  <div class="admin-grid">
    <article>
      <h3>Sales Events</h3>
      <?= ho_admin_doc_list($sales['telemetry']['sales_pipeline_events']) ?>
    </article>
    <article>
      <h3>Preview Events</h3>
      <?= ho_admin_doc_list($sales['telemetry']['preview_events']) ?>
    </article>
    <article>
      <h3>Outreach Events</h3>
      <?= ho_admin_doc_list($sales['telemetry']['outreach_events']) ?>
    </article>
    <article>
      <h3>Diagnostic Logic</h3>
      <?= ho_admin_doc_list($sales['telemetry']['diagnostic_logic']) ?>
    </article>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Preview to Build Handoff</h2>
  <div class="admin-grid">
    <article>
      <h3>Submitted Choices</h3>
      <?= ho_admin_doc_list($sales['preview_to_build_handoff']['submitted_choices_needed']) ?>
    </article>
    <article>
      <h3>Pre-Research Needed</h3>
      <?= ho_admin_doc_list($sales['preview_to_build_handoff']['pre_research_needed']) ?>
    </article>
    <article>
      <h3>Missing Input Examples</h3>
      <?= ho_admin_doc_list($sales['preview_to_build_handoff']['missing_input_checklist_examples']) ?>
    </article>
    <article>
      <h3>Build Map Example</h3>
      <pre><?= ho_h(json_encode($sales['preview_to_build_handoff']['build_map_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
    </article>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Me Scorecard</h2>
  <div class="admin-grid">
    <?php foreach ($sales['me_scorecard'] as $name => $score): ?>
      <article>
        <h3><?= ho_h($name) ?></h3>
        <p><?= ho_h($score['question']) ?></p>
        <pre><?= ho_h(json_encode($score['scale'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        <?php if (isset($score['note'])): ?><p><?= ho_h($score['note']) ?></p><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>What Not To Do</h2>
  <?= ho_admin_doc_list($sales['what_not_to_do']) ?>
</section>

<p class="admin-muted">Machine-readable JSON: <a href="/salesphilosophy.php?format=json">open JSON</a></p>

<?php
ho_admin_render_end();
