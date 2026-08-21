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
    $stmt = $pdo->prepare("UPDATE employees SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: ../recycle-bin/recycle_bin.php?success=' . urlencode('Employee restored successfully'));
    exit;
} catch (PDOException $e) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Failed to restore employee'));
    exit;
}



