CREATE TABLE IF NOT EXISTS `lead_endpoint` (
  `lead_endpoint_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `label` varchar(128) NOT NULL DEFAULT '',
  `platform_url` varchar(255) NOT NULL DEFAULT '',
  `api_key_enc` text NOT NULL,
  `campaign` varchar(128) NOT NULL DEFAULT '',
  `field_map` text DEFAULT NULL,
  `allowed_origins` text DEFAULT NULL,
  `rate_limit` INT NOT NULL DEFAULT 30,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`lead_endpoint_id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `active_slug` (`active`,`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
