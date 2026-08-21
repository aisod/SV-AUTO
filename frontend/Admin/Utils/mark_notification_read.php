<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notification_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($notification_id === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
    exit;
}

try {
    // Verify the notification belongs to this user and mark as read
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Notification not found or already read']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
