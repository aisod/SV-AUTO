<?php
declare(strict_types=1);
/**
 * Manager quotations list — Documents-style layout.
 */
?>
<?php ops_docs_render_folders('Folders', $mgr_qt_folders, ''); ?>

<?php ops_docs_files_section_open('Files', ''); ?>
<span id="qtTableTitle" hidden>All quotations</span>
<span id="qtTableMeta" hidden aria-hidden="true"></span>

<div class="mgr-qt-toolbar ops-filter-card" aria-label="List options">
    <p class="mgr-qt-toolbar-hint">Tap a folder above to filter. Use search for a client, quote number, or job card.</p>
    <div class="ops-filter-footer mgr-qt-filter-footer mgr-qt-toolbar-only">
        <label class="ops-filter-sort qt-control qt-control--sort">
            <span class="mgr-qt-sort-label">Sort</span>
            <select id="sortFilter" class="erp-input erp-input-sm" aria-label="Sort quotations">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="amount_high">Amount (high → low)</option>
                <option value="amount_low">Amount (low → high)</option>
            </select>
        </label>
        <p class="ops-filter-result" id="opsFilterResult">Showing all quotations</p>
    </div>
</div>

<?php ops_docs_table_card_open('qtQuotationsList'); ?>
    <div class="erp-card-body erp-p-0">
        <div class="erp-student-table-wrap">
        <div class="erp-table-container qt-table-scroll">
            <table class="erp-table erp-student-table qt-list-table" id="quotationTable" data-st-row-selector="tr[data-quote-row=&quot;1&quot;][data-can-preview=&quot;1&quot;]">
                <colgroup>
                    <col class="qt-col-id" style="width:96px">
                    <col class="qt-col-progress" style="width:68px">
                    <col class="qt-col-job" style="width:72px">
                    <col class="qt-col-client" style="width:128px">
                    <col class="qt-col-amount" style="width:108px">
                    <col class="qt-col-date" style="width:128px">
                    <col class="qt-col-digital" style="width:120px">
                    <col class="qt-col-saved" style="width:112px">
                    <col class="qt-col-paper" style="width:160px">
                    <col class="qt-col-client-resp" style="width:112px">
                    <col class="qt-col-actions" style="width:52px">
                </colgroup>
                <thead>
                    <tr>
                        <?php echo st_sortable_th('ID', 'qt-col-id'); ?>
                        <?php echo st_plain_th('Progress', 'qt-col-progress'); ?>
                        <?php echo st_sortable_th('Job card', 'qt-col-job'); ?>
                        <?php echo st_sortable_th('Client', 'qt-col-client'); ?>
                        <?php echo st_sortable_th('Amount', 'qt-col-amount'); ?>
                        <?php echo st_sortable_th('Date', 'qt-col-date', true, 'Submitted'); ?>
                        <?php echo st_plain_th('Digital preview', 'qt-col-digital', true, 'Portal'); ?>
                        <?php echo st_sortable_th('Saved status', 'qt-col-saved', true, 'In system'); ?>
                        <?php echo st_plain_th('Paper sign-off', 'qt-col-paper', true, 'On paper'); ?>
                        <?php echo st_plain_th('Client response', 'qt-col-client-resp'); ?>
                        <?php echo st_plain_th('Action', 'st-col-actions qt-col-actions st-no-row-nav'); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $q):
                        $statusText = 'Pending';
                        if (($q['status'] ?? '') === 'pending_manager') {
                            $statusText = 'Pending Approval';
                        } elseif (($q['status'] ?? '') === 'sent_back_admin') {
                            $statusText = 'Sent Back to Admin';
                        } elseif (($q['status'] ?? '') === 'approved') {
                            $statusText = 'Approved';
                        } elseif (($q['status'] ?? '') === 'rejected') {
                            $statusText = 'Rejected';
                        }
                        $qtWf = is_array($q['workflow'] ?? null) ? $q['workflow'] : ['steps' => [], 'next_label' => '', 'next_hint' => ''];
                        $qtWfTitle = ($qtWf['next_label'] ?? '') . ' — ' . ($qtWf['next_hint'] ?? '');
                        $qtRowDate = $q['date'] ?? null;
                        $qtLabel = 'QTN-' . str_pad((string) $q['quote_id'], 5, '0', STR_PAD_LEFT);
                        $canPreview = true;
                        $viewUrl = $mgr_qt_view_base . (int) $q['quote_id'];
                    ?>
                    <tr data-quote-row="1"
                        data-status="<?php echo htmlspecialchars(strtolower((string) ($q['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                        data-client-status="<?php echo htmlspecialchars((string) ($q['client_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-form-status="<?php echo htmlspecialchars((string) ($q['form_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        data-quote-id="<?php echo (int) $q['quote_id']; ?>"
                        data-quote-label="<?php echo htmlspecialchars($qtLabel, ENT_QUOTES, 'UTF-8'); ?>"
                        data-date-ts="<?php echo !empty($qtRowDate) ? (int) strtotime((string) $qtRowDate) : 0; ?>"
                        data-in-today="<?php echo mgr_qt_submitted_in_period($qtRowDate, 'today') ? '1' : '0'; ?>"
                        data-in-week="<?php echo mgr_qt_submitted_in_period($qtRowDate, 'week') ? '1' : '0'; ?>"
                        data-in-month="<?php echo mgr_qt_submitted_in_period($qtRowDate, 'month') ? '1' : '0'; ?>"
                        data-in-year="<?php echo mgr_qt_submitted_in_period($qtRowDate, 'year') ? '1' : '0'; ?>"
                        data-paper-signed="<?php echo !empty($q['paper_signed']) ? '1' : '0'; ?>"
                        data-digital-sent="<?php echo !empty($q['review_sent']) ? '1' : '0'; ?>"
                        data-digital-viewed="<?php echo !empty($q['review_viewed']) ? '1' : '0'; ?>"
                        data-can-preview="<?php echo $canPreview ? '1' : '0'; ?>"
                        data-amount="<?php echo (float) ($q['amount'] ?? 0); ?>"
                        data-row-url="<?php echo htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="qt-col-id"><div class="qt-td-inner"><span class="qt-id"><?php echo htmlspecialchars($qtLabel, ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <td class="qt-col-progress" onclick="event.stopPropagation();">
                            <div class="qt-td-inner qt-td-inner--center">
                            <div class="qt-row-progress" title="<?php echo htmlspecialchars($qtWfTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php foreach ($qtWf['steps'] as $wfStep): ?>
                                <span class="qt-row-dot<?php echo !empty($wfStep['done']) ? ' is-done' : ''; ?><?php echo !empty($wfStep['current']) ? ' is-current' : ''; ?>" aria-hidden="true"></span>
                                <?php endforeach; ?>
                            </div>
                            </div>
                        </td>
                        <td class="qt-col-job"><div class="qt-td-inner"><?php echo htmlspecialchars((string) ($q['job_card'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div></td>
                        <td class="qt-col-client"><div class="qt-td-inner"><span class="st-cell-primary"><?php echo htmlspecialchars((string) ($q['client_name'] ?? 'Walk-in'), ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <td class="qt-col-amount"><div class="qt-td-inner qt-td-inner--end"><?php echo formatMoney((float) ($q['amount'] ?? 0)); ?></div></td>
                        <td class="qt-col-date"><div class="qt-td-inner"><?php echo mgr_qt_list_submitted_cell($qtRowDate); ?></div></td>
                        <td class="qt-col-digital">
                            <div class="qt-td-inner">
                            <?php if (empty($q['review_sent'])): ?>
                                <span class="qt-status qt-status-digital-wait">Awaiting send</span>
                            <?php elseif (!empty($q['review_viewed'])): ?>
                                <span class="qt-status qt-status-digital-viewed">Viewed</span>
                            <?php else: ?>
                                <span class="qt-status qt-status-digital-new">New</span>
                            <?php endif; ?>
                            </div>
                        </td>
                        <td class="qt-col-saved">
                            <div class="qt-td-inner">
                            <span class="qt-status <?php echo ($q['status'] ?? '') === 'approved' ? 'qt-status-approved' : (($q['status'] ?? '') === 'rejected' ? 'qt-status-rejected' : 'qt-status-pending'); ?>">
                                <?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            </div>
                        </td>
                        <td class="qt-col-paper">
                            <div class="qt-paper-cell">
                                <div class="qt-paper-line">
                                    <?php if (($q['status'] ?? '') === 'rejected'):
                                        $rejAt = trim((string) ($q['rejected_at'] ?? ''));
                                        $rejTitle = ($q['rejection_reason'] ?? '') !== '' ? (string) $q['rejection_reason'] : (string) ($q['rejected_by'] ?? '');
                                    ?>
                                    <span class="qt-status qt-status-rejected" title="<?php echo htmlspecialchars($rejTitle, ENT_QUOTES, 'UTF-8'); ?>">Declined</span>
                                    <?php if ($rejAt !== ''): ?>
                                    <span class="qt-paper-meta"><?php echo htmlspecialchars(qt_format_paper_signoff_date($rejAt), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php elseif (!empty($q['paper_signed'])): ?>
                                    <span class="qt-status qt-status-paper-signed" title="<?php echo htmlspecialchars((string) ($q['paper_signed_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Signed</span>
                                    <?php if (!empty($q['paper_signed_at'])): ?>
                                    <span class="qt-paper-meta"><?php echo htmlspecialchars(qt_format_paper_signoff_date((string) $q['paper_signed_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="qt-status qt-status-paper-pending">Awaiting</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="qt-col-client-resp">
                            <div class="qt-td-inner">
                            <?php if (($q['status'] ?? '') === 'approved'): ?>
                                <?php if (empty($q['client_status'])): ?>
                                    <span class="erp-badge erp-badge-warning">Awaiting</span>
                                <?php elseif ($q['client_status'] === 'client_accepted'): ?>
                                    <span class="erp-badge erp-badge-success"><i class="fas fa-check"></i> Accepted</span>
                                <?php elseif ($q['client_status'] === 'client_rejected'): ?>
                                    <span class="erp-badge erp-badge-danger"><i class="fas fa-times"></i> Rejected</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="qt-cell-muted">-</span>
                            <?php endif; ?>
                            </div>
                        </td>
                        <?php echo st_actions_cell(['view' => $viewUrl]); ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($quotations === []): ?>
                    <tr class="qt-helper-row" id="qtEmptyRow">
                        <td colspan="<?php echo (int) $mgr_qt_table_colspan; ?>">
                            <div class="erp-empty-state">
                                <div class="erp-empty-icon"><i class="fas fa-file-invoice"></i></div>
                                <div class="erp-empty-title">No quotations yet</div>
                                <div class="erp-empty-text">Quotations appear here when admin creates them from job cards.</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="qt-helper-row" id="qtNoMatchRow" style="display:none;">
                        <td colspan="<?php echo (int) $mgr_qt_table_colspan; ?>" id="qtNoMatchCell"><i class="fas fa-search"></i> <span id="qtNoMatchMessage">No matching quotations found</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php st_render_table_footer('quotationTable'); ?>
        </div>
    </div>
</div>
<?php ops_docs_files_section_close(); ?>
