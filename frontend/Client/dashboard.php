<?php
// Client Dashboard
$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Welcome messages
if (isset($_SESSION['new_google_signup'])): ?>
<div style="background:#f5a623;color:#fff;padding:16px 24px;text-align:center;font-size:1rem;font-weight:600;">
    🎉 Welcome to SV Auto, <?= htmlspecialchars($_SESSION['first_name']) ?>! Your account has been created successfully.
</div>
<?php unset($_SESSION['new_google_signup']); ?>
<?php elseif (isset($_SESSION['welcome_back'])): ?>
<div style="background:#1a1a2e;color:#fff;padding:16px 24px;text-align:center;font-size:1rem;font-weight:600;">
    👋 Welcome back, <?= htmlspecialchars($_SESSION['first_name']) ?>!
</div>
<?php unset($_SESSION['welcome_back']); ?>
<?php endif; ?>

<?php
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

// Summary statistics
$pendingQuotations = 0;
$approvedQuotations = 0;
$unpaidInvoices = 0;
$totalSpent = 0;
$recentQuotations = [];

// Step 2: Use client_id to fetch documents
if ($clientId) {
    // Pending Quotations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotations WHERE client_id = ? AND status = 'pending'");
    $stmt->execute([$clientId]);
    $pendingQuotations = $stmt->fetchColumn();
    
    // Approved Quotations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quotations WHERE client_id = ? AND status = 'approved'");
    $stmt->execute([$clientId]);
    $approvedQuotations = $stmt->fetchColumn();
    
    // Unpaid Invoices (join through quotations)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM invoices i
        JOIN quotations q ON i.quotation_id = q.id
        WHERE q.client_id = ? AND i.status != 'paid'
    ");
    $stmt->execute([$clientId]);
    $unpaidInvoices = $stmt->fetchColumn();
    
    // Total Spent (paid invoices) (join through quotations)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(i.amount), 0) FROM invoices i
        JOIN quotations q ON i.quotation_id = q.id
        WHERE q.client_id = ? AND i.status = 'paid'
    ");
    $stmt->execute([$clientId]);
    $totalSpent = $stmt->fetchColumn();
    
    // Recent Quotations
    $stmt = $pdo->prepare("
        SELECT q.*, jc.card_number as job_card_number
        FROM quotations q
        LEFT JOIN job_cards jc ON q.job_card_id = jc.id
        WHERE q.client_id = ?
        ORDER BY q.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute([$clientId]);
    $recentQuotations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
/* ── RESPONSIVE DASHBOARD ── */
@media (max-width: 1024px) {
    .erp-page-header { padding: 20px; }
}

@media (max-width: 768px) {
    .erp-page-title { font-size: 20px; }
    
    /* Stats cards - 2 columns on tablet, 1 on small mobile */
    .stats-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }
    
    /* Recent activity table */
    .erp-table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    /* Quick links cards */
    .erp-card-body {
        padding: 20px !important;
    }
}

@media (max-width: 480px) {
    .erp-page-title { font-size: 18px; }
    .erp-breadcrumb { font-size: 12px; }
    
    /* Single column for stats */
    .stats-grid {
        grid-template-columns: 1fr !important;
    }
    
    /* Stack action buttons */
    .erp-flex {
        flex-direction: column;
        gap: 8px;
    }
    
    .erp-btn {
        width: 100%;
        text-align: center;
    }
}
</style>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Dashboard</span>
    </div>
    <h1 class="erp-page-title">Welcome, <?php echo htmlspecialchars($clientName); ?></h1>
</div>

<?php if ($noClientRecord): ?>
<!-- No Client Record Message -->
<div class="erp-card erp-mb-4" style="border-left: 4px solid #EF4444; text-align: center; padding: 40px;">
    <i class="fas fa-user-slash" style="font-size: 64px; color: #EF4444; margin-bottom: 20px;"></i>
    <h2 style="font-size: 24px; font-weight: 700; color: #1A1A1A; margin-bottom: 15px;">No Documents Found</h2>
    <p style="font-size: 16px; color: #6B7280; max-width: 500px; margin: 0 auto;">
        Your account is not yet linked to any documents. Please contact <strong>SV Auto Services</strong>.
    </p>
</div>
<?php else: ?>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="erp-card" style="text-align: center; border-top: 4px solid #F7A100;">
        <div class="erp-card-body" style="padding: 30px;">
            <div style="font-size: 40px; font-weight: 700; color: #F7A100;"><?php echo $pendingQuotations; ?></div>
            <div style="font-size: 14px; color: #6B7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Pending Quotations</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #10B981;">
        <div class="erp-card-body" style="padding: 30px;">
            <div style="font-size: 40px; font-weight: 700; color: #10B981;"><?php echo $approvedQuotations; ?></div>
            <div style="font-size: 14px; color: #6B7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Approved Quotations</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #EF4444;">
        <div class="erp-card-body" style="padding: 30px;">
            <div style="font-size: 40px; font-weight: 700; color: #EF4444;"><?php echo $unpaidInvoices; ?></div>
            <div style="font-size: 14px; color: #6B7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Unpaid Invoices</div>
        </div>
    </div>
    <div class="erp-card" style="text-align: center; border-top: 4px solid #2563EB;">
        <div class="erp-card-body" style="padding: 30px;">
            <div style="font-size: 40px; font-weight: 700; color: #2563EB;"><?php echo formatMoney($totalSpent); ?></div>
            <div style="font-size: 14px; color: #6B7280; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">Total Spent</div>
        </div>
    </div>
</div>

<!-- Recent Quotations -->
<div class="erp-card erp-mb-4" style="border-left: 4px solid #F7A100;">
    <div class="erp-card-header" style="padding: 16px 20px; border-bottom: 1px solid var(--gray-200);">
        <h3 class="erp-card-title" style="font-size: 14px; font-weight: 700; color: #1A1A1A; text-transform: uppercase; letter-spacing: 0.5px;">
            <i class="fas fa-file-invoice" style="color: #F7A100; margin-right: 8px;"></i>Recent Quotations
        </h3>
        <a href="quotations.php" class="erp-btn erp-btn-primary" style="padding: 6px 16px; font-size: 12px;">View All</a>
    </div>
    <div class="erp-table-container">
        <table class="erp-table">
            <thead>
                <tr>
                    <th style="background: #EBF4FF; color: #2563EB;">ID</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Job Card</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Date</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Amount</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Status</th>
                    <th style="background: #EBF4FF; color: #2563EB;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentQuotations)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: #6B7280;">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>No quotations found.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($recentQuotations as $quote): ?>
                    <tr>
                        <td style="font-weight: 600;">#<?php echo $quote['id']; ?></td>
                        <td><?php echo htmlspecialchars($quote['job_card_number'] ?? 'N/A'); ?></td>
                        <td><?php echo $quote['submitted_at'] ? date('d M Y', strtotime($quote['submitted_at'])) : 'N/A'; ?></td>
                        <td><?php echo formatMoney($quote['amount'] ?? 0); ?></td>
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

<!-- Quick Actions -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
    <div class="erp-card" style="border-left: 4px solid #F7A100;">
        <div class="erp-card-body" style="padding: 25px;">
            <h4 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin-bottom: 10px;">
                <i class="fas fa-file-invoice" style="color: #F7A100; margin-right: 8px;"></i>Manage Quotations
            </h4>
            <p style="color: #6B7280; font-size: 14px; margin-bottom: 15px;">View and respond to your service quotations.</p>
            <a href="quotations.php" class="erp-btn erp-btn-primary" style="padding: 8px 20px; font-size: 13px;">Go to Quotations</a>
        </div>
    </div>
    <div class="erp-card" style="border-left: 4px solid #2563EB;">
        <div class="erp-card-body" style="padding: 25px;">
            <h4 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin-bottom: 10px;">
                <i class="fas fa-receipt" style="color: #2563EB; margin-right: 8px;"></i>View Invoices
            </h4>
            <p style="color: #6B7280; font-size: 14px; margin-bottom: 15px;">Check your invoices and payment status.</p>
            <a href="invoices.php" class="erp-btn erp-btn-primary" style="padding: 8px 20px; font-size: 13px;">Go to Invoices</a>
        </div>
    </div>
    <div class="erp-card" style="border-left: 4px solid #10B981;">
        <div class="erp-card-body" style="padding: 25px;">
            <h4 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin-bottom: 10px;">
                <i class="fas fa-user" style="color: #10B981; margin-right: 8px;"></i>Update Profile
            </h4>
            <p style="color: #6B7280; font-size: 14px; margin-bottom: 15px;">Keep your contact information up to date.</p>
            <a href="profile.php" class="erp-btn erp-btn-primary" style="padding: 8px 20px; font-size: 13px;">Go to Profile</a>
        </div>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
