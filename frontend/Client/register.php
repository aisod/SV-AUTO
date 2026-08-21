<?php
// register.php - Client Registration
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/functions.php';
session_start();

if (isset($_POST['role_id'])) { unset($_POST['role_id']); }

if (isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$business = getBusiness();
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($title) || empty($fullName) || empty($phone) || empty($email) || empty($password) || empty($confirm)) {
        $message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } elseif (!preg_match('/^[0-9+\s\-]{7,15}$/', $phone)) {
        $message = "Please enter a valid phone number.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $email]);
        if ($stmt->fetch()) {
            $message = "An account with this email already exists. Please <a href='login.php'>sign in</a>.";
        } else {
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = 'client' LIMIT 1");
            $roleStmt->execute();
            $clientRoleId = $roleStmt->fetchColumn();

            if (!$clientRoleId) {
                try {
                    $pdo->prepare("INSERT INTO roles (name, permissions) VALUES ('client', 'view_own_documents,respond_quotations,view_invoices,update_profile')")->execute();
                    $clientRoleId = $pdo->lastInsertId();
                } catch (Exception $e) {
                    $message = "Role creation failed: " . htmlspecialchars($e->getMessage());
                }
            }

            if ($clientRoleId) {
                $clientStmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                $clientStmt->execute([$email]);
                $existingClientId = $clientStmt->fetchColumn();

                if (!$existingClientId) {
                    try {
                        $createClient = $pdo->prepare("INSERT INTO clients (name, email, phone, address) VALUES (?, ?, ?, '')");
                        $createClient->execute([$title . ' ' . $fullName, $email, $phone]);
                        $existingClientId = $pdo->lastInsertId();
                    } catch (Exception $e) {
                        $existingClientId = null;
                    }
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $username = strtolower(str_replace(' ', '.', $fullName)) . '.' . rand(100, 999);

                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$username, $email, $hashed, $clientRoleId]);
                    $success = true;
                    $message = "Registration successful! <a href='login.php'>Sign in now</a> to access your account.";
                } catch (Exception $e) {
                    $message = "Registration failed: " . htmlspecialchars($e->getMessage());
                }
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
    <title>Create Account | SV Auto Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        :root{--o:#F7A100;--ol:#F7A100;--od:#E09000;--y:#FFB52E;--w:#FFFFFF;--bg:#FFFBF5;--lightgray:#F5F5F5;--text:#1a1a1a;--gray:#4a4a4a;--ff:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;--fb:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;--ease:cubic-bezier(.16,1,.3,1)}
        body{font-family:var(--fb);background:url('https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=1920&h=1080&fit=crop&q=80') center/cover no-repeat fixed;position:relative;color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
        body::before{content:'';position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:0}
        .container{width:100%;max-width:480px;animation:slideUp .6s var(--ease);position:relative;z-index:1}
        .card{background:var(--w);border:2px solid var(--o);border-radius:18px;overflow:hidden;box-shadow:0 20px 60px rgba(247,161,0,.15)}
        .bar{height:3px;background:linear-gradient(90deg,var(--o),var(--y),var(--ol))}
        .content{padding:42px 38px}
        .logo-box{text-align:center;margin-bottom:32px}
        .company-logo{height:70px;width:auto;max-width:220px;object-fit:contain;margin:0 auto 18px;display:block}
        h1{font-family:var(--ff);font-size:1.75rem;font-weight:700;text-align:center;margin-bottom:8px;letter-spacing:-.02em;color:var(--text)}
        .subtitle{text-align:center;font-size:.9rem;color:var(--gray);margin-bottom:32px;letter-spacing:-.01em}
        .form-group{margin-bottom:18px}
        label{display:block;font-size:.8rem;color:var(--text);font-weight:700;margin-bottom:7px;letter-spacing:-.01em}
        input,select{width:100%;padding:13px 15px;background:var(--lightgray);border:2px solid #ddd;border-radius:8px;color:var(--text);font-family:var(--fb);font-size:.9rem;outline:none;transition:all .3s var(--ease);font-weight:500;letter-spacing:-.01em;appearance:none}
        input:focus,select:focus{border-color:var(--o);box-shadow:0 0 0 3px rgba(247,161,0,.1);transform:translateY(-1px)}
        input::placeholder{color:#999}
        select{cursor:pointer;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23999' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;background-color:var(--lightgray)}
        .btn{width:100%;padding:14px;background:linear-gradient(135deg,var(--o),var(--ol));border:none;color:var(--w);border-radius:8px;cursor:pointer;font-family:var(--fb);font-size:.95rem;font-weight:700;transition:all .3s var(--ease);margin-top:10px;box-shadow:0 4px 12px rgba(247,161,0,.2);letter-spacing:-.01em}
        .btn:hover{background:#E09000;transform:translateY(-2px);box-shadow:0 8px 20px rgba(247,161,0,.35)}
        .error{background:rgba(220,53,69,.1);border:2px solid rgba(220,53,69,.3);color:#dc3545;padding:13px;border-radius:10px;font-size:.85rem;margin-bottom:22px;text-align:center;font-weight:600;animation:shake .5s}
        .success{background:rgba(46,125,50,.1);border:2px solid rgba(46,125,50,.3);color:#2e7d32;padding:13px;border-radius:10px;font-size:.85rem;margin-bottom:22px;text-align:center;font-weight:600}
        .success a{color:#1b5e20;font-weight:700;text-decoration:underline}
        .links-row{display:flex;justify-content:space-between;align-items:center;margin-top:22px;font-size:.9rem}
        .links-row a{color:var(--o);text-decoration:none;font-weight:600;transition:color .2s}
        .links-row a:hover{color:var(--od);text-decoration:underline}
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
                    <h1>Create Account</h1>
                    <p class="subtitle">Get started with SV Auto Services</p>
                </div>

                <?php if($message): ?>
                <div class="<?= $success ? 'success' : 'error' ?>"><?= $message ?></div>
                <?php endif; ?>

                <?php if(!$success): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Title</label>
                        <select name="title" required>
                            <option value="" disabled selected>Select title</option>
                            <option value="Mr">Mr</option>
                            <option value="Mrs">Mrs</option>
                            <option value="Ms">Ms</option>
                            <option value="Dr">Dr</option>
                            <option value="Prof">Prof</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" placeholder="Enter your full name" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" placeholder="e.g. +264 81 234 5678" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="Enter your email address" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Create a password (min 6 chars)" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Confirm your password" required minlength="6">
                    </div>
                    <button type="submit" class="btn">Create Account →</button>
                </form>
                <?php endif; ?>

                <div class="links-row">
                    <a href="../index.php">← Back to Home</a>
                    <a href="login.php">Already have an account? Sign In</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
