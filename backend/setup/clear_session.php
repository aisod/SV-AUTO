<?php
/**
 * Clear Session Script
 * Run this to clear all active sessions and start fresh
 */

session_start();

// Clear all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

echo "<h2>✓ Session Cleared Successfully</h2>";
echo "<p>All active sessions have been cleared.</p>";
echo "<p><a href='Admin/login.php'>Go to Login Page</a></p>";
?>
