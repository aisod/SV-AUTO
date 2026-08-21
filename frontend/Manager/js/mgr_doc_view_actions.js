/**
 * Manager document views — Actions dropdown + print document.
 */
(function () {
    'use strict';

    function bindActionsMenu(btnId, menuId) {
        var btn = document.getElementById(btnId);
        var menu = document.getElementById(menuId);
        var wrap = btn ? btn.closest('.crm-cust-actions-wrap') : null;
        if (!btn || !menu) return;

        function closeMenu() {
            menu.hidden = true;
            menu.setAttribute('hidden', '');
            btn.setAttribute('aria-expanded', 'false');
            if (wrap) wrap.classList.remove('is-open');
        }

        function openMenu() {
            menu.hidden = false;
            menu.removeAttribute('hidden');
            btn.setAttribute('aria-expanded', 'true');
            if (wrap) wrap.classList.add('is-open');
        }

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (menu.hidden) openMenu();
            else closeMenu();
        });

        document.addEventListener('click', function (e) {
            if (wrap && wrap.contains(e.target)) return;
            closeMenu();
        });
    }

    function printPreviewFrame(selector) {
        var frame = document.querySelector(selector);
        if (!frame || !frame.contentWindow) return;
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch (e) { /* ignore */ }
    }

    window.mgrBindDocActionsMenu = bindActionsMenu;
    window.mgrPrintPreviewFrame = printPreviewFrame;
})();
