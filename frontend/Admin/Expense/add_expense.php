<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_order_id = (int)($_POST['purchase_order_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // Validation
    if ($purchase_order_id <= 0) {
        header('Location: expenses.php?error=' . urlencode('Please select a purchase order'));
        exit;
    }

    if ($amount <= 0) {
        header('Location: expenses.php?error=' . urlencode('Expense amount must be greater than zero'));
        exit;
    }

    if (empty($description)) {
        header('Location: expenses.php?error=' . urlencode('Please provide a description'));
        exit;
    }

    try {
        // Insert expense
        $stmt = $pdo->prepare("INSERT INTO expenses (purchase_order_id, amount, description) VALUES (?, ?, ?)");
        $stmt->execute([$purchase_order_id, $amount, $description]);

        // Optional: Log to audit_logs
        try {
            $expense_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], 'created', 'expense', $expense_id]);
        } catch (Exception $e) {
            // Audit logging is optional, don't fail if it doesn't work
        }

        header('Location: expenses.php?success=' . urlencode('Expense recorded successfully'));
        exit;

    } catch (PDOException $e) {
        header('Location: expenses.php?error=' . urlencode('Database error: ' . $e->getMessage()));
        exit;
    }
} else {
    header('Location: expenses.php');
    exit;
}

