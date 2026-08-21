<?php
// Compatibility wrapper: some parts of the app link to /Admin/logout.php
// Forward to the actual auth logout handler in Auth/
// Preserve optional ?redirect=login
$qs = '';
if (!empty($_SERVER['QUERY_STRING'])) {
    $qs = '?' . $_SERVER['QUERY_STRING'];
}
header('Location: Auth/logout.php' . $qs);
exit;
