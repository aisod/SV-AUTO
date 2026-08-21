<?php
/**
 * Fix service_updates foreign key constraint
 * Changes technician_id to reference users table instead of employees table
 */

require_once __DIR__ . '/../config/config.php';

echo "Starting service_updates foreign key fix...\n";

try {
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/fix_service_updates_fk.sql');
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "✓ service_updates table recreated with correct foreign key!\n";
    echo "✓ Foreign key now references users(id) instead of employees(id)\n";
    echo "✓ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
