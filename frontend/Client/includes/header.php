<?php
// Client Portal Header
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

// Security check - must be logged in with client role
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role_name'] ?? '') !== 'client') {
    header('Location: ../Admin/login.php');
    exit;
}

// Get client information from session
$userId = $_SESSION['user_id'];
$email = $_SESSION['email'] ?? '';

// Refresh status from database on every page load
$stmt = $pdo->prepare("SELECT status FROM clients WHERE id = ?");
$stmt->execute([$userId]);
$currentStatus = $stmt->fetchColumn();
$_SESSION['status'] = $currentStatus ?: 'pending';

// Check if account is pending approval - redirect to pending page
if ($_SESSION['role_name'] === 'client' && ($_SESSION['status'] ?? 'pending') === 'pending') {
    // Allow access to pending-approval.php and logout.php only
    $currentPage = basename($_SERVER['PHP_SELF']);
    if ($currentPage !== 'pending-approval.php' && $currentPage !== 'logout.php') {
        header('Location: pending-approval.php');
        exit;
    }
}

// Look up client record dynamically by email
$client = null;
$clientId = null;
if ($email) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($client) {
        $clientId = $client['id'];
    }
}

// Get notifications for this user
$notification_count = 0;
$recent_notifications = [];
try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(255),
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Get unread count
    $notifStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notifStmt->execute([$userId]);
    $notification_count = $notifStmt->fetchColumn();
    
    // Get recent notifications (last 10)
    $recentStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $recentStmt->execute([$userId]);
    $recent_notifications = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notification_count = 0;
    $recent_notifications = [];
}

// Build client name from first_name and last_name
if ($client) {
    $clientName = trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
    if (empty($clientName)) {
        $clientName = $_SESSION['username'] ?? 'Client';
    }
} else {
    $clientName = $_SESSION['username'] ?? 'Client';
}
$current_page = basename($_SERVER['PHP_SELF']);
$business = getBusiness();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?> - <?php echo htmlspecialchars($business['name']); ?> Client Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../Admin/css/erp-layout.css">
    <style>
        :root {
            --brand-primary: #F7A100;
            --brand-primary-dark: #E09000;
            --brand-primary-light: #FFB52E;
            --brand-primary-subtle: #FFF5E0;
        }
        
        /* ── RESPONSIVE STYLES ─────────────────────────────────────────────── */
        
        /* Mobile Menu Toggle - hidden on desktop */
        .mobile-menu-toggle { display: none !important; }
        
        /* Tablet: max-width 1024px */
        @media (max-width: 1024px) {
            .erp-sidebar {
                width: 240px;
            }
            .erp-main {
                margin-left: 240px;
            }
            .erp-content {
                padding: 20px;
            }
        }
        
        /* Mobile: max-width 768px */
        @media (max-width: 768px) {
            /* Show mobile menu toggle */
            .mobile-menu-toggle { display: flex !important; }
            
            /* Sidebar - hidden by default, overlay when open */
            .erp-sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100vh;
                width: 280px;
                z-index: 1000;
                transition: left 0.3s ease;
                box-shadow: 2px 0 20px rgba(0,0,0,0.3);
            }
            .erp-sidebar.active {
                left: 0;
            }
            
            /* Overlay for sidebar */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }
            .sidebar-overlay.active {
                display: block;
            }
            
            /* Main content - full width */
            .erp-main {
                margin-left: 0;
                width: 100%;
            }
            
            /* Header adjustments */
            .erp-header {
                padding: 12px 16px;
            }
            
            /* Content area */
            .erp-content {
                padding: 16px;
            }
            
            /* Cards stack vertically */
            .erp-card {
                margin-bottom: 16px;
            }
            
            /* Grid layouts become single column */
            .erp-grid {
                grid-template-columns: 1fr !important;
            }
            
            /* Stats grid */
            .stats-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            /* Tables scroll horizontally */
            .erp-table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .erp-table {
                min-width: 600px;
            }
            
            /* Buttons full width on mobile */
            .erp-btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 8px;
            }
            
            /* Form inputs full width */
            .erp-form-group input,
            .erp-form-group select,
            .erp-form-group textarea {
                width: 100%;
            }
            
            /* Page header adjustments */
            .erp-page-header {
                padding: 16px;
            }
            .erp-page-title {
                font-size: 20px;
            }
            
            /* Breadcrumb adjustments */
            .erp-breadcrumb {
                font-size: 12px;
                flex-wrap: wrap;
            }
            
            /* Notification dropdown - full width on mobile */
            #notificationDropdown {
                width: calc(100vw - 32px) !important;
                right: 16px !important;
                left: 16px !important;
                max-width: none;
            }
            
            /* User dropdown adjustments */
            #userDropdown {
                right: 16px !important;
                left: 16px !important;
                width: calc(100vw - 32px) !important;
            }
        }
        
        /* Small mobile: max-width 480px */
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr !important;
            }
            
            .erp-page-title {
                font-size: 18px;
            }
            
            .erp-header-right {
                gap: 8px;
            }
            
            .erp-user-info {
                display: none;
            }
        }
    </style>
<style>
    .erp-page-header .erp-breadcrumb { display: none !important; }
</style>
</head>
<body>
    <div class="erp-layout">
        <!-- Sidebar -->
        <aside class="erp-sidebar" id="sidebar">
            <div class="erp-sidebar-header">
                <a href="dashboard.php" class="erp-logo">
                    <?php 
                    $logoPath = '../assets/images/companylogo.jpeg';
                    if (file_exists($logoPath)): 
                    ?>
                        <img src="<?php echo $logoPath . '?v=' . time(); ?>" alt="<?php echo htmlspecialchars($business['name']); ?>" style="max-height: 50px;">
                    <?php else: ?>
                        <div class="erp-logo-text"><?php echo htmlspecialchars($business['name']); ?></div>
                    <?php endif; ?>
                </a>
            </div>
            
            <nav class="erp-sidebar-nav">
                <div class="erp-menu-group">
                    <div class="erp-menu-label">Client Portal</div>
                    <a href="dashboard.php" class="erp-menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span class="erp-menu-text">Dashboard</span>
                    </a>
                    <a href="quotations.php" class="erp-menu-item <?php echo in_array($current_page, ['quotations.php', 'view_quotation.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i>
                        <span class="erp-menu-text">Quotations</span>
                    </a>
                    <a href="invoices.php" class="erp-menu-item <?php echo in_array($current_page, ['invoices.php', 'view_invoice.php']) ? 'active' : ''; ?>">
                        <i class="fas fa-receipt"></i>
                        <span class="erp-menu-text">Invoices</span>
                    </a>
                </div>
            </nav>
            
            <div class="erp-sidebar-footer">
                <button class="erp-sidebar-toggle" id="sidebarToggle" title="Collapse Sidebar">
                    <i class="fas fa-chevron-left"></i>
                </button>
            </div>
        </aside>
        
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        
        <!-- Main Content -->
        <main class="erp-main">
            <!-- Header -->
            <header class="erp-header">
                <div class="erp-header-left">
                    <!-- Mobile Menu Toggle -->
                    <button class="mobile-menu-toggle erp-header-btn" onclick="toggleSidebar()" title="Menu" style="display: none; margin-right: 12px;">
                        <i class="fas fa-bars"></i>
                    </button>
<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
$breadcrumb_map = [
    'dashboard'          => [['Home','dashboard.php']],
    'job_card'           => [['Home','dashboard.php'],['Job Cards','job_card.php']],
    'add_job_card'       => [['Home','dashboard.php'],['Job Cards','job_card.php'],['Add Job Card',null]],
    'edit_job_card'      => [['Home','dashboard.php'],['Job Cards','job_card.php'],['Edit Job Card',null]],
    'view_job_card'      => [['Home','dashboard.php'],['Job Cards','job_card.php'],['View Job Card',null]],
    'quotations'         => [['Home','dashboard.php'],['Quotations','quotations.php']],
    'add_quotation'      => [['Home','dashboard.php'],['Quotations','quotations.php'],['Add Quotation',null]],
    'edit_quotation'     => [['Home','dashboard.php'],['Quotations','quotations.php'],['Edit Quotation',null]],
    'invoices'           => [['Home','dashboard.php'],['Invoices','invoices.php']],
    'add_invoice'        => [['Home','dashboard.php'],['Invoices','invoices.php'],['Add Invoice',null]],
    'view_invoice'       => [['Home','dashboard.php'],['Invoices','invoices.php'],['View Invoice',null]],
    'inventory'          => [['Home','dashboard.php'],['Inventory','inventory.php']],
    'add_inventory'      => [['Home','dashboard.php'],['Inventory','inventory.php'],['Add Item',null]],
    'edit_inventory'     => [['Home','dashboard.php'],['Inventory','inventory.php'],['Edit Item',null]],
    'purchase_orders'    => [['Home','dashboard.php'],['Purchase Orders','purchase_orders.php']],
    'add_purchase_order' => [['Home','dashboard.php'],['Purchase Orders','purchase_orders.php'],['Add Order',null]],
    'view_purchase_order'=> [['Home','dashboard.php'],['Purchase Orders','purchase_orders.php'],['View Order',null]],
    'employees'          => [['Home','dashboard.php'],['Employees','employees.php']],
    'add_employee'       => [['Home','dashboard.php'],['Employees','employees.php'],['Add Employee',null]],
    'view_employee'      => [['Home','dashboard.php'],['Employees','employees.php'],['View Employee',null]],
    'edit_employee'      => [['Home','dashboard.php'],['Employees','employees.php'],['Edit Employee',null]],
    'hr_requests'        => [['Home','dashboard.php'],['HR Requests','hr_requests.php']],
    'expenses'           => [['Home','dashboard.php'],['Expenses','expenses.php']],
    'add_expense'        => [['Home','dashboard.php'],['Expenses','expenses.php'],['Add Expense',null]],
    'reports'            => [['Home','dashboard.php'],['Reports','reports.php']],
    'user_roles'         => [['Home','dashboard.php'],['User Roles','user_roles.php']],
    'statutory'          => [['Home','dashboard.php'],['Statutory Docs','statutory.php']],
    'settings'           => [['Home','dashboard.php'],['Settings','settings.php']],
    'services_update'    => [['Home','dashboard.php'],['Services','services_update.php']],
    'recycle_bin'        => [['Home','dashboard.php'],['Recycle Bin','recycle_bin.php']],
    'manager_dashboard'  => [['Home','manager_dashboard.php']],
    'job_cards'          => [['Home','manager_dashboard.php'],['Job Cards','job_cards.php']],
    'clients'            => [['Home','dashboard.php'],['Clients','clients.php']],
    'vehicles'           => [['Home','dashboard.php'],['Vehicles','vehicles.php']],
];
$crumbs = $breadcrumb_map[$current] ?? [['Home','dashboard.php'],[ ucwords(str_replace('_',' ',$current)), null]];
?>
    <nav style="display:flex; align-items:center; gap:6px; font-size:14px;">
        <i class="fas fa-home" style="color:#6B7280;"></i>
        <?php foreach($crumbs as $i => $crumb): ?>
            <?php if($i > 0): ?>
                <span style="color:#6B7280;">/</span>
            <?php endif; ?>
            <?php if($crumb[1] && $i < count($crumbs)-1): ?>
                <a href="<?php echo $crumb[1]; ?>" style="color:#6B7280; text-decoration:none;"><?php echo $crumb[0]; ?></a>
            <?php else: ?>
                <span style="color:#F7A100; font-weight:600;"><?php echo $crumb[0]; ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</div>
                
                <div class="erp-header-right">
                    <!-- Notifications -->
                    <div style="position:relative;">
                        <button class="erp-header-btn" title="Notifications" onclick="toggleNotifications()" style="position:relative;">
                            <i class="fas fa-bell"></i>
                            <?php if ($notification_count > 0): ?>
                                <span style="position:absolute; top:-5px; right:-5px; background:#EF4444; color:white; border-radius:50%; padding:2px 6px; font-size:11px; font-weight:700;"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </button>
                        
                        <!-- Notification Dropdown -->
                        <div id="notificationDropdown" style="display:none; position:absolute; top:50px; right:0; background:white; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,0.2); border:1px solid #E5E7EB; width:350px; max-height:400px; overflow-y:auto; z-index:1002;">
                            <div style="padding:15px 20px; border-bottom:1px solid #E5E7EB; display:flex; justify-content:space-between; align-items:center;">
                                <h4 style="margin:0; font-size:16px; font-weight:700;">Notifications</h4>
                                <?php if ($notification_count > 0): ?>
                                    <span style="background:#F7A100; color:white; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600;"><?php echo $notification_count; ?> new</span>
                                <?php endif; ?>
                            </div>
                            <div style="padding:0;">
                                <?php if (empty($recent_notifications)): ?>
                                    <div style="padding:30px; text-align:center; color:#6B7280;">
                                        <i class="fas fa-bell-slash" style="font-size:32px; margin-bottom:10px; opacity:0.5;"></i>
                                        <p style="margin:0; font-size:14px;">No notifications yet</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_notifications as $notif): ?>
                                        <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" 
                                           onclick="markNotificationRead(<?php echo $notif['id']; ?>)"
                                           style="display:block; padding:15px 20px; border-bottom:1px solid #F3F4F6; text-decoration:none; color:inherit; background:<?php echo $notif['is_read'] ? 'white' : '#FFF5E0'; ?>; transition:background 0.2s;">
                                            <div style="display:flex; align-items:start; gap:12px;">
                                                <div style="width:8px; height:8px; border-radius:50%; background:<?php echo $notif['is_read'] ? '#D1D5DB' : '#F7A100'; ?>; margin-top:6px; flex-shrink:0;"></div>
                                                <div style="flex:1;">
                                                    <div style="font-weight:600; font-size:14px; color:#1F2937; margin-bottom:3px;"><?php echo htmlspecialchars($notif['title']); ?></div>
                                                    <?php if ($notif['message']): ?>
                                                        <div style="font-size:13px; color:#6B7280; line-height:1.4;"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                    <?php endif; ?>
                                                    <div style="font-size:11px; color:#9CA3AF; margin-top:5px;">
                                                        <?php echo date('M j, Y g:i A', strtotime($notif['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- User Menu with Direct Logout Link -->
                    <div style="position: relative;">
                        <button class="erp-user-menu" id="userMenuButton" type="button" style="cursor: pointer; border: none; background: transparent; position: relative; z-index: 100;">
                            <div class="erp-user-avatar">
                                <?php echo strtoupper(substr($clientName, 0, 1)); ?>
                            </div>
                            <div class="erp-user-info">
                                <div class="erp-user-name"><?php echo htmlspecialchars($clientName); ?></div>
                                <div class="erp-user-role">Client</div>
                            </div>
                            <i class="fas fa-chevron-down" style="color: var(--gray-400); font-size: 12px;"></i>
                        </button>
                        
                        <div class="erp-dropdown" id="userDropdown" style="display: none; position: absolute; top: 50px; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); border: 1px solid #E5E7EB; min-width: 220px; z-index: 1001;">
                            <div style="padding: 16px 20px; border-bottom: 1px solid #E5E7EB;">
                                <div style="font-weight: 600; color: #1F2937; font-size: 14px;"><?php echo htmlspecialchars($clientName); ?></div>
                                <div style="font-size: 12px; color: #6B7280; margin-top: 2px;"><?php echo htmlspecialchars($email); ?></div>
                            </div>
                            <a href="profile.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #4B5563; text-decoration: none; font-size: 14px; transition: background 0.2s;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
                                <i class="fas fa-user" style="width: 16px; color: #6B7280;"></i> Profile
                            </a>
                            <a href="settings.php" style="display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #4B5563; text-decoration: none; font-size: 14px; transition: background 0.2s;" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
                                <i class="fas fa-cog" style="width: 16px; color: #6B7280;"></i> Settings
                            </a>
                            <a href="#" onclick="showLogoutConfirmation(event)" style="display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: #EF4444; text-decoration: none; font-size: 14px; border-top: 1px solid #E5E7EB; transition: background 0.2s; font-weight: 600;" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">
                                <i class="fas fa-sign-out-alt" style="width: 16px;"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="erp-content">
                <script>
                function toggleNotifications() {
                    const dropdown = document.getElementById('notificationDropdown');
                    const userDropdown = document.getElementById('userDropdown');
                    if (userDropdown) userDropdown.style.display = 'none';
                    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
                }
                
                function toggleUserMenu() {
                    const dropdown = document.getElementById('userDropdown');
                    const notifDropdown = document.getElementById('notificationDropdown');
                    if (notifDropdown) notifDropdown.style.display = 'none';
                    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
                }
                
                function markNotificationRead(id) {
                    fetch('../Admin/mark_notification_read.php?id=' + id, { method: 'POST' })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) location.reload();
                        })
                        .catch(err => console.error('Error:', err));
                }
                
                function toggleSearch() {
                let searchBar = document.getElementById('globalSearchBar');
                if (searchBar) { searchBar.remove(); return; }

                searchBar = document.createElement('div');
                searchBar.id = 'globalSearchBar';
                searchBar.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.35); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); z-index:9999; display:flex; align-items:flex-start; justify-content:center; padding-top:80px;';

                searchBar.innerHTML = `
  <div style="width:620px; max-width:94vw; background:rgba(255,255,255,0.92); backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px); border-radius:16px; box-shadow:0 24px 60px rgba(0,0,0,0.25); border:1px solid rgba(255,255,255,0.6); overflow:hidden;">
    <div style="display:flex; align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid rgba(0,0,0,0.08);">
      <div style="width:40px; height:40px; border-radius:10px; background:#F7A100; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
        <i class="fas fa-search" style="color:#fff; font-size:16px;"></i>
      </div>
      <input type="text" id="globalSearchInput" placeholder="Search job cards, clients, invoices, employees..."
        style="flex:1; border:none; outline:none; font-size:15px; color:#1a1a1a; background:transparent; font-family:inherit; font-weight:500;">
      <kbd style="background:#f3f4f6; color:#6B7280; border:1px solid #e5e7eb; border-radius:6px; padding:4px 8px; font-size:11px; font-family:inherit; cursor:pointer;" onclick="document.getElementById('globalSearchBar').remove()">ESC</kbd>
    </div>
    <div id="globalSearchResults" style="max-height:420px; overflow-y:auto; padding:8px 0; background:transparent;">
      <div style="padding:32px; text-align:center; color:#9CA3AF; font-size:14px;">
        <i class="fas fa-search" style="font-size:28px; color:#e5e7eb; display:block; margin-bottom:12px;"></i>
        Start typing to search...
      </div>
    </div>
    <div style="padding:10px 20px; background:rgba(247,161,0,0.06); border-top:1px solid rgba(0,0,0,0.06); display:flex; gap:20px;">
      <span style="font-size:11px; color:#9CA3AF;"><kbd style="background:#f3f4f6; color:#6B7280; border:1px solid #e5e7eb; border-radius:4px; padding:2px 6px; font-size:10px;">↑↓</kbd> navigate</span>
      <span style="font-size:11px; color:#9CA3AF;"><kbd style="background:#f3f4f6; color:#6B7280; border:1px solid #e5e7eb; border-radius:4px; padding:2px 6px; font-size:10px;">↵</kbd> open</span>
      <span style="font-size:11px; color:#9CA3AF;"><kbd style="background:#f3f4f6; color:#6B7280; border:1px solid #e5e7eb; border-radius:4px; padding:2px 6px; font-size:10px;">ESC</kbd> close</span>
    </div>
  </div>
`;
                document.body.appendChild(searchBar);
                setTimeout(() => document.getElementById('globalSearchInput').focus(), 100);

                const input = document.getElementById('globalSearchInput');
                input.addEventListener('input', function() {
                    const query = this.value.trim();
                    const resultsDiv = document.getElementById('globalSearchResults');
                    if (query.length < 1) {
                        resultsDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#9CA3AF; font-size:14px;">Type to search...</div>';
                        return;
                    }
                    resultsDiv.innerHTML = '<div style="padding:32px; text-align:center; color:#9CA3AF; font-size:14px;"><i class="fas fa-spinner fa-spin" style="color:#F7A100; font-size:24px; display:block; margin-bottom:12px;"></i>Searching...</div>';
                    fetch('../Admin/search.php?q=' + encodeURIComponent(query), { credentials: 'same-origin' })
                        .then(r => { console.log('Search response:', r.status); return r.text(); })
                        .then(text => { console.log('Search raw:', text); const data = JSON.parse(text);
                            if (!data.length) {
                                resultsDiv.innerHTML = '<div style="padding:32px; text-align:center; color:#9CA3AF; font-size:14px;"><i class="fas fa-search-minus" style="font-size:28px; color:#e5e7eb; display:block; margin-bottom:12px;"></i>No results for "<span style=\'color:#F7A100; font-weight:600;\'>' + query + '</span>"</div>';
                                return;
                            }
                            resultsDiv.innerHTML = data.map((item, index) => `
  <a href="${item.url}" class="sv-search-result" data-index="${index}"
    style="display:flex; align-items:center; gap:14px; padding:12px 20px; text-decoration:none; color:#1a1a1a; border-bottom:1px solid rgba(0,0,0,0.05); transition:background 0.15s; cursor:pointer;"
    onmouseover="this.style.background='rgba(247,161,0,0.08)'" onmouseout="this.style.background='transparent'">
    <div style="width:40px; height:40px; border-radius:10px; background:#FFF5E0; border:1px solid #F7A100; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
      <i class="${item.icon}" style="color:#F7A100; font-size:16px;"></i>
    </div>
    <div style="flex:1; min-width:0;">
      <div style="font-weight:600; font-size:14px; color:#1a1a1a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.title}</div>
      <div style="font-size:12px; color:#6B7280; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${item.subtitle}</div>
    </div>
    <span style="font-size:11px; color:#F7A100; background:#FFF5E0; border:1px solid #F7A100; padding:3px 10px; border-radius:20px; flex-shrink:0; font-weight:600;">${item.type}</span>
    <i class="fas fa-arrow-right" style="color:#D1D5DB; font-size:12px; flex-shrink:0;"></i>
  </a>
`).join('');
                        })
                        .catch(err => { console.error('Search error:', err); resultsDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#EF4444; font-size:14px;">Search failed. Please try again.</div>'; });
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') searchBar.remove();
                });

                setTimeout(() => {
                    document.addEventListener('click', function closeSearch(e) {
                        if (!searchBar.contains(e.target) && !e.target.closest('[onclick="toggleSearch()"]')) {
                            searchBar.remove();
                            document.removeEventListener('click', closeSearch);
                        }
                    });
                }, 200);
            }

            function showLogoutConfirm() {
                let modal = document.createElement('div');
                modal.id = 'logoutModal';
                modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.45); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:99999; display:flex; align-items:center; justify-content:center;';
                modal.innerHTML = `
        <div style="width:400px; max-width:92vw; background:rgba(255,255,255,0.95); backdrop-filter:blur(20px); border-radius:20px; box-shadow:0 24px 60px rgba(0,0,0,0.2); border:1px solid rgba(255,255,255,0.6); overflow:hidden;">
            <div style="padding:32px 32px 24px; text-align:center;">
                <div style="width:64px; height:64px; background:#FFF5E0; border:2px solid #F7A100; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
                    <i class="fas fa-sign-out-alt" style="color:#F7A100; font-size:26px;"></i>
                </div>
                <h3 style="font-size:20px; font-weight:700; color:#1a1a1a; margin:0 0 8px;">Sign Out</h3>
                <p style="font-size:14px; color:#6B7280; margin:0; line-height:1.6;">Are you sure you want to log out of <strong style="color:#1a1a1a;">SV Auto</strong>? Any unsaved changes will be lost.</p>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; border-top:1px solid rgba(0,0,0,0.08);">
                <button onclick="document.getElementById('logoutModal').remove()"
                    style="padding:16px; background:transparent; border:none; border-right:1px solid rgba(0,0,0,0.08); font-size:15px; font-weight:600; color:#6B7280; cursor:pointer; font-family:inherit; transition:background 0.15s;"
                    onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='transparent'">
                    Cancel
                </button>
                <a href="../Admin/logout.php"
                    style="padding:16px; background:transparent; border:none; font-size:15px; font-weight:700; color:#EF4444; cursor:pointer; font-family:inherit; text-decoration:none; display:flex; align-items:center; justify-content:center; transition:background 0.15s;"
                    onmouseover="this.style.background='#fff5f5'" onmouseout="this.style.background='transparent'">
                    <i class="fas fa-sign-out-alt" style="margin-right:8px;"></i> Sign Out
                </a>
            </div>
        </div>
    `;
                document.body.appendChild(modal);
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) modal.remove();
                });
                return false;
            }
    }
}
                
                // Sidebar toggle for mobile
                function toggleSidebar() {
                    const sidebar = document.getElementById('sidebar');
                    const overlay = document.getElementById('sidebarOverlay');
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
                }
                
                // Close sidebar when clicking on a menu item (mobile)
                document.querySelectorAll('.erp-menu-item').forEach(item => {
                    item.addEventListener('click', () => {
                        if (window.innerWidth <= 768) {
                            toggleSidebar();
                        }
                    });
                });
                
                // Close dropdowns when clicking outside
                document.addEventListener('click', function(e) {
                    const notifBtn = document.querySelector('[onclick="toggleNotifications()"]');
                    const notifDropdown = document.getElementById('notificationDropdown');
                    const userMenu = document.querySelector('.erp-user-menu');
                    const userDropdown = document.getElementById('userDropdown');
                    
                    if (notifDropdown && notifBtn && !notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                        notifDropdown.style.display = 'none';
                    }
                    if (userDropdown && userMenu && !userMenu.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.style.display = 'none';
                    }
                });
                </script>
