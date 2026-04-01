-- Add toucher field to rolls
ALTER TABLE rolls ADD COLUMN toucher TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER result;
