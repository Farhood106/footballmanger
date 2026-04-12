-- Gameplay stabilization: per-club cycle state + simulation safety support

CREATE TABLE IF NOT EXISTS club_daily_cycle_states (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cycle_date DATE NOT NULL,
    club_id INT NOT NULL,
    matches_today TINYINT NOT NULL DEFAULT 1,
    profile_key ENUM('one_match','two_matches') NOT NULL,
    current_phase_key VARCHAR(50) NOT NULL,
    updated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cycle_club (cycle_date, club_id),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_cycle_phase (cycle_date, current_phase_key)
);
