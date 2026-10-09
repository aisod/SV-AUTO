<?php
/**
 * Finds /Admin/<name>.php inside the module folders and redirects there (307 keeps POST).
 * Only reached through frontend/Admin/.htaccess when the file does not exist at the Admin root.
 */
$file = (string) ($_GET['f'] ?? '');

// Old/short names that differ from the real file name.
$aliases = [
    'roles.php'          => 'user-role/user_roles.php',
    'statutory_docs.php' => 'Statutory/statutory.php',
    'services.php'       => 'Service/services_update.php',
];

$folders = [
    'Inventory', 'Employee', 'Expense', 'Service', 'Statutory', 'purchase-order', 'hr-request',
    'user-role', 'JobCard', 'Quotation', 'Invoice', 'Client', 'Report', 'recycle-bin', 'Utils',
];

$target = null;
if (preg_match('/^[A-Za-z0-9_]+\.php$/', $file)) {
    if (isset($aliases[$file])) {
        $target = $aliases[$file];
    } else {
        foreach ($folders as $folder) {
            if (is_file(__DIR__ . '/' . $folder . '/' . $file)) {
                $target = $folder . '/' . $file;
                break;
            }
        }
    }
}

if ($target === null) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Page not found</title></head>'
        . '<body style="font-family:sans-serif;text-align:center;padding:80px;">'
        . '<h1>Page not found</h1><p><a href="dashboard.php">Back to the dashboard</a></p></body></html>';
    exit;
}

// Rebuild the URL from what the browser asked for, so /Admin vs /frontend/Admin vs a local subfolder all work.
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$path = (string) parse_url($uri, PHP_URL_PATH);
$query = (string) parse_url($uri, PHP_URL_QUERY);
$base = rtrim(str_replace('\\', '/', dirname($path)), '/');

header('Location: ' . $base . '/' . $target . ($query !== '' ? '?' . $query : ''), true, 307);
exit;
