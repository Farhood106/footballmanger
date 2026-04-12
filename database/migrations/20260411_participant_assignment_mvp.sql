-- Competition participant assignment + qualification metadata MVP

SET @has_entry_type := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_seasons' AND COLUMN_NAME = 'entry_type'
);

SET @sql := IF(@has_entry_type = 0,
    'ALTER TABLE club_seasons ADD COLUMN entry_type ENUM(''direct'',''promoted'',''relegated'',''champion'',''qualified'',''wildcard'') NOT NULL DEFAULT ''direct'' AFTER season_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_seasons' AND INDEX_NAME = 'idx_season_entry_type'
);

SET @sql := IF(@has_idx = 0,
    'ALTER TABLE club_seasons ADD INDEX idx_season_entry_type (season_id, entry_type)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
