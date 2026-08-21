<?php
session_start();
require_once __DIR__ . '/../../backend/config/config.php';

// Security check - must be logged in with client role and be a new Google user
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role_name'] ?? '') !== 'client') {
    header('Location: ../Admin/login.php');
    exit;
}

// If not a new Google user, redirect to dashboard
if (!isset($_SESSION['google_new_user'])) {
    header('Location: dashboard.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current client data
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    die('Client record not found.');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $firstName = htmlspecialchars(trim($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $lastName = htmlspecialchars(trim($_POST['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $dob = htmlspecialchars(trim($_POST['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8');
    $gender = htmlspecialchars(trim($_POST['gender'] ?? ''), ENT_QUOTES, 'UTF-8');
    $idPassport = htmlspecialchars(trim($_POST['id_passport'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $whatsapp = htmlspecialchars(trim($_POST['whatsapp'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars(trim($_POST['address'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    // Validation
    if (empty($title) || empty($firstName) || empty($lastName) || empty($phone)) {
        $error = "Please fill in all required fields (Title, First Name, Last Name, Phone).";
    } else {
        // Password validation (optional)
        $passwordHash = null;
        if (!empty($_POST['password'])) {
            if ($_POST['password'] !== $_POST['confirm_password']) {
                $error = "Passwords do not match.";
            } else if (strlen($_POST['password']) < 8) {
                $error = "Password must be at least 8 characters.";
            } else {
                $passwordHash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
        }
        
        if (!$error) {
            try {
                // Update client record with complete profile
                if ($passwordHash) {
                    $stmt = $pdo->prepare("
                        UPDATE clients 
                        SET title = ?, first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, 
                            id_passport = ?, phone = ?, whatsapp = ?, address = ?, password_hash = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $title, $firstName, $lastName, $dob ?: null, $gender,
                        $idPassport, $phone, $whatsapp, $address, $passwordHash, $userId
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE clients 
                        SET title = ?, first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, 
                            id_passport = ?, phone = ?, whatsapp = ?, address = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $title, $firstName, $lastName, $dob ?: null, $gender,
                        $idPassport, $phone, $whatsapp, $address, $userId
                    ]);
                }
                
                // Update session
                $_SESSION['title'] = $title;
                $_SESSION['first_name'] = $firstName;
                $_SESSION['last_name'] = $lastName;
                $_SESSION['new_google_signup'] = true;
                
                // Clear the google_new_user flag
                unset($_SESSION['google_new_user']);
                
                // Redirect to dashboard with welcome message
                header('Location: dashboard.php');
                exit;
                
            } catch (Exception $e) {
                $error = "Failed to update profile. Please try again.";
                error_log("Profile completion error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile | SV Auto Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: url('../assets/images/background.jpeg') center/cover no-repeat fixed;
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(6px);
        }
        
        .container {
            position: relative;
            width: 100%;
            max-width: 600px;
            animation: slideUp 0.5s ease;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #F5A623 0%, #E09000 100%);
            padding: 32px;
            text-align: center;
            color: white;
        }
        
        .header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 0.95rem;
            opacity: 0.95;
        }
        
        .content {
            padding: 36px;
        }
        
        .welcome-message {
            background: #FFF5E0;
            border-left: 4px solid #F5A623;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            color: #1a1a1a;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .message {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .error {
            background: rgba(220, 53, 69, 0.1);
            border: 2px solid rgba(220, 53, 69, 0.3);
            color: #dc3545;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group.full {
            grid-column: 1 / -1;
        }
        
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 6px;
        }
        
        .required {
            color: #dc3545;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 14px;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 0.9rem;
            color: #1a1a1a;
            transition: all 0.3s;
            outline: none;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #F5A623;
            background: white;
            box-shadow: 0 0 0 3px rgba(245, 166, 35, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-top: 2px;
        }
        
        .checkbox-group label {
            margin: 0;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .section-divider {
            margin: 32px 0 24px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        
        .section-heading {
            color: #1a1a2e;
            margin-bottom: 8px;
            font-size: 1.1rem;
            font-weight: 700;
            border-left: 3px solid #F5A623;
            padding-left: 12px;
        }
        
        .section-description {
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 20px;
            padding-left: 15px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            background: linear-gradient(135deg, #F5A623 0%, #E09000 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(245, 166, 35, 0.3);
            margin-top: 24px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 166, 35, 0.4);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 640px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>Complete Your Profile</h1>
                <p>SV Auto Services — One last step before you're in</p>
            </div>
            
            <div class="content">
                <div class="welcome-message">
                    Your Google account has been linked successfully.<br>
                    Please complete your profile details below.
                </div>
                
                <?php if($error): ?>
                <div class="message error"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Title <span class="required">*</span></label>
                            <select name="title" required>
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
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender">
                                <option value="">Select</option>
                                <option value="Male" <?= ($client['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($client['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Prefer not to say" <?= ($client['gender'] ?? '') === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($client['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($client['last_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <input type="date" name="date_of_birth" value="<?= htmlspecialchars($client['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label>ID / Passport Number</label>
                            <input type="text" name="id_passport" value="<?= htmlspecialchars($client['id_passport'] ?? '') ?>" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>" placeholder="+264 81 234 5678" required>
                    </div>
                    
                    <div class="form-group">
                        <label>WhatsApp Number</label>
                        <input type="tel" name="whatsapp" id="whatsapp" value="<?= htmlspecialchars($client['whatsapp'] ?? '') ?>" placeholder="+264 81 234 5678">
                        <div class="checkbox-group">
                            <input type="checkbox" id="sameAsPhone" onchange="copyPhone()">
                            <label for="sameAsPhone">Same as phone number</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Physical Address / Residence</label>
                        <textarea name="address" placeholder="Street, Suburb, City&#10;e.g., 123 Independence Ave, Klein Windhoek, Windhoek"><?= htmlspecialchars($client['address'] ?? '') ?></textarea>
                    </div>
                    
                    <hr class="section-divider">
                    
                    <h4 class="section-heading">Set Your Password</h4>
                    <p class="section-description">Set a password so you can also sign in with your email and password in future — not just Google.</p>
                    
                    <div class="form-group">
                        <label>Password (Optional)</label>
                        <div style="position:relative">
                            <input type="password" id="password" name="password" placeholder="Minimum 8 characters">
                            <span onclick="togglePassword('password', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;user-select:none;font-size:20px;">👁</span>
                        </div>
                        <small style="color:#888;font-size:12px;">Minimum 8 characters. Use letters, numbers and symbols for a stronger password.</small>
                        <div id="strength-bar" style="height:4px;border-radius:2px;margin-top:6px;width:0%;transition:width 0.3s,background 0.3s;"></div>
                        <small id="strength-text" style="font-size:11px;color:#888;"></small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <div style="position:relative">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password">
                            <span onclick="togglePassword('confirm_password', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;user-select:none;font-size:20px;">👁</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">Save & Continue →</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function copyPhone() {
            const phone = document.querySelector('input[name="phone"]').value;
            const whatsapp = document.getElementById('whatsapp');
            if (document.getElementById('sameAsPhone').checked) {
                whatsapp.value = phone;
            } else {
                whatsapp.value = '';
            }
        }
        
        function togglePassword(fieldId, icon) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                icon.style.color = '#F5A623';
            } else {
                field.type = 'password';
                icon.style.color = '#888';
            }
        }
        
        document.getElementById('password').addEventListener('input', function() {
            const val = this.value;
            const bar = document.getElementById('strength-bar');
            const text = document.getElementById('strength-text');
            let strength = 0;
            
            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;
            
            const colors = ['#e74c3c','#e67e22','#f1c40f','#2ecc71'];
            const labels = ['Weak','Fair','Good','Strong'];
            const widths = ['25%','50%','75%','100%'];
            
            if (val.length > 0) {
                bar.style.width = widths[strength-1] || '25%';
                bar.style.background = colors[strength-1] || '#e74c3c';
                text.textContent = 'Password strength: ' + (labels[strength-1] || 'Weak');
                text.style.color = colors[strength-1] || '#e74c3c';
            } else {
                bar.style.width = '0%';
                text.textContent = '';
            }
        });
        
        // Auto-dismiss error messages after 4 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(msg) {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(function() { msg.style.display = 'none'; }, 500);
            });
        }, 4000);
    </script>
</body>
</html>
