<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$role_id = $_GET['id'] ?? 0;
$role_id = is_numeric($role_id) ? (int)$role_id : 0;

if ($role_id <= 0) {
    header('Location: user_roles.php?error=Invalid role');
    exit;
}

// Fetch role
$stmt = $pdo->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$role_id]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    header('Location: user_roles.php?error=Role not found');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name)) {
        $error = "Role name is required.";
    } else {
        $permsString = !empty($permissions) ? implode(',', array_map('trim', $permissions)) : '';

        $update = $pdo->prepare("UPDATE roles SET name = ?, permissions = ? WHERE id = ?");
        $update->execute([$name, $permsString, $role_id]);

        header("Location: user_roles.php?success=Role updated successfully");
        exit;
    }
}

$currentPerms = $role['permissions'] 
    ? array_flip(array_filter(array_map('trim', explode(',', $role['permissions'])))) 
    : [];

$success = $_GET['success'] ?? '';
$error = $error ?? ($_GET['error'] ?? '');
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="user_roles.php">User Roles</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Edit Role</span>
    </div>
    <h1 class="erp-page-title">
        <i class="fas fa-user-shield"></i>
        Edit Role: <?= htmlspecialchars($role['name']) ?>
    </h1>
</div>

<?php if ($success): ?>
    <div class="erp-alert erp-alert-success erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-check-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Success</div>
            <?= htmlspecialchars($success) ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="erp-alert erp-alert-danger erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Error</div>
            <?= htmlspecialchars($error) ?>
        </div>
    </div>
<?php endif; ?>

<div class="erp-card">
    <div class="erp-card-header">
        <h2 class="erp-card-title">
            <i class="fas fa-edit"></i>
            Role Information
        </h2>
    </div>
    <div class="erp-card-body">
        <form action="edit_role.php?id=<?= $role['id'] ?>" method="POST">
            <div class="erp-form-grid">
                <div class="erp-form-group">
                    <label class="erp-label" for="role_name">
                        <i class="fas fa-tag"></i>
                        Role Name
                        <span class="erp-required">*</span>
                    </label>
                    <input type="text" 
                           id="role_name"
                           name="name" 
                           class="erp-input" 
                           value="<?= htmlspecialchars($role['name']) ?>" 
                           placeholder="Enter role name"
                           required>
                </div>
            </div>

            <div class="erp-form-group" style="margin-top: 24px;">
                <label class="erp-label">
                    <i class="fas fa-key"></i>
                    Permissions
                </label>
                <div class="erp-permissions-grid">
                    <label class="erp-checkbox-card">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="full_access" 
                               <?= isset($currentPerms['full_access']) ? 'checked' : '' ?>>
                        <div class="erp-checkbox-content">
                            <i class="fas fa-crown"></i>
                            <span>Full System Access</span>
                        </div>
                    </label>

                    <label class="erp-checkbox-card">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="job_cards" 
                               <?= isset($currentPerms['job_cards']) ? 'checked' : '' ?>>
                        <div class="erp-checkbox-content">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Manage Job Cards</span>
                        </div>
                    </label>

                    <label class="erp-checkbox-card">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="invoices" 
                               <?= isset($currentPerms['invoices']) ? 'checked' : '' ?>>
                        <div class="erp-checkbox-content">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Manage Invoices</span>
                        </div>
                    </label>

                    <label class="erp-checkbox-card">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="employees" 
                               <?= isset($currentPerms['employees']) ? 'checked' : '' ?>>
                        <div class="erp-checkbox-content">
                            <i class="fas fa-users"></i>
                            <span>Manage Employees</span>
                        </div>
                    </label>

                    <label class="erp-checkbox-card">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="reports" 
                               <?= isset($currentPerms['reports']) ? 'checked' : '' ?>>
                        <div class="erp-checkbox-content">
                            <i class="fas fa-chart-bar"></i>
                            <span>View Reports</span>
                        </div>
                    </label>

                    <label class="erp-checkbox-card">
                        <input type="checkbox" 
                               name="permissions[]" 
                               value="authorize" 
                               <?= isset($currentPerms['authorize']) ? 'checked' : '' ?>>
                        <div class="erp-checkbox-content">
                            <i class="fas fa-check-circle"></i>
                            <span>Authorize Actions</span>
                        </div>
                    </label>
                </div>
            </div>

            <div class="erp-form-actions">
                <button type="submit" class="erp-btn erp-btn-primary">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
                <a href="user_roles.php" class="erp-btn erp-btn-secondary">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>


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
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
