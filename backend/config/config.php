<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Web path for this app (folder name under htdocs, port 8080 in XAMPP)
if (!defined('APP_BASE')) {
    define('APP_BASE', '/SV Auto Truck Repair');
}
if (!function_exists('app_url')) {
    function app_url($path = '') {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        return APP_BASE . ($path !== '' ? '/' . $path : '');
    }
}

// Google OAuth (copy google.local.example.php → google.local.php locally)
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
if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: (app_url('Admin/google-callback.php')));
}

// Database connection
$host = 'localhost';
$dbname = 'sams_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=3307;dbname=$dbname", $username, $password);
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