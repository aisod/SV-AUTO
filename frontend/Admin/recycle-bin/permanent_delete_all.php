<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

try {
    // Delete all expenses that are marked as deleted
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE deleted_at IS NOT NULL");
    $stmt->execute();

    $count = $stmt->rowCount();
    header('Location: recycle_bin.php?success=' . urlencode("Recycle bin emptied! $count expense(s) permanently deleted"));
    exit;

} catch (PDOException $e) {
    header('Location: recycle_bin.php?error=' . urlencode('Error emptying recycle bin: ' . $e->getMessage()));
    exit;
}

