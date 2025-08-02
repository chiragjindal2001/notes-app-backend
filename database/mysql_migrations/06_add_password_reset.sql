-- Migration: Add password reset columns to users table
ALTER TABLE users 
ADD COLUMN password_reset_token VARCHAR(255) NULL,
ADD COLUMN password_reset_expires_at TIMESTAMP NULL;

-- Create index on password_reset_token
CREATE INDEX idx_users_password_reset_token ON users(password_reset_token); 