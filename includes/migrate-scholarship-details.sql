-- Adds editable scholarship descriptions without changing existing assignments.
ALTER TABLE scholarship_types
    ADD COLUMN IF NOT EXISTS description TEXT NULL AFTER category;
