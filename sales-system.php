<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$salesChannelCanon = json_decode(<<<'JSON'
{
  "schema": "hoosier_online.sales_system_canon.v043",
  "version": "HO-SALES-SYSTEM-043",
  "status": "preview_flow_and_data_contract_locked_draft",
  "core_guardrail": "Do not build scraping or bulk lead intake until the sales channel, preview flow, option catalog, payment/build handoff, and manual test process are stable.",
  "sales_machine_flow": [
    "Research business",
    "Identify visible online gaps",
    "Store facts as confidence-scored claims",
    "Classify preview readiness",
    "Generate custom preview link",
    "Send curiosity-heavy outreach",
    "Customer opens preview",
    "Customer sees what we know, what looks good, and where customers may get stuck",
    "Customer chooses a starting setup",
    "Customer selects address option, package, and preferred direction",
    "Customer pays or requests setup",
    "System creates build handoff"
  ],
  "preview_flow_canon": {
    "purpose": "The preview page is the sales bridge between researched prospect data and a paid/front-door build handoff.",
    "customer_facing_name": "Front Door Preview",
    "internal_name": "Preview Flow",
    "primary_customer_action": "Choose a starting setup and claim the Front Door.",
    "not_allowed": [
      "Do not expose internal confidence scores directly to the customer.",
      "Do not show weak/private-looking research as if we know personal facts.",
      "Do not call the page a template builder.",
      "Do not make the preview feel like a generic website pitch.",
      "Do not present low-confidence criticism as fact."
    ],
    "page_sections": [
      {
        "key": "hero",
        "label": "Front Door headline",
        "purpose": "Make clear this is a specific preview for the business.",
        "data_needed": [
          "business_name",
          "business_type",
          "service_area_or_city"
        ],
        "customer_message": "We built a starting point for how customers should find, trust, and contact this business."
      },
      {
        "key": "what_customers_can_find_now",
        "label": "What customers can find now",
        "purpose": "Show that the preview is based on real public customer-facing research.",
        "data_needed": [
          "website_url",
          "google_profile_url",
          "facebook_url",
          "phone_number",
          "service_area"
        ],
        "customer_message": "Here is the public path customers can currently see."
      },
      {
        "key": "what_looks_good",
        "label": "What already looks good",
        "purpose": "Avoid sounding like an attack; identify active-business signals.",
        "data_needed": [
          "recent_activity_present",
          "photos_present",
          "reviews_present",
          "services_list_present",
          "business_identity_clear"
        ],
        "customer_message": "There are useful signals already working in your favor."
      },
      {
        "key": "where_customers_get_stuck",
        "label": "Where customers may get stuck",
        "purpose": "Create sales tension without insult.",
        "data_needed": [
          "request_form_present",
          "contact_path_clarity",
          "single_customer_destination_present",
          "bad_mobile_layout",
          "scattered_customer_path"
        ],
        "customer_message": "The problem is not that you do not work hard. The problem is customers may not have one clean next step."
      },
      {
        "key": "recommended_front_door",
        "label": "Recommended Front Door",
        "purpose": "Translate research into the proposed solution.",
        "data_needed": [
          "recommended_package",
          "recommended_design",
          "primary_sales_angle",
          "recommended_features"
        ],
        "customer_message": "This is the simplest setup we recommend based on what customers need first."
      },
      {
        "key": "choose_starting_point",
        "label": "Choose your starting point",
        "purpose": "Let the customer choose a design direction without making them feel overwhelmed.",
        "data_needed": [
          "template_options",
          "design_option_labels",
          "design_option_descriptions"
        ],
        "customer_message": "Pick the closest fit. It does not need to be perfect yet."
      },
      {
        "key": "choose_address",
        "label": "Choose your address",
        "purpose": "Offer fast subdomain options and custom-domain ideas.",
        "data_needed": [
          "subdomain_options",
          "domain_ideas"
        ],
        "customer_message": "Start fast with an included Hoosier Online address or ask about a custom domain."
      },
      {
        "key": "choose_package",
        "label": "Choose your package",
        "purpose": "Keep the offer simple: Standard or Managed.",
        "data_needed": [
          "standard_package",
          "managed_package"
        ],
        "customer_message": "Choose whether you want the basic setup or ongoing help."
      },
      {
        "key": "submit_or_pay",
        "label": "Submit / pay / request setup",
        "purpose": "Capture the customer decision and feed the build handoff.",
        "data_needed": [
          "selected_package",
          "selected_design",
          "selected_address_option",
          "customer_contact_confirmation"
        ],
        "customer_message": "Send the setup request and move it into build."
      }
    ]
  },
  "preview_data_contract": {
    "minimum_required_before_preview": [
      "business_name confidence >= 70",
      "business_type confidence >= 60",
      "city or service_area confidence >= 60",
      "at least one public evidence source",
      "at least one usable contact path OR clear contact-path weakness",
      "at least one active-business signal OR enough context for a soft preview",
      "at least one customer-path gap",
      "marketing_clearance_status is cleared, warm_clear, or manually approved"
    ],
    "high_confidence_customer_safe_fields": [
      "business_name",
      "business_type",
      "city",
      "state",
      "service_area",
      "website_url",
      "google_profile_url",
      "facebook_url",
      "phone_number",
      "services_list_present",
      "photos_present",
      "reviews_present",
      "recent_activity_present"
    ],
    "medium_confidence_customer_safe_fields": [
      "contact_path_clarity",
      "single_customer_destination_present",
      "request_form_present",
      "booking_link_present",
      "payment_link_present",
      "service_descriptions_clear",
      "visual_proof"
    ],
    "internal_only_fields": [
      "owner_name unless confidence is high and customer-facing use is appropriate",
      "confidence_score",
      "weak_inference notes",
      "private-looking assumptions",
      "negative character judgments",
      "criminal-history assumptions",
      "anything not based on public customer-facing evidence"
    ],
    "missing_inputs_for_customer_confirmation": [
      "best phone number",
      "best email",
      "preferred service area",
      "preferred job types",
      "photos or examples to include",
      "preferred business name spelling",
      "preferred customer next step",
      "payment/deposit preference",
      "domain preference"
    ],
    "preview_readiness_statuses": {
      "ready": "Enough customer-safe data exists to generate a preview.",
      "soft_ready": "Preview can be generated, but criticism must stay broad and careful.",
      "needs_more_research": "Research is not sufficient to generate a good preview.",
      "manual_review": "Promising prospect, but claims need human review.",
      "blocked": "Do not generate preview."
    }
  },
  "customer_choice_contract": {
    "choices_to_capture": [
      "selected_design_option",
      "selected_address_option",
      "selected_package",
      "preferred_contact_method",
      "confirmed_business_name",
      "confirmed_phone",
      "confirmed_email",
      "confirmed_service_area",
      "customer_notes"
    ],
    "address_option_types": [
      "included_hoosier_subdomain",
      "local_service_hoosier_subdomain",
      "custom_domain_idea",
      "undecided_help_me_choose"
    ],
    "package_options": [
      {
        "key": "standard",
        "label": "Standard",
        "description": "Ready-to-claim Front Door setup using recommended options."
      },
      {
        "key": "managed",
        "label": "Managed",
        "description": "Setup plus help refining, updating, and keeping the Front Door useful over time."
      }
    ],
    "post_choice_result": "Customer choices create or update preview_choices and prepare a build_handoff record."
  },
  "build_handoff_contract": {
    "build_handoff_fields": [
      "business_id",
      "preview_id",
      "selected_package",
      "selected_design_option",
      "selected_address_option",
      "confirmed_business_name",
      "confirmed_contact_details",
      "confirmed_service_area",
      "recommended_features",
      "missing_inputs",
      "customer_notes",
      "build_status"
    ],
    "build_statuses": [
      "not_started",
      "intake_needed",
      "ready_to_build",
      "building",
      "review",
      "launched",
      "paused",
      "cancelled"
    ],
    "handoff_rule": "Only confirmed customer-submitted data and high-confidence research should become build inputs. Medium-confidence data should be marked for confirmation. Low-confidence assumptions become missing inputs."
  },
  "next_development_step": {
    "recommended_version": "v044",
    "title": "Preview Schema + Seed Preparation",
    "touch": [
      "db/preview_schema_additions.sql",
      "db/seed_preview_options.sql",
      "sales-system.php"
    ],
    "purpose": "Prepare the database to store preview readiness, design options, address options, customer choices, and build handoff links before building preview.php.",
    "do_not_do": [
      "Do not build customer-facing preview.php yet.",
      "Do not add scraping.",
      "Do not start mass outreach.",
      "Do not add payment integration yet."
    ]
  }
}
JSON, true);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($salesChannelCanon, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

ho_admin_render_start(
    'sales_system',
    'Hoosier Online Sales System',
    'Sales system',
    'Sales <em>System</em>',
    'Unified sales philosophy, portal doctrine, research loop, prospect intelligence, preview strategy, and build handoff map.'
);
?>
<script type="application/json" id="ho-sales-channel-canon"><?= ho_h(json_encode($salesChannelCanon, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></script>

<section class="admin-card">
  <h2>One Cohesive System</h2>
  <p>The sales system is one flow: define the offer, research the business, store evidence as claims, score the opportunity, generate a preview, send outreach, capture customer choices, and hand the sale into fulfillment.</p>
</section>

<section class="admin-status error">
  <div class="admin-status-head"><strong>Current Guardrail</strong></div>
  <p><?= ho_h($salesChannelCanon['core_guardrail']) ?></p>
</section>

<section class="admin-card">
  <h2>Sales Machine Flow</h2>
  <div class="admin-workflow-strip">
    <?php foreach ($salesChannelCanon['sales_machine_flow'] as $i => $step): ?>
      <span><?= ho_h(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)) ?> · <?= ho_h($step) ?></span>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card">
  <h2>Preview Flow Canon</h2>
  <p><?= ho_h($salesChannelCanon['preview_flow_canon']['purpose']) ?></p>
  <div class="admin-data-list">
    <?php foreach ($salesChannelCanon['preview_flow_canon']['page_sections'] as $section): ?>
      <div class="admin-data-row">
        <div>
          <div class="admin-data-row-title"><?= ho_h($section['label']) ?></div>
          <div class="admin-data-row-note"><?= ho_h($section['purpose']) ?></div>
          <div class="admin-data-row-note"><strong>Customer message:</strong> <?= ho_h($section['customer_message']) ?></div>
          <div class="admin-data-row-note"><strong>Data:</strong> <?= ho_h(implode(', ', $section['data_needed'])) ?></div>
        </div>
        <div class="admin-count"><?= ho_h($section['key']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card-grid two">
  <article class="admin-secondary-card">
    <h2>Preview Minimum Requirements</h2>
    <?= ho_admin_doc_list($salesChannelCanon['preview_data_contract']['minimum_required_before_preview']) ?>
  </article>

  <article class="admin-secondary-card">
    <h2>Preview Readiness Statuses</h2>
    <div class="admin-data-list">
      <?php foreach ($salesChannelCanon['preview_data_contract']['preview_readiness_statuses'] as $status => $meaning): ?>
        <div class="admin-data-row">
          <div>
            <div class="admin-data-row-title"><?= ho_h($status) ?></div>
            <div class="admin-data-row-note"><?= ho_h($meaning) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>

<section class="admin-card-grid two">
  <article class="admin-secondary-card">
    <h2>Customer-Safe High Confidence Fields</h2>
    <?= ho_admin_doc_list($salesChannelCanon['preview_data_contract']['high_confidence_customer_safe_fields']) ?>
  </article>

  <article class="admin-secondary-card">
    <h2>Customer-Safe Medium Confidence Fields</h2>
    <?= ho_admin_doc_list($salesChannelCanon['preview_data_contract']['medium_confidence_customer_safe_fields']) ?>
  </article>
</section>

<section class="admin-card-grid two">
  <article class="admin-secondary-card">
    <h2>Internal-Only Fields</h2>
    <?= ho_admin_doc_list($salesChannelCanon['preview_data_contract']['internal_only_fields']) ?>
  </article>

  <article class="admin-secondary-card">
    <h2>Missing Inputs for Customer Confirmation</h2>
    <?= ho_admin_doc_list($salesChannelCanon['preview_data_contract']['missing_inputs_for_customer_confirmation']) ?>
  </article>
</section>

<section class="admin-card-grid two">
  <article class="admin-secondary-card">
    <h2>Customer Choice Contract</h2>
    <h3>Choices to Capture</h3>
    <?= ho_admin_doc_list($salesChannelCanon['customer_choice_contract']['choices_to_capture']) ?>
    <h3>Address Option Types</h3>
    <?= ho_admin_doc_list($salesChannelCanon['customer_choice_contract']['address_option_types']) ?>
  </article>

  <article class="admin-secondary-card">
    <h2>Package Options</h2>
    <div class="admin-data-list">
      <?php foreach ($salesChannelCanon['customer_choice_contract']['package_options'] as $package): ?>
        <div class="admin-data-row">
          <div>
            <div class="admin-data-row-title"><?= ho_h($package['label']) ?></div>
            <div class="admin-data-row-note"><?= ho_h($package['description']) ?></div>
          </div>
          <div class="admin-count"><?= ho_h($package['key']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>

<section class="admin-card">
  <h2>Build Handoff Contract</h2>
  <p><?= ho_h($salesChannelCanon['build_handoff_contract']['handoff_rule']) ?></p>
  <div class="admin-card-grid two">
    <article>
      <h3>Build Handoff Fields</h3>
      <?= ho_admin_doc_list($salesChannelCanon['build_handoff_contract']['build_handoff_fields']) ?>
    </article>
    <article>
      <h3>Build Statuses</h3>
      <?= ho_admin_doc_list($salesChannelCanon['build_handoff_contract']['build_statuses']) ?>
    </article>
  </div>
</section>

<section class="admin-status warning">
  <div class="admin-status-head"><strong>Next Development Step</strong></div>
  <p><strong><?= ho_h($salesChannelCanon['next_development_step']['recommended_version']) ?> — <?= ho_h($salesChannelCanon['next_development_step']['title']) ?></strong></p>
  <p><?= ho_h($salesChannelCanon['next_development_step']['purpose']) ?></p>
  <p><strong>Touch:</strong> <?= ho_h(implode(', ', $salesChannelCanon['next_development_step']['touch'])) ?></p>
  <h3>Do Not Do</h3>
  <?= ho_admin_doc_list($salesChannelCanon['next_development_step']['do_not_do']) ?>
</section>

<section class="admin-reference-panel">
  <details>
    <summary>Machine-readable / reference</summary>
    <div class="admin-reference-grid">
      <a href="/sales-system.php?format=json">Sales System JSON</a>
      <a href="/salesportal.php?format=json">Sales Portal JSON</a>
      <a href="/salesphilosophy.php">Sales Philosophy</a>
      <a href="/salesportal.php">Sales Portal Canon</a>
    </div>
  </details>
</section>

<?php
ho_admin_render_end();
