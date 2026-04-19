-- Seed import foundation MVP

SET @has_player_external_key := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'external_key'
);
SET @sql := IF(
    @has_player_external_key = 0,
    'ALTER TABLE players ADD COLUMN external_key VARCHAR(100) NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_player_external_key_index := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND INDEX_NAME = 'uniq_player_external_key'
);
SET @sql := IF(
    @has_player_external_key_index = 0,
    'ALTER TABLE players ADD UNIQUE KEY uniq_player_external_key (external_key)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
