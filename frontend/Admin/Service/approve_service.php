<?php
// approve_service.php — INSTANTLY APPROVE A SERVICE (FINAL VERSION)
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

// Security: Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

// Must have valid service ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: services.php?error=Invalid service ID');
    exit;
}

$service_id = (int)$_GET['id'];

// Verify service exists and is still pending
$stmt = $pdo->prepare("SELECT id, status FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: services.php?error=Service not found');
    exit;
}

if ($service['status'] === 'approved') {
    header('Location: services.php?success=Service already approved');
    exit;
}

// APPROVE THE SERVICE
try {
    $stmt = $pdo->prepare("UPDATE services SET status = 'approved' WHERE id = ?");
    $stmt->execute([$service_id]);

    // Optional: Add audit log (uncomment if you want tracking)
    /*
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, created_at) 
        VALUES (?, 'approved service', 'service', ?, NOW())
    ")->execute([$_SESSION['user_id'], $service_id]);
    */

    // Redirect back with success message
    $redirect = $_GET['redirect'] ?? 'services_update.php';
    $msg = $_GET['success'] ?? 'Service approved successfully!';

    header("Location: $redirect?success=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    header('Location: services_update.php?error=Failed to approve service');
    exit;
}
