-- AI owner / caretaker runtime vacancy state table

CREATE TABLE IF NOT EXISTS club_control_runtime_states (
    club_id INT PRIMARY KEY,
    state_key VARCHAR(64) NOT NULL,
    ai_owner_active BOOLEAN DEFAULT 0,
    caretaker_active BOOLEAN DEFAULT 0,
    owner_vacant BOOLEAN DEFAULT 0,
    manager_vacant BOOLEAN DEFAULT 0,
    owner_vacancy_since DATE NULL,
    manager_vacancy_since DATE NULL,
    updated_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    INDEX idx_runtime_vacancy (owner_vacant, manager_vacant, caretaker_active)
);
