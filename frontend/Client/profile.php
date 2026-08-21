<?php
// Client Profile
$page_title = 'My Profile';
require_once __DIR__ . '/includes/header.php';

$clientId = $_SESSION['client_id'] ?? null;
$clientEmail = $_SESSION['email'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$message = '';
$error = '';

// Ensure we have client_id
if (!$clientId && $clientEmail) {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$clientEmail]);
    $clientId = $stmt->fetchColumn();
    if ($clientId) {
        $_SESSION['client_id'] = $clientId;
    }
}

// Fetch client data using user_id from session
$client = null;
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $dob = htmlspecialchars(trim($_POST['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8');
    $gender = htmlspecialchars(trim($_POST['gender'] ?? ''), ENT_QUOTES, 'UTF-8');
    $idPassport = htmlspecialchars(trim($_POST['id_passport'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone = trim($_POST['phone'] ?? '');
    $whatsapp = htmlspecialchars(trim($_POST['whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = trim($_POST['address'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate required fields
    if (empty($title) || empty($firstName) || empty($lastName)) {
        $error = "Title, first name and last name are required.";
    } elseif (!filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        try {
            // Update client record with all fields
            if ($clientId) {
                $stmt = $pdo->prepare("
                    UPDATE clients 
                    SET title = ?, first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, 
                        id_passport = ?, phone = ?, whatsapp = ?, address = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $firstName, $lastName, $dob ?: null, $gender, $idPassport, $phone, $whatsapp, $address, $clientId]);
            }
            
            // Update email in clients table if changed
            $newEmail = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            if ($newEmail && $newEmail !== $clientEmail) {
                // Check if email already exists
                $checkStmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id != ? LIMIT 1");
                $checkStmt->execute([$newEmail, $clientId]);
                if ($checkStmt->fetch()) {
                    $error = "This email is already in use by another account.";
                } else {
                    if ($clientId) {
                        $stmt = $pdo->prepare("UPDATE clients SET email = ? WHERE id = ?");
                        $stmt->execute([$newEmail, $clientId]);
                        $_SESSION['email'] = $newEmail;
                        $clientEmail = $newEmail;
                    }
                }
            }
            
            // Handle password change (only for non-Google users)
            if (!$error && !empty($newPassword) && empty($client['google_id'])) {
                if (strlen($newPassword) < 8) {
                    $error = "New password must be at least 8 characters.";
                } elseif ($newPassword !== $confirmPassword) {
                    $error = "New passwords do not match.";
                } else {
                    // Verify current password from clients table
                    $stmt = $pdo->prepare("SELECT password_hash FROM clients WHERE id = ? LIMIT 1");
                    $stmt->execute([$clientId]);
                    $clientData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($clientData && password_verify($currentPassword, $clientData['password_hash'])) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE clients SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$hashedPassword, $clientId]);
                    } else {
                        $error = "Current password is incorrect.";
                    }
                }
            }
            
            if (!$error) {
                $message = "Profile updated successfully.";
                
                // Refresh client data
                if ($clientId) {
                    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
                    $stmt->execute([$clientId]);
                    $client = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (Exception $e) {
            $error = "Failed to update profile. Please try again.";
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

$clientTitle = $client['title'] ?? '';
$clientFirstName = $client['first_name'] ?? '';
$clientLastName = $client['last_name'] ?? '';
$clientDob = $client['date_of_birth'] ?? '';
$clientGender = $client['gender'] ?? '';
$clientIdPassport = $client['id_passport'] ?? '';
$clientPhone = $client['phone'] ?? '';
$clientWhatsapp = $client['whatsapp'] ?? '';
$clientAddress = $client['address'] ?? '';
?>

<style>
/* ── RESPONSIVE PROFILE ── */
@media (max-width: 1024px) {
    .erp-page-header { padding: 20px; }
}

@media (max-width: 768px) {
    .erp-page-title { font-size: 20px; }
    
    /* Profile grid becomes single column */
    .profile-grid {
        grid-template-columns: 1fr !important;
        gap: 20px !important;
    }
    
    /* Form inputs full width */
    .erp-form-group input,
    .erp-form-group select,
    .erp-form-group textarea {
        width: 100%;
    }
    
    /* Stack action buttons */
    .erp-flex {
        flex-direction: column;
        gap: 10px;
    }
    
    .erp-btn {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 480px) {
    .erp-page-title { font-size: 18px; }
    .erp-breadcrumb { font-size: 12px; }
    
    .erp-card-body {
        padding: 20px !important;
    }
    
    .erp-form-group {
        margin-bottom: 15px;
    }
}
</style>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Profile</span>
    </div>
    <h1 class="erp-page-title">My Profile</h1>
</div>

<?php if ($message): ?>
<div class="erp-alert erp-alert-success erp-mb-4" style="background: #E8F5E8; border: 1px solid #4CAF50; color: #2E7D32; padding: 15px 20px; border-radius: 8px; font-weight: 600;">
    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="erp-alert erp-alert-error erp-mb-4" style="background: #FFEBEE; border: 1px solid #EF4444; color: #C62828; padding: 15px 20px; border-radius: 8px; font-weight: 600;">
    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
    <!-- Profile Information -->
    <div class="erp-card" style="border-left: 4px solid #F7A100;">
        <div class="erp-card-header" style="padding: 20px 25px; border-bottom: 1px solid var(--gray-200);">
            <h3 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin: 0;">
                <i class="fas fa-user" style="color: #F7A100; margin-right: 10px;"></i>Profile Information
            </h3>
        </div>
        <div class="erp-card-body" style="padding: 25px;">
            <form method="POST" id="profileForm">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Title *</label>
                    <select name="title" required style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                        <option value="">Select</option>
                        <option value="Mr" <?= ($client['title'] ?? '') === 'Mr' ? 'selected' : '' ?>>Mr</option>
                        <option value="Mrs" <?= ($client['title'] ?? '') === 'Mrs' ? 'selected' : '' ?>>Mrs</option>
                        <option value="Ms" <?= ($client['title'] ?? '') === 'Ms' ? 'selected' : '' ?>>Ms</option>
                        <option value="Dr" <?= ($client['title'] ?? '') === 'Dr' ? 'selected' : '' ?>>Dr</option>
                        <option value="Prof" <?= ($client['title'] ?? '') === 'Prof' ? 'selected' : '' ?>>Prof</option>
                        <option value="Eng" <?= ($client['title'] ?? '') === 'Eng' ? 'selected' : '' ?>>Eng</option>
                        <option value="Rev" <?= ($client['title'] ?? '') === 'Rev' ? 'selected' : '' ?>>Rev</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">First Name *</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($client['first_name'] ?? '') ?>" required 
                           style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Last Name *</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($client['last_name'] ?? '') ?>" required 
                           style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?= htmlspecialchars($client['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d') ?>"
                           style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Gender</label>
                    <select name="gender" style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                        <option value="">Select</option>
                        <option value="Male" <?= ($client['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($client['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Prefer not to say" <?= ($client['gender'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">ID or Passport Number</label>
                    <input type="text" name="id_passport" value="<?= htmlspecialchars($client['id_passport'] ?? '') ?>" 
                           style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Email Address *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($clientEmail); ?>" required 
                           style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Phone Number</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>" 
                           style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">WhatsApp Number</label>
                    <input type="tel" name="whatsapp" value="<?= htmlspecialchars($client['whatsapp'] ?? '') ?>" 
                           style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Physical Address</label>
                    <textarea name="address" rows="3" 
                              style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit; resize: vertical;"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="erp-btn erp-btn-primary" style="padding: 12px 30px; font-size: 14px;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </div>
    
    <!-- Change Password -->
    <?php if (!empty($client['google_id'])): ?>
    <div>
        <div class="erp-card" style="border-left: 4px solid #4CAF50;">
            <div class="erp-card-header" style="padding: 20px 25px; border-bottom: 1px solid var(--gray-200);">
                <h3 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin: 0;">
                    <i class="fas fa-shield-alt" style="color: #4CAF50; margin-right: 10px;"></i>Account Security
                </h3>
            </div>
            <div class="erp-card-body" style="padding: 25px;">
                <div style="background:#e8f5e9;border-left:4px solid #4CAF50;padding:16px 20px;border-radius:8px;">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                        <i class="fab fa-google" style="font-size:24px;color:#4CAF50;"></i>
                        <strong style="font-size:16px;color:#2E7D32;">You signed in with Google</strong>
                    </div>
                    <p style="margin:0;color:#2E7D32;font-size:14px;">Password management is handled by Google. You don't need to set a password for this account.</p>
                </div>
            </div>
        </div>
        
        <!-- Account Info -->
        <div class="erp-card" style="border-left: 4px solid #10B981; margin-top: 25px;">
            <div class="erp-card-header" style="padding: 20px 25px; border-bottom: 1px solid var(--gray-200);">
                <h3 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin: 0;">
                    <i class="fas fa-info-circle" style="color: #10B981; margin-right: 10px;"></i>Account Information
                </h3>
            </div>
            <div class="erp-card-body" style="padding: 25px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 11px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Account Type</label>
                    <div style="font-size: 15px; color: #1A1A1A;">Client Account</div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 11px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Client ID</label>
                    <div style="font-size: 15px; color: #1A1A1A; font-family: monospace;">#<?php echo $clientId ?? 'N/A'; ?></div>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Member Since</label>
                    <div style="font-size: 15px; color: #1A1A1A;">
                        <?= !empty($client['created_at']) ? date('d M Y', strtotime($client['created_at'])) : 'N/A' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Change Password -->
    <div>
        <div class="erp-card erp-mb-4" style="border-left: 4px solid #2563EB;">
            <div class="erp-card-header" style="padding: 20px 25px; border-bottom: 1px solid var(--gray-200);">
                <h3 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin: 0;">
                    <i class="fas fa-lock" style="color: #2563EB; margin-right: 10px;"></i>Change Password
                </h3>
            </div>
            <div class="erp-card-body" style="padding: 25px;">
                <form method="POST" id="passwordForm">
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Current Password</label>
                        <input type="password" name="current_password" 
                               style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">New Password</label>
                        <input type="password" name="new_password" minlength="8" 
                               placeholder="Minimum 8 characters"
                               style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-size: 12px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 8px;">Confirm New Password</label>
                        <input type="password" name="confirm_password" minlength="8"
                               style="width: 100%; padding: 12px 15px; border: 2px solid #E5E7EB; border-radius: 8px; font-size: 15px; font-family: inherit;">
                    </div>
                    
                    <button type="submit" class="erp-btn" style="padding: 12px 30px; font-size: 14px; background: #2563EB; color: white; border: none; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-key"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Account Info -->
        <div class="erp-card" style="border-left: 4px solid #10B981; margin-top: 25px;">
            <div class="erp-card-header" style="padding: 20px 25px; border-bottom: 1px solid var(--gray-200);">
                <h3 style="font-size: 16px; font-weight: 700; color: #1A1A1A; margin: 0;">
                    <i class="fas fa-info-circle" style="color: #10B981; margin-right: 10px;"></i>Account Information
                </h3>
            </div>
            <div class="erp-card-body" style="padding: 25px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 11px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Account Type</label>
                    <div style="font-size: 15px; color: #1A1A1A;">Client Account</div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 11px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Client ID</label>
                    <div style="font-size: 15px; color: #1A1A1A; font-family: monospace;">#<?php echo $clientId ?? 'N/A'; ?></div>
                </div>
                <div>
                    <label style="display: block; font-size: 11px; color: #6B7280; text-transform: uppercase; font-weight: 600; margin-bottom: 3px;">Member Since</label>
                    <div style="font-size: 15px; color: #1A1A1A;">
                        <?= !empty($client['created_at']) ? date('d M Y', strtotime($client['created_at'])) : 'N/A' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
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

<script>
setTimeout(function() {
    const messages = document.querySelectorAll('.alert, .success, .error, .success-msg, .error-msg, .message, .erp-alert');
    messages.forEach(function(msg) {
        msg.style.transition = 'opacity 0.5s ease';
        msg.style.opacity = '0';
        setTimeout(function() { msg.style.display = 'none'; }, 500);
    });
}, 4000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
