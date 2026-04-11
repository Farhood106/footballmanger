-- Unified finance service schema extensions

CREATE TABLE IF NOT EXISTS club_sponsors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    tier ENUM('main','secondary','minor') DEFAULT 'minor',
    brand_name VARCHAR(255) NOT NULL,
    description TEXT,
    contact_link VARCHAR(500),
    banner_url VARCHAR(500),
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_club_sponsor_tier (club_id, tier)
);

CREATE TABLE IF NOT EXISTS club_owner_funding_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    owner_user_id INT NOT NULL,
    amount BIGINT NOT NULL,
    note VARCHAR(500),
    external_reference VARCHAR(255),
    status ENUM('posted','pending','rejected') DEFAULT 'posted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_owner_funding_club_date (club_id, created_at)
);

SET @sql := 'ALTER TABLE club_finance_ledger MODIFY COLUMN entry_type ENUM(''COACH_SALARY'',''MATCH_REWARD'',''SEASON_REWARD'',''GOVERNANCE_PENALTY'',''GOVERNANCE_COMPENSATION'',''TRANSFER_IN'',''TRANSFER_OUT'',''OWNER_FUNDING'',''SPONSOR_INCOME'',''MANUAL_ADMIN_ADJUSTMENT'',''WAGE'',''STAFF_WAGE'',''SPONSOR'',''TICKET'',''PRIZE'',''PENALTY'',''OTHER'') NOT NULL';
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_meta_json := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_finance_ledger' AND COLUMN_NAME = 'meta_json'
);
SET @sql := IF(@has_meta_json = 0,
    'ALTER TABLE club_finance_ledger ADD COLUMN meta_json JSON NULL AFTER reference_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
