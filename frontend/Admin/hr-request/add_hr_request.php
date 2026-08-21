<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_admin();

$business  = getBusiness();
$error     = '';
$success   = '';

// Load employees for dropdown
$employees = $pdo->query("
    SELECT id, name, position 
    FROM employees 
    WHERE status != 'on_leave'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

// HANDLE FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = (int)($_POST['employee_id'] ?? 0);
    $type        = trim($_POST['type'] ?? '');
    $details     = trim($_POST['details'] ?? '');

    $valid_types = ['overtime', 'loan', 'leave', 'warning'];

    if ($employee_id <= 0) {
        $error = "Please select an employee.";
    } elseif (!in_array($type, $valid_types)) {
        $error = "Please select a valid request type.";
    } elseif (empty($details)) {
        $error = "Please provide details for this request.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO hr_forms (employee_id, type, details, status, submitted_at) 
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$employee_id, $type, $details]);
            $new_id = $pdo->lastInsertId();

            // Audit log
            $log = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action, entity_type, entity_id) 
                VALUES (?, 'created_hr_request', 'hr_form', ?)
            ");
            $log->execute([$_SESSION['user_id'], $new_id]);

            header("Location: hr_requests.php?success=HR Request created successfully. Manager will be notified.");
            exit;
        } catch (Exception $e) {
            $error = "Failed to create request. Please try again.";
        }
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="hr_requests.php">HR Requests</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="add_hr_request.php">Add HR Request</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">New Request</span>
    </div>
    <div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
        <h1 class="erp-page-title">New HR Request</h1>
    </div>
</div>

<style>
.type-card {
    padding:16px 12px; border-radius:8px; text-align:center; cursor:pointer;
    border:2px solid #e5e7eb; background:white; transition:all .3s;
    display:block;
}
.type-card.selected, .type-card:has(input:checked) {
    border-color:#F7A100; background:#FFF8F0;
    box-shadow:0 4px 12px rgba(247,161,0,0.3);
}
</style>

<!-- HR REQUEST DOCUMENT SHELL -->
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
        <div style="font-size:22px; font-weight:900; text-transform:uppercase; letter-spacing:5px; text-transform:uppercase;">NEW HR REQUEST</div>
        <div><i class="fas fa-paper-plane" style="color:#F7A100; font-size:22px;"></i></div>
    </div>

    <!-- ERROR ALERT -->
    <?php if ($error): ?>
        <div style="padding:12px 16px;">
            <div class="erp-alert erp-alert-danger erp-mb-4">
                <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="erp-alert-content">
                    <div class="erp-alert-title">Error</div>
                    <?= htmlspecialchars($error) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- EMPLOYEE SELECTION ROW -->
    <div style="padding:12px 16px; border-bottom:2px solid #000; background:#FFF8F0;">
        <label style="font-size:11px; font-weight:700; text-transform:uppercase; color:#333;">
            <i class="fas fa-user" style="color:#F7A100;"></i> SELECT EMPLOYEE
        </label>
        <select name="employee_id" id="employeeSelect" required onchange="showEmployeeInfo(this)"
            style="border:1.5px solid #F7A100; border-radius:6px; padding:8px 12px; font-size:13px; font-weight:600; background:white; width:100%; outline:none; margin-top:8px;">
            <option value="">— Choose Employee —</option>
            <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"
                    data-position="<?= htmlspecialchars($emp['position'] ?? 'N/A') ?>">
                    <?= htmlspecialchars($emp['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div id="employeeInfo" style="display:none; margin-top:12px; background:white; border:1px solid #F7A100; border-radius:8px; padding:12px 16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Employee</span>
                    <span id="empName" style="font-size:14px; font-weight:700; color:#1A1A1A;"></span>
                </div>
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Position</span>
                    <span id="empPosition" style="font-size:14px; font-weight:700; color:#1A1A1A;"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- REQUEST TYPE ROW -->
    <div style="padding:16px; border-bottom:2px solid #000;">
        <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:12px; letter-spacing:1px;">REQUEST TYPE</div>
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:12px;">
            <label class="type-card" id="card-overtime">
                <input type="radio" name="type" value="overtime" required style="display:none;">
                <i class="fas fa-business-time" style="font-size:24px; color:#1565c0; margin-bottom:8px; display:block;"></i>
                <span style="font-weight:700; font-size:13px; color:#1A1A1A;">Overtime</span>
            </label>
            <label class="type-card" id="card-loan">
                <input type="radio" name="type" value="loan" style="display:none;">
                <i class="fas fa-hand-holding-usd" style="font-size:24px; color:#e65100; margin-bottom:8px; display:block;"></i>
                <span style="font-weight:700; font-size:13px; color:#1A1A1A;">Loan</span>
            </label>
            <label class="type-card" id="card-leave">
                <input type="radio" name="type" value="leave" style="display:none;">
                <i class="fas fa-umbrella-beach" style="font-size:24px; color:#6a1b9a; margin-bottom:8px; display:block;"></i>
                <span style="font-weight:700; font-size:13px; color:#1A1A1A;">Leave</span>
            </label>
            <label class="type-card" id="card-warning">
                <input type="radio" name="type" value="warning" style="display:none;">
                <i class="fas fa-exclamation-triangle" style="font-size:24px; color:#c62828; margin-bottom:8px; display:block;"></i>
                <span style="font-weight:700; font-size:13px; color:#1A1A1A;">Warning</span>
            </label>
        </div>
        <div id="selectedTypeBadge" style="display:none; margin-top:12px; padding:10px 16px; background:white; border:1px solid #F7A100; border-radius:8px; font-size:13px; font-weight:700; color:#1A1A1A;">
            <i class="fas fa-check-circle" style="color:#22C55E; margin-right:8px;"></i>
            Request Type Selected: <span id="selectedTypeLabel" style="color:#F7A100; text-transform:uppercase; letter-spacing:1px;"></span>
        </div>
    </div>

    <!-- DETAILS ROW -->
    <div style="padding:12px 16px; border-bottom:2px solid #000;">
        <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:8px; letter-spacing:1px;">REQUEST DETAILS</div>
        <textarea name="details" id="details" rows="5" required
            placeholder="Describe the request in detail...&#10;&#10;Example for Leave: Employee requesting 3 days annual leave from 10 April to 12 April 2026.&#10;Example for Overtime: 4 hours overtime on Saturday 5 April 2026."
            style="width:100%; border:1px solid #ddd; border-radius:4px; padding:10px; font-size:13px; font-family:Arial,sans-serif; resize:vertical; box-sizing:border-box; line-height:1.6;"></textarea>
    </div>

    <!-- ACTION BUTTONS -->
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
        <a href="hr_requests.php" style="background:#6B7280; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" style="background:#F7A100; color:white; border:none; border-radius:8px; padding:10px 28px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(247,161,0,0.4);">
            <i class="fas fa-paper-plane"></i> Submit Request
        </button>
    </div>

</div>

<script>
// Show employee info box
function showEmployeeInfo(select) {
    const option   = select.selectedOptions[0];
    const infoBox  = document.getElementById('employeeInfo');
    const empName  = document.getElementById('empName');
    const empPos   = document.getElementById('empPosition');

    if (select.value) {
        empName.textContent = option.text;
        empPos.textContent  = option.dataset.position;
        infoBox.style.display = 'block';
    } else {
        infoBox.style.display = 'none';
    }
}

// Type card selection highlight
document.querySelectorAll('input[name="type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.type-card').forEach(card => card.classList.remove('selected'));
        this.closest('.type-card').classList.add('selected');
        
        // Show selected type badge
        const badge = document.getElementById('selectedTypeBadge');
        const label = document.getElementById('selectedTypeLabel');
        if (badge && label) {
            badge.style.display = 'block';
            label.textContent = this.value.toUpperCase();
        }
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
