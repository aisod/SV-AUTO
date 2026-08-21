<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$service_id = (int)($_GET['id'] ?? 0);
if ($service_id <= 0) die('Invalid Service ID');

$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.service_date,
        s.status,
        s.notes,
        c.name AS client_name,
        c.phone AS client_phone,
        c.email AS client_email,
        c.address AS client_address,
        v.reg_no,
        v.model,
        v.vin_no,
        v.last_service_date
    FROM services s
    JOIN vehicles v ON s.vehicle_id = v.id
    JOIN clients c ON v.client_id = c.id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) die('Service Schedule Not Found');

$business = getBusiness();
$service_number = 'SVC-' . str_pad($service['id'], 4, '0', STR_PAD_LEFT);

// Logo as base64
$logo_base64 = '';
$logo_path = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logo_path)) {
    $logo_data = base64_encode(file_get_contents($logo_path));
    $logo_base64 = 'data:image/jpeg;base64,' . $logo_data;
}

$clean_notes = strip_tags($service['notes'] ?? '');

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
        font-size: 10px;
        color: #333;
        background: #fff;
        padding: 20px 25px;
    }

    /* HEADER */
    .header {
        background: #F5A623;
        color: white;
        padding: 15px 20px;
        text-align: center;
        position: relative;
        border-radius: 8px;
        margin-bottom: 15px;
    }
    .logo {
    position: absolute;
    top: 12px;
    left: 18px;
    max-width: 50px;
    max-height: 50px;
    width: auto;
    height: auto;
    border-radius: 6px;
    border: 2px solid white;
    background: white;
    padding: 4px;
}
    .company-name {
        font-size: 18px;
        font-weight: 900;
        margin: 0;
        letter-spacing: 1.5px;
    }
    .slogan {
        font-size: 10px;
        opacity: 0.9;
        margin-top: 2px;
    }

    /* SERVICE NUMBER */
    .service-number-section {
        text-align: center;
        margin: 15px 0;
    }
    .service-number {
        background: white;
        color: #4a4a4a;
        padding: 10px 30px;
        border-radius: 30px;
        font-size: 16px;
        font-weight: 900;
        border: 3px solid #F5A623;
        display: inline-block;
    }

    /* GRID */
    .grid {
        width: 100%;
        margin-bottom: 12px;
    }
    .grid-cell {
        width: 48%;
        vertical-align: top;
        background: #FFF8F0;
        border-left: 3px solid #F5A623;
        padding: 10px;
        border-radius: 4px;
    }
    .grid-cell h3 {
        font-size: 11px;
        color: #4a4a4a;
        border-bottom: 2px solid #F5A623;
        padding-bottom: 4px;
        margin-bottom: 8px;
        font-weight: 700;
    }
    .grid-row {
        font-size: 9px;
        padding: 3px 0;
    }
    .grid-label {
        font-weight: 700;
        color: #4a4a4a;
        display: inline-block;
        width: 90px;
    }
    .grid-value {
        font-weight: 500;
        color: #333;
    }
    .highlight {
        color: #F5A623;
        font-weight: 700;
    }

    /* STATUS BADGE */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 9px;
        font-weight: 700;
    }
    .status-pending {
        background: #fff4e5;
        color: #d97706;
    }
    .status-approved {
        background: #e8f5e8;
        color: #2e7d32;
    }

    /* NOTES BOX */
    .notes-box {
        background: #FFF8F0;
        padding: 10px;
        border-radius: 6px;
        border: 2px dashed #FFF8EC;
        font-size: 9px;
        line-height: 1.5;
        margin: 12px 0;
        white-space: pre-wrap;
    }

    /* SCHEDULE BOX */
    .schedule-box {
        background: #FFF8F0;
        border: 2px solid #F5A623;
        border-radius: 8px;
        padding: 12px;
        margin: 12px 0;
        text-align: center;
    }
    .schedule-date {
        font-size: 16px;
        font-weight: 900;
        color: #F5A623;
        margin-bottom: 8px;
    }

    /* FOOTER */
    .footer {
        background: #4a4a4a;
        color: white;
        text-align: center;
        padding: 10px;
        font-size: 8px;
        line-height: 1.5;
        border-radius: 6px;
        margin-top: 12px;
    }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <?php if ($logo_base64): ?>
        <img src="<?= $logo_base64 ?>" class="logo" alt="Logo">
    <?php endif; ?>
    <div class="company-name"><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></div>
    <div class="slogan"><?= htmlspecialchars($business['slogan'] ?? 'Precision • Luxury • Trust') ?></div>
</div>

<!-- SERVICE NUMBER -->
<div class="service-number-section">
    <div class="service-number"><?= $service_number ?></div>
    <div style="font-size:12px;font-weight:700;color: #4a4a4a;margin-top:8px;">SERVICE SCHEDULE</div>
</div>

<!-- CLIENT & VEHICLE -->
<table class="grid">
    <tr>
        <td class="grid-cell">
            <h3>Client Information</h3>
            <div class="grid-row">
                <span class="grid-label">Client Name</span>
                <span class="grid-value highlight"><?= htmlspecialchars($service['client_name']) ?></span>
            </div>
            <div class="grid-row">
                <span class="grid-label">Phone</span>
                <span class="grid-value"><?= htmlspecialchars($service['client_phone'] ?? '—') ?></span>
            </div>
            <div class="grid-row">
                <span class="grid-label">Email</span>
                <span class="grid-value"><?= htmlspecialchars($service['client_email'] ?? '—') ?></span>
            </div>
            <div class="grid-row">
                <span class="grid-label">Address</span>
                <span class="grid-value"><?= htmlspecialchars($service['client_address'] ?? '—') ?></span>
            </div>
        </td>
        <td style="width:4%;"></td>
        <td class="grid-cell">
            <h3>Vehicle Information</h3>
            <div class="grid-row">
                <span class="grid-label">Registration</span>
                <span class="grid-value highlight"><?= htmlspecialchars($service['reg_no']) ?></span>
            </div>
            <div class="grid-row">
                <span class="grid-label">Model</span>
                <span class="grid-value"><?= htmlspecialchars($service['model']) ?></span>
            </div>
            <div class="grid-row">
                <span class="grid-label">VIN Number</span>
                <span class="grid-value"><?= htmlspecialchars($service['vin_no'] ?? '—') ?></span>
            </div>
            <div class="grid-row">
                <span class="grid-label">Last Service</span>
                <span class="grid-value"><?= $service['last_service_date'] ? date('d M Y', strtotime($service['last_service_date'])) : 'Never recorded' ?></span>
            </div>
        </td>
    </tr>
</table>

<!-- SCHEDULE DETAILS -->
<div class="schedule-box">
    <div class="schedule-date"><?= date('l, d F Y', strtotime($service['service_date'])) ?></div>
    <span class="status-badge status-<?= $service['status'] ?>"><?= ucfirst($service['status']) ?></span>
</div>

<!-- NOTES -->
<?php if (!empty($clean_notes)): ?>
<h3 style="font-size:11px;color: #4a4a4a;margin:12px 0 6px;font-weight:700;">Recommended Services / Notes</h3>
<div class="notes-box"><?= nl2br(htmlspecialchars($clean_notes)) ?></div>
<?php endif; ?>

<!-- FOOTER -->
<div class="footer">
    <strong><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></strong><br>
    <?= htmlspecialchars($business['address'] ?? '') ?> • <?= htmlspecialchars($business['phone'] ?? '') ?> • <?= htmlspecialchars($business['email'] ?? '') ?>
    <?php if (!empty($business['tax_number'])): ?> • VAT: <?= htmlspecialchars($business['tax_number']) ?><?php endif; ?>
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

$filename = $service_number . '-Service-Schedule.pdf';

if (isset($_GET['pdf']) && $_GET['pdf'] == 1) {
    $dompdf->stream($filename, ['Attachment' => true]);
} else {
    $dompdf->stream($filename, ['Attachment' => false]);
}
exit;
?>

