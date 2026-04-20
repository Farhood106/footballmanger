-- Manager contract lifecycle MVP: termination, compensation, and audit trail

CREATE TABLE IF NOT EXISTS manager_contract_terminations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    contract_id INT NOT NULL,
    club_id INT NOT NULL,
    owner_user_id INT NULL,
    coach_user_id INT NULL,
    terminated_by_user_id INT NOT NULL,
    termination_type ENUM('OWNER_TERMINATION','MUTUAL_TERMINATION','ADMIN_FORCED_TERMINATION') NOT NULL,
    compensation_amount BIGINT DEFAULT 0,
    reason VARCHAR(1000),
    governance_case_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_termination_contract (contract_id, created_at),
    INDEX idx_termination_club (club_id, created_at),
    FOREIGN KEY (contract_id) REFERENCES manager_contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (terminated_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (governance_case_id) REFERENCES club_governance_cases(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE club_finance_ledger
MODIFY COLUMN entry_type ENUM(
   'COACH_SALARY','MATCH_REWARD','SEASON_REWARD','GOVERNANCE_PENALTY','GOVERNANCE_COMPENSATION',
   'TRANSFER_IN','TRANSFER_OUT','OWNER_FUNDING','SPONSOR_INCOME','MANUAL_ADMIN_ADJUSTMENT',
   'FACILITY_UPGRADE','FACILITY_DOWNGRADE_REFUND','FACILITY_MAINTENANCE','MANAGER_TERMINATION_COMPENSATION',
   'WAGE','STAFF_WAGE','PENALTY','PRIZE','OTHER','SPONSOR','TICKET'
) NOT NULL;
