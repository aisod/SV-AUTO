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
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: ../recycle-bin/recycle_bin.php?success=' . urlencode('Inventory item permanently deleted'));
    exit;
} catch (PDOException $e) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Failed to delete item'));
    exit;
}



