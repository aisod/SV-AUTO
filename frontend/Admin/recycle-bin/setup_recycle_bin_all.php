<?php
require_once __DIR__ . '/../../../backend/config/config.php';

echo "<h2>Setting up Recycle Bin for All Modules</h2>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

$tables = [
    'inventory',
    'quotations',
    'job_cards',
    'invoices',
    'employees',
    'purchase_orders',
    'clients',
    'vehicles'
];

foreach ($tables as $table) {
    try {
        // Check if column already exists
        $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE 'deleted_at'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='info'>✓ Table '$table' already has 'deleted_at' column</p>";
            continue;
        }
        
        // Add deleted_at column
        $pdo->exec("ALTER TABLE $table ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
        echo "<p class='success'>✓ Added 'deleted_at' column to '$table' table</p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error with table '$table': " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<h3 class='success'>Setup Complete!</h3>";
echo "<p>All tables now support soft delete (recycle bin).</p>";
echo "<p><a href='recycle_bin.php' style='background:#F5A623;color:white;padding:10px 20px;text-decoration:none;border-radius:8px;'>Go to Recycle Bin</a></p>";

