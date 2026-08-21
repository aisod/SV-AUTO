<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/user_drafts.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $draftKey = trim((string) ($_POST['draft_key'] ?? ''));
    $entityType = trim((string) ($_POST['entity_type'] ?? ''));
    $entityId = isset($_POST['entity_id']) && $_POST['entity_id'] !== '' ? (int) $_POST['entity_id'] : null;
    $pageUrl = trim((string) ($_POST['page_url'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $payloadRaw = (string) ($_POST['payload'] ?? '{}');
    $payload = json_decode($payloadRaw, true);
    if ($draftKey === '' || $entityType === '' || $pageUrl === '' || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid draft data']);
        exit;
    }
    if ($title === '') {
        $title = user_draft_title_from_payload($payload);
    }
    $ok = user_draft_save($userId, $draftKey, $entityType, $entityId, $pageUrl, $title, $payload);
    echo json_encode(['ok' => $ok, 'updated_at' => date('c')]);
    exit;
}

if ($action === 'track_page' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pageUrl = trim((string) ($_POST['page_url'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    if ($pageUrl === '' || $title === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid page data']);
        exit;
    }
    $ok = user_resume_save_page($userId, $pageUrl, $title);
    echo json_encode(['ok' => $ok]);
    exit;
}

if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $draftKey = trim((string) ($_POST['draft_key'] ?? ''));
    if ($draftKey === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing draft_key']);
        exit;
    }
    user_draft_delete($userId, $draftKey);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
