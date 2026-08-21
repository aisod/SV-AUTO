-- Fix service_updates foreign key constraint
-- Drop the existing table and recreate with correct foreign key

DROP TABLE IF EXISTS service_updates;

CREATE TABLE service_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    technician_id INT,
    update_type ENUM('diagnosis', 'progress', 'parts_added', 'completed', 'note') DEFAULT 'progress',
    description TEXT,
    progress INT DEFAULT 0 COMMENT 'Progress percentage at this update',
    labor_hours DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_service_updates_job_card ON service_updates(job_card_id);
CREATE INDEX idx_service_updates_technician ON service_updates(technician_id);
