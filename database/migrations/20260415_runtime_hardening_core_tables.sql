-- Core runtime hardening: history + admin operation log tables

CREATE TABLE IF NOT EXISTS player_awards (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    competition_id INT NOT NULL,
    award_type ENUM('PLAYER_OF_MATCH','PLAYER_OF_WEEK','TOP_SCORER','TOP_ASSIST','BEST_PLAYER','BEST_YOUNG_PLAYER') NOT NULL,
    player_id INT NOT NULL,
    club_id INT NOT NULL,
    match_id INT NULL,
    week_number INT NULL,
    score_value DECIMAL(10,2) DEFAULT 0,
    meta_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_award_scope (season_id, competition_id, award_type, match_id, week_number),
    INDEX idx_award_player (player_id, season_id),
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS club_honors (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    season_id INT NOT NULL,
    competition_id INT NOT NULL,
    honor_type ENUM('LEAGUE_TITLE','CUP_WIN','PROMOTION','RELEGATION','CHAMPIONS_QUALIFIED') NOT NULL,
    details VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_honor (club_id, season_id, competition_id, honor_type),
    INDEX idx_honor_club (club_id, created_at),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS club_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    record_key ENUM('TOP_SCORER','MOST_APPEARANCES','BEST_SEASON_SCORER') NOT NULL,
    player_id INT NOT NULL,
    record_value INT NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_record (club_id, record_key),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS club_legends (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    player_id INT NOT NULL,
    legend_score INT NOT NULL DEFAULT 0,
    status ENUM('ICON','LEGEND') DEFAULT 'LEGEND',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_legend_player (club_id, player_id),
    INDEX idx_legend_club_score (club_id, legend_score),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_operation_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT NOT NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_admin_operation_date (admin_user_id, created_at),
    INDEX idx_admin_operation_entity (entity_type, entity_id),
    FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
