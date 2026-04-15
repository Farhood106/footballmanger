-- Recurring Club Economy Expansion MVP

SET @has_recurring_amount := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_sponsors' AND COLUMN_NAME = 'recurring_amount'
);
SET @sql := IF(@has_recurring_amount = 0,
    'ALTER TABLE club_sponsors ADD COLUMN recurring_amount BIGINT DEFAULT 0 AFTER banner_url',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_recurring_cycle_days := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_sponsors' AND COLUMN_NAME = 'recurring_cycle_days'
);
SET @sql := IF(@has_recurring_cycle_days = 0,
    'ALTER TABLE club_sponsors ADD COLUMN recurring_cycle_days INT DEFAULT 7 AFTER recurring_amount',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_last_paid_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'club_sponsors' AND COLUMN_NAME = 'last_paid_at'
);
SET @sql := IF(@has_last_paid_at = 0,
    'ALTER TABLE club_sponsors ADD COLUMN last_paid_at DATETIME NULL AFTER recurring_cycle_days',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE club_finance_ledger
MODIFY COLUMN entry_type ENUM(
    'COACH_SALARY','PLAYER_WAGE','MATCH_REWARD','SEASON_REWARD','GOVERNANCE_PENALTY','GOVERNANCE_COMPENSATION',
    'TRANSFER_IN','TRANSFER_OUT','OWNER_FUNDING','SPONSOR_INCOME','OPERATING_COST','MANUAL_ADMIN_ADJUSTMENT',
    'FACILITY_UPGRADE','FACILITY_DOWNGRADE_REFUND','FACILITY_MAINTENANCE',
    'WAGE','STAFF_WAGE','PENALTY','PRIZE','OTHER','SPONSOR','TICKET'
) NOT NULL;
