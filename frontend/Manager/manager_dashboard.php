<?php
declare(strict_types=1);

$page_title = 'Dashboard';
require_once __DIR__ . '/includes/header_manager.php';
require_once __DIR__ . '/includes/mgr_dashboard.inc.php';
require_once __DIR__ . '/includes/mgr_admin_oversight.inc.php';
require_once __DIR__ . '/../Admin/includes/student_table.inc.php';

$m = mgr_load_dashboard_metrics($pdo);
$mgr_admin_latest_by_user = mgr_fetch_admin_latest_by_user($pdo);
$dash_tasks = mgr_build_dashboard_tasks($pdo, $m, (int) ($_SESSION['user_id'] ?? 0));
$preview_queue = mgr_fetch_preview_queue($pdo, 6);

$qt_new = (int) ($m['qt_new'] ?? 0);
$inv_new = (int) ($m['inv_new'] ?? 0);
$preview_waiting = (int) ($m['preview_waiting'] ?? 0);
$qt_sent = (int) ($m['qt_sent'] ?? 0);
$inv_sent = (int) ($m['inv_sent'] ?? 0);
$jc_open = (int) ($m['jc_open'] ?? 0);
$jc_in_progress = (int) ($m['jc_in_progress'] ?? 0);
$inv_outstanding_amount = (float) ($m['inv_outstanding_amount'] ?? 0);
$inv_outstanding_count = (int) ($m['inv_outstanding_count'] ?? 0);
$inv_overdue_count = (int) ($m['inv_overdue_count'] ?? 0);
$inv_paid_amount = (float) ($m['inv_paid_amount'] ?? 0);
$inv_paid_month_amount = (float) ($m['inv_paid_month_amount'] ?? 0);
$inv_unpaid_amount = (float) ($m['inv_unpaid_amount'] ?? 0);
$inv_partial_amount = (float) ($m['inv_partial_amount'] ?? 0);
$chart_month_labels = $m['chart_month_labels'] ?? [];
$chart_collected = $m['chart_collected'] ?? [];
$top_clients = $m['top_clients'] ?? [];

$inbox_href = $preview_waiting > 0
    ? ($qt_new > 0 ? 'manager_quotations.php?filter=inbox' : 'manager_invoices.php?filter=inbox')
    : 'manager_quotations.php';

$inv_status_total = max(0.01, $inv_paid_amount + $inv_unpaid_amount + $inv_partial_amount);

function mgr_vt_card_banner(string $bc): array
{
    switch ($bc) {
        case 'rd-task-badge--urgent': return ['red', 'fa-circle-exclamation'];
        case 'rd-task-badge--review': return ['blue', 'fa-magnifying-glass'];
        case 'rd-task-badge--sign': return ['violet', 'fa-pen-to-square'];
        case 'rd-task-badge--done': return ['green', 'fa-circle-check'];
        case 'rd-task-badge--open': return ['amber', 'fa-clock'];
        default: return ['orange', 'fa-clipboard-list'];
    }
}

require_once __DIR__ . '/includes/mgr_dashboard_styles.inc.php';
?>

<div id="rdDashHeaderLeft" hidden>
    <h2 class="rd-dash-welcome"><?php echo htmlspecialchars(erp_dashboard_greeting_line(), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="rd-dash-welcome-sub"><?php echo date('l, F j, Y'); ?> &middot; <?php echo count($dash_tasks); ?> follow-up<?php echo count($dash_tasks) === 1 ? '' : 's'; ?></p>
</div>

<div class="vt-dashboard mgr-vt-dashboard">

    <div class="vt-analytics-grid">

        <div class="vt-analytics-left">

            <div class="vt-stat-row mgr-vt-stat-row--4" aria-label="Key metrics">
                <a href="manager_invoices.php" class="vt-stat-card">
                    <div class="vt-stat-card-top">
                        <span class="vt-stat-label">Revenue collected</span>
                        <div class="vt-stat-icon vt-stat-icon--green" aria-hidden="true"><i class="fas fa-sack-dollar"></i></div>
                    </div>
                    <div class="vt-stat-value"><?php echo htmlspecialchars(formatMoney($inv_paid_amount), ENT_QUOTES, 'UTF-8'); ?></div>
                    <span class="vt-stat-change vt-stat-change--muted">
                        <?php echo htmlspecialchars(formatMoney($inv_paid_month_amount), ENT_QUOTES, 'UTF-8'); ?> this month
                    </span>
                </a>

                <a href="<?php echo htmlspecialchars($inbox_href, ENT_QUOTES, 'UTF-8'); ?>" class="vt-stat-card">
                    <div class="vt-stat-card-top">
                        <span class="vt-stat-label">Awaiting preview</span>
                        <div class="vt-stat-icon vt-stat-icon--violet" aria-hidden="true"><i class="fas fa-eye"></i></div>
                    </div>
                    <div class="vt-stat-value"><?php echo number_format($preview_waiting); ?></div>
                    <?php if ($preview_waiting > 0): ?>
                    <span class="vt-stat-change vt-stat-change--warn">
                        <i class="fas fa-inbox" aria-hidden="true"></i>
                        <?php echo number_format($qt_new); ?> quote<?php echo $qt_new === 1 ? '' : 's'; ?> &middot; <?php echo number_format($inv_new); ?> invoice<?php echo $inv_new === 1 ? '' : 's'; ?>
                    </span>
                    <?php else: ?>
                    <span class="vt-stat-change vt-stat-change--up"><i class="fas fa-check" aria-hidden="true"></i> All caught up</span>
                    <?php endif; ?>
                </a>

                <a href="manager_invoices.php" class="vt-stat-card">
                    <div class="vt-stat-card-top">
                        <span class="vt-stat-label">Outstanding</span>
                        <div class="vt-stat-icon vt-stat-icon--amber" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                    </div>
                    <div class="vt-stat-value"><?php echo number_format($inv_outstanding_count); ?></div>
                    <?php if ($inv_overdue_count > 0): ?>
                    <span class="vt-stat-change vt-stat-change--down">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <?php echo number_format($inv_overdue_count); ?> overdue &middot; <?php echo htmlspecialchars(formatMoney($inv_outstanding_amount), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <?php elseif ($inv_outstanding_amount > 0): ?>
                    <span class="vt-stat-change vt-stat-change--warn">
                        <?php echo htmlspecialchars(formatMoney($inv_outstanding_amount), ENT_QUOTES, 'UTF-8'); ?> total due
                    </span>
                    <?php else: ?>
                    <span class="vt-stat-change vt-stat-change--up"><i class="fas fa-check" aria-hidden="true"></i> None outstanding</span>
                    <?php endif; ?>
                </a>

                <a href="manager_job_cards.php" class="vt-stat-card">
                    <div class="vt-stat-card-top">
                        <span class="vt-stat-label">Active job cards</span>
                        <div class="vt-stat-icon vt-stat-icon--orange" aria-hidden="true"><i class="fas fa-clipboard-list"></i></div>
                    </div>
                    <div class="vt-stat-value"><?php echo number_format($jc_open); ?></div>
                    <span class="vt-stat-change vt-stat-change--info">
                        <?php echo number_format($jc_in_progress); ?> in progress &middot; <?php echo number_format($qt_sent + $inv_sent); ?> sent for review
                    </span>
                </a>
            </div>

            <div class="vt-chart-card">
                <div class="vt-chart-card-head">
                    <div>
                        <h2 class="vt-chart-card-title">Revenue trend</h2>
                        <span class="vt-chart-card-sub">Collected revenue over the last 6 months</span>
                    </div>
                    <div class="vt-chart-legend">
                        <span class="vt-chart-legend-item"><span class="vt-chart-legend-dot" style="background:#f97316"></span> Collected</span>
                    </div>
                </div>
                <div class="vt-chart-wrap">
                    <canvas id="dashTrendChart" aria-label="Monthly collected revenue trend"></canvas>
                </div>
            </div>

        </div>

        <div class="vt-donut-panel">
            <h2 class="vt-donut-panel-title">Invoice status</h2>
            <div class="vt-donut-figure">
                <canvas id="dashDonutChart" aria-label="Invoice payment status by amount"></canvas>
                <div class="vt-donut-center-overlay" aria-hidden="true">
                    <span class="vt-donut-center-num"><?php echo htmlspecialchars(formatMoney($inv_status_total), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="vt-donut-center-lbl">total billed</span>
                </div>
            </div>
            <ul class="vt-donut-legend-list" aria-label="Invoice status breakdown">
                <li class="vt-donut-legend-item">
                    <span class="vt-donut-legend-line" style="background:#22c55e" aria-hidden="true"></span>
                    <span class="vt-donut-legend-lbl">Paid</span>
                    <span class="vt-donut-legend-cnt"><?php echo htmlspecialchars(formatMoney($inv_paid_amount), ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li class="vt-donut-legend-item">
                    <span class="vt-donut-legend-line" style="background:#f97316" aria-hidden="true"></span>
                    <span class="vt-donut-legend-lbl">Unpaid</span>
                    <span class="vt-donut-legend-cnt"><?php echo htmlspecialchars(formatMoney($inv_unpaid_amount), ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
                <li class="vt-donut-legend-item">
                    <span class="vt-donut-legend-line" style="background:#3b82f6" aria-hidden="true"></span>
                    <span class="vt-donut-legend-lbl">Partial</span>
                    <span class="vt-donut-legend-cnt"><?php echo htmlspecialchars(formatMoney($inv_partial_amount), ENT_QUOTES, 'UTF-8'); ?></span>
                </li>
            </ul>
            <div class="vt-donut-footer">
                <span class="vt-donut-footer-label">Collected this month</span>
                <span class="vt-donut-footer-value"><?php echo htmlspecialchars(formatMoney($inv_paid_month_amount), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="vt-donut-footer-sub"><?php echo number_format($inv_outstanding_count); ?> invoice<?php echo $inv_outstanding_count === 1 ? '' : 's'; ?> still open</span>
            </div>
        </div>

    </div>

    <?php if ($dash_tasks !== []): ?>
    <section class="mgr-dash-followup-bar" aria-label="Your follow-up">
        <div class="mgr-dash-followup-bar-head">
            <h2 class="mgr-dash-followup-bar-title">Your follow-up</h2>
            <a href="<?php echo htmlspecialchars($inbox_href, ENT_QUOTES, 'UTF-8'); ?>" class="vt-section-link">Open inbox</a>
        </div>
        <div class="mgr-dash-followup-bar-track">
            <?php foreach (array_slice($dash_tasks, 0, 4) as $task):
                [$iconTone, $iconName] = mgr_vt_card_banner((string) $task['badge_class']);
            ?>
            <a href="<?php echo htmlspecialchars((string) $task['url'], ENT_QUOTES, 'UTF-8'); ?>" class="mgr-dash-followup-chip">
                <span class="mgr-dash-followup-chip-icon mgr-dash-followup-chip-icon--<?php echo htmlspecialchars($iconTone, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
                    <i class="fas <?php echo htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8'); ?>"></i>
                </span>
                <span class="mgr-dash-followup-chip-body">
                    <span class="mgr-dash-followup-chip-title"><?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="mgr-dash-followup-chip-meta"><?php echo htmlspecialchars((string) $task['meta'], ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <span class="vt-badge <?php echo htmlspecialchars((string) $task['badge_class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $task['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="mgr-dash-lower<?php echo $preview_queue === [] ? ' mgr-dash-lower--wide-main' : ''; ?>">

        <section class="mgr-dash-panel mgr-dash-panel--clients" aria-label="Top clients">
            <div class="mgr-dash-panel-head">
                <div>
                    <h2 class="mgr-dash-panel-title">Top clients</h2>
                    <p class="mgr-dash-panel-sub">By revenue collected</p>
                </div>
                <a href="manager_invoices.php" class="vt-section-link">See all</a>
            </div>
            <div class="erp-student-table-wrap rd-dash-clients-wrap">
                <div class="erp-table-container qt-table-scroll">
                    <table class="erp-table erp-student-table rd-dash-clients-table" id="dashClientsTable" data-st-disable-paginate="1">
                        <colgroup>
                            <col class="rd-col-rank">
                            <col class="rd-col-client">
                            <col class="rd-col-collected">
                            <col class="rd-col-outstanding">
                        </colgroup>
                        <thead>
                            <tr>
                                <?php echo st_plain_th('#', 'rd-col-rank'); ?>
                                <?php echo st_sortable_th('Client', 'rd-col-client'); ?>
                                <?php echo st_sortable_th('Collected', 'rd-col-collected'); ?>
                                <?php echo st_sortable_th('Outstanding', 'rd-col-outstanding'); ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($top_clients === []): ?>
                            <tr class="qt-helper-row">
                                <td colspan="4">
                                    <div class="erp-empty-state" style="padding:24px 16px;">
                                        <div class="erp-empty-icon"><i class="fas fa-users"></i></div>
                                        <div class="erp-empty-title">No client revenue yet</div>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach (array_slice($top_clients, 0, 6) as $i => $clientRow):
                                $clientOutstanding = (float) ($clientRow['outstanding'] ?? 0);
                                $clientName = (string) ($clientRow['name'] ?? 'Walk-in');
                                $clientPaid = (float) ($clientRow['paid_revenue'] ?? 0);
                                $clientOverdueCount = (int) ($clientRow['overdue_invoice_count'] ?? 0);
                                $clientInitial = strtoupper(substr(trim($clientName), 0, 1)) ?: '?';
                                $clientAvatarTone = abs(crc32($clientName)) % 5;
                            ?>
                            <tr data-dash-client-row="1" data-row-url="manager_invoices.php">
                                <td class="rd-col-rank"><div class="qt-td-inner qt-td-inner--center"><?php echo (int) $i + 1; ?></div></td>
                                <td class="rd-col-client">
                                    <div class="qt-td-inner rd-client-cell">
                                        <span class="rd-client-avatar rd-client-avatar--<?php echo $clientAvatarTone; ?>" aria-hidden="true"><?php echo htmlspecialchars($clientInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="st-cell-primary"><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </td>
                                <td class="rd-col-collected">
                                    <div class="qt-td-inner qt-td-inner--end"><span class="st-cell-money"><?php echo formatMoney($clientPaid); ?></span></div>
                                </td>
                                <td class="rd-col-outstanding">
                                    <div class="qt-td-inner qt-td-inner--end">
                                        <?php if ($clientOutstanding > 0): ?>
                                        <span class="qt-status <?php echo $clientOverdueCount > 0 ? 'qt-status-unpaid' : 'qt-status-partial'; ?>"><?php echo $clientOverdueCount > 0 ? 'Overdue' : 'Due'; ?></span>
                                        <span class="st-cell-money"><?php echo formatMoney($clientOutstanding); ?></span>
                                        <?php else: ?>
                                        <span class="qt-status qt-status-paid">Clear</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <aside class="mgr-dash-panel mgr-dash-panel--preview" aria-label="Preview queue">
            <div class="mgr-dash-panel-head">
                <div>
                    <h2 class="mgr-dash-panel-title">Preview queue</h2>
                    <p class="mgr-dash-panel-sub">Sent by admin for your review</p>
                </div>
                <?php if ($preview_waiting > 0): ?>
                <a href="<?php echo htmlspecialchars($inbox_href, ENT_QUOTES, 'UTF-8'); ?>" class="vt-section-link">View all</a>
                <?php endif; ?>
            </div>
            <?php if ($preview_queue === []): ?>
            <div class="mgr-dash-inbox-clear">
                <span class="mgr-dash-inbox-clear-icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                <p class="mgr-dash-inbox-clear-title">Inbox is clear</p>
                <p class="mgr-dash-inbox-clear-text">New quotations and invoices will appear here when admin sends them.</p>
                <div class="mgr-dash-inbox-links">
                    <a href="manager_quotations.php">Quotations</a>
                    <a href="manager_invoices.php">Invoices</a>
                </div>
            </div>
            <?php else: ?>
            <ul class="mgr-dash-preview-list">
                <?php foreach ($preview_queue as $item):
                    $isQt = ($item['type'] ?? '') === 'quotation';
                ?>
                <li>
                    <a href="<?php echo htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>" class="mgr-dash-preview-item">
                        <span class="mgr-doc-type-badge <?php echo $isQt ? 'mgr-doc-type-badge--qt' : 'mgr-doc-type-badge--inv'; ?>">
                            <?php echo $isQt ? 'Quote' : 'Invoice'; ?>
                        </span>
                        <span class="mgr-dash-preview-body">
                            <span class="mgr-dash-preview-ref"><?php echo htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="mgr-dash-preview-client"><?php echo htmlspecialchars((string) ($item['client_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <span class="mgr-dash-preview-amount"><?php echo formatMoney((float) ($item['amount'] ?? 0)); ?></span>
                        <i class="fas fa-chevron-right mgr-dash-preview-chevron" aria-hidden="true"></i>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </aside>

    </div>

    <?php if ($mgr_admin_latest_by_user !== []): ?>
    <section class="mgr-dash-panel mgr-dash-panel--wide" aria-label="Admin team" id="mgrActivityFeed">
        <div class="mgr-dash-panel-head">
            <div>
                <h2 class="mgr-dash-panel-title">Admin team</h2>
                <p class="mgr-dash-panel-sub">Latest action per person</p>
            </div>
            <button type="button" class="erp-btn erp-btn-ghost erp-btn-sm" onclick="window.location.reload()" title="Refresh">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        <ul class="mgr-activity-feed" aria-live="polite">
            <?php foreach ($mgr_admin_latest_by_user as $act):
                $href = trim((string) ($act['href'] ?? ''));
                $roleName = trim((string) ($act['role_name'] ?? 'Staff'));
                $actionsToday = (int) ($act['actions_today'] ?? 0);
                $todayMeta = $actionsToday === 1 ? '1 action today' : $actionsToday . ' actions today';
            ?>
            <li class="mgr-activity-feed-item">
                <?php if ($href !== ''): ?>
                <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="mgr-activity-feed-link">
                <?php else: ?>
                <div class="mgr-activity-feed-link mgr-activity-feed-link--static">
                <?php endif; ?>
                    <span class="mgr-activity-feed-dot" aria-hidden="true"></span>
                    <span class="mgr-activity-feed-body">
                        <span class="mgr-activity-feed-who">
                            <strong><?php echo htmlspecialchars((string) ($act['username'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="mgr-activity-feed-role"><?php echo htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <span class="mgr-activity-feed-what">Last: <?php echo htmlspecialchars((string) ($act['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="mgr-activity-feed-meta"><?php echo htmlspecialchars($todayMeta, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                    <time class="mgr-activity-feed-time" datetime="<?php echo htmlspecialchars((string) ($act['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($act['time_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </time>
                <?php if ($href !== ''): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    function mountDashHeader() {
        var center = document.getElementById('erpTopbarCenter');
        var leftMount = document.getElementById('rdDashHeaderLeft');
        if (!center || !leftMount) return;
        center.innerHTML = '';
        leftMount.hidden = false;
        center.appendChild(leftMount);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountDashHeader);
    } else {
        mountDashHeader();
    }
})();

window.dashUpdateChartsTheme = function (isDark) {
    var tick = isDark ? '#a1a1aa' : '#475569';
    var grid = isDark ? 'rgba(255,255,255,0.08)' : '#e2e8f0';
    var legend = isDark ? '#cbd5e1' : '#334155';
    if (window.dashTrendChart) {
        var ro = window.dashTrendChart.options;
        if (ro.scales && ro.scales.x) ro.scales.x.ticks.color = tick;
        if (ro.scales && ro.scales.y) { ro.scales.y.ticks.color = tick; ro.scales.y.grid.color = grid; }
        window.dashTrendChart.update('none');
    }
    if (window.dashDonutChart) {
        var do_ = window.dashDonutChart.options;
        if (do_.plugins && do_.plugins.legend && do_.plugins.legend.labels) do_.plugins.legend.labels.color = legend;
        window.dashDonutChart.update('none');
    }
};
document.addEventListener('erp-theme-change', function (e) {
    if (typeof window.dashUpdateChartsTheme === 'function') window.dashUpdateChartsTheme(e.detail === 'dark');
});

(function () {
    var donutCanvas = document.getElementById('dashDonutChart');
    if (donutCanvas && window.Chart) {
        window.dashDonutChart = new Chart(donutCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Unpaid', 'Partial'],
                datasets: [{
                    data: [<?php echo json_encode($inv_paid_amount); ?>, <?php echo json_encode($inv_unpaid_amount); ?>, <?php echo json_encode($inv_partial_amount); ?>],
                    backgroundColor: ['#22c55e', '#f97316', '#3b82f6'],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverBorderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                animation: { duration: 0 },
                plugins: { legend: { display: false } }
            }
        });
    }

    var trendCanvas = document.getElementById('dashTrendChart');
    if (trendCanvas && window.Chart) {
        var ctx = trendCanvas.getContext('2d');
        var grad = ctx.createLinearGradient(0, 0, 0, 240);
        grad.addColorStop(0, 'rgba(249, 115, 22, 0.22)');
        grad.addColorStop(1, 'rgba(249, 115, 22, 0)');
        window.dashTrendChart = new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_month_labels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($chart_collected); ?>,
                    borderColor: '#f97316',
                    backgroundColor: grad,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2.5,
                    pointRadius: 0,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#f97316',
                    pointHoverBorderColor: '#ffffff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 0 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                return '  ' + Number(c.parsed.y).toLocaleString(undefined, { style: 'currency', currency: 'NAD' });
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#475569', font: { size: 11, weight: '600' } } },
                    y: { beginAtZero: true, border: { display: false }, grid: { color: '#e2e8f0' }, ticks: { color: '#475569', font: { size: 11, weight: '600' }, maxTicksLimit: 5 } }
                }
            }
        });
    }

    document.querySelectorAll('#dashClientsTable tr[data-dash-client-row="1"]').forEach(function (row) {
        row.addEventListener('click', function () {
            var url = row.getAttribute('data-row-url');
            if (url) window.location.href = url;
        });
    });

    if (document.documentElement.classList.contains('rd-dash-theme-dark') && typeof window.dashUpdateChartsTheme === 'function') {
        window.dashUpdateChartsTheme(true);
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer_manager.php'; ?>
