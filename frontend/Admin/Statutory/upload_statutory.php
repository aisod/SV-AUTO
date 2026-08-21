<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: statutory_docs.php?error=Invalid request');
    exit;
}

// === Validation ===
$name = trim($_POST['name'] ?? '');
$type = $_POST['type'] ?? '';
$allowed = ['founding','good_standing','recommendation','afs','tender'];

if (empty($name) || empty($type) || !in_array($type, $allowed)) {
    header('Location: statutory_docs.php?error=Please fill all fields correctly');
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    header('Location: statutory_docs.php?error=No file selected');
    exit;
}

// === File Handling ===
$uploadDir = __DIR__ . '/../uploads/statutory/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$file = $_FILES['file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExts = ['pdf','doc','docx','jpg','jpeg','png'];

if (!in_array($ext, $allowedExts)) {
    header('Location: statutory_docs.php?error=Invalid file type');
    exit;
}
if ($file['size'] > 15 * 1024 * 1024) {
    header('Location: statutory_docs.php?error=File too large (max 15MB)');
    exit;
}

$newName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME))
         . '_' . date('Ymd_His') . '.' . $ext;
$fullPath = $uploadDir . $newName;
$relativeUrl = 'uploads/statutory/' . $newName;

if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
    header('Location: statutory_docs.php?error=Failed to save file');
    exit;
}

// === Database Insert (safe & compatible) ===
try {
    // First, check which columns actually exist
    $cols = $pdo->query("SHOW COLUMNS FROM statutory_docs LIKE 'uploaded_by'")->rowCount() > 0;
    $cols_at = $pdo->query("SHOW COLUMNS FROM statutory_docs LIKE 'uploaded_at'")->rowCount() > 0;

    if ($cols && $cols_at) {
        $sql = "INSERT INTO statutory_docs (name, file_url, type, uploaded_by, uploaded_at) 
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $relativeUrl, $type, $_SESSION['user_id']]);
    } else {
        // Fallback for older tables
        $sql = "INSERT INTO statutory_docs (name, file_url, type) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $relativeUrl, $type]);
    }

    // Audit log (if table exists)
    try {
        $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) 
                              VALUES (?, ?, 'statutory_doc', ?)");
        $log->execute([$_SESSION['user_id'], "Uploaded document: $name", $pdo->lastInsertId()]);
    } catch (Exception $e) {
        // ignore if audit_logs not ready yet
    }

    header('Location: statutory.php?success=Document uploaded successfully!');
    exit;

} catch (Exception $e) {
    // Delete uploaded file if DB failed
    if (file_exists($fullPath)) @unlink($fullPath);
    
    // Optional: show real error only in development
    $msg = $_ENV['APP_ENV'] === 'development' 
        ? $e->getMessage() 
        : "Database error. Please try again.";
    
    header("Location: statutory.php?error=" . urlencode($msg));
    exit;
}
