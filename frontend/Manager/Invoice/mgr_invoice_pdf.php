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
    echo 'Invalid invoice';
    exit;
}

require_once __DIR__ . '/../../Admin/Invoice/invoice_pdf.inc.php';
require_once __DIR__ . '/../includes/mgr_doc_preview_fix.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_inv_pdf'] ?? '') === '1') {
    $html = (string) ($_POST['doc_html'] ?? '');
    $filename = preg_replace('/[^\w\-]+/', '_', (string) ($_POST['filename'] ?? ('invoice-' . $id)));
    mgr_stream_doc_pdf_from_html($html, $filename !== '' ? $filename : ('invoice-' . $id));
    exit;
}

try {
    $html = inv_build_invoice_pdf_html($pdo, $id);
} catch (Throwable $e) {
    http_response_code(404);
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

if (!class_exists(\Dompdf\Dompdf::class)) {
    $autoload = __DIR__ . '/../../../vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
    }
}
if (!class_exists(\Dompdf\Dompdf::class)) {
    http_response_code(500);
    echo 'PDF library not available';
    exit;
}

$filename = preg_replace('/[^\w\-]+/', '_', (string) ($_GET['filename'] ?? ('invoice-' . $id)));
if ($filename === '') {
    $filename = 'invoice-' . $id;
}

$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('dpi', 120);
$options->set('defaultMediaType', 'print');
$chroot = realpath(__DIR__ . '/../..');
if ($chroot !== false) {
    $options->setChroot($chroot);
}

$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($filename . '.pdf', ['Attachment' => true]);
