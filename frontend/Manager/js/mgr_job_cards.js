/**
 * Manager job cards list — overview cards, status filters, search, sort.
 */
(function () {
    'use strict';

    const table = document.getElementById('jobCardTable');
    const searchInput = document.getElementById('jcSearchInput');
    const sortFilter = document.getElementById('jcSortFilter');
    const noMatchRow = document.getElementById('jcNoMatchRow');
    const emptyRow = document.getElementById('jcEmptyRow');
    const dashCards = Array.from(document.querySelectorAll('.mgr-stat-clickable'));
    const storageKey = window.MGR_JC_VIEW_STORAGE_KEY || 'mgr_jc_view_v1';
    let activePeriod = null;
    let saveTimer = null;

    function getStatusFilters() {
        const group = document.querySelector('.qt-tabs[data-mgr-tab-group="status"]');
        if (!group) return ['all'];
        const selected = Array.from(group.querySelectorAll('.qt-tab.active'))
            .map(function (t) { return t.getAttribute('data-filter') || ''; })
            .filter(Boolean);
        return selected.length ? selected : ['all'];
    }

    function setStatusFilters(filters) {
        const group = document.querySelector('.qt-tabs[data-mgr-tab-group="status"]');
        if (!group) return;
        const list = (filters && filters.length) ? filters.slice() : ['all'];
        group.querySelectorAll('.qt-tab').forEach(function (t) { t.classList.remove('active'); });
        if (list.indexOf('all') !== -1 || !list.length) {
            const allTab = group.querySelector('.qt-tab[data-filter="all"]');
            if (allTab) allTab.classList.add('active');
            return;
        }
        list.forEach(function (filter) {
            const tab = group.querySelector('.qt-tab[data-filter="' + filter + '"]');
            if (tab) tab.classList.add('active');
        });
    }

    function toggleStatusTab(tab) {
        const group = tab.closest('.qt-tabs');
        if (!group) return;
        const filter = tab.getAttribute('data-filter') || '';
        if (filter === 'all') {
            setStatusFilters(['all']);
            return;
        }
        const allTab = group.querySelector('.qt-tab[data-filter="all"]');
        if (allTab) allTab.classList.remove('active');
        tab.classList.toggle('active');
        if (!group.querySelector('.qt-tab.active') && allTab) allTab.classList.add('active');
    }

    function statusFilterMatches(row, filters) {
        if (!filters || filters.indexOf('all') !== -1) return true;
        const status = row.getAttribute('data-status') || '';
        return filters.some(function (f) {
            if (f === 'open') {
                return ['completed', 'invoiced', 'paid'].indexOf(status) === -1
                    && ['in_progress', 'waiting_parts'].indexOf(status) === -1;
            }
            if (f === 'in_progress') return status === 'in_progress' || status === 'waiting_parts';
            if (f === 'complete') return ['completed', 'invoiced', 'paid'].indexOf(status) !== -1;
            return false;
        });
    }

    function periodMatches(row) {
        if (!activePeriod) return true;
        return (row.getAttribute('data-in-' + activePeriod) || '') === '1';
    }

    function getListTitle() {
        const parts = [];
        const group = document.querySelector('.qt-tabs[data-mgr-tab-group="status"]');
        if (group) {
            Array.from(group.querySelectorAll('.qt-tab.active')).forEach(function (tab) {
                const t = tab.getAttribute('data-title');
                if (t && (tab.getAttribute('data-filter') || '') !== 'all') parts.push(t);
            });
        }
        if (activePeriod) {
            const labels = { today: 'Created today', week: 'Created this week', month: 'Created this month' };
            parts.push(labels[activePeriod] || activePeriod);
        }
        if (parts.length) return parts.join(' · ');
        const activeCard = document.querySelector('.ops-folder-card.is-active');
        if (activeCard) return activeCard.getAttribute('data-mgr-list-title') || 'Job cards';
        return 'All job cards';
    }

    function updateTableHeading(visibleCount) {
        const titleEl = document.getElementById('jcTableTitle');
        const metaEl = document.getElementById('jcTableMeta');
        if (titleEl) titleEl.textContent = getListTitle();
        if (metaEl) {
            metaEl.textContent = visibleCount === 1 ? '1 Job card' : (visibleCount + ' Job cards');
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
            if (sortValue === 'oldest') return dateA - dateB;
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
        const statusFilters = getStatusFilters();
        const sortBy = sortFilter ? sortFilter.value : 'newest';

        sortRows(rows, sortBy, tbody);

        let visibleCount = 0;
        rows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            const show = text.includes(query)
                && statusFilterMatches(row, statusFilters)
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
            ErpStudentTable.sync('jobCardTable');
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
                const filter = dashCard.getAttribute('data-mgr-filter');
                if (period) dash = 'period:' + period;
                else if (filter) dash = 'filter:' + filter;
            }
            localStorage.setItem(storageKey, JSON.stringify({
                statusFilters: getStatusFilters(),
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
                } else if (state.dash.indexOf('filter:') === 0) {
                    card = document.querySelector('.ops-folder-card[data-mgr-filter="' + state.dash.slice(7) + '"]');
                }
                if (card) card.classList.add('is-active');
            }

            if (Array.isArray(state.statusFilters)) setStatusFilters(state.statusFilters);
            else setStatusFilters(['all']);

            applyFilters();
            return true;
        } catch (e) {
            return false;
        }
    }

    function activateFromDashboardCard(card) {
        if (!card) return;
        clearDashCardActive();
        card.classList.add('is-active');
        activePeriod = card.getAttribute('data-mgr-period') || null;
        const filter = card.getAttribute('data-mgr-filter');
        if (filter === 'all' || !filter) {
            setStatusFilters(['all']);
        } else {
            setStatusFilters([filter]);
        }
        applyFilters();
        const el = document.getElementById('jcJobCardsList');
        if (el) {
            requestAnimationFrame(function () {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }

    document.querySelectorAll('.qt-tabs[data-mgr-tab-group="status"] .qt-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            toggleStatusTab(tab);
            activePeriod = null;
            clearDashCardActive();
            applyFilters();
        });
    });

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

    if (!restoreViewState()) {
        setStatusFilters(['all']);
        applyFilters();
    }

    if (window.ErpStudentTable) {
        ErpStudentTable.init('jobCardTable');
    }
})();
