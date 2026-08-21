<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$job_card_id = (int)($_GET['id'] ?? 0);
if ($job_card_id <= 0) die('<h2 style="text-align:center;color:#c62828;margin:100px;">Invalid Job Card</h2>');

// Fetch job card data
$stmt = $pdo->prepare("
    SELECT jc.*, v.reg_no, v.model, v.vin_no,
           COALESCE(jc_client.name, v_client.name) AS client_name,
           COALESCE(jc_client.phone, v_client.phone) AS client_phone,
           COALESCE(jc_client.email, v_client.email) AS client_email,
           COALESCE(jc_client.address, v_client.address) AS client_address
    FROM job_cards jc 
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id 
    LEFT JOIN clients jc_client ON jc.client_id = jc_client.id
    LEFT JOIN clients v_client ON v.client_id = v_client.id 
    WHERE jc.id = ?
");
$stmt->execute([$job_card_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) die('<h2 style="text-align:center;color:#c62828;margin:100px;">Job Card Not Found</h2>');

// Get business info with defaults
$business = $pdo->query("SELECT * FROM business LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$companyName = $business['name'] ?? 'SV Auto Truck Repair CC';
$companyAddress = $business['address'] ?? 'Lafrenz Industrial â€¢ Rensburger Street â€¢ Erf 174LL â€¢ Unit 18';
$companyPhone = $business['phone'] ?? '+264 81 446 9962';
$companyEmail = $business['email'] ?? 'svautotruckrepairs@gmail.com';
$companyPO = 'PO Box 21292 â€¢ Windhoek â€¢ Namibia';
$companyReg = 'Reg No. cc/2015/13178';

$logo_base64 = '';
$logo_path = __DIR__ . '/../../assets/images/companylogo2.png';
if (file_exists($logo_path)) {
    $logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path));
}

// Format card number
$card_number = str_pad($job['card_number'] ?? $job['id'], 4, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Card <?= $card_number ?> - <?= htmlspecialchars($companyName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { font-family: Arial, sans-serif; background: #e9ecef; padding: 20px; color: #111; }
        
        .print-buttons {
            max-width: 210mm;
            margin: 0 auto 20px;
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print { background: #F7A100; color: #111; flex: 1; font-weight: 800; }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        /* JOB CARD PAGES */
        .job-card-page {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 0 auto 20px;
            padding: 6mm;
            border: 0.8px solid #333;
            box-shadow: 0 8px 26px rgba(0,0,0,.15);
            page-break-after: always;
        }
        
        /* HEADER */
        .header {
            display: grid;
            grid-template-columns: 135px 1fr;
            align-items: center;
            margin-bottom: 8px;
            padding: 4px 4px 2px;
            border-bottom: 0.8px solid #222;
        }
        
        .header-logo img {
            width: 128px;
            height: auto;
        }
        
        .header-contact {
            text-align: center;
            font-size: 9px;
            line-height: 1.35;
            color: #000;
            font-family: Arial, sans-serif;
        }
        
        /* TITLE */
        .title-row {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            margin-bottom: 8px;
            padding: 0 4px 2px;
        }
        
        .title-row h1 {
            font-size: 40px;
            font-weight: 900;
            letter-spacing: 1px;
            font-family: Arial Black, sans-serif;
            text-align: center;
            line-height: 1;
        }
        
        .title-row .card-no {
            font-size: 34px;
            font-weight: 900;
            font-family: Arial, sans-serif;
            white-space: nowrap;
            line-height: 1;
        }
        
        /* INFO TABLE */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .info-table td {
            border: 0.7px solid #444;
            padding: 2px 5px;
            font-size: 9px;
            line-height: 1.15;
        }
        
        .info-table .label {
            font-weight: 700;
            width: 30%;
            background: #fdfdfd;
        }
        
        .info-table .value {
            width: 70%;
            min-height: 20px;
        }
        
        .info-table .to-cell {
            min-height: 48px;
            vertical-align: top;
        }
        
        /* SECTION HEADING */
        .section-heading {
            background: #F7A100;
            color: #000;
            text-align: center;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .5px;
            padding: 4px;
            margin: 0;
            border: 0.8px solid #333;
        }
        
        /* LINED SECTION */
        .lined-section {
            border: 0.8px solid #333;
            border-top: 0;
            padding: 0;
            min-height: 128px;
        }
        
        .lined-row {
            border-bottom: 0.6px solid #b7b7b7;
            min-height: 13px;
            padding: 0;
        }
        
        /* PARTS SUPPLY */
        .parts-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 0.8px solid #333;
            border-top: 0;
            padding: 0;
        }
        
        .parts-column { min-height: 245px; }
        .parts-column:first-child { border-right: 0.8px solid #333; }
        
        /* CONDITIONS */
        .conditions {
            margin-top: 15px;
            font-size: 7px;
            line-height: 1.5;
            color: #333;
        }
        
        .conditions strong {
            font-size: 8px;
            font-weight: 900;
        }
        
        /* PAGE 2 SPECIFIC */
        .work-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .work-table th {
            background: #F7A100;
            border: 0.8px solid #333;
            padding: 3px 5px;
            font-size: 9px;
            font-weight: 900;
            text-align: left;
        }
        
        .work-table td {
            border: 0.6px solid #b7b7b7;
            padding: 0 4px;
            min-height: 13px;
            font-size: 9px;
        }
        
        .work-table .time-col { width: 84px; }
        
        /* CALLOUT SECTION */
        .callout-section {
            border: 2px solid #000;
            margin-bottom: 15px;
        }
        
        .callout-heading {
            background: #F7A100;
            text-align: center;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 2px;
            padding: 6px;
        }
        
        .callout-body {
            padding: 10px;
        }
        
        .callout-row {
            display: flex;
            gap: 15px;
            margin-bottom: 8px;
        }
        
        .callout-field {
            flex: 1;
        }
        
        .callout-field label {
            font-size: 9px;
            font-weight: 700;
            display: block;
            margin-bottom: 3px;
        }
        
        .callout-field .value {
            border-bottom: 1px solid #000;
            min-height: 20px;
            padding: 2px 4px;
        }
        
        /* BOTTOM ROW */
        .bottom-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .bottom-field label {
            font-size: 9px;
            font-weight: 700;
            display: block;
            margin-bottom: 3px;
        }
        
        .bottom-field .value {
            border-bottom: 1px solid #000;
            min-height: 25px;
            padding: 2px 4px;
        }
        
        /* FOOTER */
        .page-footer {
            text-align: right;
            font-size: 8px;
            color: #666;
            margin-top: 20px;
        }
        
        /* PRINT STYLES */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-buttons {
                display: none !important;
            }
            
            .job-card-page {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 2mm;
                box-shadow: none;
                page-break-after: always;
            }
            
            .job-card-page:last-child {
                page-break-after: auto;
            }
            
            @page {
                size: A4;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <!-- PRINT BUTTONS -->
    <div class="print-buttons">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print Job Card
        </button>
        <a href="job_card.php" class="btn btn-back">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>
    
    <!-- PAGE 1 -->
    <div class="job-card-page">
        <!-- HEADER -->
        <div class="header">
            <div class="header-logo">
                <?php if ($logo_base64): ?>
                    <img src="<?= $logo_base64 ?>" alt="SV Auto Truck Repair CC">
                <?php else: ?>
                    <div style="font-size:20px; font-weight:900; font-family:Arial Black;">SV AUTO<br><small style="font-size:12px;">TRUCK REPAIR CC</small></div>
                <?php endif; ?>
            </div>
            <div class="header-contact">
                Lafrenz Industrial - Rensburger Street - Erf 174LL - Unit 18<br>
                Cell: +264 81 446 9962 - Email: svautotruckrepairs@gmail.com<br>
                PO Box 21292 - Windhoek - Namibia<br>
                Reg No. cc/2015/13178
            </div>
        </div>
        
        <!-- TITLE -->
        <div class="title-row">
            <h1>JOB CARD</h1>
            <div class="card-no">No. <?= htmlspecialchars($card_number) ?></div>
        </div>
        
        <!-- CLIENT & VEHICLE INFO TABLE -->
        <table class="info-table">
            <tr>
                <td class="label">To</td>
                <td class="value to-cell"><?= htmlspecialchars($job['client_name'] ?? '') ?></td>
                <td class="label">Date</td>
                <td class="value"><?= htmlspecialchars(date('d/m/Y', strtotime($job['created_at']))) ?></td>
            </tr>
            <tr>
                <td class="label">Contact No.</td>
                <td class="value"><?= htmlspecialchars($job['client_phone'] ?? '') ?></td>
                <td class="label">VIN No.</td>
                <td class="value"><?= htmlspecialchars($job['vin_no'] ?? '') ?></td>
            </tr>
            <tr>
                <td class="label">Email Address</td>
                <td class="value"><?= htmlspecialchars($job['client_email'] ?? '') ?></td>
                <td class="label">Kilometers</td>
                <td class="value"></td>
            </tr>
            <tr>
                <td class="label">Contact Person</td>
                <td class="value"></td>
                <td class="label">Fleet No.</td>
                <td class="value"></td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="value"></td>
                <td class="label">Vehicle Reg. No.</td>
                <td class="value"><?= htmlspecialchars($job['reg_no'] ?? '') ?></td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="value"></td>
                <td class="label">Model</td>
                <td class="value"><?= htmlspecialchars($job['model'] ?? '') ?></td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="value"></td>
                <td class="label">Purchase Order No.</td>
                <td class="value"></td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="value"></td>
                <td class="label">Quotation No.</td>
                <td class="value"></td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="value"></td>
                <td class="label">Invoice No.</td>
                <td class="value"></td>
            </tr>
        </table>
        
        <!-- DESCRIPTION SECTION -->
        <div class="section-heading">DESCRIPTION</div>
        <div class="lined-section">
            <?= nl2br(htmlspecialchars($job['description'] ?? '')) ?>
            <?php for ($i = 0; $i < 8; $i++): ?>
                <div class="lined-row"></div>
            <?php endfor; ?>
        </div>
        
        <!-- PARTS SUPPLY SECTION -->
        <div class="section-heading">PARTS SUPPLY</div>
        <div class="parts-columns">
            <div class="parts-column">
                <?php for ($i = 0; $i < 15; $i++): ?>
                    <div class="lined-row"></div>
                <?php endfor; ?>
            </div>
            <div class="parts-column">
                <?php for ($i = 0; $i < 15; $i++): ?>
                    <div class="lined-row"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- CONDITIONS OF SERVICE -->
        <div class="conditions">
            <strong>Conditions of Service:</strong> By signing this Job Card, the customer gives our mechanic authorization to inspect, test drive and do diagnosis on vehicle. Our mechanics will not be held liable for any valuable goods left in the vehicle by customer or existing faults after vehicle is checked into workshop or at roadside assistance. The problem of the vehicle should be clearly stipulated on the Job Card by client. Customer should be prepared to pay 100% of the invoiced amount when collecting vehicles unless prior arrangements has been made with our finance department.
        </div>
    </div>
    
    <!-- PAGE 2 -->
    <div class="job-card-page">
        <!-- WORK DETAILS SECTION -->
        <table class="work-table">
            <thead>
                <tr>
                    <th>WORK DETAILS</th>
                    <th class="time-col">TIME ALLOCATED</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < 30; $i++): ?>
                    <tr>
                        <td></td>
                        <td></td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <!-- CALL OUT OF TOWN SECTION -->
        <table style="width:100%; border-collapse:collapse; margin-top:0;">
            <tr>
                <td colspan="2" style="text-align:center; font-weight:bold; padding:3px; border:0.8px solid #333; background:#F7A100; color:#000; letter-spacing:.5px; font-size:10px;">CALL OUT OF TOWN</td>
            </tr>
            <tr>
                <td style="width:130px; font-weight:bold; padding:3px 6px; border:0.7px solid #444; font-size:9px;">Kilometres</td>
                <td style="border:0.7px solid #444; padding:3px; min-height:13px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="font-weight:bold; padding:3px 6px; border:0.7px solid #444; font-size:9px;">Call out fee</td>
                <td style="border:0.7px solid #444; padding:3px; min-height:13px;">&nbsp;</td>
            </tr>
            <tr>
                <td style="font-weight:bold; padding:3px 6px; border:0.7px solid #444; font-size:9px;">Consumables</td>
                <td style="border:0.7px solid #444; padding:3px; min-height:13px;">&nbsp;</td>
            </tr>
        </table>
        
        <!-- BOTTOM ROW -->
        <table style="width:100%; border-collapse:collapse; margin-top:0;">
            <tr>
                <td style="border:0.7px solid #444; padding:3px 6px; font-weight:bold; width:33%; font-size:9px;"><strong>Normal Time:</strong></td>
                <td style="border:0.7px solid #444; padding:3px 6px; font-weight:bold; width:33%; font-size:9px;"><strong>Overtime:</strong></td>
                <td style="border:0.7px solid #444; padding:3px 6px; font-weight:bold; width:34%; font-size:9px;"><strong>Sunday / Public Holiday:</strong></td>
            </tr>
            <tr>
                <td style="border:0.7px solid #444; padding:3px 6px; font-weight:bold; font-size:9px;"><strong>Technician No.:</strong></td>
                <td colspan="2" style="border:0.7px solid #444; padding:3px 6px; font-weight:bold; font-size:9px;"><strong>Customer Signature:</strong></td>
            </tr>
        </table>
        
        <!-- FOOTER -->
        <div class="page-footer">
            prime press 012023
        </div>
    </div>
</body>
</html>

