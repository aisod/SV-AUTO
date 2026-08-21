            </div><!-- End erp-content -->
            
            <!-- Footer -->
            <footer class="erp-footer" style="padding: var(--space-5); border-top: 1px solid var(--gray-200); margin-top: auto;">
                <div class="erp-flex erp-justify-between erp-align-center" style="flex-wrap: wrap; gap: var(--space-3);">
                    <div style="color: var(--gray-500); font-size: var(--text-sm);">
                        © <?php echo date('Y'); ?> <?php echo htmlspecialchars($business['name'] ?? 'SV Auto Services'); ?>. All rights reserved.
                    </div>
                    <div style="color: var(--gray-500); font-size: var(--text-sm);">
                        Client Portal v1.0
                    </div>
                </div>
            </footer>
        </main>
    </div><!-- End erp-layout -->
    
    <!-- Sidebar Toggle Script -->
    <script>
    // Toggle sidebar for mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
    
    // Toggle user menu dropdown
    function toggleUserMenu() {
        const dropdown = document.getElementById('userDropdown');
        const notifDropdown = document.getElementById('notificationDropdown');
        if (notifDropdown) notifDropdown.style.display = 'none';
        dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
    }
    
    // Show logout confirmation modal
    function showLogoutConfirmation(event) {
        event.preventDefault();
        
        // Create modal overlay
        const modal = document.createElement('div');
        modal.id = 'logoutModal';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(8px); z-index: 10000; display: flex; align-items: center; justify-content: center; animation: fadeIn 0.2s;';
        
        modal.innerHTML = `
            <div style="background: white; border-radius: 16px; padding: 32px; max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideUp 0.3s;">
                <div style="text-align: center; margin-bottom: 24px;">
                    <div style="width: 64px; height: 64px; background: #FEF2F2; border: 2px solid #EF4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="fas fa-sign-out-alt" style="font-size: 28px; color: #EF4444;"></i>
                    </div>
                    <h3 style="font-size: 20px; font-weight: 700; color: #1F2937; margin: 0 0 8px 0;">Confirm Logout</h3>
                    <p style="font-size: 14px; color: #6B7280; margin: 0;">Are you sure you want to sign out of your account?</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button onclick="closeLogoutModal()" style="flex: 1; padding: 12px; background: #F3F4F6; border: none; border-radius: 8px; color: #4B5563; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#E5E7EB'" onmouseout="this.style.background='#F3F4F6'">
                        Cancel
                    </button>
                    <button onclick="window.location.href='../Admin/logout.php'" style="flex: 1; padding: 12px; background: #EF4444; border: none; border-radius: 8px; color: white; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'">
                        Logout
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeLogoutModal();
            }
        });
    }
    
    // Close logout modal
    function closeLogoutModal() {
        const modal = document.getElementById('logoutModal');
        if (modal) {
            modal.remove();
        }
    }
    
    // Desktop sidebar collapse
    (function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const userMenuButton = document.getElementById('userMenuButton');
        
        // User menu button click handler
        if (userMenuButton) {
            userMenuButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleUserMenu();
            });
        }
        
        // Sidebar toggle
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const icon = this.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                } else {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-chevron-left');
                }
            });
        }
    })();
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        const userMenu = document.getElementById('userMenuButton');
        const userDropdown = document.getElementById('userDropdown');
        const notifBtn = document.querySelector('[onclick*="toggleNotifications"]');
        const notifDropdown = document.getElementById('notificationDropdown');
        
        // Close user dropdown if clicking outside
        if (userDropdown && userMenu && !userMenu.contains(e.target) && !userDropdown.contains(e.target)) {
            userDropdown.style.display = 'none';
        }
        
        // Close notification dropdown if clicking outside
        if (notifDropdown && notifBtn && !notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
            notifDropdown.style.display = 'none';
        }
    });
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>
