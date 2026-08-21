<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

// Only allow GET request with valid ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: inventory.php?error=' . urlencode('Invalid part ID.'));
    exit;
}

$part_id = (int)$_GET['id'];

// Fetch the part to confirm it exists and get its name for the success message
$stmt = $pdo->prepare("SELECT part_name, stock FROM inventory WHERE id = ?");
$stmt->execute([$part_id]);
$part = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$part) {
    header('Location: inventory.php?error=' . urlencode('Part not found.'));
    exit;
}

// Optional: Prevent deletion if stock > 0 (you can remove this if you want to allow deleting stocked items)
if ($part['stock'] > 0) {
    header('Location: inventory.php?error=' . urlencode('Cannot delete: "' . htmlspecialchars($part['part_name']) . '" has ' . $part['stock'] . ' units in stock. Reduce stock to 0 first.'));
    exit;
}

// Perform soft deletion (move to recycle bin)
try {
    $delete = $pdo->prepare("UPDATE inventory SET deleted_at = NOW() WHERE id = ?");
    $delete->execute([$part_id]);

    // Optional: Log the deletion
    $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)");
    $log->execute([$_SESSION['user_id'], 'deleted_inventory_item', 'inventory', $part_id]);

    $success_message = "Part \"" . htmlspecialchars($part['part_name']) . "\" has been moved to recycle bin.";

    header('Location: inventory.php?success=' . urlencode($success_message));
    exit;

} catch (Exception $e) {
    error_log("Delete inventory error: " . $e->getMessage());
    header('Location: inventory.php?error=' . urlencode('Failed to delete part. Please try again.'));
    exit;
}
