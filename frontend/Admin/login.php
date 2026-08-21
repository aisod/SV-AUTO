<?php
// Compatibility wrapper: some links point to /Admin/login.php
// Forward to the actual auth login handler in Auth/
$qs = '';
if (!empty($_SERVER['QUERY_STRING'])) {
    $qs = '?' . $_SERVER['QUERY_STRING'];
}
header('Location: Auth/login.php' . $qs);
exit;
