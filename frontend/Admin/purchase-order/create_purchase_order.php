<?php
// create_purchase_order.php — PROCESS NEW PURCHASE ORDER
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Auth/login.php');
    exit;
}

try {
    $job_card_id   = (int)$_POST['job_card_id'];
    $supplier_name = trim($_POST['supplier_name']);
    $amount        = (float)$_POST['amount'];
    $details       = trim($_POST['details'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');

    if ($job_card_id <= 0) {
        throw new Exception("Please select a valid job card");
    }
    if (empty($supplier_name)) {
        throw new Exception("Supplier name is required");
    }
    if ($amount <= 0) {
        throw new Exception("Amount must be greater than zero");
    }

    // Check if job card already has a PO
    $check = $pdo->prepare("SELECT id FROM purchase_orders WHERE job_card_id = ?");
    $check->execute([$job_card_id]);
    if ($check->fetch()) {
        throw new Exception("This job card already has a purchase order");
    }

    // Create the PO
    $stmt = $pdo->prepare("
        INSERT INTO purchase_orders (job_card_id, supplier_name, amount, details, notes, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$job_card_id, $supplier_name, $amount, $details, $notes]);

    header("Location: add_purchase_order.php?success=Purchase Order created successfully!");
    exit;

} catch (Exception $e) {
    header("Location: add_purchase_order.php?error=" . urlencode($e->getMessage()));
    exit;
}
