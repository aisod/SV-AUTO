-- ============================================================================
-- SV AUTO MANAGEMENT SYSTEM - DATABASE MIGRATIONS
-- ============================================================================
-- This file contains all database schema updates for the quotation module
-- Run this file once to apply all necessary changes
-- ============================================================================

-- ============================================================================
-- MIGRATION 1: Add soft delete to invoices
-- Purpose: Enable soft delete functionality for invoices (recycle bin)
-- Date: 2026-04-06
-- ============================================================================

ALTER TABLE invoices ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
CREATE INDEX IF NOT EXISTS idx_invoices_deleted_at ON invoices(deleted_at);

-- ============================================================================
-- MIGRATION 2: Add extra_data to job_cards
-- Purpose: Store additional form fields (TO lines, contact lines, etc.)
-- Date: 2026-04-06
-- ============================================================================

ALTER TABLE job_cards ADD COLUMN IF NOT EXISTS extra_data JSON NULL DEFAULT NULL;

-- ============================================================================
-- MIGRATION 3: Add client_id to job_cards
-- Purpose: Link job cards directly to clients (not just through vehicles)
-- Date: 2026-04-06
-- ============================================================================

ALTER TABLE job_cards ADD COLUMN IF NOT EXISTS client_id INT NULL DEFAULT NULL AFTER vehicle_id;

-- Add foreign key constraint (only if not exists)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'job_cards' 
                  AND CONSTRAINT_NAME = 'fk_job_cards_client');

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE job_cards ADD CONSTRAINT fk_job_cards_client FOREIGN KEY (client_id) REFERENCES clients(id)',
    'SELECT "Foreign key already exists" AS message');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index
CREATE INDEX IF NOT EXISTS idx_job_cards_client_id ON job_cards(client_id);

-- ============================================================================
-- MIGRATION 4: Add client acceptance tracking to quotations
-- Purpose: Track client responses (accepted/rejected) for quotations
-- Date: 2026-04-06
-- ============================================================================

ALTER TABLE quotations 
ADD COLUMN IF NOT EXISTS client_status VARCHAR(20) DEFAULT NULL COMMENT 'Client response: client_accepted, client_rejected, or NULL',
ADD COLUMN IF NOT EXISTS client_response_date DATETIME DEFAULT NULL COMMENT 'When client accepted/rejected',
ADD COLUMN IF NOT EXISTS client_response_notes TEXT DEFAULT NULL COMMENT 'Client notes or reason for rejection';

-- Update existing approved quotations to have NULL client_status (waiting for client response)
UPDATE quotations SET client_status = NULL WHERE status = 'approved' AND client_status IS NULL;

-- ============================================================================
-- END OF MIGRATIONS
-- ============================================================================

SELECT 'All migrations completed successfully!' AS status;
