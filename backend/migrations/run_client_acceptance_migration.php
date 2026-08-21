<?php
require_once __DIR__ . '/../../../backend/config/config.php';

echo "<h2>Running Client Acceptance Migration</h2>";

try {
    $sql = file_get_contents(__DIR__ . '/add_client_acceptance_columns.sql');
    $pdo->exec($sql);
    echo "<p style='color:green;'>✓ Migration completed successfully!</p>";
    echo "<p>Added columns: client_status, client_response_date, client_response_notes</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Migration failed: " . $e->getMessage() . "</p>";
}

echo "<p><a href='Quotation/quotations.php'>Go to Quotations</a></p>";

