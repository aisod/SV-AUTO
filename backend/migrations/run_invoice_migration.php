<?php
require_once __DIR__ . '/../../backend/config/config.php';

echo "<h2>Running Invoice Enhancements Migration...</h2>";

try {
    $sql = file_get_contents(__DIR__ . '/add_invoice_enhancements.sql');
    $pdo->exec($sql);
    echo "<p style='color:green;font-weight:bold;'>✓ Migration completed successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>✗ Migration failed: " . $e->getMessage() . "</p>";
}
?>
