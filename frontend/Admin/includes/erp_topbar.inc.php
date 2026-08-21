<?php
/**
 * erp_topbar.inc.php — Full-width application topbar
 * Shared between Admin and Manager portals.
 *
 * Expected variables (set by including file before include):
 *   $erp_shell_portal          string  'admin' | 'manager'
 *   $erp_shell_home_href       string  Dashboard href
 *   $erp_shell_logo_file       string  Absolute path to logo (for is_file check)
 *   $erp_shell_logo_web        string  Web-accessible logo path
 *   $erp_shell_workspace_name  string  Company / workspace name
 *   $erp_shell_workspace_badge string  'ERP' | 'Manager'
 *   $erp_shell_profile_href    string  User profile / settings href
 *   $erp_shell_breadcrumb_trail array|null  Breadcrumb items
 *   $erp_shell_page_title      string  Current page title
 *   $erp_shell_notif_fn        string  JS function name to toggle notif dropdown
 *   $erp_shell_can_switch_portal bool
 *   $erp_shell_admin_href      string
 *   $erp_shell_manager_href    string
 *   $erp_shell_avatar_src      string|null
 *   $erp_shell_avatar_initial  string
 *   $notification_count        int
 *   $recent_notifications      array
 *   $username                  string
 *   $role_name                 string
 */
declare(strict_types=1);

$_tb_portal       = (string) ($erp_shell_portal ?? 'admin');
$_tb_home         = (string) ($erp_shell_home_href ?? 'dashboard.php');
$_tb_logo_web     = (string) ($erp_shell_logo_web ?? '');
$_tb_logo_file    = (string) ($erp_shell_logo_file ?? '');
$_tb_ws_name      = (string) ($erp_shell_workspace_name ?? 'SV Auto');
$_tb_ws_badge     = (string) ($erp_shell_workspace_badge ?? 'ERP');
$_tb_profile      = (string) ($erp_shell_profile_href ?? '#');
$_tb_page_title   = (string) ($erp_shell_page_title ?? 'Dashboard');
$_tb_notif_fn     = (string) ($erp_shell_notif_fn ?? 'erpToggleNotifications');
$_tb_avatar_src   = (string) ($erp_shell_avatar_src ?? '');
$_tb_avatar_init  = (string) ($erp_shell_avatar_initial ?? 'U');
$_tb_username     = (string) ($username ?? 'User');
$_tb_role         = (string) ($role_name ?? '');
$_tb_notif_count  = (int) ($notification_count ?? 0);
$_tb_notifs       = is_array($recent_notifications ?? null) ? $recent_notifications : [];
$_tb_crumbs       = is_array($erp_shell_breadcrumb_trail ?? null) ? $erp_shell_breadcrumb_trail : null;
?>
<header class="erp-app-topbar" id="erpAppTopbar" role="banner">
    <!-- Mobile hamburger (hidden on desktop) -->
    <button type="button" class="erp-topbar-hamburger" id="mobileMenuToggle" aria-label="Toggle navigation menu" title="Menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <!-- Brand / workspace -->
    <div class="erp-topbar-brand">
        <a href="<?php echo htmlspecialchars($_tb_home, ENT_QUOTES, 'UTF-8'); ?>" class="erp-topbar-brand-link" title="Go to dashboard">
            <?php if ($_tb_logo_web !== '' && $_tb_logo_file !== '' && is_file($_tb_logo_file)): ?>
            <div class="erp-topbar-logo-wrap">
                <img src="<?php echo htmlspecialchars($_tb_logo_web, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($_tb_ws_name, ENT_QUOTES, 'UTF-8'); ?>" class="erp-topbar-logo-img">
            </div>
            <?php else: ?>
            <div class="erp-topbar-logo-mark" aria-hidden="true">SV</div>
            <?php endif; ?>
        </a>
        <div class="erp-topbar-workspace" aria-label="Workspace">
            <span class="erp-topbar-ws-name"><?php echo htmlspecialchars($_tb_ws_name, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <!-- Breadcrumb / page label -->
    <?php if (!empty($_tb_crumbs) && count($_tb_crumbs) > 1): ?>
    <nav class="erp-topbar-bc" id="erpTopbarCenter" aria-label="Breadcrumb">
        <?php foreach ($_tb_crumbs as $_i => $_crumb):
            $_isLast = ($_i === count($_tb_crumbs) - 1);
            $_label  = htmlspecialchars((string) ($_crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $_href   = !empty($_crumb['href']) ? htmlspecialchars((string) $_crumb['href'], ENT_QUOTES, 'UTF-8') : '';
        ?>
        <?php if ($_i > 0): ?><span class="erp-topbar-bc-sep" aria-hidden="true">/</span><?php endif; ?>
        <?php if (!$_isLast && $_href !== ''): ?>
        <a href="<?php echo $_href; ?>" class="erp-topbar-bc-item"><?php echo $_label; ?></a>
        <?php else: ?>
        <span class="erp-topbar-bc-item erp-topbar-bc-item--current" aria-current="page"><?php echo $_label; ?></span>
        <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php else: ?>
    <div class="erp-topbar-bc" id="erpTopbarCenter" aria-label="Current page">
        <span class="erp-topbar-bc-item erp-topbar-bc-item--current"><?php echo htmlspecialchars($_tb_page_title, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <?php endif; ?>

    <div class="erp-topbar-spacer" aria-hidden="true"></div>

    <!-- Right-side tools -->
    <div class="erp-topbar-tools">

        <!-- Search trigger -->
        <button type="button"
            class="rd-dash-search"
            data-erp-search-trigger
            aria-label="Search workspace (Ctrl+K)"
            title="Search (Ctrl+K)">
            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
            <span class="rd-dash-search-placeholder">Search jobs, quotes, invoices…</span>
            <span class="rd-dash-search-kbd" aria-hidden="true"><kbd>⌘</kbd><kbd>K</kbd></span>
        </button>

        <span class="rd-dash-header-vrule" aria-hidden="true"></span>

        <!-- Notifications -->
        <div class="rd-dash-notif-wrap">
            <button type="button"
                class="rd-dash-notif-btn<?php echo $_tb_notif_count > 0 ? ' has-unread' : ''; ?>"
                onclick="<?php echo htmlspecialchars($_tb_notif_fn, ENT_QUOTES, 'UTF-8'); ?>()"
                aria-label="Notifications<?php echo $_tb_notif_count > 0 ? ' (' . $_tb_notif_count . ' unread)' : ''; ?>"
                title="Notifications">
                <i class="fas fa-bell" aria-hidden="true"></i>
                <?php if ($_tb_notif_count > 0): ?>
                <span class="rd-dash-notif-dot" aria-hidden="true"></span>
                <?php endif; ?>
            </button>
            <div class="rd-dash-notif-dropdown" id="notificationDropdown" style="display:none;" role="dialog" aria-label="Notifications panel">
                <div class="rd-dash-notif-dropdown-hd">
                    <h4>Notifications</h4>
                    <?php if ($_tb_notif_count > 0): ?>
                    <span class="rd-dash-notif-dropdown-badge"><?php echo $_tb_notif_count; ?> new</span>
                    <?php endif; ?>
                </div>
                <div class="rd-dash-notif-dropdown-body">
                    <?php if (empty($_tb_notifs)): ?>
                    <div class="rd-dash-notif-empty">
                        <i class="fas fa-bell-slash" aria-hidden="true"></i>
                        <p>No notifications yet</p>
                    </div>
                    <?php else: foreach ($_tb_notifs as $_n):
                        $_nRead = !empty($_n['is_read']);
                        $_nId   = (int) ($_n['id'] ?? 0);
                    ?>
                    <a href="<?php echo htmlspecialchars((string) ($_n['link'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>"
                       class="rd-dash-notif-item<?php echo $_nRead ? ' is-read' : ''; ?>"
                       data-notif-id="<?php echo $_nId; ?>"
                       onclick="return erpNotifItemClick(<?php echo $_nId; ?>, event)">
                        <span class="rd-dash-notif-item-dot" aria-hidden="true"></span>
                        <span class="rd-dash-notif-item-text">
                            <span class="rd-dash-notif-item-title"><?php echo htmlspecialchars((string) ($_n['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($_n['message'])): ?>
                            <span class="rd-dash-notif-item-msg"><?php echo htmlspecialchars((string) $_n['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($_n['created_at'])): ?>
                            <span class="rd-dash-notif-item-time"><?php echo htmlspecialchars((string) $_n['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>

        <!-- Theme toggle -->
        <div class="rd-dash-theme-toggle" title="Color theme">
            <button type="button" class="rd-dash-theme-btn" data-sv-theme="light" aria-label="Light mode" title="Light mode">
                <i class="fas fa-sun" aria-hidden="true"></i>
            </button>
            <button type="button" class="rd-dash-theme-btn" data-sv-theme="dark" aria-label="Dark mode" title="Dark mode">
                <i class="fas fa-moon" aria-hidden="true"></i>
            </button>
        </div>

        <span class="rd-dash-header-vrule" aria-hidden="true"></span>

        <!-- User profile chip -->
        <a href="<?php echo htmlspecialchars($_tb_profile, ENT_QUOTES, 'UTF-8'); ?>"
           class="erp-user-chip erp-user-chip--pill"
           aria-label="Profile and settings"
           title="Profile &amp; settings">
            <span class="erp-user-avatar<?php echo $_tb_avatar_src !== '' ? ' erp-user-avatar--photo' : ''; ?>" aria-hidden="true">
                <?php if ($_tb_avatar_src !== ''): ?>
                <img src="<?php echo htmlspecialchars($_tb_avatar_src, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="">
                <?php else: ?>
                <span class="erp-user-avatar-letter"><?php echo htmlspecialchars($_tb_avatar_init, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </span>
            <span class="erp-user-info">
                <span class="erp-user-name"><?php echo htmlspecialchars($_tb_username, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="erp-user-role"><?php echo htmlspecialchars($_tb_role, ENT_QUOTES, 'UTF-8'); ?></span>
            </span>
        </a>
    </div>
</header>
