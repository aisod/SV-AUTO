-- Migration: Add status column to clients table for admin approval system
-- Date: 2026-04-23
-- Description: Adds status column to track client approval status

ALTER TABLE clients ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending' AFTER role;

-- Update existing clients to approved status
UPDATE clients SET status = 'approved' WHERE status IS NULL OR status = '';
