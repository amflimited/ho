-- 004_receptionist.sql — AI receptionist demo track (call_demos + settings)

CREATE TABLE IF NOT EXISTS `call_demos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `business_id` INT UNSIGNED NOT NULL,
  `scenario` VARCHAR(40) NOT NULL,
  `label` VARCHAR(80) NOT NULL,
  `transcript` TEXT NOT NULL,
  `audio_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_demo` (`business_id`, `scenario`),
  CONSTRAINT `fk_demo_biz` FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('tts_api_key', ''),
('rcpt_price_cents', '14900'),
('ap_voice_per_run', '3');
