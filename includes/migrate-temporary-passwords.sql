-- Run after migrate-staff-role.sql and migrate-user-management.sql on an existing installation.
ALTER TABLE accounts ADD COLUMN temporary_password_expires DATETIME DEFAULT NULL;
UPDATE accounts
SET disabled_at = COALESCE(disabled_at, UTC_TIMESTAMP()), access_version = access_version + 1
WHERE role = 'viewer' AND NOT EXISTS (
    SELECT 1 FROM signup_requests
    WHERE signup_requests.email = CONVERT(accounts.email USING utf8mb4) COLLATE utf8mb4_unicode_ci
);
UPDATE accounts SET role = 'staff' WHERE role = 'viewer';
ALTER TABLE accounts MODIFY COLUMN role ENUM('admin', 'staff') NOT NULL;
