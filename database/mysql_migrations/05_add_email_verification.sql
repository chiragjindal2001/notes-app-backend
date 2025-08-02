-- Migration: Add email verification columns to users table
ALTER TABLE users 
ADD COLUMN email_verified BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN email_verification_token VARCHAR(255) NULL,
ADD COLUMN email_verification_expires_at TIMESTAMP NULL;

-- Create index on email_verification_token
CREATE INDEX idx_users_email_verification_token ON users(email_verification_token); 