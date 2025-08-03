-- Migration to add missing columns from PostgreSQL schema to MySQL notes table
-- Date: 2024-08-03

-- Add missing columns to notes table
ALTER TABLE notes 
ADD COLUMN tags JSON NULL COMMENT 'Tags for the note',
ADD COLUMN features JSON NULL COMMENT 'Features of the note',
ADD COLUMN topics JSON NULL COMMENT 'Topics covered in the note',
ADD COLUMN preview_image VARCHAR(255) NULL COMMENT 'Preview image URL',
ADD COLUMN sample_pages JSON NULL COMMENT 'Sample pages URLs',
ADD COLUMN file_url VARCHAR(255) NULL COMMENT 'File URL (alternative to file_path)';

-- Add basic indexes for better performance
-- Note: JSON indexes require MySQL 5.7+ and specific syntax
-- For older MySQL versions, these will be ignored
CREATE INDEX idx_notes_preview_image ON notes (preview_image);
CREATE INDEX idx_notes_file_url ON notes (file_url);

-- Update existing records to have default values
UPDATE notes SET 
    tags = '[]',
    features = '[]',
    topics = '[]'
WHERE tags IS NULL OR features IS NULL OR topics IS NULL; 