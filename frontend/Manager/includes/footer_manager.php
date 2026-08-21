<?php
declare(strict_types=1);
$business = $business ?? getBusiness();
?>
            </div>
            <footer class="erp-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars((string) ($business['name'] ?? 'SV Auto Services'), ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</p>
                <p style="margin-top: 4px;">Manager Portal</p>
            </footer>
        </main>
    </div>
    </div><!-- End erp-app-shell -->
    <?php
    $mgr_readability_css = __DIR__ . '/../css/mgr-readability.css';
    $mgr_readability_css_v = is_file($mgr_readability_css) ? (string) filemtime($mgr_readability_css) : '1';
    ?>
    <link rel="stylesheet" href="css/mgr-readability.css?v=<?php echo htmlspecialchars($mgr_readability_css_v, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="erp-modal-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="erp-modal-overlay erp-logout-overlay" id="mgrLogoutModal" aria-hidden="true" role="presentation">
        <div class="erp-modal erp-logout-modal" role="dialog" aria-modal="true" aria-labelledby="mgrLogoutModalTitle">
            <div class="erp-logout-modal-inner">
                <div class="erp-logout-modal-icon" aria-hidden="true">
                    <i class="fas fa-right-from-bracket"></i>
                </div>
                <h3 class="erp-logout-modal-title" id="mgrLogoutModalTitle">Sign out</h3>
                <p class="erp-logout-modal-text">
                    Are you sure you want to log out of
                    <strong><?php echo htmlspecialchars((string) ($business['name'] ?? 'SV Auto Truck Repair'), ENT_QUOTES, 'UTF-8'); ?></strong>?
                </p>
                <p class="erp-logout-modal-hint">You will need to sign in again to access the manager portal.</p>
            </div>
            <div class="erp-logout-modal-actions">
                <button type="button" class="erp-logout-btn erp-logout-btn--cancel" id="mgrLogoutCancel">Cancel</button>
                <button type="button" class="erp-logout-btn erp-logout-btn--confirm" id="mgrLogoutConfirm">
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sign out
                </button>
            </div>
        </div>
    </div>
    <script>
        window.MGR_NOTIF_MARK_URL = <?php echo json_encode($mgr_notif_mark_url ?? 'Utils/mark_notification_read.php'); ?>;
        window.MGR_NOTIF_FEED_URL = <?php echo json_encode(mgr_href('Utils/notifications_feed.php')); ?>;
        window.MGR_SEARCH_URL = <?php echo json_encode($mgr_search_url ?? 'Utils/search.php'); ?>;
    </script>
    <script src="../assets/js/site-theme.js?v=<?php echo htmlspecialchars($mgr_site_theme_js_v ?? '1', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="../Admin/js/admin-header-tools.js?v=<?php echo htmlspecialchars($mgr_header_tools_js_v ?? '1', ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
        function toggleNotifications() { mgrToggleNotifications(); }
        function erpToggleNotifications() { mgrToggleNotifications(); }
        function markNotificationRead(id) { mgrMarkNotificationRead(id); }

        function mgrToggleNotifications() {
            if (typeof window.erpRefreshNotifications === 'function') {
                window.erpRefreshNotifications(true);
            }
            var dropdown = document.getElementById('notificationDropdown');
            var btn = document.querySelector('.rd-dash-notif-wrap .rd-dash-notif-btn');
            if (!dropdown) return;
            var isHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
            dropdown.style.display = isHidden ? 'block' : 'none';
            if (!isHidden || !btn) return;
            var rect = btn.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = Math.round(rect.bottom + 8) + 'px';
            dropdown.style.right = Math.max(12, Math.round(window.innerWidth - rect.right)) + 'px';
            dropdown.style.left = 'auto';
            dropdown.style.zIndex = '10050';
        }

        function mgrMarkNotificationRead(id, evt) {
            if (!id) return;
            if (evt && evt.preventDefault) {
                evt.preventDefault();
            }
            var href = evt && evt.currentTarget ? evt.currentTarget.href : '';
            fetch(window.MGR_NOTIF_MARK_URL + '?id=' + encodeURIComponent(String(id)), { method: 'POST', credentials: 'same-origin' })
                .catch(function (err) { console.error('Error marking notification read:', err); });
            if (href) {
                window.location.href = href;
            }
        }

        document.addEventListener('click', function (e) {
            var notifWrap = document.querySelector('.rd-dash-notif-wrap');
            var notifDropdown = document.getElementById('notificationDropdown');
            if (notifDropdown && notifWrap && !notifWrap.contains(e.target)) {
                notifDropdown.style.display = 'none';
            }
        });

        function toggleSearch() {
            if (typeof window.erpToggleSearch === 'function') {
                window.erpToggleSearch();
            }
        }
    </script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function setSidebarCollapseControl(collapsed) {
            if (!sidebarToggle) return;
            const icon = sidebarToggle.querySelector('i');
            const label = sidebarToggle.querySelector('span');
            if (!icon) return;
            if (collapsed) {
                icon.classList.replace('fa-angles-left', 'fa-angles-right');
                sidebarToggle.title = 'Expand sidebar';
                if (label) label.textContent = 'Expand';
            } else {
                icon.classList.replace('fa-angles-right', 'fa-angles-left');
                sidebarToggle.title = 'Collapse sidebar';
                if (label) label.textContent = 'Collapse';
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                document.body.classList.toggle('erp-sidebar-collapsed');
                const collapsed = document.body.classList.contains('erp-sidebar-collapsed');
                setSidebarCollapseControl(collapsed);
                localStorage.setItem('mgrSidebarCollapsed', collapsed ? 'true' : 'false');
            });
            if (localStorage.getItem('mgrSidebarCollapsed') === 'true') {
                document.body.classList.add('erp-sidebar-collapsed');
                setSidebarCollapseControl(true);
            }
        }

        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }

        document.querySelectorAll('.erp-nav-parent').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const group = btn.closest('.erp-nav-group');
                if (!group) return;
                const open = group.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });

        document.querySelectorAll('.erp-breadcrumb-item--dropdown').forEach(function (item) {
            item.addEventListener('toggle', function () {
                if (!item.open) return;
                document.querySelectorAll('.erp-breadcrumb-item--dropdown[open]').forEach(function (other) {
                    if (other !== item) other.open = false;
                });
            });
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest('.erp-breadcrumb-item--dropdown')) return;
            document.querySelectorAll('.erp-breadcrumb-item--dropdown[open]').forEach(function (item) {
                item.open = false;
            });
        });

        var MGR_LOGOUT_URL = <?php echo json_encode(mgr_href('../Admin/Auth/logout.php')); ?>;

        function closeMgrLogoutModal() {
            var overlay = document.getElementById('mgrLogoutModal');
            if (!overlay) return;
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('erp-logout-modal-open');
        }

        function showLogoutConfirm(evt) {
            if (evt && evt.preventDefault) {
                evt.preventDefault();
            }
            var overlay = document.getElementById('mgrLogoutModal');
            if (!overlay) {
                return false;
            }
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('erp-logout-modal-open');
            var cancelBtn = document.getElementById('mgrLogoutCancel');
            if (cancelBtn) {
                setTimeout(function () { cancelBtn.focus(); }, 80);
            }
            return false;
        }

        (function initMgrLogoutModal() {
            var overlay = document.getElementById('mgrLogoutModal');
            if (!overlay) return;
            var cancelBtn = document.getElementById('mgrLogoutCancel');
            var confirmBtn = document.getElementById('mgrLogoutConfirm');

            if (cancelBtn) cancelBtn.addEventListener('click', closeMgrLogoutModal);
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeMgrLogoutModal();
            });
            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    window.location.href = MGR_LOGOUT_URL;
                });
            }
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay.classList.contains('show')) {
                    closeMgrLogoutModal();
                }
            });
        })();
    </script>
<?php
$erp_student_table_js = __DIR__ . '/../../Admin/js/student-table.js';
$erp_student_table_js_v = is_file($erp_student_table_js) ? (string) filemtime($erp_student_table_js) : '1';
?>
    <script src="../Admin/js/student-table.js?v=<?php echo htmlspecialchars($erp_student_table_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
