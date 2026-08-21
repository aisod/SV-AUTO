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
    // Restore - set deleted_at to NULL
    $stmt = $pdo->prepare("UPDATE expenses SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$expense_id]);

    header('Location: ../recycle-bin/recycle_bin.php?success=' . urlencode('Expense restored successfully'));
    exit;

} catch (PDOException $e) {
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode('Error restoring expense: ' . $e->getMessage()));
    exit;
}



