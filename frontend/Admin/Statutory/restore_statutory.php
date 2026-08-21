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
    // Restore document by setting deleted_at to NULL
    $stmt = $pdo->prepare("UPDATE statutory_docs SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$doc_id]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: ../recycle-bin/recycle_bin.php?success=' . urlencode('Document restored successfully'));
    } else {
        header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Document not found'));
    }
    exit;
    
} catch (PDOException $e) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Failed to restore document: ' . $e->getMessage()));
    exit;
}



