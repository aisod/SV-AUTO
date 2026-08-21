<?php
// create_service.php — PROCESS NEW SERVICE
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../Auth/login.php');
    exit;
}

try {
    $data = explode('_', $_POST['client_id']);
    $client_id = (int)($data[0] ?? 0);
    $vehicle_id = (int)($data[1] ?? 0);
    $service_date = $_POST['service_date'];
    $notes = trim($_POST['notes']);

    if (!$vehicle_id || !$service_date) {
        throw new Exception("Please select a vehicle and date.");
    }

    $stmt = $pdo->prepare("INSERT INTO services (vehicle_id, service_date, status, notes) VALUES (?, ?, 'pending', ?)");
    $stmt->execute([$vehicle_id, $service_date, $notes]);

    header("Location: add_service.php?success=Service scheduled successfully!");
    exit;

} catch (Exception $e) {
    header("Location: add_service.php?error=" . urlencode($e->getMessage()));
    exit;
}
