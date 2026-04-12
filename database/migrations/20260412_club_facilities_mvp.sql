-- 20260412_club_facilities_mvp.sql

ALTER TABLE club_finance_ledger
    MODIFY COLUMN entry_type ENUM(
        'COACH_SALARY','MATCH_REWARD','SEASON_REWARD','GOVERNANCE_PENALTY','GOVERNANCE_COMPENSATION',
        'TRANSFER_IN','TRANSFER_OUT','OWNER_FUNDING','SPONSOR_INCOME','MANUAL_ADMIN_ADJUSTMENT',
        'FACILITY_UPGRADE','FACILITY_DOWNGRADE_REFUND','FACILITY_MAINTENANCE',
        'WAGE','STAFF_WAGE','SPONSOR','TICKET','PRIZE','PENALTY','OTHER'
    ) NOT NULL;

CREATE TABLE IF NOT EXISTS club_facilities (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    facility_type ENUM('stadium','training_ground','youth_academy','headquarters') NOT NULL,
    level INT NOT NULL DEFAULT 1,
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_club_facility_type (club_id, facility_type),
    INDEX idx_club_facility_level (club_id, facility_type, level),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
