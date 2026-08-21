<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$job_card_id = (int)($_GET['id'] ?? 0);
if ($job_card_id <= 0) {
    die('Invalid Job Card');
}

// Load Business & Settings
$business = getBusiness();
$companyName    = $business['name'] ?? 'SV Auto Services';
$companyPhone   = $business['phone'] ?? '';
$companyEmail   = $business['email'] ?? '';
$companyAddress = $business['address'] ?? '';
$companySlogan  = $business['slogan'] ?? 'Luxury • Precision • Trust';
$logoUrl = !empty($business['logo_url']) ? '../uploads/logos/' . $business['logo_url'] : '../assets/images/companylogo.jpeg';

// Fetch Job Card
$stmt = $pdo->prepare("
    SELECT jc.*, v.reg_no, v.model, v.vin_no, v.fiscal_no,
           c.name AS client_name, c.phone AS client_phone, c.email AS client_email, c.address AS client_address,
           t.name AS technician_name
    FROM job_cards jc
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    LEFT JOIN clients c ON v.client_id = c.id
    LEFT JOIN employees t ON jc.technician_id = t.id
    WHERE jc.id = ?
");
$stmt->execute([$job_card_id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die('Job Card Not Found');
}

// Generate QR Code
$qr_code = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode("jobs/" . $job['id']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Job Card <?php echo $job['card_number']; ?></title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.3; position: relative; }
        .page { width: 210mm; height: 297mm; padding: 10mm; background: white; position: relative; }
        
        .logo {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 60px;
            height: 60px;
            object-fit: contain;
            z-index: 10;
        }
        
        .header { 
            background: #F5A623; 
            color: white; 
            padding: 8px 8px 8px 80px; /* Add left padding for logo space */
            text-align: center; 
            margin-bottom: 8px; 
        }
        .header h1 { font-size: 16px; margin: 2px 0; }
        .header p { font-size: 8px; margin: 1px 0; }
        .card-no { font-size: 14px; font-weight: bold; letter-spacing: 2px; margin: 4px 0; }
        
        .section { margin-bottom: 8px; border-left: 3px solid #F5A623; padding: 6px 8px; background: #fafafa; }
        .section h2 { font-size: 11px; font-weight: bold; color: #4a4a4a; margin-bottom: 4px; border-bottom: 1px solid #F5A623; padding-bottom: 2px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 9px; }
        td { padding: 2px 0; vertical-align: top; }
        .label { font-weight: bold; width: 70px; color: #333; }
        .value { color: #555; }
        
        .desc-box { background: white; padding: 4px; border: 1px solid #ddd; font-size: 9px; line-height: 1.3; min-height: 25px; }
        
        .qr-section { text-align: center; margin: 8px 0; padding: 6px; background: #fafafa; border: 1px solid #ddd; }
        .qr-section img { width: 80px; height: 80px; }
        .qr-section p { font-size: 8px; margin-top: 2px; }
        
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 8px 0; }
        .sig-box { border: 2px dashed #FFF8EC; padding: 8px; text-align: center; background: #fef8f0; min-height: 60px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .sig-box .line { border-top: 1px solid #333; width: 80%; margin: 8px 0 4px 0; }
        .sig-box p { font-size: 9px; font-weight: bold; color: #4a4a4a; }
        
        .footer { background: #4a4a4a; color: white; padding: 6px; text-align: center; font-size: 8px; margin-top: 8px; }
        
        @media print {
            body { margin: 0; padding: 0; }
            .page { width: 100%; height: 100%; padding: 10mm; margin: 0; page-break-after: avoid; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>
<div class="page">
    <!-- LOGO -->
    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="SV Auto Services Logo" class="logo">
    
    <!-- HEADER -->
    <div class="header">
        <h1><?php echo htmlspecialchars($companyName); ?></h1>
        <p><?php echo htmlspecialchars($companySlogan); ?></p>
        <div class="card-no">JC: <?php echo htmlspecialchars($job['card_number']); ?></div>
    </div>
    
    <!-- CLIENT & VEHICLE -->
    <div class="section">
        <h2>CLIENT & VEHICLE</h2>
        <table>
            <tr><td class="label">Client:</td><td class="value"><?php echo htmlspecialchars($job['client_name'] ?? '—'); ?></td></tr>
            <tr><td class="label">Phone:</td><td class="value"><?php echo htmlspecialchars($job['client_phone'] ?? '—'); ?></td></tr>
            <tr><td class="label">Email:</td><td class="value"><?php echo htmlspecialchars($job['client_email'] ?? '—'); ?></td></tr>
            <tr><td class="label">Address:</td><td class="value"><?php echo htmlspecialchars($job['client_address'] ?? '—'); ?></td></tr>
            <tr><td class="label">Reg No:</td><td class="value"><strong><?php echo htmlspecialchars($job['reg_no'] ?? '—'); ?></strong></td></tr>
            <tr><td class="label">Model:</td><td class="value"><?php echo htmlspecialchars($job['model'] ?? '—'); ?></td></tr>
            <tr><td class="label">VIN:</td><td class="value"><?php echo htmlspecialchars($job['vin_no'] ?? '—'); ?></td></tr>
            <tr><td class="label">Fiscal:</td><td class="value"><?php echo htmlspecialchars($job['fiscal_no'] ?? '—'); ?></td></tr>
        </table>
    </div>
    
    <!-- JOB DETAILS -->
    <div class="section">
        <h2>JOB DETAILS</h2>
        <table>
            <tr><td class="label">Technician:</td><td class="value"><strong><?php echo htmlspecialchars($job['technician_name'] ?? '—'); ?></strong></td></tr>
            <tr><td class="label">Requester:</td><td class="value"><?php echo htmlspecialchars($job['requester_for_parts'] ?? '—'); ?></td></tr>
            <tr><td class="label">Status:</td><td class="value"><strong><?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?></strong></td></tr>
            <tr><td class="label">Created:</td><td class="value"><?php echo date('d M Y H:i', strtotime($job['created_at'])); ?></td></tr>
        </table>
    </div>
    
    <!-- WORK REQUIRED -->
    <div class="section">
        <h2>WORK REQUIRED</h2>
        <div class="desc-box"><?php echo nl2br(htmlspecialchars($job['description'] ?? 'None')); ?></div>
    </div>
    
    <!-- PARTS REQUIRED -->
    <div class="section">
        <h2>PARTS REQUIRED</h2>
        <div class="desc-box"><?php echo nl2br(htmlspecialchars($job['parts_supply'] ?? 'None')); ?></div>
    </div>
    
    <!-- QR CODE -->
    <div class="qr-section">
        <img src="<?php echo htmlspecialchars($qr_code); ?>" alt="QR Code">
        <p>Scan to access job card</p>
    </div>
    
    <!-- SIGNATURES -->
    <div class="signatures">
        <div class="sig-box">
            <div style="font-size: 24px; color: #ccc; margin: 10px 0;">✒</div>
            <div class="line"></div>
            <p><?php echo htmlspecialchars($job['technician_name'] ?? 'Technician'); ?></p>
        </div>
        <div class="sig-box">
            <div style="font-size: 24px; color: #ccc; margin: 10px 0;">🤝</div>
            <div class="line"></div>
            <p>Client Signature</p>
        </div>
    </div>
    
    <!-- FOOTER -->
    <div class="footer">
        <strong><?php echo htmlspecialchars($companyName); ?></strong><br>
        <?php echo htmlspecialchars($companyAddress); ?><br>
        <?php echo htmlspecialchars($companyPhone); ?> | <?php echo htmlspecialchars($companyEmail); ?>
    </div>
</div>

<script>
    window.print();
</script>
</body>
</html>
