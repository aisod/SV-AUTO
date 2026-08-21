<?php
/**
 * Default entry for /Admin/ — avoid Apache directory listing.
 * Hash fragments are handled in the browser (not sent to PHP).
 */
session_start();
$isLoggedIn = isset($_SESSION['user_id']);

if (!$isLoggedIn) {
    header('Location: Auth/login.php', true, 302);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting…</title>
    <script>
    (function () {
        var hash = window.location.hash || '';
        var profile = ['#my-profile', '#account-details', '#security'];
        var target = profile.indexOf(hash) >= 0 ? 'settings.php' + hash : 'dashboard.php';
        window.location.replace(target);
    })();
    </script>
    <meta http-equiv="refresh" content="0;url=dashboard.php">
</head>
<body>
    <p><a href="dashboard.php">Continue to dashboard</a></p>
</body>
</html>
