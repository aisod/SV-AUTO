<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../includes/mgr_review.inc.php';

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

$quoteId = isset($_POST['quote_id']) ? (int) $_POST['quote_id'] : (int) ($_GET['quote_id'] ?? 0);
if ($quoteId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid quotation']);
    exit;
}

$result = qt_mark_manager_review_viewed(
    $pdo,
    $quoteId,
    (int) $_SESSION['user_id'],
    (string) ($_SESSION['username'] ?? 'Manager')
);

if (!$result['ok']) {
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Could not mark viewed']);
    exit;
}

echo json_encode([
    'ok' => true,
    'viewed_at' => $result['viewed_at'] ?? '',
    'already' => !empty($result['already']),
]);
