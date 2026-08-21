<?php
// admin/download.php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

// === Security: Must be logged in ===
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access denied. Please log in.');
}

$file = $_GET['file'] ?? '';

// === 1. Basic validation ===
if (empty($file)) {
    http_response_code(400);
    die('No file specified.');
}

// === 2. Prevent directory traversal (CRITICAL) ===
if (strpos($file, '..') !== false || strpos($file, '\\') !== false) {
    http_response_code(400);
    die('Invalid file path.');
}

// === 3. Only allow files from uploads/statutory/ ===
$allowedBase = 'uploads/statutory/';
if (strpos($file, $allowedBase) !== 0) {
    http_response_code(403);
    die('Access restricted to statutory documents only.');
}

// === 4. Build full server path ===
$fullPath = __DIR__ . '/../' . $file;  // This works perfectly when in admin/

if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    die('File not found.');
}

// === 5. Optional: Verify file exists in database (Recommended) ===
$docName = 'document'; // fallback
try {
    $stmt = $pdo->prepare("SELECT name FROM statutory_docs WHERE file_url = ? LIMIT 1");
    $stmt->execute([$file]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $docName = $row['name'];
    }
} catch (Exception $e) {
    error_log("Download DB check failed: " . $e->getMessage());
    // Continue anyway — don't block download if DB is slow
}

// === 6. Clean filename for download ===
$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $docName);
if (!str_ends_with(strtolower($safeName), '.' . $extension)) {
    $safeName .= '.' . $extension;
}

// === 7. Send file securely ===
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
header('Pragma: public');

if (ob_get_level()) {
    ob_end_clean(); // Clear any output buffer
}

readfile($fullPath);
exit;