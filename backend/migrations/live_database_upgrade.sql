-- ============================================================================
-- SV Auto - live database upgrade (MariaDB 10.5+ / 11.x, Hostinger)
-- Brings a base-schema database up to what the application code expects.
--
-- Safe to run more than once: every statement uses IF NOT EXISTS / INSERT IGNORE
-- or only fills empty values. It never drops tables or deletes rows.
-- TAKE A BACKUP (phpMyAdmin > Export) BEFORE RUNNING.
-- Run in phpMyAdmin > SQL tab (keep "Enable foreign key checks" ticked).
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. New tables
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS currencies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code CHAR(3) NOT NULL UNIQUE,
    name VARCHAR(50) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    format ENUM('left','right') DEFAULT 'left',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO currencies (code, name, symbol, format) VALUES
('NAD', 'Namibian Dollar', 'N$', 'left'),
('ZAR', 'South African Rand', 'R', 'left'),
('USD', 'United States Dollar', '$', 'left'),
('EUR', 'Euro', '€', 'right'),
('GBP', 'British Pound', '£', 'left'),
('AUD', 'Australian Dollar', 'A$', 'left');

CREATE TABLE IF NOT EXISTS business (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(30),
    address TEXT,
    tax_number VARCHAR(50),
    logo_url VARCHAR(255) DEFAULT NULL,
    website VARCHAR(100),
    slogan VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One default row, only if the table is empty (edit these details later in the app / phpMyAdmin)
INSERT INTO business (name, email, phone, address, tax_number)
SELECT 'SV Auto Truck Repair', 'svautotruckrepair@gmail.com', '+264 81 446 9962', 'Windhoek, Namibia', '7098116-01-5'
WHERE NOT EXISTS (SELECT 1 FROM business);

CREATE TABLE IF NOT EXISTS labor_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate_type VARCHAR(50) NOT NULL COMMENT 'normal_hours, after_hours, weekend, holiday',
    hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rate_type (rate_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rates match the defaults enforced in backend/config/config.php (N$560 / 840 / 1120 / 1120)
INSERT IGNORE INTO labor_rates (rate_type, hourly_rate, description) VALUES
('normal_hours', 560.00, 'Normal working hours (Mon-Fri, 8am-5pm)'),
('after_hours', 840.00, 'After hours (Mon-Fri, 5pm-8am)'),
('weekend', 1120.00, 'Weekend rates (Saturday & Sunday)'),
('holiday', 1120.00, 'Public holidays and special days');

CREATE TABLE IF NOT EXISTS mobile_service_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    callout_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Fixed fee for going to client location',
    per_km_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Rate per kilometer traveled',
    min_callout_distance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Minimum distance for callout fee',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO mobile_service_rates (callout_fee, per_km_rate, min_callout_distance)
SELECT 500.00, 15.00, 5.00 WHERE NOT EXISTS (SELECT 1 FROM mobile_service_rates);

CREATE TABLE IF NOT EXISTS client_custom_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    rate_type VARCHAR(50) NOT NULL COMMENT 'normal_hours, after_hours, weekend, holiday',
    custom_hourly_rate DECIMAL(10,2) NOT NULL,
    notes TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY unique_client_rate (client_id, rate_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS public_holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    is_recurring TINYINT(1) DEFAULT 0 COMMENT '1 = repeats yearly (e.g., Christmas)',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO public_holidays (holiday_name, holiday_date, is_recurring) VALUES
('New Year''s Day', '2026-01-01', 1),
('Independence Day', '2026-03-21', 1),
('Good Friday', '2026-04-03', 0),
('Easter Monday', '2026-04-06', 0),
('Workers'' Day', '2026-05-01', 1),
('Cassinga Day', '2026-05-04', 1),
('Africa Day', '2026-05-25', 1),
('Ascension Day', '2026-05-14', 0),
('Heroes'' Day', '2026-08-26', 1),
('Human Rights Day', '2026-12-10', 1),
('Christmas Day', '2026-12-25', 1),
('Family Day', '2026-12-26', 1);

-- ---------------------------------------------------------------------------
-- 2. job_cards: new columns (status keeps its old values 'pending'/'complete'
--    and gains the newer ones, so no existing row is changed)
-- ---------------------------------------------------------------------------
ALTER TABLE job_cards
    ADD COLUMN IF NOT EXISTS client_id INT NULL DEFAULT NULL AFTER vehicle_id,
    ADD COLUMN IF NOT EXISTS extra_data LONGTEXT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS progress INT DEFAULT 0 COMMENT 'Completion percentage 0-100',
    ADD COLUMN IF NOT EXISTS labor_hours DECIMAL(5,2) DEFAULT 0 COMMENT 'Total labor hours',
    ADD COLUMN IF NOT EXISTS parts_used TEXT,
    ADD COLUMN IF NOT EXISTS technician_notes TEXT,
    ADD COLUMN IF NOT EXISTS started_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS service_type ENUM('in_shop','mobile') DEFAULT 'in_shop',
    ADD COLUMN IF NOT EXISTS work_date DATE NULL,
    ADD COLUMN IF NOT EXISTS work_start_time TIME NULL,
    ADD COLUMN IF NOT EXISTS work_end_time TIME NULL,
    ADD COLUMN IF NOT EXISTS total_hours DECIMAL(5,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS distance_km DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS labor_rate_applied DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS labor_cost DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS callout_fee DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS travel_cost DECIMAL(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

ALTER TABLE job_cards
    MODIFY COLUMN status ENUM('pending','in_progress','complete','new','diagnosed','quoted','approved',
                              'waiting_parts','completed','invoiced','paid') DEFAULT 'new';

CREATE INDEX IF NOT EXISTS idx_job_cards_client_id ON job_cards (client_id);
CREATE INDEX IF NOT EXISTS idx_job_card_status ON job_cards (status);
CREATE INDEX IF NOT EXISTS idx_work_date ON job_cards (work_date);
CREATE INDEX IF NOT EXISTS idx_service_type ON job_cards (service_type);

ALTER TABLE job_cards
    ADD CONSTRAINT fk_job_cards_client FOREIGN KEY IF NOT EXISTS (client_id) REFERENCES clients (id);

-- ---------------------------------------------------------------------------
-- 3. quotations
-- ---------------------------------------------------------------------------
ALTER TABLE quotations
    ADD COLUMN IF NOT EXISTS created_by INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS client_status VARCHAR(20) DEFAULT NULL COMMENT 'client_accepted, client_rejected, or NULL',
    ADD COLUMN IF NOT EXISTS client_response_date DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS client_response_notes TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS sent_to_portal TINYINT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 4. invoices (+ invoice_items)
-- ---------------------------------------------------------------------------
ALTER TABLE invoices
    ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS vat_amount DECIMAL(10,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS status_paid ENUM('unpaid','partial','paid') DEFAULT 'unpaid',
    ADD COLUMN IF NOT EXISTS issued_date DATE NULL,
    ADD COLUMN IF NOT EXISTS due_date DATE NULL,
    ADD COLUMN IF NOT EXISTS details TEXT,
    ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50),
    ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100),
    ADD COLUMN IF NOT EXISTS notes TEXT,
    ADD COLUMN IF NOT EXISTS sent_at TIMESTAMP NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_editable TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS manager_review_sent_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS manager_review_sent_by VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS manager_review_viewed_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS uq_invoices_invoice_number ON invoices (invoice_number);
CREATE INDEX IF NOT EXISTS idx_invoices_deleted_at ON invoices (deleted_at);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices (status);
CREATE INDEX IF NOT EXISTS idx_invoices_due_date ON invoices (due_date);

-- Fill the new columns for invoices that already exist (only where still empty)
UPDATE invoices SET status_paid = 'paid' WHERE status = 'paid' AND status_paid <> 'paid';
UPDATE invoices SET issued_date = DATE(created_at) WHERE issued_date IS NULL AND created_at IS NOT NULL;

CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 5. service_updates
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS service_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    technician_id INT,
    update_type ENUM('diagnosis','progress','parts_added','completed','note') DEFAULT 'progress',
    description TEXT,
    progress INT DEFAULT 0,
    labor_hours DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES job_cards(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_service_updates_job_card (job_card_id),
    INDEX idx_service_updates_technician (technician_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- 6. Other tables: recycle-bin (soft delete) and extra columns
-- ---------------------------------------------------------------------------
ALTER TABLE purchase_orders
    ADD COLUMN IF NOT EXISTS details TEXT,
    ADD COLUMN IF NOT EXISTS notes TEXT,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

ALTER TABLE expenses     ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE inventory     ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE employees     ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE clients       ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE clients
    ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reset_expiry DATETIME NULL DEFAULT NULL;
ALTER TABLE vehicles      ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

ALTER TABLE statutory_docs
    ADD COLUMN IF NOT EXISTS uploaded_by INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS uploaded_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reset_expiry DATETIME NULL DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- Check: should list the new tables and show 'business' with 1 row
-- ---------------------------------------------------------------------------
SELECT (SELECT COUNT(*) FROM business) AS business_rows,
       (SELECT COUNT(*) FROM labor_rates) AS labor_rate_rows,
       (SELECT COUNT(*) FROM currencies) AS currency_rows;
