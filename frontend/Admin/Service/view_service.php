<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<h2 style="text-align:center;color:#c62828;padding:100px;font-family:\'Inter\',-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">Invalid Service ID</h2>');
}

$service_id = (int)$_GET['id'];

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

if (!$service) {
    die('<h2 style="text-align:center;color:#c62828;padding:100px;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">Service Schedule Not Found</h2>');
}

$business = getBusiness();
$service_number = 'SVC-' . str_pad($service['id'], 4, '0', STR_PAD_LEFT);

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="services_update.php">Services</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">View Service</span>
    </div>
    <div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
        <h1 class="erp-page-title">Service Details</h1>
    </div>
</div>

<!-- SERVICE DOCUMENT -->
<div style="background:white; border:2px solid #000; max-width:900px; margin:0 auto 20px; font-family:Arial,sans-serif;">

    <!-- DOCUMENT HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:12px 16px; border-bottom:2px solid #000;">
        <div>
            <?php
            $logoPath = '../assets/images/companylogo.jpeg';
            if (file_exists($logoPath)):
            ?>
                <img src="<?php echo $logoPath . '?v=' . time(); ?>" style="height:55px; width:auto;">
            <?php endif; ?>
        </div>
        <div style="text-align:right; font-size:11px; line-height:1.7; color:#222;">
            <?php echo htmlspecialchars($business['address'] ?? 'Lafrenz Industrial • Rensburger Street • Erf 174LL • Unit 18'); ?><br>
            Cell: <?php echo htmlspecialchars($business['phone'] ?? ''); ?> •
            Email: <?php echo htmlspecialchars($business['email'] ?? ''); ?><br>
            PO Box 21292 • Windhoek • Namibia<br>
            Reg No: <?php echo htmlspecialchars($business['tax_number'] ?? ''); ?>
        </div>
    </div>

    <!-- TITLE BAR -->
    <div style="display:flex; justify-content:space-between; align-items:center; background:#f5f5f5; border-bottom:2px solid #000; padding:8px 16px;">
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">Service Schedule</div>
        <div style="text-align:right;">
            <div style="font-size:15px; font-weight:900; letter-spacing:2px;"><?php echo $service_number; ?></div>
            <?php
            $badgeColor = '#d97706'; $badgeBg = '#fff4e5';
            if ($service['status'] === 'approved') { $badgeColor = '#16a34a'; $badgeBg = '#f0fdf4'; }
            elseif ($service['status'] === 'rejected') { $badgeColor = '#dc2626'; $badgeBg = '#fef2f2'; }
            ?>
            <span style="background:<?php echo $badgeBg; ?>; color:<?php echo $badgeColor; ?>; padding:3px 12px; border-radius:12px; font-size:11px; font-weight:700; text-transform:uppercase;">
                <?php echo ucfirst($service['status']); ?>
            </span>
        </div>
    </div>

    <!-- TWO COLUMN BODY -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">

        <!-- LEFT: CLIENT INFO -->
        <div style="border-right:2px solid #000; padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:8px; letter-spacing:1px;">Client Information</div>

            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Client Name</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo htmlspecialchars($service['client_name']); ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Address</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo htmlspecialchars($service['client_address'] ?? '—'); ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Phone</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo htmlspecialchars($service['client_phone'] ?? '—'); ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Email</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo htmlspecialchars($service['client_email'] ?? '—'); ?></span>
            </div>
        </div>

        <!-- RIGHT: VEHICLE & SERVICE INFO -->
        <div style="padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:8px; letter-spacing:1px;">Vehicle & Service Details</div>

            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Service Date</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo date('d M Y', strtotime($service['service_date'])); ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Reg No.</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo htmlspecialchars($service['reg_no']); ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Model</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo htmlspecialchars($service['model']); ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">VIN No.</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo htmlspecialchars($service['vin_no'] ?? '—'); ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Last Service</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo $service['last_service_date'] ? date('d M Y', strtotime($service['last_service_date'])) : '—'; ?></span>
            </div>
            <div style="display:flex; align-items:flex-end; margin-bottom:8px; gap:8px;">
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#333; min-width:100px; white-space:nowrap;">Next Service</label>
                <span style="flex:1; border-bottom:1px solid #000; padding:2px 4px; font-size:13px; font-weight:600;"><?php echo date('d M Y', strtotime($service['service_date'] . ' +6 months')); ?></span>
            </div>
        </div>
    </div>

    <!-- NOTES SECTION -->
    <div style="text-align:center; font-size:10px; font-weight:900; letter-spacing:4px; text-transform:uppercase; background:#f0f0f0; border-bottom:1px solid #000; padding:5px 0;">
        Notes / Recommended Services
    </div>
    <div style="padding:12px 16px; min-height:80px; border-bottom:2px solid #000;">
        <p style="font-size:13px; line-height:1.8; color:#111; margin:0;">
            <?php echo !empty($service['notes']) ? nl2br(htmlspecialchars($service['notes'])) : '<span style="color:#999; font-style:italic;">No notes added.</span>'; ?>
        </p>
    </div>

    <!-- SIGNATURE ROW -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">
        <div style="padding:16px; border-right:1px solid #000; min-height:70px;">
            <div style="font-size:9px; font-weight:700; text-transform:uppercase; color:#333; margin-bottom:24px;">Authorized Signature</div>
            <div style="border-top:1px solid #000; margin-top:8px;"></div>
        </div>
        <div style="padding:16px; min-height:70px;">
            <div style="font-size:9px; font-weight:700; text-transform:uppercase; color:#333; margin-bottom:24px;">Client Signature</div>
            <div style="border-top:1px solid #000; margin-top:8px;"></div>
        </div>
    </div>

    <!-- FOOTER -->
    <div style="padding:8px 16px; background:#fafafa; font-size:9px; color:#666; text-align:center; line-height:1.5;">
        Thank you for choosing <?php echo htmlspecialchars($business['name'] ?? 'SV Auto Services'); ?> •
        This service schedule is valid for 30 days from the service date.
    </div>

</div>

<!-- ACTION BUTTONS -->
<div style="max-width:900px; margin:0 auto 40px; display:flex; gap:12px; justify-content:center;">
    <button onclick="window.print()" class="erp-btn erp-btn-secondary" style="background:#607d8b;">
        <i class="fas fa-print"></i> Print
    </button>
    <a href="edit_service.php?id=<?php echo $service['id']; ?>" class="erp-btn erp-btn-primary">
        <i class="fas fa-edit"></i> Edit Service
    </a>
    <?php if ($service['status'] === 'pending'): ?>
        <a href="approve_service.php?id=<?php echo $service['id']; ?>&success=Service approved!" 
           class="erp-btn erp-btn-success" 
           onclick="return confirm('Approve this service schedule?');">
            <i class="fas fa-check"></i> Approve
        </a>
    <?php endif; ?>
</div>

<style>
@media print {
    .erp-header, .erp-sidebar, .erp-sidebar-footer,
    .sidebar-overlay, .erp-breadcrumb, .erp-page-header,
    .erp-btn, div[style*="justify-content:center"] { display:none !important; }
    body, html { margin:0; padding:0; background:white; }
    .erp-content, .erp-main { margin:0; padding:0; }
    @page { size:A4 portrait; margin:10mm; }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
