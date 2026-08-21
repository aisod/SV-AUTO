<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../Auth/auth.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_role(['admin', 'manager']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: quotations.php?error=Invalid Quotation ID');
    exit;
}

$quote_id = (int)$_GET['id'];
$business = getBusiness();
$currency = getCurrency();
$currency_symbol = $currency['symbol'] ?? 'N$';

// Load quotation
$stmt = $pdo->prepare("
    SELECT 
        q.*,
        jc.card_number,
        c.name    AS client_name,
        c.phone   AS client_phone,
        c.email   AS client_email,
        c.address AS client_address,
        v.reg_no,
        v.model,
        v.vin_no
    FROM quotations q
    LEFT JOIN job_cards jc ON q.job_card_id = jc.id
    LEFT JOIN vehicles  v  ON jc.vehicle_id = v.id
    LEFT JOIN clients   c  ON q.client_id   = c.id
    WHERE q.id = ?
    LIMIT 1
");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header('Location: quotations.php?error=Quotation not found');
    exit;
}

$quote_number = 'QTN-' . str_pad($quote['id'], 6, '0', STR_PAD_LEFT);

// Logo as base64
$logo_base64 = '';
$logo_path   = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logo_path)) {
    $logo_data   = base64_encode(file_get_contents($logo_path));
    $logo_base64 = 'data:image/jpeg;base64,' . $logo_data;
}

// Clean details — strip HTML tags
$clean_details = strip_tags($quote['details'] ?? '');

// Status colors
$status_colors = [
    'pending'  => '#d97706',
    'approved' => '#2e7d32',
    'rejected' => '#c62828',
];
$status_color = $status_colors[$quote['status']] ?? '#d97706';

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #333;
        background: #fff;
        padding: 25px 30px;
    }

    /* ── HEADER ── */
    .header-table { width:100%; margin-bottom:8px; }
    .logo-cell { width:90px; vertical-align:middle; }
    .logo-cell img {
        max-width: 70px;
        max-height: 70px;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 6px;
    }
    .company-cell { vertical-align:middle; padding-left:12px; }
    .company-name {
        font-size: 18px;
        font-weight: 900;
        color: #F5A623;
        margin-bottom: 3px;
    }
    .company-sub {
        font-size: 9px;
        color: #888;
        line-height: 1.6;
    }
    .quote-cell {
        vertical-align: middle;
        text-align: right;
        width: 180px;
    }
    .quote-number {
        font-size: 16px;
        font-weight: 900;
        color: #4a4a4a;
    }
    .status-badge {
        display: inline-block;
        margin-top: 5px;
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 700;
        color: <?= $status_color ?>;
        border: 2px solid <?= $status_color ?>;
    }

    /* ── DIVIDER ── */
    .divider {
        border: none;
        border-top: 2px solid #F5A623;
        margin: 8px 0;
    }
    .divider-thin {
        border: none;
        border-top: 1px solid #FFF8EC;
        margin: 8px 0;
    }

    /* ── SECTION TITLE ── */
    .section-title {
        font-size: 11px;
        font-weight: 900;
        color: #F5A623;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    /* ── INFO TABLE ── */
    .info-table { width:100%; margin-bottom:12px; }
    .info-box {
        width: 48%;
        vertical-align: top;
        background: #FFF8F0;
        border-left: 4px solid #F5A623;
        padding: 10px 12px;
        border-radius: 4px;
    }
    .info-box h3 {
        font-size: 9px;
        font-weight: 900;
        color: #4a4a4a;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid #FFF8EC;
        padding-bottom: 4px;
        margin-bottom: 6px;
    }
    .info-row {
        font-size: 10px;
        color: #444;
        margin-bottom: 3px;
        line-height: 1.5;
    }
    .info-label {
        font-weight: 700;
        color: #4a4a4a;
        display: inline-block;
        width: 65px;
    }

    /* ── DETAILS BOX ── */
    .details-box {
        background: #f9f9f9;
        border: 1px dashed #FFF8EC;
        border-radius: 4px;
        padding: 10px 14px;
        font-size: 10px;
        line-height: 1.8;
        color: #444;
        white-space: pre-wrap;
        margin-bottom: 12px;
        max-height: 180px;
        overflow: hidden;
    }

    /* ── TOTAL ── */
    .total-row {
        width: 100%;
        margin-bottom: 12px;
    }
    .total-box {
        background: #F5A623;
        color: white;
        border-radius: 6px;
        padding: 12px 18px;
        text-align: right;
        width: 280px;
        float: right;
    }
    .total-label {
        font-size: 9px;
        opacity: 0.85;
        margin-bottom: 2px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .total-amount {
        font-size: 20px;
        font-weight: 900;
    }
    .clearfix { clear: both; }

    /* ── META ── */
    .meta-table { width:100%; margin-bottom:12px; }
    .meta-box {
        width: 30%;
        text-align: center;
        background: #FFF8F0;
        border: 1px solid #FFF8EC;
        border-radius: 4px;
        padding: 8px;
        vertical-align: top;
    }
    .meta-label {
        font-size: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #4a4a4a;
        font-weight: 700;
        margin-bottom: 3px;
    }
    .meta-value {
        font-size: 11px;
        font-weight: 700;
        color: #333;
    }

    /* ── FOOTER ── */
    .footer {
        border-top: 1px solid #FFF8EC;
        padding-top: 8px;
        text-align: center;
        font-size: 8px;
        color: #888;
        line-height: 1.7;
    }
    .footer strong {
        color: #4a4a4a;
        font-size: 9px;
    }

    /* ── WATERMARK ── */
    .watermark {
        position: fixed;
        top: 35%;
        left: 5%;
        font-size: 90px;
        font-weight: 900;
        color: rgba(198,40,40,0.06);
        transform: rotate(-35deg);
        letter-spacing: 6px;
    }
    .watermark-approved {
        color: rgba(46,125,50,0.06);
    }
</style>
</head>
<body>

<?php if ($quote['status'] === 'rejected'): ?>
    <div class="watermark">REJECTED</div>
<?php elseif ($quote['status'] === 'approved'): ?>
    <div class="watermark watermark-approved">APPROVED</div>
<?php endif; ?>

<!-- HEADER -->
<table class="header-table">
    <tr>
        <td class="logo-cell">
            <?php if ($logo_base64): ?>
                <img src="<?= $logo_base64 ?>" alt="Logo">
            <?php endif; ?>
        </td>
        <td class="company-cell">
            <div class="company-name"><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></div>
            <div class="company-sub">
                <?= htmlspecialchars($business['address'] ?? 'Windhoek, Namibia') ?><br>
                <?= htmlspecialchars($business['phone'] ?? '') ?>
                <?= !empty($business['email']) ? ' | ' . htmlspecialchars($business['email']) : '' ?><br>
                <?php if (!empty($business['tax_number'])): ?>
                    Tax No: <?= htmlspecialchars($business['tax_number']) ?>
                <?php endif; ?>
            </div>
        </td>
        <td class="quote-cell">
    <div class="quote-number"><?= $quote_number ?></div>
</td>
    </tr>
</table>

<hr class="divider">

<!-- CLIENT & VEHICLE -->
<table class="info-table">
    <tr>
        <td class="info-box">
            <h3>Client Information</h3>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <?= htmlspecialchars($quote['client_name'] ?? 'Walk-in Client') ?>
            </div>
            <?php if (!empty($quote['client_phone'])): ?>
            <div class="info-row">
                <span class="info-label">Phone:</span>
                <?= htmlspecialchars($quote['client_phone']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($quote['client_email'])): ?>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <?= htmlspecialchars($quote['client_email']) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($quote['client_address'])): ?>
            <div class="info-row">
                <span class="info-label">Address:</span>
                <?= htmlspecialchars($quote['client_address']) ?>
            </div>
            <?php endif; ?>
        </td>
        <td style="width:4%;"></td>
        <td class="info-box">
            <h3>Vehicle Information</h3>
            <div class="info-row">
                <span class="info-label">Reg No:</span>
                <?= htmlspecialchars($quote['reg_no'] ?? '—') ?>
            </div>
            <div class="info-row">
                <span class="info-label">Model:</span>
                <?= htmlspecialchars($quote['model'] ?? '—') ?>
            </div>
            <?php if (!empty($quote['vin_no'])): ?>
            <div class="info-row">
                <span class="info-label">VIN:</span>
                <?= htmlspecialchars($quote['vin_no']) ?>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <span class="info-label">Job Card:</span>
                <?= htmlspecialchars($quote['card_number'] ?? '—') ?>
            </div>
        </td>
    </tr>
</table>

<hr class="divider-thin">

<!-- QUOTATION DETAILS -->
<div class="section-title">Quotation Details — Parts, Labour & Services</div>
<div class="details-box"><?= nl2br(htmlspecialchars($clean_details)) ?></div>

<!-- TOTAL -->
<div class="total-box">
    <div class="total-label">Total Amount</div>
    <div class="total-amount"><?= $currency_symbol . number_format((float)$quote['amount'], 2) ?></div>
</div>
<div class="clearfix"></div>

<hr class="divider-thin">

<!-- META -->
<table class="meta-table">
    <tr>
        <td class="meta-box">
            <div class="meta-label">Date Created</div>
            <div class="meta-value">
                <?= $quote['submitted_at'] ? date('d F Y', strtotime($quote['submitted_at'])) : '—' ?>
            </div>
        </td>
        <td style="width:5%;"></td>
        <td class="meta-box">
            <div class="meta-label">Valid For</div>
            <div class="meta-value">30 Days</div>
        </td>
        <td style="width:5%;"></td>
        <td class="meta-box">
            <div class="meta-label">Status</div>
            <div class="meta-value" style="color:<?= $status_color ?>;">
                <?= ucfirst($quote['status']) ?>
            </div>
        </td>
    </tr>
</table>

<!-- FOOTER -->
<div class="footer">
    <strong><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></strong><br>
    <?= htmlspecialchars($business['address'] ?? '') ?>
    <?= !empty($business['phone']) ? ' | ' . htmlspecialchars($business['phone']) : '' ?>
    <?= !empty($business['email']) ? ' | ' . htmlspecialchars($business['email']) : '' ?><br>
    <?php if (!empty($business['tax_number'])): ?>
        Tax Registration No: <?= htmlspecialchars($business['tax_number']) ?><br>
    <?php endif; ?>
    <span style="font-size:7px;opacity:0.6;">
        Generated on <?= date('d F Y \a\t H:i') ?> — SV Auto Management System
    </span>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = $quote_number . '-Quotation.pdf';

if (isset($_GET['pdf']) && $_GET['pdf'] == 1) {
    $dompdf->stream($filename, ['Attachment' => true]);
} else {
    $dompdf->stream($filename, ['Attachment' => false]);
}
exit;
?>


