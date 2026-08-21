<?php
// Client Settings Page
$page_title = 'Settings';
require_once __DIR__ . '/includes/header.php';

// Get client information
$clientEmail = $_SESSION['email'] ?? '';
$clientId = null;

if ($clientEmail) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$clientEmail]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client) {
        $clientId = $client['id'];
    }
}

$message = '';
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($name)) {
        $message = "Name is required.";
    } else {
        // Update client information
        if ($clientId) {
            try {
                $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $address, $clientId]);
                $success = true;
                $message = "Profile updated successfully!";
                
                // Refresh client data
                $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute([$clientId]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $message = "Error updating profile: " . $e->getMessage();
            }
        }
        
        // Update password if provided
        if (!empty($currentPassword) && !empty($newPassword)) {
            if ($newPassword !== $confirmPassword) {
                $message = "New passwords do not match.";
                $success = false;
            } elseif (strlen($newPassword) < 6) {
                $message = "New password must be at least 6 characters.";
                $success = false;
            } else {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $userPassword = $stmt->fetchColumn();
                
                if (password_verify($currentPassword, $userPassword)) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $_SESSION['user_id']]);
                    $success = true;
                    $message = "Password updated successfully!";
                } else {
                    $message = "Current password is incorrect.";
                    $success = false;
                }
            }
        }
    }
}
?>

<style>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
}

.settings-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.settings-card h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1F2937;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #F7A100;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #F7A100;
    box-shadow: 0 0 0 3px rgba(247, 161, 0, 0.1);
}

.form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.btn-save {
    background: #F7A100;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}

.btn-save:hover {
    background: #E09000;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
}

.alert-success {
    background: #D1FAE5;
    color: #065F46;
    border: 1px solid #10B981;
}

.alert-error {
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #EF4444;
}

.divider {
    height: 1px;
    background: #E5E7EB;
    margin: 24px 0;
}

@media (max-width: 768px) {
    .settings-card {
        padding: 16px;
    }
}
</style>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Settings</span>
    </div>
    <h1 class="erp-page-title">Account Settings</h1>
</div>

<div class="settings-container">
    <?php if ($message): ?>
    <div class="alert <?= $success ? 'alert-success' : 'alert-error' ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <form method="POST">
        <!-- Profile Information -->
        <div class="settings-card">
            <h3><i class="fas fa-user" style="color: #F7A100; margin-right: 8px;"></i>Profile Information</h3>
            
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($client['name'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" value="<?= htmlspecialchars($clientEmail) ?>" disabled style="background: #F3F4F6; cursor: not-allowed;">
                <small style="color: #6B7280; font-size: 12px; display: block; margin-top: 4px;">Email cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>" placeholder="Enter your phone number">
            </div>
            
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" placeholder="Enter your address"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="settings-card">
            <h3><i class="fas fa-lock" style="color: #F7A100; margin-right: 8px;"></i>Change Password</h3>
            
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" placeholder="Enter current password">
            </div>
            
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="Enter new password (min 6 characters)">
            </div>
            
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password">
            </div>
            
            <small style="color: #6B7280; font-size: 12px; display: block; margin-top: -12px;">Leave blank if you don't want to change your password</small>
        </div>
        
        <!-- Save Button -->
        <div style="text-align: right;">
            <button type="submit" class="btn-save">
                <i class="fas fa-save" style="margin-right: 8px;"></i>Save Changes
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
