<?php
require_once __DIR__ . '/../config/config.php';

echo "<h2>Running Job Card Progress Migration...</h2>";

try {
    $sql = file_get_contents(__DIR__ . '/add_job_card_progress.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "<p style='color:green;'>✓ Executed: " . substr($statement, 0, 80) . "...</p>";
        }
    }
    
    echo "<p style='color:green;font-weight:bold;'>✓ Migration completed successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;font-weight:bold;'>✗ Migration failed: " . $e->getMessage() . "</p>";
}
?>
