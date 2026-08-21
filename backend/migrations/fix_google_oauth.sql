-- Fix Google OAuth Login Bug
-- Ensures google_id column exists and has proper index

-- Check if google_id column exists, if not add it
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL AFTER email;

-- Add index for faster lookups
ALTER TABLE clients 
ADD INDEX IF NOT EXISTS idx_google_id (google_id);

-- Ensure status column has correct values
ALTER TABLE clients 
MODIFY COLUMN status VARCHAR(20) DEFAULT 'pending';
