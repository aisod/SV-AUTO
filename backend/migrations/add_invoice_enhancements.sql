-- ============================================================================
-- INVOICE ENHANCEMENTS MIGRATION
-- Purpose: Add fields for editable invoices, payment tracking, and PDF/email
-- Date: 2026-04-06
-- ============================================================================

-- Add invoice details and tracking fields
ALTER TABLE invoices 
ADD COLUMN IF NOT EXISTS details TEXT COMMENT 'Invoice line items and description',
ADD COLUMN IF NOT EXISTS due_date DATE COMMENT 'Payment due date',
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) COMMENT 'Cash, Card, Bank Transfer, etc.',
ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100) COMMENT 'Transaction reference or check number',
ADD COLUMN IF NOT EXISTS notes TEXT COMMENT 'Additional notes or terms',
ADD COLUMN IF NOT EXISTS sent_at TIMESTAMP NULL COMMENT 'When invoice was emailed to client',
ADD COLUMN IF NOT EXISTS is_editable BOOLEAN DEFAULT TRUE COMMENT 'FALSE after payment to lock editing';

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status);
CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON invoices(due_date);

SELECT 'Invoice enhancements migration completed!' AS status;
