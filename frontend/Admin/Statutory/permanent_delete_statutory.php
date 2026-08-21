<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$doc_id = (int)($_GET['id'] ?? 0);

if ($doc_id <= 0) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Invalid document ID'));
    exit;
}

try {
    // Get document info
    $stmt = $pdo->prepare("SELECT * FROM statutory_docs WHERE id = ?");
    $stmt->execute([$doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Document not found'));
        exit;
    }
    
    // Delete physical file if exists
    $file_path = __DIR__ . '/../' . $document['file_url'];
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
    
    // Permanently delete from database
    $stmt = $pdo->prepare("DELETE FROM statutory_docs WHERE id = ?");
    $stmt->execute([$doc_id]);
    
    header('Location: ../recycle-bin/recycle_bin.php?success=' . urlencode('Document permanently deleted'));
    exit;
    
} catch (PDOException $e) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Failed to delete document: ' . $e->getMessage()));
    exit;
}



