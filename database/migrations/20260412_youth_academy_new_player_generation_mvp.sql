-- Youth academy + deterministic annual intake foundation

SET @has_academy_origin := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'academy_origin_club_id'
);
SET @sql := IF(@has_academy_origin = 0,
    'ALTER TABLE players ADD COLUMN academy_origin_club_id INT NULL AFTER market_value',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_intake_season := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'youth_intake_season_id'
);
SET @sql := IF(@has_intake_season = 0,
    'ALTER TABLE players ADD COLUMN youth_intake_season_id INT NULL AFTER academy_origin_club_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_academy_flag := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'is_academy_product'
);
SET @sql := IF(@has_academy_flag = 0,
    'ALTER TABLE players ADD COLUMN is_academy_product BOOLEAN DEFAULT 0 AFTER youth_intake_season_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS youth_intakes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    club_id INT NOT NULL,
    academy_level INT NOT NULL DEFAULT 1,
    intake_count INT NOT NULL DEFAULT 0,
    intake_json JSON NULL,
    generated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_youth_intake_season_club (season_id, club_id),
    INDEX idx_youth_intake_club_season (club_id, season_id),
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_idx_origin := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND INDEX_NAME = 'idx_players_academy_origin'
);
SET @sql := IF(@has_idx_origin = 0,
    'ALTER TABLE players ADD INDEX idx_players_academy_origin (academy_origin_club_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_intake := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND INDEX_NAME = 'idx_players_youth_intake_season'
);
SET @sql := IF(@has_idx_intake = 0,
    'ALTER TABLE players ADD INDEX idx_players_youth_intake_season (youth_intake_season_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_idx_product := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND INDEX_NAME = 'idx_players_academy_product'
);
SET @sql := IF(@has_idx_product = 0,
    'ALTER TABLE players ADD INDEX idx_players_academy_product (club_id, is_academy_product)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
