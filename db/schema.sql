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
