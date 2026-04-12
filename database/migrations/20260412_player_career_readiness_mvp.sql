-- Player readiness, development and career history MVP extensions

SET @has_fitness := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'fitness'
);
SET @sql := IF(@has_fitness = 0,
    'ALTER TABLE players ADD COLUMN fitness INT DEFAULT 100 AFTER morale',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_morale_score := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'morale_score'
);
SET @sql := IF(@has_morale_score = 0,
    'ALTER TABLE players ADD COLUMN morale_score INT DEFAULT 70 AFTER fitness',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_starts := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'player_season_stats' AND COLUMN_NAME = 'starts'
);
SET @sql := IF(@has_starts = 0,
    'ALTER TABLE player_season_stats ADD COLUMN starts INT DEFAULT 0 AFTER appearances',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS player_career_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    season_id INT NOT NULL,
    club_id INT NOT NULL,
    appearances INT DEFAULT 0,
    starts INT DEFAULT 0,
    minutes_played INT DEFAULT 0,
    goals INT DEFAULT 0,
    assists INT DEFAULT 0,
    yellow_cards INT DEFAULT 0,
    red_cards INT DEFAULT 0,
    avg_rating DECIMAL(3,1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_player_season_club_history (player_id, season_id, club_id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
);
