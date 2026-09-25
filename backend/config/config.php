<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Web path: XAMPP folder locally; empty when site is at domain root (Hostinger public_html)
if (!defined('APP_PRODUCTION_HOST')) {
    define('APP_PRODUCTION_HOST', 'svautotruckrepair.com');
}
if (!defined('APP_BASE')) {
    $httpHost = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = (bool) preg_match('/^(localhost|127\.0\.0\.1)$/i', $httpHost);
    define('APP_BASE', $isLocal ? '/SV Auto Truck Repair' : '');
}
if (!function_exists('app_url')) {
    function app_url($path = '') {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        return APP_BASE . ($path !== '' ? '/' . $path : '');
    }
}
if (!function_exists('app_is_production')) {
    function app_is_production(): bool {
        $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        return (bool) preg_match('/^(www\.)?' . preg_quote(APP_PRODUCTION_HOST, '/') . '$/i', $host);
    }
}

// Google OAuth (copy google.local.example.php → google.local.php)
$googleLocal = __DIR__ . '/google.local.php';
if (is_file($googleLocal)) {
    require_once $googleLocal;
}
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
}

// Redirect URIs follow the current host (localhost XAMPP vs live domain).
$googleHost = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
if (app_is_production()) {
    $googleScheme = 'https';
    $googleHost = APP_PRODUCTION_HOST;
} else {
    $googleScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
}
$googleOrigin = $googleScheme . '://' . $googleHost;

if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: ($googleOrigin . app_url('Admin/Auth/google-callback.php')));
}
if (!defined('GOOGLE_REGISTER_REDIRECT_URI')) {
    define('GOOGLE_REGISTER_REDIRECT_URI', getenv('GOOGLE_REGISTER_REDIRECT_URI') ?: ($googleOrigin . app_url('Admin/Auth/register.php')));
}

// Database (copy db.local.example.php → db.local.php on Hostinger)
$dbHost = 'localhost';
$dbPort = null; // null = default MySQL port 3306 (Hostinger)
$dbname = 'sams_db';
$username = 'root';
$password = '';

$dbLocal = __DIR__ . '/db.local.php';
if (is_file($dbLocal)) {
    require $dbLocal;
}

try {
    $dsn = $dbPort
        ? "mysql:host={$dbHost};port={$dbPort};dbname={$dbname};charset=utf8mb4"
        : "mysql:host={$dbHost};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Runtime migrations for production safety (idempotent).
    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS hourly_rate DECIMAL(10,2) DEFAULT 560.00 AFTER contact_person");
    } catch (Exception $e) {
        error_log('Migration warning (clients.hourly_rate): ' . $e->getMessage());
    }

    try {
        $rateUpdates = $pdo->prepare("UPDATE labor_rates SET hourly_rate = ? WHERE rate_type = ?");
        $rateUpdates->execute([560.00, 'normal_hours']);
        $rateUpdates->execute([840.00, 'after_hours']);
        $rateUpdates->execute([1120.00, 'weekend']);
        $rateUpdates->execute([1120.00, 'holiday']);
    } catch (Exception $e) {
        error_log('Migration warning (labor_rates defaults): ' . $e->getMessage());
    }

    try {
        $pdo->exec("ALTER TABLE clients ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL AFTER email");
    } catch (Exception $e) {
        error_log('Migration warning (clients.google_id): ' . $e->getMessage());
    }

    try {
        $pdo->exec("ALTER TABLE clients ADD INDEX IF NOT EXISTS idx_google_id (google_id)");
    } catch (Exception $e) {
        error_log('Migration warning (clients.idx_google_id): ' . $e->getMessage());
    }

    // Allow reuse of Job Card numbers for records in recycle bin.
    // Keep lookup performance via non-unique index; active uniqueness is enforced in app logic.
    try {
        $ix = $pdo->query("
            SELECT NON_UNIQUE
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'job_cards'
              AND INDEX_NAME = 'card_number'
            LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
        if ($ix && (int)($ix['NON_UNIQUE'] ?? 1) === 0) {
            $pdo->exec("ALTER TABLE job_cards DROP INDEX card_number");
            $pdo->exec("ALTER TABLE job_cards ADD INDEX idx_card_number (card_number)");
        }
    } catch (Exception $e) {
        error_log('Migration warning (job_cards.card_number unique): ' . $e->getMessage());
    }
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
