<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: roles.php?error=Invalid role ID');
    exit;
}

$role_id = (int)$_GET['id'];

// Fetch role with user count
$stmt = $pdo->prepare("
    SELECT r.*, COUNT(u.id) as user_count 
    FROM roles r 
    LEFT JOIN users u ON r.id = u.role_id 
    WHERE r.id = ? 
    GROUP BY r.id
");
$stmt->execute([$role_id]);
$role = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$role) {
    header('Location: roles.php?error=Role not found');
    exit;
}

$permissions = $role['permissions'] 
    ? array_filter(array_map('trim', explode(',', $role['permissions']))) 
    : [];

// Map permission keys to friendly names
$permLabels = [
    'full_access' => 'Full System Access',
    'job_cards'   => 'Manage Job Cards',
    'invoices'    => 'Manage Invoices',
    'employees'   => 'Manage Employees',
    'reports'     => 'View Reports',
    'authorize'   => 'Authorize Actions',
];
?>

<?php include __DIR__ . '/../sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Role: <?= htmlspecialchars($role['name']) ?> - SV Auto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {--primary:#F5A623;--secondary:#1a1a1a;--accent:#FFF8EC;--light:#FFF8F0;--text:#1a1a1a;--bg:#FFFBF5;}
        .page-title {font-size: 28px; color: var(--secondary); margin: 0 0 30px 0; font-weight: 700; display: flex; align-items: center; gap: 12px;}
        .card {background: white; padding: 32px; border-radius: 16px; box-shadow: 0 6px 20px rgba(139,69,19,0.1); border-top: 5px solid var(--primary);}
        .role-header {display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;}
        .role-name {font-size: 26px; font-weight: 800; color: var(--text);}
        .badge {padding: 8px 16px; border-radius: 20px; background: linear-gradient(135deg,var(--primary),var(--secondary)); color: white; font-size: 12px; font-weight: bold;}
        .info-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin: 30px 0;}
        .info-item {background: var(--light); padding: 20 alimentairespx; border-radius: 12px; border: 2px solid var(--accent);}
        .info-label {font-size: 13px; color: #777; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;}
        .info-value {font-size: 20px; font-weight: 700; color: var(--text);}
        .permissions-list {display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-top: 16px;}
        .perm-item {background: white; padding: 16px; border-radius: 12px; border: 2px solid var(--accent); display: flex; align-items: center; gap: 12px; font-weight: 500;}
        .perm-item i {color: var(--primary); font-size: 18px;}
        .btn-back {padding: 12px 28px; background: var(--secondary); color: white; border: none; border-radius: 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; margin-top: 20px;}
        .btn-back:hover {background: #6b4423;}
    </style>
</head>
<body>

<div class="content-wrapper">

    <h2 class="page-title">
        View Role Details
    </h2>

    <div class="card">
        <div class="role-header">
            <div>
                <div class="role-name"><?= htmlspecialchars($role['name']) ?></div>
            </div>
            <div>
                <?php if (stripos($role['name'], 'admin') !== false): ?>
                    <span class="badge">ADMIN ROLE</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Total Users Assigned</div>
                <div class="info-value"><?= $role['user_count'] ?> user<?= $role['user_count'] != 1 ? 's' : '' ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Total Permissions</div>
                <div class="info-value"><?= count($permissions) ?></div>
            </div>
        </div>

        <h3 style="margin: 32px 0 16px; color: var(--secondary); display:flex; align-items:center; gap:12px;">
            Assigned Permissions
        </h3>

        <?php if (empty($permissions)): ?>
            <p style="color:#999; font-style:italic;">No permissions assigned to this role.</p>
        <?php else: ?>
            <div class="permissions-list">
                <?php foreach ($permissions as $perm): 
                    $label = $permLabels[$perm] ?? ucwords(str_replace('_', ' ', $perm));
                ?>
                    <div class="perm-item">
                        <i class="fas fa-check-circle"></i>
                        <span><?= htmlspecialchars($label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 40px;">
            <a href="edit_role.php?id=<?= $role['id'] ?>" style="background:var(--primary); padding:12px 28px; border-radius:12px; color:white; text-decoration:none; display:inline-flex; align-items:center; gap:10px;">
                Edit Role
            </a>
        </div>
    </div>

</div>

</body>
</html>
