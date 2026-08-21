<?php
/**
 * Migration: Fix Google OAuth Login Bug
 * 
 * This migration ensures:
 * 1. google_id column exists in clients table
 * 2. Index on google_id for faster lookups
 * 3. Status column has correct type
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Running Google OAuth Fix Migration</h2>";
echo "<hr>";

try {
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/fix_google_oauth.sql');
    
    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "<h3>Executing SQL Statements:</h3>";
    
    foreach ($statements as $index => $statement) {
        if (empty(trim($statement))) continue;
        
        echo "<p><strong>Statement " . ($index + 1) . ":</strong></p>";
        echo "<pre>" . htmlspecialchars($statement) . "</pre>";
        
        try {
            $pdo->exec($statement);
            echo "<p style='color: green;'>✓ Success</p>";
        } catch (PDOException $e) {
            // Check if error is about column already existing
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "<p style='color: orange;'>⚠ Column already exists (skipped)</p>";
            } elseif (strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "<p style='color: orange;'>⚠ Index already exists (skipped)</p>";
            } else {
                echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
        
        echo "<hr>";
    }
    
    echo "<h3 style='color: green;'>✓ Migration completed!</h3>";
    echo "<p><a href='../Admin/dashboard.php'>← Back to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Migration failed!</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='../Admin/dashboard.php'>← Back to Dashboard</a></p>";
}
?>
