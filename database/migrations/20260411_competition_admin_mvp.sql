-- Competition/division/season admin MVP extensions

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE competitions ADD COLUMN parent_competition_id INT NULL AFTER id',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'parent_competition_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE competitions ADD COLUMN code VARCHAR(50) NULL AFTER parent_competition_id',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'code'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE competitions MODIFY COLUMN type ENUM(\'LEAGUE\',\'CUP\',\'SUPER_CUP\',\'CHAMPIONS_LEAGUE\',\'FRIENDLY\') NOT NULL',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'type'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE competitions ADD COLUMN promotion_slots INT DEFAULT 0 AFTER teams_count',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'promotion_slots'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE competitions ADD COLUMN relegation_slots INT DEFAULT 0 AFTER promotion_slots',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'relegation_slots'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(COUNT(*) = 0,
        'ALTER TABLE competitions ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER relegation_slots',
        'SELECT 1')
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'competitions' AND COLUMN_NAME = 'is_active'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
