<?php
declare(strict_types=1);

require __DIR__ . '/admin-core.php';

$salesPortalCanon = json_decode(<<<'JSON'
{
  "schema": "hoosier_online.sales_portal_locked_canon.v1",
  "version": "HO-SALES-PORTAL-032",
  "summary": "Additive locked database canon for Sales Portal v1.",
  "system_purpose": [
    "Identify businesses",
    "Collect evidence",
    "Store facts as confidence-scored claims",
    "Map claims into Me requirements",
    "Score each Me category",
    "Calculate marketing clearance",
    "Generate preview data",
    "Track outreach and preview telemetry",
    "Prepare build handoff after sale"
  ],
  "rollup_chain": [
    "Claims",
    "Requirement Scores",
    "Me Category Scores",
    "Marketing Clearance Score",
    "Marketing Clearance Status",
    "Preview / Outreach / Build Handoff"
  ],
  "me_requirements": {
    "find_me": {
      "business_identity_clear": "The business name, business type, and basic identity are clear.",
      "location_or_service_area_clear": "The customer can tell where the business operates or what area it serves.",
      "public_search_presence": "The business has some public discoverability through Google, website, Facebook, directory listing, or another searchable public source.",
      "single_customer_destination": "There is, or should be, one clean place customers can go instead of scattered links/posts/profiles."
    },
    "trust_me": {
      "appears_active": "The business appears currently operating.",
      "has_proof": "There is visible proof: photos, reviews, before/after examples, portfolio, testimonials, or public activity.",
      "has_consistent_identity": "The business name, contact information, branding, and basic details do not conflict across sources.",
      "has_credible_presentation": "The current online presence does not look abandoned, sketchy, confusing, or amateur enough to hurt trust."
    },
    "contact_me": {
      "clear_primary_contact": "A customer can clearly find the best way to contact the business.",
      "structured_request_path": "A customer can submit a structured request, quote request, job request, appointment request, or inquiry without relying only on vague messaging.",
      "customer_next_step_clear": "The customer understands what to do next and what happens after reaching out."
    },
    "show_me": {
      "services_visible": "The business’s services are visible and understandable.",
      "products_or_work_visible": "Products, work examples, project examples, menu items, gallery items, or portfolio items are visible when relevant.",
      "offer_clarity": "The customer can understand what is being offered without digging through old posts or guessing.",
      "visual_proof": "The business has or needs visual proof that supports customer confidence."
    },
    "book_me": {
      "request_time_possible": "The customer can request a job, estimate, appointment, visit, consultation, or time slot.",
      "appointment_or_estimate_path": "There is a clean workflow for appointment, estimate, or job-request intake when relevant.",
      "booking_expectation_clear": "The customer understands whether they are booking directly, requesting a quote, requesting a callback, or asking for availability."
    },
    "pay_me": {
      "payment_path_exists": "There is a payment path when payment before/during booking makes sense.",
      "deposit_path_exists": "There is a deposit path when deposits are useful or expected.",
      "payment_instructions_clear": "The customer can understand how payment works without awkward back-and-forth."
    },
    "fix_me": {
      "broken_or_conflicting_info": "There are broken, outdated, conflicting, or inaccurate business details.",
      "outdated_presence": "The online presence appears stale, abandoned, or not current.",
      "technical_mess": "There are broken links, dead pages, bad mobile layout, missing images, domain confusion, or other technical issues.",
      "customer_path_mess": "The customer journey is scattered across too many places or requires too much guessing."
    }
  },
  "claim_fields": {
    "core_identity": [
      "business_name",
      "business_type",
      "business_description",
      "owner_name",
      "brand_name_consistency"
    ],
    "location_service_area": [
      "street_address",
      "city",
      "state",
      "service_area",
      "hours_of_operation",
      "location_consistency"
    ],
    "public_presence": [
      "website_url",
      "google_profile_url",
      "facebook_url",
      "instagram_url",
      "directory_listing_url",
      "single_customer_destination_present",
      "public_presence_consistency"
    ],
    "contact": [
      "phone_number",
      "email_address",
      "contact_form_present",
      "request_form_present",
      "facebook_message_enabled",
      "primary_cta_text",
      "confirmation_message_present",
      "contact_path_clarity"
    ],
    "service_offer": [
      "services_list_present",
      "products_list_present",
      "menu_present",
      "pricing_present",
      "package_or_offer_present",
      "service_descriptions_clear",
      "customer_use_case_clear"
    ],
    "proof_trust": [
      "photos_present",
      "photo_quality",
      "before_after_present",
      "portfolio_present",
      "reviews_present",
      "review_count",
      "average_rating",
      "testimonials_present",
      "licenses_certifications_present",
      "recent_activity_present"
    ],
    "booking": [
      "booking_link_present",
      "appointment_form_present",
      "estimate_request_form_present",
      "calendar_link_present",
      "preferred_time_field_present",
      "availability_note_present",
      "booking_expectation_text"
    ],
    "payment": [
      "payment_link_present",
      "deposit_link_present",
      "invoice_link_present",
      "checkout_link_present",
      "payment_provider_visible",
      "payment_terms_present",
      "payment_path_clarity"
    ],
    "fix_cleanup": [
      "broken_links_present",
      "conflicting_phone_numbers",
      "conflicting_hours",
      "dead_website",
      "bad_mobile_layout",
      "missing_images",
      "old_posts_or_stale_activity",
      "duplicate_profiles",
      "domain_confusion",
      "too_much_scrolling_required",
      "scattered_customer_path"
    ],
    "recommendation": [
      "primary_sales_angle",
      "recommended_package",
      "recommended_design",
      "recommended_features",
      "marketing_clearance_score",
      "marketing_clearance_status"
    ]
  },
  "confidence_levels": {
    "confirmed": "90-100",
    "likely": "70-89",
    "inferred": "40-69",
    "weak_inference": "20-39",
    "missing": "0",
    "conflicting": "variable / needs_review",
    "rejected": "0"
  },
  "source_confidence_defaults": {
    "official_website": 90,
    "google_business_profile": 85,
    "official_facebook_page": 80,
    "official_instagram_tiktok": 70,
    "directory_listing": 55,
    "email_address_inference": 35,
    "manual_visual_inference": 40,
    "unverified_third_party_source": 30
  },
  "me_category_weights": {
    "find_me": {
      "business_identity_clear": 30,
      "location_or_service_area_clear": 25,
      "public_search_presence": 25,
      "single_customer_destination": 20
    },
    "trust_me": {
      "appears_active": 25,
      "has_proof": 30,
      "has_consistent_identity": 25,
      "has_credible_presentation": 20
    },
    "contact_me": {
      "clear_primary_contact": 35,
      "structured_request_path": 40,
      "customer_next_step_clear": 25
    },
    "show_me": {
      "services_visible": 30,
      "products_or_work_visible": 25,
      "offer_clarity": 25,
      "visual_proof": 20
    },
    "book_me": {
      "request_time_possible": 35,
      "appointment_or_estimate_path": 40,
      "booking_expectation_clear": 25
    },
    "pay_me": {
      "payment_path_exists": 35,
      "deposit_path_exists": 25,
      "payment_instructions_clear": 40
    },
    "fix_me": {
      "broken_or_conflicting_info": 30,
      "outdated_presence": 20,
      "technical_mess": 25,
      "customer_path_mess": 25
    }
  },
  "marketing_clearance_components": {
    "business_activity_score": 20,
    "need_score": 25,
    "fit_score": 20,
    "confidence_score": 15,
    "contactability_score": 10,
    "buildability_score": 10
  },
  "clearance_statuses": {
    "cleared": "Approved for preview generation and outreach.",
    "warm_clear": "Probably worth outreach, but with softer language and some missing/medium-confidence data.",
    "needs_review": "Promising or uncertain, but not automatically cleared.",
    "hold": "Possible future prospect, but insufficient information now.",
    "skip": "Not worth pursuing under the current offer.",
    "blocked": "Do not contact or preview because of a hard blocker, compliance concern, do-not-contact request, severe identity conflict, or similar reason."
  },
  "minimum_field_gates": {
    "cleared": [
      "marketing_clearance_score >= 75",
      "business_name confidence >= 70",
      "business_type confidence >= 60",
      "city or service_area confidence >= 60",
      "Business Activity Score >= 11",
      "Contactability Score >= 5",
      "Need Score >= 10",
      "Fit Score >= 12",
      "Confidence Score >= 9",
      "Buildability Score >= 5",
      "primary_weakness confidence >= 70",
      "no hard blockers"
    ],
    "warm_clear": [
      "marketing_clearance_score >= 60",
      "business_name confidence >= 60",
      "business_type confidence >= 50",
      "at least one usable contact method",
      "no hard blockers",
      "business appears active enough",
      "at least one likely Front Door weakness"
    ]
  },
  "public_use_rules": {
    "outreach_thresholds": {
      "business_name": 70,
      "business_type": 60,
      "primary_weakness": 70,
      "specific_critique": 75,
      "owner_name": 85
    }
  }
}
JSON, true);

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($salesPortalCanon, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return $salesPortalCanon;
}

ho_admin_render_start(
    'portal',
    'Hoosier Online Sales Portal',
    'Sales portal',
    'Sales <em>Portal</em>',
    'Locked database canon added for prospect intelligence, scoring, clearance, and schema preparation.'
);
?>
<script type="application/json" id="ho-salesportal-locked-canon"><?= ho_h(json_encode($salesPortalCanon, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></script>


<section class="admin-operator-banner">
  <div>
    <strong>Reference canon</strong>
    <span>This page supports the operator workflow. Use Prospects as the main working surface unless this page is needed for reference or maintenance.</span>
  </div>
  <a class="admin-btn admin-btn-secondary" href="/sales-portal-dashboard.php">Prospects</a>
</section>

<section class="admin-card">
  <h2>Locked Canon Summary</h2>
  <p><?= ho_h($salesPortalCanon['summary']) ?></p>
  <h3>System Purpose</h3>
  <?= ho_admin_doc_list($salesPortalCanon['system_purpose']) ?>
  <h3>Rollup Chain</h3>
  <?= ho_admin_doc_list($salesPortalCanon['rollup_chain']) ?>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Locked Me Requirements</h2>
  <div class="admin-grid">
    <?php foreach ($salesPortalCanon['me_requirements'] as $category => $requirements): ?>
      <article>
        <h3><?= ho_h(str_replace('_', ' ', $category)) ?></h3>
        <?php foreach ($requirements as $key => $description): ?>
          <p><strong><?= ho_h($category . '.' . $key) ?></strong><br><?= ho_h($description) ?></p>
        <?php endforeach; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Locked Claim Fields</h2>
  <div class="admin-grid">
    <?php foreach ($salesPortalCanon['claim_fields'] as $group => $fields): ?>
      <article>
        <h3><?= ho_h(str_replace('_', ' ', $group)) ?></h3>
        <?= ho_admin_doc_list($fields) ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Locked Scoring Formulas</h2>
  <div class="admin-grid">
    <article><h3>Confidence Levels</h3><pre><?= ho_h(json_encode($salesPortalCanon['confidence_levels'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article>
    <article><h3>Source Defaults</h3><pre><?= ho_h(json_encode($salesPortalCanon['source_confidence_defaults'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article>
    <article><h3>Me Weights</h3><pre><?= ho_h(json_encode($salesPortalCanon['me_category_weights'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article>
    <article><h3>Marketing Components</h3><pre><?= ho_h(json_encode($salesPortalCanon['marketing_clearance_components'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></article>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Locked Clearance Statuses</h2>
  <div class="admin-grid">
    <?php foreach ($salesPortalCanon['clearance_statuses'] as $status => $meaning): ?>
      <article><h3><?= ho_h(str_replace('_', ' ', $status)) ?></h3><p><?= ho_h($meaning) ?></p></article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Locked Minimum Field Gates</h2>
  <div class="admin-grid">
    <?php foreach ($salesPortalCanon['minimum_field_gates'] as $status => $rules): ?>
      <article><h3><?= ho_h(str_replace('_', ' ', $status)) ?></h3><?= ho_admin_doc_list($rules) ?></article>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card" style="margin-top:18px;">
  <h2>Database Schema Preparation Notes</h2>
  <p>The accompanying <code>db/schema.sql</code> creates the v1 tables for prospect intelligence, evidence, scoring, previews, telemetry, and build handoff.</p>
  <p>The seed files create canonical Me categories, locked requirements, claim field definitions, scoring references, and clearance statuses.</p>
</section>

<p class="admin-muted">Machine-readable JSON: <a href="/salesportal.php?format=json">open JSON</a></p>
<?php ho_admin_render_end(); ?>
