<?php
/**
 * Admin â€” view / print tax invoice (quotation-style document).
 */
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_role(['admin', 'manager']);

$invoiceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Same pattern as add_quotation.php: POST uses location.pathname (no ?id= in URL) + invoice_id in body.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'send_to_manager_review') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!is_admin()) {
        echo json_encode(['ok' => false, 'error' => 'Only admin can send invoices for manager review.']);
        exit;
    }
    $postInvId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
    if ($postInvId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid invoice.']);
        exit;
    }
    $invoiceId = $postInvId;
    require_once __DIR__ . '/../../Manager/includes/mgr_review.inc.php';
    try {
        $result = inv_send_to_manager_review($pdo, $invoiceId, (string) ($_SESSION['username'] ?? 'Admin'));
    } catch (Throwable $e) {
        error_log('view.php send_to_manager_review: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Server error while sending to manager.']);
        exit;
    }
    if (empty($result['ok'])) {
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Could not send to manager.']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'id' => (int) ($result['id'] ?? $invoiceId),
        'sent_at' => (string) ($result['sent_at'] ?? ''),
        'sent_by' => (string) ($_SESSION['username'] ?? 'Admin'),
        'notified' => (int) ($result['notified'] ?? 0),
        'manager_link' => app_url('Manager/Invoice/view_invoice.php?id=' . $invoiceId),
    ]);
    exit;
}

if ($invoiceId <= 0) {
    header('Location: invoices.php?error=' . urlencode('Invalid invoice ID'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['_inv_pdf'] ?? '') === '1') {
    require_once __DIR__ . '/../../../vendor/autoload.php';
    $invCheck = get_invoice($invoiceId);
    if (!$invCheck) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Invoice not found';
        exit;
    }
    $html = (string) ($_POST['doc_html'] ?? '');
    if ($html === '' || (stripos($html, 'aq-print-inner') === false && stripos($html, 'invoice-print-inner') === false)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Invalid document content';
        exit;
    }
    $filename = preg_replace('/[^\w\-]+/', '_', (string) ($_POST['filename'] ?? ($invCheck['invoice_number'] ?? 'invoice')));
    if ($filename === '') {
        $filename = 'invoice';
    }
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['payment_status'] ?? 'unpaid';
    if (mark_invoice_paid($invoiceId, $newStatus)) {
        header('Location: view_invoice.php?id=' . $invoiceId . '&success=' . urlencode('Payment status updated'));
        exit;
    }
    header('Location: view_invoice.php?id=' . $invoiceId . '&error=' . urlencode('Failed to update payment status'));
    exit;
}

$invoice = get_invoice($invoiceId);
if (!$invoice) {
    header('Location: invoices.php?error=' . urlencode('Invoice not found'));
    exit;
}

require_once __DIR__ . '/../../Manager/includes/mgr_review.inc.php';
mgr_ensure_invoice_review_columns($pdo);
$inv_mgr_sent_at = '';
$inv_mgr_sent_by = '';
$inv_mgr_viewed_at = '';
try {
    $mStmt = $pdo->prepare('SELECT manager_review_sent_at, manager_review_sent_by, manager_review_viewed_at FROM invoices WHERE id = ? LIMIT 1');
    $mStmt->execute([$invoiceId]);
    $mRow = $mStmt->fetch(PDO::FETCH_ASSOC);
    if ($mRow) {
        $inv_mgr_sent_at = trim((string) ($mRow['manager_review_sent_at'] ?? ''));
        $inv_mgr_sent_by = trim((string) ($mRow['manager_review_sent_by'] ?? ''));
        $inv_mgr_viewed_at = trim((string) ($mRow['manager_review_viewed_at'] ?? ''));
    }
} catch (Throwable $e) {
    // non-fatal
}

require_once __DIR__ . '/../Quotation/quotation_paper_signoff.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_purchase_order'])) {
    $po = trim((string) ($_POST['purchase_order'] ?? ''));
    $qid = (int) ($invoice['quotation_id'] ?? 0);
    if ($qid <= 0) {
        header('Location: view_invoice.php?id=' . $invoiceId . '&error=' . urlencode('This invoice has no linked quotation.'));
        exit;
    }
    $st = $pdo->prepare('SELECT details FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $st->execute([$qid]);
    $detailsRaw = $st->fetchColumn();
    if ($detailsRaw === false) {
        header('Location: view_invoice.php?id=' . $invoiceId . '&error=' . urlencode('Quotation not found.'));
        exit;
    }
    $newDetails = qt_persist_details_form_fields((string) $detailsRaw, ['purchase_order' => $po]);
    if ($newDetails === null) {
        header('Location: view_invoice.php?id=' . $invoiceId . '&error=' . urlencode('Could not update purchase order on quotation.'));
        exit;
    }
    $up = $pdo->prepare('UPDATE quotations SET details = ? WHERE id = ? AND deleted_at IS NULL');
    if ($up->execute([$newDetails, $qid])) {
        header('Location: view_invoice.php?id=' . $invoiceId . '&success=' . urlencode('Purchase order saved. It will appear on the tax invoice.'));
        exit;
    }
    header('Location: view_invoice.php?id=' . $invoiceId . '&error=' . urlencode('Failed to save purchase order.'));
    exit;
}

$currency = getCurrency();
$sym = htmlspecialchars($currency['symbol'] ?? 'N$', ENT_QUOTES, 'UTF-8');

require_once __DIR__ . '/../includes/trade_document_helpers.inc.php';
$docAssets = trade_doc_assets(__DIR__ . '/..');
$header_base64 = $docAssets['header'];
$footer_base64 = $docAssets['footer'];

$quoteData = [];
$quotationId = (int) ($invoice['quotation_id'] ?? 0);
if ($quotationId > 0) {
    $qStmt = $pdo->prepare('SELECT details FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $qStmt->execute([$quotationId]);
    $qDetails = $qStmt->fetchColumn();
    if ($qDetails !== false && $qDetails !== null && $qDetails !== '') {
        $parsed = qt_parse_quotation_details((string) $qDetails);
        if (is_array($parsed)) {
            $quoteData = $parsed;
        }
    }
}
if ($quoteData === [] && !empty($invoice['quote_details'])) {
    $parsed = qt_parse_quotation_details((string) $invoice['quote_details']);
    if (is_array($parsed)) {
        $quoteData = $parsed;
    }
}

require_once __DIR__ . '/../Quotation/quotation_manager_review.inc.php';
$inv_manager_comments = [];
$inv_open_chat = isset($_GET['open_chat']) && (string) $_GET['open_chat'] === '1';
$inv_qt_chat_enabled = $quotationId > 0;
if ($inv_qt_chat_enabled && $quoteData !== []) {
    $inv_manager_comments = qt_quotation_messages_list($quoteData);
    if ($inv_open_chat && !empty($_SESSION['user_id'])) {
        require_once __DIR__ . '/../../../backend/config/notifications.php';
        erp_mark_notifications_read_for_quotation($pdo, (int) $_SESSION['user_id'], $quotationId);
    }
}
$formData = isset($quoteData['form']) && is_array($quoteData['form']) ? $quoteData['form'] : [];
$jobExtra = inv_doc_load_job_card_extra($pdo, $quotationId);
$contactResolved = inv_doc_resolve_customer_contact($invoice, $formData, $jobExtra);
$customerName = $invoice['client_name'] ?? ($formData['customer_name'] ?? '');
$customerAddress = $invoice['client_address'] ?? ($formData['customer_address'] ?? '');
$customerPhone = $contactResolved['phone'];
$customerEmail = $contactResolved['email'];
$contactPerson = $contactResolved['person'];
$vehicleRegNo = $formData['vehicle_reg_no'] ?? $invoice['vehicle_reg_no'] ?? '';
$vehicleModel = $formData['model'] ?? $invoice['vehicle_model'] ?? '';
$vehicleVinNo = $formData['vin_no'] ?? $invoice['vehicle_vin_no'] ?? '';
$kilometers = $formData['kilometers'] ?? '';
$fleetNo = $formData['fleet_no'] ?? '';
$jobNo = $formData['job_no'] ?? $invoice['job_card_number'] ?? '';
$purchaseOrder = $formData['purchase_order'] ?? '';
$quotationNumber = $formData['quote_number'] ?? '';
$invoiceDate = $formData['date'] ?? $invoice['issued_date'] ?? '';

$invNumber = (string) ($invoice['invoice_number'] ?? '');
$invStatus = strtolower((string) ($invoice['status_paid'] ?? 'unpaid'));
$invAmount = (float) ($invoice['amount'] ?? 0);
$statusLabels = ['unpaid' => 'Unpaid', 'partial' => 'Partially paid', 'paid' => 'Paid'];
$statusLabel = $statusLabels[$invStatus] ?? ucfirst($invStatus);
$statusClass = in_array($invStatus, ['unpaid', 'partial', 'paid'], true) ? $invStatus : 'unpaid';

$clientInitials = '';
if ($customerName !== '') {
    $parts = preg_split('/\s+/', trim($customerName));
    $clientInitials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
if ($clientInitials === '') {
    $clientInitials = '?';
}

$vatAmount = (float) ($invoice['vat_amount'] ?? 0);
$subtotal = max(0, $invAmount - $vatAmount);
$issuedDisplay = $invoiceDate !== '' ? date('d M Y', strtotime((string) $invoiceDate) ?: time()) : '-';
$dueRaw = $invoice['due_date'] ?? '';
$isOverdue = ($invStatus !== 'paid' && $dueRaw !== '' && strtotime((string) $dueRaw) < strtotime('today'));

$clientStats = ['count' => 1, 'total' => $invAmount, 'paid_count' => 0, 'paid_amt' => 0.0, 'due_count' => 0, 'due_amt' => 0.0, 'overdue_count' => 0, 'overdue_amt' => 0.0];
try {
    $qid = (int) ($invoice['quotation_id'] ?? 0);
    if ($qid > 0) {
        $cStmt = $pdo->prepare(
            "SELECT i.status_paid, i.amount, i.due_date
             FROM invoices i
             INNER JOIN quotations q ON q.id = i.quotation_id
             WHERE q.client_id = (SELECT client_id FROM quotations WHERE id = ? LIMIT 1)
               AND i.deleted_at IS NULL"
        );
        $cStmt->execute([$qid]);
        $rows = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $clientStats = ['count' => 0, 'total' => 0.0, 'paid_count' => 0, 'paid_amt' => 0.0, 'due_count' => 0, 'due_amt' => 0.0, 'overdue_count' => 0, 'overdue_amt' => 0.0];
            foreach ($rows as $row) {
                $amt = (float) ($row['amount'] ?? 0);
                $st = strtolower((string) ($row['status_paid'] ?? 'unpaid'));
                $clientStats['count']++;
                $clientStats['total'] += $amt;
                if ($st === 'paid') {
                    $clientStats['paid_count']++;
                    $clientStats['paid_amt'] += $amt;
                } elseif ($st !== 'paid' && !empty($row['due_date']) && strtotime((string) $row['due_date']) < strtotime('today')) {
                    $clientStats['overdue_count']++;
                    $clientStats['overdue_amt'] += $amt;
                } else {
                    $clientStats['due_count']++;
                    $clientStats['due_amt'] += $amt;
                }
            }
        }
    }
} catch (Exception $e) {
    // keep single-invoice defaults
}
$statsTotal = max(0.01, (float) $clientStats['total']);
$pctPaid = min(100, round(($clientStats['paid_amt'] / $statsTotal) * 100));
$pctDue = min(100 - $pctPaid, round(($clientStats['due_amt'] / $statsTotal) * 100));
$pctOver = max(0, 100 - $pctPaid - $pctDue);

$pageTitle = 'View Invoice';
$is_admin_user = is_admin();
// ERP nav highlights Invoices for view_invoice.php (rewrite may run view.php).
if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'view.php') {
    $_SERVER['PHP_SELF'] = preg_replace('#view\.php$#', 'view_invoice.php', (string) ($_SERVER['PHP_SELF'] ?? 'view_invoice.php'));
}
include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/view_crm_shell_before.inc.php'; ?>
<style id="inv-doc-view-extras">
  #inv-live-doc-mount #aq-print-inner .aq-doc-header-wrap { position: relative; overflow: visible; }
  #inv-live-doc-mount #aq-print-inner .aq-doc-title-badge {
    top: 4px !important;
    bottom: auto !important;
    right: 10px !important;
    background: transparent !important;
    padding: 0 !important;
    letter-spacing: 3px !important;
    line-height: 1 !important;
  }
  @media print {
    html, body { margin: 0 !important; padding: 0 !important; background: #fff !important; }
    #inv-live-doc-mount {
      display: block !important;
      width: 100% !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    #inv-live-doc-mount #aq-print-inner {
      width: 100% !important;
      max-width: 100% !important;
      min-height: auto !important;
      margin: 0 !important;
      padding: 10mm 12mm !important;
      box-shadow: none !important;
      border: none !important;
      background: #fff !important;
    }
    #inv-live-doc-mount #aq-print-inner .aq-doc-header-wrap { position: relative !important; overflow: visible !important; }
    #inv-live-doc-mount #aq-print-inner .aq-doc-title-badge {
      top: 4px !important;
      bottom: auto !important;
      right: 10px !important;
      background: transparent !important;
      padding: 0 !important;
      letter-spacing: 3px !important;
      line-height: 1 !important;
    }
  }
</style>
<?php /* Tax invoice preview — do not modify */ ?>
          <?php
          $doc_blank_only = !empty($_GET['print_blank']);
          $doc_title = 'Tax Invoice';
          $doc_wrapper_id = 'aq-print-inner';
          $doc_wrapper_class = 'aq-print-inner';
          $show_invoice_number = true;
          $doc_invoice_number = $invoice['invoice_number'] ?? '';
          $paid_at = $invoice['paid_at'] ?? '';
          $invoice_payment_note_kind = in_array($invStatus, ['paid', 'partial'], true) ? $invStatus : '';
          $show_invoice_payment_note = $invoice_payment_note_kind !== '';
          $show_paid_banner = ($invStatus === 'paid');
          include __DIR__ . '/../includes/trade_document_body.inc.php';
          ?>
<?php include __DIR__ . '/view_crm_shell_after.inc.php'; ?>
<script>
(function () {
  var main = document.getElementById('inv-doc-print-styles');
  var extras = document.getElementById('inv-doc-view-extras');
  if (main && extras) {
    main.textContent += '\n' + (extras.textContent || '');
    extras.parentNode.removeChild(extras);
  }
})();
</script>

<?php if ($inv_qt_chat_enabled): ?>
<?php
$qt_chat_quote_id = $quotationId;
$qt_chat_quote_label = $quotationNumber !== ''
    ? $quotationNumber
    : ('QTN-' . str_pad((string) $quotationId, 5, '0', STR_PAD_LEFT));
$qt_chat_customer_name = trim((string) $customerName);
$qt_chat_messages = $inv_manager_comments;
$qt_chat_viewer_role = 'admin';
require __DIR__ . '/../Quotation/quotation_chat_modal.inc.php';
$qt_chat_modal_js = __DIR__ . '/../Quotation/qt_chat_modal.js';
$qt_chat_modal_js_v = is_file($qt_chat_modal_js) ? (string) filemtime($qt_chat_modal_js) : '1';
?>
<script>
window.QT_CHAT_API_URL = <?php echo json_encode('Quotation/qt_message_api.php'); ?>;
window.QT_CHAT_QUOTE_ID = <?php echo (int) $quotationId; ?>;
window.QT_CHAT_VIEWER_ROLE = 'admin';
window.QT_CHAT_INITIAL_MESSAGES = <?php echo json_encode($inv_manager_comments, JSON_UNESCAPED_UNICODE); ?>;
window.QT_CHAT_OPEN_ON_LOAD = <?php echo $inv_open_chat ? 'true' : 'false'; ?>;
</script>
<script src="Quotation/qt_chat_modal.js?v=<?php echo htmlspecialchars($qt_chat_modal_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
