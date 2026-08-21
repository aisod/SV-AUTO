<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: expenses.php?error=Invalid expense ID');
    exit;
}

$expense_id = (int)$_GET['id'];

// Fetch expense + related data
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.purchase_order_id,
        e.amount,
        e.description,
        e.created_at,
        po.amount AS po_amount,
        jc.card_number,
        c.name AS client_name,
        COALESCE(v.reg_no, 'Walk-in') AS reg_no
    FROM expenses e
    JOIN purchase_orders po ON e.purchase_order_id = po.id
    JOIN job_cards jc ON po.job_card_id = jc.id
    JOIN quotations q ON jc.id = q.job_card_id
    JOIN clients c ON q.client_id = c.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE e.id = ?
    LIMIT 1
");
$stmt->execute([$expense_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    header('Location: expenses.php?error=Expense not found');
    exit;
}

$business = getBusiness();
$expense_number = 'EXP-' . str_pad($expense['id'], 4, '0', STR_PAD_LEFT);

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <h1 class="erp-page-title">Edit Expense Record</h1>
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

<form id="expenseForm" action="update_expense.php" method="POST">

<!-- EXPENSE DOCUMENT SHELL -->
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
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">EDIT EXPENSE</div>
        <div style="text-align:right;">
            <div style="font-size:15px; font-weight:900; letter-spacing:2px;"><?php echo $expense_number; ?></div>
            <div style="font-size:11px; color:#888; margin-top:2px;">EXPENSE RECORD</div>
        </div>
    </div>

    <!-- LINKED INFO BANNER -->
    <div style="padding:12px 16px; border-bottom:2px solid #000; background:#FFF8F0;">
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:12px;">
            <i class="fas fa-info-circle" style="color:#F7A100;"></i>
            <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:#555;">LINKED INFORMATION</span>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
            <div>
                <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Job Card</span>
                <span style="font-size:13px; font-weight:700; color:#1A1A1A;"><?php echo htmlspecialchars($expense['card_number']); ?></span>
            </div>
            <div>
                <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Client / Vehicle</span>
                <span style="font-size:13px; font-weight:700; color:#1A1A1A;"><?php echo htmlspecialchars($expense['client_name'] . ' / ' . $expense['reg_no']); ?></span>
            </div>
            <div>
                <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Purchase Order Amount</span>
                <span style="font-size:13px; font-weight:700; color:#1A1A1A;"><?php echo formatMoney($expense['po_amount']); ?></span>
            </div>
            <div>
                <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Recorded On</span>
                <span style="font-size:13px; font-weight:700; color:#1A1A1A;"><?php echo date('d F Y \a\t H:i', strtotime($expense['created_at'])); ?></span>
            </div>
        </div>
    </div>

    <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">

    <!-- TWO-COLUMN FIELDS SECTION -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">
        <!-- LEFT: EXPENSE AMOUNT -->
        <div style="border-right:2px solid #000; padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">EXPENSE AMOUNT</div>
            <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Amount</label>
            <input type="number" name="amount" step="0.01" required placeholder="0.00"
                value="<?php echo number_format($expense['amount'], 2, '.', ''); ?>"
                style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px; font-size:13px; font-weight:600; background:transparent; outline:none; box-sizing:border-box;">
        </div>
        <!-- RIGHT: DESCRIPTION -->
        <div style="padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">DESCRIPTION</div>
            <textarea name="description" rows="4" required
                style="width:100%; border:1px solid #ddd; border-radius:4px; padding:8px; font-size:13px; font-family:Arial,sans-serif; resize:vertical; box-sizing:border-box;"><?php echo htmlspecialchars($expense['description']); ?></textarea>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
        <a href="expenses.php" style="background:#6B7280; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-arrow-left"></i> Cancel
        </a>
        <button type="button" onclick="showConfirmModal()" style="background:#F7A100; color:white; border:none; border-radius:8px; padding:10px 28px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(247,161,0,0.4);">
            <i class="fas fa-save"></i> Update Expense
        </button>
    </div>

</div>
</form>

<!-- Confirmation Modal -->
<div class="erp-modal-overlay" id="confirmModal">
    <div class="erp-modal">
        <div class="erp-modal-header">
            <h3 class="erp-modal-title">Confirm Update</h3>
            <button class="erp-modal-close" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="erp-modal-body" style="text-align: center;">
            <div style="width: 80px; height: 80px; background: var(--status-warning-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-question-circle" style="font-size: 36px; color: var(--status-warning);"></i>
            </div>
            <p style="color: var(--gray-600); margin-bottom: 16px;">
                Are you sure you want to update this expense record?
            </p>
        </div>
        <div class="erp-modal-footer">
            <button class="erp-btn erp-btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="erp-btn erp-btn-primary" onclick="submitForm()">Update</button>
        </div>
    </div>
</div>

<script>
    function showConfirmModal() {
        document.getElementById('confirmModal').classList.add('show');
    }
    
    function closeModal() {
        document.getElementById('confirmModal').classList.remove('show');
    }
    
    function submitForm() {
        document.getElementById('expenseForm').submit();
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('confirmModal');
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


<script>
(function() {
    const PAGE_KEY = 'draft_' + window.location.pathname + window.location.search;
    const form = document.querySelector('form');
    if (!form) return;

    // Restore draft on page load
    const saved = localStorage.getItem(PAGE_KEY);
    if (saved) {
        try {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(name => {
                const fields = form.querySelectorAll(`[name="${name}"]`);
                fields.forEach(field => {
                    if (!field) return;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = data[name] === true || data[name] === field.value;
                    } else if (field.tagName === 'SELECT') {
                        field.value = data[name];
                        field.dispatchEvent(new Event('change'));
                    } else {
                        field.value = data[name];
                        field.dispatchEvent(new Event('input'));
                    }
                });
            });
            // Show restored notice
            const notice = document.createElement('div');
            notice.innerHTML = '📝 <strong>Draft restored</strong> — your unsaved changes have been recovered.';
            notice.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#F7A100; color:white; padding:14px 22px; border-radius:12px; font-size:14px; z-index:99999; box-shadow:0 6px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;';
            const closeBtn = document.createElement('span');
            closeBtn.textContent = '✕';
            closeBtn.style.cssText = 'cursor:pointer; margin-left:10px; font-size:16px; opacity:0.8;';
            closeBtn.onclick = () => notice.remove();
            notice.appendChild(closeBtn);
            document.body.appendChild(notice);
            setTimeout(() => notice.remove(), 5000);
        } catch(e) {}
    }

    // Auto-save on any input change
    let saveTimeout;
    function doSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const data = {};
            form.querySelectorAll('input, select, textarea').forEach(field => {
                if (!field.name) return;
                if (field.type === 'password' || field.type === 'file' || field.type === 'hidden') return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    data[field.name] = field.checked;
                } else {
                    data[field.name] = field.value;
                }
            });
            localStorage.setItem(PAGE_KEY, JSON.stringify(data));

            // Show saved indicator
            let indicator = document.getElementById('draft-save-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'draft-save-indicator';
                indicator.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#22C55E; color:white; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.15); opacity:0; transition:opacity 0.3s;';
                document.body.appendChild(indicator);
            }
            indicator.textContent = '✓ Draft saved';
            indicator.style.opacity = '1';
            clearTimeout(indicator._hideTimer);
            indicator._hideTimer = setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
        }, 800);
    }

    form.addEventListener('input', doSave);
    form.addEventListener('change', doSave);

    // Clear draft on successful form submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(PAGE_KEY);
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
