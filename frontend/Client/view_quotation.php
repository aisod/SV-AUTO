<?php
// View Quotation - Client can view and respond
$page_title = 'View Quotation';
require_once __DIR__ . '/includes/header.php';

// Get client email from session
$clientEmail = $_SESSION['email'] ?? '';
$clientId = null;
$message = '';
$error = '';

// Step 1: Find client record by matching email
if ($clientEmail) {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$clientEmail]);
    $clientId = $stmt->fetchColumn();
}

// Get quotation ID
$quotationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$quotationId || !$clientId) {
    header('Location: quotations.php');
    exit;
}

// Step 2: Fetch quotation with full client details
$stmt = $pdo->prepare("
    SELECT q.*, jc.card_number, jc.description as job_description, jc.extra_data,
           v.reg_no, v.model, v.vin_no,
           c.name as client_name, c.email as client_email, c.phone as client_phone, c.address as client_address
    FROM quotations q
    JOIN clients c ON q.client_id = c.id
    LEFT JOIN job_cards jc ON q.job_card_id = jc.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE q.id = ? AND q.client_id = ?
    LIMIT 1
");
$stmt->execute([$quotationId, $clientId]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header('Location: quotations.php');
    exit;
}

// Handle form submission (Accept/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $responseNotes = trim($_POST['response_notes'] ?? '');
    
    if ($action === 'accept' || $action === 'reject') {
        $clientStatus = $action === 'accept' ? 'client_accepted' : 'client_rejected';
        
        try {
            $stmt = $pdo->prepare("
                UPDATE quotations 
                SET client_status = ?, client_response_notes = ?, client_response_date = NOW() 
                WHERE id = ? AND client_id = ?
            ");
            $stmt->execute([$clientStatus, $responseNotes, $quotationId, $clientId]);
            
            $message = "Quotation " . ($action === 'accept' ? 'accepted' : 'rejected') . " successfully.";
            
            // Refresh quotation data
            $stmt->execute([$quotationId, $clientId]);
            $quote = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Failed to update quotation. Please try again.";
        }
    }
}

// Parse extra data
$extra = [];
if (isset($quote['extra_data']) && $quote['extra_data']) {
    $extra = json_decode($quote['extra_data'], true) ?: [];
}

// Parse line items from details text (strip HTML tags)
$cleanDetails = isset($quote['details']) ? strip_tags($quote['details']) : '';
$detailsLines = explode("\n", $cleanDetails);
$items = [];
$currentItem = null;
foreach ($detailsLines as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '═') !== false) {
        if (strpos($line, 'TOTAL:') !== false && $currentItem) {
            $items[] = $currentItem;
            $currentItem = null;
        }
        continue;
    }
    $firstChar = mb_substr($line, 0, 1, 'UTF-8');
    if ($firstChar === '•' || ord($firstChar) > 127) {
        if ($currentItem) $items[] = $currentItem;
        $currentItem = ['desc' => trim(mb_substr($line, 1, null, 'UTF-8')), 'amount' => ''];
    } elseif ($currentItem && preg_match('/N\$\s*[0-9,.]+/', $line)) {
        $currentItem['amount'] = $line;
    }
}
if ($currentItem) $items[] = $currentItem;

// Logo
$logoBase64 = '';
$logoPath = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
}

$business = getBusiness();
$currency = getCurrency();

// Determine client response status
$clientStatus = isset($quote['client_status']) ? $quote['client_status'] : null;
$clientResponseDate = isset($quote['client_response_date']) ? $quote['client_response_date'] : null;
$clientResponseNotes = isset($quote['client_response_notes']) ? $quote['client_response_notes'] : '';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="quotations.php">Quotations</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">QTN-<?php echo str_pad($quotationId, 6, '0', STR_PAD_LEFT); ?></span>
    </div>
    <div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
        <h1 class="erp-page-title">View Quotation</h1>
        <a href="quotations.php" class="erp-btn erp-btn-secondary" style="padding: 8px 20px; font-size: 13px;">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="erp-alert erp-alert-success erp-mb-4">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="erp-alert erp-alert-error erp-mb-4">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<style>
    /* Professional Quotation Document Styles */
    .quote-shell { background:white; border:2px solid #000; max-width:900px; margin:0 auto 30px; font-family:Arial,sans-serif; }
    .quote-head { display:flex; justify-content:space-between; align-items:flex-start; padding:8px 12px; border-bottom:2px solid #000; }
    .quote-head img { height:50px; width:auto; }
    .quote-head-address { text-align:right; font-size:9px; line-height:1.5; color:#222; }
    .quote-title-bar { display:flex; justify-content:space-between; align-items:center; background:#f5f5f5; border-bottom:2px solid #000; padding:6px 14px; }
    .quote-title-bar .title { font-size:20px; font-weight:900; letter-spacing:5px; text-transform:uppercase; }
    .quote-title-bar .quote-no { font-size:14px; font-weight:900; color:#F7A100; letter-spacing:2px; }
    .status-badge-large { display:inline-block; padding:8px 20px; border-radius:20px; font-weight:900; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-left:15px; }
    .status-pending  { background:#fff4e5; color:#d97706; }
    .status-approved { background:#e8f5e8; color:#2e7d32; }
    .status-rejected { background:#ffebee; color:#c62828; }
    .quote-body { display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000; }
    .quote-col-left  { border-right:2px solid #000; padding:8px 12px; }
    .quote-col-right { padding:8px 12px; }
    .quote-field { display:flex; align-items:flex-end; margin-bottom:7px; gap:6px; }
    .quote-field label { font-size:9px; font-weight:700; text-transform:uppercase; white-space:nowrap; color:#333; min-width:110px; padding-bottom:3px; letter-spacing:0.3px; }
    .quote-field .value { flex:1; border-bottom:1.5px solid #000; padding:3px 5px; font-size:12px; color:#111; min-height:20px; font-weight:600; }
    .quote-section { text-align:center; font-size:10px; font-weight:900; letter-spacing:4px; text-transform:uppercase; background:#f0f0f0; border-top:2px solid #000; border-bottom:1px solid #000; padding:4px 0; }
    .items-table { width:100%; border-collapse:collapse; margin:0; }
    .items-table th { background:#EBF4FF; border:1px solid #EBF4FF; padding:5px 8px; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:1px; text-align:left; color:#2563EB; }
    .items-table td { border:1px solid #ccc; padding:4px 6px; min-height:22px; font-size:11px; color:#111; font-weight:600; vertical-align:top; }
    .items-table .amount-col { width:120px; text-align:right; }
    .total-box { background:#f0f0f0; border:2px solid #000; padding:12px 14px; text-align:right; font-size:16px; font-weight:900; color:#F7A100; }
    .quote-terms { border-top:2px solid #000; padding:6px 12px; font-size:7px; line-height:1.4; color:#555; background:#fafafa; }
    .quote-terms strong { font-size:7.5px; color:#222; }
    .signature-box { display:grid; grid-template-columns:1fr 1fr; gap:20px; padding:20px 12px; border-top:2px solid #000; }
    .signature-line { border-bottom:2px solid #000; margin-bottom:8px; padding-bottom:60px; }
    .stamp-box { border:2px dashed #999; height:80px; display:flex; align-items:center; justify-content:center; margin-bottom:8px; background:#fafafa; }
    
    /* Client Response Section */
    .client-response-section { max-width:900px; margin:30px auto; }
    .response-box { background:#fff; border-radius:12px; padding:24px; }
    .response-box.pending { border:3px solid #F7A100; }
    .response-box.approved { border:3px solid #10B981; background:#e8f5e8; }
    .response-box.rejected { border:3px solid #EF4444; background:#ffebee; }
    
    /* Responsive Quotation */
    @media (max-width: 1024px) {
        .quote-shell { max-width:100%; margin:0 10px 30px; }
        .client-response-section { max-width:100%; margin:20px 10px; }
    }
    
    @media (max-width: 768px) {
        /* Hide hamburger menu on quotation view */
        .mobile-menu-toggle { display: none !important; }
        
        .quote-shell { 
            max-width:100%; 
            margin:0 0 20px; 
            border-width:1px;
        }
        .quote-head { flex-direction: column; gap: 10px; padding: 10px; }
        .quote-head img { height:40px; }
        .quote-head-address { text-align:center; }
        .quote-title-bar { flex-direction: column; gap: 8px; text-align:center; }
        .quote-title-bar .title { font-size:16px; letter-spacing:3px; }
        .quote-title-bar .quote-no { font-size:12px; }
        .quote-body { grid-template-columns: 1fr; }
        .quote-col-left { border-right:none; border-bottom:2px solid #000; }
        .items-table { font-size:10px; }
        .items-table th, .items-table td { padding:4px; }
        .signature-box { grid-template-columns: 1fr; gap: 15px; padding: 15px; }
        .total-box { font-size:14px; }
        
        .client-response-section { max-width:100%; margin:15px 0; }
        .response-box { padding: 15px; }
    }
    
    @media (max-width: 480px) {
        .quote-title-bar .title { font-size:14px; letter-spacing:2px; }
        .status-badge-large { padding:6px 12px; font-size:10px; margin-left:0; margin-top:8px; }
        .items-table { font-size:9px; }
        .quote-field label { font-size:8px; min-width:90px; }
        .quote-field .value { font-size:11px; }
    }
    
    @media print {
        .erp-sidebar, .erp-header, .erp-page-header, .erp-btn, .client-response-section { display:none !important; }
        .quote-shell { max-width:100%; margin:0; border:2px solid #000; }
        @page { size:A4; margin:10mm; }
    }
</style>

<!-- ── PROFESSIONAL QUOTATION DOCUMENT ───────────────────────────────────── -->
<div class="quote-shell">
    <!-- Header with Logo and Address -->
    <div class="quote-head">
        <div>
            <?php if ($logoBase64): ?>
                <img src="<?php echo $logoBase64; ?>" alt="Logo">
            <?php endif; ?>
        </div>
        <div class="quote-head-address">
            <?php echo htmlspecialchars($business['address'] ?? 'Lafrenz Industrial • Rensburger Street • Erf 174LL • Unit 18'); ?><br>
            Cell: <?php echo htmlspecialchars($business['phone'] ?? ''); ?> •
            Email: <?php echo htmlspecialchars($business['email'] ?? ''); ?><br>
            PO Box 21292 • Windhoek • Namibia<br>
            Reg No: <?php echo htmlspecialchars($business['tax_number'] ?? ''); ?>
        </div>
    </div>

    <!-- Title Bar with Quotation Number and Status -->
    <div class="quote-title-bar">
        <div class="title">Quotation</div>
        <div class="quote-no">
            QTN-<?php echo str_pad($quotationId, 6, '0', STR_PAD_LEFT); ?>
            <?php 
            $statusClass = 'status-pending';
            $statusText = 'PENDING';
            if (isset($quote['status'])) {
                if ($quote['status'] === 'approved') {
                    $statusClass = 'status-approved';
                    $statusText = 'APPROVED';
                } elseif ($quote['status'] === 'rejected') {
                    $statusClass = 'status-rejected';
                    $statusText = 'REJECTED';
                }
            }
            ?>
            <span class="status-badge-large <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
        </div>
    </div>

    <!-- Client and Vehicle Info -->
    <div class="quote-body">
        <div class="quote-col-left">
            <div class="quote-field"><label>To</label><div class="value"><?php echo htmlspecialchars($quote['client_name'] ?? 'Walk-in Client'); ?></div></div>
            <div class="quote-field"><label>Address</label><div class="value"><?php echo htmlspecialchars($quote['client_address'] ?? '—'); ?></div></div>
            <div class="quote-field"><label>Contact No.</label><div class="value"><?php echo htmlspecialchars($quote['client_phone'] ?? '—'); ?></div></div>
            <div class="quote-field"><label>Email Address</label><div class="value"><?php echo htmlspecialchars($quote['client_email'] ?? '—'); ?></div></div>
        </div>
        <div class="quote-col-right">
            <div class="quote-field"><label>Date</label><div class="value"><?php echo isset($quote['submitted_at']) && $quote['submitted_at'] ? date('d M Y', strtotime($quote['submitted_at'])) : '—'; ?></div></div>
            <div class="quote-field"><label>Job Card No.</label><div class="value"><?php echo htmlspecialchars($quote['card_number'] ?? '—'); ?></div></div>
            <div class="quote-field"><label>Vehicle Reg. No.</label><div class="value"><?php echo htmlspecialchars($quote['reg_no'] ?? '—'); ?></div></div>
            <div class="quote-field"><label>Model</label><div class="value"><?php echo htmlspecialchars($quote['model'] ?? '—'); ?></div></div>
            <div class="quote-field"><label>VIN No.</label><div class="value"><?php echo htmlspecialchars($quote['vin_no'] ?? '—'); ?></div></div>
            <div class="quote-field"><label>Kilometre</label><div class="value"><?php echo htmlspecialchars($extra['kilometre'] ?? '—'); ?></div></div>
        </div>
    </div>

    <!-- Customer Complaint / Work Required -->
    <div class="quote-section">Customer Complaint / Work Required</div>
    <div style="padding:8px 12px;border-bottom:2px solid #000;">
        <div style="font-size:11px;line-height:1.6;color:#111;font-weight:600;min-height:40px;">
            <?php echo nl2br(htmlspecialchars($quote['job_description'] ?? 'No description provided.')); ?>
        </div>
    </div>

    <!-- Items & Services -->
    <div class="quote-section">Items &amp; Services</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:60px;">No.</th>
                <th>Description</th>
                <th class="amount-col">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td style="text-align:center;"><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($item['desc']); ?></td>
                    <td class="amount-col"><?php echo htmlspecialchars($item['amount']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="3" style="text-align:center;padding:20px;color:#999;">No items listed</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Total Amount -->
    <div class="total-box">
        TOTAL AMOUNT: <?php echo htmlspecialchars($currency['symbol'] ?? 'N$') . number_format((float)($quote['amount'] ?? 0), 2); ?>
    </div>

    <!-- Terms & Conditions -->
    <div class="quote-terms">
        <strong>Terms &amp; Conditions:</strong><br>
        • This quotation is valid for 30 days from the date of issue.<br>
        • <strong>Quotation Expiry Date: <?php echo isset($quote['submitted_at']) && $quote['submitted_at'] ? date('d M Y', strtotime($quote['submitted_at'] . ' +30 days')) : 'N/A'; ?></strong><br>
        • A 50% deposit is required before commencement of work.<br>
        • Prices are subject to change if additional work is required.<br>
        • All parts carry a 3-month warranty.<br>
        • Payment is due upon completion of work.<br>
        • Bank Details: <?php echo htmlspecialchars($business['bank_details'] ?? 'Available upon request'); ?>
    </div>

    <!-- Signature Section -->
    <div class="signature-box">
        <div style="text-align:center;">
            <div class="signature-line"></div>
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Authorized Signature</div>
            <div style="font-size:9px;color:#666;margin-top:4px;"><?php echo htmlspecialchars($business['name'] ?? 'SV Auto Services'); ?></div>
        </div>
        <div style="text-align:center;">
            <div class="stamp-box">
                <div style="font-size:9px;color:#999;text-transform:uppercase;letter-spacing:1px;">Company Stamp</div>
            </div>
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Official Seal</div>
        </div>
    </div>
</div>

<!-- ── CLIENT RESPONSE SECTION ─────────────────────────────────────────── -->
<div class="client-response-section">
    
    <?php if (!$clientStatus || $clientStatus === 'pending' || $clientStatus === null): ?>
        <!-- Pending: Show Accept/Reject Form -->
        <div class="response-box pending">
            <h3 style="color:#F7A100; font-size:20px; margin-bottom:15px; text-align:center;">
                <i class="fas fa-reply"></i> Your Response Required
            </h3>
            <p style="color:#6B7280; text-align:center; margin-bottom:20px;">
                Please review the quotation above and accept or reject it. You may add comments below.
            </p>
            
            <form method="POST">
                <div style="margin-bottom:20px;">
                    <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:8px;">
                        Response Notes (Optional)
                    </label>
                    <textarea name="response_notes" rows="4" 
                        style="width:100%; padding:12px; border:2px solid #E5E7EB; border-radius:8px; font-family:inherit; font-size:14px; resize:vertical;"
                        placeholder="Add any comments or questions about this quotation..."></textarea>
                </div>
                
                <div style="display:flex; gap:12px; justify-content:center;">
                    <button type="submit" name="action" value="accept" 
                        style="padding:15px 40px; background:#10B981; color:white; border:none; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-check"></i> ACCEPT
                    </button>
                    <button type="submit" name="action" value="reject" 
                        style="padding:15px 40px; background:#EF4444; color:white; border:none; border-radius:8px; font-weight:700; font-size:16px; cursor:pointer; display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-times"></i> REJECT
                    </button>
                </div>
            </form>
        </div>
        
    <?php elseif ($clientStatus === 'client_accepted'): ?>
        <!-- Client Accepted: Show Green Box -->
        <div class="response-box approved">
            <div style="text-align:center;">
                <div style="display:inline-block; background:#10B981; color:white; padding:15px 40px; border-radius:8px; font-weight:700; font-size:18px;">
                    <i class="fas fa-check-circle"></i> Client Response: ACCEPTED
                </div>
                <?php if ($clientResponseDate): ?>
                    <p style="color:#6B7280; margin-top:15px; font-size:14px;">
                        Response Date: <?php echo date('d F Y \a\t h:i A', strtotime($clientResponseDate)); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($clientResponseNotes): ?>
                <div style="margin-top:25px; padding:20px; background:rgba(255,255,255,0.7); border-radius:8px;">
                    <label style="display:block; font-size:12px; color:#6B7280; text-transform:uppercase; font-weight:700; margin-bottom:10px;">
                        Response Notes
                    </label>
                    <div style="font-size:15px; color:#374151; line-height:1.6;">
                        <?php echo nl2br(htmlspecialchars($clientResponseNotes)); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
    <?php elseif ($clientStatus === 'client_rejected'): ?>
        <!-- Client Rejected: Show Red Box -->
        <div class="response-box rejected">
            <div style="text-align:center;">
                <div style="display:inline-block; background:#EF4444; color:white; padding:15px 40px; border-radius:8px; font-weight:700; font-size:18px;">
                    <i class="fas fa-times-circle"></i> Client Response: REJECTED
                </div>
                <?php if ($clientResponseDate): ?>
                    <p style="color:#6B7280; margin-top:15px; font-size:14px;">
                        Response Date: <?php echo date('d F Y \a\t h:i A', strtotime($clientResponseDate)); ?>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($clientResponseNotes): ?>
                <div style="margin-top:25px; padding:20px; background:rgba(255,255,255,0.7); border-radius:8px;">
                    <label style="display:block; font-size:12px; color:#6B7280; text-transform:uppercase; font-weight:700; margin-bottom:10px;">
                        Response Notes
                    </label>
                    <div style="font-size:15px; color:#374151; line-height:1.6;">
                        <?php echo nl2br(htmlspecialchars($clientResponseNotes)); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
</div>


<script>
(function() {
    const PAGE_KEY = 'draft_' + window.location.pathname + window.location.search;
    const form = document.querySelector('form');
    if (!form) return;

    // Restore draft on page load
    const saved = localStorage.getItem(PAGE_KEY);
    if (saved) {
        try {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(name => {
                const fields = form.querySelectorAll(`[name="${name}"]`);
                fields.forEach(field => {
                    if (!field) return;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = data[name] === true || data[name] === field.value;
                    } else if (field.tagName === 'SELECT') {
                        field.value = data[name];
                        field.dispatchEvent(new Event('change'));
                    } else {
                        field.value = data[name];
                        field.dispatchEvent(new Event('input'));
                    }
                });
            });
            // Show restored notice
            const notice = document.createElement('div');
            notice.innerHTML = '📝 <strong>Draft restored</strong> — your unsaved changes have been recovered.';
            notice.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#F7A100; color:white; padding:14px 22px; border-radius:12px; font-size:14px; z-index:99999; box-shadow:0 6px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;';
            const closeBtn = document.createElement('span');
            closeBtn.textContent = '✕';
            closeBtn.style.cssText = 'cursor:pointer; margin-left:10px; font-size:16px; opacity:0.8;';
            closeBtn.onclick = () => notice.remove();
            notice.appendChild(closeBtn);
            document.body.appendChild(notice);
            setTimeout(() => notice.remove(), 5000);
        } catch(e) {}
    }

    // Auto-save on any input change
    let saveTimeout;
    function doSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const data = {};
            form.querySelectorAll('input, select, textarea').forEach(field => {
                if (!field.name) return;
                if (field.type === 'password' || field.type === 'file' || field.type === 'hidden') return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    data[field.name] = field.checked;
                } else {
                    data[field.name] = field.value;
                }
            });
            localStorage.setItem(PAGE_KEY, JSON.stringify(data));

            // Show saved indicator
            let indicator = document.getElementById('draft-save-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'draft-save-indicator';
                indicator.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#22C55E; color:white; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.15); opacity:0; transition:opacity 0.3s;';
                document.body.appendChild(indicator);
            }
            indicator.textContent = '✓ Draft saved';
            indicator.style.opacity = '1';
            clearTimeout(indicator._hideTimer);
            indicator._hideTimer = setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
        }, 800);
    }

    form.addEventListener('input', doSave);
    form.addEventListener('change', doSave);

    // Clear draft on successful form submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(PAGE_KEY);
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
