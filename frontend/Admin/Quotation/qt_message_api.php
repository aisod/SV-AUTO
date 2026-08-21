<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/notifications.php';
require_once __DIR__ . '/quotation_manager_review.inc.php';

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
$text = trim((string) ($_POST['message'] ?? $_POST['comment'] ?? ''));

$displayName = trim((string) ($_SESSION['username'] ?? ''));
if (function_exists('erp_dashboard_display_name')) {
    $displayName = erp_dashboard_display_name();
}

$result = qt_add_quotation_message(
    $pdo,
    $quoteId,
    (int) $_SESSION['user_id'],
    $displayName,
    $text,
    $role === 'admin' ? 'admin' : 'manager'
);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Could not send message']);
    exit;
}

$m = $result['message'] ?? $result['comment'] ?? [];
echo json_encode([
    'ok' => true,
    'message' => $m,
    'comment' => $m,
    'at' => (string) ($m['at'] ?? ''),
    'by_name' => (string) ($m['by_name'] ?? ''),
    'by_role' => (string) ($m['by_role'] ?? ''),
    'text' => (string) ($m['text'] ?? ''),
    'notified' => (int) ($result['notified'] ?? 0),
]);
