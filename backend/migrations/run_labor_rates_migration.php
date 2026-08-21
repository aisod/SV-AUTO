<?php
// Run this file ONCE to add labor rates system to the database
require_once __DIR__ . '/../config/config.php';

try {
    echo "Starting Labor Rates System Migration...\n\n";

    // 1. Create labor_rates table
    echo "Creating labor_rates table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS labor_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rate_type VARCHAR(50) NOT NULL COMMENT 'normal_hours, after_hours, weekend, holiday',
            hourly_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_rate_type (rate_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insert default rates
    $pdo->exec("
        INSERT INTO labor_rates (rate_type, hourly_rate, description) VALUES
        ('normal_hours', 150.00, 'Normal working hours (Mon-Fri, 8am-5pm)'),
        ('after_hours', 225.00, 'After hours (Mon-Fri, 5pm-8am)'),
        ('weekend', 300.00, 'Weekend rates (Saturday & Sunday)'),
        ('holiday', 450.00, 'Public holidays and special days')
        ON DUPLICATE KEY UPDATE rate_type = rate_type
    ");

    // 2. Create mobile_service_rates table
    echo "Creating mobile_service_rates table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mobile_service_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            callout_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            per_km_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            min_callout_distance DECIMAL(10,2) DEFAULT 0.00,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("INSERT INTO mobile_service_rates (callout_fee, per_km_rate, min_callout_distance) VALUES (500.00, 15.00, 5.00)");

    // 3. Create client_custom_rates table
    echo "Creating client_custom_rates table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS client_custom_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            rate_type VARCHAR(50) NOT NULL,
            custom_hourly_rate DECIMAL(10,2) NOT NULL,
            notes TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            UNIQUE KEY unique_client_rate (client_id, rate_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // 4. Create public_holidays table
    echo "Creating public_holidays table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS public_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_name VARCHAR(100) NOT NULL,
            holiday_date DATE NOT NULL,
            is_recurring TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_holiday_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insert holidays
    $pdo->exec("
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
        ON DUPLICATE KEY UPDATE holiday_name = holiday_name
    ");

    // 5. Add columns to job_cards table
    echo "Enhancing job_cards table...\n";
    
    $columns = [
        "service_type ENUM('in_shop', 'mobile') DEFAULT 'in_shop'",
        "work_date DATE",
        "work_start_time TIME",
        "work_end_time TIME",
        "total_hours DECIMAL(5,2) DEFAULT 0.00",
        "distance_km DECIMAL(10,2) DEFAULT 0.00",
        "labor_rate_applied DECIMAL(10,2) DEFAULT 0.00",
        "labor_cost DECIMAL(10,2) DEFAULT 0.00",
        "callout_fee DECIMAL(10,2) DEFAULT 0.00",
        "travel_cost DECIMAL(10,2) DEFAULT 0.00"
    ];

    foreach ($columns as $column) {
        $columnName = explode(' ', $column)[0];
        try {
            // Check if column exists
            $check = $pdo->query("SHOW COLUMNS FROM job_cards LIKE '$columnName'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE job_cards ADD COLUMN $column");
                echo "  Added column: $columnName\n";
            } else {
                echo "  Column already exists: $columnName\n";
            }
        } catch (PDOException $e) {
            echo "  Warning: Could not add $columnName - " . $e->getMessage() . "\n";
        }
    }

    // 6. Create indexes
    echo "Creating indexes...\n";
    
    $indexes = [
        ['job_cards', 'idx_work_date', 'work_date'],
        ['job_cards', 'idx_service_type', 'service_type'],
        ['public_holidays', 'idx_holiday_date', 'holiday_date']
    ];

    foreach ($indexes as list($table, $indexName, $column)) {
        try {
            $check = $pdo->query("SHOW INDEX FROM $table WHERE Key_name = '$indexName'");
            if ($check->rowCount() == 0) {
                $pdo->exec("CREATE INDEX $indexName ON $table($column)");
                echo "  Created index: $indexName\n";
            } else {
                echo "  Index already exists: $indexName\n";
            }
        } catch (PDOException $e) {
            echo "  Warning: Could not create index $indexName - " . $e->getMessage() . "\n";
        }
    }

    echo "\n✓ Migration completed successfully!\n\n";
    
    echo "Tables created:\n";
    echo "  ✓ labor_rates\n";
    echo "  ✓ mobile_service_rates\n";
    echo "  ✓ client_custom_rates\n";
    echo "  ✓ public_holidays\n";
    echo "  ✓ job_cards (enhanced)\n\n";
    
    echo "Default rates:\n";
    echo "  - Normal hours: N\$150/hour\n";
    echo "  - After hours: N\$225/hour\n";
    echo "  - Weekend: N\$300/hour\n";
    echo "  - Holiday: N\$450/hour\n";
    echo "  - Callout fee: N\$500\n";
    echo "  - Per km: N\$15\n\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
