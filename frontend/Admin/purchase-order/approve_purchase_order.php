<?php
// approve_purchase_order.php — INSTANTLY APPROVE PURCHASE ORDER
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: purchase_orders.php?error=Invalid request');
    exit;
}

$po_id = (int)$_GET['id'];

// Verify PO exists and is still pending
$stmt = $pdo->prepare("SELECT id, status FROM purchase_orders WHERE id = ? LIMIT 1");
$stmt->execute([$po_id]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    header('Location: purchase_orders.php?error=Purchase Order not found');
    exit;
}

if ($po['status'] !== 'pending') {
    header('Location: purchase_orders.php?error=This Purchase Order is already ' . $po['status']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Approve the Purchase Order
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'approved' WHERE id = ?");
    $stmt->execute([$po_id]);

    // Optional: Log audit trail
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, created_at) 
        VALUES (?, 'approved purchase order', 'purchase_order', ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $po_id]);

    $pdo->commit();

    // Success! Redirect with beautiful message
    header('Location: view_purchase_order.php?id=' . $po_id . '&success=Purchase Order Approved Successfully!');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: purchase_orders.php?error=Approval failed. Please try again.');
    exit;
}
?>
