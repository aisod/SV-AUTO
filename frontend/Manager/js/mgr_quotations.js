/**
 * Manager quotations list — folder cards + search + sort (simple manager portal).
 */
(function () {
    'use strict';

    const quotationTable = document.getElementById('quotationTable');
    const searchInput = document.getElementById('searchInput');
    const sortFilter = document.getElementById('sortFilter');
    const noMatchRow = document.getElementById('qtNoMatchRow');
    const emptyRow = document.getElementById('qtEmptyRow');
    const dashCards = Array.from(document.querySelectorAll('.qt-stat-clickable'));
    const storageKey = window.MGR_QT_VIEW_STORAGE_KEY || 'mgr_qt_view_v1';
    let activePeriod = null;
    let saveTimer = null;
    let paperFilters = ['all'];
    let systemFilters = ['all'];
    let digitalFilters = ['all'];

    function isShowingAllQuotations() {
        return paperFilters.indexOf('all') !== -1
            && systemFilters.indexOf('all') !== -1
            && digitalFilters.indexOf('all') !== -1
            && !activePeriod;
    }

    function getPaperFilters() { return paperFilters; }
    function getSystemFilters() { return systemFilters; }
    function getDigitalFilters() { return digitalFilters; }

    function setFiltersInGroup(groupName, filters) {
        let list = (filters && filters.length) ? filters.slice() : ['all'];
        if ((groupName === 'system' || groupName === 'digital') && list.indexOf('all') === -1 && list.length > 1) {
            list = [list[0]];
        }
        if (list.indexOf('all') !== -1 || !list.length) {
            list = ['all'];
        }
        if (groupName === 'paper') paperFilters = list;
        else if (groupName === 'system') systemFilters = list;
        else if (groupName === 'digital') digitalFilters = list;
    }

    function setShowAllFilters() {
        setFiltersInGroup('paper', ['all']);
        setFiltersInGroup('system', ['all']);
        setFiltersInGroup('digital', ['all']);
    }

    function qtRowFilterCtx(row) {
        return {
            status: (row.getAttribute('data-status') || '').toLowerCase(),
            clientStatus: row.getAttribute('data-client-status') || '',
            paperSigned: (row.getAttribute('data-paper-signed') || '') === '1',
            formStatus: (row.getAttribute('data-form-status') || '').toLowerCase()
        };
    }

    function qtIsPendingManagerStatus(status) {
        return ['pending', 'pending_manager', 'sent_back_admin'].indexOf(status) !== -1;
    }

    function paperFilterMatches(row, paperFilters) {
        if (!paperFilters || paperFilters.indexOf('all') !== -1) return true;
        const ctx = qtRowFilterCtx(row);
        return paperFilters.some(function (paperFilter) {
            if (ctx.status === 'rejected') return false;
            if (paperFilter === 'paper_pending') {
                return !ctx.paperSigned && qtIsPendingManagerStatus(ctx.status);
            }
            if (paperFilter === 'paper_signed') {
                return ctx.paperSigned && ctx.status === 'approved';
            }
            if (paperFilter === 'awaiting_client') {
                return ctx.status === 'approved'
                    && ctx.paperSigned
                    && ctx.clientStatus === ''
                    && ctx.formStatus !== 'sent_to_client';
            }
            return false;
        });
    }

    function systemFilterMatches(row, systemFilters) {
        if (!systemFilters || systemFilters.indexOf('all') !== -1) return true;
        const ctx = qtRowFilterCtx(row);
        return systemFilters.some(function (systemFilter) {
            if (systemFilter === 'pending_group') return qtIsPendingManagerStatus(ctx.status);
            if (systemFilter === 'approved') return ctx.status === 'approved';
            if (systemFilter === 'rejected') return ctx.status === 'rejected';
            return false;
        });
    }

    function digitalFilterMatches(row, digitalFilters) {
        if (!digitalFilters || digitalFilters.indexOf('all') !== -1) return true;
        const sent = (row.getAttribute('data-digital-sent') || '') === '1';
        const viewed = (row.getAttribute('data-digital-viewed') || '') === '1';
        return digitalFilters.some(function (f) {
            if (f === 'new_review') return sent && !viewed;
            if (f === 'viewed') return sent && viewed;
            if (f === 'not_sent') return !sent;
            return false;
        });
    }

    function getFilterConflictMessage(paperFilters, systemFilters) {
        if (!paperFilters || !systemFilters) return '';
        if (paperFilters.indexOf('all') !== -1 || systemFilters.indexOf('all') !== -1) return '';
        const paperPending = paperFilters.indexOf('paper_pending') !== -1;
        const paperSigned = paperFilters.indexOf('paper_signed') !== -1;
        const awaitingClient = paperFilters.indexOf('awaiting_client') !== -1;
        const sysPending = systemFilters.indexOf('pending_group') !== -1;
        const sysApproved = systemFilters.indexOf('approved') !== -1;
        if (paperPending && sysApproved) {
            return 'No quotations match this folder. Try another folder above.';
        }
        if (paperSigned && sysPending) {
            return 'No quotations match this folder. Try another folder above.';
        }
        if (awaitingClient && sysPending) {
            return 'No quotations match this folder. Try another folder above.';
        }
        if ((paperPending || paperSigned || awaitingClient) && systemFilters.indexOf('rejected') !== -1) {
            return 'No quotations match this folder. Try another folder above.';
        }
        return '';
    }

    function getListTitle() {
        const activeCard = document.querySelector('.ops-folder-card.is-active');
        if (activeCard) {
            return activeCard.getAttribute('data-qt-list-title')
                || activeCard.getAttribute('data-mgr-list-title')
                || 'Quotations';
        }
        if (activePeriod) {
            const labels = {
                today: 'Submitted today',
                week: 'Submitted this week',
                month: 'Submitted this month',
                year: 'Submitted this year'
            };
            return labels[activePeriod] || 'Quotations';
        }
        return isShowingAllQuotations() ? 'All quotations' : 'Quotations';
    }

    function updateTableHeading(visibleCount) {
        const titleEl = document.getElementById('qtTableTitle');
        const footMeta = document.getElementById('opsFilterResult');
        const metaText = visibleCount === 1 ? '1 quotation' : (visibleCount + ' quotations');
        if (titleEl) titleEl.textContent = getListTitle();
        if (footMeta) {
            footMeta.textContent = 'Showing ' + metaText;
        }
    }

    function clearDashCardActive() {
        document.querySelectorAll('.ops-folder-card.is-active').forEach(function (c) {
            c.classList.remove('is-active');
        });
    }

    function periodMatches(row) {
        if (!activePeriod) return true;
        return (row.getAttribute('data-in-' + activePeriod) || '') === '1';
    }

    function sortQuotationRows(rows, sortValue, tbody) {
        rows.sort(function (a, b) {
            const dateA = Number(a.getAttribute('data-date-ts') || 0);
            const dateB = Number(b.getAttribute('data-date-ts') || 0);
            const amountA = Number(a.getAttribute('data-amount') || 0);
            const amountB = Number(b.getAttribute('data-amount') || 0);
            if (sortValue === 'oldest') return dateA - dateB;
            if (sortValue === 'amount_high') return amountB - amountA;
            if (sortValue === 'amount_low') return amountA - amountB;
            return dateB - dateA;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
    }

    function applyFilters() {
        if (!quotationTable) return;
        const tbody = quotationTable.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr[data-quote-row="1"]'));
        const query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
        const paperFilters = getPaperFilters();
        const systemFilters = getSystemFilters();
        const digitalFilters = getDigitalFilters();
        const sortBy = sortFilter ? sortFilter.value : 'newest';

        sortQuotationRows(rows, sortBy, tbody);

        const conflictMsg = getFilterConflictMessage(paperFilters, systemFilters);
        let visibleCount = 0;
        rows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            const show = !conflictMsg
                && text.includes(query)
                && paperFilterMatches(row, paperFilters)
                && systemFilterMatches(row, systemFilters)
                && digitalFilterMatches(row, digitalFilters)
                && periodMatches(row);
            row.classList.remove('qt-search-hit');
            row.style.display = show ? '' : 'none';
            if (show) {
                visibleCount++;
                if (query) row.classList.add('qt-search-hit');
            }
        });

        if (noMatchRow) {
            const noMatchMessage = document.getElementById('qtNoMatchMessage');
            if (noMatchMessage) {
                noMatchMessage.textContent = conflictMsg || 'No matching quotations found';
            }
            noMatchRow.style.display = (!emptyRow && visibleCount === 0) ? '' : 'none';
        }
        updateTableHeading(visibleCount);
        scheduleSaveViewState();
        if (window.ErpStudentTable) {
            ErpStudentTable.sync('quotationTable');
        }
    }

    function scheduleSaveViewState() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveViewState, 250);
    }

    function saveViewState() {
        try {
            const dashCard = document.querySelector('.ops-folder-card.is-active');
            let dash = null;
            if (dashCard) {
                const period = dashCard.getAttribute('data-qt-period');
                const tab = dashCard.getAttribute('data-qt-tab');
                const digital = dashCard.getAttribute('data-mgr-digital');
                if (period) dash = 'period:' + period;
                else if (digital) dash = 'digital:' + digital;
                else if (tab) dash = 'tab:' + tab;
            }
            localStorage.setItem(storageKey, JSON.stringify({
                paperFilters: getPaperFilters(),
                systemFilters: getSystemFilters(),
                digitalFilters: getDigitalFilters(),
                period: activePeriod || null,
                dash: dash,
                search: searchInput ? searchInput.value : '',
                sort: sortFilter ? sortFilter.value : 'newest',
                savedAt: Date.now()
            }));
        } catch (e) { /* ignore */ }
    }

    function restoreViewState() {
        try {
            const raw = localStorage.getItem(storageKey);
            if (!raw) return false;
            const state = JSON.parse(raw);
            if (!state || typeof state !== 'object') return false;

            if (searchInput && typeof state.search === 'string') searchInput.value = state.search;
            if (sortFilter && state.sort) sortFilter.value = state.sort;
            activePeriod = state.period || null;

            clearDashCardActive();
            if (state.dash) {
                let card = null;
                if (state.dash.indexOf('period:') === 0) {
                    card = document.querySelector('.ops-folder-card[data-qt-period="' + state.dash.slice(7) + '"]');
                } else if (state.dash.indexOf('digital:') === 0) {
                    card = document.querySelector('.ops-folder-card[data-mgr-digital="' + state.dash.slice(8) + '"]');
                } else if (state.dash.indexOf('tab:') === 0) {
                    card = document.querySelector('.ops-folder-card[data-qt-tab="' + state.dash.slice(4) + '"]');
                }
                if (card) card.classList.add('is-active');
            }

            if (Array.isArray(state.paperFilters)) setFiltersInGroup('paper', state.paperFilters);
            else setFiltersInGroup('paper', ['all']);

            if (Array.isArray(state.systemFilters)) {
                const sysOnly = state.systemFilters.filter(function (f) { return f && f !== 'all'; });
                setFiltersInGroup('system', sysOnly.length ? [sysOnly[0]] : ['all']);
            } else setFiltersInGroup('system', ['all']);

            if (Array.isArray(state.digitalFilters)) {
                const digOnly = state.digitalFilters.filter(function (f) { return f && f !== 'all'; });
                setFiltersInGroup('digital', digOnly.length ? [digOnly[0]] : ['all']);
            } else setFiltersInGroup('digital', ['all']);

            applyFilters();
            return true;
        } catch (e) {
            return false;
        }
    }

    function applyDefaultView() {
        setShowAllFilters();
        clearDashCardActive();
        const allCard = document.querySelector('.ops-folder-card[data-qt-tab="all"]');
        if (allCard) allCard.classList.add('is-active');
        applyFilters();
    }

    function activateFromDashboardCard(card) {
        if (!card) return;
        clearDashCardActive();
        card.classList.add('is-active');
        activePeriod = card.getAttribute('data-qt-period') || null;
        const tabFilter = card.getAttribute('data-qt-tab');
        const systemTabs = card.getAttribute('data-qt-system-tabs');
        const digital = card.getAttribute('data-mgr-digital');

        if (tabFilter === 'all') {
            setShowAllFilters();
        } else {
            setFiltersInGroup('digital', digital ? [digital] : ['all']);
            if (tabFilter) setFiltersInGroup('paper', [tabFilter]);
            else setFiltersInGroup('paper', ['all']);
            if (systemTabs) {
                setFiltersInGroup('system', systemTabs.split(',').map(function (s) { return s.trim(); }).filter(Boolean));
            } else {
                setFiltersInGroup('system', ['all']);
            }
        }
        applyFilters();
        const el = document.getElementById('qtQuotationsList');
        if (el) {
            requestAnimationFrame(function () {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }

    dashCards.forEach(function (card) {
        card.addEventListener('click', function () { activateFromDashboardCard(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                activateFromDashboardCard(card);
            }
        });
    });

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (sortFilter) sortFilter.addEventListener('change', applyFilters);

    function applyUrlFilter() {
        const params = new URLSearchParams(window.location.search);
        const filter = params.get('filter');
        if (filter === 'inbox') {
            const inboxCard = document.querySelector('.ops-folder-card[data-mgr-digital="new_review"]');
            if (inboxCard) {
                activateFromDashboardCard(inboxCard);
                return true;
            }
            setFiltersInGroup('paper', ['all']);
            setFiltersInGroup('system', ['all']);
            setFiltersInGroup('digital', ['new_review']);
            activePeriod = null;
            clearDashCardActive();
            applyFilters();
            return true;
        }
        return false;
    }

    if (!applyUrlFilter() && !restoreViewState()) {
        applyDefaultView();
    }


    if (window.ErpStudentTable) {
        ErpStudentTable.init('quotationTable');
    }
})();
