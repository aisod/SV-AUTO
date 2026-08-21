<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/notifications.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_manager_review.inc.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
if (!in_array($role, ['admin', 'manager'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid request']);
    exit;
}

$quoteId = isset($_POST['quote_id']) ? (int) $_POST['quote_id'] : 0;
$comment = trim((string) ($_POST['comment'] ?? ''));

$displayName = trim((string) ($_SESSION['username'] ?? 'Manager'));
if (function_exists('erp_dashboard_display_name')) {
    $displayName = erp_dashboard_display_name();
}

$result = qt_add_manager_comment(
    $pdo,
    $quoteId,
    (int) $_SESSION['user_id'],
    $displayName,
    $comment
);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Could not save comment']);
    exit;
}

$c = $result['comment'] ?? [];
echo json_encode([
    'ok' => true,
    'comment' => $c,
    'at' => (string) ($c['at'] ?? ''),
    'by_name' => (string) ($c['by_name'] ?? ''),
    'text' => (string) ($c['text'] ?? ''),
    'notified' => (int) ($result['notified'] ?? 0),
]);
