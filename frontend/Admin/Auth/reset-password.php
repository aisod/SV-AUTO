<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

$error = '';
$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$validToken = erp_validate_password_reset_token($token) !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = erp_complete_password_reset(
        $token,
        (string) ($_POST['password'] ?? ''),
        (string) ($_POST['confirm_password'] ?? '')
    );
    if ($result['ok']) {
        header('Location: login.php?reset=success');
        exit;
    }
    $error = $result['message'];
    $validToken = erp_validate_password_reset_token($token) !== null;
} elseif ($token === '') {
    $error = 'No reset token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | SV Auto Management</title>
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
        .msg{padding:12px;border-radius:8px;margin-bottom:16px;font-size:.85rem;text-align:center;background:#fef2f2;border:1px solid #fca5a5;color:#b91c1c}
        .back{display:block;text-align:center;margin-top:20px;color:#F7A100;font-weight:600;text-decoration:none}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="content">
                <img src="../../assets/images/companylogo.jpeg" alt="SV Auto" class="logo">
                <h1>Reset password</h1>
                <p class="sub">Choose a new password</p>

                <?php if ($error !== ''): ?>
                <div class="msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <?php if ($validToken): ?>
                <form method="POST">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" minlength="8" required autofocus>
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                    <button type="submit" class="btn">Save new password</button>
                </form>
                <?php else: ?>
                <a class="back" href="forgot-password.php">Request a new reset link</a>
                <?php endif; ?>

                <a class="back" href="login.php">← Back to sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
