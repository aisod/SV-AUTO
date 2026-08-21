<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$employee_id = $_GET['id'] ?? 0;
if (!is_numeric($employee_id) || $employee_id <= 0) {
    header('Location: employees.php?error=Invalid employee');
    exit;
}

$stmt = $pdo->prepare("
    SELECT name, position, status, certificates, licenses, ids, uniforms, photo_url, event_locations 
    FROM employees 
    WHERE id = ? 
    LIMIT 1
");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?error=Employee not found');
    exit;
}

$business = getBusiness();

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="employees.php">Employees</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">View Employee</span>
    </div>
    <h1 class="erp-page-title">Employee Profile</h1>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['error'])): ?>
    <div class="erp-alert erp-alert-danger erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Error</div>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    </div>
<?php endif; ?>

<!-- EMPLOYEE DOCUMENT SHELL -->
<div style="background:white; border:2px solid #000; max-width:1000px; margin:0 auto 20px; font-family:Arial,sans-serif;">

    <!-- DOCUMENT HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:12px 16px; border-bottom:2px solid #000;">
        <div>
            <?php $logoPath = '../assets/images/companylogo.jpeg'; if (file_exists($logoPath)): ?>
                <img src="<?php echo $logoPath . '?v=' . time(); ?>" style="height:55px; width:auto;">
            <?php endif; ?>
        </div>
        <div style="text-align:right; font-size:11px; line-height:1.7; color:#222;">
            <?php echo htmlspecialchars($business['address'] ?? 'Lafrenz Industrial • Rensburger Street'); ?><br>
            Cell: <?php echo htmlspecialchars($business['phone'] ?? ''); ?> •
            Email: <?php echo htmlspecialchars($business['email'] ?? ''); ?><br>
            PO Box 21292 • Windhoek • Namibia<br>
            Reg No: <?php echo htmlspecialchars($business['tax_number'] ?? ''); ?>
        </div>
    </div>

    <!-- TITLE BAR -->
    <div style="display:flex; justify-content:space-between; align-items:center; background:#f5f5f5; border-bottom:2px solid #000; padding:8px 16px;">
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">EMPLOYEE PROFILE</div>
        <?php
        $statusColor = '#22C55E';
        if ($employee['status'] === 'on_leave') {
            $statusColor = '#F7A100';
        } elseif ($employee['status'] === 'on_field') {
            $statusColor = '#3B82F6';
        } elseif ($employee['status'] === 'inactive') {
            $statusColor = '#EF4444';
        }
        ?>
        <span style="background:<?php echo $statusColor; ?>; color:white; padding:6px 14px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px;">
            <?php echo ucwords(str_replace('_', ' ', $employee['status'])); ?>
        </span>
    </div>

    <!-- PROFILE IDENTITY ROW -->
    <div style="padding:12px 16px; border-bottom:2px solid #000; display:flex; align-items:center; gap:20px; background:#FFF8F0;">
        <div style="width:70px; height:70px; border-radius:50%; overflow:hidden; border:3px solid #F7A100; flex-shrink:0;">
            <?php 
            $photo_path = !empty($employee['photo_url']) ? "../" . $employee['photo_url'] : null;
            if ($photo_path && file_exists($photo_path)): ?>
                <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Employee Photo" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
                <div style="width:100%; height:100%; background:#F7A100; display:flex; align-items:center; justify-content:center; color:white; font-size:24px; font-weight:600;">
                    <?php echo strtoupper(substr($employee['name'], 0, 2)); ?>
                </div>
            <?php endif; ?>
        </div>
        <div>
            <div style="font-size:20px; font-weight:900; color:#1A1A1A;"><?php echo htmlspecialchars($employee['name']); ?></div>
            <div style="font-size:13px; color:#6B7280; margin-top:2px;"><?php echo htmlspecialchars($employee['position']); ?></div>
        </div>
    </div>

    <!-- TWO-COLUMN INFO GRID -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">
        <!-- LEFT: PROFESSIONAL DETAILS -->
        <div style="border-right:2px solid #000; padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">PROFESSIONAL DETAILS</div>
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                <span style="font-size:11px; color:#6B7280;">Position</span>
                <span style="font-size:13px; font-weight:600; color:#1A1A1A; text-align:right;"><?php echo htmlspecialchars($employee['position']); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                <span style="font-size:11px; color:#6B7280;">Certificates</span>
                <span style="font-size:13px; font-weight:600; color:#1A1A1A; text-align:right;"><?php echo nl2br(htmlspecialchars($employee['certificates'] ?: '—')); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0;">
                <span style="font-size:11px; color:#6B7280;">Licenses</span>
                <span style="font-size:13px; font-weight:600; color:#1A1A1A; text-align:right;"><?php echo nl2br(htmlspecialchars($employee['licenses'] ?: '—')); ?></span>
            </div>
        </div>
        <!-- RIGHT: ADMINISTRATIVE INFO -->
        <div style="padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">ADMINISTRATIVE INFO</div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                <span style="font-size:11px; color:#6B7280;">ID Numbers</span>
                <span style="font-size:13px; font-weight:600; color:#1A1A1A; text-align:right;"><?php echo nl2br(htmlspecialchars($employee['ids'] ?: '—')); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                <span style="font-size:11px; color:#6B7280;">Uniform Size</span>
                <span style="font-size:13px; font-weight:600; color:#1A1A1A; text-align:right;"><?php echo htmlspecialchars($employee['uniforms'] ?: '—'); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:8px 0;">
                <span style="font-size:11px; color:#6B7280;">Event Locations</span>
                <span style="font-size:13px; font-weight:600; color:#1A1A1A; text-align:right;"><?php echo nl2br(htmlspecialchars($employee['event_locations'] ?: '—')); ?></span>
            </div>
        </div>
    </div>

    <!-- DOCUMENT FOOTER -->
    <div style="padding:8px 16px; text-align:center; font-size:9px; color:#666; background:#fafafa; border-top:2px solid #000;">
        <strong><?php echo htmlspecialchars($business['name'] ?? 'SV Auto Services'); ?></strong> •
        <?php echo htmlspecialchars($business['address'] ?? ''); ?>
        <?php if (!empty($business['phone'])): ?> • <?php echo htmlspecialchars($business['phone']); ?><?php endif; ?>
        <?php if (!empty($business['email'])): ?> • <?php echo htmlspecialchars($business['email']); ?><?php endif; ?>
        <?php if (!empty($business['tax_number'])): ?><br>Reg No: <?php echo htmlspecialchars($business['tax_number']); ?><?php endif; ?>
    </div>

</div>

<!-- ACTION BUTTONS (Outside Document) -->
<div style="max-width:1000px; margin:32px auto; padding:0 20px;">
    <div style="display:flex; justify-content:center; align-items:center; gap:12px; flex-wrap:wrap;">
        <a href="print_employee.php?id=<?php echo $employee_id; ?>" target="_blank" class="erp-btn erp-btn-success" style="min-width:160px;">
            <i class="fas fa-print"></i> Print Profile
        </a>
        <a href="edit_employee.php?id=<?php echo $employee_id; ?>" class="erp-btn erp-btn-primary" style="min-width:160px;">
            <i class="fas fa-edit"></i> Edit Employee
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
