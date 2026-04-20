-- Matchday / Squad Management Depth MVP
-- Adds role + recent participation tracking columns to players

SET @has_squad_role := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'squad_role'
);
SET @sql := IF(
    @has_squad_role = 0,
    "ALTER TABLE players ADD COLUMN squad_role ENUM('KEY_PLAYER','REGULAR_STARTER','ROTATION','BENCH','PROSPECT') NOT NULL DEFAULT 'ROTATION' AFTER morale_score",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_last_played_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'last_played_at'
);
SET @sql := IF(
    @has_last_played_at = 0,
    "ALTER TABLE players ADD COLUMN last_played_at DATETIME NULL AFTER squad_role",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_last_minutes_played := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players' AND COLUMN_NAME = 'last_minutes_played'
);
SET @sql := IF(
    @has_last_minutes_played = 0,
    "ALTER TABLE players ADD COLUMN last_minutes_played INT NOT NULL DEFAULT 0 AFTER last_played_at",
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill null/invalid values for existing rows
UPDATE players
SET squad_role = 'ROTATION'
WHERE squad_role IS NULL OR squad_role = '';

UPDATE players
SET last_minutes_played = 0
WHERE last_minutes_played IS NULL OR last_minutes_played < 0;
