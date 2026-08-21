<?php
// Migration script to add client_id column to users table
require_once __DIR__ . '/../config/config.php';

echo "Checking if client_id column exists in users table...\n";

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'client_id'");
    $columnExists = $stmt->fetch();
    
    if ($columnExists) {
        echo "✓ client_id column already exists in users table.\n";
    } else {
        echo "Adding client_id column to users table...\n";
        
        $pdo->exec("
            ALTER TABLE users 
            ADD COLUMN client_id INT NULL AFTER role_id,
            ADD FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
        ");
        
        echo "✓ client_id column added successfully!\n";
    }
    
    // Check if 'client' role exists
    $stmt = $pdo->query("SELECT id FROM roles WHERE LOWER(name) = 'client' LIMIT 1");
    $clientRole = $stmt->fetch();
    
    if ($clientRole) {
        echo "✓ 'client' role already exists with ID: " . $clientRole['id'] . "\n";
    } else {
        echo "Creating 'client' role...\n";
        
        $pdo->exec("
            INSERT INTO roles (name, permissions) 
            VALUES ('client', 'view_own_documents,respond_quotations,view_invoices,update_profile')
        ");
        
        $newRoleId = $pdo->lastInsertId();
        echo "✓ 'client' role created with ID: $newRoleId\n";
    }
    
    echo "\n=== Migration completed successfully! ===\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
