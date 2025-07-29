ALTER TABLE notes ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE;
-- Optionally, set all existing notes to active (should be default)
UPDATE notes SET is_active = TRUE WHERE is_active IS NULL; 