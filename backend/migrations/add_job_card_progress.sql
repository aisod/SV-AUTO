-- Add progress tracking fields to job_cards table
ALTER TABLE job_cards 
ADD COLUMN IF NOT EXISTS progress INT DEFAULT 0 COMMENT 'Completion percentage 0-100',
ADD COLUMN IF NOT EXISTS labor_hours DECIMAL(5,2) DEFAULT 0 COMMENT 'Total labor hours',
ADD COLUMN IF NOT EXISTS parts_used TEXT COMMENT 'JSON array of parts used',
ADD COLUMN IF NOT EXISTS technician_notes TEXT COMMENT 'Technician work notes',
ADD COLUMN IF NOT EXISTS started_at TIMESTAMP NULL COMMENT 'When work started',
ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL COMMENT 'When work completed';

-- Update status enum to include more realistic statuses
ALTER TABLE job_cards 
MODIFY COLUMN status ENUM('new', 'diagnosed', 'quoted', 'approved', 'in_progress', 'waiting_parts', 'completed', 'invoiced', 'paid') DEFAULT 'new';

-- Create service_updates table for tracking work progress
CREATE TABLE IF NOT EXISTS service_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    technician_id INT,
    update_type ENUM('diagnosis', 'progress', 'parts_added', 'completed', 'note') DEFAULT 'progress',
    description TEXT,
    progress INT DEFAULT 0 COMMENT 'Progress percentage at this update',
    labor_hours DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for faster queries
CREATE INDEX IF NOT EXISTS idx_job_card_status ON job_cards(status);
CREATE INDEX IF NOT EXISTS idx_service_updates_job_card ON service_updates(job_card_id);
