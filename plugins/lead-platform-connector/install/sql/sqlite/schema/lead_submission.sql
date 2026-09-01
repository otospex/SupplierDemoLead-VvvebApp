CREATE TABLE IF NOT EXISTS `lead_submission` (
  `lead_submission_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `endpoint_slug` TEXT NOT NULL,
  `status` TEXT NOT NULL DEFAULT 'pending',
  `platform_lead_id` TEXT DEFAULT NULL,
  `http_status` INTEGER DEFAULT NULL,
  `phone_hash` TEXT DEFAULT NULL,
  `email_hash` TEXT DEFAULT NULL,
  `provider_slug` TEXT DEFAULT NULL,
  `consent_text_version` TEXT DEFAULT NULL,
  `consent_at` datetime DEFAULT NULL,
  `payload` TEXT DEFAULT NULL,
  `payload_enc` TEXT DEFAULT NULL,
  `response` TEXT DEFAULT NULL,
  `error` TEXT DEFAULT NULL,
  `client_ip` TEXT DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `source_page` TEXT DEFAULT NULL,
  `attempts` TINYINT NOT NULL DEFAULT 0,
  `stage` INTEGER NOT NULL DEFAULT 3,
  `lead_token_hash` TEXT DEFAULT NULL,
  `lead_token_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS `lead_submission_status_date` ON `lead_submission` (`status`,`created_at`,`lead_submission_id`);
CREATE INDEX IF NOT EXISTS `lead_submission_endpoint_date` ON `lead_submission` (`endpoint_slug`,`created_at`);
CREATE UNIQUE INDEX IF NOT EXISTS `lead_submission_lead_token_hash_unique` ON `lead_submission` (`lead_token_hash`);
