INSERT INTO lead_endpoint (slug,label,platform_url,api_key_enc,campaign,field_map,allowed_origins,rate_limit,active,created_at,updated_at)
VALUES ('solution-registration','Référencement de solutions','','','solution-registration',NULL,NULL,10,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE label=VALUES(label),platform_url='',api_key_enc='',campaign=VALUES(campaign),field_map=NULL,allowed_origins=NULL,rate_limit=VALUES(rate_limit),active=1,updated_at=CURRENT_TIMESTAMP;
