/**
 * Manager invoices list — folder cards + search + sort.
 */
(function () {
    'use strict';

    const table = document.getElementById('invoiceTable');
    const searchInput = document.getElementById('invSearchInput');
    const sortFilter = document.getElementById('invSortFilter');
    const noMatchRow = document.getElementById('invNoMatchRow');
    const emptyRow = document.getElementById('invEmptyRow');
    const dashCards = Array.from(document.querySelectorAll('.mgr-stat-clickable'));
    const storageKey = window.MGR_INV_VIEW_STORAGE_KEY || 'mgr_inv_view_v1';
    let activePeriod = null;
    let saveTimer = null;
    let digitalFilters = ['all'];
    let paymentFilters = ['all'];

    function getDigitalFilters() { return digitalFilters; }
    function getPaymentFilters() { return paymentFilters; }

    function isShowingAll() {
        return digitalFilters.indexOf('all') !== -1
            && paymentFilters.indexOf('all') !== -1
            && !activePeriod;
    }

    function setFiltersInGroup(groupName, filters) {
        let list = (filters && filters.length) ? filters.slice() : ['all'];
        if (groupName === 'payment' && list.indexOf('all') === -1 && list.length > 1) {
            list = [list[0]];
        }
        if (groupName === 'digital' && list.indexOf('all') === -1 && list.length > 1) {
            list = [list[0]];
        }
        if (list.indexOf('all') !== -1 || !list.length) {
            list = ['all'];
        }
        if (groupName === 'digital') digitalFilters = list;
        else if (groupName === 'payment') paymentFilters = list;
    }

    function setShowAllFilters() {
        setFiltersInGroup('digital', ['all']);
        setFiltersInGroup('payment', ['all']);
    }

    function digitalFilterMatches(row, filters) {
        if (!filters || filters.indexOf('all') !== -1) return true;
        const sent = (row.getAttribute('data-digital-sent') || '') === '1';
        const viewed = (row.getAttribute('data-digital-viewed') || '') === '1';
        return filters.some(function (f) {
            if (f === 'new_review') return sent && !viewed;
            if (f === 'viewed') return sent && viewed;
            if (f === 'not_sent') return !sent;
            return false;
        });
    }

    function paymentFilterMatches(row, filters) {
        if (!filters || filters.indexOf('all') !== -1) return true;
        const paid = row.getAttribute('data-paid') || '';
        return filters.some(function (f) {
            if (f === 'paid') return paid === 'paid';
            if (f === 'unpaid_group') return paid === 'unpaid' || paid === 'partial';
            return false;
        });
    }

    function periodMatches(row) {
        if (!activePeriod) return true;
        return (row.getAttribute('data-in-' + activePeriod) || '') === '1';
    }

    function getListTitle() {
        const activeCard = document.querySelector('.ops-folder-card.is-active');
        if (activeCard) {
            return activeCard.getAttribute('data-mgr-list-title') || 'Invoices';
        }
        if (activePeriod) {
            const labels = { today: 'Issued today', week: 'Issued this week', month: 'Issued this month' };
            return labels[activePeriod] || 'Invoices';
        }
        return isShowingAll() ? 'All invoices' : 'Invoices';
    }

    function updateTableHeading(visibleCount) {
        const titleEl = document.getElementById('invTableTitle');
        const footMeta = document.getElementById('opsFilterResult');
        const metaText = visibleCount === 1 ? '1 invoice' : (visibleCount + ' invoices');
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

    function sortRows(rows, sortValue, tbody) {
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
        if (!table) return;
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr[data-mgr-row="1"]'));
        const query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
        const sortBy = sortFilter ? sortFilter.value : 'newest';

        sortRows(rows, sortBy, tbody);

        let visibleCount = 0;
        rows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            const show = text.includes(query)
                && digitalFilterMatches(row, digitalFilters)
                && paymentFilterMatches(row, paymentFilters)
                && periodMatches(row);
            row.classList.remove('qt-search-hit');
            row.style.display = show ? '' : 'none';
            if (show) {
                visibleCount++;
                if (query) row.classList.add('qt-search-hit');
            }
        });

        if (noMatchRow) {
            noMatchRow.style.display = (!emptyRow && visibleCount === 0) ? '' : 'none';
        }
        updateTableHeading(visibleCount);
        scheduleSaveViewState();
        if (window.ErpStudentTable) {
            ErpStudentTable.sync('invoiceTable');
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
                const period = dashCard.getAttribute('data-mgr-period');
                const digital = dashCard.getAttribute('data-mgr-digital');
                const payment = dashCard.getAttribute('data-mgr-payment');
                if (period) dash = 'period:' + period;
                else if (digital) dash = 'digital:' + digital;
                else if (payment) dash = 'payment:' + payment;
            }
            localStorage.setItem(storageKey, JSON.stringify({
                digitalFilters: getDigitalFilters(),
                paymentFilters: getPaymentFilters(),
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
                    card = document.querySelector('.ops-folder-card[data-mgr-period="' + state.dash.slice(7) + '"]');
                } else if (state.dash.indexOf('digital:') === 0) {
                    card = document.querySelector('.ops-folder-card[data-mgr-digital="' + state.dash.slice(8) + '"]');
                } else if (state.dash.indexOf('payment:') === 0) {
                    card = document.querySelector('.ops-folder-card[data-mgr-payment="' + state.dash.slice(8) + '"]');
                }
                if (card) card.classList.add('is-active');
            }

            if (Array.isArray(state.digitalFilters)) {
                const digOnly = state.digitalFilters.filter(function (f) { return f && f !== 'all'; });
                setFiltersInGroup('digital', digOnly.length ? [digOnly[0]] : ['all']);
            } else {
                setFiltersInGroup('digital', ['all']);
            }

            if (Array.isArray(state.paymentFilters)) {
                const payOnly = state.paymentFilters.filter(function (f) { return f && f !== 'all'; });
                setFiltersInGroup('payment', payOnly.length ? [payOnly[0]] : ['all']);
            } else {
                setFiltersInGroup('payment', ['all']);
            }

            applyFilters();
            return true;
        } catch (e) {
            return false;
        }
    }

    function applyDefaultView() {
        setShowAllFilters();
        clearDashCardActive();
        const allCard = document.querySelector('.ops-folder-card[data-mgr-digital="all"][data-mgr-payment="all"]');
        if (allCard) allCard.classList.add('is-active');
        applyFilters();
    }

    function activateFromDashboardCard(card) {
        if (!card) return;
        clearDashCardActive();
        card.classList.add('is-active');
        activePeriod = card.getAttribute('data-mgr-period') || null;
        const digital = card.getAttribute('data-mgr-digital');
        const payment = card.getAttribute('data-mgr-payment');
        if (digital === 'all') {
            setShowAllFilters();
        } else {
            if (digital) setFiltersInGroup('digital', [digital]);
            else setFiltersInGroup('digital', ['all']);
            if (payment && payment !== 'all') {
                setFiltersInGroup('payment', [payment]);
            } else {
                setFiltersInGroup('payment', ['all']);
            }
        }
        applyFilters();
        const el = document.getElementById('invInvoicesList');
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
            setFiltersInGroup('digital', ['new_review']);
            setFiltersInGroup('payment', ['all']);
            activePeriod = null;
            clearDashCardActive();
            applyFilters();
            return true;
        }
        if (filter === 'all') {
            applyDefaultView();
            return true;
        }
        return false;
    }

    if (!applyUrlFilter() && !restoreViewState()) {
        applyDefaultView();
    }

    if (window.ErpStudentTable) {
        ErpStudentTable.init('invoiceTable');
    }
})();
