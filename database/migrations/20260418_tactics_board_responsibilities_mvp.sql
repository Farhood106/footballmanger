-- Tactics board MVP: LM/RM position support and responsibility assignments.

SET @sql := "ALTER TABLE players MODIFY COLUMN position ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LM','RM','LW','RW','ST','CF') NOT NULL";
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := "ALTER TABLE match_lineups MODIFY COLUMN position ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LM','RM','LW','RW','ST','CF') NOT NULL";
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := "ALTER TABLE tactic_lineups MODIFY COLUMN position_slot ENUM('GK','LB','RB','CB','LWB','RWB','CDM','CM','CAM','LM','RM','LW','RW','ST','CF') NOT NULL";
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_captain := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tactics' AND COLUMN_NAME = 'captain'
);
SET @sql := IF(@has_captain = 0,
    'ALTER TABLE tactics ADD COLUMN captain INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_penalty := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tactics' AND COLUMN_NAME = 'penalty_taker'
);
SET @sql := IF(@has_penalty = 0,
    'ALTER TABLE tactics ADD COLUMN penalty_taker INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_freekick := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tactics' AND COLUMN_NAME = 'freekick_taker'
);
SET @sql := IF(@has_freekick = 0,
    'ALTER TABLE tactics ADD COLUMN freekick_taker INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_corner := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tactics' AND COLUMN_NAME = 'corner_taker'
);
SET @sql := IF(@has_corner = 0,
    'ALTER TABLE tactics ADD COLUMN corner_taker INT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
