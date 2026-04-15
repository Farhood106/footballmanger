-- Transfer Market Depth MVP
-- Adds counter-offer state and lightweight negotiation tracking fields.

SET @has_counter_fee := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'counter_fee'
);
SET @sql := IF(
    @has_counter_fee = 0,
    'ALTER TABLE transfers ADD COLUMN counter_fee BIGINT NULL AFTER fee',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_negotiation_round := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'negotiation_round'
);
SET @sql := IF(
    @has_negotiation_round = 0,
    'ALTER TABLE transfers ADD COLUMN negotiation_round TINYINT DEFAULT 0 AFTER counter_fee',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_countered_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'countered_at'
);
SET @sql := IF(
    @has_countered_at = 0,
    'ALTER TABLE transfers ADD COLUMN countered_at DATETIME NULL AFTER completed_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_responded_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transfers' AND COLUMN_NAME = 'responded_at'
);
SET @sql := IF(
    @has_responded_at = 0,
    'ALTER TABLE transfers ADD COLUMN responded_at DATETIME NULL AFTER countered_at',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE transfers
MODIFY COLUMN status ENUM('PENDING','COUNTERED','COMPLETED','CANCELLED','REJECTED','SUPERSEDED') DEFAULT 'PENDING';
