<?php
/**
 * Quick Account Verification
 * Check if admin and manager accounts exist
 */
require_once __DIR__ . '/../config/config.php';

echo "<h2>Account Verification</h2>";
echo "<style>body{font-family:sans-serif;padding:20px}table{border-collapse:collapse;width:100%;max-width:800px}th,td{border:1px solid #ddd;padding:12px;text-align:left}th{background:#F7A100;color:white}tr:nth-child(even){background:#f9f9f9}</style>";

try {
    // Check all users
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.email, r.name as role, u.created_at
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        ORDER BY u.id DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "<p style='color:red;'>⚠️ No users found in database!</p>";
        echo "<p><a href='setup_test_accounts.php' style='background:#F7A100;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>Create Test Accounts</a></p>";
    } else {
        echo "<h3>All Users in Database:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created</th></tr>";
        
        $hasAdmin = false;
        $hasManager = false;
        
        foreach ($users as $user) {
            $roleClass = '';
            if (strtolower($user['role']) === 'admin') {
                $roleClass = 'style="background:#4CAF50;color:white;font-weight:bold"';
                $hasAdmin = true;
            } elseif (strtolower($user['role']) === 'manager') {
                $roleClass = 'style="background:#2196F3;color:white;font-weight:bold"';
                $hasManager = true;
            } elseif (strtolower($user['role']) === 'client') {
                $roleClass = 'style="background:#FF9800;color:white"';
            }
            
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td><strong>{$user['email']}</strong></td>";
            echo "<td {$roleClass}>" . strtoupper($user['role']) . "</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Status Check:</h3>";
        echo "<ul>";
        echo $hasAdmin ? "<li style='color:green'>✓ Admin account exists</li>" : "<li style='color:red'>✗ Admin account missing</li>";
        echo $hasManager ? "<li style='color:green'>✓ Manager account exists</li>" : "<li style='color:red'>✗ Manager account missing</li>";
        echo "</ul>";
        
        if (!$hasAdmin || !$hasManager) {
            echo "<p><a href='setup_test_accounts.php' style='background:#F7A100;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;margin-top:20px;'>Create Missing Accounts</a></p>";
        }
    }
    
    echo "<hr style='margin:30px 0'>";
    echo "<h3>Test Login Credentials:</h3>";
    echo "<div style='background:#f5f5f5;padding:20px;border-radius:8px;max-width:600px'>";
    echo "<p><strong>Admin:</strong><br>Email: daviid@svauto.com<br>Password: 123456789012</p>";
    echo "<p><strong>Manager:</strong><br>Email: saima@svauto.com<br>Password: 123456789012</p>";
    echo "</div>";
    
    echo "<p style='margin-top:30px'>";
    echo "<a href='Admin/login.php' style='background:#4CAF50;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;margin-right:10px;'>Go to Login</a>";
    echo "<a href='index.php' style='background:#2196F3;color:white;padding:12px 24px;text-decoration:none;border-radius:5px;display:inline-block;'>Back to Home</a>";
    echo "</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
