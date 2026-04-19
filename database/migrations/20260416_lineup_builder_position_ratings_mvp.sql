-- Lineup Builder MVP: support duplicate positional slots per formation and deterministic slot ordering.

SET @has_slot_order := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tactic_lineups' AND COLUMN_NAME = 'slot_order'
);
SET @sql := IF(@has_slot_order = 0,
    'ALTER TABLE tactic_lineups ADD COLUMN slot_order TINYINT NOT NULL DEFAULT 1 AFTER position_slot',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tactic_lineups' AND INDEX_NAME = 'unique_active_lineup_slot'
);
SET @sql := IF(@has_old_unique > 0,
    'ALTER TABLE tactic_lineups DROP INDEX unique_active_lineup_slot',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_new_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tactic_lineups' AND INDEX_NAME = 'unique_active_lineup_slot_order'
);
SET @sql := IF(@has_new_unique = 0,
    'ALTER TABLE tactic_lineups ADD UNIQUE KEY unique_active_lineup_slot_order (club_id, phase_key, position_slot, slot_order, is_active)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
