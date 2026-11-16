<?php
// Compatibility wrapper: links and redirects still point here. Sign-in is handled by frontend/login.php,
// which has the CSRF token and the sign-in attempt limit this page lacked.
$qs = '';
if (!empty($_SERVER['QUERY_STRING'])) {
    $qs = '?' . $_SERVER['QUERY_STRING'];
}
header('Location: ../../login.php' . $qs);
exit;
