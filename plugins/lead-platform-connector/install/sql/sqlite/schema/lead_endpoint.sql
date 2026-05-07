CREATE TABLE IF NOT EXISTS `lead_endpoint` (
  `lead_endpoint_id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `slug` TEXT NOT NULL UNIQUE,
  `label` TEXT NOT NULL DEFAULT '',
  `platform_url` TEXT NOT NULL DEFAULT '',
  `api_key_enc` TEXT NOT NULL DEFAULT '',
  `campaign` TEXT NOT NULL DEFAULT '',
  `field_map` TEXT DEFAULT NULL,
  `allowed_origins` TEXT DEFAULT NULL,
  `rate_limit` INTEGER NOT NULL DEFAULT 30,
  `active` TINYINT NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS `lead_endpoint_active` ON `lead_endpoint` (`active`,`slug`);
