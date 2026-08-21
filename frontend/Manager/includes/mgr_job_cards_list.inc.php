<?php
declare(strict_types=1);
/**
 * Manager job cards — Documents-style layout (folders + filters + table).
 */
?>
<?php ops_docs_render_folders('Folders', $mgr_jc_folders, ''); ?>

<?php ops_docs_files_section_open('Files', ''); ?>
<span id="jcTableTitle" hidden>All job cards</span>
<span id="jcTableMeta" hidden aria-hidden="true"></span>

<div class="qt-section qt-find-panel ops-filter-card" aria-label="Filter job cards">
    <div class="ops-filter-row">
        <span class="ops-filter-label">Status</span>
        <div class="qt-tabs" data-mgr-tab-group="status">
            <button type="button" class="qt-tab active" data-filter="all" data-title="All job cards">All</button>
            <button type="button" class="qt-tab" data-filter="open" data-title="Open job cards">Open</button>
            <button type="button" class="qt-tab" data-filter="in_progress" data-title="In progress">In progress</button>
            <button type="button" class="qt-tab" data-filter="complete" data-title="Completed">Completed</button>
        </div>
    </div>
    <div class="ops-filter-footer">
        <label class="ops-filter-sort qt-control qt-control--sort">
            <select id="jcSortFilter" class="erp-input erp-input-sm" aria-label="Sort job cards">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
            </select>
        </label>
        <p class="ops-filter-result" id="opsFilterResult"></p>
    </div>
</div>

<?php ops_docs_table_card_open('jcJobCardsList'); ?>
    <div class="erp-card-body erp-p-0">
        <div class="erp-student-table-wrap">
        <div class="erp-table-container qt-table-scroll">
            <table class="erp-table erp-student-table mgr-list-table" id="jobCardTable" data-st-row-selector="tr[data-mgr-row=&quot;1&quot;]">
                <colgroup>
                    <col class="jc-col-card-no" style="width:100px">
                    <col class="jc-col-progress" style="width:68px">
                    <col class="jc-col-client" style="width:140px">
                    <col class="jc-col-vehicle" style="width:120px">
                    <col class="jc-col-status" style="width:120px">
                    <col class="jc-col-created" style="width:128px">
                    <col class="jc-col-actions" style="width:52px">
                </colgroup>
                <thead>
                    <tr>
                        <?php echo st_sortable_th('Job card', 'jc-col-card-no'); ?>
                        <?php echo st_plain_th('Progress', 'jc-col-progress'); ?>
                        <?php echo st_sortable_th('Client', 'jc-col-client'); ?>
                        <?php echo st_sortable_th('Vehicle', 'jc-col-vehicle'); ?>
                        <?php echo st_sortable_th('Status', 'jc-col-status'); ?>
                        <?php echo st_sortable_th('Created', 'jc-col-created', true, 'Logged'); ?>
                        <?php echo st_plain_th('Action', 'st-col-actions st-no-row-nav'); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobCards as $r):
                        $id = (int) ($r['id'] ?? 0);
                        $label = trim((string) ($r['card_number'] ?? ''));
                        if ($label === '') {
                            $label = 'JC-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
                        }
                        $statusNorm = jc_status_normalize((string) ($r['status'] ?? ''));
                        $vehicle = trim((string) ($r['reg_no'] ?? '') . ($r['model'] !== '' && $r['reg_no'] !== '' ? ' · ' : '') . (string) ($r['model'] ?? ''));
                        $created = $r['created_at'] ?? null;
                        $dots = mgr_jc_progress_dots((string) ($r['status'] ?? ''));
                        $viewUrl = $mgr_jc_view_base . $id;
                        $statusClass = 'qt-status-open';
                        if (in_array($statusNorm, ['completed', 'invoiced', 'paid'], true)) {
                            $statusClass = 'qt-status-complete';
                        } elseif (in_array($statusNorm, ['in_progress', 'waiting_parts'], true)) {
                            $statusClass = 'qt-status-progress';
                        }
                    ?>
                    <tr data-mgr-row="1"
                        data-status="<?php echo htmlspecialchars($statusNorm, ENT_QUOTES, 'UTF-8'); ?>"
                        data-jc-id="<?php echo $id; ?>"
                        data-date-ts="<?php echo !empty($created) ? (int) strtotime((string) $created) : 0; ?>"
                        data-in-today="<?php echo mgr_jc_in_period($created, 'today') ? '1' : '0'; ?>"
                        data-in-week="<?php echo mgr_jc_in_period($created, 'week') ? '1' : '0'; ?>"
                        data-in-month="<?php echo mgr_jc_in_period($created, 'month') ? '1' : '0'; ?>"
                        data-row-url="<?php echo htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="jc-col-card-no"><div class="qt-td-inner"><span class="mgr-id"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <td class="jc-col-progress" onclick="event.stopPropagation();"><div class="qt-td-inner qt-td-inner--center"><div class="qt-row-progress"><?php foreach ($dots as $d): ?><span class="qt-row-dot<?php echo !empty($d['done']) ? ' is-done' : ''; ?><?php echo !empty($d['current']) ? ' is-current' : ''; ?>"></span><?php endforeach; ?></div></div></td>
                        <td class="jc-col-client"><div class="qt-td-inner"><span class="st-cell-primary"><?php echo htmlspecialchars((string) ($r['client_name'] ?? 'Walk-in'), ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <td class="jc-col-vehicle"><div class="qt-td-inner"><?php echo htmlspecialchars($vehicle !== '' ? $vehicle : '—', ENT_QUOTES, 'UTF-8'); ?></div></td>
                        <td class="jc-col-status"><div class="qt-td-inner"><span class="qt-status <?php echo $statusClass; ?>"><?php echo htmlspecialchars(jc_status_label((string) ($r['status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span></div></td>
                        <td class="jc-col-created"><div class="qt-td-inner"><?php echo mgr_jc_list_date_cell($created); ?></div></td>
                        <?php echo st_actions_cell(['view' => $viewUrl]); ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($jobCards === []): ?>
                    <tr class="qt-helper-row" id="jcEmptyRow">
                        <td colspan="<?php echo (int) $mgr_jc_table_colspan; ?>">
                            <div class="erp-empty-state">
                                <div class="erp-empty-icon"><i class="fas fa-clipboard-list"></i></div>
                                <div class="erp-empty-title">No job cards yet</div>
                                <div class="erp-empty-text">Job cards appear when admin creates them from the workshop.</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="qt-helper-row" id="jcNoMatchRow" style="display:none;">
                        <td colspan="<?php echo (int) $mgr_jc_table_colspan; ?>" id="jcNoMatchCell"><i class="fas fa-search"></i> <span id="jcNoMatchMessage">No matching job cards found</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php st_render_table_footer('jobCardTable'); ?>
        </div>
    </div>
</div>
<?php ops_docs_files_section_close(); ?>
