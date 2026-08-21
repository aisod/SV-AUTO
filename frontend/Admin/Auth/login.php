<?php
session_start();

// login.php - Unified Authentication Handler
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

// If already logged in and trying to access login page, show option to continue or logout
$alreadyLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role_name']);
if ($alreadyLoggedIn && !isset($_GET['force'])) {
    // Show "already logged in" message with options
    $currentRole = ucfirst($_SESSION['role_name']);
    $currentEmail = $_SESSION['email'] ?? 'Unknown';
}

$message = '';

// Check for password reset success
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $message = "Password reset successful! You can now sign in with your new password.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $message = "Please enter both email and password.";
    } else {
        // First check users table (admin, manager, etc.)
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.password, u.role_id, r.name AS role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = ? OR u.email = ?
            LIMIT 1
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['username']    = $user['username'];
            $_SESSION['email']       = $user['email'];
            $_SESSION['role_id']     = $user['role_id'];
            $_SESSION['role_name']   = strtolower($user['role_name']);

            // Log the login
            try {
                $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type) VALUES (?, ?, ?)");
                $log->execute([$user['id'], 'Login successful', 'auth']);
            } catch (Exception $e) {
                // Silently fail logging
            }

            // Redirect based on role
            header('Location: ' . getRoleRedirect($_SESSION['role_name']));
            exit;
        } else {
            // Not found in users table, check clients table
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$username]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($client && password_verify($password, $client['password_hash'])) {
                // Check if account is blocked
                if ($client['status'] === 'blocked') {
                    $message = "Your account has been suspended. Please contact SV Auto Services for assistance.";
                } elseif ($client['status'] === 'pending') {
                    // Pending approval - set minimal session and redirect
                    $_SESSION['user_id']    = $client['id'];
                    $_SESSION['role_name']  = 'client';
                    $_SESSION['status']     = 'pending';
                    $_SESSION['first_name'] = $client['first_name'];
                    $_SESSION['email']      = $client['email'];
                    header('Location: ../Client/pending-approval.php');
                    exit;
                } elseif ($client['status'] === 'approved') {
                    // Approved - grant full access
                    $_SESSION['user_id']    = $client['id'];
                    $_SESSION['role_name']  = 'client';
                    $_SESSION['status']     = 'approved';
                    $_SESSION['title']      = $client['title'];
                    $_SESSION['first_name'] = $client['first_name'];
                    $_SESSION['last_name']  = $client['last_name'];
                    $_SESSION['email']      = $client['email'];
                    header('Location: ../Client/dashboard.php');
                    exit;
                } else {
                    // Unknown status
                    $message = "Account status error. Please contact support.";
                }
            } else {
                $message = "Invalid email or password.";
            }
        }
    }
}

function getRoleRedirect($roleName) {
    $roleName = strtolower($roleName);
    
    // Map roles to their dashboards
    if (strpos($roleName, 'admin') !== false) {
        return 'dashboard.php';
    }
    if (strpos($roleName, 'manager') !== false) {
        return '../Manager/manager_dashboard.php';
    }
    if (strpos($roleName, 'tech') !== false) {
        return '../Technician/technician_dashboard.php';
    }
    if (strpos($roleName, 'finance') !== false) {
        return 'finance_dashboard.php';
    }
    if (strpos($roleName, 'hr') !== false) {
        return '../Hr/hr_dashboard.php';
    }
    if (strpos($roleName, 'client') !== false) {
        return '../Client/dashboard.php';
    }
    
    // Default fallback
    return 'dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | SV Auto Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--o:#F7A100;--ol:#F7A100;--od:#E09000;--y:#FFB52E;--w:#FFFFFF;--bg:#FFFBF5;--lightgray:#F5F5F5;--text:#1a1a1a;--gray:#4a4a4a;--ff:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;--fb:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;--ease:cubic-bezier(.16,1,.3,1)}
        body{font-family:var(--fb);background:url('../assets/images/background.jpeg') center/cover no-repeat fixed;position:relative;color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
        body::before{content:'';position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:0}
        .container{width:100%;max-width:440px;animation:slideUp .6s var(--ease);position:relative;z-index:1}
        .card{background:var(--w);border:2px solid var(--o);border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(247,161,0,.15)}
        .bar{height:3px;background:linear-gradient(90deg,var(--o),var(--y),var(--ol))}
        .content{padding:42px 38px}
        .logo-box{text-align:center;margin-bottom:32px}
        .company-logo{height:70px;width:auto;max-width:220px;object-fit:contain;margin:0 auto 18px;display:block}
        h1{font-family:var(--ff);font-size:1.75rem;font-weight:700;text-align:center;margin-bottom:8px;letter-spacing:-.02em;color:var(--text)}
        .subtitle{text-align:center;font-size:.9rem;color:var(--gray);margin-bottom:32px;letter-spacing:-.01em}
        .form-group{margin-bottom:18px}
        label{display:block;font-size:.8rem;color:var(--text);font-weight:700;margin-bottom:7px;letter-spacing:-.01em}
        input{width:100%;padding:13px 15px;background:var(--lightgray);border:2px solid #ddd;border-radius:8px;color:var(--text);font-family:var(--fb);font-size:.9rem;outline:none;transition:all .3s var(--ease);font-weight:500;letter-spacing:-.01em}
        input:focus{border-color:var(--o);box-shadow:0 0 0 3px rgba(247,161,0,.1);transform:translateY(-1px)}
        input::placeholder{color:#999}
        .btn{width:100%;padding:14px;background:linear-gradient(135deg,var(--o),var(--ol));border:none;color:var(--w);border-radius:8px;cursor:pointer;font-family:var(--fb);font-size:.95rem;font-weight:700;transition:all .3s var(--ease);margin-top:10px;box-shadow:0 4px 12px rgba(247,161,0,.2);letter-spacing:-.01em}
        .btn:hover{background:#E09000;transform:translateY(-2px);box-shadow:0 8px 20px rgba(247,161,0,.35)}
        .btn-google{width:100%;padding:14px;background:white;border:2px solid #ddd;border-radius:8px;cursor:pointer;font-family:var(--fb);font-size:.95rem;font-weight:600;transition:all .3s var(--ease);margin-top:12px;display:flex;align-items:center;justify-content:center;gap:10px;color:var(--text)}
        .btn-google:hover{border-color:var(--o);background:#fff9f0}
        .btn-google img{height:20px;width:20px}
        .divider{display:flex;align-items:center;margin:20px 0;color:#999;font-size:.85rem}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:#ddd}
        .divider span{padding:0 15px}
        .error{background:rgba(220,53,69,.1);border:2px solid rgba(220,53,69,.3);color:#dc3545;padding:13px;border-radius:10px;font-size:.85rem;margin-bottom:22px;text-align:center;font-weight:600;animation:shake .5s}
        .success{background:rgba(34,197,94,.1);border:2px solid rgba(34,197,94,.3);color:#22c55e;padding:13px;border-radius:10px;font-size:.85rem;margin-bottom:22px;text-align:center;font-weight:600}
        .links-row{display:flex;justify-content:space-between;align-items:center;margin-top:22px;font-size:.9rem}
        .links-row a{color:var(--o);text-decoration:none;font-weight:600;transition:color .2s}
        .links-row a:hover{color:var(--od);text-decoration:underline}
        .get-started{text-align:center;margin-top:25px;padding-top:25px;border-top:1px solid #eee}
        .get-started p{color:var(--gray);font-size:.9rem;margin-bottom:12px}
        .btn-secondary{display:inline-block;padding:10px 24px;background:white;border:2px solid var(--o);color:var(--o);border-radius:8px;text-decoration:none;font-weight:600;font-size:.9rem;transition:all .3s}
        .btn-secondary:hover{background:var(--o);color:white}
        @keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
        @keyframes shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-8px)}75%{transform:translateX(8px)}}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="bar"></div>
            <div class="content">
                <div class="logo-box">
                    <img src="../assets/images/companylogo.jpeg" alt="SV Auto Logo" class="company-logo">
                    <h1>Welcome Back</h1>
                    <p class="subtitle">Sign in to continue</p>
                </div>
                
                <?php if($alreadyLoggedIn): ?>
                <div class="error" style="background:rgba(247,161,0,.1);border-color:rgba(247,161,0,.3);color:#E09000;">
                    You are already logged in as <strong><?= htmlspecialchars($currentEmail) ?></strong> (<?= htmlspecialchars($currentRole) ?>)
                </div>
                <a href="<?= getRoleRedirect($_SESSION['role_name']) ?>" class="btn" style="display:block;text-align:center;text-decoration:none;margin-bottom:15px;">
                    Continue to Dashboard →
                </a>
                <a href="logout.php?redirect=login" class="btn-secondary" style="display:block;text-align:center;text-decoration:none;padding:14px;">
                    Sign Out & Login as Different User
                </a>
                <div class="links-row" style="margin-top:25px;">
                    <a href="../index.php">← Back to Home</a>
                </div>
                <?php else: ?>
                
                <?php if($message): ?>
                <div class="<?= isset($_GET['reset']) && $_GET['reset'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['google_message'])): 
                    $googleMsg = $_SESSION['google_message'];
                    $isSuspended = stripos($googleMsg, 'suspended') !== false;
                    unset($_SESSION['google_message']);
                ?>
                <div class="<?= $isSuspended ? 'error' : 'error' ?>" style="<?= $isSuspended ? 'background:rgba(220,53,69,0.1);border:2px solid rgba(220,53,69,0.4);color:#dc3545;' : '' ?>">
                    <?= $isSuspended ? '<i class="fas fa-ban" style="margin-right:8px;"></i>' : '' ?><?= htmlspecialchars($googleMsg) ?>
                </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="username" placeholder="Enter your email" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <div style="position:relative">
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                            <span onclick="togglePassword('password', this)" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;user-select:none;">👁</span>
                        </div>
                    </div>
                    <button type="submit" class="btn">Sign In →</button>
                </form>
                
                <div class="divider"><span>or</span></div>
                
                <!-- Google OAuth -->
                <a href="google-auth.php" class="btn-google" style="text-decoration:none;">
                    <img src="../assets/images/Google__G__logo.webp" alt="Google">
                    Sign in with Google
                </a>
                
                <div class="links-row">
                    <a href="../index.php">← Back to Home</a>
                    <a href="forgot-password.php">Forgot Password?</a>
                </div>
                
                <div class="get-started">
                    <p>New client? Create an account to get started</p>
                    <a href="register.php" class="btn-secondary">Get Started</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
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
    
    setTimeout(function() {
        const messages = document.querySelectorAll('.alert, .success, .error, .success-msg, .error-msg, .message');
        messages.forEach(function(msg) {
            msg.style.transition = 'opacity 0.5s ease';
            msg.style.opacity = '0';
            setTimeout(function() { msg.style.display = 'none'; }, 500);
        });
    }, 4000);
    </script>
</body>
</html>
