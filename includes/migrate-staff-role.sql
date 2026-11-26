-- Select the existing ScholarHub database before importing this file.
ALTER TABLE accounts MODIFY COLUMN role ENUM('admin', 'viewer', 'staff') NOT NULL;
ALTER TABLE audit_log MODIFY COLUMN actor_role ENUM('admin', 'viewer', 'staff') NOT NULL;
