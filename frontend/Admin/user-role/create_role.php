<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$business = getBusiness();

// PROCESS FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name)) {
        $error = "Role name is required";
    } elseif ($pdo->query("SELECT COUNT(*) FROM roles WHERE name = " . $pdo->quote($name))->fetchColumn() > 0) {
        $error = "Role name already exists";
    } else {
        $perms_string = !empty($permissions) ? implode(',', array_map('trim', $permissions)) : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO roles (name, permissions) VALUES (?, ?)");
            $stmt->execute([$name, $perms_string]);

            $role_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'created role', 'role', ?)")
                ->execute([$_SESSION['user_id'], $role_id]);

            header('Location: user_roles.php?success=Role created successfully!');
            exit;
        } catch (Exception $e) {
            $error = "Failed to create role";
        }
    }
}

$success = $_GET['success'] ?? '';
$error   = $error ?? $_GET['error'] ?? '';
?>

<?php include __DIR__ . '/../sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Role • <?= htmlspecialchars($business['name'] ?? 'SV Auto') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root{--p:#F5A623;--s:#1a1a1a;--a:#FFF8EC;--t:#1a1a1a;--g:#2e7d32;--r:#c62828;--light:#FFF8F0;}
        .page-title{font-size:42px;color:var(--s);font-weight:900;margin:0 0 50px;text-shadow:2px 2px 12px rgba(139,69,19,0.25);text-align:center;}
        .form-container{max-width:1000px;margin:0 auto;background:white;padding:60px 80px;border-radius:32px;box-shadow:0 40px 100px rgba(139,69,19,0.3);border-top:12px solid var(--p);position:relative;overflow:hidden;}
        .form-header{background:linear-gradient(135deg,var(--p),var(--s));color:white;padding:40px 60px;text-align:center;margin:-60px -80px 50px;border-radius:32px 32px 0 0;}
        .form-header h2{font-size:38px;font-weight:900;letter-spacing:3px;margin:0;}
        .form-header p{font-size:18px;opacity:0.95;margin-top:12px;}

        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:32px;margin-bottom:32px;}
        label{display:block;margin-bottom:12px;font-weight:800;color:var(--s);font-size:17px;display:flex;align-items:center;gap:10px;}
        input[type="text"]{width:100%;padding:18px 20px;border:3px solid var(--a);border-radius:16px;font-size:16px;transition:all .4s;background:#FFF8F0;}
        input:focus{outline:none;border-color:var(--p);box-shadow:0 0 0 6px rgba(210,105,30,0.2);}

        .permissions-grid{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;
            padding:30px;background:#FFF8F0;border-radius:20px;border:3px dashed var(--a);
        }
        .perm-item{
            display:flex;align-items:center;gap:14px;background:white;padding:18px 20px;
            border-radius:16px;box-shadow:0 6px 20px rgba(139,69,19,0.08);cursor:pointer;transition:all .4s;
            font-size:16px;font-weight:600;color:var(--t);
        }
        .perm-item:hover{background:#ffe4c4;transform:translateY(-4px);}
        .perm-item input[type="checkbox"]{
            width:22px;height:22px;accent-color:var(--p);cursor:pointer;
        }

        .btn-group{display:flex;justify-content:center;gap:20px;margin-top:50px;}
        .btn{
            padding:18px 50px;border:none;border-radius:50px;font-size:18px;font-weight:900;
            cursor:pointer;transition:all .5s;box-shadow:0 15px 40px rgba(0,0,0,0.2);
            display:inline-flex;align-items:center;gap:12px;
        }
        .btn-create{background:linear-gradient(135deg,var(--p),var(--s));color:white;}
        .btn-cancel{background:#6d4c41;color:white;}
        .btn:hover{transform:translateY(-8px);box-shadow:0 25px 60px rgba(0,0,0,0.3);}

        .alert{padding:25px;border-radius:20px;margin:30px 0;font-size:18px;font-weight:700;text-align:center;border:4px solid;}
        .alert-success{background:#e8f5e8;color:var(--g);border-color:#4caf50;}
        .alert-error{background:#ffebee;color:var(--r);border-color:#f44336;}
    </style>
</head>
<body>

<div class="content-wrapper">

    <h2 class="page-title">Create New Role</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-container">
        <div class="form-header">
            <h2>DEFINE ACCESS LEVEL</h2>
            <p>Create a new user role with specific permissions</p>
        </div>

        <form action="" method="POST">
            <div class="form-grid">
                <div>
                    <label>Role Name</label>
                    <input type="text" name="name" placeholder="e.g. Workshop Supervisor" required>
                </div>
            </div>

            <div style="margin-top:30px;">
                <label>Select Permissions</label>
                <div class="permissions-grid">
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[]" value="full_access" id="full">
                        <label for="full">Full System Access (Admin)</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[]" value="job_cards" id="jc">
                        <label for="jc">Manage Job Cards</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[]" value="invoices" id="inv">
                        <label for="inv">Create & Manage Invoices</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[]" value="employees" id="emp">
                        <label for="emp">Manage Employees & HR</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[]" value="reports" id="rep">
                        <label for="rep">View Reports & Analytics</label>
                    </div>
                    <div class="perm-item">
                        <input type="checkbox" name="permissions[]" value="authorize" id="auth">
                        <label for="auth">Authorize Payments & Orders</label>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-create">
                    CREATE ROLE
                </button>
                <button type="button" onclick="history.back()" class="btn btn-cancel">
                    CANCEL
                </button>
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
})();
</script>

</body>
</html>
