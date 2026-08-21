<?php
/**
 * Manager — invoice HTML preview (same document as PDF source).
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid invoice';
    exit;
}

require_once __DIR__ . '/../../Admin/Invoice/invoice_pdf.inc.php';
try {
    $html = inv_build_invoice_pdf_html($pdo, $id);
} catch (Throwable $e) {
    http_response_code(404);
    echo 'Invoice not found';
    exit;
}

require_once __DIR__ . '/../includes/mgr_doc_preview_fix.inc.php';
require_once __DIR__ . '/../../Admin/includes/trade_document_helpers.inc.php';
$shellCss = mgr_doc_preview_shell_css() . inv_doc_print_layout_styles();

if (stripos($html, '</head>') !== false) {
    $html = str_ireplace('</head>', '<style>' . $shellCss . '</style></head>', $html);
} else {
    $html = '<style>' . $shellCss . '</style>' . $html;
}

header('Content-Type: text/html; charset=UTF-8');
echo $html;
