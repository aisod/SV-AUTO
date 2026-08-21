<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$id = isset($_REQUEST['id']) && is_numeric($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid quotation';
    exit;
}

require_once __DIR__ . '/../../Admin/Quotation/quotation_pdf.inc.php';
require_once __DIR__ . '/../includes/mgr_doc_preview_fix.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_qt_pdf'] ?? '') === '1') {
    $html = (string) ($_POST['doc_html'] ?? '');
    $filename = preg_replace('/[^\w\-]+/', '_', (string) ($_POST['filename'] ?? ('quotation-' . $id)));
    mgr_stream_doc_pdf_from_html($html, $filename !== '' ? $filename : ('quotation-' . $id));
    exit;
}

require_once __DIR__ . '/../../Admin/Quotation/quotation_paper_signoff.inc.php';
require_once __DIR__ . '/../includes/mgr_quotation_paper_signoff_date.inc.php';

try {
    $filename = preg_replace('/[^\w\-]+/', '_', (string) ($_GET['filename'] ?? ''));
    aq_stream_quotation_pdf($pdo, $id, $filename !== '' ? $filename : null);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF generation failed: ' . $e->getMessage();
}
