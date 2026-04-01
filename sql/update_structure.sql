-- Update sessions table
ALTER TABLE sessions
ADD COLUMN bowls_per_end TINYINT UNSIGNED NOT NULL DEFAULT 4 AFTER hand,
ADD COLUMN total_ends TINYINT UNSIGNED NOT NULL DEFAULT 15 AFTER bowls_per_end;

-- Update rolls table - add end_number, remove delivery requirement
ALTER TABLE rolls
ADD COLUMN end_number TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER session_id,
MODIFY COLUMN delivery TINYINT UNSIGNED NULL COMMENT 'deprecated';
