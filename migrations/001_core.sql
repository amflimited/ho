-- 001_core.sql — hand-written core tables (business_profile is generated separately)

CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `filename` VARCHAR(190) NOT NULL PRIMARY KEY,
  `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `name` VARCHAR(190) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `businesses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_uid` VARCHAR(40) NOT NULL UNIQUE,
  `business_slug` VARCHAR(200) NOT NULL UNIQUE,
  `business_name` VARCHAR(200) NOT NULL,
  `category_id` INT UNSIGNED NULL,
  `location_city` VARCHAR(120) NOT NULL,
  `location_state` CHAR(2) NOT NULL DEFAULT 'IN',
  `location_county` VARCHAR(120) NULL,
  `website_url` TEXT NULL,
  `facebook_url` TEXT NULL,
  `instagram_url` TEXT NULL,
  `google_business_url` TEXT NULL,
  `phone_number` VARCHAR(30) NULL,
  `email_address` VARCHAR(190) NULL,
  `best_contact_method` ENUM('email','phone','facebook','website_form','unknown') NOT NULL DEFAULT 'unknown',
  `owner_first_name` VARCHAR(100) NULL,
  `pipeline_status` ENUM('identified','researched','preview_ready','enhancement_ready','pitched','converted','not_a_fit','needs_contact','excluded') NOT NULL DEFAULT 'identified',
  `triaged` TINYINT(1) NOT NULL DEFAULT 0,
  `website_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `fit_score` INT NOT NULL DEFAULT 0,
  `fit_score_version` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_pipeline` (`pipeline_status`, `triaged`),
  KEY `idx_score` (`fit_score` DESC),
  UNIQUE KEY `uq_name_city` (`business_name`, `location_city`),
  CONSTRAINT `fk_biz_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- THE table the machine must consult before every single send. No exceptions.
CREATE TABLE IF NOT EXISTS `suppression` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(190) NULL,
  `domain` VARCHAR(190) NULL,
  `business_id` INT UNSIGNED NULL,
  `reason` ENUM('unsubscribe','bounce','complaint','not_a_fit','manual','v1_import') NOT NULL,
  `note` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_domain` (`domain`),
  KEY `idx_biz` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `previews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `preview_slug` VARCHAR(200) NOT NULL UNIQUE,
  `preview_status` ENUM('draft','ready','sent','expired') NOT NULL DEFAULT 'draft',
  `preview_type` ENUM('site_build','enhancement') NOT NULL,
  `headline` VARCHAR(255) NULL,
  `subheadline` VARCHAR(255) NULL,
  `services_display` JSON NULL,
  `opportunity_statement` TEXT NULL,
  `package_recommendation` ENUM('standard','managed') NULL,
  `package_items` JSON NULL,
  `selected_template` VARCHAR(120) NULL,
  `view_count` INT NOT NULL DEFAULT 0,
  `last_viewed_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_preview_biz` FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `outreach_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `sent_via` ENUM('email','facebook_dm','phone','website_form','other') NOT NULL,
  `touch_number` TINYINT NOT NULL DEFAULT 1,
  `subject` VARCHAR(255) NULL,
  `body` TEXT NULL,
  `sequence_version` VARCHAR(40) NOT NULL DEFAULT 'v2.0',
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `follow_up_at` DATE NULL,
  `outcome` ENUM('pending','no_response','interested','not_interested','converted') NOT NULL DEFAULT 'pending',
  KEY `idx_biz_touch` (`business_id`, `touch_number`),
  KEY `idx_followup` (`follow_up_at`, `outcome`),
  CONSTRAINT `fk_outreach_biz` FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NULL,
  `kind` VARCHAR(40) NOT NULL DEFAULT 'pitch',
  `touch` TINYINT NOT NULL DEFAULT 1,
  `sent_to` VARCHAR(190) NOT NULL,
  `subject` VARCHAR(255) NULL,
  `ok` TINYINT(1) NOT NULL DEFAULT 0,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `preview_visits` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `preview_id` INT UNSIGNED NOT NULL,
  `ip_hash` CHAR(64) NOT NULL,
  `visited_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_preview` (`preview_id`, `visited_at`),
  CONSTRAINT `fk_visit_preview` FOREIGN KEY (`preview_id`) REFERENCES `previews`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `status_token` VARCHAR(64) NOT NULL UNIQUE,
  `package` ENUM('standard','launch','managed','reputation','app_engine') NOT NULL,
  `template_key` VARCHAR(120) NULL,
  `chosen_domain` VARCHAR(190) NULL,
  `amount_cents` INT NOT NULL DEFAULT 0,
  `stripe_session_id` VARCHAR(190) NULL UNIQUE,
  `domain_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `hosting_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `design_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `launch_status` VARCHAR(40) NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_order_biz` FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `captured_leads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(190) NULL,
  `contact` VARCHAR(190) NOT NULL,
  `message` TEXT NULL,
  `forwarded_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_lead_biz` FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` VARCHAR(190) NOT NULL PRIMARY KEY,
  `setting_value` LONGTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `gap_prices` (
  `gap_key` VARCHAR(60) NOT NULL PRIMARY KEY,
  `label` VARCHAR(190) NOT NULL,
  `price_cents` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `gap_prices` (`gap_key`, `label`, `price_cents`) VALUES
('tech_issues','Mobile & security fixes',9900),
('contact_form','Contact form setup',9900),
('online_booking','Online booking',9900),
('site_outdated','Site refresh',9900),
('paid_leads','Stop paying per lead',9900),
('google_business','Google Business Profile setup',9900),
('gbp_incomplete','Complete your Google profile',9900),
('gbp_photos','Google profile photos',9900),
('stale_reviews','Fresh review generation',4900),
('no_before_after','Before & after showcase',4900),
('no_gallery','Photo gallery',4900),
('no_testimonials','Testimonials section',4900),
('dead_facebook','Facebook revival',4900),
('freemail','Professional email',4900),
('no_trust_signals','License & insurance badges',4900),
('yelp_unclaimed','Claim your Yelp listing',4900);
