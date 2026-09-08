ALTER TABLE settings ADD COLUMN totp_secret TEXT;
ALTER TABLE settings ADD COLUMN totp_counter INTEGER NOT NULL DEFAULT -1;
CREATE TABLE recovery_codes (hash TEXT PRIMARY KEY);
ALTER TABLE jobs ADD COLUMN security_report TEXT;
