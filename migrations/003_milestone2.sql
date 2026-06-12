-- 003_milestone2.sql — pitch drafts table + safe default settings

CREATE TABLE IF NOT EXISTS `pitch_drafts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `touch` TINYINT NOT NULL DEFAULT 1,
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `source` ENUM('ai','template') NOT NULL DEFAULT 'template',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_biz_touch` (`business_id`, `touch`),
  CONSTRAINT `fk_draft_biz` FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Safe defaults: autopilot OFF until the operator flips it deliberately.
INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('ap_master', '0'),
('ap_digest', '1'),
('ap_daily_cap', '30'),
('ap_pitch_per_run', '5'),
('ap_research_per_run', '3'),
('ap_verify_per_run', '3'),
('llm_provider', 'anthropic'),
('ap_site_base', 'https://v2.hoosieronline.com');
