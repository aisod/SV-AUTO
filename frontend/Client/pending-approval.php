<?php
session_start();
require_once __DIR__ . '/../../backend/config/config.php';

// Security check - must be logged in with client role
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role_name'] ?? '') !== 'client') {
    header('Location: ../Admin/login.php');
    exit;
}

// If status is approved, redirect to dashboard
if (($_SESSION['status'] ?? 'pending') === 'approved') {
    header('Location: dashboard.php');
    exit;
}

$firstName = $_SESSION['first_name'] ?? 'Client';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Pending Approval | SV Auto Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(6px);
            z-index: 0;
        }
        
        .container {
            position: relative;
            z-index: 1;
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
        
        .logo {
            max-width: 180px;
            height: auto;
            margin-bottom: 16px;
            filter: brightness(0) invert(1);
        }
        
        .header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 0.95rem;
            opacity: 0.95;
        }
        
        .content {
            padding: 40px 36px;
            text-align: center;
        }
        
        .icon-wrapper {
            width: 90px;
            height: 90px;
            background: #FFF5E0;
            border: 3px solid #F5A623;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: pulse 2s infinite;
        }
        
        .icon-wrapper i {
            font-size: 40px;
            color: #F5A623;
        }
        
        h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 16px;
        }
        
        .message {
            font-size: 1rem;
            color: #4a5568;
            line-height: 1.8;
            margin-bottom: 32px;
        }
        
        .contact-box {
            background: #1a1a2e;
            border-radius: 12px;
            border-top: 3px solid #F5A623;
            padding: 24px;
            margin-bottom: 32px;
        }
        
        .contact-box h3 {
            color: #F5A623;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .phone-numbers {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .phone-number {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            padding: 12px;
            background: rgba(245, 166, 35, 0.1);
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .phone-number:hover {
            background: rgba(245, 166, 35, 0.2);
            transform: translateY(-2px);
        }
        
        .phone-number i {
            color: #F5A623;
        }
        
        .info-box {
            background: #FFF5E0;
            border-left: 4px solid #F5A623;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            text-align: left;
        }
        
        .info-box p {
            color: #4a5568;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }
        
        .btn-signout {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: white;
            color: #1a1a1a;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-signout:hover {
            border-color: #F5A623;
            color: #F5A623;
            transform: translateY(-2px);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        @media (max-width: 640px) {
            .content {
                padding: 32px 24px;
            }
            h1 {
                font-size: 1.4rem;
            }
            .phone-number {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <?php 
                // Check if logo file exists using absolute path
                $logoFilePath = __DIR__ . '/../assets/images/companylogo.jpeg';
                $logoExists = file_exists($logoFilePath);
                ?>
                
                <img src="../assets/images/companylogo.jpeg" alt="SV Auto Services" class="logo" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <h2 style="display: none; color: #F5A623; font-size: 1.5rem; font-weight: 700; margin-bottom: 16px;">SV Auto Services</h2>
                <h2>Account Pending Approval</h2>
                <p>SV Auto Services — Windhoek's trusted truck repair experts</p>
            </div>
            
            <div class="content">
                <div class="icon-wrapper">
                    <i class="fas fa-clock"></i>
                </div>
                
                <h1>You're almost in, <?= htmlspecialchars($firstName) ?>!</h1>
                
                <p class="message">
                    Your account is currently under review. SV Auto will verify your details and approve your account within 24 hours.
                </p>
                
                <div class="contact-box">
                    <h3><i class="fas fa-phone-alt"></i> Need Urgent Repair?</h3>
                    <div class="phone-numbers">
                        <a href="tel:0812815912" class="phone-number">
                            <i class="fas fa-phone"></i>
                            <span>081 281 5912</span>
                        </a>
                        <a href="tel:0812193702" class="phone-number">
                            <i class="fas fa-phone"></i>
                            <span>081 219 3702</span>
                        </a>
                    </div>
                </div>
                
                <div class="info-box">
                    <p>
                        <i class="fas fa-info-circle" style="color: #F5A623; margin-right: 8px;"></i>
                        <strong>You will receive a confirmation once your account is approved.</strong> 
                        Please check your email regularly for updates.
                    </p>
                </div>
                
                <a href="../Admin/logout.php" class="btn-signout">
                    <i class="fas fa-sign-out-alt"></i>
                    Sign Out
                </a>
            </div>
        </div>
    </div>
</body>
</html>
