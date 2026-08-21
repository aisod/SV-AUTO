<?php
declare(strict_types=1);

/** Manager portal — same feed as Admin with manager link resolution. */
$_GET['portal'] = 'manager';
require __DIR__ . '/../../Admin/Utils/notifications_feed.php';
