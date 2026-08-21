(function () {
    'use strict';

    var modal = document.getElementById('qtChatModal');
    if (!modal) return;

    var thread = document.getElementById('qt-chat-modal-messages');
    var input = document.getElementById('qt-chat-modal-input');
    var sendBtn = document.getElementById('qt-chat-modal-send');
    var errEl = document.getElementById('qt-chat-modal-error');
    var viewerRole = (window.QT_CHAT_VIEWER_ROLE || 'manager').toLowerCase();
    var apiUrl = window.QT_CHAT_API_URL || '';
    var quoteId = window.QT_CHAT_QUOTE_ID || 0;
    var messages = Array.isArray(window.QT_CHAT_INITIAL_MESSAGES) ? window.QT_CHAT_INITIAL_MESSAGES.slice() : [];

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatAt(at) {
        if (!at) return '';
        var d = new Date(String(at).replace(' ', 'T'));
        if (isNaN(d.getTime())) return at;
        return d.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit' });
    }

    function renderMessages() {
        if (!thread) return;
        if (!messages.length) {
            thread.innerHTML = '<div class="qt-chat-modal-empty"><i class="fas fa-inbox" aria-hidden="true"></i><p>No messages yet.</p><p class="qt-chat-modal-empty-sub">Say hello to start the thread.</p></div>';
            return;
        }
        thread.innerHTML = messages.map(function (msg) {
            var role = (msg.by_role || 'manager').toLowerCase();
            var isMine = role === viewerRole;
            var atLabel = formatAt(msg.at || '');
            return '<article class="qt-chat-bubble'
                + (isMine ? ' qt-chat-bubble--mine' : ' qt-chat-bubble--theirs')
                + (role === 'admin' ? ' qt-chat-bubble--admin' : ' qt-chat-bubble--manager')
                + '">'
                + '<p class="qt-chat-bubble-meta"><strong>' + escapeHtml(msg.by_name || '') + '</strong>'
                + '<span>' + (role === 'admin' ? 'Admin' : 'Manager') + '</span>'
                + (atLabel ? '<span> · ' + escapeHtml(atLabel) + '</span>' : '') + '</p>'
                + '<p class="qt-chat-bubble-text">' + escapeHtml(msg.text || '').replace(/\n/g, '<br>') + '</p>'
                + '</article>';
        }).join('');
        thread.scrollTop = thread.scrollHeight;
    }

    function updateTeaser() {
        var teaserMeta = document.querySelector('.qt-chat-teaser-meta');
        if (!teaserMeta) return;
        var peer = viewerRole === 'admin' ? 'manager' : 'admin';
        if (!messages.length) {
            teaserMeta.textContent = 'No messages yet — start the conversation.';
            return;
        }
        var last = messages[messages.length - 1];
        var preview = String(last.text || '').trim();
        if (preview.length > 72) preview = preview.slice(0, 69) + '…';
        teaserMeta.textContent = messages.length + ' message' + (messages.length === 1 ? '' : 's')
            + (preview ? ' · “' + preview + '”' : '');
    }

    function openChatModal() {
        modal.hidden = false;
        document.body.classList.add('qt-chat-modal-open');
        renderMessages();
        if (input) setTimeout(function () { input.focus(); }, 120);
    }

    function closeChatModal() {
        modal.hidden = true;
        document.body.classList.remove('qt-chat-modal-open');
    }

    function sendMessage() {
        if (!apiUrl || !quoteId || !input) return;
        var text = input.value.trim();
        if (errEl) {
            errEl.hidden = true;
            errEl.textContent = '';
        }
        if (!text) {
            if (errEl) {
                errEl.hidden = false;
                errEl.textContent = 'Please enter a message.';
            }
            return;
        }
        if (sendBtn) sendBtn.disabled = true;
        var fd = new FormData();
        fd.append('quote_id', String(quoteId));
        fd.append('message', text);
        fetch(apiUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (sendBtn) sendBtn.disabled = false;
                if (!j || !j.ok) {
                    if (errEl) {
                        errEl.hidden = false;
                        errEl.textContent = (j && j.error) ? j.error : 'Could not send message.';
                    }
                    return;
                }
                var msg = j.message || j;
                messages.push(msg);
                input.value = '';
                renderMessages();
                updateTeaser();
            })
            .catch(function () {
                if (sendBtn) sendBtn.disabled = false;
                if (errEl) {
                    errEl.hidden = false;
                    errEl.textContent = 'Network error. Try again.';
                }
            });
    }

    modal.querySelectorAll('[data-qt-chat-close]').forEach(function (el) {
        el.addEventListener('click', closeChatModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeChatModal();
    });
    if (sendBtn) sendBtn.addEventListener('click', sendMessage);
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    document.querySelectorAll('[data-qt-open-chat]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openChatModal();
        });
    });

    window.qtOpenChatModal = openChatModal;
    window.qtCloseChatModal = closeChatModal;

    renderMessages();

    if (window.QT_CHAT_OPEN_ON_LOAD === true || window.QT_CHAT_OPEN_ON_LOAD === 'true') {
        setTimeout(openChatModal, 280);
    }
})();
