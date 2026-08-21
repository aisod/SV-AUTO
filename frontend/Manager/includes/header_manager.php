<?php
/**
 * Manager portal layout header — separate from Admin (base URL is /Manager/).
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../backend/config/user_drafts.php';
require_once __DIR__ . '/mgr_paths.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . mgr_href('../login.php'));
    exit;
}

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
if (!in_array($role, ['admin', 'manager'], true)) {
    header('Location: ' . mgr_href('../login.php'));
    exit;
}

// Pure manager accounts must not use Admin pages (auth.php also redirects if they land there).
if ($role === 'manager' && stripos(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/Admin/') !== false) {
    header('Location: ' . mgr_href('manager_dashboard.php'));
    exit;
}

$paths = mgr_portal_paths();
$mgr_base_href = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $paths['base'];

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$username = (string) ($_SESSION['username'] ?? 'User');
$role_name = (string) ($_SESSION['role_name'] ?? 'User');
$business = getBusiness();

erp_ensure_user_avatar_column();
$mgr_user_avatar_web = erp_get_user_avatar_path($user_id);
$mgr_user_avatar_src = $mgr_user_avatar_web ? mgr_app_url($mgr_user_avatar_web) : null;
$mgr_user_avatar_initial = erp_user_avatar_initial($username);
$mgr_profile_href = mgr_href('../Admin/settings.php#my-profile');
$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$page_title = isset($page_title) && trim((string) $page_title) !== '' ? trim((string) $page_title) : 'Manager';

$mgr_page_in = static function (array $pages) use ($current_page): bool {
    return in_array($current_page, $pages, true);
};

/** Manager-only menu — add/remove items here (paths stay under /Manager/). */
$mgr_nav_main = [
    [
        'type' => 'link',
        'label' => 'Dashboard',
        'icon' => 'fa-gauge-high',
        'href' => 'manager_dashboard.php',
        'pages' => ['manager_dashboard.php'],
    ],
    [
        'type' => 'group',
        'label' => 'Documents',
        'icon' => 'fa-file-lines',
        'pages' => [
            'manager_job_cards.php',
            'manager_quotations.php',
            'manager_invoices.php',
            'job_card.php',
            'mgr_job_card_preview.php',
            'view_quotation.php',
            'mgr_quotation_preview.php',
            'view_invoice.php',
            'mgr_invoice_preview.php',
        ],
        'children' => [
            [
                'label' => 'Job Cards',
                'href' => 'manager_job_cards.php',
                'pages' => ['manager_job_cards.php', 'job_card.php', 'mgr_job_card_preview.php'],
                'breadcrumb_pages' => ['manager_job_cards.php', 'job_card.php', 'mgr_job_card_preview.php'],
            ],
            [
                'label' => 'Quotations',
                'href' => 'manager_quotations.php',
                'pages' => ['manager_quotations.php', 'view_quotation.php', 'mgr_quotation_preview.php'],
                'breadcrumb_pages' => ['manager_quotations.php', 'view_quotation.php', 'mgr_quotation_preview.php'],
            ],
            [
                'label' => 'Invoices',
                'href' => 'manager_invoices.php',
                'pages' => ['manager_invoices.php', 'view_invoice.php', 'mgr_invoice_preview.php'],
                'breadcrumb_pages' => ['manager_invoices.php', 'view_invoice.php', 'mgr_invoice_preview.php'],
            ],
        ],
    ],
];

require_once __DIR__ . '/mgr_breadcrumb.inc.php';

$mgr_current_key = basename((string) ($_SERVER['PHP_SELF'] ?? ''), '.php');
$mgr_breadcrumb_trail = mgr_resolve_breadcrumb_trail($mgr_nav_main, $current_page, $mgr_current_key);

$mgr_logo_file = __DIR__ . '/../../assets/images/companylogo2.png';
$mgr_logo_web = '../assets/images/companylogo2.png';

$mgr_support_email = trim((string) ($business['email'] ?? ''));
$mgr_support_href = $mgr_support_email !== ''
    ? 'mailto:' . rawurlencode($mgr_support_email) . '?subject=' . rawurlencode('Manager portal support')
    : '#';

$erp_layout_css = __DIR__ . '/../../Admin/css/erp-layout.css';
$erp_layout_css_v = is_file($erp_layout_css) ? (string) filemtime($erp_layout_css) : '1';
$mgr_header_tools_css = __DIR__ . '/../../Admin/css/admin-header-tools.css';
$mgr_header_tools_css_v = is_file($mgr_header_tools_css) ? (string) filemtime($mgr_header_tools_css) : '1';

$notification_count = 0;
$recent_notifications = [];
if ($user_id > 0) {
    try {
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

        $notifStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $notifStmt->execute([$user_id]);
        $notification_count = (int) $notifStmt->fetchColumn();

        $recentStmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10');
        $recentStmt->execute([$user_id]);
        $recent_notifications = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Manager notifications: ' . $e->getMessage());
        $notification_count = 0;
        $recent_notifications = [];
    }
}

$mgr_notif_mark_url = mgr_href('Utils/mark_notification_read.php');
$mgr_search_url = mgr_href('Utils/search.php');
$mgr_header_tools_js_v = is_file(__DIR__ . '/../../Admin/js/admin-header-tools.js')
    ? (string) filemtime(__DIR__ . '/../../Admin/js/admin-header-tools.js')
    : '1';
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
    <base href="<?php echo htmlspecialchars($mgr_base_href, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars((string) ($business['name'] ?? 'SV Auto'), ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Roboto:wght@400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../Admin/css/erp-layout.css?v=<?php echo htmlspecialchars($erp_layout_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../Admin/css/admin-header-tools.css?v=<?php echo htmlspecialchars($mgr_header_tools_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $mgr_cake_layout_css = __DIR__ . '/../../Admin/css/cake-layout.css';
    $mgr_cake_layout_css_v = is_file($mgr_cake_layout_css) ? (string) filemtime($mgr_cake_layout_css) : '1';
    $mgr_cake_dashboard_css = __DIR__ . '/../../Admin/css/cake-dashboard.css';
    $mgr_cake_dashboard_css_v = is_file($mgr_cake_dashboard_css) ? (string) filemtime($mgr_cake_dashboard_css) : '1';
    ?>
    <link rel="stylesheet" href="../Admin/css/cake-layout.css?v=<?php echo htmlspecialchars($mgr_cake_layout_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../Admin/css/cake-dashboard.css?v=<?php echo htmlspecialchars($mgr_cake_dashboard_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <?php
    $mgr_ops_docs_pages = ['manager_job_cards.php', 'manager_quotations.php', 'manager_invoices.php'];
    if (in_array($current_page, $mgr_ops_docs_pages, true)) {
        $mgr_ops_docs_css = __DIR__ . '/../../Admin/css/operations-docs-layout.css';
        $mgr_ops_docs_css_v = is_file($mgr_ops_docs_css) ? (string) filemtime($mgr_ops_docs_css) : '1';
        echo '<link rel="stylesheet" href="../Admin/css/operations-docs-layout.css?v=' . htmlspecialchars($mgr_ops_docs_css_v, ENT_QUOTES, 'UTF-8') . '">' . "\n    ";
    }
    $mgr_site_theme_css = __DIR__ . '/../../assets/css/site-theme.css';
    $mgr_site_theme_css_v = is_file($mgr_site_theme_css) ? (string) filemtime($mgr_site_theme_css) : '1';
    $mgr_site_theme_ext_css = __DIR__ . '/../../assets/css/erp-site-theme.css';
    $mgr_site_theme_ext_css_v = is_file($mgr_site_theme_ext_css) ? (string) filemtime($mgr_site_theme_ext_css) : '1';
    $mgr_site_theme_js = __DIR__ . '/../../assets/js/site-theme.js';
    $mgr_site_theme_js_v = is_file($mgr_site_theme_js) ? (string) filemtime($mgr_site_theme_js) : '1';
    ?>
    <link rel="stylesheet" href="../assets/css/site-theme.css?v=<?php echo htmlspecialchars($mgr_site_theme_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../assets/css/erp-site-theme.css?v=<?php echo htmlspecialchars($mgr_site_theme_ext_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <style>
        html { color-scheme: light; }
        html.site-theme-dark { color-scheme: dark; }
        .erp-page-header .erp-breadcrumb { display: none !important; }
        .erp-mobile-menu-btn { display: none !important; }
    </style>
</head>
<body class="erp-app cake-ui">
    <div class="erp-app-shell">
<?php
foreach ($recent_notifications as &$mgr_notif_row) {
    $mgr_notif_row['link'] = mgr_notif_link((string) ($mgr_notif_row['link'] ?? '#'));
}
unset($mgr_notif_row);

$erp_shell_portal = 'manager';
$erp_shell_home_href = 'manager_dashboard.php';
$erp_shell_logo_file = $mgr_logo_file;
$erp_shell_logo_web = mgr_href($mgr_logo_web . (is_file($mgr_logo_file) ? '?v=' . (string) filemtime($mgr_logo_file) : ''));
$erp_shell_workspace_name = (string) ($business['name'] ?? 'SV Auto Truck Repair');
$erp_shell_workspace_badge = 'Manager';
$erp_shell_profile_href = $mgr_profile_href;
$erp_shell_support_href = $mgr_support_href;
$erp_shell_breadcrumb_trail = ($current_page === 'manager_dashboard.php') ? null : $mgr_breadcrumb_trail;
$erp_shell_page_title = (string) $page_title;
$erp_shell_notif_fn = 'mgrToggleNotifications';
$erp_shell_can_switch_portal = in_array($role, ['admin', 'manager'], true);
$erp_shell_avatar_src = $mgr_user_avatar_src;
$erp_shell_avatar_initial = $mgr_user_avatar_initial;
$erp_shell_admin_href = mgr_href('../Admin/dashboard.php');
$erp_shell_manager_href = mgr_href('manager_dashboard.php');
include __DIR__ . '/../../Admin/includes/erp_topbar.inc.php';
?>
    <div class="erp-layout">
<?php include __DIR__ . '/../sidebar_manager.php'; ?>
        <main class="erp-main">
            <script>
            function mgrToggleNotifications() {
                var dropdown = document.getElementById('notificationDropdown');
                if (!dropdown) return;
                var isHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
                dropdown.style.display = isHidden ? 'block' : 'none';
            }
            function mgrMarkNotificationRead(id, evt) {
                if (evt && evt.preventDefault) evt.preventDefault();
                var href = evt && evt.currentTarget ? evt.currentTarget.href : '';
                fetch('<?php echo htmlspecialchars($mgr_notif_mark_url, ENT_QUOTES, 'UTF-8'); ?>?id=' + id, { method: 'POST' })
                    .catch(function (err) { console.error('Error marking notification read:', err); });
                if (href) window.location.href = href;
                return false;
            }
            function markNotificationRead(id, evt) {
                return mgrMarkNotificationRead(id, evt);
            }
            document.addEventListener('click', function (e) {
                var notifWrap = document.querySelector('.rd-dash-notif-wrap');
                var notifDropdown = document.getElementById('notificationDropdown');
                if (notifDropdown && notifWrap && !notifWrap.contains(e.target)) {
                    notifDropdown.style.display = 'none';
                }
            });
            </script>
            <div class="erp-content">
