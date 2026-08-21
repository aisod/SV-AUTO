(function () {
    'use strict';

    if (typeof window.mgrBindDocActionsMenu === 'function') {
        window.mgrBindDocActionsMenu('mgr-inv-actions-btn', 'mgr-inv-actions-menu');
    }

    var printBtn = document.getElementById('mgr-inv-print-btn');
    if (printBtn && typeof window.mgrPrintPreviewFrame === 'function') {
        printBtn.addEventListener('click', function () {
            window.mgrPrintPreviewFrame('#mgr-inv-preview-frame');
        });
    }

    var markUrl = window.MGR_INV_MARK_VIEWED_URL;
    var invoiceId = window.MGR_INV_INVOICE_ID;
    var alreadyViewed = window.MGR_INV_ALREADY_VIEWED === true;
    if (!markUrl || !invoiceId || alreadyViewed) return;

    var fd = new FormData();
    fd.append('invoice_id', String(invoiceId));
    fetch(markUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
})();
