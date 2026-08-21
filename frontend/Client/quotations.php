<?php
// Client Quotations List
$page_title = 'Quotations';
require_once __DIR__ . '/includes/header.php';

// Get client email from session
$clientEmail = $_SESSION['email'] ?? '';
$clientId = null;
$noClientRecord = false;

// Step 1: Find client record by matching email
if ($clientEmail) {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$clientEmail]);
    $clientId = $stmt->fetchColumn();
    
    if (!$clientId) {
        $noClientRecord = true;
    }
}

// Step 2: Use client_id to fetch all quotations
$quotations = [];
if ($clientId) {
    $stmt = $pdo->prepare("
        SELECT q.*, jc.card_number as job_card_number, v.reg_no, v.model
        FROM quotations q
        LEFT JOIN job_cards jc ON q.job_card_id = jc.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        WHERE q.client_id = ?
        ORDER BY q.submitted_at DESC
    ");
    $stmt->execute([$clientId]);
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate statistics
$stats = [
    'total' => count($quotations),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

foreach ($quotations as $quote) {
    if (isset($stats[$quote['status']])) {
        $stats[$quote['status']]++;
    }
}
?>

<style>
/* ── RESPONSIVE QUOTATIONS ── */
@media (max-width: 1024px) {
    .erp-page-header { padding: 20px; }
}

@media (max-width: 768px) {
    .erp-page-title { font-size: 20px; }
    
    /* Stats cards */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }
    
    /* Quotations table */
    .erp-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .erp-table {
        min-width: 600px;
    }
    
    /* Action buttons */
    .erp-flex {
        flex-direction: column;
        gap: 8px;
    }
    
    .erp-btn {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .erp-page-title { font-size: 18px; }
    .erp-breadcrumb { font-size: 12px; }
    
    /* Single column for stats */
    .stats-grid {
        grid-template-columns: 1fr !important;
    }
    
    .erp-card-body {
        padding: 15px !important;
    }
}
</style>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Quotations</span>
    </div>
    <div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
        <h1 class="erp-page-title">My Quotations</h1>
    </div>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div class="erp-card" style="text-align: center; border-top: 4px solid #F7A100;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; color: #F7A100;"><?php echo $stats['total']; ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Total</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #F7A100;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; color: #F7A100;"><?php echo $stats['pending']; ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Pending</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #10B981;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; color: #10B981;"><?php echo $stats['approved']; ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Approved</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #EF4444;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; color: #EF4444;"><?php echo $stats['rejected']; ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Rejected</div>
        </div>
    </div>
</div>

<!-- Quotations Table -->
<div class="erp-card erp-mb-4" style="border-left: 4px solid #F7A100;">
    <div class="erp-card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--gray-200);">
        <h3 class="erp-card-title" style="font-size: 14px; font-weight: 700; color: #1A1A1A; text-transform: uppercase; letter-spacing: 0.5px;">
            <i class="fas fa-list" style="color: #F7A100; margin-right: 8px;"></i>All Quotations
        </h3>
    </div>
    <div class="erp-table-container">
        <table class="erp-table" id="quotationsTable">
            <thead>
                <tr>
                    <th style="background: #EBF4FF; color: #2563EB;">Quote ID</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Job Card</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Vehicle</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Date</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Amount</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Status</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($noClientRecord): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px 20px; color: #6B7280;">
                        <i class="fas fa-user-slash" style="font-size: 64px; margin-bottom: 20px; opacity: 0.5; color: #EF4444;"></i>
                        <p style="font-size: 18px; font-weight: 600; margin-bottom: 10px;">No Documents Found</p>
                        <p style="font-size: 14px;">Your account is not yet linked to any documents. Please contact <strong>SV Auto Services</strong>.</p>
                    </td>
                </tr>
                <?php elseif (empty($quotations)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px 20px; color: #6B7280;">
                        <i class="fas fa-file-invoice" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3; color: #F7A100;"></i>
                        <p style="font-size: 18px; font-weight: 600; margin-bottom: 10px;">No quotations found</p>
                        <p style="font-size: 14px;">You don't have any quotations yet.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($quotations as $quote): ?>
                    <tr>
                        <td style="font-weight: 600;">#<?php echo $quote['id']; ?></td>
                        <td><?php echo htmlspecialchars($quote['job_card_number'] ?? 'N/A'); ?></td>
                        <td>
                            <?php 
                            if ($quote['reg_no']) {
                                echo htmlspecialchars($quote['reg_no']);
                                if ($quote['model']) {
                                    echo ' <span style="color: #6B7280; font-size: 12px;">(' . htmlspecialchars($quote['model']) . ')</span>';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td><?php echo $quote['submitted_at'] ? date('d M Y', strtotime($quote['submitted_at'])) : 'N/A'; ?></td>
                        <td style="font-weight: 600;"><?php echo formatMoney($quote['amount'] ?? 0); ?></td>
                        <td>
                            <?php 
                            switch ($quote['status']) {
                                case 'approved':
                                    $statusClass = 'erp-badge-success';
                                    break;
                                case 'rejected':
                                    $statusClass = 'erp-badge-danger';
                                    break;
                                default:
                                    $statusClass = 'erp-badge-warning';
                            }
                            ?>
                            <span class="erp-badge <?php echo $statusClass; ?>"><?php echo ucfirst($quote['status']); ?></span>
                        </td>
                        <td>
                            <a href="view_quotation.php?id=<?php echo $quote['id']; ?>" class="erp-btn erp-btn-primary" style="padding: 6px 16px; font-size: 12px;">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
