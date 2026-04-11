-- Season rollover/finalization log table for deterministic world progression

CREATE TABLE IF NOT EXISTS season_rollover_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    competition_id INT NOT NULL,
    status ENUM('FINALIZED','APPLIED') DEFAULT 'FINALIZED',
    finalized_standings_json JSON,
    rollover_plan_json JSON,
    finalized_at DATETIME NOT NULL,
    applied_at DATETIME,
    UNIQUE KEY uniq_season_rollover (season_id),
    INDEX idx_rollover_comp_status (competition_id, status)
);
