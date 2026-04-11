-- Contract negotiation MVP between owner and coach

CREATE TABLE IF NOT EXISTS manager_contract_negotiations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    club_id INT NOT NULL,
    coach_user_id INT NOT NULL,
    owner_user_id INT NOT NULL,
    status ENUM('open','accepted','rejected','expired','superseded') DEFAULT 'open',
    offered_salary_per_cycle BIGINT NOT NULL,
    offered_contract_length_cycles INT NOT NULL,
    club_objective VARCHAR(255),
    bonus_promotion BIGINT DEFAULT 0,
    bonus_title BIGINT DEFAULT 0,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME,
    INDEX idx_negotiation_application_status (application_id, status),
    INDEX idx_negotiation_coach_status (coach_user_id, status),
    INDEX idx_negotiation_owner_status (owner_user_id, status)
);
