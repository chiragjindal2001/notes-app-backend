-- Add email verification columns
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS verification_code VARCHAR(6),
ADD COLUMN IF NOT EXISTS verification_code_expires_at TIMESTAMP;

-- Create index for verification code lookups
CREATE INDEX IF NOT EXISTS idx_users_verification_code ON users(verification_code);

-- Update existing users to be verified (for backward compatibility)
UPDATE users SET is_verified = TRUE WHERE is_verified IS NULL; 