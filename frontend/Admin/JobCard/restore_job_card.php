<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/jc_form_helpers.inc.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ../recycle-bin/recycle_bin.php?error=Invalid request');
    exit;
}

$job_card_id = (int)$_GET['id'];

try {
    $numStmt = $pdo->prepare('SELECT card_number FROM job_cards WHERE id = ? LIMIT 1');
    $numStmt->execute([$job_card_id]);
    $card_number = trim((string) $numStmt->fetchColumn());
    if ($card_number !== '') {
        $conflict = jc_find_job_card_number_conflict($pdo, $card_number, $job_card_id);
        if ($conflict !== null) {
            $msg = "Cannot restore: job card number {$card_number} is already used by {$conflict['client_name']}.";
            if ($conflict['has_quotation']) {
                $msg .= ' That record already has an open quotation.';
            }
            header('Location: ../recycle-bin/recycle_bin.php?error=' . rawurlencode($msg));
            exit;
        }
    }

    // Restore job card
    $stmt = $pdo->prepare("UPDATE job_cards SET deleted_at = NULL WHERE id = ?");
    $stmt->execute([$job_card_id]);

    // Audit log
    $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'restored_job_card', 'job_card', ?)");
    $log->execute([$_SESSION['user_id'], $job_card_id]);

    header('Location: ../recycle-bin/recycle_bin.php?success=Job card restored successfully');
    exit;

} catch (Exception $e) {
    error_log("Job Card Restore Failed: " . $e->getMessage());
    header('Location: ../recycle-bin/recycle_bin.php?error=' . urlencode($e->getMessage()));
    exit;
}



