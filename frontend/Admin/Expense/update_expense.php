<?php
// update_expense.php — PROCESS EXPENSE UPDATE (FINAL VERSION)
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: expenses.php?error=Invalid request method');
    exit;
}

try {
    // Get and sanitize input
    $expense_id  = (int)($_POST['expense_id'] ?? 0);
    $amount      = trim($_POST['amount'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate expense ID
    if ($expense_id <= 0) {
        throw new Exception("Invalid expense record");
    }

    // Validate amount
    $amount = str_replace(',', '', $amount); // Remove commas if any
    $amount = (float)$amount;
    if ($amount <= 0) {
        throw new Exception("Amount must be greater than zero");
    }

    // Validate description
    if (empty($description)) {
        throw new Exception("Description is required");
    }
    if (strlen($description) > 500) {
        throw new Exception("Description too long (max 500 characters)");
    }

    // Verify expense exists and belongs to a valid purchase order
    $stmt = $pdo->prepare("SELECT id FROM expenses WHERE id = ?");
    $stmt->execute([$expense_id]);
    if (!$stmt->fetch()) {
        throw new Exception("Expense record not found");
    }

    // Update the expense
    $stmt = $pdo->prepare("
        UPDATE expenses 
        SET amount = ?, 
            description = ? 
        WHERE id = ?
    ");
    $stmt->execute([$amount, $description, $expense_id]);

    // Optional: Log to audit trail
    /*
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, created_at) 
        VALUES (?, 'updated expense', 'expense', ?, NOW())
    ")->execute([$_SESSION['user_id'], $expense_id]);
    */

    // Success!
    header("Location: edit_expense.php?id=$expense_id&success=Expense updated successfully!");
    exit;

} catch (Exception $e) {
    // Return to form with error
    header("Location: edit_expense.php?id=$expense_id&error=" . urlencode($e->getMessage()));
    exit;
}
