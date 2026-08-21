(function () {
    'use strict';

    function initTheme() {
        if (window.SVSiteTheme && typeof window.SVSiteTheme.apply === 'function') {
            window.SVSiteTheme.apply(window.SVSiteTheme.getSaved());
        }
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function searchApiUrl() {
        if (window.MGR_SEARCH_URL) {
            return window.MGR_SEARCH_URL;
        }
        if (window.ERP_SEARCH_URL) {
            return window.ERP_SEARCH_URL;
        }
        return 'Utils/search.php';
    }

    function getSearchOverlay() {
        return document.getElementById('globalSearchBar');
    }

    function closeGlobalSearch() {
        var overlay = getSearchOverlay();
        if (overlay) {
            overlay.remove();
        }
        document.body.classList.remove('erp-search-open');
    }

    function searchEmptyIntroHtml() {
        return ''
            + '<div class="erp-search-empty erp-search-empty--intro">'
            + '<div class="erp-search-empty-icon" aria-hidden="true"><i class="fas fa-search"></i></div>'
            + '<p class="erp-search-empty-title">Search the workspace</p>'
            + '<p class="erp-search-empty-desc">Find job cards, clients, quotations, invoices, and more.</p>'
            + '<div class="erp-search-scope">'
            + '<span>Job cards</span><span>Clients</span><span>Quotations</span><span>Invoices</span>'
            + '</div></div>';
    }

    function getSearchResultLinks() {
        var resultsDiv = document.getElementById('globalSearchResults');
        if (!resultsDiv) {
            return [];
        }
        return Array.prototype.slice.call(resultsDiv.querySelectorAll('.erp-search-result'));
    }

    function setActiveSearchResult(index) {
        var links = getSearchResultLinks();
        if (!links.length) {
            return;
        }
        var idx = Math.max(0, Math.min(index, links.length - 1));
        links.forEach(function (el, i) {
            el.classList.toggle('is-active', i === idx);
        });
        var active = links[idx];
        if (active && typeof active.scrollIntoView === 'function') {
            active.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    function renderSearchResults(resultsDiv, data, query) {
        if (!Array.isArray(data) || data.error) {
            resultsDiv.innerHTML = ''
                + '<div class="erp-search-empty erp-search-empty--error">'
                + '<div class="erp-search-empty-icon" aria-hidden="true"><i class="fas fa-circle-exclamation"></i></div>'
                + '<p class="erp-search-empty-title">Search unavailable</p>'
                + '<p class="erp-search-empty-desc">Please try again in a moment.</p></div>';
            return;
        }
        if (!data.length) {
            resultsDiv.innerHTML = ''
                + '<div class="erp-search-empty">'
                + '<div class="erp-search-empty-icon" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></div>'
                + '<p class="erp-search-empty-title">No results</p>'
                + '<p class="erp-search-empty-desc">Nothing matched <strong>' + escapeHtml(query) + '</strong>. Try another term.</p>'
                + '</div>';
            return;
        }
        resultsDiv.innerHTML = '<div class="erp-search-results-list">' + data.map(function (item, index) {
            var url = escapeHtml(item.url || '#');
            var title = escapeHtml(item.title || '');
            var subtitle = escapeHtml(item.subtitle || '');
            var type = escapeHtml(item.type || '');
            var icon = escapeHtml(item.icon || 'fas fa-file');
            var activeClass = index === 0 ? ' is-active' : '';
            return ''
                + '<a href="' + url + '" class="erp-search-result' + activeClass + '" data-index="' + index + '">'
                + '<span class="erp-search-result-icon"><i class="' + icon + '" aria-hidden="true"></i></span>'
                + '<span class="erp-search-result-body">'
                + '<span class="erp-search-result-title">' + title + '</span>'
                + '<span class="erp-search-result-sub">' + subtitle + '</span>'
                + '</span>'
                + '<span class="erp-search-result-type">' + type + '</span>'
                + '<i class="fas fa-arrow-right erp-search-result-arrow" aria-hidden="true"></i>'
                + '</a>';
        }).join('') + '</div>';
    }

    function runSearch(query, resultsDiv) {
        resultsDiv.innerHTML = ''
            + '<div class="erp-search-empty erp-search-empty--loading">'
            + '<div class="erp-search-empty-icon" aria-hidden="true"><i class="fas fa-spinner fa-spin"></i></div>'
            + '<p class="erp-search-empty-title">Searching…</p>'
            + '<p class="erp-search-empty-desc">Looking up records across the system.</p></div>';
        fetch(searchApiUrl() + '?q=' + encodeURIComponent(query), { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                renderSearchResults(resultsDiv, data, query);
            })
            .catch(function () {
                resultsDiv.innerHTML = ''
                    + '<div class="erp-search-empty erp-search-empty--error">'
                    + '<div class="erp-search-empty-icon" aria-hidden="true"><i class="fas fa-wifi"></i></div>'
                    + '<p class="erp-search-empty-title">Search failed</p>'
                    + '<p class="erp-search-empty-desc">Check your connection and try again.</p></div>';
            });
    }

    function getActiveSearchIndex() {
        var links = getSearchResultLinks();
        for (var i = 0; i < links.length; i++) {
            if (links[i].classList.contains('is-active')) {
                return i;
            }
        }
        return 0;
    }

    function bindSearchInput(input) {
        var debounceTimer = null;

        input.addEventListener('input', function () {
            var query = input.value.trim();
            var resultsDiv = document.getElementById('globalSearchResults');
            if (!resultsDiv) {
                return;
            }
            if (query.length < 1) {
                resultsDiv.innerHTML = searchEmptyIntroHtml();
                return;
            }
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                runSearch(query, resultsDiv);
            }, 220);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeGlobalSearch();
                return;
            }

            var links = getSearchResultLinks();
            if (!links.length) {
                return;
            }

            var idx = getActiveSearchIndex();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActiveSearchResult(Math.min(idx + 1, links.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActiveSearchResult(Math.max(idx - 1, 0));
            } else if (e.key === 'Enter') {
                var active = links[idx] || links[0];
                if (active && active.href && active.href !== '#') {
                    e.preventDefault();
                    window.location.href = active.href;
                }
            }
        });
    }

    function openGlobalSearch() {
        if (getSearchOverlay()) {
            closeGlobalSearch();
            return;
        }

        var overlay = document.createElement('div');
        overlay.id = 'globalSearchBar';
        overlay.className = 'erp-search-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Search');
        overlay.innerHTML = ''
            + '<div class="erp-search-dialog">'
            + '<div class="erp-search-dialog-hd">'
            + '<label class="erp-search-dialog-field" for="globalSearchInput">'
            + '<span class="erp-search-dialog-icon" aria-hidden="true"><i class="fas fa-search"></i></span>'
            + '<input type="search" id="globalSearchInput" class="erp-search-dialog-input" placeholder="Search job cards, clients, quotations, invoices…" autocomplete="off" spellcheck="false">'
            + '</label>'
            + '<button type="button" class="erp-search-dialog-esc" id="globalSearchClose" aria-label="Close search">ESC</button>'
            + '</div>'
            + '<div id="globalSearchResults" class="erp-search-dialog-results">'
            + searchEmptyIntroHtml()
            + '</div>'
            + '<div class="erp-search-dialog-ft">'
            + '<span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>'
            + '<span><kbd>↵</kbd> open</span>'
            + '<span><kbd>esc</kbd> close</span>'
            + '</div>'
            + '</div>';

        document.body.appendChild(overlay);
        document.body.classList.add('erp-search-open');
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeGlobalSearch();
            }
        });

        var input = document.getElementById('globalSearchInput');
        var closeBtn = document.getElementById('globalSearchClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.preventDefault();
                closeGlobalSearch();
            });
        }
        var resultsDiv = document.getElementById('globalSearchResults');
        if (resultsDiv) {
            resultsDiv.addEventListener('mouseover', function (e) {
                var item = e.target.closest('.erp-search-result');
                if (!item) {
                    return;
                }
                var links = getSearchResultLinks();
                var i = links.indexOf(item);
                if (i >= 0) {
                    setActiveSearchResult(i);
                }
            });
        }

        if (input) {
            bindSearchInput(input);
            setTimeout(function () {
                input.focus();
            }, 50);
        }
    }

    window.erpToggleSearch = openGlobalSearch;
    window.toggleSearch = openGlobalSearch;
    if (typeof window.mgrToggleSearch !== 'function') {
        window.mgrToggleSearch = openGlobalSearch;
    }

    function escapeNotifHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getNotifFeedUrl() {
        return window.ERP_NOTIF_FEED_URL || window.MGR_NOTIF_FEED_URL || '';
    }

    function getNotifMarkUrl() {
        return window.ERP_NOTIF_MARK_URL || window.MGR_NOTIF_MARK_URL || 'Utils/mark_notification_read.php';
    }

    function notifFeedSignature(data) {
        if (!data || !data.ok) {
            return '';
        }
        var items = data.items || [];
        var head = items.length ? items[0].id + ':' + (items[0].time || '') : '0';
        return String(data.count) + '|' + head + '|' + items.length;
    }

    function renderNotificationFeed(data) {
        var wrap = document.querySelector('.rd-dash-notif-wrap');
        var dropdown = document.getElementById('notificationDropdown');
        var btn = document.querySelector('.rd-dash-notif-btn');
        if (!wrap || !dropdown || !btn || !data || !data.ok) {
            return;
        }

        var count = parseInt(data.count, 10) || 0;
        var items = data.items || [];
        var markBase = getNotifMarkUrl();

        btn.classList.toggle('has-unread', count > 0);
        var titleBits = ['Notifications'];
        if (count > 0) {
            titleBits.push('(' + count + ' unread)');
        }
        btn.title = titleBits.join(' ');
        btn.setAttribute('aria-label', titleBits.join(', '));

        var dot = btn.querySelector('.rd-dash-notif-dot');
        if (count > 0) {
            if (!dot) {
                dot = document.createElement('span');
                dot.className = 'rd-dash-notif-dot';
                dot.setAttribute('aria-hidden', 'true');
                btn.appendChild(dot);
            }
        } else if (dot) {
            dot.remove();
        }

        var hd = dropdown.querySelector('.rd-dash-notif-dropdown-hd');
        if (hd) {
            var badge = hd.querySelector('.rd-dash-notif-dropdown-badge');
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'rd-dash-notif-dropdown-badge';
                    hd.appendChild(badge);
                }
                badge.textContent = count + ' new';
            } else if (badge) {
                badge.remove();
            }
        }

        var body = dropdown.querySelector('.rd-dash-notif-dropdown-body');
        if (!body) {
            return;
        }

        if (!items.length) {
            body.innerHTML =
                '<div class="rd-dash-notif-empty">' +
                '<i class="fas fa-bell-slash" aria-hidden="true"></i>' +
                '<p>No notifications yet</p></div>';
            return;
        }

        var html = '';
        items.forEach(function (n) {
            var readClass = n.is_read ? ' is-read' : '';
            var msg = n.message
                ? '<span class="rd-dash-notif-item-msg">' + escapeNotifHtml(n.message) + '</span>'
                : '';
            var time = n.time
                ? '<span class="rd-dash-notif-item-time">' + escapeNotifHtml(n.time) + '</span>'
                : '';
            html +=
                '<a href="' + escapeNotifHtml(n.link || '#') + '"' +
                ' class="rd-dash-notif-item' + readClass + '"' +
                ' data-notif-id="' + escapeNotifHtml(String(n.id)) + '"' +
                ' onclick="return erpNotifItemClick(' + parseInt(n.id, 10) + ', event)">' +
                '<span class="rd-dash-notif-item-dot" aria-hidden="true"></span>' +
                '<span class="rd-dash-notif-item-text">' +
                '<span class="rd-dash-notif-item-title">' + escapeNotifHtml(n.title) + '</span>' +
                msg + time +
                '</span></a>';
        });
        body.innerHTML = html;
        window.__erpNotifMarkBase = markBase;
    }

    window.erpNotifItemClick = function (id, evt) {
        if (evt && evt.preventDefault) {
            evt.preventDefault();
        }
        var href = evt && evt.currentTarget ? evt.currentTarget.href : '';
        var markUrl = window.__erpNotifMarkBase || getNotifMarkUrl();
        if (id && markUrl) {
            fetch(markUrl + '?id=' + encodeURIComponent(String(id)), {
                method: 'POST',
                credentials: 'same-origin'
            }).catch(function (err) {
                console.error('Error marking notification read:', err);
            });
        }
        if (href) {
            window.location.href = href;
        }
        return false;
    };

    var _erpNotifPollTimer = null;
    var _erpNotifLastSig = '';

    function refreshNotifications(force) {
        var url = getNotifFeedUrl();
        if (!url || !document.getElementById('notificationDropdown')) {
            return;
        }
        fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('feed ' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                var sig = notifFeedSignature(data);
                if (force || sig !== _erpNotifLastSig) {
                    _erpNotifLastSig = sig;
                    renderNotificationFeed(data);
                }
            })
            .catch(function () { /* silent — will retry on next poll */ });
    }

    function initNotificationPolling() {
        if (!getNotifFeedUrl() || !document.getElementById('notificationDropdown')) {
            return;
        }
        var POLL_MS = 15000;
        refreshNotifications(true);
        if (_erpNotifPollTimer) {
            clearInterval(_erpNotifPollTimer);
        }
        _erpNotifPollTimer = setInterval(function () {
            if (!document.hidden) {
                refreshNotifications(false);
            }
        }, POLL_MS);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                refreshNotifications(true);
            }
        });
    }

    window.erpRefreshNotifications = refreshNotifications;

    window.erpToggleNotifications = function () {
        if (typeof toggleNotifications !== 'function') {
            return;
        }
        refreshNotifications(true);
        toggleNotifications();
        var dropdown = document.getElementById('notificationDropdown');
        var btn = document.querySelector('.rd-dash-notif-btn');
        if (!dropdown || !btn || dropdown.style.display === 'none') {
            return;
        }
        var rect = btn.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.top = Math.round(rect.bottom + 8) + 'px';
        dropdown.style.right = Math.max(12, Math.round(window.innerWidth - rect.right)) + 'px';
        dropdown.style.left = 'auto';
        dropdown.style.zIndex = '10050';
    };

    function initSearchTriggers() {
        document.querySelectorAll('.rd-dash-search, [data-erp-search-trigger]').forEach(function (btn) {
            if (btn.dataset.erpSearchBound === '1') {
                return;
            }
            btn.dataset.erpSearchBound = '1';
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openGlobalSearch();
            });
        });
    }

    document.addEventListener('keydown', function (e) {
        if (!(e.metaKey || e.ctrlKey) || String(e.key).toLowerCase() !== 'k') {
            return;
        }
        e.preventDefault();
        openGlobalSearch();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && getSearchOverlay()) {
            closeGlobalSearch();
        }
    });

    function boot() {
        initTheme();
        initSearchTriggers();
        initNotificationPolling();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
