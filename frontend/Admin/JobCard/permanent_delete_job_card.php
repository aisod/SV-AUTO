<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ../recycle-bin/recycle_bin.php?error=Invalid request');
    exit;
}

$job_card_id = (int)$_GET['id'];

try {
    // Permanently delete job card
    $stmt = $pdo->prepare("DELETE FROM job_cards WHERE id = ?");
    $stmt->execute([$job_card_id]);

    // Audit log
    $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'permanent_delete_job_card', 'job_card', ?)");
    $log->execute([$_SESSION['user_id'], $job_card_id]);

    header('Location: ../recycle-bin/recycle_bin.php?success=Job card permanently deleted');
    exit;

} catch (Exception $e) {
    error_log("Job Card Permanent Delete Failed: " . $e->getMessage());
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode($e->getMessage()));
    exit;
}



