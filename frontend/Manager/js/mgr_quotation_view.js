(function () {
    'use strict';

    if (typeof window.mgrBindDocActionsMenu === 'function') {
        window.mgrBindDocActionsMenu('mgr-qt-actions-btn', 'mgr-qt-actions-menu');
    }

    var printBtn = document.getElementById('mgr-qt-print-btn');
    if (printBtn && typeof window.mgrPrintPreviewFrame === 'function') {
        printBtn.addEventListener('click', function () {
            window.mgrPrintPreviewFrame('#mgr-qt-preview-frame');
        });
    }

    var markUrl = window.MGR_QT_MARK_VIEWED_URL;
    var quoteId = window.MGR_QT_QUOTE_ID;
    var alreadyViewed = window.MGR_QT_ALREADY_VIEWED === true;
    if (!markUrl || !quoteId || alreadyViewed) return;

    var fd = new FormData();
    fd.append('quote_id', String(quoteId));
    fetch(markUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function () {});
})();
