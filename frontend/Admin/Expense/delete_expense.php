<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$expense_id = (int)($_GET['id'] ?? 0);

if ($expense_id <= 0) {
    header('Location: expenses.php?error=' . urlencode('Invalid expense ID'));
    exit;
}

try {
    // Soft delete - mark as deleted instead of removing
    $stmt = $pdo->prepare("UPDATE expenses SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$expense_id]);

    // Optional: Log to audit_logs
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'deleted', 'expense', $expense_id]);
    } catch (Exception $e) {
        // Audit logging is optional
    }

    header('Location: expenses.php?success=' . urlencode('Expense moved to recycle bin'));
    exit;

} catch (PDOException $e) {
    header('Location: expenses.php?error=' . urlencode('Error deleting expense: ' . $e->getMessage()));
    exit;
}

