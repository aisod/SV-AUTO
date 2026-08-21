<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$employee_id = (int)($_GET['id'] ?? 0);
if ($employee_id <= 0) die('Invalid Employee ID');

$stmt = $pdo->prepare("
    SELECT id, name, position, status, certificates, licenses, ids, uniforms, photo_url, event_locations 
    FROM employees 
    WHERE id = ? 
    LIMIT 1
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) die('Employee Not Found');

$business = getBusiness();
$employee_number = 'EMP-' . str_pad($employee['id'], 4, '0', STR_PAD_LEFT);

// Logo as base64
$logo_base64 = '';
$logo_path = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logo_path)) {
    $logo_data = base64_encode(file_get_contents($logo_path));
    $logo_base64 = 'data:image/jpeg;base64,' . $logo_data;
}

// Employee photo as base64 (if exists)
$photo_base64 = '';
if (!empty($employee['photo_url'])) {
    $photo_full_path = __DIR__ . '/../' . $employee['photo_url'];
    if (file_exists($photo_full_path)) {
        $photo_data = base64_encode(file_get_contents($photo_full_path));
        $photo_base64 = 'data:image/jpeg;base64,' . $photo_data;
    }
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= $employee_number ?> - Employee Profile</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10px;
        color: #000;
        background: #fff;
        padding: 15px 20px;
        line-height: 1.3;
    }
    
    /* HEADER */
    .header {
        padding: 12px 15px;
        margin-bottom: 15px;
        border-bottom: 2px solid #F5A623;
        position: relative;
        text-align: center;
        min-height: 60px;
    }
    .logo {
        position: absolute;
        left: 15px;
        top: 8px;
        max-width: 70px;
        max-height: 50px;
        width: auto;
        height: auto;
    }
    .company-name {
        font-size: 20px;
        font-weight: 900;
        margin: 0;
        letter-spacing: 1.5px;
        color: #000;
        padding-top: 5px;
        text-transform: uppercase;
    }
    
    /* EMPLOYEE TITLE */
    .employee-title-section {
        margin: 12px 0 10px 0;
        text-align: center;
    }
    .employee-title {
        font-size: 16px;
        color: #000;
        margin: 0;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .employee-number {
        font-size: 11px;
        color: #4a4a4a;
        margin-top: 3px;
        font-weight: bold;
    }
    
    /* PHOTO SECTION */
    .photo-section {
        text-align: center;
        margin: 12px 0;
    }
    .employee-photo {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid #F5A623;
        object-fit: cover;
    }
    .photo-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 3px solid #F5A623;
        background: #FFF8EC;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        font-weight: bold;
        color: white;
    }
    
    /* NAME & POSITION */
    .name-position {
        text-align: center;
        margin: 10px 0;
    }
    .employee-name {
        font-size: 14px;
        font-weight: bold;
        color: #000;
        margin-bottom: 3px;
    }
    .employee-position {
        font-size: 11px;
        color: #4a4a4a;
        font-weight: bold;
    }
    
    /* STATUS BADGE */
    .status-section {
        text-align: center;
        margin: 10px 0 15px 0;
    }
    .status-badge {
        display: inline-block;
        padding: 6px 20px;
        border-radius: 15px;
        font-size: 9px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .status-active { background: #e8f5e8; color: #1b5e20; border: 2px solid #4caf50; }
    .status-on_leave { background: #fff4e5; color: #e65100; border: 2px solid #FFF8EC; }
    .status-on_field { background: #e3f2fd; color: #1565c0; border: 2px solid #42a5f5; }
    
    /* INFO SECTIONS - TWO COLUMNS */
    .info-grid {
        display: table;
        width: 100%;
        margin: 10px 0;
    }
    .info-box {
        display: table-cell;
        width: 48%;
        background: #FFF8F0;
        padding: 12px;
        border-left: 3px solid #F5A623;
        vertical-align: top;
    }
    .info-box:first-child {
        margin-right: 4%;
    }
    .info-box h3 {
        font-size: 10px;
        color: #4a4a4a;
        margin-bottom: 8px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .info-row {
        margin-bottom: 8px;
        padding-bottom: 6px;
        border-bottom: 1px solid #eee;
    }
    .info-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .info-label {
        font-weight: bold;
        color: #000;
        display: block;
        margin-bottom: 2px;
        font-size: 9px;
    }
    .info-value {
        color: #333;
        font-size: 9px;
        line-height: 1.4;
    }
    
    /* FOOTER */
    .footer {
        background: #4a4a4a;
        color: white;
        text-align: center;
        padding: 8px;
        font-size: 7px;
        line-height: 1.5;
        margin-top: 15px;
        border-top: 2px solid #F5A623;
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
</div>

<!-- EMPLOYEE TITLE -->
<div class="employee-title-section">
    <div class="employee-title">EMPLOYEE PROFILE</div>
    <div class="employee-number"><?= $employee_number ?></div>
</div>

<!-- PHOTO -->
<div class="photo-section">
    <?php if ($photo_base64): ?>
        <img src="<?= $photo_base64 ?>" class="employee-photo" alt="Employee Photo">
    <?php else: ?>
        <div class="photo-placeholder">
            <?= strtoupper(substr($employee['name'], 0, 2)) ?>
        </div>
    <?php endif; ?>
</div>

<!-- NAME & POSITION -->
<div class="name-position">
    <div class="employee-name">
        <?= htmlspecialchars($employee['name']) ?>
    </div>
    <div class="employee-position">
        <?= htmlspecialchars($employee['position']) ?>
    </div>
</div>

<!-- STATUS -->
<div class="status-section">
    <span class="status-badge status-<?= $employee['status'] ?>">
        <?= ucwords(str_replace('_', ' ', $employee['status'])) ?>
    </span>
</div>

<!-- INFO SECTIONS - TWO COLUMNS -->
<div class="info-grid">
    <!-- Professional Details -->
    <div class="info-box" style="padding-right:2%;">
        <h3>Professional Details</h3>
        <div class="info-row">
            <div class="info-label">Certificates</div>
            <div class="info-value"><?= nl2br(htmlspecialchars($employee['certificates'] ?: 'Not specified')) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Licenses</div>
            <div class="info-value"><?= nl2br(htmlspecialchars($employee['licenses'] ?: 'Not specified')) ?></div>
        </div>
    </div>
    
    <!-- Administrative Info -->
    <div class="info-box" style="padding-left:2%;">
        <h3>Administrative Information</h3>
        <div class="info-row">
            <div class="info-label">ID Numbers</div>
            <div class="info-value"><?= nl2br(htmlspecialchars($employee['ids'] ?: 'Not specified')) ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Uniform Size</div>
            <div class="info-value"><?= htmlspecialchars($employee['uniforms'] ?: 'Not specified') ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Event Locations</div>
            <div class="info-value"><?= nl2br(htmlspecialchars($employee['event_locations'] ?: 'Not specified')) ?></div>
        </div>
    </div>
</div>

<!-- FOOTER -->
<div class="footer">
    <strong><?= htmlspecialchars($business['name']) ?></strong><br>
    <?= htmlspecialchars($business['address']) ?> • <?= htmlspecialchars($business['phone']) ?> • <?= htmlspecialchars($business['email']) ?>
    <?php if (!empty($business['tax_number'])): ?> • VAT: <?= htmlspecialchars($business['tax_number']) ?><?php endif; ?><br>
    Generated on <?= date('d M Y H:i') ?>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// Generate PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = $employee_number . '-' . str_replace(' ', '-', $employee['name']) . '-Profile.pdf';
$dompdf->stream($filename, ['Attachment' => false]);

