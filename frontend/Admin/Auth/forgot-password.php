<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

$message = '';
$success = false;
$devResetUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = erp_request_password_reset((string) ($_POST['email'] ?? ''));
    $message = $result['message'];
    $success = $result['sent'];
    $devResetUrl = $result['dev_reset_url'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | SV Auto Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Inter,sans-serif;background:url('../../assets/images/background.jpeg') center/cover no-repeat fixed;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#1a1a1a}
        body::before{content:'';position:absolute;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(8px)}
        .container{width:100%;max-width:440px;position:relative;z-index:1}
        .card{background:#fff;border:2px solid #F7A100;border-radius:18px;overflow:hidden}
        .content{padding:40px 36px}
        .logo{display:block;height:70px;margin:0 auto 18px}
        h1{text-align:center;font-size:1.6rem;margin-bottom:8px}
        .sub{text-align:center;color:#666;margin-bottom:24px;font-size:.9rem}
        label{display:block;font-size:.8rem;font-weight:700;margin-bottom:6px}
        input{width:100%;padding:12px 14px;border:2px solid #ddd;border-radius:8px;margin-bottom:16px}
        .btn{width:100%;padding:14px;border:none;border-radius:8px;background:#F7A100;color:#fff;font-weight:700;cursor:pointer}
        .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:.85rem;text-align:center}
        .msg.ok{background:#ecfdf5;border:1px solid #86efac;color:#166534}
        .msg.err{background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c}
        .dev-link{display:block;word-break:break-all;font-size:12px;margin-top:8px;color:#b45309}
        .back{display:block;text-align:center;margin-top:20px;color:#F7A100;font-weight:600;text-decoration:none}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="content">
                <img src="../../assets/images/companylogo.jpeg" alt="SV Auto" class="logo">
                <h1>Forgot password?</h1>
                <p class="sub">Enter your account email (admin or client)</p>

                <?php if ($message !== ''): ?>
                <div class="msg <?= $success ? 'ok' : 'err' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($devResetUrl): ?>
                <a class="dev-link" href="<?= htmlspecialchars($devResetUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($devResetUrl, ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (!$success): ?>
                <form method="POST">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" placeholder="you@example.com" required autofocus>
                    <button type="submit" class="btn">Send reset link</button>
                </form>
                <?php endif; ?>

                <a class="back" href="login.php">← Back to sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
