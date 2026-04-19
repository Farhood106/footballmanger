-- Youth intake + academy-origin MVP

SET @has_is_academy_origin := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'is_academy_origin'
);
SET @sql := IF(@has_is_academy_origin = 0,
    'ALTER TABLE players ADD COLUMN is_academy_origin BOOLEAN DEFAULT 0 AFTER is_retired',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_origin_club := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'academy_origin_club_id'
);
SET @sql := IF(@has_origin_club = 0,
    'ALTER TABLE players ADD COLUMN academy_origin_club_id INT NULL AFTER is_academy_origin',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_intake_season := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'academy_intake_season_id'
);
SET @sql := IF(@has_intake_season = 0,
    'ALTER TABLE players ADD COLUMN academy_intake_season_id INT NULL AFTER academy_origin_club_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_intake_batch := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'academy_intake_batch_key'
);
SET @sql := IF(@has_intake_batch = 0,
    'ALTER TABLE players ADD COLUMN academy_intake_batch_key VARCHAR(64) NULL AFTER academy_intake_season_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS youth_intake_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    intake_season_id INT NOT NULL,
    intake_key VARCHAR(64) NOT NULL,
    academy_level INT NOT NULL DEFAULT 1,
    generated_count INT NOT NULL DEFAULT 0,
    generated_player_ids_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_season_intake (club_id, intake_season_id, intake_key),
    INDEX idx_intake_club_created (club_id, created_at),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (intake_season_id) REFERENCES seasons(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
