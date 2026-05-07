CREATE SEQUENCE IF NOT EXISTS lead_endpoint_seq;

CREATE TABLE IF NOT EXISTS lead_endpoint (
  "lead_endpoint_id" int check ("lead_endpoint_id" > 0) NOT NULL DEFAULT NEXTVAL ('lead_endpoint_seq'),
  "slug" varchar(64) NOT NULL,
  "label" varchar(128) NOT NULL DEFAULT '',
  "platform_url" varchar(255) NOT NULL DEFAULT '',
  "api_key_enc" text NOT NULL,
  "campaign" varchar(128) NOT NULL DEFAULT '',
  "field_map" text DEFAULT NULL,
  "allowed_origins" text DEFAULT NULL,
  "rate_limit" int NOT NULL DEFAULT 30,
  "active" smallint NOT NULL DEFAULT 1,
  "created_at" timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY ("lead_endpoint_id"),
  UNIQUE ("slug")
);

CREATE INDEX IF NOT EXISTS "lead_endpoint_active" ON lead_endpoint ("active","slug");
