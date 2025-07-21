-- Migration to update cart_items table to use only user_id

-- First, ensure all cart items have a user_id
-- For any items with session_id but no user_id, we'll need to either:
-- 1. Associate them with a user if we can (requires login before checkout)
-- 2. Remove them (less ideal, but ensures data consistency)

-- For this migration, we'll remove any guest cart items since we can't associate them with a user
-- This is a business decision - you might want to handle this differently

-- Step 1: Delete any cart items without a user_id (guest carts)
-- COMMENT OUT THE FOLLOWING LINE IF YOU WANT TO KEEP GUEST CARTS
-- DELETE FROM cart_items WHERE user_id IS NULL;

-- Step 2: Make user_id required
ALTER TABLE cart_items 
    ALTER COLUMN user_id SET NOT NULL;

-- Step 3: Drop the session_id column
ALTER TABLE cart_items 
    DROP COLUMN IF EXISTS session_id;

-- Step 4: Add foreign key constraint to users table
ALTER TABLE cart_items
    ADD CONSTRAINT fk_cart_items_user_id
    FOREIGN KEY (user_id) 
    REFERENCES users(id)
    ON DELETE CASCADE;

-- Step 5: Create index on user_id for better performance
CREATE INDEX IF NOT EXISTS idx_cart_items_user_id ON cart_items(user_id);
