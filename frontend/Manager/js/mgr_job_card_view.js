(function () {
    'use strict';

    if (typeof window.mgrBindDocActionsMenu === 'function') {
        window.mgrBindDocActionsMenu('mgr-jc-actions-btn', 'mgr-jc-actions-menu');
    }

    var printBtn = document.getElementById('mgr-jc-print-btn');
    if (printBtn && typeof window.mgrPrintPreviewFrame === 'function') {
        printBtn.addEventListener('click', function () {
            window.mgrPrintPreviewFrame('#mgr-jc-preview-frame');
        });
    }
})();
