-- Remove delivery constraint and make column nullable
ALTER TABLE rolls DROP CONSTRAINT chk_delivery;
ALTER TABLE rolls MODIFY COLUMN delivery TINYINT UNSIGNED NULL;
