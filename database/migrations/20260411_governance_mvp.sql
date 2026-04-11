-- Governance MVP runtime migration

CREATE TABLE IF NOT EXISTS club_governance_cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    contract_id INT,
    owner_user_id INT NULL,
    manager_user_id INT NULL,
    raised_by_user_id INT NOT NULL,
    against_user_id INT NULL,
    case_type ENUM('UNFAIR_DISMISSAL','COMPENSATION_DISAGREEMENT','CONTRACT_BREACH','MUTUAL_TERMINATION_DISPUTE','OTHER') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('open','under_review','resolved','rejected') DEFAULT 'open',
    opened_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    INDEX idx_governance_status (club_id, status)
);

CREATE TABLE IF NOT EXISTS club_governance_decisions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    decision_type ENUM('CASE_UPHELD','CASE_REJECTED','WARNING','PENALTY','COMPENSATION','MIXED') NOT NULL,
    decision_summary TEXT NOT NULL,
    penalty_amount BIGINT DEFAULT 0,
    compensation_amount BIGINT DEFAULT 0,
    decided_by_user_id INT,
    decided_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_case_decision (case_id, decided_at)
);

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE club_governance_cases ADD COLUMN subject VARCHAR(255) NOT NULL AFTER case_type',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_governance_cases' AND COLUMN_NAME = 'subject'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE club_governance_cases ADD COLUMN owner_user_id INT NULL AFTER contract_id',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_governance_cases' AND COLUMN_NAME = 'owner_user_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE club_governance_cases ADD COLUMN manager_user_id INT NULL AFTER owner_user_id',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_governance_cases' AND COLUMN_NAME = 'manager_user_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE club_governance_decisions ADD COLUMN decision_type ENUM(\'CASE_UPHELD\',\'CASE_REJECTED\',\'WARNING\',\'PENALTY\',\'COMPENSATION\',\'MIXED\') NOT NULL AFTER case_id',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_governance_decisions' AND COLUMN_NAME = 'decision_type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE club_governance_decisions ADD COLUMN decision_summary TEXT NOT NULL AFTER decision_type',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_governance_decisions' AND COLUMN_NAME = 'decision_summary'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
