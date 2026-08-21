<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$expense_id = (int)($_GET['id'] ?? 0);

if ($expense_id <= 0) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Invalid expense ID'));
    exit;
}

try {
    // Permanent delete - actually remove from database
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND deleted_at IS NOT NULL");
    $stmt->execute([$expense_id]);

    header('Location: ../recycle-bin/recycle_bin.php?success=' . urlencode('Expense permanently deleted'));
    exit;

} catch (PDOException $e) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Error deleting expense: ' . $e->getMessage()));
    exit;
}



