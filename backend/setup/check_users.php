<?php
/**
 * Check Users Script
 * Shows all users in the database and their roles
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Current Users in Database</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background-color: #F7A100; color: white; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .admin { background-color: #ffebee; }
    .manager { background-color: #e3f2fd; }
    .client { background-color: #f1f8e9; }
</style>";

try {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.role_id, r.name as role_name, u.created_at
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.id
    ");
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color: red;'>No users found in database!</p>";
        echo "<p><a href='setup_test_accounts.php'>Create Test Accounts</a></p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role ID</th><th>Role Name</th><th>Created</th></tr>";
        
        foreach ($users as $user) {
            $roleClass = strtolower($user['role_name'] ?? 'unknown');
            echo "<tr class='{$roleClass}'>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td><strong>{$user['email']}</strong></td>";
            echo "<td>{$user['role_id']}</td>";
            echo "<td><strong>" . strtoupper($user['role_name'] ?? 'UNKNOWN') . "</strong></td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        echo "<h3>Summary:</h3>";
        echo "<ul>";
        echo "<li>Total Users: " . count($users) . "</li>";
        
        $roleCount = array_count_values(array_column($users, 'role_name'));
        foreach ($roleCount as $role => $count) {
            echo "<li>" . ucfirst($role) . ": {$count}</li>";
        }
        echo "</ul>";
    }
    
    // Check roles table
    echo "<h2>Available Roles</h2>";
    $rolesStmt = $pdo->query("SELECT * FROM roles ORDER BY id");
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Role Name</th><th>Permissions</th></tr>";
    foreach ($roles as $role) {
        echo "<tr>";
        echo "<td>{$role['id']}</td>";
        echo "<td><strong>{$role['name']}</strong></td>";
        echo "<td>{$role['permissions']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='clear_session.php'>Clear Session</a> | ";
echo "<a href='setup_test_accounts.php'>Setup Test Accounts</a> | ";
echo "<a href='Admin/login.php'>Go to Login</a></p>";
?>
