-- Backfill manager application workflow tables/columns for migration-first runtime

CREATE TABLE IF NOT EXISTS club_manager_expectations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    owner_user_id INT,
    title VARCHAR(255) NOT NULL,
    expectations TEXT,
    duties TEXT,
    commitments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_club_expectation (club_id),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS club_manager_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    coach_user_id INT NOT NULL,
    proposed_expectations TEXT,
    proposed_duties TEXT,
    proposed_commitments TEXT,
    cover_letter TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason VARCHAR(1000),
    reviewed_by_user_id INT,
    reviewed_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_pending_coach_club (club_id, coach_user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- legacy column rename/backfill guard
SET @has_reviewed_by_old := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_manager_applications' AND COLUMN_NAME = 'reviewed_by'
);
SET @has_reviewed_by_new := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_manager_applications' AND COLUMN_NAME = 'reviewed_by_user_id'
);
SET @sql := IF(@has_reviewed_by_old = 1 AND @has_reviewed_by_new = 0,
    'ALTER TABLE club_manager_applications CHANGE COLUMN reviewed_by reviewed_by_user_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_rejection_reason := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_manager_applications' AND COLUMN_NAME = 'rejection_reason'
);
SET @sql := IF(@has_rejection_reason = 0,
    'ALTER TABLE club_manager_applications ADD COLUMN rejection_reason VARCHAR(1000) NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE club_manager_applications SET status = LOWER(status);
