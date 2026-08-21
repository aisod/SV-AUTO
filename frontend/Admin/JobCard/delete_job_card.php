<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_admin();

$listUrl = app_url('Admin/JobCard/job_card.php');

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ' . $listUrl . '?error=' . urlencode('Invalid request'));
    exit;
}

$job_card_id = (int) $_GET['id'];
if ($job_card_id <= 0) {
    header('Location: ' . $listUrl . '?error=' . urlencode('Invalid job card'));
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE job_cards SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$job_card_id]);
    if ($stmt->rowCount() === 0) {
        header('Location: ' . $listUrl . '?error=' . urlencode('Job card not found or already deleted'));
        exit;
    }

    try {
        $log = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)');
        $log->execute([$_SESSION['user_id'], 'deleted_job_card', 'job_card', $job_card_id]);
    } catch (Exception $logEx) {
        error_log('Job card delete audit log failed: ' . $logEx->getMessage());
    }

    header('Location: ' . $listUrl . '?success=' . urlencode('Job card moved to recycle bin'));
    exit;
} catch (Exception $e) {
    error_log('Job Card Deletion Failed: ' . $e->getMessage());
    header('Location: ' . $listUrl . '?error=' . urlencode('Failed to delete job card'));
    exit;
}
