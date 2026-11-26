-- ScholarHub MySQL schema. Select your existing database in phpMyAdmin before importing.
-- The records table stores students, discount history, and activity.
-- Reports are calculated from discount history. The app supplies the scholarship catalog.

CREATE TABLE IF NOT EXISTS records (
    record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kind ENUM('scholars', 'discountHistory', 'activities') NOT NULL,
    student_id VARBINARY(255) DEFAULT NULL,
    payload JSON NOT NULL,
    UNIQUE KEY scholars_by_id (student_id)
) ENGINE=InnoDB;

-- Lock this row before adding or restoring records to serialize writes.
CREATE TABLE IF NOT EXISTS record_write_lock (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    session_epoch INT UNSIGNED NOT NULL DEFAULT 0,
    data_entry_locked TINYINT UNSIGNED NOT NULL DEFAULT 0,
    lock_reason VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS database_backups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('manual', 'weekly', 'recovery') NOT NULL,
    created_at DATETIME NOT NULL,
    row_count INT UNSIGNED NOT NULL,
    payload LONGBLOB NOT NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO record_write_lock (id) VALUES (1);

CREATE TABLE IF NOT EXISTS accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    role ENUM('admin', 'staff') NOT NULL,
    disabled_at DATETIME DEFAULT NULL,
    access_version INT UNSIGNED NOT NULL DEFAULT 0,
    temporary_password_expires DATETIME DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    invitation_hash CHAR(64) DEFAULT NULL,
    invitation_expires BIGINT UNSIGNED DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS request_limits (
    bucket CHAR(64) NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    requests INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket, window_start),
    KEY expired_windows (window_start)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor_id BIGINT UNSIGNED NOT NULL,
    actor_role ENUM('admin', 'viewer', 'staff') NOT NULL,
    action VARCHAR(48) NOT NULL,
    details JSON NOT NULL,
    occurred_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS signup_requests (
    email VARCHAR(254) NOT NULL PRIMARY KEY,
    requested_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS signup_verifications (
    email VARCHAR(254) NOT NULL PRIMARY KEY,
    invitation_hash CHAR(64) DEFAULT NULL,
    invitation_expires BIGINT UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scholarship_types (
    name VARCHAR(120) NOT NULL PRIMARY KEY,
    category VARCHAR(80) NOT NULL,
    coverage VARCHAR(255) NOT NULL,
    benefit VARCHAR(100) NOT NULL,
    disabled TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS semesters (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    school_year VARCHAR(20) NOT NULL,
    semester VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY year_semester (school_year, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_archive (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(48) NOT NULL,
    generated_by BIGINT UNSIGNED NOT NULL,
    generated_at DATETIME NOT NULL,
    payload JSON NOT NULL
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
