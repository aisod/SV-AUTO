<?php
// Run this file ONCE to add details and notes columns to purchase_orders table
require_once __DIR__ . '/../config/config.php';

try {
    echo "Starting Purchase Order Enhancement Migration...\n\n";

    // Check if columns already exist
    $check = $pdo->query("SHOW COLUMNS FROM purchase_orders LIKE 'details'");
    if ($check->rowCount() > 0) {
        echo "✓ Columns already exist. Migration skipped.\n";
        exit;
    }

    // Read and execute migration
    $sql = file_get_contents(__DIR__ . '/enhance_purchase_orders.sql');
    $pdo->exec($sql);

    echo "✓ Successfully added 'details' and 'notes' columns to purchase_orders table\n";
    echo "✓ Migration completed successfully!\n\n";
    echo "You can now:\n";
    echo "  - Create purchase orders with itemized parts from quotations\n";
    echo "  - View detailed breakdown of what parts are being purchased\n";
    echo "  - Add notes to purchase orders\n";

} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
