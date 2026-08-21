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

$file_path = __DIR__ . '/../' . $file_url;

if (!file_exists($file_path)) {
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

