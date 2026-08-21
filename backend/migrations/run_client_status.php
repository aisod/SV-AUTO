<?php
// Run client status migration
require_once __DIR__ . '/../config/config.php';

try {
    $sql = file_get_contents(__DIR__ . '/add_client_status.sql');
    $pdo->exec($sql);
    echo "<h2>✅ Client Status Migration Successful</h2>";
    echo "<p>The 'status' column has been added to the clients table.</p>";
    echo "<p>All existing clients have been set to 'approved' status.</p>";
    echo "<p><a href='../Admin/dashboard.php'>Go to Admin Dashboard</a></p>";
} catch (Exception $e) {
    echo "<h2>❌ Migration Failed</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
