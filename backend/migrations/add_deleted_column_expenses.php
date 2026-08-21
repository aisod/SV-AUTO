<?php
// Run this once to add deleted_at column to expenses table
require_once __DIR__ . '/../../../backend/config/config.php';

try {
    // Add deleted_at column to expenses table
    $pdo->exec("ALTER TABLE expenses ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
    echo "Success! Added deleted_at column to expenses table.<br>";
    echo "You can now use the recycle bin feature.<br>";
    echo "<a href='expenses.php'>Go to Expenses</a>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists. No changes needed.<br>";
        echo "<a href='expenses.php'>Go to Expenses</a>";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
