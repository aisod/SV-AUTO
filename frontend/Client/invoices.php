<?php
// Client Invoices List
$page_title = 'Invoices';
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

// Step 2: Use client_id to fetch all invoices (join through quotations)
$invoices = [];
if ($clientId) {
    $stmt = $pdo->prepare("
        SELECT i.* FROM invoices i
        JOIN quotations q ON i.quotation_id = q.id
        WHERE q.client_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$clientId]);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate statistics
$stats = [
    'total' => count($invoices),
    'unpaid' => 0,
    'paid' => 0,
    'total_unpaid' => 0
];

foreach ($invoices as $invoice) {
    if ($invoice['status'] === 'unpaid') {
        $stats['unpaid']++;
        $stats['total_unpaid'] += $invoice['amount'] ?? 0;
    } elseif ($invoice['status'] === 'paid') {
        $stats['paid']++;
    }
}
?>

<style>
/* ── RESPONSIVE INVOICES ── */
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
    
    /* Invoices table */
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
        <span class="erp-breadcrumb-current">Invoices</span>
    </div>
    <div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
        <h1 class="erp-page-title">My Invoices</h1>
    </div>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div class="erp-card" style="text-align: center; border-top: 4px solid #F7A100;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; color: #F7A100;"><?php echo $stats['total']; ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Total Invoices</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #EF4444;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; color: #EF4444;"><?php echo $stats['unpaid']; ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Unpaid</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #10B981;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 32px; font-weight: 700; color: #10B981;"><?php echo $stats['paid']; ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Paid</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #2563EB;">
        <div class="erp-card-body" style="padding: 20px;">
            <div style="font-size: 28px; font-weight: 700; color: #2563EB;"><?php echo formatMoney($stats['total_unpaid']); ?></div>
            <div style="font-size: 13px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Amount Due</div>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="erp-card erp-mb-4" style="border-left: 4px solid #F7A100;">
    <div class="erp-card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--gray-200);">
        <h3 class="erp-card-title" style="font-size: 14px; font-weight: 700; color: #1A1A1A; text-transform: uppercase; letter-spacing: 0.5px;">
            <i class="fas fa-list" style="color: #F7A100; margin-right: 8px;"></i>All Invoices
        </h3>
    </div>
    <div class="erp-table-container">
        <table class="erp-table" id="invoicesTable">
            <thead>
                <tr>
                    <th style="background: #EBF4FF; color: #2563EB;">Invoice #</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Job Card</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Date</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Due Date</th>
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
                <?php elseif (empty($invoices)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 60px 20px; color: #6B7280;">
                        <i class="fas fa-receipt" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3; color: #F7A100;"></i>
                        <p style="font-size: 18px; font-weight: 600; margin-bottom: 10px;">No invoices found</p>
                        <p style="font-size: 14px;">You don't have any invoices yet.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td style="font-weight: 600;">
                            <?php echo htmlspecialchars($invoice['invoice_number'] ?? ('INV-' . str_pad($invoice['id'], 5, '0', STR_PAD_LEFT))); ?>
                        </td>
                        <td><?php echo htmlspecialchars($invoice['job_card_number'] ?? 'N/A'); ?></td>
                        <td><?php echo $invoice['created_at'] ? date('d M Y', strtotime($invoice['created_at'])) : 'N/A'; ?></td>
                        <td>
                            <?php 
                            if ($invoice['due_date']) {
                                $dueDate = strtotime($invoice['due_date']);
                                $today = strtotime('today');
                                $isOverdue = $dueDate < $today && $invoice['status'] === 'unpaid';
                                echo '<span style="color: ' . ($isOverdue ? '#EF4444' : 'inherit') . '">';
                                echo date('d M Y', $dueDate);
                                echo '</span>';
                                if ($isOverdue) {
                                    echo ' <span class="erp-badge erp-badge-danger" style="font-size: 10px;">Overdue</span>';
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td style="font-weight: 600;"><?php echo formatMoney($invoice['amount'] ?? 0); ?></td>
                        <td>
                            <?php 
                            switch ($invoice['status']) {
                                case 'paid':
                                    $statusClass = 'erp-badge-success';
                                    break;
                                case 'unpaid':
                                    $statusClass = 'erp-badge-danger';
                                    break;
                                default:
                                    $statusClass = 'erp-badge-warning';
                            }
                            ?>
                            <span class="erp-badge <?php echo $statusClass; ?>"><?php echo ucfirst($invoice['status']); ?></span>
                        </td>
                        <td>
                            <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="erp-btn erp-btn-primary" style="padding: 6px 16px; font-size: 12px;">
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
