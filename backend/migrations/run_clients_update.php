<?php
/**
 * Migration Runner: Update Clients Table
 * Run this file once to update the clients table structure
 * Access: http://localhost:8080/SV-Auto-main/SV-Auto-main/migrations/run_clients_update.php
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Running Clients Table Migration...</h2>";
echo "<pre>";

try {
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/update_clients_table.sql');
    
    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
            echo "✓ Executed successfully\n";
        } catch (PDOException $e) {
            // Ignore "duplicate column" errors as they're expected
            if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "→ Column already exists (skipped)\n";
            } else {
                $errorCount++;
                echo "✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Migration completed!\n";
    echo "Successful: $successCount\n";
    echo "Errors: $errorCount\n";
    echo "========================================\n";
    echo "\nClients table is now ready for the new registration system.\n";
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='../Admin/register.php'>Go to Registration Page</a></p>";
