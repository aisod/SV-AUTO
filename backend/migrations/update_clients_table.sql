-- Migration: Update clients table for enhanced registration system
-- Date: 2026-04-22
-- Description: Adds Google OAuth support and additional client fields

-- Check if table exists, if not create it
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) DEFAULT NULL,
    title VARCHAR(10) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender VARCHAR(30) DEFAULT NULL,
    id_passport VARCHAR(100) DEFAULT NULL,
    email VARCHAR(191) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    whatsapp VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If table already exists, add new columns if they don't exist
ALTER TABLE clients 
ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) DEFAULT NULL AFTER id,
ADD COLUMN IF NOT EXISTS title VARCHAR(10) DEFAULT 'Mr' AFTER google_id,
ADD COLUMN IF NOT EXISTS date_of_birth DATE DEFAULT NULL AFTER last_name,
ADD COLUMN IF NOT EXISTS gender VARCHAR(30) DEFAULT NULL AFTER date_of_birth,
ADD COLUMN IF NOT EXISTS id_passport VARCHAR(100) DEFAULT NULL AFTER gender,
ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) DEFAULT NULL AFTER phone,
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL AFTER address,
ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'client' AFTER password_hash;

-- Add indexes for performance
ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_google_id (google_id);
ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_email (email);

-- Update existing records to have default title if NULL
UPDATE clients SET title = 'Mr' WHERE title IS NULL OR title = '';

-- Rename 'password' column to 'password_hash' if it exists
-- Note: This is a safe operation that preserves data
ALTER TABLE clients CHANGE COLUMN password password_hash VARCHAR(255) NOT NULL;
