(function () {
    'use strict';

    var panel = document.getElementById('qt-chat-panel');
    if (!panel) return;

    var list = document.getElementById('qt-chat-messages');
    var input = document.getElementById('qt-chat-input');
    var sendBtn = document.getElementById('qt-chat-send');
    var errEl = document.getElementById('qt-chat-error');
    var viewerRole = (panel.getAttribute('data-qt-chat-role') || 'manager').toLowerCase();
    var apiUrl = window.QT_CHAT_API_URL || '';
    var quoteId = window.QT_CHAT_QUOTE_ID || 0;

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function scrollChat() {
        if (!list) return;
        list.scrollTop = list.scrollHeight;
    }

    function appendMessage(msg) {
        if (!list || !msg) return;
        var empty = document.getElementById('qt-chat-empty');
        if (empty) empty.remove();
        var role = (msg.by_role || 'manager').toLowerCase();
        var isMine = role === viewerRole;
        var article = document.createElement('article');
        article.className = 'qt-chat-bubble'
            + (isMine ? ' qt-chat-bubble--mine' : ' qt-chat-bubble--theirs')
            + (role === 'admin' ? ' qt-chat-bubble--admin' : ' qt-chat-bubble--manager');
        article.setAttribute('data-msg-role', role);
        var atLabel = '';
        if (msg.at) {
            try {
                var d = new Date(String(msg.at).replace(' ', 'T'));
                if (!isNaN(d.getTime())) {
                    atLabel = d.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit' });
                }
            } catch (e) { atLabel = msg.at; }
        }
        article.innerHTML =
            '<p class="qt-chat-bubble-meta"><strong>' + escapeHtml(msg.by_name || '') + '</strong>'
            + '<span>' + (role === 'admin' ? 'Admin' : 'Manager') + '</span>'
            + (atLabel ? '<span> · ' + escapeHtml(atLabel) + '</span>' : '') + '</p>'
            + '<p class="qt-chat-bubble-text">' + escapeHtml(msg.text || '').replace(/\n/g, '<br>') + '</p>';
        list.appendChild(article);
        scrollChat();
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
                input.value = '';
                appendMessage(j.message || j);
            })
            .catch(function () {
                if (sendBtn) sendBtn.disabled = false;
                if (errEl) {
                    errEl.hidden = false;
                    errEl.textContent = 'Network error. Try again.';
                }
            });
    }

    if (sendBtn) sendBtn.addEventListener('click', sendMessage);
    if (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }
    scrollChat();
})();
