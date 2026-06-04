-- Hoosier Online Preview Schema Additions v044
-- Purpose:
-- Add preview readiness, preview option catalog, address options,
-- customer choice capture, and build handoff linking support.
--
-- Safe to run after v032 base schema.
-- Does not remove or overwrite existing data.

CREATE TABLE IF NOT EXISTS preview_readiness (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  readiness_status ENUM('ready','soft_ready','needs_more_research','manual_review','blocked') NOT NULL DEFAULT 'needs_more_research',
  readiness_score DECIMAL(5,2) DEFAULT NULL,
  customer_safe_summary TEXT DEFAULT NULL,
  internal_review_notes TEXT DEFAULT NULL,
  missing_inputs_json JSON DEFAULT NULL,
  blocked_reason VARCHAR(190) DEFAULT NULL,
  last_evaluated_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_preview_readiness_business (business_id),
  KEY idx_preview_readiness_status (readiness_status),
  CONSTRAINT fk_preview_readiness_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preview_option_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_key VARCHAR(120) NOT NULL,
  group_label VARCHAR(190) NOT NULL,
  business_type_key VARCHAR(120) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_preview_option_group_key (group_key),
  KEY idx_preview_group_business_type (business_type_key),
  KEY idx_preview_group_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preview_design_options (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id INT UNSIGNED NOT NULL,
  option_key VARCHAR(120) NOT NULL,
  option_label VARCHAR(190) NOT NULL,
  short_description TEXT DEFAULT NULL,
  recommended_for TEXT DEFAULT NULL,
  layout_family VARCHAR(120) DEFAULT NULL,
  tone VARCHAR(120) DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_preview_design_option_key (option_key),
  KEY idx_preview_design_group (group_id),
  KEY idx_preview_design_active (is_active),
  CONSTRAINT fk_preview_design_group
    FOREIGN KEY (group_id) REFERENCES preview_option_groups(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preview_address_options (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED DEFAULT NULL,
  preview_id BIGINT UNSIGNED DEFAULT NULL,
  option_type ENUM('included_hoosier_subdomain','local_service_hoosier_subdomain','custom_domain_idea','undecided_help_me_choose') NOT NULL,
  address_value VARCHAR(255) NOT NULL,
  display_label VARCHAR(255) DEFAULT NULL,
  availability_status ENUM('suggested','reserved','claimed','unavailable','needs_check') NOT NULL DEFAULT 'suggested',
  is_recommended TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 100,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_preview_address_business (business_id),
  KEY idx_preview_address_preview (preview_id),
  KEY idx_preview_address_type (option_type),
  KEY idx_preview_address_status (availability_status),
  CONSTRAINT fk_preview_address_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_preview_address_preview
    FOREIGN KEY (preview_id) REFERENCES prospect_previews(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preview_customer_choices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  preview_id BIGINT UNSIGNED DEFAULT NULL,
  selected_design_option_id INT UNSIGNED DEFAULT NULL,
  selected_address_option_id BIGINT UNSIGNED DEFAULT NULL,
  selected_package ENUM('standard','managed','unknown') NOT NULL DEFAULT 'unknown',
  confirmed_business_name VARCHAR(255) DEFAULT NULL,
  confirmed_phone VARCHAR(80) DEFAULT NULL,
  confirmed_email VARCHAR(255) DEFAULT NULL,
  confirmed_service_area TEXT DEFAULT NULL,
  preferred_contact_method VARCHAR(120) DEFAULT NULL,
  customer_notes TEXT DEFAULT NULL,
  raw_choice_json JSON DEFAULT NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_preview_customer_choices_business (business_id),
  KEY idx_preview_customer_choices_preview (preview_id),
  KEY idx_preview_customer_choices_design (selected_design_option_id),
  KEY idx_preview_customer_choices_address (selected_address_option_id),
  CONSTRAINT fk_preview_customer_choices_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_preview_customer_choices_preview
    FOREIGN KEY (preview_id) REFERENCES prospect_previews(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_preview_customer_choices_design
    FOREIGN KEY (selected_design_option_id) REFERENCES preview_design_options(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_preview_customer_choices_address
    FOREIGN KEY (selected_address_option_id) REFERENCES preview_address_options(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS preview_build_handoff_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id BIGINT UNSIGNED NOT NULL,
  preview_id BIGINT UNSIGNED DEFAULT NULL,
  preview_choice_id BIGINT UNSIGNED DEFAULT NULL,
  build_handoff_id BIGINT UNSIGNED DEFAULT NULL,
  handoff_status ENUM('not_created','ready_to_create','created','needs_review','blocked') NOT NULL DEFAULT 'not_created',
  handoff_notes TEXT DEFAULT NULL,
  missing_inputs_json JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_preview_build_link_business (business_id),
  KEY idx_preview_build_link_preview (preview_id),
  KEY idx_preview_build_link_choice (preview_choice_id),
  KEY idx_preview_build_link_handoff (build_handoff_id),
  KEY idx_preview_build_link_status (handoff_status),
  CONSTRAINT fk_preview_build_link_business
    FOREIGN KEY (business_id) REFERENCES businesses(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_preview_build_link_preview
    FOREIGN KEY (preview_id) REFERENCES prospect_previews(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_preview_build_link_choice
    FOREIGN KEY (preview_choice_id) REFERENCES preview_customer_choices(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_preview_build_link_handoff
    FOREIGN KEY (build_handoff_id) REFERENCES build_handoffs(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backward-compatible metadata references.
INSERT INTO salesportal_reference (ref_type, ref_key, ref_label, ref_value, sort_order)
VALUES
('schema_version','preview_schema','Preview Schema','v044',440),
('preview_doctrine','no_preview_php_yet','No Preview PHP Yet','v044 only prepares schema and seeds. Customer-facing preview.php remains intentionally unbuilt.',10),
('preview_doctrine','no_scraping_yet','No Scraping Yet','Bulk scraping remains blocked until preview/payment/build handoff is proven manually.',20)
ON DUPLICATE KEY UPDATE
  ref_label=VALUES(ref_label),
  ref_value=VALUES(ref_value),
  sort_order=VALUES(sort_order);
