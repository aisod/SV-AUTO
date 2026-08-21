<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: statutory.php?error=' . urlencode('Invalid document ID'));
    exit;
}

$doc_id = (int)$_GET['id'];

// If no confirmation, redirect to confirmation page
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    header('Location: confirm_delete_statutory.php?id=' . $doc_id);
    exit;
}

try {
    // First, get the document info to delete the file
    $stmt = $pdo->prepare("SELECT * FROM statutory_docs WHERE id = ?");
    $stmt->execute([$doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        header('Location: statutory.php?error=' . urlencode('Document not found'));
        exit;
    }
    
    // Delete the physical file if it exists
    $file_path = __DIR__ . '/../' . $document['file_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM statutory_docs WHERE id = ?");
    $stmt->execute([$doc_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: statutory.php?success=' . urlencode('Document "' . $document['name'] . '" deleted successfully'));
    } else {
        header('Location: statutory.php?error=' . urlencode('Failed to delete document'));
    }
    
} catch (PDOException $e) {
    error_log("Delete statutory error: " . $e->getMessage());
    header('Location: statutory.php?error=' . urlencode('Database error occurred'));
}
exit;
?>
