            </div><!-- End erp-content -->
            
            <!-- Footer -->
            <footer class="erp-footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($business['name'] ?? 'SV Auto Services'); ?>. All rights reserved.</p>
                <p style="margin-top: 4px;">Powered by SV Auto Management System</p>
            </footer>
        </main><!-- End erp-main -->
    </div><!-- End erp-layout -->
    </div><!-- End erp-app-shell -->
    
    <!-- Mobile Sidebar Overlay -->
    <div class="erp-modal-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

    <div class="erp-modal-overlay erp-logout-overlay" id="erpLogoutModal" aria-hidden="true" role="presentation">
        <div class="erp-modal erp-logout-modal" role="dialog" aria-modal="true" aria-labelledby="erpLogoutModalTitle">
            <div class="erp-logout-modal-inner">
                <div class="erp-logout-modal-icon" aria-hidden="true">
                    <i class="fas fa-right-from-bracket"></i>
                </div>
                <h3 class="erp-logout-modal-title" id="erpLogoutModalTitle">Sign out</h3>
                <p class="erp-logout-modal-text">
                    Are you sure you want to log out of
                    <strong><?php echo htmlspecialchars((string) ($business['name'] ?? 'SV Auto'), ENT_QUOTES, 'UTF-8'); ?></strong>?
                    Any unsaved changes will be lost.
                </p>
            </div>
            <div class="erp-logout-modal-actions">
                <button type="button" class="erp-logout-btn erp-logout-btn--cancel" id="erpLogoutCancel">Cancel</button>
                <a href="logout.php" class="erp-logout-btn erp-logout-btn--confirm" id="erpLogoutConfirm">
                    <i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sign out
                </a>
            </div>
        </div>
    </div>
    
    <!-- Common Scripts -->
    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function setSidebarCollapseControl(collapsed) {
            if (!sidebarToggle) return;
            const icon = sidebarToggle.querySelector('i');
            const label = sidebarToggle.querySelector('span');
            if (!icon) return;
            if (collapsed) {
                icon.classList.remove('fa-angles-left');
                icon.classList.add('fa-angles-right');
                sidebarToggle.title = 'Expand sidebar';
                sidebarToggle.setAttribute('aria-label', 'Expand sidebar');
                if (label) label.textContent = 'Expand';
            } else {
                icon.classList.remove('fa-angles-right');
                icon.classList.add('fa-angles-left');
                sidebarToggle.title = 'Collapse sidebar';
                sidebarToggle.setAttribute('aria-label', 'Collapse sidebar');
                if (label) label.textContent = 'Collapse';
            }
        }

        // Desktop sidebar collapse
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                erpClearCollapsedFlyouts();
                document.body.classList.toggle('erp-sidebar-collapsed');
                const collapsed = document.body.classList.contains('erp-sidebar-collapsed');
                setSidebarCollapseControl(collapsed);
                localStorage.setItem('sidebarCollapsed', collapsed ? 'true' : 'false');
            });
        }
        
        // Mobile sidebar toggle
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
            });
        }
        
        function closeSidebar() {
            sidebar.classList.remove('show');
            sidebarOverlay.classList.remove('show');
        }
        
        function erpNavItemLabel(el) {
            var span = el.querySelector('span');
            return span && span.textContent ? span.textContent.trim() : '';
        }

        document.querySelectorAll('.erp-nav-link, .erp-nav-parent, .erp-sidebar-foot-link').forEach(function (el) {
            var label = erpNavItemLabel(el);
            if (label) {
                el.setAttribute('title', label);
                if (el.classList.contains('erp-nav-parent')) {
                    el.setAttribute('aria-label', label);
                }
            }
        });

        function erpClearCollapsedFlyouts() {
            document.querySelectorAll('.erp-nav-group.is-flyout-open').forEach(function (g) {
                g.classList.remove('is-flyout-open');
            });
            document.querySelectorAll('.erp-nav-group.is-flyout-positioned').forEach(function (g) {
                g.classList.remove('is-flyout-positioned');
                var menu = g.querySelector('.erp-nav-children');
                if (menu) {
                    menu.style.top = '';
                    menu.style.left = '';
                }
            });
        }

        function erpPositionCollapsedFlyout(group) {
            if (!document.body.classList.contains('erp-sidebar-collapsed')) return;
            var menu = group.querySelector('.erp-nav-children');
            var trigger = group.querySelector('.erp-nav-parent');
            if (!menu || !trigger) return;

            if (!group.classList.contains('is-flyout-open')) {
                group.classList.remove('is-flyout-positioned');
                menu.style.top = '';
                menu.style.left = '';
                return;
            }

            var rect = trigger.getBoundingClientRect();
            var menuHeight = menu.offsetHeight || 0;
            var top = rect.top;
            var maxTop = window.innerHeight - menuHeight - 12;

            if (menuHeight > 0 && top > maxTop) {
                top = Math.max(12, maxTop);
            }

            menu.style.top = top + 'px';
            menu.style.left = (rect.right + 8) + 'px';
            group.classList.add('is-flyout-positioned');
        }

        function erpRepositionOpenFlyouts() {
            if (!document.body.classList.contains('erp-sidebar-collapsed')) return;
            document.querySelectorAll('.erp-nav-group.is-flyout-open').forEach(erpPositionCollapsedFlyout);
        }

        var ERP_NAV_OPEN_KEY = 'sv-erp-nav-open-groups-v1';

        function erpNavGroupId(group) {
            return group ? (group.getAttribute('data-nav-group') || '') : '';
        }

        function erpGetOpenNavGroups() {
            try {
                var raw = localStorage.getItem(ERP_NAV_OPEN_KEY);
                var parsed = raw ? JSON.parse(raw) : [];
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }

        function erpSaveOpenNavGroups(ids) {
            try {
                localStorage.setItem(ERP_NAV_OPEN_KEY, JSON.stringify(ids));
            } catch (e) {}
        }

        function erpSyncOpenNavGroupsToStorage() {
            if (document.body.classList.contains('erp-sidebar-collapsed')) return;
            var ids = [];
            document.querySelectorAll('.erp-nav-group.is-open[data-nav-group]').forEach(function (g) {
                var id = erpNavGroupId(g);
                if (id) ids.push(id);
            });
            erpSaveOpenNavGroups(ids);
        }

        function erpRestoreNavGroupsFromStorage() {
            if (document.body.classList.contains('erp-sidebar-collapsed')) return;
            var saved = erpGetOpenNavGroups();
            if (!saved.length) return;
            document.querySelectorAll('.erp-nav-group[data-nav-group]').forEach(function (group) {
                if (saved.indexOf(erpNavGroupId(group)) === -1) return;
                group.classList.add('is-open');
                var btn = group.querySelector('.erp-nav-parent');
                if (btn) btn.setAttribute('aria-expanded', 'true');
            });
        }

        document.querySelectorAll('.erp-nav-parent').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                var group = btn.closest('.erp-nav-group');
                if (!group) return;
                if (document.body.classList.contains('erp-sidebar-collapsed')) {
                    e.preventDefault();
                    e.stopPropagation();
                    var willOpen = !group.classList.contains('is-flyout-open');
                    document.querySelectorAll('.erp-nav-group.is-flyout-open').forEach(function (g) {
                        if (g !== group) {
                            g.classList.remove('is-flyout-open');
                            erpPositionCollapsedFlyout(g);
                        }
                    });
                    group.classList.toggle('is-flyout-open', willOpen);
                    btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    requestAnimationFrame(function () {
                        erpPositionCollapsedFlyout(group);
                    });
                    return;
                }
                var willOpen = !group.classList.contains('is-open');
                group.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                erpSyncOpenNavGroupsToStorage();
            });
        });

        erpRestoreNavGroupsFromStorage();
        erpSyncOpenNavGroupsToStorage();

        document.addEventListener('click', function (e) {
            if (!document.body.classList.contains('erp-sidebar-collapsed')) return;
            if (e.target.closest('.erp-nav-group')) return;
            erpClearCollapsedFlyouts();
        });

        window.addEventListener('resize', erpRepositionOpenFlyouts);
        window.addEventListener('scroll', erpRepositionOpenFlyouts, true);

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

        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 1024) {
            document.body.classList.add('erp-sidebar-collapsed');
            setSidebarCollapseControl(true);
        }
        
        // Notifications toggle
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown) {
                const isHidden = dropdown.style.display === 'none' || dropdown.style.display === '';
                dropdown.style.display = isHidden ? 'block' : 'none';
            }
        }
        
        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('notificationDropdown');
            const wrap = document.querySelector('.rd-dash-notif-wrap');
            if (dropdown && wrap && !wrap.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.erp-alert, .alert, .success-message, .error-message');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
        
        // Table sorting helper
        function sortTable(table, column, direction) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort(function(a, b) {
                const aVal = a.cells[column].textContent.trim();
                const bVal = b.cells[column].textContent.trim();
                
                // Try numeric sort
                const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // Text sort
                return direction === 'asc' 
                    ? aVal.localeCompare(bVal) 
                    : bVal.localeCompare(aVal);
            });
            
            rows.forEach(function(row) {
                tbody.appendChild(row);
            });
        }
        
        // Add click handlers to sortable table headers (skip student-style tables — student-table.js)
        document.addEventListener('DOMContentLoaded', function() {
            const sortableHeaders = document.querySelectorAll('.erp-table:not(.erp-student-table) th.sortable');
            sortableHeaders.forEach(function(header, index) {
                header.addEventListener('click', function() {
                    const table = this.closest('table');
                    const isAsc = this.classList.contains('sort-asc');
                    
                    // Reset all headers
                    sortableHeaders.forEach(function(h) {
                        h.classList.remove('sort-asc', 'sort-desc');
                    });
                    
                    // Toggle sort direction
                    if (isAsc) {
                        this.classList.add('sort-desc');
                        sortTable(table, index, 'desc');
                    } else {
                        this.classList.add('sort-asc');
                        sortTable(table, index, 'asc');
                    }
                });
            });
        });

        (function () {
            const meta = document.querySelector('meta[name="erp-resume-page"]');
            if (!meta) return;
            const pageUrl = meta.getAttribute('content') || '';
            const title = meta.getAttribute('data-title') || document.title || '';
            if (!pageUrl || !title) return;
            const api = <?php echo json_encode(($erp_admin_rel_prefix ?? '') . 'api/user_draft.php', JSON_UNESCAPED_SLASHES); ?>;
            function pingResume() {
                const body = new URLSearchParams({ action: 'track_page', page_url: pageUrl, title: title });
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(api, body);
                } else {
                    fetch(api, { method: 'POST', body, credentials: 'same-origin', keepalive: true }).catch(function () {});
                }
            }
            window.addEventListener('beforeunload', pingResume);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') pingResume();
            });
        })();
    </script>
    <?php
    $erp_student_table_js = __DIR__ . '/../js/student-table.js';
    $erp_student_table_js_v = is_file($erp_student_table_js) ? (string) filemtime($erp_student_table_js) : '1';
    ?>
    <script src="js/student-table.js?v=<?php echo htmlspecialchars($erp_student_table_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <?php
    $erp_header_tools_js = __DIR__ . '/../js/admin-header-tools.js';
    $erp_header_tools_js_v = is_file($erp_header_tools_js) ? (string) filemtime($erp_header_tools_js) : '1';
?>
    <script>
        window.ERP_SEARCH_URL = <?php echo json_encode('Utils/search.php'); ?>;
        window.ERP_NOTIF_FEED_URL = <?php echo json_encode('Utils/notifications_feed.php'); ?>;
        window.ERP_NOTIF_MARK_URL = <?php echo json_encode('Utils/mark_notification_read.php'); ?>;
    </script>
    <?php
    $erp_site_theme_js = __DIR__ . '/../../assets/js/site-theme.js';
    $erp_site_theme_js_v = is_file($erp_site_theme_js) ? (string) filemtime($erp_site_theme_js) : '1';
    ?>
    <script src="../assets/js/site-theme.js?v=<?php echo htmlspecialchars($erp_site_theme_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="js/admin-header-tools.js?v=<?php echo htmlspecialchars($erp_header_tools_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
        function closeErpLogoutModal() {
            var overlay = document.getElementById('erpLogoutModal');
            if (!overlay) return;
            overlay.classList.remove('show');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('erp-logout-modal-open');
        }

        function showLogoutConfirm(evt) {
            if (evt && evt.preventDefault) {
                evt.preventDefault();
            }
            var overlay = document.getElementById('erpLogoutModal');
            if (!overlay) {
                return false;
            }
            overlay.classList.add('show');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.classList.add('erp-logout-modal-open');
            var cancelBtn = document.getElementById('erpLogoutCancel');
            if (cancelBtn) {
                setTimeout(function () { cancelBtn.focus(); }, 60);
            }
            return false;
        }

        (function initErpLogoutModal() {
            var overlay = document.getElementById('erpLogoutModal');
            if (!overlay) return;
            var cancelBtn = document.getElementById('erpLogoutCancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', closeErpLogoutModal);
            }
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) {
                    closeErpLogoutModal();
                }
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && overlay.classList.contains('show')) {
                    closeErpLogoutModal();
                }
            });
        })();
    </script>
</body>
</html>
