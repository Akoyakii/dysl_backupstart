CREATE TABLE IF NOT EXISTS college_departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    disabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academic_programs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    department_id INT UNSIGNED DEFAULT NULL,
    disabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE academic_programs ADD COLUMN department_id INT UNSIGNED DEFAULT NULL;

INSERT IGNORE INTO college_departments (name, created_at) VALUES
('College of Accountancy', UTC_TIMESTAMP()), ('College of Arts and Sciences', UTC_TIMESTAMP()),
('College of Business Administration', UTC_TIMESTAMP()), ('College of Computer Studies', UTC_TIMESTAMP()),
('College of Education', UTC_TIMESTAMP()), ('College of Health Sciences', UTC_TIMESTAMP()),
('College of Hospitality Management and Tourism', UTC_TIMESTAMP()), ('College of Maritime Education', UTC_TIMESTAMP()),
('School of Mechanical Engineering', UTC_TIMESTAMP());

INSERT IGNORE INTO academic_programs (code, name, department_id, created_at)
SELECT v.code, v.name, d.id, UTC_TIMESTAMP() FROM (
 SELECT 'BSA' code, 'BS in Accountancy' name, 'College of Accountancy' department_name UNION ALL
 SELECT 'BSAIS', 'BS in Accounting Information System', 'College of Accountancy' UNION ALL
 SELECT 'ABPOLSCI', 'AB Major in Political Science', 'College of Arts and Sciences' UNION ALL
 SELECT 'ABPSYCH', 'AB Psychology', 'College of Arts and Sciences' UNION ALL
 SELECT 'BSBA', 'BS in Business Administration — Economics', 'College of Business Administration' UNION ALL
 SELECT 'BSBA-FIN', 'BS in Business Administration — Finance', 'College of Business Administration' UNION ALL
 SELECT 'BSBA-HR', 'BS in Business Administration — Human Resource', 'College of Business Administration' UNION ALL
 SELECT 'BSBA-MKT', 'BS in Business Administration — Marketing', 'College of Business Administration' UNION ALL
 SELECT 'BSBA-OPS', 'BS in Business Administration — Operations', 'College of Business Administration' UNION ALL
 SELECT 'BSCS', 'BS in Computer Science', 'College of Computer Studies' UNION ALL
 SELECT 'BSIT', 'BS in Information Technology', 'College of Computer Studies' UNION ALL
 SELECT 'BSCPE', 'BS in Computer Engineering', 'College of Computer Studies' UNION ALL
 SELECT 'BEED', 'Bachelor of Elementary Education', 'College of Education' UNION ALL
 SELECT 'BSED-SCI', 'Bachelor of Secondary Education — Sciences', 'College of Education' UNION ALL
 SELECT 'BSED-ENG', 'Bachelor of Secondary Education — English', 'College of Education' UNION ALL
 SELECT 'BSED-FIL', 'Bachelor of Secondary Education — Filipino', 'College of Education' UNION ALL
 SELECT 'BSED-MATH', 'Bachelor of Secondary Education — Mathematics', 'College of Education' UNION ALL
 SELECT 'CPTE', 'Continuing Professional Teacher Education', 'College of Education' UNION ALL
 SELECT 'BSN', 'BS in Nursing', 'College of Health Sciences' UNION ALL
 SELECT 'BSMID', 'BS in Midwifery', 'College of Health Sciences' UNION ALL
 SELECT 'BSHM', 'BS in Hospitality Management', 'College of Hospitality Management and Tourism' UNION ALL
 SELECT 'BSTM', 'BS in Tourism Management', 'College of Hospitality Management and Tourism' UNION ALL
 SELECT 'BSMT', 'BS in Marine Transportation', 'College of Maritime Education' UNION ALL
 SELECT 'BSMarE', 'BS in Marine Engineering', 'College of Maritime Education' UNION ALL
 SELECT 'BSME', 'BS in Mechanical Engineering', 'School of Mechanical Engineering'
) v JOIN college_departments d ON d.name = v.department_name;
