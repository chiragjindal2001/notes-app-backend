-- Migration: Add is_active column to notes table
ALTER TABLE notes ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT TRUE; 