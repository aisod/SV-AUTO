<?php
// View Invoice - Client can view and download
$page_title = 'View Invoice';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../../backend/config/notifications.php';

// Handle payment notification
$paymentNotificationSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'notify_payment') {
    $invoiceId = filter_input(INPUT_POST, 'invoice_id', FILTER_VALIDATE_INT);
    if ($invoiceId && isset($inv) && $inv['id'] == $invoiceId) {
        $clientName = isset($inv['client_name']) ? $inv['client_name'] : 'Client';
        $invNumber = isset($inv['invoice_number']) ? $inv['invoice_number'] : ('INV-' . str_pad($invoiceId, 6, '0', STR_PAD_LEFT));
        $amount = isset($inv['amount']) ? $symbol . number_format($inv['amount'], 2) : 'N/A';
        
        $title = 'Payment Notification';
        $message = 'Client ' . $clientName . ' has made payment for ' . $invNumber . ' of ' . $amount;
        $link = '../Admin/view_invoice.php?id=' . $invoiceId;
        
        $notified = notifyAdmins($pdo, $title, $message, $link);
        if ($notified > 0) {
            $paymentNotificationSent = true;
        }
    }
}

// Get client email from session
$clientEmail = $_SESSION['email'] ?? '';
$clientId = null;
$client = null;

// Step 1: Find client record by matching email
if ($clientEmail) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$clientEmail]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client) {
        $clientId = $client['id'];
    }
}

// Get invoice ID
$invoiceId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$invoiceId || !$clientId) {
    header('Location: invoices.php');
    exit;
}

// Step 2: Fetch invoice with proper joins - using only columns that exist
$stmt = $pdo->prepare("
    SELECT 
        i.id, i.amount, i.status, i.created_at, i.paid_at, i.is_editable,
        i.issue_date, i.due_date, i.notes, i.payment_method, i.payment_reference,
        i.details AS invoice_details,
        q.details AS quotation_details, q.id AS quotation_id,
        c.name AS client_name, c.phone AS client_phone,
        c.email AS client_email, c.address AS client_address,
        v.reg_no, v.model, v.vin_no,
        jc.card_number, jc.description AS job_description
    FROM invoices i
    JOIN quotations q ON i.quotation_id = q.id
    LEFT JOIN job_cards jc ON q.job_card_id = jc.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    JOIN clients c ON q.client_id = c.id
    WHERE i.id = ? AND q.client_id = ? AND i.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([$invoiceId, $clientId]);
$inv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inv) {
    header('Location: invoices.php');
    exit;
}

// Setup variables
$business = getBusiness();
$currency = getCurrency();
$symbol = isset($currency['symbol']) ? $currency['symbol'] : 'N$';
$tax_rate = (float)getSetting('tax_rate', 15);

$invoice_number = 'INV-' . str_pad($inv['id'], 6, '0', STR_PAD_LEFT);
$amount_excl = round($inv['amount'] / (1 + $tax_rate / 100), 2);
$vat_amount = $inv['amount'] - $amount_excl;
$details = isset($inv['invoice_details']) && $inv['invoice_details'] ? $inv['invoice_details'] : (isset($inv['quotation_details']) ? $inv['quotation_details'] : '');

// Check if overdue
$is_overdue = false;
if (isset($inv['status']) && $inv['status'] === 'unpaid' && isset($inv['due_date']) && $inv['due_date']) {
    $is_overdue = strtotime($inv['due_date']) < time();
}

// Logo base64
$logo_base64 = '';
$logo_path = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logo_path)) {
    $logo_base64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
}

// Parse line items from details
$items = [];
$current_item = null;
foreach (explode("\n", $details ?? '') as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '<strong>') !== false || strpos($line, '═') !== false) {
        if (strpos($line, 'TOTAL:') !== false && $current_item) {
            $items[] = $current_item;
            $current_item = null;
        }
        continue;
    }
    $fc = mb_substr($line, 0, 1, 'UTF-8');
    if ($fc === '•' || ord($fc) > 127) {
        if ($current_item) $items[] = $current_item;
        $current_item = ['desc' => trim(mb_substr($line, 1, null, 'UTF-8')), 'amount' => ''];
    } elseif ($current_item && preg_match('/N\$\s*[0-9,.]+/', $line)) {
        $current_item['amount'] = $line;
    }
}
if ($current_item) $items[] = $current_item;

// Status display
$statusClass = 'erp-badge-warning';
$statusLabel = 'Unknown';
$statusBadgeClass = 'status-pending';
$statusText = 'PENDING';

if (isset($inv['status'])) {
    switch ($inv['status']) {
        case 'paid':
            $statusClass = 'erp-badge-success';
            $statusLabel = 'Paid';
            $statusBadgeClass = 'status-approved';
            $statusText = 'PAID';
            break;
        case 'unpaid':
            $statusClass = 'erp-badge-danger';
            $statusLabel = 'Unpaid';
            $statusBadgeClass = 'status-rejected';
            $statusText = 'UNPAID';
            break;
    }
}
?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="invoices.php">Invoices</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Invoice #<?php echo htmlspecialchars(isset($inv['invoice_number']) ? $inv['invoice_number'] : $invoice_number); ?></span>
    </div>
    <div class="erp-flex erp-justify-center erp-align-center erp-mt-2">
        <h1 class="erp-page-title" style="text-align:center;">Invoice Details</h1>
    </div>
</div>

<style>
/* ── INVOICE SHELL ── */
.invoice-shell { background:white; border:2px solid #000; max-width:900px; margin:0 auto 16px; font-family:Arial,sans-serif; }

/* ── HEADER ── */
.inv-head { display:flex; justify-content:space-between; align-items:flex-start; padding:10px 14px; border-bottom:2px solid #000; }
.inv-head img { height:55px; width:auto; }
.inv-head-center { flex:1; padding:0 14px; }
.inv-head-center .biz-name { font-size:18px; font-weight:900; color:#F5A623; }
.inv-head-center .biz-sub  { font-size:9px; color:#555; line-height:1.7; margin-top:2px; }
.inv-head-right { text-align:right; font-size:9px; line-height:1.6; color:#222; }

/* ── TITLE BAR ── */
.inv-title-bar { display:flex; justify-content:space-between; align-items:center; background:#f5f5f5; border-bottom:2px solid #000; padding:7px 14px; }
.inv-title-bar .doc-title { font-size:20px; font-weight:900; letter-spacing:5px; text-transform:uppercase; }
.inv-title-bar .doc-no    { font-size:14px; font-weight:900; color:#1a1a1a; letter-spacing:2px; display:flex; align-items:center; gap:10px; }

/* ── STATUS BADGES ── */
.badge { display:inline-block; padding:6px 16px; border-radius:20px; font-weight:900; font-size:11px; text-transform:uppercase; letter-spacing:1px; }
.badge-unpaid  { background:#fff4e5; color:#d97706; border:2px solid #d97706; }
.badge-paid    { background:#e8f5e8; color:#2e7d32; border:2px solid #2e7d32; }
.badge-overdue { background:#ffebee; color:#c62828; border:2px solid #c62828; }

/* ── BODY GRID ── */
.inv-body { display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000; }
.inv-col-left  { border-right:2px solid #000; padding:10px 14px; }
.inv-col-right { padding:10px 14px; }
.col-section-title { font-size:9px; font-weight:900; color:#1a1a1a; text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid #ddd; padding-bottom:5px; margin-bottom:10px; }

/* ── FIELDS ── */
.inv-field { display:flex; align-items:flex-end; margin-bottom:7px; gap:6px; }
.inv-field label { font-size:9px; font-weight:700; text-transform:uppercase; white-space:nowrap; color:#333; min-width:110px; padding-bottom:3px; }
.inv-field .val   { flex:1; border-bottom:1.5px solid #000; padding:3px 5px; font-size:12px; color:#111; min-height:20px; font-weight:600; }
.inv-field .val.highlight { color:#F5A623; }

/* ── SECTION HDR ── */
.inv-section { text-align:center; font-size:10px; font-weight:900; letter-spacing:4px; text-transform:uppercase; background:#f0f0f0; border-top:2px solid #000; border-bottom:1px solid #000; padding:4px 0; }

/* ── WORK DESC ── */
.work-desc { padding:8px 14px; border-bottom:2px solid #000; font-size:11px; line-height:1.6; color:#111; font-weight:600; min-height:40px; }

/* ── ITEMS TABLE ── */
.items-tbl { width:100%; border-collapse:collapse; }
.items-tbl th { background:#EBF4FF; border:1px solid #EBF4FF; padding:5px 8px; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:1px; text-align:left; color:#2563EB; }
.items-tbl td { border:1px solid #ccc; padding:4px 8px; font-size:11px; color:#111; font-weight:600; vertical-align:top; }
.items-tbl .amt-col { width:120px; text-align:right; }

/* ── TOTALS ── */
.amt-row     { display:flex; justify-content:space-between; padding:6px 14px; font-size:12px; font-weight:600; border-bottom:1px solid #eee; }
.grand-total { display:flex; justify-content:space-between; padding:10px 14px; font-size:16px; font-weight:900; color:#1a1a1a; background:#f0f0f0; border-top:2px solid #000; border-bottom:2px solid #000; }

/* ── NOTES ── */
.notes-box { padding:8px 14px; border-bottom:2px solid #000; font-size:10px; line-height:1.6; background:#FFF8F0; }

/* ── PAYMENT INFO ── */
.payment-info { display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000; }
.payment-info .pi-col { padding:10px 14px; }
.payment-info .pi-col:first-child { border-right:2px solid #000; }

/* ── SIGNATURE ROW ── */
.sig-row { display:grid; grid-template-columns:1fr 1fr; border-top:2px solid #000; }
.sig-left  { border-right:2px solid #000; padding:16px 14px; text-align:center; }
.sig-right { padding:16px 14px; text-align:center; }
.sig-line  { border-bottom:2px solid #000; margin-bottom:8px; padding-bottom:50px; }
.sig-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; }
.stamp-box { border:2px dashed #999; height:60px; display:flex; align-items:center; justify-content:center; margin-bottom:8px; background:#fafafa; }

/* ── DOC FOOTER ── */
.doc-footer { text-align:center; border-top:1px solid #ccc; padding:8px 14px; font-size:8px; color:#666; line-height:1.8; background:#fff; }

/* ── PAID WATERMARK (screen only) ── */
.paid-watermark { position:fixed; top:38%; left:5%; font-size:100px; font-weight:900; transform:rotate(-35deg); letter-spacing:6px; opacity:0.05; color:#2e7d32; pointer-events:none; z-index:0; }

/* ── BUTTON BAR ── */
.btn-bar { max-width:900px; margin:20px auto 40px; display:flex; gap:12px; flex-wrap:wrap; justify-content:center; }

/* ── RESPONSIVE INVOICE ── */
@media (max-width: 1024px) {
    .invoice-shell { max-width:100%; margin:0 10px; }
    .btn-bar { max-width:100%; margin:20px 10px 40px; }
}

@media (max-width: 768px) {
    /* Hide hamburger menu on invoice view */
    .mobile-menu-toggle { display: none !important; }
    
    /* Invoice adjustments for mobile */
    .invoice-shell { 
        max-width:100%; 
        margin:0; 
        border-width:1px;
    }
    
    .inv-head { 
        flex-direction: column; 
        gap: 10px;
        padding: 10px;
    }
    .inv-head img { height:40px; }
    .inv-head-center { padding:0; text-align:center; }
    .inv-head-center .biz-name { font-size:16px; }
    .inv-head-right { text-align:center; }
    
    .inv-title-bar { 
        flex-direction: column; 
        gap: 8px;
        text-align:center;
    }
    .inv-title-bar .doc-title { font-size:16px; }
    .inv-title-bar .doc-no { font-size:12px; }
    
    .inv-body { grid-template-columns: 1fr; }
    
    .items-tbl { font-size:10px; }
    .items-tbl th, .items-tbl td { padding:4px; }
    
    .grand-total { font-size:14px; }
    
    /* Button bar adjustments */
    .btn-bar { 
        flex-direction: column;
        gap: 8px;
        padding: 0 10px;
    }
    .btn-bar .erp-btn { 
        width: 100%;
        margin-bottom: 0;
    }
}

@media (max-width: 480px) {
    .inv-head-center .biz-name { font-size:14px; }
    .inv-title-bar .doc-title { font-size:14px; letter-spacing:2px; }
    .items-tbl { font-size:9px; }
    .badge { padding:4px 10px; font-size:9px; }
}

/* ── PRINT ── */
@media print {
    .sidebar, .header, .erp-page-header, .btn-bar, .erp-alert { display:none !important; }
    body { background:white; }
    .content-wrapper { margin:0 !important; padding:0 !important; }
    .invoice-shell { max-width:100%; margin:0; box-shadow:none; }
    @page { size:A4 portrait; margin:10mm; }
}
</style>

<?php if (isset($inv['status']) && $inv['status'] === 'paid'): ?>
<div class="paid-watermark">PAID</div>
<?php endif; ?>

<!-- ── INVOICE DOCUMENT ─────────────────────────────────────────────── -->
<div class="invoice-shell invoice-document">

    <!-- HEADER -->
    <div class="inv-head">
        <div>
            <?php if ($logo_base64): ?>
            <img src="<?php echo $logo_base64; ?>" alt="Logo">
            <?php endif; ?>
        </div>
        <div class="inv-head-center">
            <div class="biz-name"><?php echo htmlspecialchars(isset($business['name']) ? $business['name'] : 'SV Auto Services'); ?></div>
            <div class="biz-sub">
                <?php echo htmlspecialchars(isset($business['address']) ? $business['address'] : ''); ?><br>
                <?php echo htmlspecialchars(isset($business['phone']) ? $business['phone'] : ''); ?>
                <?php echo !empty($business['email']) ? ' • ' . htmlspecialchars($business['email']) : ''; ?>
                <?php if (!empty($business['tax_number'])): ?><br>Tax No: <?php echo htmlspecialchars($business['tax_number']); ?><?php endif; ?>
            </div>
        </div>
        <div class="inv-head-right">
            <?php echo htmlspecialchars(isset($business['address']) ? $business['address'] : ''); ?><br>
            Cell: <?php echo htmlspecialchars(isset($business['phone']) ? $business['phone'] : ''); ?><br>
            <?php echo !empty($business['email']) ? htmlspecialchars($business['email']) . '<br>' : ''; ?>
            <?php echo !empty($business['tax_number']) ? 'Reg No: ' . htmlspecialchars($business['tax_number']) : ''; ?>
        </div>
    </div>

    <!-- TITLE BAR -->
    <div class="inv-title-bar">
        <div class="doc-title">Tax Invoice</div>
        <div class="doc-no">
            <?php echo $invoice_number; ?>
            <?php if (isset($inv['status']) && $inv['status'] === 'paid'): ?>
                <span class="badge badge-paid"><i class="fas fa-check"></i> Paid</span>
            <?php elseif ($is_overdue): ?>
                <span class="badge badge-overdue"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
            <?php else: ?>
                <span class="badge badge-unpaid"><i class="fas fa-clock"></i> Unpaid</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- BILL TO + INVOICE DETAILS -->
    <div class="inv-body">
        <div class="inv-col-left">
            <div class="col-section-title">Bill To</div>
            <div class="inv-field"><label>Client</label><div class="val highlight"><?php echo htmlspecialchars(isset($inv['client_name']) ? $inv['client_name'] : 'N/A'); ?></div></div>
            <div class="inv-field"><label>Address</label><div class="val"><?php echo htmlspecialchars(isset($inv['client_address']) ? $inv['client_address'] : '—'); ?></div></div>
            <div class="inv-field"><label>Phone</label><div class="val"><?php echo htmlspecialchars(isset($inv['client_phone']) ? $inv['client_phone'] : '—'); ?></div></div>
            <div class="inv-field"><label>Email</label><div class="val"><?php echo htmlspecialchars(isset($inv['client_email']) ? $inv['client_email'] : '—'); ?></div></div>
        </div>
        <div class="inv-col-right">
            <div class="col-section-title">Invoice Details</div>
            <div class="inv-field"><label>Invoice No.</label><div class="val highlight"><?php echo $invoice_number; ?></div></div>
            <div class="inv-field"><label>Issue Date</label><div class="val"><?php echo date('d M Y', strtotime(isset($inv['issue_date']) && $inv['issue_date'] ? $inv['issue_date'] : $inv['created_at'])); ?></div></div>
            <div class="inv-field"><label>Due Date</label><div class="val" <?php echo $is_overdue ? 'style="color:#c62828;font-weight:900;"' : ''; ?>><?php echo date('d M Y', strtotime(isset($inv['due_date']) && $inv['due_date'] ? $inv['due_date'] : $inv['created_at'])); ?></div></div>
            <div class="inv-field"><label>Vehicle Reg.</label><div class="val"><?php echo htmlspecialchars(isset($inv['reg_no']) ? $inv['reg_no'] : 'N/A'); ?></div></div>
            <div class="inv-field"><label>Model</label><div class="val"><?php echo htmlspecialchars(isset($inv['model']) ? $inv['model'] : 'N/A'); ?></div></div>
            <div class="inv-field"><label>Job Card</label><div class="val"><?php echo htmlspecialchars(isset($inv['card_number']) ? $inv['card_number'] : 'N/A'); ?></div></div>
        </div>
    </div>

    <!-- WORK DESCRIPTION -->
    <?php if (!empty($inv['job_description'])): ?>
    <div class="inv-section">Customer Complaint / Work Required</div>
    <div class="work-desc"><?php echo nl2br(htmlspecialchars($inv['job_description'])); ?></div>
    <?php endif; ?>

    <!-- ITEMS -->
    <div class="inv-section">Items &amp; Services</div>
    <table class="items-tbl">
        <thead>
            <tr>
                <th style="width:50px;">No.</th>
                <th>Description</th>
                <th class="amt-col">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $idx => $it): ?>
                <tr>
                    <td style="text-align:center;"><?php echo $idx + 1; ?></td>
                    <td><?php echo htmlspecialchars(isset($it['desc']) ? $it['desc'] : ''); ?></td>
                    <td class="amt-col"><?php echo htmlspecialchars(isset($it['amount']) ? $it['amount'] : ''); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3" style="text-align:center;padding:20px;color:#999;">Service as per approved quotation</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- TOTALS -->
    <div class="amt-row"><span>Amount Excl. VAT</span><span><?php echo $symbol . number_format($amount_excl, 2); ?></span></div>
    <div class="amt-row"><span>VAT (<?php echo $tax_rate; ?>%)</span><span><?php echo $symbol . number_format($vat_amount, 2); ?></span></div>
    <div class="grand-total"><span>TOTAL AMOUNT DUE</span><span><?php echo $symbol . number_format((float)$inv['amount'], 2); ?></span></div>

    <!-- NOTES -->
    <?php if (!empty($inv['notes'])): ?>
    <div class="notes-box"><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($inv['notes'])); ?></div>
    <?php endif; ?>

    <!-- PAYMENT INFO (if paid) -->
    <?php if (isset($inv['status']) && $inv['status'] === 'paid'): ?>
    <div class="inv-section">Payment Information</div>
    <div class="payment-info">
        <div class="pi-col">
            <div class="inv-field"><label>Paid On</label><div class="val"><?php echo date('d M Y H:i', strtotime($inv['paid_at'])); ?></div></div>
            <?php if (isset($inv['payment_method']) && $inv['payment_method']): ?>
            <div class="inv-field"><label>Method</label><div class="val"><?php echo htmlspecialchars($inv['payment_method']); ?></div></div>
            <?php endif; ?>
        </div>
        <div class="pi-col">
            <?php if (isset($inv['payment_reference']) && $inv['payment_reference']): ?>
            <div class="inv-field"><label>Reference</label><div class="val"><?php echo htmlspecialchars($inv['payment_reference']); ?></div></div>
            <?php endif; ?>
            <div class="inv-field"><label>Status</label><div class="val" style="color:#2e7d32;">PAID</div></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TERMS -->
    <div style="padding:6px 14px;border-top:2px solid #000;border-bottom:2px solid #000;font-size:8px;line-height:1.5;color:#555;background:#fafafa;">
        <strong>Terms &amp; Conditions:</strong>
        Payment is due by <?php echo date('d M Y', strtotime(isset($inv['due_date']) && $inv['due_date'] ? $inv['due_date'] : $inv['created_at'])); ?> •
        Late payments may attract a penalty •
        Bank Details: <?php echo htmlspecialchars(isset($business['bank_details']) ? $business['bank_details'] : 'Available upon request'); ?> •
        All parts carry a 3-month warranty • Thank you for choosing <?php echo htmlspecialchars(isset($business['name']) ? $business['name'] : 'SV Auto Services'); ?>
    </div>

    <!-- SIGNATURES -->
    <div class="sig-row">
        <div class="sig-left">
            <div class="sig-line"></div>
            <div class="sig-label">Authorized Signature</div>
            <div style="font-size:9px;color:#666;margin-top:4px;"><?php echo htmlspecialchars(isset($business['name']) ? $business['name'] : 'SV Auto Services'); ?></div>
        </div>
        <div class="sig-right">
            <div class="stamp-box"><div style="font-size:9px;color:#999;text-transform:uppercase;letter-spacing:1px;">Company Stamp</div></div>
            <div class="sig-label">Official Seal</div>
        </div>
    </div>

    <!-- DOC FOOTER -->
    <div class="doc-footer">
        <strong><?php echo htmlspecialchars(isset($business['name']) ? $business['name'] : 'SV Auto Services'); ?></strong><br>
        <?php echo htmlspecialchars(isset($business['address']) ? $business['address'] : ''); ?>
        <?php echo !empty($business['phone']) ? ' • ' . htmlspecialchars($business['phone']) : ''; ?>
        <?php echo !empty($business['email']) ? ' • ' . htmlspecialchars($business['email']) : ''; ?>
        <?php if (!empty($business['tax_number'])): ?> • Tax No: <?php echo htmlspecialchars($business['tax_number']); ?><?php endif; ?><br>
        <span style="font-size:7px;opacity:.6;">Generated <?php echo date('d F Y \a\t H:i'); ?> — SV Auto Management System</span>
    </div>

</div><!-- /.invoice-shell -->

<!-- ── BUTTON BAR ─────────────────────────────────────────────────── -->
<div class="btn-bar">
    <button onclick="window.print()" class="erp-btn erp-btn-success">
        <i class="fas fa-print"></i> Print / PDF
    </button>
    <?php if (isset($inv['status']) && $inv['status'] === 'unpaid'): ?>
    <button onclick="showPayModal()" class="erp-btn erp-btn-primary">
        <i class="fas fa-credit-card"></i> Pay Now
    </button>
    <?php endif; ?>
</div>

<?php if ($paymentNotificationSent): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        alert('Thank you! Admin has been notified of your payment.');
    });
</script>
<?php endif; ?>

<!-- ── PAY NOW MODAL ─────────────────────────────────────────────── -->
<div id="payModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,.3); max-width:520px; width:90%; max-height:90vh; overflow-y:auto; animation:slideIn .25s ease-out;">
        <div style="padding:30px 30px 20px; text-align:center; border-bottom:1px solid #eee;">
            <div style="width:80px; height:80px; background:#FFF8EC; border-radius:50%; margin:0 auto 16px; display:flex; align-items:center; justify-content:center;">
                <i class="fas fa-credit-card" style="font-size:36px; color:#F5A623;"></i>
            </div>
            <h3 style="font-size:22px; font-weight:900; color:#333; margin-bottom:8px;">Payment Instructions</h3>
        </div>
        <div style="padding:20px 30px; font-size:15px; line-height:1.7; color:#555;">
            <p style="margin-bottom:20px;">
                To complete your payment for invoice <strong><?php echo htmlspecialchars($invoice_number); ?></strong> of <strong><?php echo $symbol . number_format((float)$inv['amount'], 2); ?></strong>, please use one of the following methods:
            </p>
            
            <div style="background:#f8f9fa; border-radius:8px; padding:16px; margin-bottom:20px;">
                <h4 style="font-size:14px; font-weight:700; color:#333; margin-bottom:12px;"><i class="fas fa-university" style="color:#F5A623; margin-right:8px;"></i>Bank Transfer</h4>
                <p style="font-size:13px; margin:0; line-height:1.8;">
                    <?php echo nl2br(htmlspecialchars(isset($business['bank_details']) && $business['bank_details'] ? $business['bank_details'] : "Bank: Bank of Namibia\nAccount Name: SV Auto Services\nAccount Number: 1234567890\nBranch: Windhoek\nReference: " . $invoice_number)); ?>
                </p>
            </div>
            
            <div style="background:#FFF8EC; border-left:4px solid #F5A623; padding:12px 16px; border-radius:4px; margin-bottom:20px;">
                <h4 style="font-size:14px; font-weight:700; color:#333; margin-bottom:8px;"><i class="fas fa-info-circle" style="color:#F5A623; margin-right:8px;"></i>Contact Us</h4>
                <p style="font-size:13px; margin:0; line-height:1.6;">
                    <i class="fas fa-phone" style="color:#F5A623; width:20px;"></i> <?php echo htmlspecialchars(isset($business['phone']) ? $business['phone'] : 'N/A'); ?><br>
                    <i class="fas fa-envelope" style="color:#F5A623; width:20px;"></i> <?php echo htmlspecialchars(isset($business['email']) ? $business['email'] : 'N/A'); ?>
                </p>
            </div>
            
            <form method="POST" id="paymentForm" style="margin:0;">
                <input type="hidden" name="action" value="notify_payment">
                <input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars($inv['id']); ?>">
                <button type="submit" class="erp-btn erp-btn-success" style="width:100%; padding:14px; font-size:15px;">
                    <i class="fas fa-check-circle"></i> I have made payment - Notify Admin
                </button>
            </form>
        </div>
        <div style="padding:0 30px 30px; text-align:center;">
            <button onclick="closePayModal()" class="erp-btn erp-btn-secondary" style="padding:10px 24px;">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<style>
@keyframes slideIn { from { transform:translateY(-40px); opacity:0; } to { transform:translateY(0); opacity:1; } }

/* ── PRINT STYLES ─────────────────────────────────────────────────── */
@media print {
    /* Hide everything except the invoice document */
    .erp-sidebar,
    .erp-header,
    .erp-page-header,
    .erp-breadcrumb,
    nav,
    header,
    footer,
    .erp-footer,
    button,
    a.erp-btn,
    .erp-card:not(.invoice-document),
    .btn-bar,
    #payModal {
        display: none !important;
    }
    
    /* Show only the invoice document */
    .invoice-document {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
    }
    
    /* Fit everything on one page */
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }
    
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    @page {
        size: A4;
        margin: 10mm;
    }
}
</style>

<script>
function showPayModal() {
    document.getElementById('payModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closePayModal() {
    document.getElementById('payModal').style.display = 'none';
    document.body.style.overflow = '';
}
// Close on backdrop click
document.getElementById('payModal').addEventListener('click', function(e) {
    if (e.target === this) closePayModal();
});
// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePayModal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
