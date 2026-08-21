<?php
require_once __DIR__ . '/../../../backend/config/config.php';

try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM statutory_docs LIKE 'deleted_at'");
    if ($stmt->rowCount() > 0) {
        echo "Column 'deleted_at' already exists in statutory_docs table.";
        exit;
    }
    
    // Add deleted_at column
    $pdo->exec("ALTER TABLE statutory_docs ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL");
    
    echo "Success! Added deleted_at column to statutory_docs table.<br>";
    echo "You can now use the recycle bin feature for statutory documents.<br>";
    echo "<a href='statutory.php'>Go to Statutory Docs</a>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

