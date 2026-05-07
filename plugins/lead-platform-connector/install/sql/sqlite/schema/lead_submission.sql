CREATE TABLE IF NOT EXISTS `lead_submission` (
  `lead_submission_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `endpoint_slug` TEXT NOT NULL,
  `status` TEXT NOT NULL DEFAULT 'pending',
  `platform_lead_id` TEXT DEFAULT NULL,
  `http_status` INTEGER DEFAULT NULL,
  `phone_hash` TEXT DEFAULT NULL,
  `email_hash` TEXT DEFAULT NULL,
  `payload` TEXT DEFAULT NULL,
  `response` TEXT DEFAULT NULL,
  `error` TEXT DEFAULT NULL,
  `client_ip` TEXT DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `source_page` TEXT DEFAULT NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS `lead_submission_status_date` ON `lead_submission` (`status`,`created_at`,`lead_submission_id`);
CREATE INDEX IF NOT EXISTS `lead_submission_endpoint_date` ON `lead_submission` (`endpoint_slug`,`created_at`);
