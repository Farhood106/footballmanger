-- Seed import club external-key fix

SET @has_club_external_key := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clubs' AND COLUMN_NAME = 'external_key'
);
SET @sql := IF(
    @has_club_external_key = 0,
    'ALTER TABLE clubs ADD COLUMN external_key VARCHAR(64) NULL AFTER manager_user_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_club_external_key_index := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clubs' AND INDEX_NAME = 'uniq_club_external_key'
);
SET @sql := IF(
    @has_club_external_key_index = 0,
    'ALTER TABLE clubs ADD UNIQUE KEY uniq_club_external_key (external_key)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
