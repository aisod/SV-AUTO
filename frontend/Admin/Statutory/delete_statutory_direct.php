<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$doc_id = (int)($_GET['id'] ?? 0);

if ($doc_id <= 0) {
    header('Location: statutory.php?error=' . urlencode('Invalid document ID'));
    exit;
}

try {
    // Get document info
    $stmt = $pdo->prepare("SELECT * FROM statutory_docs WHERE id = ?");
    $stmt->execute([$doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        header('Location: statutory.php?error=' . urlencode('Document not found'));
        exit;
    }
    
    // Soft delete - set deleted_at timestamp
    $stmt = $pdo->prepare("UPDATE statutory_docs SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$doc_id]);
    
    header('Location: statutory.php?success=' . urlencode('Document moved to recycle bin'));
    exit;
    
} catch (PDOException $e) {
    header('Location: statutory.php?error=' . urlencode('Failed to delete document: ' . $e->getMessage()));
    exit;
}

