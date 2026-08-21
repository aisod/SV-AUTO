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

// FIXED QUERY — FETCH PO WITH DETAILS
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
        c.phone,
        COALESCE(v.reg_no, 'Walk-in') AS reg_no,
        v.model
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
$po_number = 'PO-' . str_pad($po['id'], 4, '0', STR_PAD_LEFT);

// Parse line items from details
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
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="purchase_orders.php">Purchase Orders</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">View Purchase Order</span>
    </div>
    <h1 class="erp-page-title">Purchase Order Details</h1>
</div>

<!-- Alert Messages -->
<?php if (isset($_GET['success'])): ?>
    <div class="erp-alert erp-alert-success erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-check-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Success</div>
            <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    </div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="erp-alert erp-alert-danger erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Error</div>
            <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    </div>
<?php endif; ?>

<!-- PURCHASE ORDER DOCUMENT SHELL -->
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
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">PURCHASE ORDER</div>
        <div style="text-align:right;">
            <div style="font-size:15px; font-weight:900; letter-spacing:2px;"><?php echo $po_number; ?></div>
            <div style="font-size:11px; color:#888; margin-top:2px;"><?php echo strtoupper($po['status']); ?></div>
        </div>
    </div>

    <!-- TWO-COLUMN INFO GRID -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">
        <!-- LEFT: ORDER DETAILS -->
        <div style="border-right:2px solid #000; padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">ORDER DETAILS</div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:13px; color:#555;">PO Number</span>
                <span style="font-size:14px; font-weight:700; color:#111;"><?php echo $po_number; ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:13px; color:#555;">Date</span>
                <span style="font-size:14px; font-weight:700; color:#111;"><?php echo date('d M Y', strtotime($po['created_at'])); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span style="font-size:13px; color:#555;">Status</span>
                <?php
                $statusColor = '#F7A100';
                if ($po['status'] === 'approved') {
                    $statusColor = '#22C55E';
                } elseif ($po['status'] === 'rejected') {
                    $statusColor = '#DC2626';
                }
                ?>
                <span style="font-size:13px; font-weight:700; color:<?php echo $statusColor; ?>; text-transform:uppercase;"><?php echo ucfirst($po['status']); ?></span>
            </div>
        </div>
        <!-- RIGHT: SUPPLIER & JOB CARD -->
        <div style="padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">SUPPLIER & JOB CARD</div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:13px; color:#555;">Supplier</span>
                <span style="font-size:14px; font-weight:700; color:#111;"><?php echo htmlspecialchars($po['supplier_name']); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                <span style="font-size:13px; color:#555;">Job Card</span>
                <span style="font-size:14px; font-weight:700; color:#111;"><?php echo htmlspecialchars($po['card_number'] ?? '—'); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between;">
                <span style="font-size:13px; color:#555;">Vehicle</span>
                <span style="font-size:14px; font-weight:700; color:#111;"><?php echo htmlspecialchars($po['reg_no']); ?> • <?php echo htmlspecialchars($po['model'] ?? ''); ?></span>
            </div>
        </div>
    </div>

    <!-- CLIENT ROW -->
    <div style="padding:12px 16px; border-bottom:2px solid #000; display:flex; gap:40px;">
        <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; letter-spacing:1px; white-space:nowrap;">CLIENT INFORMATION</div>
        <div style="display:flex; gap:40px; flex:1;">
            <div>
                <span style="font-size:13px; color:#555;">Client Name:</span>
                <span style="font-size:14px; font-weight:700; color:#111; margin-left:8px;"><?php echo htmlspecialchars($po['client_name'] ?? 'Walk-in Client'); ?></span>
            </div>
            <div>
                <span style="font-size:13px; color:#555;">Phone:</span>
                <span style="font-size:14px; font-weight:700; color:#111; margin-left:8px;"><?php echo htmlspecialchars($po['phone'] ?? '—'); ?></span>
            </div>
        </div>
    </div>

    <!-- PARTS & SERVICES TABLE -->
    <div style="padding:12px 16px; border-bottom:2px solid #000;">
        <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">PARTS & SERVICES BREAKDOWN</div>
        <?php if (!empty($items)): ?>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="width:60px; background:#EBF4FF; color:#2563EB; padding:8px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; text-align:center; border:1px solid #ccc;">No.</th>
                        <th style="background:#EBF4FF; color:#2563EB; padding:8px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; text-align:left; border:1px solid #ccc;">Description</th>
                        <th style="width:150px; background:#EBF4FF; color:#2563EB; padding:8px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; text-align:right; border:1px solid #ccc;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $i => $item): ?>
                    <tr>
                        <td style="text-align:center; padding:8px; border:1px solid #ddd; font-weight:600;"><?php echo $i + 1; ?></td>
                        <td style="padding:8px; border:1px solid #ddd;"><?php echo htmlspecialchars($item['desc']); ?></td>
                        <td style="text-align:right; padding:8px; border:1px solid #ddd; font-weight:600;"><?php echo htmlspecialchars($item['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="font-size:14px; color:#6B7280; margin:0; font-style:italic;">No itemized parts breakdown available.</p>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; padding-top:12px; border-top:2px solid #000;">
            <span style="font-size:14px; font-weight:900; text-transform:uppercase;">TOTAL</span>
            <span style="font-size:18px; font-weight:900; color:#F7A100;"><?php echo formatMoney($po['amount']); ?></span>
        </div>
    </div>

    <!-- NOTES SECTION -->
    <?php if (!empty($po['notes'])): ?>
    <div style="padding:12px 16px; border-bottom:2px solid #000;">
        <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:6px; letter-spacing:1px;">
            <i class="fas fa-sticky-note" style="color:#F7A100;"></i> ADDITIONAL NOTES
        </div>
        <div style="font-size:14px; line-height:1.6; color:#111;">
            <?php echo nl2br(htmlspecialchars($po['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION BUTTONS -->
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
        <a href="purchase_orders.php" style="background:#6B7280; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <div style="display:flex; gap:12px;">
            <a href="print_purchase_order.php?id=<?php echo $po['id']; ?>" target="_blank" style="background:#1A1A1A; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
                <i class="fas fa-print"></i> Print / PDF
            </a>
            <?php if ($po['status'] === 'pending'): ?>
                <button onclick="showApproveModal()" style="background:#22C55E; color:white; border:none; border-radius:8px; padding:10px 24px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-check"></i> Approve
                </button>
            <?php else: ?>
                <button disabled style="background:#9CA3AF; color:white; border:none; border-radius:8px; padding:10px 24px; font-size:14px; font-weight:700; cursor:not-allowed; display:inline-flex; align-items:center; gap:8px;">
                    <i class="fas fa-check-circle"></i> Already Approved
                </button>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Approval Confirmation Modal -->
<div class="erp-modal-overlay" id="approveModal">
    <div class="erp-modal">
        <div class="erp-modal-header">
            <h3 class="erp-modal-title">Approve Purchase Order</h3>
            <button class="erp-modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="erp-modal-body" style="text-align: center;">
            <div style="width: 80px; height: 80px; background: var(--status-success-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-check-circle" style="font-size: 36px; color: var(--status-success);"></i>
            </div>
            <p style="color: var(--gray-600); margin-bottom: 16px;">
                Are you sure you want to approve this purchase order for <strong><?php echo formatMoney($po['amount']); ?></strong>?
            </p>
        </div>
        <div class="erp-modal-footer">
            <button class="erp-btn erp-btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="erp-btn erp-btn-success" onclick="confirmApprove()">Approve</button>
        </div>
    </div>
</div>

<style>
    .erp-detail-table {
        width: 100%;
        border-collapse: collapse;
    }
    .erp-detail-table td {
        padding: 8px 0;
        vertical-align: top;
    }
    .erp-detail-table .erp-label {
        width: 100px;
        font-weight: 500;
        color: var(--gray-500);
        font-size: 13px;
    }
    .erp-detail-table .erp-value {
        color: var(--gray-700);
        font-weight: 600;
    }
</style>

<script>
    function showApproveModal() {
        document.getElementById('approveModal').classList.add('show');
    }
    
    function closeModal() {
        document.getElementById('approveModal').classList.remove('show');
    }
    
    function confirmApprove() {
        location.href = 'approve_purchase_order.php?id=<?php echo $po['id']; ?>';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('approveModal');
        if (event.target === modal) {
            closeModal();
        }
    }
    
    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.erp-alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
