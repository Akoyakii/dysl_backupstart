-- Migration: Add data entry lock and semester management.
-- Import into the existing ScholarHub database.

ALTER TABLE record_write_lock
    ADD COLUMN data_entry_locked TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER session_epoch,
    ADD COLUMN lock_reason VARCHAR(255) NOT NULL DEFAULT '' AFTER data_entry_locked;

ALTER TABLE scholarship_types
    ADD COLUMN disabled TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER benefit;

CREATE TABLE IF NOT EXISTS semesters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    school_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY year_semester (school_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
