-- Transfer market core expansion MVP

SET @has_transfer_listed := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'is_transfer_listed'
);
SET @sql := IF(@has_transfer_listed = 0,
    'ALTER TABLE players ADD COLUMN is_transfer_listed BOOLEAN DEFAULT 0 AFTER market_value',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_asking_price := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'asking_price'
);
SET @sql := IF(@has_asking_price = 0,
    'ALTER TABLE players ADD COLUMN asking_price BIGINT NULL AFTER is_transfer_listed',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_transfer_listed_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'transfer_listed_at'
);
SET @sql := IF(@has_transfer_listed_at = 0,
    'ALTER TABLE players ADD COLUMN transfer_listed_at DATETIME NULL AFTER asking_price',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_transfer_season_id := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'season_id'
);
SET @sql := IF(@has_transfer_season_id = 0,
    'ALTER TABLE transfers ADD COLUMN season_id INT NULL AFTER initiated_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fk_transfer_season := (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'transfers'
      AND COLUMN_NAME = 'season_id'
      AND REFERENCED_TABLE_NAME = 'seasons'
);
SET @sql := IF(@has_fk_transfer_season = 0,
    'ALTER TABLE transfers ADD CONSTRAINT fk_transfers_season FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_player_status := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND INDEX_NAME = 'idx_transfer_player_status'
);
SET @sql := IF(@has_idx_player_status = 0,
    'ALTER TABLE transfers ADD INDEX idx_transfer_player_status (player_id, status)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
