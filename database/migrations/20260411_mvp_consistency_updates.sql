-- MVP consistency migration for multiplayer club-management loop

ALTER TABLE clubs CHANGE COLUMN budget balance BIGINT DEFAULT 10000000;
ALTER TABLE matches CHANGE COLUMN match_time scheduled_at DATETIME NOT NULL;
ALTER TABLE players CHANGE COLUMN overall_rating overall INT NOT NULL;

CREATE TABLE IF NOT EXISTS tactic_lineups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    phase_key ENUM('MATCH_1','MATCH_2','RECOVERY','NEXT_DAY') DEFAULT 'MATCH_1',
    player_id INT NOT NULL,
    position_slot ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LW','RW','ST','CF') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
    closed_at DATETIME
);

CREATE TABLE IF NOT EXISTS club_governance_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    decided_by_user_id INT,
    decision ENUM('WARNING','FINE','COMPENSATION','CONTRACT_UPHELD','CONTRACT_TERMINATED','NO_ACTION') NOT NULL,
    summary TEXT,
    applied_actions JSON,
    decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS daily_cycle_snapshots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cycle_date DATE NOT NULL,
    phase_key VARCHAR(50) NOT NULL,
    executed_at DATETIME NOT NULL,
    matches_simulated INT DEFAULT 0,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
