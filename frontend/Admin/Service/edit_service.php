<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: services.php?error=Invalid service ID');
    exit;
}

$service_id = (int)$_GET['id'];

// Fetch current service + vehicle + client
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.vehicle_id,
        s.service_date,
        s.status,
        s.notes,
        c.id AS client_id,
        c.name AS client_name,
        v.reg_no,
        v.model,
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
    header('Location: services.php?error=Service not found');
    exit;
}

$business = getBusiness();
$service_number = 'SVC-' . str_pad($service['id'], 4, '0', STR_PAD_LEFT);

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <h1 class="erp-page-title">Edit Service Schedule</h1>
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

<!-- Service Edit Card -->
<div class="erp-card erp-mb-4" style="max-width:900px;">
    <div class="erp-card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="erp-card-title">
            <i class="fas fa-edit"></i> Edit Service — <?php echo $service_number; ?>
        </h2>
        <?php
        $badgeClass = $service['status'] === 'approved' ? 'erp-badge-success' : 'erp-badge-warning';
        ?>
        <span class="erp-badge <?php echo $badgeClass; ?>" style="font-size:13px; padding:6px 16px;">
            <?php echo ucfirst($service['status']); ?>
        </span>
    </div>
    <div class="erp-card-body">
        <form action="update_service.php" method="POST">
            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">

            <!-- Vehicle Info Banner -->
            <div class="erp-alert erp-alert-info erp-mb-4">
                <div class="erp-alert-icon"><i class="fas fa-car"></i></div>
                <div class="erp-alert-content">
                    <strong>Client:</strong> <?php echo htmlspecialchars($service['client_name']); ?> &nbsp;|&nbsp;
                    <strong>Vehicle:</strong> <?php echo htmlspecialchars($service['reg_no']); ?> • <?php echo htmlspecialchars($service['model']); ?><br>
                    <strong>Last Service:</strong> <?php echo $service['last_service_date'] ? date('d F Y', strtotime($service['last_service_date'])) : 'Never recorded'; ?>
                </div>
            </div>

            <!-- Fields -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                <div>
                    <label class="erp-label">New Service Date</label>
                    <input type="date" name="service_date" class="erp-input" 
                        value="<?php echo $service['service_date']; ?>" required>
                </div>
                <div>
                    <label class="erp-label">Status</label>
                    <select name="status" class="erp-select">
                        <option value="pending" <?php echo $service['status'] === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                        <option value="approved" <?php echo $service['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label class="erp-label">Notes / Recommended Services</label>
                <textarea name="notes" class="erp-textarea" rows="5" 
                    placeholder="e.g. Full service • Replace filters • Brake inspection • Alignment"><?php echo htmlspecialchars($service['notes'] ?? ''); ?></textarea>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
                <a href="services_update.php" class="erp-btn erp-btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancel
                </a>
                <div style="display:flex; gap:12px;">
                    <a href="view_service.php?id=<?php echo $service['id']; ?>" class="erp-btn erp-btn-secondary">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <button type="button" class="erp-btn erp-btn-primary" onclick="showConfirmModal()">
                        <i class="fas fa-save"></i> Update Schedule
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="erp-modal-overlay" id="confirmModal">
    <div class="erp-modal">
        <div class="erp-modal-header">
            <h3 class="erp-modal-title">Confirm Update</h3>
            <button class="erp-modal-close" onclick="closeConfirmModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="erp-modal-body" style="text-align: center;">
            <div style="width: 80px; height: 80px; background: var(--status-warning-bg); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <i class="fas fa-question-circle" style="font-size: 36px; color: var(--status-warning);"></i>
            </div>
            <p style="color: var(--gray-600); margin-bottom: 16px;">
                Are you sure you want to update this service schedule? This will save all changes you've made.
            </p>
        </div>
        <div class="erp-modal-footer">
            <button class="erp-btn erp-btn-secondary" onclick="closeConfirmModal()">Cancel</button>
            <button class="erp-btn erp-btn-primary" onclick="submitForm()">Yes, Update Schedule</button>
        </div>
    </div>
</div>

<script>
function showConfirmModal() {
    document.getElementById('confirmModal').classList.add('show');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

function submitForm() {
    document.querySelector('form').submit();
}

// Close modal when clicking outside
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeConfirmModal();
    }
});

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
