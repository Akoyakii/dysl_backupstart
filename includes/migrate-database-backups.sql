-- Run once on existing installations before deploying the updated PHP files.
ALTER TABLE record_write_lock ADD COLUMN session_epoch INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS database_backups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    backup_type ENUM('manual', 'weekly', 'recovery') NOT NULL,
    created_at DATETIME NOT NULL,
    row_count INT UNSIGNED NOT NULL,
    payload LONGBLOB NOT NULL
) ENGINE=InnoDB;
