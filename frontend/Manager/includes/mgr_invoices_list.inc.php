<?php
declare(strict_types=1);
/**
 * Manager invoices — Documents-style layout.
 */
?>
<?php ops_docs_render_folders('Folders', $mgr_inv_folders, ''); ?>

<?php ops_docs_files_section_open('Files', ''); ?>
<span id="invTableTitle" hidden>All invoices</span>
<span id="invTableMeta" hidden aria-hidden="true"></span>

<div class="mgr-list-toolbar ops-filter-card" aria-label="List options">
    <p class="mgr-list-toolbar-hint">Tap a folder above to filter. Use search for a client, invoice number, or amount.</p>
    <div class="ops-filter-footer mgr-list-filter-footer mgr-list-toolbar-only">
        <label class="ops-filter-sort qt-control qt-control--sort">
            <span class="mgr-list-sort-label">Sort</span>
            <select id="invSortFilter" class="erp-input erp-input-sm" aria-label="Sort invoices">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="amount_high">Amount (high → low)</option>
                <option value="amount_low">Amount (low → high)</option>
            </select>
        </label>
        <p class="ops-filter-result" id="opsFilterResult">Showing all invoices</p>
    </div>
</div>

<?php ops_docs_table_card_open('invInvoicesList'); ?>
    <div class="erp-card-body erp-p-0">
        <div class="erp-student-table-wrap">
        <div class="erp-table-container qt-table-scroll">
            <table class="erp-table erp-student-table mgr-list-table" id="invoiceTable" data-st-row-selector="tr[data-mgr-row=&quot;1&quot;][data-can-preview=&quot;1&quot;]">
                <colgroup>
                    <col class="inv-col-id" style="width:100px">
                    <col class="inv-col-progress" style="width:68px">
                    <col class="inv-col-client" style="width:140px">
                    <col class="inv-col-amount" style="width:108px">
                    <col class="inv-col-issued" style="width:128px">
                    <col class="inv-col-digital" style="width:120px">
                    <col class="inv-col-payment" style="width:112px">
                    <col class="inv-col-actions" style="width:52px">
                </colgroup>
                <thead>
                    <tr>
                        <?php echo st_sortable_th('Invoice', 'inv-col-id'); ?>
                        <?php echo st_plain_th('Progress', 'inv-col-progress'); ?>
                        <?php echo st_sortable_th('Client', 'inv-col-client'); ?>
                        <?php echo st_sortable_th('Amount', 'inv-col-amount'); ?>
                        <?php echo st_sortable_th('Date', 'inv-col-issued', true, 'Issued'); ?>
                        <?php echo st_plain_th('Digital preview', 'inv-col-digital', true, 'Portal'); ?>
                        <?php echo st_sortable_th('Payment', 'inv-col-payment'); ?>
                        <?php echo st_plain_th('Action', 'st-col-actions st-no-row-nav'); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $r):
                        $id = (int) ($r['id'] ?? 0);
                        $label = trim((string) ($r['invoice_number'] ?? ''));
                        if ($label === '') {
                            $label = 'INV-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                        }
                        $sent = !empty($r['review_sent']);
                        $viewed = !empty($r['review_viewed']);
                        $canPreview = true;
                        $viewUrl = $mgr_inv_view_base . $id;
                        $rowDate = $r['issued_date'] ?? $r['manager_review_sent_at'] ?? null;
                        $paid = (string) ($r['paid_norm'] ?? 'unpaid');
                        $dots = mgr_inv_progress_dots($sent, $viewed, (string) ($r['status_paid'] ?? ''));
                        $paidClass = 'qt-status-unpaid';
                        if ($paid === 'paid') {
                            $paidClass = 'qt-status-paid';
                        } elseif ($paid === 'partial') {
                            $paidClass = 'qt-status-partial';
                        }
                    ?>
                    <tr data-mgr-row="1"
                        data-paid="<?php echo htmlspecialchars($paid, ENT_QUOTES, 'UTF-8'); ?>"
                        data-digital-sent="<?php echo $sent ? '1' : '0'; ?>"
                        data-digital-viewed="<?php echo $viewed ? '1' : '0'; ?>"
                        data-can-preview="<?php echo $canPreview ? '1' : '0'; ?>"
                        data-inv-id="<?php echo $id; ?>"
                        data-date-ts="<?php echo !empty($rowDate) ? (int) strtotime((string) $rowDate) : 0; ?>"
                        data-amount="<?php echo (float) ($r['amount'] ?? 0); ?>"
                        data-in-today="<?php echo mgr_inv_in_period($rowDate, 'today') ? '1' : '0'; ?>"
                        data-in-week="<?php echo mgr_inv_in_period($rowDate, 'week') ? '1' : '0'; ?>"
                        data-in-month="<?php echo mgr_inv_in_period($rowDate, 'month') ? '1' : '0'; ?>"
                        data-row-url="<?php echo htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="inv-col-id"><div class="qt-td-inner"><span class="mgr-id"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <td class="inv-col-progress" onclick="event.stopPropagation();"><div class="qt-td-inner qt-td-inner--center"><div class="qt-row-progress"><?php foreach ($dots as $d): ?><span class="qt-row-dot<?php echo !empty($d['done']) ? ' is-done' : ''; ?><?php echo !empty($d['current']) ? ' is-current' : ''; ?>"></span><?php endforeach; ?></div></div></td>
                        <td class="inv-col-client"><div class="qt-td-inner"><span class="st-cell-primary"><?php echo htmlspecialchars((string) ($r['client_name'] ?? 'Walk-in'), ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <td class="inv-col-amount"><div class="qt-td-inner qt-td-inner--end"><?php echo formatMoney((float) ($r['amount'] ?? 0)); ?></div></td>
                        <td class="inv-col-issued"><div class="qt-td-inner"><?php echo mgr_inv_list_date_cell($rowDate); ?></div></td>
                        <td class="inv-col-digital"><div class="qt-td-inner">
                            <?php if (!$sent): ?>
                                <span class="qt-status qt-status-digital-wait">Awaiting send</span>
                            <?php elseif ($viewed): ?>
                                <span class="qt-status qt-status-digital-viewed">Viewed</span>
                            <?php else: ?>
                                <span class="qt-status qt-status-digital-new">New</span>
                            <?php endif; ?>
                        </div></td>
                        <td class="inv-col-payment"><div class="qt-td-inner"><span class="qt-status <?php echo $paidClass; ?>"><?php echo htmlspecialchars(mgr_inv_paid_label((string) ($r['status_paid'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <?php echo st_actions_cell(['view' => $viewUrl]); ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($invoices === []): ?>
                    <tr class="qt-helper-row" id="invEmptyRow">
                        <td colspan="<?php echo (int) $mgr_inv_table_colspan; ?>">
                            <div class="erp-empty-state">
                                <div class="erp-empty-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                                <div class="erp-empty-title">No invoices yet</div>
                                <div class="erp-empty-text">Invoices appear when admin creates them from approved quotations.</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="qt-helper-row" id="invNoMatchRow" style="display:none;">
                        <td colspan="<?php echo (int) $mgr_inv_table_colspan; ?>" id="invNoMatchCell"><i class="fas fa-search"></i> <span id="invNoMatchMessage">No matching invoices found</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php st_render_table_footer('invoiceTable'); ?>
        </div>
    </div>
</div>
<?php ops_docs_files_section_close(); ?>
