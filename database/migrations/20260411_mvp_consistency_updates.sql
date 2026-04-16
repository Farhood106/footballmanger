-- MVP consistency migration for multiplayer club-management loop

-- NOTE: run on legacy databases only; canonical installs should use database/schema.sql

SET @has_budget := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clubs' AND COLUMN_NAME = 'budget'
);
SET @sql := IF(@has_budget > 0,
    'ALTER TABLE clubs CHANGE COLUMN budget balance BIGINT DEFAULT 10000000',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_match_time := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'matches' AND COLUMN_NAME = 'match_time'
);
SET @sql := IF(@has_match_time > 0,
    'ALTER TABLE matches CHANGE COLUMN match_time scheduled_at DATETIME NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_overall_rating := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'overall_rating'
);
SET @sql := IF(@has_overall_rating > 0,
    'ALTER TABLE players CHANGE COLUMN overall_rating overall INT NOT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- manager application normalization (backward-safe)
SET @has_reviewed_by := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_manager_applications' AND COLUMN_NAME = 'reviewed_by'
);
SET @sql := IF(@has_reviewed_by > 0,
    'ALTER TABLE club_manager_applications CHANGE COLUMN reviewed_by reviewed_by_user_id INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_rejection_reason := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_manager_applications' AND COLUMN_NAME = 'rejection_reason'
);
SET @sql := IF(@has_rejection_reason = 0,
    'ALTER TABLE club_manager_applications ADD COLUMN rejection_reason VARCHAR(1000) NULL AFTER status',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := 'ALTER TABLE club_manager_applications MODIFY COLUMN status ENUM(\'pending\',\'approved\',\'rejected\') DEFAULT \'pending\'';
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE club_manager_applications
SET status = LOWER(status)
WHERE status IN ('PENDING','APPROVED','REJECTED','CANCELLED');

CREATE TABLE IF NOT EXISTS tactic_lineups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    phase_key ENUM('MATCH_1','MATCH_2','RECOVERY','NEXT_DAY') DEFAULT 'MATCH_1',
    player_id INT NOT NULL,
    position_slot ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LW','RW','ST','CF') NOT NULL,
    slot_order TINYINT NOT NULL DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_active_lineup_slot_order (club_id, phase_key, position_slot, slot_order, is_active),
    INDEX idx_lineup_club_phase (club_id, phase_key)
);

CREATE TABLE IF NOT EXISTS manager_contracts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    owner_user_id INT NOT NULL,
    coach_user_id INT NOT NULL,
    status ENUM('PROPOSED','ACTIVE','TERMINATED','EXPIRED','REJECTED') DEFAULT 'PROPOSED',
    start_date DATE,
    end_date DATE,
    salary BIGINT DEFAULT 0,
    terms_json JSON,
    termination_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (coach_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_contract_status (club_id, status)
);

CREATE TABLE IF NOT EXISTS club_governance_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    contract_id INT,
    raised_by_user_id INT NOT NULL,
    against_user_id INT NOT NULL,
    case_type ENUM('CONTRACT_DISPUTE','ROLE_CONFLICT','FINANCIAL_DISPUTE','DISCIPLINARY') NOT NULL,
    status ENUM('OPEN','UNDER_REVIEW','DECIDED','CLOSED') DEFAULT 'OPEN',
    description TEXT,
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    closed_at DATETIME,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (contract_id) REFERENCES manager_contracts(id) ON DELETE SET NULL,
    FOREIGN KEY (raised_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (against_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS club_governance_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    decided_by_user_id INT,
    decision ENUM('WARNING','FINE','COMPENSATION','CONTRACT_UPHELD','CONTRACT_TERMINATED','NO_ACTION') NOT NULL,
    summary TEXT,
    applied_actions JSON,
    decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES club_governance_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS club_finance_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    season_id INT,
    entry_type ENUM('TRANSFER_IN','TRANSFER_OUT','WAGE','STAFF_WAGE','SPONSOR','TICKET','PRIZE','PENALTY','OTHER') NOT NULL,
    amount BIGINT NOT NULL,
    description VARCHAR(500),
    reference_type VARCHAR(50),
    reference_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
    INDEX idx_finance_club_date (club_id, created_at)
);

CREATE TABLE IF NOT EXISTS daily_cycle_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cycle_date DATE NOT NULL,
    phase_key VARCHAR(50) NOT NULL,
    executed_at DATETIME NOT NULL,
    matches_simulated INT DEFAULT 0,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_cycle_phase_execution (cycle_date, phase_key, executed_at)
);
