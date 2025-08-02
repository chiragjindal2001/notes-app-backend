-- Migration: Add Google OAuth columns to users table
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) UNIQUE NULL,
ADD COLUMN google_email VARCHAR(255) NULL,
ADD COLUMN google_name VARCHAR(255) NULL,
ADD COLUMN avatar_url TEXT NULL;

-- Create index on google_id for faster lookups
CREATE INDEX idx_users_google_id ON users(google_id); 