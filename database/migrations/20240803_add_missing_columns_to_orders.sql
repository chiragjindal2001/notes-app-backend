-- Migration to add missing columns from PostgreSQL schema to MySQL orders table
-- Date: 2024-08-03

-- Add missing columns to orders table
ALTER TABLE orders 
ADD COLUMN order_id VARCHAR(50) NOT NULL COMMENT 'Unique order identifier',
ADD COLUMN customer_email VARCHAR(100) NOT NULL COMMENT 'Customer email address',
ADD COLUMN customer_name VARCHAR(100) NULL COMMENT 'Customer full name',
ADD COLUMN phone VARCHAR(32) NULL COMMENT 'Customer phone number',
ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last updated timestamp';

-- Add unique constraint on order_id
ALTER TABLE orders ADD UNIQUE KEY uk_orders_order_id (order_id);

-- Add indexes for better performance
CREATE INDEX idx_orders_customer_email ON orders (customer_email);
CREATE INDEX idx_orders_status ON orders (status);
CREATE INDEX idx_orders_created_at ON orders (created_at);

-- Update existing records to have default values for required fields
UPDATE orders SET 
    order_id = CONCAT('ORD-', id, '-', UNIX_TIMESTAMP(created_at)),
    customer_email = 'unknown@example.com',
    customer_name = 'Unknown Customer'
WHERE order_id IS NULL OR customer_email IS NULL; 