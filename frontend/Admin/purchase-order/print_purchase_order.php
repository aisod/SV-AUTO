<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: purchase_orders.php?error=Invalid Purchase Order');
    exit;
}

$po_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT 
        po.id, 
        po.amount, 
        po.supplier_name, 
        po.status, 
        po.created_at,
        po.details,
        po.notes,
        jc.card_number,
        c.name AS client_name,
        c.phone AS client_phone,
        c.address AS client_address,
        COALESCE(v.reg_no, 'Walk-in') AS reg_no,
        v.model,
        v.vin_no
    FROM purchase_orders po
    LEFT JOIN job_cards jc ON po.job_card_id = jc.id
    LEFT JOIN quotations q ON jc.id = q.job_card_id
    LEFT JOIN clients c ON q.client_id = c.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE po.id = ?
    LIMIT 1
");
$stmt->execute([$po_id]);
$po = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$po) {
    header('Location: purchase_orders.php?error=Purchase Order not found');
    exit;
}

$business = getBusiness();
$currency = getCurrency();
$symbol = $currency['symbol'] ?? 'N$';
$po_number = 'PO-' . str_pad($po['id'], 6, '0', STR_PAD_LEFT);

// Parse line items
$items = [];
$current_item = null;
foreach (explode("\n", $po['details'] ?? '') as $line) {
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

// Logo
$logo_base64 = '';
$logo_path = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logo_path)) {
    $logo_base64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $po_number ?> - Purchase Order</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:Arial,sans-serif; font-size:11px; color:#111; background:#fff; padding:20px; }
        .document { border:2px solid #000; max-width:900px; margin:0 auto; background:#fff; }
        
        /* Header */
        .header { display:flex; justify-content:space-between; align-items:flex-start; padding:15px 20px; border-bottom:2px solid #000; }
        .header img { height:60px; width:auto; }
        .header-center { flex:1; padding:0 20px; }
        .company-name { font-size:18px; font-weight:900; color:#F5A623; }
        .company-info { font-size:9px; color:#555; line-height:1.6; margin-top:3px; }
        .header-right { text-align:right; font-size:9px; line-height:1.6; color:#222; }
        
        /* Title Bar */
        .title-bar { display:flex; justify-content:space-between; align-items:center; background:#f5f5f5; border-bottom:2px solid #000; padding:10px 20px; }
        .doc-title { font-size:22px; font-weight:900; letter-spacing:5px; }
        .doc-number { font-size:14px; font-weight:900; color: #4a4a4a; }
        .status-badge { display:inline-block; padding:4px 12px; border-radius:12px; font-size:9px; font-weight:700; margin-left:10px; }
        .badge-pending { background:#fff4e5; color:#d97706; border:1.5px solid #d97706; }
        .badge-approved { background:#e8f5e8; color:#2e7d32; border:1.5px solid #2e7d32; }
        
        /* Info Grid */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000; }
        .info-col { padding:15px 20px; }
        .info-col:first-child { border-right:2px solid #000; }
        .section-title { font-size:9px; font-weight:900; color: #4a4a4a; text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid #ddd; padding-bottom:5px; margin-bottom:10px; }
        .info-row { margin-bottom:8px; display:flex; }
        .info-label { font-size:9px; font-weight:700; text-transform:uppercase; color:#555; min-width:100px; }
        .info-value { font-size:10px; font-weight:600; color:#111; flex:1; border-bottom:1px solid #ddd; padding:2px 5px; }
        
        /* Items Section */
        .section-header { text-align:center; font-size:10px; font-weight:900; letter-spacing:4px; text-transform:uppercase; background:#f0f0f0; border-top:2px solid #000; border-bottom:1px solid #000; padding:5px 0; }
        .items-table { width:100%; border-collapse:collapse; }
        .items-table th { background:#EBF4FF; border:1px solid #EBF4FF; padding:8px; font-size:9px; font-weight:600; text-transform:uppercase; text-align:left; color:#2563EB; }
        .items-table td { border:1px solid #ccc; padding:8px; font-size:10px; font-weight:600; vertical-align:top; }
        .items-table .no-col { width:40px; text-align:center; }
        .items-table .amt-col { width:120px; text-align:right; }
        
        /* Total */
        .total-row { background:#f0f0f0; border-top:2px solid #000; border-bottom:2px solid #000; padding:12px 20px; text-align:right; font-size:16px; font-weight:900; color: #4a4a4a; }
        
        /* Notes */
        .notes-section { padding:12px 20px; border-bottom:2px solid #000; font-size:10px; background:#FFF8F0; }
        .notes-section strong { color: #4a4a4a; }
        
        /* Signature */
        .signature-row { display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000; }
        .sig-col { padding:20px; text-align:center; }
        .sig-col:first-child { border-right:2px solid #000; }
        .sig-line { border-bottom:2px solid #000; margin-bottom:10px; padding-bottom:50px; }
        .sig-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:1px; }
        .stamp-box { border:2px dashed #999; height:60px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; background:#fafafa; }
        .stamp-text { font-size:8px; color:#aaa; text-transform:uppercase; }
        
        /* Footer */
        .footer { text-align:center; padding:10px; font-size:8px; color:#666; background:#fafafa; line-height:1.8; }
        
        @media print {
            body { padding:0; }
            .no-print { display:none; }
        }
    </style>
</head>
<body>

<div class="document">
    <!-- Header -->
    <div class="header">
        <div>
            <?php if ($logo_base64): ?>
                <img src="<?= $logo_base64 ?>" alt="Logo">
            <?php endif; ?>
        </div>
        <div class="header-center">
            <div class="company-name"><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></div>
            <div class="company-info">
                <?= htmlspecialchars($business['address'] ?? '') ?><br>
                <?= htmlspecialchars($business['phone'] ?? '') ?>
                <?= !empty($business['email']) ? ' | ' . htmlspecialchars($business['email']) : '' ?>
                <?php if (!empty($business['tax_number'])): ?><br>Tax No: <?= htmlspecialchars($business['tax_number']) ?><?php endif; ?>
            </div>
        </div>
        <div class="header-right">
            <?= htmlspecialchars($business['address'] ?? '') ?><br>
            Cell: <?= htmlspecialchars($business['phone'] ?? '') ?><br>
            <?= !empty($business['email']) ? htmlspecialchars($business['email']) . '<br>' : '' ?>
            <?= !empty($business['tax_number']) ? 'Reg No: ' . htmlspecialchars($business['tax_number']) : '' ?>
        </div>
    </div>

    <!-- Title Bar -->
    <div class="title-bar">
        <div class="doc-title">PURCHASE ORDER</div>
        <div class="doc-number">
            <?= $po_number ?>
            <span class="status-badge badge-<?= $po['status'] ?>">
                <?= strtoupper($po['status']) ?>
            </span>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="info-grid">
        <div class="info-col">
            <div class="section-title">Supplier Information</div>
            <div class="info-row">
                <div class="info-label">Supplier</div>
                <div class="info-value"><?= htmlspecialchars($po['supplier_name']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">PO Date</div>
                <div class="info-value"><?= date('d M Y', strtotime($po['created_at'])) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Job Card</div>
                <div class="info-value"><?= htmlspecialchars($po['card_number'] ?? '—') ?></div>
            </div>
        </div>
        <div class="info-col">
            <div class="section-title">Vehicle & Client Details</div>
            <div class="info-row">
                <div class="info-label">Client</div>
                <div class="info-value"><?= htmlspecialchars($po['client_name'] ?? 'Walk-in') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Phone</div>
                <div class="info-value"><?= htmlspecialchars($po['client_phone'] ?? '—') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Address</div>
                <div class="info-value"><?= htmlspecialchars($po['client_address'] ?? '—') ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Vehicle Reg.</div>
                <div class="info-value"><?= htmlspecialchars($po['reg_no']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Model</div>
                <div class="info-value"><?= htmlspecialchars($po['model'] ?? '—') ?></div>
            </div>
            <?php if (!empty($po['vin_no'])): ?>
            <div class="info-row">
                <div class="info-label">VIN No.</div>
                <div class="info-value"><?= htmlspecialchars($po['vin_no']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items Section -->
    <div class="section-header">Items & Services</div>
    <table class="items-table">
        <thead>
            <tr>
                <th class="no-col">No.</th>
                <th>Description</th>
                <th class="amt-col">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): ?>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td class="no-col"><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['desc']) ?></td>
                    <td class="amt-col"><?= htmlspecialchars($item['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align:center;padding:20px;color:#999;font-style:italic;">
                        Parts and services as per approved quotation
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Total -->
    <div class="total-row">
        TOTAL AMOUNT: <?= $symbol . number_format((float)$po['amount'], 2) ?>
    </div>

    <!-- Notes -->
    <?php if (!empty($po['notes'])): ?>
    <div class="notes-section">
        <strong>Notes:</strong> <?= nl2br(htmlspecialchars($po['notes'])) ?>
    </div>
    <?php endif; ?>

    <!-- Signature Row -->
    <div class="signature-row">
        <div class="sig-col">
            <div class="sig-line"></div>
            <div class="sig-label">Authorized Signature</div>
            <div style="font-size:8px;color:#666;margin-top:3px;"><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></div>
        </div>
        <div class="sig-col">
            <div class="stamp-box">
                <span class="stamp-text">Company Stamp</span>
            </div>
            <div class="sig-label">Official Seal</div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <strong><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></strong> &bull;
        <?= htmlspecialchars($business['address'] ?? '') ?>
        <?= !empty($business['phone']) ? ' &bull; ' . htmlspecialchars($business['phone']) : '' ?>
        <?= !empty($business['email']) ? ' &bull; ' . htmlspecialchars($business['email']) : '' ?>
        <?php if (!empty($business['tax_number'])): ?> &bull; Tax No: <?= htmlspecialchars($business['tax_number']) ?><?php endif; ?><br>
        <span style="font-size:7px;opacity:0.6;">Generated <?= date('d F Y \a\t H:i') ?> &mdash; SV Auto Management System</span>
    </div>
</div>

<div class="no-print" style="text-align:center;margin-top:20px;">
    <button onclick="window.print()" style="padding:12px 30px;background:#2e7d32;color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin:0 5px;">
        <i class="fas fa-print"></i> Print / PDF
    </button>
    <button onclick="window.history.back()" style="padding:12px 30px;background:#666;color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;margin:0 5px;">
        <i class="fas fa-arrow-left"></i> Back
    </button>
</div>

</body>
</html>

