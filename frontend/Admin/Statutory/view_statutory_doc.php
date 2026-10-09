<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$file_url = $_GET['file'] ?? '';
if (empty($file_url)) {
    die('No file specified');
}

// Only serve files that live inside Admin/uploads/ (blocks ../ tricks such as ../../backend/config/db.local.php),
// and only to staff — customers also have sessions.
$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
if ($role === '' || $role === 'client') {
    http_response_code(403);
    die('Forbidden');
}

$uploads_root = realpath(__DIR__ . '/../uploads');
$file_path = realpath(__DIR__ . '/../' . $file_url);

if ($uploads_root === false || $file_path === false
    || strpos($file_path, $uploads_root . DIRECTORY_SEPARATOR) !== 0
    || !is_file($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Get file extension
$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

// Set proper content type for high quality display
$content_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

$content_type = $content_types[$ext] ?? 'application/octet-stream';

// Set headers for high quality display
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($file_path));
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Cache-Control: public, max-age=31536000');
header('Accept-Ranges: bytes');

// Output file
readfile($file_path);
exit;

