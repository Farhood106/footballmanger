-- Align tactics.mentality enum values with runtime/UI vocabulary.
-- Legacy values: AGGRESSIVE, NORMAL, CAUTIOUS
-- New values: ULTRA_ATTACK, ATTACK, BALANCED, DEFEND, ULTRA_DEFEND

UPDATE tactics SET mentality = 'ATTACK' WHERE mentality = 'AGGRESSIVE';
UPDATE tactics SET mentality = 'BALANCED' WHERE mentality = 'NORMAL';
UPDATE tactics SET mentality = 'DEFEND' WHERE mentality = 'CAUTIOUS';

SET @sql := "ALTER TABLE tactics MODIFY COLUMN mentality ENUM('ULTRA_ATTACK','ATTACK','BALANCED','DEFEND','ULTRA_DEFEND') NOT NULL DEFAULT 'BALANCED'";
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
