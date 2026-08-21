/**
 * Client-side pagination for .erp-student-table list pages.
 * Call ErpStudentTable.sync(tableId) after filter/search updates.
 */
(function (global) {
    'use strict';

    const stateByTable = new Map();

    function getTable(id) {
        if (!id) return null;
        return document.getElementById(id);
    }

    function getConfig(table) {
        const id = table.id;
        if (!stateByTable.has(id)) {
            const footer = document.querySelector('[data-st-footer-for="' + id + '"]');
            const perPage = footer ? parseInt(footer.getAttribute('data-st-per-page') || '10', 10) : 10;
            stateByTable.set(id, {
                page: 1,
                perPage: perPage > 0 ? perPage : 10,
                footer: footer,
            });
        }
        return stateByTable.get(id);
    }

    function getDataRows(table) {
        const sel = table.getAttribute('data-st-row-selector');
        if (!sel) return [];
        return Array.from(table.querySelectorAll('tbody ' + sel));
    }

    function isFilterVisible(row) {
        return row.style.display !== 'none';
    }

    function getFilterVisibleRows(table) {
        return getDataRows(table).filter(isFilterVisible);
    }

    function buildPageList(current, total) {
        if (total <= 7) {
            const pages = [];
            for (let i = 1; i <= total; i++) pages.push(i);
            return pages;
        }
        const list = [1];
        if (current > 3) list.push('…');
        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);
        for (let i = start; i <= end; i++) {
            if (!list.includes(i)) list.push(i);
        }
        if (current < total - 2) list.push('…');
        if (!list.includes(total)) list.push(total);
        return list;
    }

    function renderPagination(cfg, totalPages) {
        const nav = cfg.footer && cfg.footer.querySelector('[data-st-pagination]');
        if (!nav) return;
        nav.innerHTML = '';
        const page = cfg.page;

        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'st-page-btn';
        prev.innerHTML = '<i class="fas fa-chevron-left" aria-hidden="true"></i>';
        prev.setAttribute('aria-label', 'Previous page');
        prev.disabled = page <= 1;
        prev.addEventListener('click', function () {
            cfg.page = Math.max(1, page - 1);
            syncTable(cfg.footer.getAttribute('data-st-footer-for'));
        });
        nav.appendChild(prev);

        buildPageList(page, totalPages).forEach(function (p) {
            if (p === '…') {
                const ell = document.createElement('span');
                ell.className = 'st-page-btn st-page-ellipsis';
                ell.textContent = '…';
                ell.setAttribute('aria-hidden', 'true');
                nav.appendChild(ell);
                return;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'st-page-btn' + (p === page ? ' active' : '');
            btn.textContent = String(p);
            btn.setAttribute('aria-label', 'Page ' + p);
            if (p === page) btn.setAttribute('aria-current', 'page');
            btn.addEventListener('click', function () {
                cfg.page = p;
                syncTable(cfg.footer.getAttribute('data-st-footer-for'));
            });
            nav.appendChild(btn);
        });

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'st-page-btn';
        next.innerHTML = '<i class="fas fa-chevron-right" aria-hidden="true"></i>';
        next.setAttribute('aria-label', 'Next page');
        next.disabled = page >= totalPages;
        next.addEventListener('click', function () {
            cfg.page = Math.min(totalPages, page + 1);
            syncTable(cfg.footer.getAttribute('data-st-footer-for'));
        });
        nav.appendChild(next);
    }

    function updateRange(cfg, visibleCount) {
        const rangeEl = cfg.footer && cfg.footer.querySelector('[data-st-range]');
        if (!rangeEl) return;
        if (visibleCount === 0) {
            rangeEl.textContent = '0 of 0';
            return;
        }
        const totalPages = Math.max(1, Math.ceil(visibleCount / cfg.perPage));
        if (cfg.page > totalPages) cfg.page = totalPages;
        const start = (cfg.page - 1) * cfg.perPage + 1;
        const end = Math.min(cfg.page * cfg.perPage, visibleCount);
        rangeEl.textContent = start + ' – ' + end + ' of ' + visibleCount;
    }

    function isPaginateDisabled(table) {
        return table && table.getAttribute('data-st-disable-paginate') === '1';
    }

    function cellSortValue(cell) {
        if (!cell) return '';
        if (cell.hasAttribute('data-st-sort-value')) {
            return cell.getAttribute('data-st-sort-value') || '';
        }
        return (cell.textContent || '').trim();
    }

    function syncTable(tableId) {
        const table = getTable(tableId);
        if (!table) return;
        const cfg = getConfig(table);
        const allRows = getDataRows(table);
        const visible = getFilterVisibleRows(table);

        if (isPaginateDisabled(table)) {
            allRows.forEach(function (row) {
                row.classList.remove('st-page-hidden');
            });
            return;
        }

        const total = visible.length;
        const totalPages = Math.max(1, Math.ceil(total / cfg.perPage) || 1);

        if (cfg.page > totalPages) cfg.page = totalPages;
        if (cfg.page < 1) cfg.page = 1;

        const startIdx = (cfg.page - 1) * cfg.perPage;
        const endIdx = startIdx + cfg.perPage;

        allRows.forEach(function (row) {
            row.classList.remove('st-page-hidden');
        });

        visible.forEach(function (row, idx) {
            if (idx < startIdx || idx >= endIdx) {
                row.classList.add('st-page-hidden');
            }
        });

        updateRange(cfg, total);
        renderPagination(cfg, totalPages);
    }

    function bindFooter(tableId) {
        const table = getTable(tableId);
        if (!table) return;
        const cfg = getConfig(table);
        if (!cfg.footer || cfg.footer._stBound) return;
        cfg.footer._stBound = true;

        const select = cfg.footer.querySelector('[data-st-rows-per-page]');
        if (select) {
            select.value = String(cfg.perPage);
            select.addEventListener('change', function () {
                cfg.perPage = parseInt(select.value, 10) || 10;
                cfg.page = 1;
                syncTable(tableId);
            });
        }
    }

    function initSortHeaders(table) {
        if (!table || table._stSortBound) return;
        table._stSortBound = true;
        const headers = table.querySelectorAll('thead th.sortable');
        headers.forEach(function (header) {
            header.addEventListener('click', function () {
                const idx = Array.from(header.parentNode.children).indexOf(header);
                const isAsc = header.classList.contains('sort-asc');
                headers.forEach(function (h) {
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                const dir = isAsc ? 'desc' : 'asc';
                header.classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');
                sortTableRows(table, idx, dir);
                syncTable(table.id);
            });
        });
    }

    function sortTableRows(table, colIndex, direction) {
        const tbody = table.querySelector('tbody');
        if (!tbody) return;
        const rows = getDataRows(table);
        rows.sort(function (a, b) {
            const aCell = a.cells[colIndex];
            const bCell = b.cells[colIndex];
            const aVal = cellSortValue(aCell);
            const bVal = cellSortValue(bCell);
            const aNum = parseFloat(aVal.replace(/[^0-9.-]/g, ''));
            const bNum = parseFloat(bVal.replace(/[^0-9.-]/g, ''));
            if (!isNaN(aNum) && !isNaN(bNum) && aVal !== '' && bVal !== '') {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            }
            return direction === 'asc'
                ? aVal.localeCompare(bVal)
                : bVal.localeCompare(aVal);
        });
        rows.forEach(function (row) {
            tbody.appendChild(row);
        });
    }

    const ErpStudentTable = {
        init: function (tableId, options) {
            const table = getTable(tableId);
            if (!table) return;
            if (options && options.perPage) {
                const cfg = getConfig(table);
                cfg.perPage = options.perPage;
                const select = cfg.footer && cfg.footer.querySelector('[data-st-rows-per-page]');
                if (select) select.value = String(options.perPage);
            }
            if (options && options.rowSelector) {
                table.setAttribute('data-st-row-selector', options.rowSelector);
            }
            bindFooter(tableId);
            initSortHeaders(table);
            syncTable(tableId);
        },
        sync: syncTable,
        resetPage: function (tableId) {
            const table = getTable(tableId);
            if (!table) return;
            getConfig(table).page = 1;
            syncTable(tableId);
        },
    };

    global.ErpStudentTable = ErpStudentTable;

    function bootStudentTables() {
        document.querySelectorAll('table.erp-student-table[id]').forEach(function (table) {
            const id = table.id;
            const selector = table.getAttribute('data-st-row-selector') || '';
            const opts = { perPage: 10 };
            if (selector) {
                opts.rowSelector = selector;
            }
            ErpStudentTable.init(id, opts);
        });
        document.dispatchEvent(new CustomEvent('erp-student-tables-ready'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootStudentTables);
    } else {
        bootStudentTables();
    }
})(typeof window !== 'undefined' ? window : this);
