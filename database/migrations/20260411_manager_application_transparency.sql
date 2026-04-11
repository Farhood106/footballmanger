-- Manager application rejection transparency

SET @has_reviewed_by := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_manager_applications' AND COLUMN_NAME = 'reviewed_by'
);
SET @sql := IF(@has_reviewed_by > 0,
    'ALTER TABLE club_manager_applications CHANGE COLUMN reviewed_by reviewed_by_user_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_reviewed_by_user_id := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_manager_applications' AND COLUMN_NAME = 'reviewed_by_user_id'
);
SET @sql := IF(@has_reviewed_by_user_id = 0,
    'ALTER TABLE club_manager_applications ADD COLUMN reviewed_by_user_id INT NULL AFTER rejection_reason',
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

ALTER TABLE club_manager_applications
    MODIFY COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending';

UPDATE club_manager_applications
SET status = LOWER(status)
WHERE status IN ('PENDING','APPROVED','REJECTED','CANCELLED');
