<!-- sidebar.php – COMPANY LOGO FROM PUBLIC FOLDER -->
<?php 
if (session_status() === PHP_SESSION_NONE) session_start(); 
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/functions.php';

// Get business name from database
$business = getBusiness();
// Use companylogo2.png from Public folder with cache-busting
$logoPath = '../assets/images/companylogo2.png?v=' . (is_file(__DIR__ . '/../assets/images/companylogo2.png') ? filemtime(__DIR__ . '/../assets/images/companylogo2.png') : time());
$businessName = htmlspecialchars($business['name'] ?? 'SV Auto Management');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SV Auto Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root{
            --o:#F5A623;--ol:#F5A623;--od:#E09515;--y:#F5C518;--w:#FFFFFF;--bg:#FFFBF5;
            --lightgray:#F5F5F5;--text:#1a1a1a;--gray:#4a4a4a;
            --ff:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;--fb:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:var(--fb);
            background:var(--bg);
            color:var(--text);
            overflow-x:hidden;
            -webkit-font-smoothing:antialiased;
            letter-spacing:-.01em;
        }

        /* HEADER */
        .header{
            background:linear-gradient(135deg,var(--o),var(--ol));
            color:white;
            padding:16px 24px;
            position:fixed;
            top:0;left:0;right:0;
            height:64px;
            z-index:1100;
            display:flex;
            align-items:center;
            justify-content:space-between;
            box-shadow:0 2px 12px rgba(244,106,31,0.2);
        }
        .header h2{margin:0;font-size:20px;font-weight:700;flex-shrink:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:var(--ff);letter-spacing:-.02em;}
        .header > div{flex-shrink:0;white-space:nowrap;margin-left:10px;}
        .menu-toggle{
            background:none;border:none;color:white;font-size:24px;cursor:pointer;display:none;flex-shrink:0;
        }

        /* OVERLAY */
        .overlay{
            position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:999;
            opacity:0;visibility:hidden;transition:all .3s;
        }
        .overlay.active{opacity:1;visibility:visible;}

        /* SIDEBAR */
        .sidebar{
            width:260px;
            background:var(--w);
            height:100vh;
            position:fixed;
            top:0;left:0;
            padding-top:74px;
            border-right:2px solid rgba(244,106,31,0.15);
            box-shadow:2px 0 16px rgba(244,106,31,0.08);
            z-index:1000;
            overflow-y:hidden;
            display:flex;
            flex-direction:column;
        }

        .sidebar-nav-scroll {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        /* LOGO AREA */
        .sidebar-logo{
            text-align:center;
            padding:20px 20px 24px;
            border-bottom:2px solid var(--lightgray);
            background: #FFF8EC;
        }
        .sidebar-logo img{
            max-height:70px;
            max-width:180px;
            height:auto;
            width:auto;
            border-radius:8px;
            box-shadow:0 2px 8px rgba(0,0,0,0.08);
            transition:transform .3s;
        }
        .sidebar-logo img:hover{
            transform:scale(1.05);
        }
        .sidebar-logo p{
            margin-top:10px;
            font-weight:700;
            color:var(--text);
            font-size:14px;
            letter-spacing:-.01em;
            font-family:var(--ff);
        }

        /* MENU LINKS */
        .sidebar a{
            display:flex;align-items:center;
            padding:14px 24px;
            color:#1A1A1A;
            text-decoration:none;
            transition:all .3s cubic-bezier(.16,1,.3,1);
            border-left:3px solid transparent;
            font-weight:600;
            font-size:14px;
        }
        .sidebar a:hover,
        .sidebar a.active{
            background:#F7A100;
            border-left-color:#F7A100;
            padding-left:28px;
            color:white;
        }
        .sidebar a i{
            margin-right:12px;
            width:24px;
            color:#1A1A1A;
            font-size:16px;
            text-align:center;
        }
        .sidebar a:hover i,
        .sidebar a.active i{
            color:white;
        }

        /* MAIN CONTENT */
        .main-content{
            margin-left:260px;
            min-height:100vh;
            padding:74px 0 0 0;
            background:var(--bg);
        }
        .content-wrapper{
            width:100%;
            max-width:100%;
            padding:0 30px 80px 30px;
        }

        /* MOBILE */
        @media (max-width:991px){
            .menu-toggle{display:block;}
            .header h2{font-size:18px;}
            .header > div{font-size:13px;}
            .sidebar{
                left:-260px;
                transition:left .35s ease;
            }
            .sidebar.active{left:0;}
            .main-content{
                margin-left:0 !important;
                padding:74px 0 0 0;
            }
            .content-wrapper{
                padding:0 20px 80px 20px;
            }
            .sidebar-logo{
                padding:15px 20px 20px;
            }
            .sidebar-logo img{
                max-height:60px;
            }
        }
        @media (max-width:768px){
            .header{padding:12px 16px;}
            .header h2{font-size:16px;}
            .header > div{font-size:12px;}
        }
    </style>
</head>
<body>

    <!-- Header -->
    <div class="header">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h2>SV Auto Management System</h2>
        <div><small>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></small></div>
    </div>

    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        
        <!-- DYNAMIC LOGO FROM SETTINGS -->
        <div class="sidebar-logo">
            <img src="<?php echo $logoPath; ?>" alt="<?php echo $businessName; ?> Logo">
            <p><?php echo $businessName; ?></p>
        </div>

        <?php $page = basename($_SERVER['PHP_SELF']); ?>
        <div class="sidebar-nav-scroll">
        <a href="dashboard.php" class="<?= $page=='dashboard.php'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="Inventory/inventory.php" class="<?= $page=='inventory.php'?'active':'' ?>"><i class="fas fa-boxes"></i> Inventory</a>
        <a href="Quotation/quotations.php" class="<?= $page=='quotations.php'?'active':'' ?>"><i class="fas fa-file-invoice"></i> Quotations</a>
        <a href="JobCard/job_card.php" class="<?= $page=='job_card.php'?'active':'' ?>"><i class="fas fa-tools"></i> Job Cards</a>
        <a href="Invoice/invoices.php" class="<?= $page=='invoices.php'?'active':'' ?>"><i class="fas fa-receipt"></i> Invoices</a>
        <a href="Service/services_update.php" class="<?= $page=='services_update.php'?'active':'' ?>"><i class="fas fa-wrench"></i> Services Update</a>
        <a href="Report/reports.php" class="<?= $page=='reports.php'?'active':'' ?>"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="Expense/expenses.php" class="<?= $page=='expenses.php'?'active':'' ?>"><i class="fas fa-money-bill-wave"></i> Expenses</a>
        <a href="purchase-order/purchase_orders.php" class="<?= $page=='purchase_orders.php'?'active':'' ?>"><i class="fas fa-shopping-cart"></i> Purchase Orders</a>
        <a href="Employee/employees.php" class="<?= $page=='employees.php'?'active':'' ?>"><i class="fas fa-users"></i> Employees</a>
        <a href="hr-request/hr_requests.php" class="<?= $page=='hr_requests.php'?'active':'' ?>"><i class="fas fa-clipboard-check"></i> HR Requests</a>
        <a href="Client/clients.php" class="<?= $page=='clients.php'?'active':'' ?>"><i class="fas fa-users"></i> Clients</a>
        <a href="user-role/user_roles.php" class="<?= $page=='user_roles.php'?'active':'' ?>"><i class="fas fa-user-shield"></i> User Roles</a>
        <a href="Statutory/statutory.php" class="<?= $page=='statutory.php'?'active':'' ?>"><i class="fas fa-file-contract"></i> Statutory Docs</a>
        <a href="recycle-bin/recycle_bin.php" class="<?= $page=='recycle_bin.php'?'active':'' ?>"><i class="fas fa-trash-restore"></i> Recycle Bin</a>
        <a href="Auth/logout.php" onclick="showLogoutModal(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" class="logout-modal">
        <div class="logout-modal-content">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to log out?</p>
            <div class="logout-buttons">
                <button onclick="confirmLogout()" class="btn-logout">Yes, Logout</button>
                <button onclick="closeLogoutModal()" class="btn-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <style>
        .logout-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .logout-modal.show {
            display: flex;
        }
        .logout-modal-content {
            background: white;
            border-radius: 16px;
            padding: 36px 32px;
            max-width: 420px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(245, 166, 35, 0.3);
            border: 2px solid #F5A623;
            animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .logout-icon {
            width: 80px;
            height: 80px;
            background: #F5A623;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 36px;
            box-shadow: 0 8px 24px rgba(245, 166, 35, 0.3);
        }
        .logout-modal-content h3 {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: #4a4a4a;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .logout-modal-content p {
            font-size: 16px;
            color: #4a4a4a;
            margin-bottom: 28px;
            line-height: 1.6;
        }
        .logout-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .logout-buttons button {
            padding: 12px 28px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            letter-spacing: -0.01em;
        }
        .btn-logout {
            background: #F5A623;
            color: white;
            box-shadow: 0 4px 12px rgba(245, 166, 35, 0.3);
        }
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 106, 31, 0.4);
        }
        .btn-cancel {
            background: #F5F5F5;
            color: #4a4a4a;
            border: 2px solid #ddd;
        }
        .btn-cancel:hover {
            background: white;
            border-color: #F5A623;
            color: #F5A623;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="content-wrapper">
            <!-- ALL PAGE CONTENT WILL BE INJECTED HERE -->

<script>
// Show logout modal
function showLogoutModal(event) {
    event.preventDefault();
    document.getElementById('logoutModal').classList.add('show');
}

// Close logout modal
function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('show');
}

// Confirm logout
function confirmLogout() {
    window.location.href = 'logout.php';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('logoutModal');
    if (event.target === modal) {
        closeLogoutModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeLogoutModal();
    }
});

// Auto-dismiss success and error messages after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.success-message, .error-message, .alert-success, .alert-danger, .alert-info, .alert-warning');
    
    messages.forEach(function(message) {
        // Add fade-out animation after 5 seconds
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s ease-out';
            message.style.opacity = '0';
            
            // Remove from DOM after fade completes
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 5000); // 5 seconds
    });
});
</script>
