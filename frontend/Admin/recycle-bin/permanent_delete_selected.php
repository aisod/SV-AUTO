<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$ids = $_GET['ids'] ?? '';
$id_array = array_filter(array_map('intval', explode(',', $ids)));

if (empty($id_array)) {
    header('Location: recycle_bin.php?error=' . urlencode('No expenses selected'));
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($id_array), '?'));
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE id IN ($placeholders) AND deleted_at IS NOT NULL");
    $stmt->execute($id_array);

    $count = $stmt->rowCount();
    header('Location: recycle_bin.php?success=' . urlencode("$count expense(s) permanently deleted"));
    exit;

} catch (PDOException $e) {
    header('Location: recycle_bin.php?error=' . urlencode('Error deleting expenses: ' . $e->getMessage()));
    exit;
}

