<?php
/**
 * Manager portal sidebar (ERP layout). Included from includes/header_manager.php only.
 */
declare(strict_types=1);

if (!isset($mgr_nav_main) || !is_array($mgr_nav_main)) {
    $mgr_nav_main = [];
}
if (!isset($mgr_page_in) || !is_callable($mgr_page_in)) {
    $mgr_page_in = static fn(array $pages): bool => false;
}
if (!isset($mgr_support_href)) {
    $mgr_support_href = '#';
}
if (!isset($mgr_logo_file)) {
    $mgr_logo_file = __DIR__ . '/../assets/images/companylogo2.png';
}
if (!isset($mgr_logo_web)) {
    $mgr_logo_web = '../assets/images/companylogo2.png';
}
if (!isset($current_page)) {
    $current_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
}
?>
        <aside class="erp-sidebar" id="sidebar">
            <nav class="erp-sidebar-nav" aria-label="Main navigation">
                <div class="erp-nav-section">
                    <div class="erp-nav-section-label">Main</div>
                    <?php foreach ($mgr_nav_main as $item):
                        if (($item['type'] ?? '') === 'link'):
                            $isActive = $mgr_page_in($item['pages'] ?? []);
                    ?>
                    <a href="<?php echo htmlspecialchars(mgr_nav_href((string) $item['href']), ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-link<?php echo $isActive ? ' is-active' : ''; ?>">
                        <i class="fas <?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <span><?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <?php
                        else:
                            $groupPages = $item['pages'] ?? [];
                            $groupOpen = $mgr_page_in($groupPages);
                            $groupActive = $groupOpen;
                    ?>
                    <div class="erp-nav-group<?php echo $groupOpen ? ' is-open' : ''; ?>">
                        <button type="button" class="erp-nav-parent<?php echo $groupActive ? ' is-active' : ''; ?>" aria-expanded="<?php echo $groupOpen ? 'true' : 'false'; ?>">
                            <i class="fas <?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <span><?php echo htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <i class="fas fa-chevron-right erp-nav-chevron" aria-hidden="true"></i>
                        </button>
                        <ul class="erp-nav-children">
                            <?php foreach ($item['children'] as $child):
                                $childActive = $mgr_page_in($child['pages'] ?? []);
                            ?>
                            <li>
                                <a href="<?php echo htmlspecialchars(mgr_nav_href((string) $child['href']), ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-child<?php echo $childActive ? ' is-current' : ''; ?>">
                                    <?php echo htmlspecialchars((string) $child['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; endforeach; ?>
                </div>

                <div class="erp-nav-section erp-nav-section--others">
                    <div class="erp-nav-section-label">Others</div>
                    <a href="<?php echo htmlspecialchars($mgr_support_href, ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-link">
                        <i class="fas fa-headset"></i>
                        <span>Support</span>
                    </a>
                </div>
            </nav>

            <div class="erp-sidebar-footer">
                <a href="<?php echo htmlspecialchars(mgr_href('../Admin/settings.php#my-profile'), ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-link erp-sidebar-foot-link">
                    <i class="fas fa-cog" aria-hidden="true"></i>
                    <span>Settings</span>
                </a>
                <a href="<?php echo htmlspecialchars(mgr_href('../Admin/Auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="erp-nav-link erp-sidebar-foot-link erp-sidebar-foot-link--logout" id="mgrLogoutLink" onclick="return showLogoutConfirm(event);">
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i>
                    <span>Log out</span>
                </a>
                <div class="erp-sidebar-collapse-wrap">
                    <button type="button" class="erp-sidebar-collapse-btn" id="sidebarToggle" title="Collapse sidebar" aria-label="Collapse sidebar">
                        <i class="fas fa-angles-left" aria-hidden="true"></i>
                        <span>Collapse</span>
                    </button>
                </div>
            </div>
        </aside>
