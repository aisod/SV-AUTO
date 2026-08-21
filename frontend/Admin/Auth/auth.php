<?php
// auth.php - Include at the top of every protected page
require_once __DIR__ . '/../../../backend/config/config.php';

/** Web path prefix ending at /Admin (works for Admin root and Admin subfolder scripts). */
function admin_nav_base(): string {
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($sn, '/Admin/');
    if ($pos === false) {
        return '';
    }
    return substr($sn, 0, $pos + strlen('/Admin'));
}

function admin_nav_url(string $filename): string {
    $base = admin_nav_base();
    $tail = ltrim($filename, '/');
    return $base !== '' ? $base . '/' . $tail : $tail;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . admin_nav_url('login.php'));
    exit();
}

// Managers use the Manager portal only — not Admin URLs.
if (strtolower((string) ($_SESSION['role_name'] ?? '')) === 'manager') {
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (stripos($sn, '/Admin/') !== false) {
        $parts = array_values(array_filter(explode('/', $sn)));
        $adminIdx = array_search('Admin', $parts, true);
        if ($adminIdx !== false) {
            $prefix = '/' . implode('/', array_slice($parts, 0, $adminIdx));
            header('Location: ' . $prefix . '/Manager/manager_dashboard.php');
            exit();
        }
    }
}

// Regenerate session ID every 5 minutes
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Admin only pages
function require_admin() {
    if (strtolower($_SESSION['role_name'] ?? '') !== 'admin') {
        header('Location: ' . admin_nav_url('dashboard.php'));
        exit();
    }
}

// Manager only pages
function require_manager() {
    if (strtolower($_SESSION['role_name'] ?? '') !== 'manager') {
        header('Location: ' . admin_nav_url('dashboard.php'));
        exit();
    }
}

// Pages accessible by multiple roles
function require_role(array $allowed_roles) {
    $current = strtolower($_SESSION['role_name'] ?? '');
    $allowed = array_map('strtolower', $allowed_roles);
    if (!in_array($current, $allowed)) {
        header('Location: ' . admin_nav_url('dashboard.php'));
        exit();
    }
}

// Check if current user is admin (returns true/false — use in views)
function is_admin(): bool {
    return strtolower($_SESSION['role_name'] ?? '') === 'admin';
}
?>