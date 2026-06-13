-- 005_autopilot.sql — autopilot motor: source worker settings + double-send guard

INSERT IGNORE INTO `app_settings` (`setting_key`, `setting_value`) VALUES
('ap_source_per_run',     '8'),
('ap_personalize_per_run','5'),
('ap_source_area_idx',    '0'),
('ap_last_run',           ''),
('ap_last_run_summary',   '');

ALTER TABLE `outreach_log`
  ADD UNIQUE KEY IF NOT EXISTS `uq_biz_touch` (`business_id`, `touch_number`);
