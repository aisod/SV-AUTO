<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$portal = strtolower(trim((string) ($_GET['portal'] ?? 'admin')));

if ($portal === 'manager') {
    require_once __DIR__ . '/../../Manager/includes/mgr_paths.inc.php';
    $resolveLink = static function (string $link, string $title): string {
        return mgr_notif_link($link);
    };
} else {
    $resolveLink = static function (string $link, string $title): string {
        $link = trim($link);
        if ($link === '' || $link === '#') {
            return '#';
        }
        if (preg_match('#^https?://#i', $link) || str_starts_with($link, '/')) {
            return $link;
        }
        return $link;
    };
}

$feed = erp_notifications_header_feed($pdo, $userId, $resolveLink, 10);
echo json_encode(['ok' => true, 'count' => $feed['count'], 'items' => $feed['items']]);
