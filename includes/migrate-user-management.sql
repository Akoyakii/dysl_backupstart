-- Run once on the existing ScholarHub database before uploading the PHP changes.
ALTER TABLE accounts
    ADD COLUMN disabled_at DATETIME DEFAULT NULL,
    ADD COLUMN access_version INT UNSIGNED NOT NULL DEFAULT 0;
