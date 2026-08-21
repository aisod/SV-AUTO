<?php
declare(strict_types=1);

$query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';

header('Location: Manager/manager_quotations.php' . $query, true, 302);
exit;
