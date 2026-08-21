<?php
/**
 * Database Migrations Runner
 * Run this file ONCE to apply all database changes for the quotation module
 */

require_once __DIR__ . '/../config/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Migrations</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #F5A623; }
        .success { background: #e8f5e8; border: 2px solid #2e7d32; padding: 15px; border-radius: 5px; color: #1b5e20; margin: 10px 0; }
        .error { background: #ffebee; border: 2px solid #c62828; padding: 15px; border-radius: 5px; color: #b71c1c; margin: 10px 0; }
        .info { background: #e3f2fd; border: 2px solid #1976d2; padding: 15px; border-radius: 5px; color: #0d47a1; margin: 10px 0; }
        .migration { background: #f5f5f5; padding: 10px; margin: 5px 0; border-left: 4px solid #F5A623; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Database Migrations Runner</h1>";

try {
    // Read the SQL file
    $sql_file = __DIR__ . '/database_migrations.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found: database_migrations.sql");
    }
    
    $sql = file_get_contents($sql_file);
    
    echo "<div class='info'><strong>📄 Reading migrations from:</strong> database_migrations.sql</div>";
    
    // Split by semicolons but keep multi-line statements together
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   strpos($stmt, '--') !== 0 && 
                   strpos($stmt, '/*') !== 0;
        }
    );
    
    $success_count = 0;
    $error_count = 0;
    
    echo "<h2>Running Migrations...</h2>";
    
    foreach ($statements as $statement) {
        // Skip comments and empty statements
        if (empty(trim($statement))) continue;
        
        // Extract migration name from comments
        if (preg_match('/MIGRATION \d+: (.+)/', $statement, $matches)) {
            echo "<div class='migration'><strong>📦 " . htmlspecialchars($matches[1]) . "</strong></div>";
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $success_count++;
            
            // Show what was executed (first 100 chars)
            $preview = substr(trim($statement), 0, 100);
            if (strlen($statement) > 100) $preview .= '...';
            echo "<div class='success'>✅ Executed: <code>" . htmlspecialchars($preview) . "</code></div>";
            
        } catch (PDOException $e) {
            // Check if error is because column/index already exists
            if (strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false ||
                strpos($e->getMessage(), 'already exists') !== false) {
                echo "<div class='info'>ℹ️ Skipped (already exists): <code>" . htmlspecialchars(substr($statement, 0, 100)) . "...</code></div>";
            } else {
                $error_count++;
                echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "<br><code>" . htmlspecialchars(substr($statement, 0, 100)) . "...</code></div>";
            }
        }
    }
    
    echo "<hr>";
    echo "<h2>📊 Migration Summary</h2>";
    echo "<div class='success'><strong>✅ Successful:</strong> $success_count statements</div>";
    
    if ($error_count > 0) {
        echo "<div class='error'><strong>❌ Errors:</strong> $error_count statements</div>";
    } else {
        echo "<div class='success'><strong>🎉 All migrations completed successfully!</strong></div>";
    }
    
    echo "<hr>";
    echo "<div class='info'>";
    echo "<h3>✅ What Was Updated:</h3>";
    echo "<ul>";
    echo "<li><strong>invoices</strong> table: Added <code>deleted_at</code> column for soft delete</li>";
    echo "<li><strong>job_cards</strong> table: Added <code>extra_data</code> column for additional fields</li>";
    echo "<li><strong>job_cards</strong> table: Added <code>client_id</code> column to link clients</li>";
    echo "<li><strong>quotations</strong> table: Added client acceptance tracking columns</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='info'>";
    echo "<h3>🔄 Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Go to <a href='quotations.php'>Quotations</a> page</li>";
    echo "<li>Create or view a quotation</li>";
    echo "<li>Test the client acceptance tracking</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>❌ Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "    </div>
</body>
</html>";
?>
