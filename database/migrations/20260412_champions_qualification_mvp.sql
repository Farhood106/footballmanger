-- Champions qualification slots mapping (source league -> target champions competition)

CREATE TABLE IF NOT EXISTS competition_qualification_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_competition_id INT NOT NULL,
    target_competition_id INT NOT NULL,
    slots INT NOT NULL DEFAULT 1,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_source_target (source_competition_id, target_competition_id),
    INDEX idx_target_active (target_competition_id, is_active),
    FOREIGN KEY (source_competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    FOREIGN KEY (target_competition_id) REFERENCES competitions(id) ON DELETE CASCADE
);
