<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$job_card_id = (int)($_GET['id'] ?? 0);
if ($job_card_id <= 0) die('<h2 style="text-align:center;color:#c62828;margin:100px;">Invalid Job Card</h2>');

$business = getBusiness();
$logoUrl = !empty($business['logo_url']) ? '../uploads/logos/' . $business['logo_url'] : '../assets/images/companylogo.jpeg';

$stmt = $pdo->prepare("SELECT jc.*, v.reg_no, v.model, v.vin_no, v.fiscal_no, c.name AS client_name, c.phone AS client_phone, c.email AS client_email, c.address AS client_address, t.name AS technician_name FROM job_cards jc LEFT JOIN vehicles v ON jc.vehicle_id = v.id LEFT JOIN clients c ON v.client_id = c.id LEFT JOIN employees t ON jc.technician_id = t.id WHERE jc.id = ?");
$stmt->execute([$job_card_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) die('<h2 style="text-align:center;color:#c62828;margin:100px;">Job Card Not Found</h2>');

$qr_code = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode("jobs/" . $job['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Card - <?= htmlspecialchars($job['card_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --p: #F5A623;
            --s: #1a1a1a;
            --a: #FFF8EC;
            --g: #2e7d32;
            --dark: #1a1a1a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: white;
            color: #333;
            line-height: 1.2;
        }
        .page {
            width: 210mm;
            height: 297mm;
            margin: 0 auto;
            background: white;
            position: relative;
            padding: 8mm;
            overflow: visible;
        }
        .luxury-card {
            background: white;
            border: 2px solid var(--p);
            margin: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: visible;
        }
        .jc-logo {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            left: 15px;
            max-width: 100px;
            max-height: 100px;
            width: auto;
            height: auto;
            border-radius: 8px;
            border: 3px solid white;
            object-fit: contain;
            background: white;
            box-shadow: 0 3px 10px rgba(0,0,0,0.3);
            z-index: 10;
            padding: 6px;
        }
        .jc-header {
            background: linear-gradient(135deg, var(--p), var(--s));
            color: white;
            padding: 15px 130px;
            text-align: center;
            position: relative;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .jc-header h1 {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
            margin: 2px 0;
        }
        .jc-header .slogan {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 300;
            margin-bottom: 6px;
        }
        .card-no {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 2px;
            background: none;
            color: white;
            padding: 4px 14px;
            border-radius: 6px;
            display: inline-block;
            margin: 4px 0 0;
        }
        .jc-content { padding: 10px 12px; flex: 1; display: flex; flex-direction: column; }
        .section {
            margin-bottom: 6px;
            background: #FFF8F0;
            padding: 6px 8px;
            border-left: 3px solid var(--p);
            border-radius: 0 4px 4px 0;
            page-break-inside: avoid;
        }
        .section h2 {
            font-size: 11px;
            color: var(--s);
            margin-bottom: 4px;
            padding-bottom: 2px;
            border-bottom: 1px solid var(--p);
            display: inline-block;
            font-weight: 700;
        }
        .jc-table {
            width: 100%;
            font-size: 10px;
            border-collapse: collapse;
        }
        .jc-table td {
            padding: 2px 0;
            vertical-align: top;
        }
        .label {
            font-weight: 700;
            color: var(--dark);
            width: 100px;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.3px;
        }
        .value { font-weight: 500; color: #333; font-size: 10px; }
        .highlight { color: var(--p); font-weight: 700; }
        .desc-box {
            background: #FFF8F0;
            padding: 6px 8px;
            border-radius: 4px;
            font-size: 9px;
            line-height: 1.3;
            min-height: 30px;
            max-height: 35px;
            overflow: hidden;
            border: 1px dashed #ffe4c4;
            word-wrap: break-word;
        }
        .qr-section {
            text-align: center;
            margin: 8px 0 6px;
            page-break-inside: avoid;
        }
        .qr-section img {
            width: 60px;
            height: 60px;
            border: 2px solid white;
            border-radius: 4px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }
        .qr-section p {
            font-size: 8px;
            color: var(--s);
            margin-top: 2px;
        }
        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 6px 0;
            page-break-inside: avoid;
        }
        .sign-box {
            border: 2px dashed var(--a);
            border-radius: 4px;
            height: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #fef8f0;
            position: relative;
        }
        .sign-box i {
            font-size: 20px;
            color: #ccc;
            margin-bottom: 4px;
        }
        .sign-label-bottom {
            position: absolute;
            bottom: 4px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 8px;
            font-weight: 600;
            color: var(--s);
            white-space: nowrap;
        }
        .jc-footer {
            text-align: center;
            padding: 6px;
            background: var(--dark);
            color: white;
            font-size: 8px;
            line-height: 1.3;
            page-break-inside: avoid;
            margin-top: auto;
        }
        @media print {
            body { margin: 0; padding: 0; }
            .page { margin: 0; width: 100%; height: 100%; padding: 8mm; }
            .luxury-card { border: none; box-shadow: none; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body onload="window.print();">

<div class="page">
    <div class="luxury-card">
        <div class="jc-header">
            <?php if (file_exists(__DIR__ . '/' . $logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" class="jc-logo" alt="Company Logo">
            <?php endif; ?>
            <h1><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></h1>
            <div class="slogan"><?= htmlspecialchars($business['slogan'] ?? 'Luxury • Precision • Trust') ?></div>
            <div class="card-no"><?= htmlspecialchars($job['card_number']) ?></div>
        </div>

        <div class="jc-content">
            <div class="section">
                <h2>Client & Vehicle Information</h2>
                <table class="jc-table">
                    <tr><td class="label">Client Name</td><td class="value highlight"><?= htmlspecialchars($job['client_name'] ?? '—') ?></td></tr>
                    <tr><td class="label">Phone</td><td class="value"><?= htmlspecialchars($job['client_phone'] ?? '—') ?></td></tr>
                    <tr><td class="label">Email</td><td class="value"><?= htmlspecialchars($job['client_email'] ?? '—') ?></td></tr>
                    <tr><td class="label">Address</td><td class="value"><?= htmlspecialchars($job['client_address'] ?? '—') ?></td></tr>
                    <tr><td class="label">Registration No.</td><td class="value highlight"><?= htmlspecialchars($job['reg_no'] ?? '—') ?></td></tr>
                    <tr><td class="label">Model</td><td class="value"><?= htmlspecialchars($job['model'] ?? '—') ?></td></tr>
                    <tr><td class="label">VIN Number</td><td class="value"><?= htmlspecialchars($job['vin_no'] ?? '—') ?></td></tr>
                    <tr><td class="label">Fiscal No.</td><td class="value"><?= htmlspecialchars($job['fiscal_no'] ?? '—') ?></td></tr>
                </table>
            </div>

            <div class="section">
                <h2>Job Details</h2>
                <table class="jc-table">
                    <tr><td class="label">Technician</td><td class="value highlight"><?= htmlspecialchars($job['technician_name'] ?? '—') ?></td></tr>
                    <tr><td class="label">Parts Requester</td><td class="value"><?= htmlspecialchars($job['requester_for_parts'] ?? '—') ?></td></tr>
                    <tr>
                        <td class="label">Status</td>
                        <td class="value">
                            <strong style="background:#e8f5e8;color:var(--g);padding:3px 10px;border-radius:10px;font-size:9px;">
                                <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <td class="label">Created</td>
                        <td class="value"><?= date('d F Y \a\t H:i', strtotime($job['created_at'])) ?></td>
                    </tr>
                </table>
            </div>

            <div class="section">
                <h2>Customer Complaint / Work Required</h2>
                <div class="desc-box"><?= nl2br(htmlspecialchars($job['description'] ?: 'No description provided.')) ?></div>
            </div>

            <div class="section">
                <h2>Parts Required / Supplied</h2>
                <div class="desc-box"><?= nl2br(htmlspecialchars($job['parts_supply'] ?: 'None specified.')) ?></div>
            </div>

            <div class="qr-section">
                <img src="<?= htmlspecialchars($qr_code) ?>" alt="QR Code">
                <p>Scan to access</p>
            </div>

            <div class="signatures">
                <div class="sign-box">
                    <i class="fas fa-pen"></i>
                    <div class="sign-label-bottom"><?= htmlspecialchars($job['technician_name'] ?? 'Technician') ?></div>
                </div>
                <div class="sign-box">
                    <i class="fas fa-handshake"></i>
                    <div class="sign-label-bottom">Client Signature</div>
                </div>
            </div>

            <div class="jc-footer">
                <strong><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></strong><br>
                <?= htmlspecialchars($business['address'] ?? '') ?> • <?= htmlspecialchars($business['phone'] ?? '') ?> • <?= htmlspecialchars($business['email'] ?? '') ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>

