<?php
require_once __DIR__ . '/../../../backend/config/config.php';
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

// Check if redirect parameter is set
$redirect = $_GET['redirect'] ?? 'home';
if ($redirect === 'login') {
    header("Location: login.php?force=1");
} else {
    // Go to the project root index (two levels up from Admin/Auth)
    header("Location: ../../index.php");
}
exit();
?>
