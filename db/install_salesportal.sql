-- ===== schema.sql =====
-- Hoosier Online Sales Portal Database Schema v032
CREATE TABLE IF NOT EXISTS businesses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_slug VARCHAR(190) NOT NULL,
  business_name_current VARCHAR(255) DEFAULT NULL,
  business_type VARCHAR(120) DEFAULT NULL,
  location_city VARCHAR(120) DEFAULT NULL,
  location_state VARCHAR(80) DEFAULT NULL,
  service_area_text TEXT DEFAULT NULL,
  status ENUM('prospect','customer','inactive','archived','blocked') NOT NULL DEFAULT 'prospect',
  qualification_status ENUM('found','qualified','researched','needs_more_research','not_fit') NOT NULL DEFAULT 'found',
  marketing_clearance_score DECIMAL(5,2) DEFAULT NULL,
  marketing_clearance_status ENUM('cleared','warm_clear','needs_review','hold','skip','blocked') NOT NULL DEFAULT 'hold',
  recommended_package ENUM('standard','managed','unknown') NOT NULL DEFAULT 'unknown',
  recommended_design VARCHAR(120) DEFAULT NULL,
  skip_reason VARCHAR(120) DEFAULT NULL,
  block_reason VARCHAR(120) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_business_slug (business_slug),
  KEY idx_business_status (status),
  KEY idx_marketing_clearance_status (marketing_clearance_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS me_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_key VARCHAR(80) NOT NULL,
  category_name VARCHAR(120) NOT NULL,
  category_order INT UNSIGNED NOT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_category_key (category_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS me_requirements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id INT UNSIGNED NOT NULL,
  requirement_key VARCHAR(120) NOT NULL,
  requirement_label VARCHAR(190) NOT NULL,
  requirement_description TEXT NOT NULL,
  importance_weight DECIMAL(6,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_requirement_key (requirement_key),
  KEY idx_requirement_category (category_id),
  CONSTRAINT fk_me_requirements_category FOREIGN KEY (category_id) REFERENCES me_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidence_sources (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  source_type ENUM('website','google_business_profile','facebook','instagram','directory','email','manual_observation','phone_call','customer_submission','other') NOT NULL DEFAULT 'other',
  source_url TEXT DEFAULT NULL,
  source_title VARCHAR(255) DEFAULT NULL,
  captured_at TIMESTAMP NULL DEFAULT NULL,
  capture_status ENUM('captured','failed','partial','manual','not_applicable') NOT NULL DEFAULT 'manual',
  raw_excerpt MEDIUMTEXT DEFAULT NULL,
  screenshot_path TEXT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_evidence_business (business_id),
  CONSTRAINT fk_evidence_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_claims (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  evidence_source_id BIGINT UNSIGNED DEFAULT NULL,
  field_key VARCHAR(120) NOT NULL,
  field_label VARCHAR(190) DEFAULT NULL,
  claim_value TEXT DEFAULT NULL,
  normalized_value TEXT DEFAULT NULL,
  confidence_level ENUM('confirmed','likely','inferred','weak_inference','missing','conflicting','rejected') NOT NULL DEFAULT 'missing',
  confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0,
  claim_status ENUM('active','needs_review','missing','conflicting','rejected','superseded') NOT NULL DEFAULT 'active',
  source_type VARCHAR(80) DEFAULT NULL,
  source_url TEXT DEFAULT NULL,
  source_label VARCHAR(255) DEFAULT NULL,
  evidence_note TEXT DEFAULT NULL,
  supports_me_category VARCHAR(80) DEFAULT NULL,
  supports_requirement_key VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_claim_business (business_id),
  KEY idx_claim_field (field_key),
  KEY idx_claim_requirement (supports_requirement_key),
  CONSTRAINT fk_claim_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_claim_evidence FOREIGN KEY (evidence_source_id) REFERENCES evidence_sources(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_requirement_scores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  requirement_id INT UNSIGNED NOT NULL,
  score DECIMAL(4,2) NOT NULL DEFAULT 0,
  confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('active','needs_review','missing','conflicting') NOT NULL DEFAULT 'missing',
  reason TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_business_requirement (business_id, requirement_id),
  CONSTRAINT fk_req_score_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_req_score_requirement FOREIGN KEY (requirement_id) REFERENCES me_requirements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_me_scores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  score DECIMAL(4,2) NOT NULL DEFAULT 0,
  confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0,
  top_issue TEXT DEFAULT NULL,
  top_strength TEXT DEFAULT NULL,
  status ENUM('active','needs_review','missing','conflicting') NOT NULL DEFAULT 'missing',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_business_category (business_id, category_id),
  CONSTRAINT fk_me_score_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_me_score_category FOREIGN KEY (category_id) REFERENCES me_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospect_previews (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  preview_slug VARCHAR(190) NOT NULL,
  preview_url TEXT DEFAULT NULL,
  preview_status ENUM('not_created','draft','ready','sent','viewed','choices_submitted','expired') NOT NULL DEFAULT 'draft',
  primary_sales_angle VARCHAR(190) DEFAULT NULL,
  recommended_package ENUM('standard','managed','unknown') NOT NULL DEFAULT 'unknown',
  recommended_design VARCHAR(120) DEFAULT NULL,
  recommended_features_json JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_viewed_at TIMESTAMP NULL DEFAULT NULL,
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_preview_slug (preview_slug),
  CONSTRAINT fk_preview_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preview_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  preview_id BIGINT UNSIGNED NOT NULL,
  business_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  event_value TEXT DEFAULT NULL,
  session_id VARCHAR(190) DEFAULT NULL,
  ip_hash VARCHAR(190) DEFAULT NULL,
  user_agent_hash VARCHAR(190) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_preview_event_type (event_type),
  CONSTRAINT fk_preview_event_preview FOREIGN KEY (preview_id) REFERENCES prospect_previews(id) ON DELETE CASCADE,
  CONSTRAINT fk_preview_event_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS outreach_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  preview_id BIGINT UNSIGNED DEFAULT NULL,
  channel ENUM('email','facebook','contact_form','phone','manual','other') NOT NULL DEFAULT 'manual',
  message_version VARCHAR(120) DEFAULT NULL,
  recipient VARCHAR(255) DEFAULT NULL,
  sent_at TIMESTAMP NULL DEFAULT NULL,
  opened_at TIMESTAMP NULL DEFAULT NULL,
  clicked_at TIMESTAMP NULL DEFAULT NULL,
  replied_at TIMESTAMP NULL DEFAULT NULL,
  outcome ENUM('sent','opened','clicked','replied','declined','no_response','bounced','do_not_contact') DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_outreach_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_outreach_preview FOREIGN KEY (preview_id) REFERENCES prospect_previews(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preview_choices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  preview_id BIGINT UNSIGNED NOT NULL,
  business_id BIGINT UNSIGNED NOT NULL,
  selected_package ENUM('standard','managed','unknown') NOT NULL DEFAULT 'unknown',
  selected_design VARCHAR(120) DEFAULT NULL,
  selected_features_json JSON DEFAULT NULL,
  business_goal TEXT DEFAULT NULL,
  customer_notes TEXT DEFAULT NULL,
  preferred_contact_method VARCHAR(120) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_preview_choices_preview FOREIGN KEY (preview_id) REFERENCES prospect_previews(id) ON DELETE CASCADE,
  CONSTRAINT fk_preview_choices_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS build_handoffs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  preview_id BIGINT UNSIGNED DEFAULT NULL,
  package ENUM('standard','managed','unknown') NOT NULL DEFAULT 'unknown',
  design VARCHAR(120) DEFAULT NULL,
  modules_json JSON DEFAULT NULL,
  missing_inputs_json JSON DEFAULT NULL,
  build_status ENUM('not_started','intake_needed','ready_to_build','building','review','launched','paused','cancelled') NOT NULL DEFAULT 'not_started',
  launch_url TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_build_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_build_preview FOREIGN KEY (preview_id) REFERENCES prospect_previews(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS salesportal_reference (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ref_type VARCHAR(80) NOT NULL,
  ref_key VARCHAR(120) NOT NULL,
  ref_label VARCHAR(190) DEFAULT NULL,
  ref_value TEXT DEFAULT NULL,
  ref_json JSON DEFAULT NULL,
  sort_order INT UNSIGNED DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ref_type_key (ref_type, ref_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ===== seed_me_categories.sql =====
-- Seed Me Categories v032
INSERT INTO me_categories (category_key, category_name, category_order, description) VALUES
('find_me','Find Me',10,'Can customers discover the business and understand where/how it operates?'),
('trust_me','Trust Me',20,'Does the business look real, active, current, and worth hiring?'),
('contact_me','Contact Me',30,'Can customers reach the business without friction?'),
('show_me','Show Me',40,'Can customers see what the business offers?'),
('book_me','Book Me',50,'Can customers request time, work, appointments, estimates, or consultations?'),
('pay_me','Pay Me',60,'Can customers pay, deposit, or understand payment?'),
('fix_me','Fix Me',70,'What existing online mess needs cleanup before the Front Door works well?')
ON DUPLICATE KEY UPDATE category_name=VALUES(category_name), category_order=VALUES(category_order), description=VALUES(description);


-- ===== seed_me_requirements.sql =====
-- Seed Me Requirements v032

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.business_identity_clear', 'Business Identity Clear', 'The business name, business type, and basic identity are clear.', 30
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.location_or_service_area_clear', 'Location Or Service Area Clear', 'The customer can tell where the business operates or what area it serves.', 25
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.public_search_presence', 'Public Search Presence', 'The business has some public discoverability through Google, website, Facebook, directory listing, or another searchable public source.', 25
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'find_me.single_customer_destination', 'Single Customer Destination', 'There is, or should be, one clean place customers can go instead of scattered links/posts/profiles.', 20
FROM me_categories WHERE category_key = 'find_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.appears_active', 'Appears Active', 'The business appears currently operating.', 25
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.has_proof', 'Has Proof', 'There is visible proof: photos, reviews, before/after examples, portfolio, testimonials, or public activity.', 30
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.has_consistent_identity', 'Has Consistent Identity', 'The business name, contact information, branding, and basic details do not conflict across sources.', 25
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'trust_me.has_credible_presentation', 'Has Credible Presentation', 'The current online presence does not look abandoned, sketchy, confusing, or amateur enough to hurt trust.', 20
FROM me_categories WHERE category_key = 'trust_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'contact_me.clear_primary_contact', 'Clear Primary Contact', 'A customer can clearly find the best way to contact the business.', 35
FROM me_categories WHERE category_key = 'contact_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'contact_me.structured_request_path', 'Structured Request Path', 'A customer can submit a structured request, quote request, job request, appointment request, or inquiry without relying only on vague messaging.', 40
FROM me_categories WHERE category_key = 'contact_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'contact_me.customer_next_step_clear', 'Customer Next Step Clear', 'The customer understands what to do next and what happens after reaching out.', 25
FROM me_categories WHERE category_key = 'contact_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.services_visible', 'Services Visible', 'The business’s services are visible and understandable.', 30
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.products_or_work_visible', 'Products Or Work Visible', 'Products, work examples, project examples, menu items, gallery items, or portfolio items are visible when relevant.', 25
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.offer_clarity', 'Offer Clarity', 'The customer can understand what is being offered without digging through old posts or guessing.', 25
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'show_me.visual_proof', 'Visual Proof', 'The business has or needs visual proof that supports customer confidence.', 20
FROM me_categories WHERE category_key = 'show_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'book_me.request_time_possible', 'Request Time Possible', 'The customer can request a job, estimate, appointment, visit, consultation, or time slot.', 35
FROM me_categories WHERE category_key = 'book_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'book_me.appointment_or_estimate_path', 'Appointment Or Estimate Path', 'There is a clean workflow for appointment, estimate, or job-request intake when relevant.', 40
FROM me_categories WHERE category_key = 'book_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'book_me.booking_expectation_clear', 'Booking Expectation Clear', 'The customer understands whether they are booking directly, requesting a quote, requesting a callback, or asking for availability.', 25
FROM me_categories WHERE category_key = 'book_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'pay_me.payment_path_exists', 'Payment Path Exists', 'There is a payment path when payment before/during booking makes sense.', 35
FROM me_categories WHERE category_key = 'pay_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'pay_me.deposit_path_exists', 'Deposit Path Exists', 'There is a deposit path when deposits are useful or expected.', 25
FROM me_categories WHERE category_key = 'pay_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'pay_me.payment_instructions_clear', 'Payment Instructions Clear', 'The customer can understand how payment works without awkward back-and-forth.', 40
FROM me_categories WHERE category_key = 'pay_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.broken_or_conflicting_info', 'Broken Or Conflicting Info', 'There are broken, outdated, conflicting, or inaccurate business details.', 30
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.outdated_presence', 'Outdated Presence', 'The online presence appears stale, abandoned, or not current.', 20
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.technical_mess', 'Technical Mess', 'There are broken links, dead pages, bad mobile layout, missing images, domain confusion, or other technical issues.', 25
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);

INSERT INTO me_requirements (category_id, requirement_key, requirement_label, requirement_description, importance_weight)
SELECT id, 'fix_me.customer_path_mess', 'Customer Path Mess', 'The customer journey is scattered across too many places or requires too much guessing.', 25
FROM me_categories WHERE category_key = 'fix_me'
ON DUPLICATE KEY UPDATE requirement_label=VALUES(requirement_label), requirement_description=VALUES(requirement_description), importance_weight=VALUES(importance_weight);


-- ===== seed_claim_fields.sql =====
-- Seed Claim Field Definitions v032

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','business_name','Business Name',CAST('{"group": "core_identity", "field_key": "business_name"}' AS JSON),10)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','business_type','Business Type',CAST('{"group": "core_identity", "field_key": "business_type"}' AS JSON),20)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','business_description','Business Description',CAST('{"group": "core_identity", "field_key": "business_description"}' AS JSON),30)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','owner_name','Owner Name',CAST('{"group": "core_identity", "field_key": "owner_name"}' AS JSON),40)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','brand_name_consistency','Brand Name Consistency',CAST('{"group": "core_identity", "field_key": "brand_name_consistency"}' AS JSON),50)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','street_address','Street Address',CAST('{"group": "location_service_area", "field_key": "street_address"}' AS JSON),60)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','city','City',CAST('{"group": "location_service_area", "field_key": "city"}' AS JSON),70)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','state','State',CAST('{"group": "location_service_area", "field_key": "state"}' AS JSON),80)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','service_area','Service Area',CAST('{"group": "location_service_area", "field_key": "service_area"}' AS JSON),90)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','hours_of_operation','Hours Of Operation',CAST('{"group": "location_service_area", "field_key": "hours_of_operation"}' AS JSON),100)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','location_consistency','Location Consistency',CAST('{"group": "location_service_area", "field_key": "location_consistency"}' AS JSON),110)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','website_url','Website Url',CAST('{"group": "public_presence", "field_key": "website_url"}' AS JSON),120)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','google_profile_url','Google Profile Url',CAST('{"group": "public_presence", "field_key": "google_profile_url"}' AS JSON),130)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','facebook_url','Facebook Url',CAST('{"group": "public_presence", "field_key": "facebook_url"}' AS JSON),140)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','instagram_url','Instagram Url',CAST('{"group": "public_presence", "field_key": "instagram_url"}' AS JSON),150)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','directory_listing_url','Directory Listing Url',CAST('{"group": "public_presence", "field_key": "directory_listing_url"}' AS JSON),160)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','single_customer_destination_present','Single Customer Destination Present',CAST('{"group": "public_presence", "field_key": "single_customer_destination_present"}' AS JSON),170)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','public_presence_consistency','Public Presence Consistency',CAST('{"group": "public_presence", "field_key": "public_presence_consistency"}' AS JSON),180)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','phone_number','Phone Number',CAST('{"group": "contact", "field_key": "phone_number"}' AS JSON),190)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','email_address','Email Address',CAST('{"group": "contact", "field_key": "email_address"}' AS JSON),200)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','contact_form_present','Contact Form Present',CAST('{"group": "contact", "field_key": "contact_form_present"}' AS JSON),210)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','request_form_present','Request Form Present',CAST('{"group": "contact", "field_key": "request_form_present"}' AS JSON),220)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','facebook_message_enabled','Facebook Message Enabled',CAST('{"group": "contact", "field_key": "facebook_message_enabled"}' AS JSON),230)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','primary_cta_text','Primary Cta Text',CAST('{"group": "contact", "field_key": "primary_cta_text"}' AS JSON),240)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','confirmation_message_present','Confirmation Message Present',CAST('{"group": "contact", "field_key": "confirmation_message_present"}' AS JSON),250)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','contact_path_clarity','Contact Path Clarity',CAST('{"group": "contact", "field_key": "contact_path_clarity"}' AS JSON),260)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','services_list_present','Services List Present',CAST('{"group": "service_offer", "field_key": "services_list_present"}' AS JSON),270)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','products_list_present','Products List Present',CAST('{"group": "service_offer", "field_key": "products_list_present"}' AS JSON),280)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','menu_present','Menu Present',CAST('{"group": "service_offer", "field_key": "menu_present"}' AS JSON),290)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','pricing_present','Pricing Present',CAST('{"group": "service_offer", "field_key": "pricing_present"}' AS JSON),300)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','package_or_offer_present','Package Or Offer Present',CAST('{"group": "service_offer", "field_key": "package_or_offer_present"}' AS JSON),310)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','service_descriptions_clear','Service Descriptions Clear',CAST('{"group": "service_offer", "field_key": "service_descriptions_clear"}' AS JSON),320)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','customer_use_case_clear','Customer Use Case Clear',CAST('{"group": "service_offer", "field_key": "customer_use_case_clear"}' AS JSON),330)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','photos_present','Photos Present',CAST('{"group": "proof_trust", "field_key": "photos_present"}' AS JSON),340)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','photo_quality','Photo Quality',CAST('{"group": "proof_trust", "field_key": "photo_quality"}' AS JSON),350)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','before_after_present','Before After Present',CAST('{"group": "proof_trust", "field_key": "before_after_present"}' AS JSON),360)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','portfolio_present','Portfolio Present',CAST('{"group": "proof_trust", "field_key": "portfolio_present"}' AS JSON),370)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','reviews_present','Reviews Present',CAST('{"group": "proof_trust", "field_key": "reviews_present"}' AS JSON),380)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','review_count','Review Count',CAST('{"group": "proof_trust", "field_key": "review_count"}' AS JSON),390)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','average_rating','Average Rating',CAST('{"group": "proof_trust", "field_key": "average_rating"}' AS JSON),400)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','testimonials_present','Testimonials Present',CAST('{"group": "proof_trust", "field_key": "testimonials_present"}' AS JSON),410)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','licenses_certifications_present','Licenses Certifications Present',CAST('{"group": "proof_trust", "field_key": "licenses_certifications_present"}' AS JSON),420)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recent_activity_present','Recent Activity Present',CAST('{"group": "proof_trust", "field_key": "recent_activity_present"}' AS JSON),430)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','booking_link_present','Booking Link Present',CAST('{"group": "booking", "field_key": "booking_link_present"}' AS JSON),440)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','appointment_form_present','Appointment Form Present',CAST('{"group": "booking", "field_key": "appointment_form_present"}' AS JSON),450)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','estimate_request_form_present','Estimate Request Form Present',CAST('{"group": "booking", "field_key": "estimate_request_form_present"}' AS JSON),460)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','calendar_link_present','Calendar Link Present',CAST('{"group": "booking", "field_key": "calendar_link_present"}' AS JSON),470)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','preferred_time_field_present','Preferred Time Field Present',CAST('{"group": "booking", "field_key": "preferred_time_field_present"}' AS JSON),480)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','availability_note_present','Availability Note Present',CAST('{"group": "booking", "field_key": "availability_note_present"}' AS JSON),490)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','booking_expectation_text','Booking Expectation Text',CAST('{"group": "booking", "field_key": "booking_expectation_text"}' AS JSON),500)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_link_present','Payment Link Present',CAST('{"group": "payment", "field_key": "payment_link_present"}' AS JSON),510)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','deposit_link_present','Deposit Link Present',CAST('{"group": "payment", "field_key": "deposit_link_present"}' AS JSON),520)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','invoice_link_present','Invoice Link Present',CAST('{"group": "payment", "field_key": "invoice_link_present"}' AS JSON),530)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','checkout_link_present','Checkout Link Present',CAST('{"group": "payment", "field_key": "checkout_link_present"}' AS JSON),540)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_provider_visible','Payment Provider Visible',CAST('{"group": "payment", "field_key": "payment_provider_visible"}' AS JSON),550)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_terms_present','Payment Terms Present',CAST('{"group": "payment", "field_key": "payment_terms_present"}' AS JSON),560)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','payment_path_clarity','Payment Path Clarity',CAST('{"group": "payment", "field_key": "payment_path_clarity"}' AS JSON),570)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','broken_links_present','Broken Links Present',CAST('{"group": "fix_cleanup", "field_key": "broken_links_present"}' AS JSON),580)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','conflicting_phone_numbers','Conflicting Phone Numbers',CAST('{"group": "fix_cleanup", "field_key": "conflicting_phone_numbers"}' AS JSON),590)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','conflicting_hours','Conflicting Hours',CAST('{"group": "fix_cleanup", "field_key": "conflicting_hours"}' AS JSON),600)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','dead_website','Dead Website',CAST('{"group": "fix_cleanup", "field_key": "dead_website"}' AS JSON),610)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','bad_mobile_layout','Bad Mobile Layout',CAST('{"group": "fix_cleanup", "field_key": "bad_mobile_layout"}' AS JSON),620)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','missing_images','Missing Images',CAST('{"group": "fix_cleanup", "field_key": "missing_images"}' AS JSON),630)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','old_posts_or_stale_activity','Old Posts Or Stale Activity',CAST('{"group": "fix_cleanup", "field_key": "old_posts_or_stale_activity"}' AS JSON),640)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','duplicate_profiles','Duplicate Profiles',CAST('{"group": "fix_cleanup", "field_key": "duplicate_profiles"}' AS JSON),650)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','domain_confusion','Domain Confusion',CAST('{"group": "fix_cleanup", "field_key": "domain_confusion"}' AS JSON),660)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','too_much_scrolling_required','Too Much Scrolling Required',CAST('{"group": "fix_cleanup", "field_key": "too_much_scrolling_required"}' AS JSON),670)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','scattered_customer_path','Scattered Customer Path',CAST('{"group": "fix_cleanup", "field_key": "scattered_customer_path"}' AS JSON),680)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','primary_sales_angle','Primary Sales Angle',CAST('{"group": "recommendation", "field_key": "primary_sales_angle"}' AS JSON),690)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recommended_package','Recommended Package',CAST('{"group": "recommendation", "field_key": "recommended_package"}' AS JSON),700)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recommended_design','Recommended Design',CAST('{"group": "recommendation", "field_key": "recommended_design"}' AS JSON),710)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','recommended_features','Recommended Features',CAST('{"group": "recommendation", "field_key": "recommended_features"}' AS JSON),720)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','marketing_clearance_score','Marketing Clearance Score',CAST('{"group": "recommendation", "field_key": "marketing_clearance_score"}' AS JSON),730)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_json, sort_order)
VALUES ('claim_field','marketing_clearance_status','Marketing Clearance Status',CAST('{"group": "recommendation", "field_key": "marketing_clearance_status"}' AS JSON),740)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_json=VALUES(ref_json), sort_order=VALUES(sort_order);


-- ===== seed_scoring_statuses.sql =====
-- Seed Scoring and Status References v032

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','official_website','Official Website','90',10)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','google_business_profile','Google Business Profile','85',20)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','official_facebook_page','Official Facebook Page','80',30)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','official_instagram_tiktok','Official Instagram Tiktok','70',40)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','directory_listing','Directory Listing','55',50)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','email_address_inference','Email Address Inference','35',60)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','manual_visual_inference','Manual Visual Inference','40',70)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('source_confidence_default','unverified_third_party_source','Unverified Third Party Source','30',80)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','cleared','Cleared','Approved for preview generation and outreach.',90)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','warm_clear','Warm Clear','Probably worth outreach, but with softer language and some missing/medium-confidence data.',100)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','needs_review','Needs Review','Promising or uncertain, but not automatically cleared.',110)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','hold','Hold','Possible future prospect, but insufficient information now.',120)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','skip','Skip','Not worth pursuing under the current offer.',130)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('marketing_clearance_status','blocked','Blocked','Do not contact or preview because of a hard blocker, compliance concern, do-not-contact request, severe identity conflict, or similar reason.',140)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('minimum_field_gate','cleared','Cleared','["marketing_clearance_score >= 75", "business_name confidence >= 70", "business_type confidence >= 60", "city or service_area confidence >= 60", "Business Activity Score >= 11", "Contactability Score >= 5", "Need Score >= 10", "Fit Score >= 12", "Confidence Score >= 9", "Buildability Score >= 5", "primary_weakness confidence >= 70", "no hard blockers"]',150)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('minimum_field_gate','warm_clear','Warm Clear','["marketing_clearance_score >= 60", "business_name confidence >= 60", "business_type confidence >= 50", "at least one usable contact method", "no hard blockers", "business appears active enough", "at least one likely Front Door weakness"]',160)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','business_name','Business Name','70',170)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','business_type','Business Type','60',180)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','primary_weakness','Primary Weakness','70',190)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','specific_critique','Specific Critique','75',200)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);

INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES ('outreach_threshold','owner_name','Owner Name','85',210)
ON DUPLICATE KEY UPDATE ref_label=VALUES(ref_label), ref_value=VALUES(ref_value), sort_order=VALUES(sort_order);
