CREATE SEQUENCE IF NOT EXISTS lead_submission_seq;

CREATE TABLE IF NOT EXISTS lead_submission (
  "lead_submission_id" int check ("lead_submission_id" > 0) NOT NULL DEFAULT NEXTVAL ('lead_submission_seq'),
  "endpoint_slug" varchar(64) NOT NULL,
  "status" varchar(20) NOT NULL DEFAULT 'pending',
  "platform_lead_id" varchar(64) DEFAULT NULL,
  "http_status" int DEFAULT NULL,
  "phone_hash" varchar(64) DEFAULT NULL,
  "email_hash" varchar(64) DEFAULT NULL,
  "payload" text DEFAULT NULL,
  "response" text DEFAULT NULL,
  "error" text DEFAULT NULL,
  "client_ip" varchar(45) DEFAULT NULL,
  "user_agent" varchar(255) DEFAULT NULL,
  "source_page" varchar(255) DEFAULT NULL,
  "attempts" smallint NOT NULL DEFAULT 0,
  "created_at" timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "updated_at" timestamp(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY ("lead_submission_id")
);

CREATE INDEX IF NOT EXISTS "lead_submission_status_date" ON lead_submission ("status","created_at","lead_submission_id");
CREATE INDEX IF NOT EXISTS "lead_submission_endpoint_date" ON lead_submission ("endpoint_slug","created_at");
