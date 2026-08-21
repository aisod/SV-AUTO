(function () {
    'use strict';

    var root = document.getElementById('jcQtConfigRoot');
    if (!root) return;

    var labourSections = [];
    var partsRows = [];

    function $(id) { return document.getElementById(id); }

    function uid() { return Math.random().toString(36).slice(2, 9); }

    function emptyRow() { return { _key: uid(), activity: '' }; }

    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function defaultSections() {
        var nt = parseFloat($('jcQcNormalTimeRate') && $('jcQcNormalTimeRate').value) || 560;
        var ph = parseFloat($('jcQcPublicHolidayRate') && $('jcQcPublicHolidayRate').value) || 1500;
        var ovEl = $('jcQcOvertimeRate');
        var ov = ovEl && ovEl.value !== '' ? parseFloat(ovEl.value) : null;
        return [
            { _key: uid(), section_type: 'normal_time', section_label: 'Normal Time', rate: nt, hours: '', rows: [emptyRow(), emptyRow(), emptyRow()] },
            { _key: uid(), section_type: 'overtime', section_label: 'Overtime', rate: ov, hours: '', rows: [emptyRow(), emptyRow(), emptyRow()] },
            { _key: uid(), section_type: 'public_holiday', section_label: 'Public Holiday', rate: ph, hours: '', rows: [emptyRow(), emptyRow()] }
        ];
    }

    var CALLOUT_TYPES = ['Kilometres', 'Call out fee', 'Consumables'];

    function getCalloutLabel() {
        return 'Call-out';
    }

    function isCalloutRow(row) {
        if (!row) return false;
        if (row.section_type === 'callout' || row.section_type === 'consumables') return true;
        var n = (row.callout_type || row.item_name || '').trim();
        return CALLOUT_TYPES.indexOf(n) >= 0 || n === 'Consumable';
    }

    function normalizePartRow(row) {
        if (!row) return row;
        var nameHint = (row.callout_type || row.item_name || '').trim();
        if (isCalloutRow(row)) {
            row.section_type = 'callout';
            row.section_label = getCalloutLabel();
            var savedType = (row.callout_type || '').trim();
            var savedName = (row.item_name || '').trim();
            if (CALLOUT_TYPES.indexOf(savedType) >= 0) {
                row.callout_type = savedType;
            } else if (CALLOUT_TYPES.indexOf(savedName) >= 0) {
                row.callout_type = savedName;
                savedName = '';
            } else if (savedName === 'Consumable') {
                row.callout_type = 'Consumables';
                savedName = '';
            } else {
                row.callout_type = CALLOUT_TYPES[0];
            }
            row.item_name = CALLOUT_TYPES.indexOf(savedName) >= 0 ? '' : savedName;
            return row;
        }
        row.section_type = 'parts_supply';
        if (!row.section_label) row.section_label = 'Parts Supply';
        return row;
    }

    function newCalloutRow(calloutType) {
        var type = (calloutType || '').trim();
        if (CALLOUT_TYPES.indexOf(type) < 0) {
            type = CALLOUT_TYPES[0];
        }
        return {
            _key: uid(),
            section_type: 'callout',
            section_label: getCalloutLabel(),
            callout_type: type,
            item_name: '',
            qty: '',
            unit_cost: ''
        };
    }

    function addCalloutRow(calloutType) {
        if ($('jcQcShowConsumables') && !$('jcQcShowConsumables').checked) {
            $('jcQcShowConsumables').checked = true;
        }
        partsRows.push(newCalloutRow(calloutType));
        renderPartsTable();
    }

    function addSupplyRow() {
        partsRows.push({ _key: uid(), section_type: 'parts_supply', section_label: 'Parts Supply', item_name: '', qty: '', unit_cost: '' });
        renderPartsTable();
    }

    function defaultParts() {
        return [
            { _key: uid(), section_type: 'parts_supply', section_label: 'Parts Supply', item_name: '', qty: '', unit_cost: '' },
            { _key: uid(), section_type: 'parts_supply', section_label: 'Parts Supply', item_name: '', qty: '', unit_cost: '' }
        ];
    }

    function syncCalloutSectionLabels() {
        var lbl = getCalloutLabel();
        partsRows.forEach(function (row) {
            if (isCalloutRow(row)) row.section_label = lbl;
        });
    }

    function buildCalloutTypeSelect(ri, selected) {
        var html = '<select class="jc-qt-config-callout-type" data-part-ri="' + ri + '" data-field="callout_type" title="Call out of town type">';
        CALLOUT_TYPES.forEach(function (opt) {
            html += '<option value="' + esc(opt) + '"' + (opt === selected ? ' selected' : '') + '>' + esc(opt) + '</option>';
        });
        return html + '</select>';
    }

    function countSupplyRows() {
        return partsRows.filter(function (row) { return !isCalloutRow(row); }).length;
    }

    function ensurePartsSupplyRows() {
        if (countSupplyRows() === 0) {
            partsRows.unshift({
                _key: uid(),
                section_type: 'parts_supply',
                section_label: 'Parts Supply',
                item_name: '',
                qty: '',
                unit_cost: ''
            });
        }
    }

    function removeCalloutSection() {
        partsRows = partsRows.filter(function (row) { return !isCalloutRow(row); });
        if ($('jcQcShowConsumables')) $('jcQcShowConsumables').checked = false;
        renderPartsTable();
    }

    function updateCalloutHint() {
        var hint = $('jcQcCalloutHint');
        if (!hint) return;
        var hasCallout = partsRows.some(isCalloutRow);
        hint.style.display = !hasCallout ? 'block' : 'none';
    }

    function computeSectionTotal(sec) {
        var h = parseFloat(sec.hours);
        var r = parseFloat(sec.rate);
        if (isNaN(h) || isNaN(r) || h === 0) return 0;
        return h * r;
    }

    function computeLabourGrandTotal() {
        return labourSections.reduce(function (sum, sec) {
            return sum + computeSectionTotal(sec);
        }, 0);
    }

    function computePartsGrandTotal() {
        var total = 0;
        partsRows.forEach(function (row) {
            var t = parseFloat(computePartsRowTotal(row));
            if (!isNaN(t)) total += t;
        });
        return total;
    }

    function updateQuotationSummary() {
        var sym = 'N$';
        var labour = computeLabourGrandTotal();
        var parts = computePartsGrandTotal();
        var subtotal = labour + parts;
        var vatInp = $('jcQcVatRate');
        var vatRate = vatInp ? (parseFloat(vatInp.value) || 0) : 0;
        var vatAmt = subtotal * (vatRate / 100);
        var grand = subtotal + vatAmt;

        var subEl = $('jcQcSummarySubtotal');
        var vatLabelEl = $('jcQcSummaryVatLabel');
        var vatEl = $('jcQcSummaryVat');
        var grandEl = $('jcQcSummaryGrand');
        if (subEl) subEl.textContent = sym + subtotal.toFixed(2);
        if (vatLabelEl) vatLabelEl.textContent = 'VAT (' + vatRate + '%)';
        if (vatEl) vatEl.textContent = sym + vatAmt.toFixed(2);
        if (grandEl) grandEl.textContent = sym + grand.toFixed(2);

        document.dispatchEvent(new CustomEvent('jc-quotation-totals', {
            detail: { subtotal: subtotal, vatRate: vatRate, vatAmt: vatAmt, grand: grand }
        }));
    }

    function updateLabourGrandTotal() {
        var el = $('jcQcLabourGrandTotal');
        if (!el) return;
        var t = computeLabourGrandTotal();
        el.textContent = t > 0 ? t.toFixed(2) : '0.00';
        updateQuotationSummary();
    }

    function updatePartsGrandTotal(total) {
        var el = $('jcQcPartsGrandTotal');
        if (!el) return;
        var t = typeof total === 'number' && !isNaN(total) ? total : computePartsGrandTotal();
        el.textContent = t > 0 ? t.toFixed(2) : '0.00';
        updateQuotationSummary();
    }

    function normalizeLabourSection(sec) {
        var rows = (sec.rows || []).map(function (row) {
            return {
                _key: uid(),
                activity: row.activity || '',
                hours: row.hours,
                rate_override: row.rate_override
            };
        });
        if (!rows.length) rows.push(emptyRow());
        var hours = sec.hours != null && sec.hours !== '' ? String(sec.hours) : '';
        if (hours === '') {
            var sum = 0;
            var found = false;
            rows.forEach(function (row) {
                var h = parseFloat(row.hours);
                if (!isNaN(h) && h > 0) {
                    sum += h;
                    found = true;
                }
            });
            if (found) hours = String(sum);
        }
        return {
            _key: uid(),
            section_type: sec.section_type || 'normal_time',
            section_label: sec.section_label || 'Section',
            rate: sec.rate != null && sec.rate !== '' ? parseFloat(sec.rate) : null,
            hours: hours,
            rows: rows.map(function (row) {
                return { _key: row._key, activity: row.activity };
            })
        };
    }

    function normalizeUnitCostExpr(expr) {
        return String(expr).replace(/(\d+(?:\.\d+)?)\s*%/g, function (_, n) {
            return '(' + n + '/100)';
        });
    }

    /** Parts Supply: replace QTY token with the Qty column value (e.g. =1752.79*30%/QTY). */
    function substituteQtyToken(expr, row) {
        if (!row || isCalloutRow(row)) return expr;
        var q = parseFloat(row.qty);
        if (!isFinite(q) || q <= 0) return expr;
        return String(expr).replace(/\bQTY\b/gi, String(q));
    }

    /** Excel-style unit cost for Parts Supply (=1752.79*30%/2, =1752.79*30%/QTY, etc.). */
    function evaluateUnitCostExpression(raw, row) {
        var s = String(raw == null ? '' : raw).trim();
        if (s === '') return { ok: true, value: '', display: '' };

        var expr = s;
        var hadEquals = false;
        if (expr.charAt(0) === '=') {
            hadEquals = true;
            expr = expr.slice(1).trim();
        }

        if (!hadEquals) {
            var plain = s.replace(/,/g, '').trim();
            if (/^-?\d+(\.\d+)?$/.test(plain)) {
                var n0 = parseFloat(plain);
                if (!isNaN(n0)) return { ok: true, value: n0, display: n0.toFixed(2) };
            }
            if (!/[*+\/()%-]/.test(plain) && !/\bQTY\b/i.test(plain)) {
                var loose = parseFloat(plain);
                if (!isNaN(loose)) return { ok: true, value: loose, display: loose.toFixed(2) };
                return { ok: false, value: '', display: s, partial: true };
            }
        }

        if (expr === '') return { ok: false, value: '', display: s, partial: true };
        expr = substituteQtyToken(expr, row);
        expr = normalizeUnitCostExpr(expr);
        if (!/^[\d\s+\-*/().]+$/.test(expr)) {
            return { ok: false, value: '', display: s, error: true };
        }

        try {
            var result = Function('"use strict"; return (' + expr + ')')();
            if (typeof result !== 'number' || !isFinite(result)) {
                return { ok: false, value: '', display: s, error: true };
            }
            return { ok: true, value: result, display: result.toFixed(2) };
        } catch (err) {
            return { ok: false, value: '', display: s, error: true };
        }
    }

    function resolveUnitCostNumber(raw, row) {
        var ev = evaluateUnitCostExpression(raw, row);
        if (ev.ok && ev.value !== '') return ev.value;
        return NaN;
    }

    function formatUnitCost(val, row) {
        var ev = evaluateUnitCostExpression(val, row);
        if (ev.ok && ev.value !== '') return ev.display;
        if (ev.ok && ev.value === '') return '';
        var s = String(val == null ? '' : val).trim();
        if (s === '') return '';
        var n = parseFloat(s.replace(/,/g, ''));
        if (isNaN(n)) return '';
        return n.toFixed(2);
    }

    function unitCostInputValue(row) {
        var raw = row.unit_cost;
        if (raw === null || raw === undefined) return '';
        var s = String(raw).trim();
        if (s === '') return '';
        if (s.charAt(0) === '=') return esc(s);
        var formatted = formatUnitCost(s, row);
        return formatted === '' ? esc(s) : esc(formatted);
    }

    /** Parts Supply: Unit cost = formula; Total = unit cost × Qty column. */
    function computePartsSupplyLineTotal(row) {
        var u = resolveUnitCostNumber(row.unit_cost, row);
        if (isNaN(u)) return '';
        var q = parseFloat(row.qty);
        if (!isFinite(q) || q <= 0) return u.toFixed(2);
        return (u * q).toFixed(2);
    }

    /** Call-out rows: Total = Qty × Unit cost. */
    function computeCalloutRowTotal(row) {
        var q = parseFloat(row.qty);
        var u = resolveUnitCostNumber(row.unit_cost);
        if (isNaN(q) || isNaN(u) || q === 0) return '';
        return (q * u).toFixed(2);
    }

    function computePartsRowTotal(row) {
        if (isCalloutRow(row)) return computeCalloutRowTotal(row);
        return computePartsSupplyLineTotal(row);
    }

    function recalcPartsTotals() {
        var partsTotalVal = 0;
        partsRows.forEach(function (row) {
            var t = parseFloat(computePartsRowTotal(row));
            if (!isNaN(t)) partsTotalVal += t;
        });
        updatePartsGrandTotal(partsTotalVal);
        syncToForm();
    }

    function renderLabourSections() {
        var container = $('jcQcLabourSectionsContainer');
        if (!container) return;
        container.innerHTML = '';

        labourSections.forEach(function (sec, si) {
            var div = document.createElement('div');
            div.className = 'jc-qt-config-section-block';
            var sectionTotal = computeSectionTotal(sec);
            var rowCount = Math.max(sec.rows.length, 1);
            var hoursVal = sec.hours != null && sec.hours !== '' ? sec.hours : '';
            var rateVal = sec.rate !== null && sec.rate !== '' ? sec.rate : '';

            div.innerHTML =
                '<div class="jc-qt-config-sec-controls">' +
                '<span class="jc-qt-config-sec-num">Category ' + (si + 1) + ':</span>' +
                '<label>Type:</label>' +
                '<select class="jc-qt-config-sec-type" data-si="' + si + '">' +
                '<option value="normal_time"' + (sec.section_type === 'normal_time' ? ' selected' : '') + '>Normal Time</option>' +
                '<option value="overtime"' + (sec.section_type === 'overtime' ? ' selected' : '') + '>Overtime</option>' +
                '<option value="public_holiday"' + (sec.section_type === 'public_holiday' ? ' selected' : '') + '>Public Holiday</option>' +
                '<option value="custom"' + (sec.section_type === 'custom' ? ' selected' : '') + '>Custom</option>' +
                '</select>' +
                '<label>Label:</label>' +
                '<input type="text" class="jc-qt-config-sec-label-inp" data-si="' + si + '" value="' + esc(sec.section_label) + '">' +
                '<button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-xs" data-jc-qc-add-row="' + si + '">+ Row</button>' +
                '<button type="button" class="jc-qt-config-btn jc-qt-config-btn-danger jc-qt-config-btn-xs" data-jc-qc-rm-sec="' + si + '">&times; Remove</button>' +
                '</div>' +
                '<div class="jc-qt-config-table-wrap"><table class="jc-qt-config-table"><thead><tr>' +
                '<th style="width:110px;" class="jc-qt-config-sec-th-' + si + '">' + esc(sec.section_label) + '</th>' +
                '<th class="jc-qt-config-svc-col-th">' + esc(getPrimaryHeaderLabel()) + '</th>' +
                '<th class="jc-qt-config-col-metric">Hour</th>' +
                '<th class="jc-qt-config-col-metric">Rate</th>' +
                '<th class="jc-qt-config-col-metric">Total</th>' +
                '<th style="width:50px;">Del</th>' +
                '</tr></thead><tbody class="jc-qt-config-labour-tbody">' +
                sec.rows.map(function (row, ri) {
                    var descCell = ri === 0
                        ? '<td class="jc-qt-config-sec-desc-cell" rowspan="' + rowCount + '">' + esc(sec.section_label) + '</td>'
                        : '';
                    var metricCells = ri === 0
                        ? '<td class="jc-qt-config-sec-metric" rowspan="' + rowCount + '">' +
                            '<input type="number" class="jc-qt-config-cell-inp" data-si="' + si + '" data-field="hours" value="' + esc(hoursVal) + '" min="0" step="0.5">' +
                            '</td>' +
                            '<td class="jc-qt-config-sec-metric jc-qt-config-sec-rate-col" rowspan="' + rowCount + '">' +
                            '<input type="number" class="jc-qt-config-cell-inp" data-si="' + si + '" data-field="rate" value="' + esc(rateVal) + '" min="0" step="0.01">' +
                            '</td>' +
                            '<td class="jc-qt-config-total jc-qt-config-sec-total-cell" rowspan="' + rowCount + '" id="jcQcSecTotal_' + si + '">' +
                            (sectionTotal > 0 ? sectionTotal.toFixed(2) : '') +
                            '</td>'
                        : '';
                    return '<tr>' +
                        descCell +
                        '<td class="jc-qt-config-activity-cell"><input type="text" class="jc-qt-config-cell-inp" data-si="' + si + '" data-ri="' + ri + '" data-field="activity" value="' + esc(row.activity) + '" placeholder="Activity"></td>' +
                        metricCells +
                        '<td style="text-align:center"><button type="button" class="jc-qt-config-btn jc-qt-config-btn-danger jc-qt-config-btn-xs" data-jc-qc-rm-row="' + si + '-' + ri + '">&times;</button></td>' +
                        '</tr>';
                }).join('') +
                '</tbody></table></div>';

            container.appendChild(div);

            var typeSel = div.querySelector('.jc-qt-config-sec-type');
            if (typeSel) typeSel.addEventListener('change', function () { updateSectionType(si, this.value); });
            var labelInp = div.querySelector('.jc-qt-config-sec-label-inp');
            if (labelInp) labelInp.addEventListener('input', function () {
                labourSections[si].section_label = this.value;
                var th = div.querySelector('.jc-qt-config-sec-th-' + si);
                if (th) th.textContent = this.value;
                var descCell = div.querySelector('.jc-qt-config-sec-desc-cell');
                if (descCell) descCell.textContent = this.value;
            });
        });

        updateLabourGrandTotal();
        syncToForm();
    }

    function recalcSection(si) {
        var sec = labourSections[si];
        if (!sec) return;
        var totalCell = $('jcQcSecTotal_' + si);
        var t = computeSectionTotal(sec);
        if (totalCell) totalCell.textContent = t > 0 ? t.toFixed(2) : '';
        updateLabourGrandTotal();
        syncToForm();
    }

    function updateSectionType(si, type) {
        var labelMap = { normal_time: 'Normal Time', overtime: 'Overtime', public_holiday: 'Public Holiday', custom: 'Custom Section' };
        labourSections[si].section_type = type;
        if (type === 'normal_time') {
            labourSections[si].rate = parseFloat($('jcQcNormalTimeRate').value) || 560;
        } else if (type === 'overtime') {
            var ov = $('jcQcOvertimeRate').value;
            labourSections[si].rate = ov !== '' ? parseFloat(ov) : null;
        } else if (type === 'public_holiday') {
            labourSections[si].rate = parseFloat($('jcQcPublicHolidayRate').value) || 1500;
        }
        labourSections[si].section_label = labelMap[type] || 'Custom Section';
        renderLabourSections();
    }

    function renderPartsTable() {
        var tbody = $('jcQcPartsTableBody');
        var showCallout = $('jcQcShowConsumables') && $('jcQcShowConsumables').checked;
        if (!tbody) return;
        ensurePartsSupplyRows();
        tbody.innerHTML = '';

        partsRows.forEach(function (row, ri) {
            partsRows[ri] = normalizePartRow(row);
        });

        var partsTotalVal = 0;
        var supplyCount = countSupplyRows();
        var supplyIdx = 0;

        partsRows.forEach(function (row, ri) {
            if (isCalloutRow(row)) return;
            var isFirstSupply = supplyIdx === 0;
            var labelTd = '';
            if (isFirstSupply) {
                var rs = supplyCount > 1 ? ' rowspan="' + supplyCount + '"' : '';
                labelTd = '<td class="jc-qt-config-row-label jc-qt-config-parts-supply-label"' + rs + '><strong>Parts Supply</strong></td>';
            }
            var tr = document.createElement('tr');
            tr.innerHTML =
                labelTd +
                '<td><input type="text" class="jc-qt-config-cell-inp" data-part-ri="' + ri + '" data-field="item_name" value="' + esc(row.item_name) + '" placeholder="Item name"></td>' +
                '<td class="jc-qt-config-col-metric"><input type="number" class="jc-qt-config-cell-inp" data-part-ri="' + ri + '" data-field="qty" value="' + esc(row.qty) + '" min="0" step="1" title="Qty — multiplied with Unit cost for Total"></td>' +
                '<td class="jc-qt-config-col-metric jc-qt-config-col-metric-rate"><input type="text" class="jc-qt-config-cell-inp jc-qt-config-unit-cost-inp jc-qt-config-unit-cost-inp--supply" data-part-ri="' + ri + '" data-field="unit_cost" value="' + unitCostInputValue(row) + '" inputmode="decimal" placeholder="=1752.79*30%/QTY" title="Unit cost formula. Total = Unit cost × Qty. Use QTY for Qty column, % for percent (e.g. =1752.79*30%/QTY or =1752.79*30%/2)." autocomplete="off" spellcheck="false"></td>' +
                '<td class="jc-qt-config-col-metric jc-qt-config-total jc-qt-config-part-total" data-part-ri="' + ri + '">' + computePartsRowTotal(row) + '</td>' +
                '<td style="text-align:center"><button type="button" class="jc-qt-config-btn jc-qt-config-btn-danger jc-qt-config-btn-xs" data-jc-qc-rm-part="' + ri + '" title="Remove line"' + (countSupplyRows() <= 1 ? ' disabled' : '') + '>&times;</button></td>';
            tbody.appendChild(tr);
            var t = parseFloat(computePartsRowTotal(row));
            if (!isNaN(t)) partsTotalVal += t;
            supplyIdx++;
        });

        if (showCallout) {
            var calloutHeader = document.createElement('tr');
            calloutHeader.className = 'jc-qt-config-callout-group-row';
            calloutHeader.innerHTML =
                '<td colspan="5" class="jc-qt-config-sec-label">' + esc(getCalloutLabel()) + '</td>' +
                '<td style="text-align:center">' +
                '<button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-xs" data-jc-qc-remove-callout title="Remove entire call-out section from quotation">Remove section</button>' +
                '</td>';
            tbody.appendChild(calloutHeader);

            partsRows.forEach(function (row, ri) {
                if (!isCalloutRow(row)) return;
                var trCall = document.createElement('tr');
                trCall.innerHTML =
                    '<td class="jc-qt-config-callout-cell">' + buildCalloutTypeSelect(ri, row.callout_type || CALLOUT_TYPES[0]) + '</td>' +
                    '<td><input type="text" class="jc-qt-config-cell-inp" data-part-ri="' + ri + '" data-field="item_name" value="' + esc(row.item_name) + '" placeholder="Description / notes"></td>' +
                    '<td class="jc-qt-config-col-metric"><input type="number" class="jc-qt-config-cell-inp" data-part-ri="' + ri + '" data-field="qty" value="' + esc(row.qty) + '" min="0" step="1"></td>' +
                    '<td class="jc-qt-config-col-metric jc-qt-config-col-metric-rate"><input type="text" class="jc-qt-config-cell-inp jc-qt-config-unit-cost-inp" data-part-ri="' + ri + '" data-field="unit_cost" value="' + unitCostInputValue(row) + '" inputmode="decimal" placeholder="0.00 or =345*2" title="Amount or formula (e.g. =345*2, =345*2/2)" autocomplete="off" spellcheck="false"></td>' +
                    '<td class="jc-qt-config-col-metric jc-qt-config-total jc-qt-config-part-total" data-part-ri="' + ri + '">' + computePartsRowTotal(row) + '</td>' +
                    '<td style="text-align:center"><button type="button" class="jc-qt-config-btn jc-qt-config-btn-danger jc-qt-config-btn-xs" data-jc-qc-rm-part="' + ri + '" title="Remove line">&times;</button></td>';
                tbody.appendChild(trCall);
                var tc = parseFloat(computePartsRowTotal(row));
                if (!isNaN(tc)) partsTotalVal += tc;
            });
        }

        updatePartsGrandTotal(partsTotalVal);
        updateCalloutHint();
        syncToForm();
    }

    function syncToForm() {
        syncLegacyLabelFields();
        var secEl = $('jcQcSectionsJson');
        var partsEl = $('jcQcPartsJson');
        if (secEl) {
            secEl.value = JSON.stringify(labourSections.map(function (sec) {
                return {
                    section_type: sec.section_type,
                    section_label: sec.section_label,
                    rate: sec.rate !== null && sec.rate !== '' ? sec.rate : '',
                    hours: sec.hours != null && sec.hours !== '' ? sec.hours : '',
                    rows: sec.rows.map(function (row) {
                        return { activity: row.activity };
                    })
                };
            }));
        }
        if (partsEl) {
            partsEl.value = JSON.stringify(partsRows.filter(function (row) {
                if (!row) return false;
                var name = (row.item_name || '').trim();
                var qty = parseFloat(row.qty);
                var cost = resolveUnitCostNumber(row.unit_cost, row);
                if (isCalloutRow(row)) {
                    var type = (row.callout_type || '').trim();
                    return type !== '' || name !== '' || (isFinite(qty) && qty > 0) || (isFinite(cost) && cost > 0);
                }
                return name !== '' || (isFinite(qty) && qty > 0) || (isFinite(cost) && cost > 0);
            }).map(function (row) {
                var lineTotal = computePartsRowTotal(row);
                var out = {
                    section_type: isCalloutRow(row) ? 'callout' : 'parts_supply',
                    section_label: row.section_label,
                    item_name: row.item_name,
                    qty: row.qty,
                    unit_cost: row.unit_cost
                };
                if (lineTotal !== '') out.total = parseFloat(lineTotal);
                if (isCalloutRow(row)) out.callout_type = row.callout_type || CALLOUT_TYPES[0];
                return out;
            }));
        }
    }

    function initFromPayload(payload) {
        if (!payload) return;
        var labelRows = labelsFromPayload(payload);
        renderGeneralLabelInputs(labelRows.length ? labelRows : ['']);
        if (payload.rate_normal != null && $('jcQcNormalTimeRate')) $('jcQcNormalTimeRate').value = payload.rate_normal;
        if (payload.rate_after != null && $('jcQcOvertimeRate')) $('jcQcOvertimeRate').value = payload.rate_after || '';
        if (payload.rate_holiday != null && $('jcQcPublicHolidayRate')) $('jcQcPublicHolidayRate').value = payload.rate_holiday;
        if (payload.vat_rate != null && $('jcQcVatRate')) $('jcQcVatRate').value = payload.vat_rate;
        if (payload.show_consumables !== undefined && $('jcQcShowConsumables')) {
            $('jcQcShowConsumables').checked = !!payload.show_consumables;
        }
        updatePreviewLabels();

        var sections = payload.quotation_labour_sections;
        if (typeof sections === 'string') {
            try { sections = JSON.parse(sections); } catch (e) { sections = []; }
        }
        if (Array.isArray(sections) && sections.length) {
            labourSections = sections.map(normalizeLabourSection);
        }

        var parts = payload.quotation_parts;
        if (typeof parts === 'string') {
            try { parts = JSON.parse(parts); } catch (e) { parts = []; }
        }
        if (Array.isArray(parts) && parts.length) {
            partsRows = parts.map(function (p) {
                var row = {
                    _key: uid(),
                    section_type: p.section_type || 'parts_supply',
                    section_label: p.section_label || 'Parts Supply',
                    callout_type: p.callout_type || '',
                    item_name: p.item_name || '',
                    qty: p.qty != null ? p.qty : '',
                    unit_cost: p.unit_cost != null ? p.unit_cost : ''
                };
                return normalizePartRow(row);
            });
        }

        partsRows = partsRows.map(normalizePartRow);
        syncCalloutSectionLabels();
        renderLabourSections();
        renderPartsTable();
    }

    function getGeneralHeaderLabels() {
        return Array.from(root.querySelectorAll('.jc-qt-config-general-label-inp'))
            .map(function (inp) { return inp.value.trim(); })
            .filter(function (text) { return text !== ''; });
    }

    function syncLegacyLabelFields() {
        var labels = getGeneralHeaderLabels();
        var legacySvc = $('jcQcLegacyServiceLabel');
        var legacyDiag = $('jcQcLegacyDiagnosticLabel');
        if (legacySvc) legacySvc.value = labels[0] || '';
        if (legacyDiag) legacyDiag.value = labels[1] || '';
    }

    function getPrimaryHeaderLabel() {
        var labels = getGeneralHeaderLabels();
        return labels.length ? labels[0] : '';
    }

    function renderGeneralLabelInputs(labels) {
        var list = $('jcQcGeneralLabelsList');
        if (!list) return;
        var rows = Array.isArray(labels) && labels.length ? labels.slice() : [''];
        list.innerHTML = '';
        rows.forEach(function (text) {
            var row = document.createElement('div');
            row.className = 'jc-qt-config-general-label-row';
            row.innerHTML =
                '<input type="text" class="jc-qt-config-general-label-inp aq-ic" name="general_header_labels[]" value="' + esc(text) + '" placeholder="e.g. Attend to service, Diagnostic, Connect diagnose and quick-test" autocomplete="off">' +
                '<button type="button" class="jc-qt-config-btn jc-qt-config-btn-danger jc-qt-config-btn-xs" data-jc-qc-rm-general-label title="Remove label">&times;</button>';
            list.appendChild(row);
        });
        updatePreviewLabels();
        syncLegacyLabelFields();
    }

    function addGeneralLabelRow(value) {
        var list = $('jcQcGeneralLabelsList');
        if (!list) return;
        var row = document.createElement('div');
        row.className = 'jc-qt-config-general-label-row';
        row.innerHTML =
            '<input type="text" class="jc-qt-config-general-label-inp aq-ic" name="general_header_labels[]" value="' + esc(value || '') + '" placeholder="e.g. Attend to service, Diagnostic, Connect diagnose and quick-test" autocomplete="off">' +
            '<button type="button" class="jc-qt-config-btn jc-qt-config-btn-danger jc-qt-config-btn-xs" data-jc-qc-rm-general-label title="Remove label">&times;</button>';
        list.appendChild(row);
        var inp = row.querySelector('input');
        if (inp) inp.focus();
        updatePreviewLabels();
        syncLegacyLabelFields();
    }

    function labelsFromPayload(payload) {
        if (!payload) return [];
        if (Array.isArray(payload.general_header_labels) && payload.general_header_labels.length) {
            return payload.general_header_labels.map(function (x) { return String(x || '').trim(); }).filter(Boolean);
        }
        var out = [];
        var a = String(payload.attend_to_service_label || '').trim();
        var d = String(payload.diagnostic_label || '').trim();
        if (a) out.push(a);
        if (d) out.push(d);
        return out;
    }

    function updatePreviewLabels() {
        var labels = getGeneralHeaderLabels();
        var thead = $('jcQcPreviewLabelsHead');
        if (thead) {
            thead.innerHTML = '';
            if (!labels.length) {
                labels = [''];
            }
            labels.forEach(function (text, idx) {
                var tr = document.createElement('tr');
                tr.className = idx === 0 ? 'jc-qt-config-preview-hdr-main' : 'jc-qt-config-preview-hdr-diag';
                if (idx === 0) {
                    tr.innerHTML =
                        '<th style="width:110px;">Description</th>' +
                        '<th class="jc-qt-config-preview-band" id="jcQcPreviewPrimaryLabel">' + esc(text) + '</th>' +
                        '<th class="jc-qt-config-col-metric">Hour</th>' +
                        '<th class="jc-qt-config-col-metric">Rate</th>' +
                        '<th class="jc-qt-config-col-metric">Total</th>';
                } else {
                    tr.innerHTML =
                        '<th class="jc-qt-config-preview-hdr-spacer">&nbsp;</th>' +
                        '<th class="jc-qt-config-preview-band">' + esc(text) + '</th>' +
                        '<th class="jc-qt-config-col-metric"></th>' +
                        '<th class="jc-qt-config-col-metric"></th>' +
                        '<th class="jc-qt-config-col-metric"></th>';
                }
                thead.appendChild(tr);
            });
        }
        var primary = labels.length ? labels[0] : '';
        root.querySelectorAll('.jc-qt-config-svc-col-th').forEach(function (th) {
            th.textContent = primary;
        });
        var previewWrap = root.querySelector('.jc-qt-config-preview-head');
        if (previewWrap) {
            previewWrap.classList.toggle('jc-qt-config-preview-head--dual', labels.length >= 2);
        }
        syncLegacyLabelFields();
    }

    function resetForm() {
        if (!confirm('Reset quotation configuration to defaults? Unsaved changes will be lost.')) return;
        renderGeneralLabelInputs(['']);
        if ($('jcQcNormalTimeRate')) $('jcQcNormalTimeRate').value = '560';
        if ($('jcQcOvertimeRate')) $('jcQcOvertimeRate').value = '';
        if ($('jcQcPublicHolidayRate')) $('jcQcPublicHolidayRate').value = '1500';
        if ($('jcQcVatRate')) $('jcQcVatRate').value = '15';
        if ($('jcQcShowConsumables')) $('jcQcShowConsumables').checked = false;
        labourSections = defaultSections();
        partsRows = defaultParts();
        updatePreviewLabels();
        renderLabourSections();
        renderPartsTable();
    }

    root.addEventListener('input', function (e) {
        var t = e.target;
        if (!t) return;
        if (t.classList && t.classList.contains('jc-qt-config-general-label-inp')) updatePreviewLabels();
        if (t.id === 'jcQcVatRate') updateQuotationSummary();
        var si = t.getAttribute('data-si');
        var ri = t.getAttribute('data-ri');
        var field = t.getAttribute('data-field');
        if (si !== null && field && labourSections[si]) {
            var siNum = parseInt(si, 10);
            if (ri !== null && labourSections[siNum].rows[ri]) {
                labourSections[siNum].rows[ri][field] = t.value;
                syncToForm();
            } else if (ri === null) {
                if (field === 'hours') labourSections[siNum].hours = t.value;
                else if (field === 'rate') labourSections[siNum].rate = t.value === '' ? null : parseFloat(t.value);
                if (field === 'hours' || field === 'rate') recalcSection(siNum);
                else syncToForm();
            }
        }
        var pri = t.getAttribute('data-part-ri');
        if (pri !== null && field && partsRows[pri]) {
            partsRows[pri][field] = t.value;
            if (field === 'qty' || field === 'unit_cost') {
                if (field === 'unit_cost') {
                    t.classList.remove('jc-qt-config-unit-cost-error');
                }
                var cellPart = document.querySelector('.jc-qt-config-part-total[data-part-ri="' + pri + '"]');
                if (cellPart) cellPart.textContent = computePartsRowTotal(partsRows[pri]);
                recalcPartsTotals();
            } else syncToForm();
        }
    });

    root.addEventListener('keydown', function (e) {
        var t = e.target;
        if (!t || t.getAttribute('data-field') !== 'unit_cost') return;
        if (e.key !== 'Enter') return;
        e.preventDefault();
        t.blur();
    });

    root.addEventListener('blur', function (e) {
        var t = e.target;
        if (!t || t.getAttribute('data-field') !== 'unit_cost') return;
        var pri = t.getAttribute('data-part-ri');
        if (pri === null || !partsRows[pri]) return;
        var ev = evaluateUnitCostExpression(t.value, partsRows[pri]);
        var stored = '';
        var display = '';
        if (ev.ok && ev.value !== '') {
            stored = ev.display;
            display = ev.display;
        } else if (ev.partial && String(t.value).trim() !== '') {
            stored = String(t.value).trim();
            display = stored;
        } else {
            stored = '';
            display = '';
        }
        partsRows[pri].unit_cost = stored;
        t.value = display;
        t.classList.toggle('jc-qt-config-unit-cost-error', !!(ev.error && String(t.value).trim() !== ''));
        var cell = document.querySelector('.jc-qt-config-part-total[data-part-ri="' + pri + '"]');
        if (cell) cell.textContent = computePartsRowTotal(partsRows[pri]);
        recalcPartsTotals();
    }, true);

    root.addEventListener('change', function (e) {
        var t = e.target;
        if (t.id === 'jcQcShowConsumables') {
            if (!t.checked) {
                partsRows = partsRows.filter(function (row) { return !isCalloutRow(row); });
            }
            renderPartsTable();
            return;
        }
        var pri = t.getAttribute('data-part-ri');
        var field = t.getAttribute('data-field');
        if (pri !== null && field === 'callout_type' && partsRows[pri]) {
            partsRows[pri].callout_type = t.value;
            syncToForm();
        }
    });

    root.addEventListener('click', function (e) {
        var addSec = e.target.closest('#jcQcAddSection');
        if (addSec) {
            var ntAdd = parseFloat($('jcQcNormalTimeRate') && $('jcQcNormalTimeRate').value) || 560;
            labourSections.push({
                _key: uid(),
                section_type: 'normal_time',
                section_label: 'Normal Time',
                rate: ntAdd,
                hours: '',
                rows: [emptyRow(), emptyRow(), emptyRow()]
            });
            renderLabourSections();
            return;
        }
        var rmSec = e.target.closest('[data-jc-qc-rm-sec]');
        if (rmSec) {
            var si = parseInt(rmSec.getAttribute('data-jc-qc-rm-sec'), 10);
            if (labourSections.length <= 1) return;
            labourSections.splice(si, 1);
            renderLabourSections();
            return;
        }
        var addRow = e.target.closest('[data-jc-qc-add-row]');
        if (addRow) {
            var si2 = parseInt(addRow.getAttribute('data-jc-qc-add-row'), 10);
            labourSections[si2].rows.push(emptyRow());
            renderLabourSections();
            return;
        }
        var rmRow = e.target.closest('[data-jc-qc-rm-row]');
        if (rmRow) {
            var parts = rmRow.getAttribute('data-jc-qc-rm-row').split('-');
            var s = parseInt(parts[0], 10);
            var r = parseInt(parts[1], 10);
            if (labourSections[s].rows.length <= 1) return;
            labourSections[s].rows.splice(r, 1);
            renderLabourSections();
            return;
        }
        var addCallout = e.target.closest('[data-jc-qc-add-callout]');
        if (addCallout) {
            addCalloutRow(addCallout.getAttribute('data-jc-qc-add-callout'));
            return;
        }
        var addSupply = e.target.closest('[data-jc-qc-add-supply]');
        if (addSupply) {
            addSupplyRow();
            return;
        }
        var rmCalloutSec = e.target.closest('[data-jc-qc-remove-callout]');
        if (rmCalloutSec) {
            removeCalloutSection();
            return;
        }
        var rmPart = e.target.closest('[data-jc-qc-rm-part]');
        if (rmPart) {
            if (rmPart.disabled) return;
            var ri = parseInt(rmPart.getAttribute('data-jc-qc-rm-part'), 10);
            var row = partsRows[ri];
            if (!row) return;
            if (isCalloutRow(row)) {
                partsRows.splice(ri, 1);
            } else if (countSupplyRows() > 1) {
                partsRows.splice(ri, 1);
            }
            renderPartsTable();
            return;
        }
        if (e.target.closest('#jcQcResetBtn')) resetForm();
        if (e.target.closest('#jcQcAddGeneralLabel')) {
            addGeneralLabelRow('');
            return;
        }
        var rmLabel = e.target.closest('[data-jc-qc-rm-general-label]');
        if (rmLabel) {
            var row = rmLabel.closest('.jc-qt-config-general-label-row');
            var list = $('jcQcGeneralLabelsList');
            if (row && list) {
                if (list.querySelectorAll('.jc-qt-config-general-label-row').length > 1) {
                    row.remove();
                } else {
                    var inp = row.querySelector('.jc-qt-config-general-label-inp');
                    if (inp) inp.value = '';
                }
                updatePreviewLabels();
                syncLegacyLabelFields();
            }
            return;
        }
    });

    var form = document.getElementById('jobCardForm');
    if (form) {
        form.addEventListener('submit', function () { syncToForm(); });
    }

    partsRows = defaultParts().map(normalizePartRow);
    labourSections = defaultSections();
    renderLabourSections();
    renderPartsTable();
    updateCalloutHint();
    updatePreviewLabels();
    updateQuotationSummary();

    window.jcQcInit = initFromPayload;
    window.jcQcSyncToForm = syncToForm;
    window.jcQcGetQuotationSubtotal = function () {
        return computeLabourGrandTotal() + computePartsGrandTotal();
    };

    if (window.__jc_quot_config) initFromPayload(window.__jc_quot_config);
})();
