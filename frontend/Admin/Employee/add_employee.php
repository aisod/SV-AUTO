<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$business = getBusiness();

$positions = ['Technician','Senior Mechanic','Junior Mechanic','Accountant','HR Manager','Receptionist','Sales Executive','Inventory Manager','Workshop Foreman','Driver','Cleaner','Security'];

// PROCESS FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $status   = $_POST['status'] ?? 'active';
    $details  = trim($_POST['details'] ?? '');

    if (empty($name) || empty($position)) {
        $error = "Name and Position are required";
    } else {
        $photo_url = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/employees/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $filename = 'emp_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $dest = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
                    $photo_url = 'uploads/employees/' . $filename;
                }
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO employees (name, position, status, photo_url, certificates, licenses, ids, uniforms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $position, $status, $photo_url, $details, $details, $details, $details]);

            $emp_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'created employee', 'employee', ?)")
                ->execute([$_SESSION['user_id'], $emp_id]);

            header('Location: employees.php?success=Employee added successfully!');
            exit;
        } catch (Exception $e) {
            $error = "Failed to add employee. Please try again.";
        }
    }
}

$success = $_GET['success'] ?? '';
$error   = $error ?? $_GET['error'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <h1 class="erp-page-title">Add New Team Member</h1>
</div>

<!-- Alert Messages -->
<?php if ($success): ?>
    <div class="erp-alert erp-alert-success erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-check-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Success</div>
            <?php echo htmlspecialchars($success); ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="erp-alert erp-alert-danger erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Error</div>
            <?php echo htmlspecialchars($error); ?>
        </div>
    </div>
<?php endif; ?>

<form action="" method="POST" enctype="multipart/form-data">

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
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">NEW TEAM MEMBER</div>
        <div><i class="fas fa-user-plus" style="color:#F7A100; font-size:22px;"></i></div>
    </div>

    <!-- ERROR ALERT -->
    <?php if ($error): ?>
        <div style="padding:12px 16px;">
            <div class="erp-alert erp-alert-danger erp-mb-4">
                <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="erp-alert-content">
                    <div class="erp-alert-title">Error</div>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- PHOTO UPLOAD ROW -->
    <div style="padding:12px 16px; border-bottom:2px solid #000; background:#FFF8F0;">
        <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:8px; letter-spacing:1px;">PROFILE PHOTO</div>
        <input type="file" name="photo" accept="image/*"
            style="border:1.5px solid #F7A100; border-radius:6px; padding:8px 12px; font-size:13px; width:100%; box-sizing:border-box;">
        <small style="font-size:11px; color:#888; display:block; margin-top:4px;">JPG, PNG, GIF up to 5MB</small>
    </div>

    <!-- TWO-COLUMN FIELDS ROW 1 -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">
        <!-- LEFT: FULL NAME -->
        <div style="border-right:2px solid #000; padding:12px 16px;">
            <label style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">FULL NAME</label>
            <input type="text" name="name" required placeholder="e.g. Johannes Shikongo"
                style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px; font-size:13px; font-weight:600; background:transparent; outline:none; box-sizing:border-box;">
        </div>
        <!-- RIGHT: POSITION -->
        <div style="padding:12px 16px;">
            <label style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">POSITION</label>
            <select name="position" required
                style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px; font-size:13px; font-weight:600; background:transparent; outline:none;">
                <option value="">Choose Position</option>
                <?php foreach ($positions as $pos): ?>
                    <option value="<?php echo htmlspecialchars($pos); ?>"><?php echo htmlspecialchars($pos); ?></option>
                <?php endforeach; ?>
                <option value="__add_new__" style="color:#F7A100; font-weight:700;">＋ Add New Position</option>
            </select>
            <div id="newPositionRow" style="display:none; margin-top:10px;">
                <input type="text" id="newPositionInput"
                    placeholder="Type new position title e.g. Paint Technician"
                    style="width:100%; border:none; border-bottom:1.5px solid #F7A100; padding:6px; font-size:13px; font-weight:600; background:transparent; outline:none; box-sizing:border-box;">
                <small style="font-size:11px; color:#888; display:block; margin-top:4px;">This position will be used for this employee only.</small>
            </div>
        </div>
    </div>

    <!-- TWO-COLUMN FIELDS ROW 2 -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">
        <!-- LEFT: CURRENT STATUS -->
        <div style="border-right:2px solid #000; padding:12px 16px;">
            <label style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">CURRENT STATUS</label>
            <select name="status" required
                style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px; font-size:13px; font-weight:600; background:transparent; outline:none;">
                <option value="active">Active (In Workshop)</option>
                <option value="on_leave">On Leave</option>
                <option value="on_field">On Field / Delivery</option>
            </select>
        </div>
        <!-- RIGHT: CERTIFICATES, LICENSES & NOTES -->
        <div style="padding:12px 16px;">
            <label style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">CERTIFICATES, LICENSES & NOTES</label>
            <textarea name="details" rows="4" placeholder="Driver's license, safety certs, uniform size, ID number, etc."
                style="width:100%; border:1px solid #ddd; border-radius:4px; padding:8px; font-size:13px; font-family:Arial,sans-serif; resize:vertical; box-sizing:border-box;"></textarea>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
        <a href="employees.php" style="background:#6B7280; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" style="background:#F7A100; color:white; border:none; border-radius:8px; padding:10px 28px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(247,161,0,0.4);">
            <i class="fas fa-user-plus"></i> Add to Team
        </button>
    </div>

</div>
</form>

<script>
const positionSelect = document.querySelector('select[name="position"]');
const newPositionRow = document.getElementById('newPositionRow');
const newPositionInput = document.getElementById('newPositionInput');

positionSelect.addEventListener('change', function() {
    if (this.value === '__add_new__') {
        newPositionRow.style.display = 'block';
        newPositionInput.focus();
    } else {
        newPositionRow.style.display = 'none';
        newPositionInput.value = '';
    }
});

document.querySelector('form').addEventListener('submit', function(e) {
    if (positionSelect.value === '__add_new__') {
        const custom = newPositionInput.value.trim();
        if (!custom) {
            e.preventDefault();
            newPositionInput.style.borderBottomColor = '#c62828';
            newPositionInput.placeholder = 'Please type a position title!';
            newPositionInput.focus();
            return;
        }
        const opt = document.createElement('option');
        opt.value = custom;
        opt.selected = true;
        positionSelect.appendChild(opt);
        positionSelect.value = custom;
    }
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
