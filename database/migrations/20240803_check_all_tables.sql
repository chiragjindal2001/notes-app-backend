-- Comprehensive migration to check and fix all tables from PostgreSQL to MySQL
-- Date: 2024-08-03

-- Check and fix orders table
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS order_id VARCHAR(50) NOT NULL COMMENT 'Unique order identifier',
ADD COLUMN IF NOT EXISTS customer_email VARCHAR(100) NOT NULL COMMENT 'Customer email address',
ADD COLUMN IF NOT EXISTS customer_name VARCHAR(100) NULL COMMENT 'Customer full name',
ADD COLUMN IF NOT EXISTS phone VARCHAR(32) NULL COMMENT 'Customer phone number',
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated timestamp';

-- Add unique constraint on order_id if not exists
ALTER TABLE orders ADD UNIQUE KEY IF NOT EXISTS uk_orders_order_id (order_id);

-- Check and fix notes table (if not already done)
ALTER TABLE notes 
ADD COLUMN IF NOT EXISTS tags JSON NULL COMMENT 'Tags for the note',
ADD COLUMN IF NOT EXISTS features JSON NULL COMMENT 'Features of the note',
ADD COLUMN IF NOT EXISTS topics JSON NULL COMMENT 'Topics covered in the note',
ADD COLUMN IF NOT EXISTS preview_image VARCHAR(255) NULL COMMENT 'Preview image URL',
ADD COLUMN IF NOT EXISTS sample_pages JSON NULL COMMENT 'Sample pages URLs',
ADD COLUMN IF NOT EXISTS file_url VARCHAR(255) NULL COMMENT 'File URL (alternative to file_path)';

-- Check and fix users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS google_id VARCHAR(100) NULL COMMENT 'Google OAuth ID',
ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) NULL COMMENT 'User avatar URL',
ADD COLUMN IF NOT EXISTS email_verified BOOLEAN DEFAULT FALSE COMMENT 'Email verification status',
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255) NULL COMMENT 'Email verification token',
ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL COMMENT 'Password reset token',
ADD COLUMN IF NOT EXISTS password_reset_expires TIMESTAMP NULL COMMENT 'Password reset token expiry',
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated timestamp';

-- Check and fix order_items table
ALTER TABLE order_items 
ADD COLUMN IF NOT EXISTS price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Price at time of purchase',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp';

-- Check and fix reviews table
ALTER TABLE reviews 
ADD COLUMN IF NOT EXISTS rating INTEGER NOT NULL DEFAULT 5 COMMENT 'Rating 1-5',
ADD COLUMN IF NOT EXISTS comment TEXT NULL COMMENT 'Review comment',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp',
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated timestamp';

-- Check and fix coupons table
ALTER TABLE coupons 
ADD COLUMN IF NOT EXISTS code VARCHAR(50) NOT NULL COMMENT 'Coupon code',
ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2) NOT NULL COMMENT 'Discount percentage',
ADD COLUMN IF NOT EXISTS max_uses INTEGER NULL COMMENT 'Maximum number of uses',
ADD COLUMN IF NOT EXISTS used_count INTEGER DEFAULT 0 COMMENT 'Number of times used',
ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NULL COMMENT 'Expiration date',
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE COMMENT 'Active status',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp';

-- Check and fix cart table
ALTER TABLE cart 
ADD COLUMN IF NOT EXISTS user_id INTEGER NOT NULL COMMENT 'User ID',
ADD COLUMN IF NOT EXISTS note_id INTEGER NOT NULL COMMENT 'Note ID',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp';

-- Check and fix refresh_tokens table
ALTER TABLE refresh_tokens 
ADD COLUMN IF NOT EXISTS user_id INTEGER NOT NULL COMMENT 'User ID',
ADD COLUMN IF NOT EXISTS token VARCHAR(255) NOT NULL COMMENT 'Refresh token',
ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP NOT NULL COMMENT 'Token expiry',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp';

-- Check and fix contacts table
ALTER TABLE contacts 
ADD COLUMN IF NOT EXISTS name VARCHAR(100) NOT NULL COMMENT 'Contact name',
ADD COLUMN IF NOT EXISTS email VARCHAR(100) NOT NULL COMMENT 'Contact email',
ADD COLUMN IF NOT EXISTS subject VARCHAR(200) NULL COMMENT 'Subject',
ADD COLUMN IF NOT EXISTS message TEXT NOT NULL COMMENT 'Message content',
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending' COMMENT 'Status',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Created timestamp';

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_orders_customer_email ON orders (customer_email);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status);
CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders (created_at);
CREATE INDEX IF NOT EXISTS idx_notes_status ON notes (status);
CREATE INDEX IF NOT EXISTS idx_notes_is_active ON notes (is_active);
CREATE INDEX IF NOT EXISTS idx_users_email ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_google_id ON users (google_id);
CREATE INDEX IF NOT EXISTS idx_cart_user_note ON cart (user_id, note_id);
CREATE INDEX IF NOT EXISTS idx_refresh_tokens_user ON refresh_tokens (user_id);
CREATE INDEX IF NOT EXISTS idx_refresh_tokens_token ON refresh_tokens (token);

-- Update existing records to have default values for required fields
UPDATE orders SET 
    order_id = CONCAT('ORD-', id, '-', UNIX_TIMESTAMP(created_at)),
    customer_email = 'unknown@example.com',
    customer_name = 'Unknown Customer'
WHERE order_id IS NULL OR customer_email IS NULL;

UPDATE notes SET
    tags = '[]',
    features = '[]',
    topics = '[]'
WHERE tags IS NULL OR features IS NULL OR topics IS NULL; 