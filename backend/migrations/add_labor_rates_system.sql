-- ============================================================================
-- LABOR RATES SYSTEM - Database Schema
-- ============================================================================

-- 1. Labor Rates Table (Default rates set by admin)
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

-- Insert default labor rates
INSERT INTO labor_rates (rate_type, hourly_rate, description) VALUES
('normal_hours', 150.00, 'Normal working hours (Mon-Fri, 8am-5pm)'),
('after_hours', 225.00, 'After hours (Mon-Fri, 5pm-8am)'),
('weekend', 300.00, 'Weekend rates (Saturday & Sunday)'),
('holiday', 450.00, 'Public holidays and special days')
ON DUPLICATE KEY UPDATE rate_type = rate_type;

-- 2. Mobile Service Rates Table
CREATE TABLE IF NOT EXISTS mobile_service_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    callout_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Fixed fee for going to client location',
    per_km_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Rate per kilometer traveled',
    min_callout_distance DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Minimum distance for callout fee',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default mobile service rates
INSERT INTO mobile_service_rates (callout_fee, per_km_rate, min_callout_distance) VALUES
(500.00, 15.00, 5.00);

-- 3. Client Custom Rates Table (Override default rates for specific clients)
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

-- 4. Public Holidays Table
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

-- Insert common Namibian public holidays for 2026
INSERT INTO public_holidays (holiday_name, holiday_date, is_recurring) VALUES
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
('Family Day', '2026-12-26', 1)
ON DUPLICATE KEY UPDATE holiday_name = holiday_name;

-- 5. Enhance Job Cards Table with labor tracking (check if columns exist first)
SET @dbname = DATABASE();
SET @tablename = 'job_cards';

-- Add service_type column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'service_type');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN service_type ENUM(''in_shop'', ''mobile'') DEFAULT ''in_shop'' COMMENT ''Where work is performed''',
    'SELECT ''Column service_type already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add work_date column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'work_date');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN work_date DATE COMMENT ''Date work was performed''',
    'SELECT ''Column work_date already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add work_start_time column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'work_start_time');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN work_start_time TIME COMMENT ''Start time of work''',
    'SELECT ''Column work_start_time already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add work_end_time column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'work_end_time');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN work_end_time TIME COMMENT ''End time of work''',
    'SELECT ''Column work_end_time already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add total_hours column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'total_hours');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN total_hours DECIMAL(5,2) DEFAULT 0.00 COMMENT ''Total hours worked''',
    'SELECT ''Column total_hours already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add distance_km column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'distance_km');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN distance_km DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Distance traveled for mobile service''',
    'SELECT ''Column distance_km already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add labor_rate_applied column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'labor_rate_applied');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN labor_rate_applied DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Hourly rate applied''',
    'SELECT ''Column labor_rate_applied already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add labor_cost column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'labor_cost');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN labor_cost DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Total labor cost calculated''',
    'SELECT ''Column labor_cost already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add callout_fee column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'callout_fee');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN callout_fee DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Callout fee if mobile''',
    'SELECT ''Column callout_fee already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add travel_cost column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'travel_cost');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE job_cards ADD COLUMN travel_cost DECIMAL(10,2) DEFAULT 0.00 COMMENT ''Travel cost (km * rate)''',
    'SELECT ''Column travel_cost already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Create indexes for faster queries (only if they don't exist)
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'job_cards' AND INDEX_NAME = 'idx_work_date');
SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_work_date ON job_cards(work_date)',
    'SELECT ''Index idx_work_date already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'job_cards' AND INDEX_NAME = 'idx_service_type');
SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_service_type ON job_cards(service_type)',
    'SELECT ''Index idx_service_type already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'public_holidays' AND INDEX_NAME = 'idx_holiday_date');
SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_holiday_date ON public_holidays(holiday_date)',
    'SELECT ''Index idx_holiday_date already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'client_custom_rates' AND INDEX_NAME = 'idx_client_custom_rates');
SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_client_custom_rates ON client_custom_rates(client_id, rate_type)',
    'SELECT ''Index idx_client_custom_rates already exists'' AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- NOTES:
-- - Labor rates can be updated by admin in settings
-- - Client custom rates override default rates
-- - Public holidays can be managed (add/edit/delete)
-- - Job cards now track work time and calculate labor costs automatically
-- ============================================================================
