<?php
// includes/header.php - ERP Layout Header
// This file should be included at the top of every admin page

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../backend/config/user_drafts.php';

$erp_script_path_parts = array_values(array_filter(explode('/', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')))));
$erp_admin_idx = array_search('Admin', $erp_script_path_parts, true);
$erp_admin_after_parts = ($erp_admin_idx !== false) ? array_slice($erp_script_path_parts, $erp_admin_idx + 1) : [];
$erp_admin_rel_prefix = empty($erp_admin_after_parts) ? '' : str_repeat('../', count($erp_admin_after_parts));
if ($erp_admin_idx !== false) {
    $erp_admin_base_path = '/' . implode('/', array_slice($erp_script_path_parts, 0, $erp_admin_idx + 1)) . '/';
} else {
    $erp_admin_base_path = '/';
}
$erp_admin_base_href = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $erp_admin_base_path;

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $erp_admin_rel_prefix . 'login.php');
    exit;
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'User';
$role_name = $_SESSION['role_name'] ?? 'User';
$business = getBusiness();

erp_ensure_user_avatar_column();
$erp_user_avatar_web = erp_get_user_avatar_path($user_id);
$erp_user_avatar_src = erp_user_avatar_src($erp_user_avatar_web);
$erp_user_avatar_initial = erp_user_avatar_initial($username);
$erp_settings_href = htmlspecialchars('settings.php#my-profile', ENT_QUOTES, 'UTF-8');

// Get current page for active menu
$current_page = basename($_SERVER['PHP_SELF']);

$erp_page_in = static function (array $pages) use ($current_page): bool {
    return in_array($current_page, $pages, true);
};

$erp_nav_main = [
    [
        'type' => 'link',
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'href' => 'dashboard.php',
        'pages' => ['dashboard.php'],
    ],
    [
        'type' => 'group',
        'label' => 'Operations',
        'icon' => 'fa-screwdriver-wrench',
        'pages' => ['job_card.php', 'add_job_card.php', 'job_card_preview.php', 'view_job_card.php', 'edit_job_card.php', 'quotations.php', 'add_quotation.php', 'edit_quotation.php', 'view_quotation.php', 'create_quotation.php', 'invoices.php', 'add_invoice.php', 'view_invoice.php', 'pay_invoice.php', 'services_update.php', 'edit_service.php', 'view_service.php', 'add_service.php'],
        'children' => [
            ['label' => 'Job Cards', 'href' => 'JobCard/job_card.php', 'pages' => ['job_card.php', 'add_job_card.php', 'job_card_preview.php', 'view_job_card.php', 'edit_job_card.php']],
            ['label' => 'Quotations', 'href' => 'Quotation/quotations.php', 'pages' => ['quotations.php', 'add_quotation.php', 'edit_quotation.php', 'view_quotation.php', 'create_quotation.php']],
            ['label' => 'Invoices', 'href' => 'Invoice/invoices.php', 'pages' => ['invoices.php', 'add_invoice.php', 'view_invoice.php', 'pay_invoice.php']],
            ['label' => 'Services', 'href' => 'Service/services_update.php', 'pages' => ['services_update.php', 'edit_service.php', 'view_service.php', 'add_service.php']],
        ],
    ],
    [
        'type' => 'group',
        'label' => 'Inventory',
        'icon' => 'fa-boxes-stacked',
        'pages' => ['inventory.php', 'add_inventory.php', 'edit_inventory.php', 'view_inventory.php', 'purchase_orders.php', 'add_purchase_order.php', 'view_purchase_order.php'],
        'children' => [
            ['label' => 'Inventory', 'href' => 'Inventory/inventory.php', 'pages' => ['inventory.php', 'add_inventory.php', 'edit_inventory.php', 'view_inventory.php']],
            ['label' => 'Purchase Orders', 'href' => 'purchase-order/purchase_orders.php', 'pages' => ['purchase_orders.php', 'add_purchase_order.php', 'view_purchase_order.php']],
        ],
    ],
    [
        'type' => 'group',
        'label' => 'Finance',
        'icon' => 'fa-coins',
        'pages' => ['expenses.php', 'add_expense.php', 'edit_expense.php', 'reports.php'],
        'children' => [
            ['label' => 'Expenses', 'href' => 'Expense/expenses.php', 'pages' => ['expenses.php', 'add_expense.php', 'edit_expense.php']],
            ['label' => 'Reports', 'href' => 'Report/reports.php', 'pages' => ['reports.php']],
        ],
    ],
    [
        'type' => 'group',
        'label' => 'Human Resources',
        'icon' => 'fa-user-group',
        'pages' => ['employees.php', 'add_employee.php', 'edit_employee.php', 'view_employee.php', 'hr_requests.php', 'add_hr_request.php'],
        'children' => [
            ['label' => 'Employees', 'href' => 'Employee/employees.php', 'pages' => ['employees.php', 'add_employee.php', 'edit_employee.php', 'view_employee.php']],
            ['label' => 'HR Requests', 'href' => 'hr-request/hr_requests.php', 'pages' => ['hr_requests.php', 'add_hr_request.php']],
        ],
    ],
    [
        'type' => 'group',
        'label' => 'Administration',
        'icon' => 'fa-building-user',
        'pages' => ['clients.php', 'user_roles.php', 'create_role.php', 'edit_role.php', 'view_role.php', 'statutory.php', 'upload_statutory.php', 'recycle_bin.php'],
        'children' => [
            ['label' => 'Clients', 'href' => 'Client/clients.php', 'pages' => ['clients.php']],
            ['label' => 'User Roles', 'href' => 'user-role/user_roles.php', 'pages' => ['user_roles.php', 'create_role.php', 'edit_role.php', 'view_role.php']],
            ['label' => 'Statutory Docs', 'href' => 'Statutory/statutory.php', 'pages' => ['statutory.php', 'upload_statutory.php']],
            ['label' => 'Recycle Bin', 'href' => 'recycle-bin/recycle_bin.php', 'pages' => ['recycle_bin.php']],
        ],
    ],
];

$erp_support_email = trim((string) ($business['email'] ?? ''));
$erp_support_href = $erp_support_email !== '' ? 'mailto:' . rawurlencode($erp_support_email) . '?subject=' . rawurlencode('ERP Support Request') : '#';

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
    $notifStmt->execute([$user_id]);
    $notification_count = $notifStmt->fetchColumn();
    
    // Get recent notifications (last 10)
    $recentStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $recentStmt->execute([$user_id]);
    $recent_notifications = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $notification_count = 0;
    $recent_notifications = [];
}

// Generate breadcrumb
function getBreadcrumb() {
    $page = basename($_SERVER['PHP_SELF'], '.php');
    $page = str_replace('_', ' ', $page);
    $page = ucwords($page);
    
    // Custom mappings
    $mappings = [
        'Dashboard' => 'Dashboard',
        'Inventory' => 'Inventory',
        'Job Card' => 'Job Cards',
        'Job Cards' => 'Job Cards',
        'Quotations' => 'Quotations',
        'Invoices' => 'Invoices',
        'Services Update' => 'Services',
        'Reports' => 'Reports',
        'Expenses' => 'Expenses',
        'Purchase Orders' => 'Purchase Orders',
        'Employees' => 'Employees',
        'Hr Requests' => 'HR Requests',
        'User Roles' => 'User Roles',
        'Statutory' => 'Statutory Documents',
        'Recycle Bin' => 'Recycle Bin',
        'Settings' => 'Settings',
    ];
    
    return $mappings[$page] ?? $page;
}

$page_title = getBreadcrumb();

$erp_breadcrumb_page_labels = [
    'dashboard' => 'Dashboard',
    'job_card' => 'All Job Cards',
    'add_job_card' => 'Add Job Card',
    'edit_job_card' => 'Edit Job Card',
    'view_job_card' => 'View Job Card',
    'job_card_preview' => 'Job Card Preview',
    'quotations' => 'All Quotations',
    'add_quotation' => 'Add Quotation',
    'edit_quotation' => 'Edit Quotation',
    'view_quotation' => 'View Quotation',
    'create_quotation' => 'Create Quotation',
    'invoices' => 'All Invoices',
    'add_invoice' => 'Add Invoice',
    'view_invoice' => 'View Invoice',
    'pay_invoice' => 'Pay Invoice',
    'services_update' => 'All Services',
    'add_service' => 'Add Service',
    'edit_service' => 'Edit Service',
    'view_service' => 'View Service',
    'inventory' => 'All Inventory',
    'add_inventory' => 'Add Item',
    'edit_inventory' => 'Edit Item',
    'view_inventory' => 'View Item',
    'purchase_orders' => 'All Purchase Orders',
    'add_purchase_order' => 'Add Order',
    'view_purchase_order' => 'View Order',
    'expenses' => 'All Expenses',
    'add_expense' => 'Add Expense',
    'edit_expense' => 'Edit Expense',
    'reports' => 'Reports',
    'employees' => 'All Employees',
    'add_employee' => 'Add Employee',
    'edit_employee' => 'Edit Employee',
    'view_employee' => 'View Employee',
    'hr_requests' => 'All HR Requests',
    'add_hr_request' => 'Add HR Request',
    'clients' => 'Clients',
    'user_roles' => 'User Roles',
    'create_role' => 'Create Role',
    'edit_role' => 'Edit Role',
    'view_role' => 'View Role',
    'statutory' => 'Statutory Documents',
    'upload_statutory' => 'Upload Statutory',
    'recycle_bin' => 'Recycle Bin',
    'settings' => 'Settings',
];

$erp_breadcrumb_page_href = static function (string $sectionHref, string $pageFile) {
    $dir = dirname($sectionHref);
    if ($dir === '.' || $dir === '') {
        return $pageFile;
    }
    return $dir . '/' . $pageFile;
};

$erp_breadcrumb_page_label = static function (string $pageKey) use ($erp_breadcrumb_page_labels) {
    return $erp_breadcrumb_page_labels[$pageKey] ?? ucwords(str_replace('_', ' ', $pageKey));
};

$erp_build_breadcrumb_trail = static function (array $nav, string $currentPageFile, string $currentPageKey) use ($erp_breadcrumb_page_href, $erp_breadcrumb_page_label) {
    $trail = [
        [
            'label' => 'Home',
            'href' => 'dashboard.php',
            'dropdown' => null,
            'current' => ($currentPageKey === 'dashboard'),
        ],
    ];

    if ($currentPageKey === 'dashboard') {
        return $trail;
    }

    foreach ($nav as $item) {
        if (($item['type'] ?? '') !== 'group') {
            continue;
        }
        if (!in_array($currentPageFile, $item['pages'] ?? [], true)) {
            continue;
        }

        $groupDropdown = [];
        foreach ($item['children'] ?? [] as $child) {
            $groupDropdown[] = [
                'label' => (string) $child['label'],
                'href' => (string) $child['href'],
            ];
        }

        $trail[] = [
            'label' => (string) $item['label'],
            'href' => null,
            'dropdown' => $groupDropdown,
            'current' => false,
        ];

        foreach ($item['children'] ?? [] as $child) {
            $childPages = $child['pages'] ?? [];
            if (!in_array($currentPageFile, $childPages, true)) {
                continue;
            }

            $sectionDropdown = [];
            foreach ($childPages as $pageFile) {
                $pageKey = basename((string) $pageFile, '.php');
                $sectionDropdown[] = [
                    'label' => $erp_breadcrumb_page_label($pageKey),
                    'href' => $erp_breadcrumb_page_href((string) $child['href'], (string) $pageFile),
                    'active' => ($pageFile === $currentPageFile),
                ];
            }

            $sectionHref = (string) $child['href'];
            $listPageFile = basename($sectionHref);
            $isListPage = ($currentPageFile === $listPageFile);

            $trail[] = [
                'label' => (string) $child['label'],
                'href' => $sectionHref,
                'dropdown' => count($sectionDropdown) > 1 ? $sectionDropdown : null,
                'current' => $isListPage,
            ];

            if (!$isListPage) {
                $trail[] = [
                    'label' => $erp_breadcrumb_page_label($currentPageKey),
                    'href' => null,
                    'dropdown' => null,
                    'current' => true,
                ];
            }

            return $trail;
        }
    }

    return null;
};

$erp_resume_page_url = '';
$erp_resume_page_title = '';
if (user_resume_should_track()) {
    $erp_resume_page_url = user_resume_build_page_url();
    $erp_resume_page_title = trim((string) ($GLOBALS['erp_resume_title'] ?? ''));
    if ($erp_resume_page_title === '') {
        $erp_resume_page_title = user_resume_label_from_script(basename($_SERVER['PHP_SELF'] ?? ''));
    }
    user_resume_track_current_page((int) $user_id);
}

$current = basename($_SERVER['PHP_SELF'], '.php');
$breadcrumb_map = [
    'dashboard'          => [['Home','dashboard.php']],
    'job_card'           => [['Home','dashboard.php'],['Job Cards','JobCard/job_card.php']],
    'job_card_preview'   => [['Home','dashboard.php'],['Job Card','JobCard/job_card.php'],['Job Card Preview',null]],
    'add_job_card'       => [['Home','dashboard.php'],['Job Cards','JobCard/job_card.php'],['Add Job Card',null]],
    'edit_job_card'      => [['Home','dashboard.php'],['Job Cards','JobCard/job_card.php'],['Edit Job Card',null]],
    'view_job_card'      => [['Home','dashboard.php'],['Job Cards','JobCard/job_card.php'],['View Job Card',null]],
    'quotations'         => [['Home','dashboard.php'],['Quotations','Quotation/quotations.php']],
    'add_quotation'      => [['Home','dashboard.php'],['Quotations','Quotation/quotations.php'],['Add Quotation',null]],
    'edit_quotation'     => [['Home','dashboard.php'],['Quotations','Quotation/quotations.php'],['Edit Quotation',null]],
    'invoices'           => [['Home','dashboard.php'],['Invoices','Invoice/invoices.php']],
    'add_invoice'        => [['Home','dashboard.php'],['Invoices','Invoice/invoices.php'],['Add Invoice',null]],
    'view_invoice'       => [['Home','dashboard.php'],['Invoices','Invoice/invoices.php'],['View Invoice',null]],
    'inventory'          => [['Home','dashboard.php'],['Inventory','Inventory/inventory.php']],
    'add_inventory'      => [['Home','dashboard.php'],['Inventory','Inventory/inventory.php'],['Add Item',null]],
    'edit_inventory'     => [['Home','dashboard.php'],['Inventory','inventory.php'],['Edit Item',null]],
    'purchase_orders'    => [['Home','dashboard.php'],['Purchase Orders','purchase_orders.php']],
    'add_purchase_order' => [['Home','dashboard.php'],['Purchase Orders','purchase_orders.php'],['Add Order',null]],
    'view_purchase_order'=> [['Home','dashboard.php'],['Purchase Orders','purchase_orders.php'],['View Order',null]],
    'employees'          => [['Home','dashboard.php'],['Employees','employees.php']],
    'add_employee'       => [['Home','dashboard.php'],['Employees','employees.php'],['Add Employee',null]],
    'view_employee'      => [['Home','dashboard.php'],['Employees','employees.php'],['View Employee',null]],
    'edit_employee'      => [['Home','dashboard.php'],['Employees','employees.php'],['Edit Employee',null]],
    'hr_requests'        => [['Home','dashboard.php'],['HR Requests','hr_requests.php']],
    'add_hr_request'     => [['Home','dashboard.php'],['HR Requests','hr_requests.php'],['Add HR Request',null]],
    'expenses'           => [['Home','dashboard.php'],['Expenses','expenses.php']],
    'add_expense'        => [['Home','dashboard.php'],['Expenses','expenses.php'],['Add Expense',null]],
    'reports'            => [['Home','dashboard.php'],['Reports','reports.php']],
    'user_roles'         => [['Home','dashboard.php'],['User Roles','user_roles.php']],
    'statutory'          => [['Home','dashboard.php'],['Statutory Docs','statutory.php']],
    'settings'           => [['Home','dashboard.php'],['Settings','settings.php']],
    'services_update'    => [['Home','dashboard.php'],['Services','services_update.php']],
    'edit_service'       => [['Home','dashboard.php'],['Services','services_update.php'],['Edit Service',null]],
    'view_service'       => [['Home','dashboard.php'],['Services','services_update.php'],['View Service',null]],
    'add_service'        => [['Home','dashboard.php'],['Services','services_update.php'],['Add Service',null]],
    'recycle_bin'        => [['Home','dashboard.php'],['Recycle Bin','recycle_bin.php']],
    'manager_dashboard'  => [['Home','dashboard.php']],
    'job_cards'          => [['Home','dashboard.php'],['Job Cards','job_cards.php']],
    'clients'            => [['Home','dashboard.php'],['Clients','clients.php']],
    'vehicles'           => [['Home','dashboard.php'],['Vehicles','vehicles.php']],
];
$erp_breadcrumb_trail = $erp_build_breadcrumb_trail($erp_nav_main, $current_page, $current);
if ($erp_breadcrumb_trail === null) {
    $erp_breadcrumb_trail = [
        ['label' => 'Home', 'href' => 'dashboard.php', 'dropdown' => null, 'current' => false],
    ];
    $legacyCrumbs = $breadcrumb_map[$current] ?? [['Home', 'dashboard.php'], [ucwords(str_replace('_', ' ', $current)), null]];
    foreach ($legacyCrumbs as $i => $legacyCrumb) {
        if ($i === 0) {
            continue;
        }
        $erp_breadcrumb_trail[] = [
            'label' => (string) $legacyCrumb[0],
            'href' => $legacyCrumb[1] ? (string) $legacyCrumb[1] : null,
            'dropdown' => null,
            'current' => ($legacyCrumb[1] === null),
        ];
    }
}
if ($current === 'add_job_card' && isset($_GET['edit_id']) && (int) $_GET['edit_id'] > 0) {
    $erp_breadcrumb_trail = [
        ['label' => 'Home', 'href' => 'dashboard.php', 'dropdown' => null, 'current' => false],
        [
            'label' => 'Operations',
            'href' => null,
            'dropdown' => array_map(static function ($child) {
                return ['label' => (string) $child['label'], 'href' => (string) $child['href']];
            }, $erp_nav_main[1]['children'] ?? []),
            'current' => false,
        ],
        [
            'label' => 'Job Cards',
            'href' => 'JobCard/job_card.php',
            'dropdown' => [
                ['label' => 'All Job Cards', 'href' => 'JobCard/job_card.php', 'active' => false],
                ['label' => 'Add Job Card', 'href' => 'JobCard/add_job_card.php', 'active' => true],
                ['label' => 'Edit Job Card', 'href' => 'JobCard/add_job_card.php', 'active' => false],
            ],
            'current' => false,
        ],
        ['label' => 'Edit Job Card', 'href' => null, 'dropdown' => null, 'current' => true],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script>
        (function () {
            var marker = 'sv-erp-theme-system-default-v1';
            if (localStorage.getItem(marker) === '1') return;
            localStorage.removeItem('sv-site-theme');
            localStorage.removeItem('sv-site-theme-explicit');
            localStorage.removeItem('rd-dashboard-theme');
            localStorage.setItem(marker, '1');
        })();
    </script>
    <script src="../assets/js/site-theme-init.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars($erp_admin_base_href, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($erp_resume_page_url !== '' && $erp_resume_page_title !== ''): ?>
    <meta name="erp-resume-page" content="<?php echo htmlspecialchars($erp_resume_page_url, ENT_QUOTES, 'UTF-8'); ?>" data-title="<?php echo htmlspecialchars($erp_resume_page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo htmlspecialchars($business['name']); ?></title>
    <?php
    $erp_company_logo_file = __DIR__ . '/../../assets/images/companylogo2.png';
    if (is_file($erp_company_logo_file)):
        $erp_company_logo_icon = '../assets/images/companylogo2.png?v=' . (string) filemtime($erp_company_logo_file);
    ?>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($erp_company_logo_icon, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($erp_company_logo_icon, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- ERP Layout CSS -->
    <?php
    $erp_layout_css = __DIR__ . '/../css/erp-layout.css';
    $erp_layout_css_v = is_file($erp_layout_css) ? (string) filemtime($erp_layout_css) : '1';
    ?>
    <link rel="stylesheet" href="css/erp-layout.css?v=<?php echo htmlspecialchars($erp_layout_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $erp_student_table_css = __DIR__ . '/../css/student-table.css';
    $erp_student_table_css_v = is_file($erp_student_table_css) ? (string) filemtime($erp_student_table_css) : '1';
    ?>
    <link rel="stylesheet" href="css/student-table.css?v=<?php echo htmlspecialchars($erp_student_table_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $erp_header_tools_css = __DIR__ . '/../css/admin-header-tools.css';
    $erp_header_tools_css_v = is_file($erp_header_tools_css) ? (string) filemtime($erp_header_tools_css) : '1';
    ?>
    <link rel="stylesheet" href="css/admin-header-tools.css?v=<?php echo htmlspecialchars($erp_header_tools_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $erp_cake_layout_css = __DIR__ . '/../css/cake-layout.css';
    $erp_cake_layout_css_v = is_file($erp_cake_layout_css) ? (string) filemtime($erp_cake_layout_css) : '1';
    $erp_cake_dashboard_css = __DIR__ . '/../css/cake-dashboard.css';
    $erp_cake_dashboard_css_v = is_file($erp_cake_dashboard_css) ? (string) filemtime($erp_cake_dashboard_css) : '1';
    ?>
    <link rel="stylesheet" href="css/cake-layout.css?v=<?php echo htmlspecialchars($erp_cake_layout_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="css/cake-dashboard.css?v=<?php echo htmlspecialchars($erp_cake_dashboard_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $erp_ops_docs_pages = ['job_card.php', 'quotations.php', 'invoices.php'];
    if (in_array($current_page, $erp_ops_docs_pages, true)) {
        $erp_ops_docs_css = __DIR__ . '/../css/operations-docs-layout.css';
        $erp_ops_docs_css_v = is_file($erp_ops_docs_css) ? (string) filemtime($erp_ops_docs_css) : '1';
        echo '<link rel="stylesheet" href="css/operations-docs-layout.css?v=' . htmlspecialchars($erp_ops_docs_css_v, ENT_QUOTES, 'UTF-8') . '">' . "\n    ";
    }
    ?>
    <?php
    $erp_site_theme_css = __DIR__ . '/../../assets/css/site-theme.css';
    $erp_site_theme_css_v = is_file($erp_site_theme_css) ? (string) filemtime($erp_site_theme_css) : '1';
    $erp_site_theme_ext_css = __DIR__ . '/../../assets/css/erp-site-theme.css';
    $erp_site_theme_ext_css_v = is_file($erp_site_theme_ext_css) ? (string) filemtime($erp_site_theme_ext_css) : '1';
    ?>
    <link rel="stylesheet" href="../assets/css/site-theme.css?v=<?php echo htmlspecialchars($erp_site_theme_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../assets/css/erp-site-theme.css?v=<?php echo htmlspecialchars($erp_site_theme_ext_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        html { color-scheme: light; }
        html.site-theme-dark { color-scheme: dark; }
    </style>
<style>
    .erp-page-header .erp-breadcrumb { display: none !important; }
    /* Shared quotation-style action buttons (system-wide) */
    .aq-s1-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        border: 1px solid transparent;
        background: #fff;
        line-height: 1.2;
        white-space: nowrap;
        text-decoration: none;
    }
    .aq-s1-btn--gray { border-color: #cbd5e1; color: #334155; }
    .aq-s1-btn--gray:hover { border-color: #94a3b8; background: #fafafa; }
    .aq-s1-btn--blue { border-color: #93c5fd; color: #1d4ed8; }
    .aq-s1-btn--blue:hover { border-color: #60a5fa; background: #eff6ff; }
    .aq-s1-btn--orange-outline { border-color: #fdba74; color: #c2410c; }
    .aq-s1-btn--orange-outline:hover { border-color: #fb923c; background: #fff7ed; }
    .aq-s1-btn--green-outline { border-color: #bbf7d0; color: #16a34a; }
    .aq-s1-btn--green-outline:disabled {
        border-color: #e2e8f0;
        color: #94a3b8;
        background: #fafafa;
        opacity: 0.55;
        cursor: not-allowed;
    }
    .aq-s1-btn--save {
        background: #f97316;
        color: #fff;
        border: none;
    }
    .aq-s1-btn--save:hover { background: #ea580c; }
</style>
</head>
<body class="erp-app cake-ui">
    <div class="erp-app-shell">
<?php
$erp_shell_portal = 'admin';
$erp_shell_home_href = 'dashboard.php';
$erp_shell_logo_file = $erp_company_logo_file ?? null;
$erp_shell_logo_web = (!empty($erp_company_logo_file) && is_file($erp_company_logo_file))
    ? '../assets/images/companylogo2.png?v=' . (string) filemtime($erp_company_logo_file)
    : '';
$erp_shell_workspace_name = 'SV Auto ERP System';
$erp_shell_workspace_badge = 'ERP';
$erp_shell_profile_href = $erp_settings_href;
$erp_shell_support_href = $erp_support_href;
$erp_shell_breadcrumb_trail = ($current_page === 'dashboard.php') ? null : ($erp_breadcrumb_trail ?? null);
$erp_shell_page_title = (string) $page_title;
$erp_shell_notif_fn = 'erpToggleNotifications';
$erp_shell_can_switch_portal = in_array(strtolower((string) $role_name), ['admin', 'manager'], true);
$erp_shell_avatar_src = $erp_user_avatar_src;
$erp_shell_avatar_initial = $erp_user_avatar_initial;
$erp_shell_admin_href = 'dashboard.php';
$erp_shell_manager_href = '../Manager/manager_dashboard.php';
include __DIR__ . '/erp_topbar.inc.php';
?>
    <div class="erp-layout">
        <!-- Sidebar -->
        <aside class="erp-sidebar" id="sidebar">
            <nav class="erp-sidebar-nav" aria-label="Main navigation">
                <div class="erp-nav-section erp-nav-section--menu">
                    <div class="erp-nav-section-label">Menu</div>
                    <?php foreach ($erp_nav_main as $item):
                        if (($item['type'] ?? '') === 'link'):
                            $isActive = $erp_page_in($item['pages'] ?? []);
                    ?>
                    <a href="<?php echo htmlspecialchars((string) $item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-link<?php echo $isActive ? ' is-active' : ''; ?>">
                        <i class="fas <?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <span><?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <?php
                        else:
                            $groupPages = $item['pages'] ?? [];
                            $groupOpen = $erp_page_in($groupPages);
                            $groupActive = $groupOpen;
                    ?>
                    <div class="erp-nav-group<?php echo $groupOpen ? ' is-open' : ''; ?>" data-nav-group="<?php echo htmlspecialchars(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string) $item['label']))) ?: 'group', ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="erp-nav-parent<?php echo $groupActive ? ' is-active' : ''; ?>" aria-expanded="<?php echo $groupOpen ? 'true' : 'false'; ?>">
                            <i class="fas <?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <span><?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <i class="fas fa-chevron-right erp-nav-chevron" aria-hidden="true"></i>
                        </button>
                        <ul class="erp-nav-children">
                            <?php foreach ($item['children'] as $child):
                                $childActive = $erp_page_in($child['pages'] ?? []);
                            ?>
                            <li>
                                <a href="<?php echo htmlspecialchars((string) $child['href'], ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-child<?php echo $childActive ? ' is-current' : ''; ?>">
                                    <?php echo htmlspecialchars((string) $child['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </nav>

            <div class="erp-sidebar-footer">
                <div class="erp-nav-section erp-nav-section--general">
                    <div class="erp-nav-section-label erp-sidebar-foot-label">General</div>
                    <a href="settings.php" class="erp-nav-link erp-sidebar-foot-link<?php echo $current_page === 'settings.php' ? ' is-active' : ''; ?>">
                        <i class="fas fa-cog" aria-hidden="true"></i>
                        <span>Settings</span>
                    </a>
                    <a href="<?php echo htmlspecialchars($erp_support_href, ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-link erp-sidebar-foot-link">
                        <i class="fas fa-circle-question" aria-hidden="true"></i>
                        <span>Help</span>
                    </a>
                    <a href="logout.php"
                       class="erp-nav-link erp-sidebar-foot-link erp-sidebar-foot-link--logout"
                       onclick="return showLogoutConfirm();"
                       title="Log out">
                        <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                        <span>Logout</span>
                    </a>
                </div>

                <div class="erp-sidebar-collapse-wrap">
                    <button type="button" class="erp-sidebar-collapse-btn" id="sidebarToggle" title="Collapse sidebar" aria-label="Collapse sidebar">
                        <i class="fas fa-angles-left" aria-hidden="true"></i>
                        <span>Collapse</span>
                    </button>
                </div>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="erp-main">
            <script>
            function erpToggleNotifications() {
                toggleNotifications();
            }
            function toggleNotifications() {
                const dropdown = document.getElementById('notificationDropdown');
                if (!dropdown) return;
                const isHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
                dropdown.style.display = isHidden ? 'block' : 'none';
            }

            function markNotificationRead(id, evt) {
                if (evt && evt.preventDefault) {
                    evt.preventDefault();
                }
                var href = evt && evt.currentTarget ? evt.currentTarget.href : '';
                fetch('Utils/mark_notification_read.php?id=' + id, { method: 'POST' })
                    .catch(err => console.error('Error marking notification read:', err));
                if (href) {
                    window.location.href = href;
                }
                return false;
            }
            
            // Close dropdowns when clicking outside
            document.addEventListener('click', function(e) {
                const notifWrap = document.querySelector('.rd-dash-notif-wrap');
                const notifDropdown = document.getElementById('notificationDropdown');
                if (notifDropdown && notifWrap && !notifWrap.contains(e.target)) {
                    notifDropdown.style.display = 'none';
                }
            });
            </script>
            
            <!-- Content Area -->
            <div class="erp-content">
