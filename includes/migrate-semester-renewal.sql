-- Migration: Add scholarship_assignments table for semester renewals.
-- Import into the existing ScholarHub database in phpMyAdmin before deploying.

CREATE TABLE IF NOT EXISTS scholarship_assignments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(64) NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    course VARCHAR(255) NOT NULL,
    scholarship VARCHAR(120) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    semester VARCHAR(32) NOT NULL,
    status ENUM('Approved', 'Pending', 'Not Approved') NOT NULL DEFAULT 'Pending',
    source_year VARCHAR(20) DEFAULT NULL,
    source_semester VARCHAR(32) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY assignment_unique (student_id, scholarship, school_year, semester),
    KEY renewal_queue_idx (status, school_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
