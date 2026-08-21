<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Invalid ID'));
    exit;
}

try {
    // Get employee photo to delete
    $stmt = $pdo->prepare("SELECT photo_url FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete photo file if exists
    if ($employee && !empty($employee['photo_url'])) {
        $photo_path = __DIR__ . '/../' . $employee['photo_url'];
        if (file_exists($photo_path)) {
            @unlink($photo_path);
        }
    }
    
    // Delete employee
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: ../recycle-bin/recycle_bin.php?success=' . urlencode('Employee permanently deleted'));
    exit;
} catch (PDOException $e) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Failed to delete employee'));
    exit;
}



