<?php
/**
 * Quotation table configuration panel (embedded in add_job_card.php).
 * Expects optional $jc_cfg prefill array keys from extra_data.
 */
$jc_cfg = isset($jc_cfg) && is_array($jc_cfg) ? $jc_cfg : [];
$jc_general_labels = jc_general_header_labels_from_extra($jc_cfg);
if ($jc_general_labels === []) {
    $jc_general_labels = [''];
}
$jc_rate_normal = (string)($jc_cfg['rate_normal'] ?? $jc_cfg['normal_time_rate'] ?? '560');
$jc_rate_ot = (string)($jc_cfg['rate_after'] ?? $jc_cfg['overtime_rate'] ?? '');
$jc_rate_hol = (string)($jc_cfg['rate_holiday'] ?? $jc_cfg['public_holiday_rate'] ?? '1500');
$jc_vat_rate = (string)($jc_cfg['vat_rate'] ?? '15');
$jc_show_cons = !empty($jc_cfg['show_consumables']);
$jc_preview_multi = count(array_filter($jc_general_labels, static fn($l) => trim((string) $l) !== '')) >= 2;
?>
<div class="jc-qt-config no-print" id="jcQtConfigRoot">
    <style>
    #jcQtConfigRoot #jcQcPartsTable .jc-qt-config-parts-supply-label{
        text-align:center !important;
        vertical-align:middle;
    }
    #jcQtConfigRoot .jc-qt-config-general-labels-list{
        display:flex;
        flex-direction:column;
        gap:0.5rem;
    }
    #jcQtConfigRoot .jc-qt-config-general-label-row{
        display:flex;
        gap:0.5rem;
        align-items:center;
    }
    #jcQtConfigRoot .jc-qt-config-general-label-row .aq-ic,
    #jcQtConfigRoot .jc-qt-config-general-label-row input[type="text"]{
        flex:1;
    }
    </style>
    <input type="hidden" name="quotation_labour_sections" id="jcQcSectionsJson" value="">
    <input type="hidden" name="quotation_parts" id="jcQcPartsJson" value="">
    <input type="hidden" name="attend_to_service_label" id="jcQcLegacyServiceLabel" value="">
    <input type="hidden" name="diagnostic_label" id="jcQcLegacyDiagnosticLabel" value="">

    <div class="jc-qt-config-head">
        <h2>Quotation Table Configuration</h2>
        <p>Configure labour and material lines for quotations created from this job card.</p>
    </div>

    <div class="jc-qt-config-card">
        <div class="jc-qt-config-card-header">
            <span>Description column labels</span>
            <button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-sm" id="jcQcAddGeneralLabel">+ Add description label</button>
        </div>
        <div class="jc-qt-config-card-body">
            <p class="jc-qt-config-hint" style="margin-bottom:0.75rem;">
                Custom text for the <strong>description column headers</strong> on the job card and quotation labour table
                (not labour categories such as Normal Time / Overtime). Add as many lines as you need.
            </p>
            <div class="jc-qt-config-general-labels-list" id="jcQcGeneralLabelsList">
                <?php foreach ($jc_general_labels as $jc_label): ?>
                <div class="jc-qt-config-general-label-row">
                    <input type="text"
                           class="jc-qt-config-general-label-inp aq-ic"
                           name="general_header_labels[]"
                           value="<?= htmlspecialchars(trim((string) $jc_label), ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="e.g. Attend to service, Diagnostic, Connect diagnose and quick-test"
                           autocomplete="off">
                    <button type="button" class="jc-qt-config-btn jc-qt-config-btn-danger jc-qt-config-btn-xs" data-jc-qc-rm-general-label title="Remove label">&times;</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="jc-qt-config-card">
        <div class="jc-qt-config-card-header">Default labour section rates</div>
        <div class="jc-qt-config-card-body">
            <div class="jc-qt-config-form-row">
                <div class="jc-qt-config-form-group">
                    <label for="jcQcNormalTimeRate">Normal time rate (/hr)</label>
                    <input type="number" id="jcQcNormalTimeRate" name="rate_normal"
                           value="<?= htmlspecialchars($jc_rate_normal, ENT_QUOTES, 'UTF-8') ?>"
                           min="0" step="0.01" autocomplete="off">
                </div>
                <div class="jc-qt-config-form-group">
                    <label for="jcQcOvertimeRate">Overtime rate (/hr)</label>
                    <input type="number" id="jcQcOvertimeRate" name="rate_after"
                           value="<?= htmlspecialchars($jc_rate_ot, ENT_QUOTES, 'UTF-8') ?>"
                           min="0" step="0.01" placeholder="Leave blank if unknown" autocomplete="off">
                </div>
                <div class="jc-qt-config-form-group">
                    <label for="jcQcPublicHolidayRate">Public holiday rate (/hr)</label>
                    <input type="number" id="jcQcPublicHolidayRate" name="rate_holiday"
                           value="<?= htmlspecialchars($jc_rate_hol, ENT_QUOTES, 'UTF-8') ?>"
                           min="0" step="0.01" autocomplete="off">
                </div>
                <div class="jc-qt-config-form-group">
                    <label for="jcQcVatRate">VAT (%)</label>
                    <input type="number" id="jcQcVatRate" name="vat_rate"
                           value="<?= htmlspecialchars($jc_vat_rate, ENT_QUOTES, 'UTF-8') ?>"
                           min="0" max="100" step="0.01" autocomplete="off">
                </div>
            </div>
            <p class="jc-qt-config-hint">Default hourly rates and VAT apply to the quotation summary below.</p>
        </div>
    </div>

    <div class="jc-qt-config-card">
        <div class="jc-qt-config-card-header">
            <span>Labour &amp; service lines</span>
            <button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-sm" id="jcQcAddSection">+ Add labour category</button>
        </div>
        <div class="jc-qt-config-card-body jc-qt-config-card-body--tight">
            <div class="jc-qt-config-table-wrap jc-qt-config-preview-head<?= $jc_preview_multi ? ' jc-qt-config-preview-head--dual' : '' ?>"><table class="jc-qt-config-table">
                <thead id="jcQcPreviewLabelsHead">
                    <tr class="jc-qt-config-preview-hdr-main">
                        <th style="width:110px;">Description</th>
                        <th class="jc-qt-config-preview-band" id="jcQcPreviewPrimaryLabel"><?= htmlspecialchars($jc_general_labels[0] ?? '', ENT_QUOTES, 'UTF-8') ?></th>
                        <th class="jc-qt-config-col-metric">Hour</th>
                        <th class="jc-qt-config-col-metric">Rate</th>
                        <th class="jc-qt-config-col-metric">Total</th>
                    </tr>
                </thead>
            </table></div>
            <div id="jcQcLabourSectionsContainer" class="jc-qt-config-labour-sections"></div>
            <div class="jc-qt-config-total-row">
                <div class="jc-qt-config-card-total" id="jcQcLabourGrandTotalWrap">
                    <span>Total labour &amp; service</span>
                    <strong id="jcQcLabourGrandTotal">0.00</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="jc-qt-config-card">
        <div class="jc-qt-config-card-header">
            <span>Parts &amp; materials lines</span>
            <div class="jc-qt-config-section-actions">
                <button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-sm" data-jc-qc-add-supply>+ Parts Supply row</button>
                <span class="jc-qt-config-callout-add-group" role="group" aria-label="Add call-out of town rows" style="display:inline-flex;gap:0.35rem;flex-wrap:wrap">
                    <button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-sm" data-jc-qc-add-callout="Kilometres">+ Kilometres</button>
                    <button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-sm" data-jc-qc-add-callout="Call out fee">+ Call out fee</button>
                    <button type="button" class="jc-qt-config-btn jc-qt-config-btn-light jc-qt-config-btn-sm" data-jc-qc-add-callout="Consumables">+ Consumables</button>
                </span>
            </div>
        </div>
        <div class="jc-qt-config-card-body jc-qt-config-card-body--tight">
            <p class="jc-qt-config-hint jc-qt-config-parts-note">
                <strong>Parts Supply</strong> is always included on the quotation. Use <strong>+ Parts Supply row</strong> to add more parts lines.
                Optional call-out of town: use <strong>+ Kilometres</strong>, <strong>+ Call out fee</strong>, or <strong>+ Consumables</strong> to add as many rows as you need (each type can be added more than once).
            </p>
            <div class="jc-qt-config-toggle-row jc-qt-config-callout-toggle">
                <input type="checkbox" id="jcQcShowConsumables" name="show_consumables" value="1"<?= $jc_show_cons ? ' checked' : '' ?>>
                <label for="jcQcShowConsumables">Include <strong>call-out of town</strong> section on quotation</label>
            </div>
            <input type="hidden" name="consumables_label" value="Call-out">
            <div class="jc-qt-config-table-wrap"><table class="jc-qt-config-table" id="jcQcPartsTable">
                <thead>
                    <tr>
                        <th style="width:170px;">Line type</th>
                        <th>Item name / description</th>
                        <th class="jc-qt-config-col-metric">Qty</th>
                        <th class="jc-qt-config-col-metric">Unit cost</th>
                        <th class="jc-qt-config-col-metric">Total</th>
                        <th style="width:50px;">Del</th>
                    </tr>
                </thead>
                <tbody id="jcQcPartsTableBody"></tbody>
            </table></div>
            <div class="jc-qt-config-total-row">
                <div class="jc-qt-config-card-total" id="jcQcPartsGrandTotalWrap">
                    <span>Total parts &amp; materials</span>
                    <strong id="jcQcPartsGrandTotal">0.00</strong>
                </div>
            </div>
            <p class="jc-qt-config-hint" id="jcQcCalloutHint" style="margin-top:8px;">
                Use <strong>+ Kilometres</strong>, <strong>+ Call out fee</strong>, or <strong>+ Consumables</strong> to add rows (you can add multiple of the same type). Change the line type in the dropdown if needed. Tick the checkbox above to include this block on the quotation. Use <strong>Remove section</strong> on the call-out header to clear all call-out rows.
            </p>
        </div>
    </div>

    <div class="jc-qt-config-card">
        <div class="jc-qt-config-card-header">Quotation summary</div>
        <div class="jc-qt-config-card-body">
            <div class="jc-qt-config-summary-box">
                <h3 class="jc-qt-config-summary-title">Summary</h3>
                <div class="jc-qt-config-summary-lines">
                    <div class="jc-qt-config-summary-line">
                        <span>Subtotal</span>
                        <span id="jcQcSummarySubtotal">N$0.00</span>
                    </div>
                    <div class="jc-qt-config-summary-line">
                        <span id="jcQcSummaryVatLabel">VAT (15%)</span>
                        <span id="jcQcSummaryVat">N$0.00</span>
                    </div>
                    <div class="jc-qt-config-summary-line jc-qt-config-summary-line--grand">
                        <span>Total</span>
                        <span id="jcQcSummaryGrand">N$0.00</span>
                    </div>
                </div>
                <p class="jc-qt-config-hint jc-qt-config-summary-hint">Subtotal combines labour &amp; service and parts &amp; materials totals.</p>
            </div>
        </div>
    </div>

    <div class="jc-qt-config-action-bar">
        <button type="button" class="jc-qt-config-btn jc-qt-config-btn-light" id="jcQcResetBtn">Reset to defaults</button>
    </div>
</div>
