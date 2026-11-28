-- Adds an editable scholarship code/category to existing MySQL installations.
ALTER TABLE scholarship_types ADD COLUMN IF NOT EXISTS code VARCHAR(24) NULL AFTER name;
UPDATE scholarship_types SET code = TRIM(SUBSTRING_INDEX(name, ' ', 1)) WHERE code IS NULL OR code = '';
ALTER TABLE scholarship_types MODIFY code VARCHAR(24) NOT NULL DEFAULT '';
