-- Hoosier Online v2 Schema
-- Fresh start. Run this in phpMyAdmin against spofnkte_db.
-- Drops all old tables, creates the 7 new ones.
--
-- HOW TO RUN IN phpMyAdmin:
--   1. Select database spofnkte_db
--   2. Click the Import tab → choose this file → Go
--   OR paste into the SQL tab and click Go (runs all at once, same session)

SET FOREIGN_KEY_CHECKS = 0;

-- Drop child tables (FK dependents) before parents
DROP TABLE IF EXISTS preview_build_handoff_links;
DROP TABLE IF EXISTS preview_address_options;
DROP TABLE IF EXISTS preview_choices;
DROP TABLE IF EXISTS preview_customer_choices;
DROP TABLE IF EXISTS preview_design_options;
DROP TABLE IF EXISTS preview_events;
DROP TABLE IF EXISTS preview_option_groups;
DROP TABLE IF EXISTS preview_readiness;
DROP TABLE IF EXISTS prospect_previews;
DROP TABLE IF EXISTS previews;
DROP TABLE IF EXISTS outreach_drafts;
DROP TABLE IF EXISTS outreach_events;
DROP TABLE IF EXISTS outreach_log;
DROP TABLE IF EXISTS contact_attempts;
DROP TABLE IF EXISTS business_me_scores;
DROP TABLE IF EXISTS business_requirement_scores;
DROP TABLE IF EXISTS business_sources;
DROP TABLE IF EXISTS business_claims;
DROP TABLE IF EXISTS research_profiles;
DROP TABLE IF EXISTS research_records;
DROP TABLE IF EXISTS source_candidates;
DROP TABLE IF EXISTS source_runs;
DROP TABLE IF EXISTS evidence_sources;
DROP TABLE IF EXISTS sales_assets;
DROP TABLE IF EXISTS salesportal_reference;
DROP TABLE IF EXISTS me_requirements;
DROP TABLE IF EXISTS me_categories;
DROP TABLE IF EXISTS market_coverage;
DROP TABLE IF EXISTS build_handoffs;
DROP TABLE IF EXISTS businesses;
DROP TABLE IF EXISTS categories;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── 1. categories ───────────────────────────────────────────────────────────

CREATE TABLE categories (
  id              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(60)       NOT NULL,
  name            VARCHAR(120)      NOT NULL,
  typical_services JSON             NOT NULL,
  active          TINYINT(1)        NOT NULL DEFAULT 1,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. source_runs ──────────────────────────────────────────────────────────

CREATE TABLE source_runs (
  id                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  run_uid           VARCHAR(40)     NOT NULL,
  category_id       INT UNSIGNED    NOT NULL,
  area_query        VARCHAR(200)    NOT NULL,
  target_count      SMALLINT UNSIGNED NOT NULL DEFAULT 25,
  status            ENUM('ready','sourced','imported') NOT NULL DEFAULT 'ready',
  businesses_found  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_source_runs_uid (run_uid),
  KEY idx_source_runs_category (category_id),
  CONSTRAINT fk_source_runs_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. source_candidates ────────────────────────────────────────────────────

CREATE TABLE source_candidates (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  candidate_uid        VARCHAR(40)   NOT NULL,
  source_run_id        INT UNSIGNED  NOT NULL,
  category_id          INT UNSIGNED  NOT NULL,
  raw_name             VARCHAR(200)  NOT NULL,
  city                 VARCHAR(100)  NOT NULL DEFAULT '',
  state                VARCHAR(2)    NOT NULL DEFAULT 'IN',
  website_url          VARCHAR(500)  NOT NULL DEFAULT '',
  facebook_url         VARCHAR(500)  NOT NULL DEFAULT '',
  google_url           VARCHAR(500)  NOT NULL DEFAULT '',
  phone                VARCHAR(30)   NOT NULL DEFAULT '',
  email                VARCHAR(200)  NOT NULL DEFAULT '',
  candidate_status     ENUM('new','promoted','rejected','duplicate') NOT NULL DEFAULT 'new',
  rejection_reason     VARCHAR(200)  DEFAULT NULL,
  promoted_business_id INT UNSIGNED  DEFAULT NULL,
  raw_payload          JSON          DEFAULT NULL,
  created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_candidates_uid (candidate_uid),
  KEY idx_candidates_run (source_run_id),
  KEY idx_candidates_status (candidate_status),
  CONSTRAINT fk_candidates_run      FOREIGN KEY (source_run_id) REFERENCES source_runs (id),
  CONSTRAINT fk_candidates_category FOREIGN KEY (category_id)   REFERENCES categories  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. businesses ───────────────────────────────────────────────────────────

CREATE TABLE businesses (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  business_uid         VARCHAR(40)   NOT NULL,
  business_slug        VARCHAR(200)  NOT NULL,
  business_name        VARCHAR(200)  NOT NULL,
  category_id          INT UNSIGNED  NOT NULL,
  location_city        VARCHAR(100)  NOT NULL DEFAULT '',
  location_state       VARCHAR(2)    NOT NULL DEFAULT 'IN',
  location_county      VARCHAR(100)  NOT NULL DEFAULT '',
  website_url          VARCHAR(500)  NOT NULL DEFAULT '',
  facebook_url         VARCHAR(500)  NOT NULL DEFAULT '',
  instagram_url        VARCHAR(500)  NOT NULL DEFAULT '',
  google_business_url  VARCHAR(500)  NOT NULL DEFAULT '',
  phone_number         VARCHAR(30)   NOT NULL DEFAULT '',
  email_address        VARCHAR(200)  NOT NULL DEFAULT '',
  best_contact_method  ENUM('email','phone','facebook','website_form','unknown') NOT NULL DEFAULT 'unknown',
  pipeline_status      ENUM('identified','researched','preview_ready','pitched','converted','not_a_fit') NOT NULL DEFAULT 'identified',
  source_candidate_id  INT UNSIGNED  DEFAULT NULL,
  notes                TEXT          DEFAULT NULL,
  created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_businesses_uid  (business_uid),
  UNIQUE KEY uq_businesses_slug (business_slug),
  KEY idx_businesses_pipeline  (pipeline_status),
  KEY idx_businesses_category  (category_id),
  CONSTRAINT fk_businesses_category FOREIGN KEY (category_id) REFERENCES categories (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. research_records ─────────────────────────────────────────────────────

CREATE TABLE research_records (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  business_id          INT UNSIGNED  NOT NULL,
  has_website          TINYINT(1)    NOT NULL DEFAULT 0,
  website_quality      ENUM('none','poor','basic','decent') NOT NULL DEFAULT 'none',
  website_notes        VARCHAR(500)  NOT NULL DEFAULT '',
  has_google_business  TINYINT(1)    NOT NULL DEFAULT 0,
  google_review_count  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  google_rating        DECIMAL(3,1)  NOT NULL DEFAULT 0.0,
  google_notes         VARCHAR(500)  NOT NULL DEFAULT '',
  has_facebook         TINYINT(1)    NOT NULL DEFAULT 0,
  facebook_activity    ENUM('none','dormant','active') NOT NULL DEFAULT 'none',
  facebook_notes       VARCHAR(500)  NOT NULL DEFAULT '',
  has_instagram        TINYINT(1)    NOT NULL DEFAULT 0,
  instagram_activity   ENUM('none','dormant','active') NOT NULL DEFAULT 'none',
  services_list        JSON          DEFAULT NULL,
  service_area_text    VARCHAR(500)  NOT NULL DEFAULT '',
  opportunity_summary  TEXT          DEFAULT NULL,
  strengths            JSON          DEFAULT NULL,
  gaps                 JSON          DEFAULT NULL,
  recommended_package  ENUM('standard','managed') NOT NULL DEFAULT 'standard',
  research_status      ENUM('pending','complete','needs_review') NOT NULL DEFAULT 'pending',
  research_method      ENUM('gpt_assisted','manual') NOT NULL DEFAULT 'gpt_assisted',
  researched_at        TIMESTAMP     NULL DEFAULT NULL,
  created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_research_business (business_id),
  KEY idx_research_status (research_status),
  CONSTRAINT fk_research_business FOREIGN KEY (business_id) REFERENCES businesses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 6. previews ─────────────────────────────────────────────────────────────

CREATE TABLE previews (
  id                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  business_id            INT UNSIGNED  NOT NULL,
  preview_slug           VARCHAR(200)  NOT NULL,
  preview_status         ENUM('draft','ready','sent','expired') NOT NULL DEFAULT 'draft',
  headline               VARCHAR(300)  NOT NULL DEFAULT '',
  subheadline            VARCHAR(500)  NOT NULL DEFAULT '',
  services_display       JSON          DEFAULT NULL,
  opportunity_statement  TEXT          DEFAULT NULL,
  package_recommendation ENUM('standard','managed') NOT NULL DEFAULT 'standard',
  view_count             INT UNSIGNED  NOT NULL DEFAULT 0,
  last_viewed_at         TIMESTAMP     NULL DEFAULT NULL,
  generated_at           TIMESTAMP     NULL DEFAULT NULL,
  created_at             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_previews_business (business_id),
  UNIQUE KEY uq_previews_slug    (preview_slug),
  KEY idx_previews_status (preview_status),
  CONSTRAINT fk_previews_business FOREIGN KEY (business_id) REFERENCES businesses (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 7. outreach_log ─────────────────────────────────────────────────────────

CREATE TABLE outreach_log (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  business_id  INT UNSIGNED  NOT NULL,
  preview_id   INT UNSIGNED  DEFAULT NULL,
  sent_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_via     ENUM('email','facebook_dm','phone','website_form','other') NOT NULL DEFAULT 'other',
  sent_to      VARCHAR(500)  NOT NULL DEFAULT '',
  outcome      ENUM('pending','no_response','interested','not_interested','converted') NOT NULL DEFAULT 'pending',
  follow_up_at DATE          DEFAULT NULL,
  notes        TEXT          DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_outreach_business (business_id),
  KEY idx_outreach_outcome  (outcome),
  CONSTRAINT fk_outreach_business FOREIGN KEY (business_id) REFERENCES businesses (id),
  CONSTRAINT fk_outreach_preview  FOREIGN KEY (preview_id)  REFERENCES previews   (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
