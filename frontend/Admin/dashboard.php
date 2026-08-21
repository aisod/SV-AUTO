<?php
session_start();
require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/functions.php';
require_once __DIR__ . '/../../backend/config/notifications.php';
require_once __DIR__ . '/../../backend/config/user_drafts.php';
require_once __DIR__ . '/Quotation/quotation_paper_signoff.inc.php';
require_once __DIR__ . '/JobCard/jc_status.php';
require_once __DIR__ . '/includes/student_table.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$username  = $_SESSION['username'] ?? 'User';
$business = getBusiness();
$dash_show_revenue = erp_can_view_dashboard_revenue();

$jc_open_count = 0;
$jc_in_progress_count = 0;
$jc_awaiting_quote_count = 0;
$qt_pending_count = 0;
$qt_awaiting_manager_count = 0;
$qt_awaiting_client_count = 0;
$inv_outstanding_count = 0;
$inv_outstanding_amount = 0.0;
$inv_overdue_count = 0;
$inv_overdue_amount = 0.0;
$inv_paid_amount = 0.0;
$inv_paid_month_amount = 0.0;
$inv_unpaid_amount = 0.0;
$inv_partial_amount = 0.0;
$chart_month_labels = [];
$chart_collected = [];
$chart_outstanding = [];
$chart_trend_labels = [];
$chart_jc_counts = [];
$top_clients = [];
$dash_jc_today = 0;
$dash_jc_week = 0;
$dash_inv_today = 0;
$dash_inv_week = 0;
$dash_qt_today = 0;
$dash_qt_week = 0;

try {
    $row = $pdo->query('SELECT
        COUNT(CASE WHEN ' . jc_status_open_sql('jc') . ' THEN 1 END) AS open_count,
        COUNT(CASE WHEN ' . jc_status_in_progress_sql('jc') . ' THEN 1 END) AS in_progress_count
    FROM job_cards jc
    WHERE jc.deleted_at IS NULL')->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $jc_open_count = (int) ($row['open_count'] ?? 0);
        $jc_in_progress_count = (int) ($row['in_progress_count'] ?? 0);
    }

    $row = $pdo->query('SELECT COUNT(*) AS awaiting_quote_count
    FROM job_cards jc
    WHERE jc.deleted_at IS NULL
      AND ' . jc_status_open_sql('jc') . '
      AND NOT EXISTS (
          SELECT 1 FROM quotations q
          WHERE q.job_card_id = jc.id AND q.deleted_at IS NULL
      )')->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $jc_awaiting_quote_count = (int) ($row['awaiting_quote_count'] ?? 0);
    }

    $row = $pdo->query("SELECT
        COUNT(CASE WHEN status IN ('pending', 'pending_manager', 'sent_back_admin') THEN 1 END) AS pending_count
    FROM quotations
    WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $qt_pending_count = (int) ($row['pending_count'] ?? 0);
    }

    $qtStmt = $pdo->query("SELECT status, client_status, details FROM quotations WHERE deleted_at IS NULL");
    while ($qRow = $qtStmt->fetch(PDO::FETCH_ASSOC)) {
        $payload = qt_parse_quotation_details((string) ($qRow['details'] ?? ''));
        $form = (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) ? $payload['form'] : [];
        $paper = qt_paper_signoff_info($form);
        $ctx = [
            'status' => strtolower(trim((string) ($qRow['status'] ?? ''))),
            'client_status' => trim((string) ($qRow['client_status'] ?? '')),
            'paper_signed' => $paper['signed'],
            'form_status' => strtolower(trim((string) ($form['status'] ?? ''))),
        ];
        if (qt_matches_paper_filter($ctx, 'paper_pending')) {
            $qt_awaiting_manager_count++;
        }
        if (qt_matches_paper_filter($ctx, 'awaiting_client')) {
            $qt_awaiting_client_count++;
        }
    }

    if ($dash_show_revenue) {
        $row = $pdo->query("SELECT
            COUNT(CASE WHEN status_paid IN ('unpaid', 'partial') THEN 1 END) AS open_count,
            COALESCE(SUM(CASE WHEN status_paid IN ('unpaid', 'partial') THEN amount ELSE 0 END), 0) AS outstanding_amount,
            COUNT(CASE WHEN status_paid IN ('unpaid', 'partial')
                AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 END) AS overdue_count,
            COALESCE(SUM(CASE WHEN status_paid IN ('unpaid', 'partial')
                AND due_date IS NOT NULL AND due_date < CURDATE() THEN amount ELSE 0 END), 0) AS overdue_amount,
            COALESCE(SUM(CASE WHEN status_paid = 'paid' THEN amount ELSE 0 END), 0) AS paid_amount,
            COALESCE(SUM(CASE WHEN status_paid = 'paid'
                AND DATE_FORMAT(COALESCE(paid_at, issued_date, created_at), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                THEN amount ELSE 0 END), 0) AS paid_month_amount,
            COALESCE(SUM(CASE WHEN status_paid = 'unpaid' THEN amount ELSE 0 END), 0) AS unpaid_amount,
            COALESCE(SUM(CASE WHEN status_paid = 'partial' THEN amount ELSE 0 END), 0) AS partial_amount
        FROM invoices
        WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $inv_outstanding_count = (int) ($row['open_count'] ?? 0);
            $inv_outstanding_amount = (float) ($row['outstanding_amount'] ?? 0);
            $inv_overdue_count = (int) ($row['overdue_count'] ?? 0);
            $inv_overdue_amount = (float) ($row['overdue_amount'] ?? 0);
            $inv_paid_amount = (float) ($row['paid_amount'] ?? 0);
            $inv_paid_month_amount = (float) ($row['paid_month_amount'] ?? 0);
            $inv_unpaid_amount = (float) ($row['unpaid_amount'] ?? 0);
            $inv_partial_amount = (float) ($row['partial_amount'] ?? 0);
        }
    } else {
        $row = $pdo->query("SELECT
            COUNT(CASE WHEN status_paid IN ('unpaid', 'partial') THEN 1 END) AS open_count,
            COUNT(CASE WHEN status_paid IN ('unpaid', 'partial')
                AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 END) AS overdue_count
        FROM invoices
        WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $inv_outstanding_count = (int) ($row['open_count'] ?? 0);
            $inv_overdue_count = (int) ($row['overdue_count'] ?? 0);
        }

        $row = $pdo->query("SELECT
            (SELECT COUNT(*) FROM job_cards jc
                WHERE jc.deleted_at IS NULL AND DATE(jc.created_at) = CURDATE()) AS jc_today,
            (SELECT COUNT(*) FROM job_cards jc
                WHERE jc.deleted_at IS NULL
                  AND YEARWEEK(DATE(jc.created_at), 1) = YEARWEEK(CURDATE(), 1)) AS jc_week,
            (SELECT COUNT(*) FROM invoices i
                WHERE i.deleted_at IS NULL AND i.issued_date IS NOT NULL
                  AND DATE(i.issued_date) = CURDATE()) AS inv_today,
            (SELECT COUNT(*) FROM invoices i
                WHERE i.deleted_at IS NULL AND i.issued_date IS NOT NULL
                  AND YEARWEEK(DATE(i.issued_date), 1) = YEARWEEK(CURDATE(), 1)) AS inv_week,
            (SELECT COUNT(*) FROM quotations q
                WHERE q.deleted_at IS NULL
                  AND DATE(COALESCE(q.submitted_at, q.created_at)) = CURDATE()) AS qt_today,
            (SELECT COUNT(*) FROM quotations q
                WHERE q.deleted_at IS NULL
                  AND YEARWEEK(DATE(COALESCE(q.submitted_at, q.created_at)), 1) = YEARWEEK(CURDATE(), 1)) AS qt_week")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $dash_jc_today = (int) ($row['jc_today'] ?? 0);
            $dash_jc_week = (int) ($row['jc_week'] ?? 0);
            $dash_inv_today = (int) ($row['inv_today'] ?? 0);
            $dash_inv_week = (int) ($row['inv_week'] ?? 0);
            $dash_qt_today = (int) ($row['qt_today'] ?? 0);
            $dash_qt_week = (int) ($row['qt_week'] ?? 0);
        }
    }

    if ($dash_show_revenue) {
        $chart_month_keys = [];
        $chart_month_labels = [];
        for ($m = 5; $m >= 0; $m--) {
            $ts = strtotime('-' . $m . ' months');
            $chart_month_keys[] = date('Y-m', $ts);
            $chart_month_labels[] = date('M Y', $ts);
        }
        $chart_collected = array_fill(0, 6, 0.0);
        $chart_outstanding = array_fill(0, 6, 0.0);
        $since = date('Y-m-01', strtotime('-5 months'));

        $collectedRows = $pdo->query("SELECT DATE_FORMAT(COALESCE(paid_at, issued_date, created_at), '%Y-%m') AS ym,
            COALESCE(SUM(amount), 0) AS total
            FROM invoices
            WHERE deleted_at IS NULL
              AND status_paid = 'paid'
              AND COALESCE(paid_at, issued_date, created_at) >= '$since'
            GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($collectedRows as $collectedRow) {
            $idx = array_search((string) ($collectedRow['ym'] ?? ''), $chart_month_keys, true);
            if ($idx !== false) {
                $chart_collected[$idx] = (float) ($collectedRow['total'] ?? 0);
            }
        }

        $outstandingRows = $pdo->query("SELECT DATE_FORMAT(COALESCE(issued_date, created_at), '%Y-%m') AS ym,
            COALESCE(SUM(CASE WHEN status_paid IN ('unpaid', 'partial') THEN amount ELSE 0 END), 0) AS total
            FROM invoices
            WHERE deleted_at IS NULL
              AND COALESCE(issued_date, created_at) >= '$since'
            GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($outstandingRows as $outstandingRow) {
            $idx = array_search((string) ($outstandingRow['ym'] ?? ''), $chart_month_keys, true);
            if ($idx !== false) {
                $chart_outstanding[$idx] = (float) ($outstandingRow['total'] ?? 0);
            }
        }
    }

    if ($dash_show_revenue) {
        $top_clients = $pdo->query("
            SELECT
                COALESCE(c.id, 0) AS client_id,
                COALESCE(NULLIF(TRIM(c.name), ''), 'Walk-in') AS name,
                COUNT(i.id) AS invoice_count,
                COUNT(CASE WHEN i.status_paid IN ('unpaid', 'partial') THEN 1 END) AS open_invoice_count,
                COUNT(CASE WHEN i.status_paid = 'paid' THEN 1 END) AS paid_invoice_count,
                COALESCE(SUM(CASE WHEN i.status_paid = 'paid' THEN i.amount ELSE 0 END), 0) AS paid_revenue,
                COALESCE(SUM(CASE WHEN i.status_paid IN ('unpaid', 'partial') THEN i.amount ELSE 0 END), 0) AS outstanding,
                COUNT(CASE WHEN i.status_paid IN ('unpaid', 'partial')
                    AND i.due_date IS NOT NULL AND i.due_date < CURDATE() THEN 1 END) AS overdue_invoice_count
            FROM invoices i
            LEFT JOIN quotations q ON i.quotation_id = q.id AND q.deleted_at IS NULL
            LEFT JOIN job_cards jc ON jc.id = q.job_card_id AND jc.deleted_at IS NULL
            LEFT JOIN clients c ON c.id = COALESCE(q.client_id, jc.client_id)
            WHERE i.deleted_at IS NULL
            GROUP BY COALESCE(c.id, 0), COALESCE(NULLIF(TRIM(c.name), ''), 'Walk-in')
            HAVING paid_revenue > 0 OR outstanding > 0
            ORDER BY paid_revenue DESC, outstanding DESC, invoice_count DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $top_clients = $pdo->query("
            SELECT
                COALESCE(c.id, 0) AS client_id,
                COALESCE(NULLIF(TRIM(c.name), ''), 'Walk-in') AS name,
                COUNT(i.id) AS invoice_count,
                COUNT(CASE WHEN i.status_paid IN ('unpaid', 'partial') THEN 1 END) AS open_invoice_count,
                COUNT(CASE WHEN i.status_paid = 'paid' THEN 1 END) AS paid_invoice_count,
                COUNT(CASE WHEN i.status_paid IN ('unpaid', 'partial')
                    AND i.due_date IS NOT NULL AND i.due_date < CURDATE() THEN 1 END) AS overdue_invoice_count
            FROM invoices i
            LEFT JOIN quotations q ON i.quotation_id = q.id AND q.deleted_at IS NULL
            LEFT JOIN job_cards jc ON jc.id = q.job_card_id AND jc.deleted_at IS NULL
            LEFT JOIN clients c ON c.id = COALESCE(q.client_id, jc.client_id)
            WHERE i.deleted_at IS NULL
            GROUP BY COALESCE(c.id, 0), COALESCE(NULLIF(TRIM(c.name), ''), 'Walk-in')
            HAVING invoice_count > 0
            ORDER BY invoice_count DESC, open_invoice_count DESC, paid_invoice_count DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
    // ── Monthly job-card activity (all roles) — last 6 months ──
    $jc_month_keys = [];
    for ($m = 5; $m >= 0; $m--) {
        $ts = strtotime('-' . $m . ' months');
        $jc_month_keys[] = date('Y-m', $ts);
        $chart_trend_labels[] = date('M', $ts);
    }
    $chart_jc_counts = array_fill(0, 6, 0);
    $jcSince = date('Y-m-01', strtotime('-5 months'));
    $jcTrendRows = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c
        FROM job_cards
        WHERE deleted_at IS NULL AND created_at >= '$jcSince'
        GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($jcTrendRows as $jcTrendRow) {
        $idx = array_search((string) ($jcTrendRow['ym'] ?? ''), $jc_month_keys, true);
        if ($idx !== false) {
            $chart_jc_counts[$idx] = (int) ($jcTrendRow['c'] ?? 0);
        }
    }
} catch (Exception $e) {
    error_log('Dashboard metrics error: ' . $e->getMessage());
}

if ($chart_trend_labels === []) {
    for ($m = 5; $m >= 0; $m--) {
        $chart_trend_labels[] = date('M', strtotime('-' . $m . ' months'));
    }
    $chart_jc_counts = array_fill(0, 6, 0);
}

if ($dash_show_revenue && $chart_month_labels === []) {
    for ($m = 5; $m >= 0; $m--) {
        $chart_month_labels[] = date('M Y', strtotime('-' . $m . ' months'));
    }
    $chart_collected = array_fill(0, 6, 0.0);
    $chart_outstanding = array_fill(0, 6, 0.0);
}

$last_resume_page = user_resume_get_last($user_id);

$dash_tasks = [];
if ($last_resume_page !== null && trim((string) ($last_resume_page['page_url'] ?? '')) !== '') {
    $dash_tasks[] = [
        'title' => 'Resume — ' . trim((string) ($last_resume_page['title'] ?? 'Last page')),
        'meta' => 'Continue where you left off',
        'url' => (string) $last_resume_page['page_url'],
        'badge' => 'Resume',
        'badge_class' => 'rd-task-badge--resume',
    ];
}

$dash_followups = [
    [
        'show' => $inv_overdue_count > 0,
        'title' => 'Overdue invoices',
        'meta' => $dash_show_revenue
            ? $inv_overdue_count . ' past due · ' . formatMoney($inv_overdue_amount)
            : $inv_overdue_count . ' past due',
        'url' => 'Invoice/invoices.php',
        'badge' => 'Urgent',
        'badge_class' => 'rd-task-badge--urgent',
    ],
    [
        'show' => $inv_outstanding_count > 0,
        'title' => 'Unpaid invoices',
        'meta' => $dash_show_revenue
            ? $inv_outstanding_count . ' open · ' . formatMoney($inv_outstanding_amount)
            : $inv_outstanding_count . ' open',
        'url' => 'Invoice/invoices.php',
        'badge' => 'Open',
        'badge_class' => 'rd-task-badge--open',
    ],
    [
        'show' => $jc_awaiting_quote_count > 0,
        'title' => 'Job cards awaiting quotation',
        'meta' => $jc_awaiting_quote_count . ' open · no quotation linked yet',
        'url' => 'JobCard/job_card.php?view=open',
        'badge' => 'Quote',
        'badge_class' => 'rd-task-badge--review',
    ],
    [
        'show' => $jc_in_progress_count > 0,
        'title' => 'Jobs in progress',
        'meta' => $jc_in_progress_count . ' underway in the workshop',
        'url' => 'JobCard/job_card.php?view=in_progress',
        'badge' => 'Active',
        'badge_class' => 'rd-task-badge--open',
    ],
    [
        'show' => $qt_pending_count > 0,
        'title' => 'Pending quotations',
        'meta' => $qt_pending_count . ' waiting for manager / admin review',
        'url' => 'Quotation/quotations.php',
        'badge' => 'Review',
        'badge_class' => 'rd-task-badge--review',
    ],
    [
        'show' => $qt_awaiting_manager_count > 0,
        'title' => 'Awaiting manager signature',
        'meta' => $qt_awaiting_manager_count . ' need paper sign-off recorded',
        'url' => 'Quotation/quotations.php',
        'badge' => 'Sign-off',
        'badge_class' => 'rd-task-badge--sign',
    ],
    [
        'show' => $qt_awaiting_client_count > 0,
        'title' => 'Awaiting client response',
        'meta' => $qt_awaiting_client_count . ' approved · client has not answered',
        'url' => 'Quotation/quotations.php',
        'badge' => 'Client',
        'badge_class' => 'rd-task-badge--sign',
    ],
];

foreach ($dash_followups as $item) {
    if (empty($item['show'])) {
        continue;
    }
    $dash_tasks[] = [
        'title' => (string) $item['title'],
        'meta' => (string) $item['meta'],
        'url' => (string) $item['url'],
        'badge' => (string) $item['badge'],
        'badge_class' => (string) $item['badge_class'],
    ];
}

$notifFollowup = erp_latest_unread_notification_task($pdo, $user_id, static function (string $link): string {
    if ($link === '' || $link === '#') {
        return 'dashboard.php';
    }
    if (preg_match('#^https?://#i', $link) && preg_match('#/Admin/(.+)$#i', $link, $m)) {
        return $m[1];
    }
    if (preg_match('#^https?://#i', $link)) {
        return $link;
    }
    if (str_starts_with($link, 'Admin/')) {
        return substr($link, 6);
    }
    return ltrim($link, '/');
});
if ($notifFollowup !== null) {
    array_unshift($dash_tasks, $notifFollowup);
}

if ($dash_tasks === []) {
    $dash_tasks[] = [
        'title' => 'All caught up',
        'meta' => 'No follow-ups right now',
        'url' => 'dashboard.php',
        'badge' => 'Clear',
        'badge_class' => 'rd-task-badge--done',
    ];
}

include __DIR__ . '/includes/header.php';
?>

<style>
    .erp-page-header .erp-breadcrumb { display: none !important; }
</style>

<div id="rdDashHeaderLeft" hidden>
    <h2 class="rd-dash-welcome"><?php echo htmlspecialchars(erp_dashboard_greeting_line(), ENT_QUOTES, 'UTF-8'); ?></h2>
    <p class="rd-dash-welcome-sub"><?php echo date('l, F j, Y'); ?> &middot; <?php echo count($dash_tasks); ?> action item<?php echo count($dash_tasks) !== 1 ? 's' : ''; ?> awaiting</p>
</div>

<?php
/* ── Computed helpers ── */
$jc_total_active = $jc_open_count + $jc_in_progress_count + $jc_awaiting_quote_count;

/* Map badge class → flat icon tint token and icon */
function vt_card_banner(string $bc): array {
    switch ($bc) {
        case 'rd-task-badge--urgent': return ['red',    'fa-circle-exclamation'];
        case 'rd-task-badge--review': return ['blue',   'fa-magnifying-glass'];
        case 'rd-task-badge--sign':   return ['violet', 'fa-pen-to-square'];
        case 'rd-task-badge--done':   return ['green',  'fa-circle-check'];
        case 'rd-task-badge--resume': return ['amber',  'fa-arrow-rotate-right'];
        default:                      return ['orange', 'fa-clipboard-list'];
    }
}
?>
<div class="vt-dashboard">

    <!-- ── Analytics grid: LEFT (stats + chart) | RIGHT (donut spanning) ── -->
    <div class="vt-analytics-grid">

        <!-- ─ Left column ─────────────────────────────────────────── -->
        <div class="vt-analytics-left">

            <!-- 3 Stat Cards -->
            <div class="vt-stat-row" aria-label="Key metrics">

                <a href="JobCard/job_card.php?view=open" class="vt-stat-card">
                    <div class="vt-stat-card-top">
                        <span class="vt-stat-label">Job Cards Open</span>
                        <div class="vt-stat-icon vt-stat-icon--orange" aria-hidden="true"><i class="fas fa-clipboard-list"></i></div>
                    </div>
                    <div class="vt-stat-value"><?php echo number_format($jc_open_count); ?></div>
                    <?php if ($jc_in_progress_count > 0 || $jc_awaiting_quote_count > 0): ?>
                    <span class="vt-stat-change vt-stat-change--info">
                        <i class="fas fa-arrow-trend-up" aria-hidden="true"></i>
                        <?php echo number_format($jc_in_progress_count); ?> in progress &middot; <?php echo number_format($jc_awaiting_quote_count); ?> awaiting quote
                    </span>
                    <?php else: ?>
                    <span class="vt-stat-change vt-stat-change--muted">
                        <?php echo $dash_show_revenue ? 'No activity sub-data' : ($dash_jc_today > 0 ? '+' . $dash_jc_today . ' today' : $dash_jc_week . ' this week'); ?>
                    </span>
                    <?php endif; ?>
                </a>

                <a href="Quotation/quotations.php" class="vt-stat-card">
                    <div class="vt-stat-card-top">
                        <span class="vt-stat-label">Quotations Pending</span>
                        <div class="vt-stat-icon vt-stat-icon--violet" aria-hidden="true"><i class="fas fa-file-invoice"></i></div>
                    </div>
                    <div class="vt-stat-value"><?php echo number_format($qt_pending_count); ?></div>
                    <?php if ($qt_awaiting_manager_count > 0 || $qt_awaiting_client_count > 0): ?>
                    <span class="vt-stat-change vt-stat-change--warn">
                        <i class="fas fa-clock" aria-hidden="true"></i>
                        <?php echo number_format($qt_awaiting_manager_count); ?> awaiting mgr &middot; <?php echo number_format($qt_awaiting_client_count); ?> client
                    </span>
                    <?php else: ?>
                    <span class="vt-stat-change vt-stat-change--muted">
                        <?php echo $dash_show_revenue ? 'Up to date' : ($dash_qt_today > 0 ? '+' . $dash_qt_today . ' today' : $dash_qt_week . ' this week'); ?>
                    </span>
                    <?php endif; ?>
                </a>

                <a href="Invoice/invoices.php" class="vt-stat-card">
                    <div class="vt-stat-card-top">
                        <span class="vt-stat-label">Invoices Outstanding</span>
                        <div class="vt-stat-icon vt-stat-icon--amber" aria-hidden="true"><i class="fas fa-receipt"></i></div>
                    </div>
                    <div class="vt-stat-value"><?php echo number_format($inv_outstanding_count); ?></div>
                    <?php if ($inv_overdue_count > 0): ?>
                    <span class="vt-stat-change vt-stat-change--down">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <?php echo number_format($inv_overdue_count); ?> overdue<?php if ($dash_show_revenue): ?> &middot; <?php echo htmlspecialchars(formatMoney($inv_outstanding_amount), ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                    </span>
                    <?php elseif ($dash_show_revenue && $inv_outstanding_amount > 0): ?>
                    <span class="vt-stat-change vt-stat-change--warn">
                        <i class="fas fa-arrow-trend-up" aria-hidden="true"></i>
                        <?php echo htmlspecialchars(formatMoney($inv_outstanding_amount), ENT_QUOTES, 'UTF-8'); ?> total outstanding
                    </span>
                    <?php else: ?>
                    <span class="vt-stat-change vt-stat-change--up">
                        <i class="fas fa-check" aria-hidden="true"></i>
                        None overdue
                    </span>
                    <?php endif; ?>
                </a>

            </div><!-- end vt-stat-row -->

            <!-- Trend line chart (Market Value Trends equivalent) -->
            <div class="vt-chart-card">
                <div class="vt-chart-card-head">
                    <div>
                        <h2 class="vt-chart-card-title"><?php echo $dash_show_revenue ? 'Revenue Trend' : 'Job Card Activity'; ?></h2>
                        <span class="vt-chart-card-sub"><?php echo $dash_show_revenue ? 'Collected revenue over the last 6 months' : 'Job cards created over the last 6 months'; ?></span>
                    </div>
                    <div class="vt-chart-legend">
                        <span class="vt-chart-legend-item"><span class="vt-chart-legend-dot" style="background:#f97316"></span> <?php echo $dash_show_revenue ? 'Collected' : 'Job cards'; ?></span>
                    </div>
                </div>
                <div class="vt-chart-wrap">
                    <canvas id="dashTrendChart" aria-label="<?php echo $dash_show_revenue ? 'Monthly collected revenue trend' : 'Monthly job card activity'; ?>"></canvas>
                </div>
            </div>

        </div><!-- end vt-analytics-left -->

        <!-- ─ Right column: Job Status donut spanning full height ─── -->
        <div class="vt-donut-panel">
            <h2 class="vt-donut-panel-title">Job Status</h2>

            <!-- Donut chart with total count in center -->
            <div class="vt-donut-figure">
                <canvas id="dashDonutChart" aria-label="Job card status breakdown"></canvas>
                <div class="vt-donut-center-overlay" aria-hidden="true">
                    <span class="vt-donut-center-num"><?php echo number_format($jc_total_active); ?></span>
                    <span class="vt-donut-center-lbl">Active jobs</span>
                </div>
            </div>

            <!-- Legend with colored lines -->
            <ul class="vt-donut-legend-list" aria-label="Job status breakdown">
                <li class="vt-donut-legend-item">
                    <span class="vt-donut-legend-line" style="background:#f97316" aria-hidden="true"></span>
                    <span class="vt-donut-legend-lbl">Open</span>
                    <span class="vt-donut-legend-cnt"><?php echo number_format($jc_open_count); ?></span>
                </li>
                <li class="vt-donut-legend-item">
                    <span class="vt-donut-legend-line" style="background:#22c55e" aria-hidden="true"></span>
                    <span class="vt-donut-legend-lbl">In Progress</span>
                    <span class="vt-donut-legend-cnt"><?php echo number_format($jc_in_progress_count); ?></span>
                </li>
                <li class="vt-donut-legend-item">
                    <span class="vt-donut-legend-line" style="background:#f59e0b" aria-hidden="true"></span>
                    <span class="vt-donut-legend-lbl">Awaiting Quote</span>
                    <span class="vt-donut-legend-cnt"><?php echo number_format($jc_awaiting_quote_count); ?></span>
                </li>
            </ul>

            <!-- Footer: revenue total (admin) or invoice summary (others) -->
            <div class="vt-donut-footer">
                <?php if ($dash_show_revenue): ?>
                <span class="vt-donut-footer-label">Total Revenue Collected</span>
                <span class="vt-donut-footer-value"><?php echo htmlspecialchars(formatMoney($inv_paid_amount), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="vt-donut-footer-sub"><?php echo htmlspecialchars(formatMoney($inv_paid_month_amount), ENT_QUOTES, 'UTF-8'); ?> collected this month</span>
                <?php else: ?>
                <span class="vt-donut-footer-label">Invoices</span>
                <span class="vt-donut-footer-value"><?php echo number_format($inv_outstanding_count); ?> <span style="font-size:14px;font-weight:600;color:#9ca3af">outstanding</span></span>
                <span class="vt-donut-footer-sub"><?php echo $inv_overdue_count > 0 ? number_format($inv_overdue_count) . ' overdue' : 'None overdue'; ?></span>
                <?php endif; ?>
            </div>

        </div><!-- end vt-donut-panel -->

    </div><!-- end vt-analytics-grid -->

    <!-- ── Action Items (like "Featured Properties") ─────────────── -->
    <section class="vt-action-section" aria-label="Action items">
        <div class="vt-section-head">
            <h2 class="vt-section-title">Action Items</h2>
            <a href="Invoice/invoices.php" class="vt-section-link">View all</a>
        </div>
        <div class="vt-action-grid">
            <?php
            $displayTasks = array_slice($dash_tasks, 0, 4);
            foreach ($displayTasks as $task):
                [$iconTone, $iconName] = vt_card_banner((string) $task['badge_class']);
            ?>
            <a href="<?php echo htmlspecialchars((string) $task['url'], ENT_QUOTES, 'UTF-8'); ?>" class="vt-action-card">
                <div class="vt-action-card-top">
                    <span class="vt-action-card-icon vt-action-card-icon--<?php echo htmlspecialchars($iconTone, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true">
                        <i class="fas <?php echo htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8'); ?>"></i>
                    </span>
                    <span class="vt-badge <?php echo htmlspecialchars((string) $task['badge_class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $task['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="vt-action-card-title"><?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="vt-action-card-meta"><?php echo htmlspecialchars((string) $task['meta'], ENT_QUOTES, 'UTF-8'); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

</div><!-- end vt-dashboard -->

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
/* ── Mount greeting into topbar ── */
(function () {
    function mountDashHeader() {
        var center = document.getElementById('erpTopbarCenter');
        var el = document.getElementById('rdDashHeaderLeft');
        if (!center || !el) return;
        center.innerHTML = '';
        el.hidden = false;
        center.appendChild(el);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountDashHeader);
    } else {
        mountDashHeader();
    }
})();

/* ── Theme-sync ── */
window.dashUpdateChartsTheme = function (isDark) {
    var tick = isDark ? '#a1a1aa' : '#94a3b8';
    var grid = isDark ? 'rgba(255,255,255,0.08)' : '#f1f5f9';
    if (window.dashTrendChart) {
        var ro = window.dashTrendChart.options;
        if (ro.scales && ro.scales.x) ro.scales.x.ticks.color = tick;
        if (ro.scales && ro.scales.y) { ro.scales.y.ticks.color = tick; ro.scales.y.grid.color = grid; }
        window.dashTrendChart.update('none');
    }
};
document.addEventListener('erp-theme-change', function (e) {
    if (typeof window.dashUpdateChartsTheme === 'function') window.dashUpdateChartsTheme(e.detail === 'dark');
});

/* ── Job Status donut ── */
(function () {
    var canvas = document.getElementById('dashDonutChart');
    if (!canvas || !window.Chart) return;
    var total = <?php echo json_encode($jc_total_active); ?>;
    window.dashDonutChart = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Open', 'In Progress', 'Awaiting Quote'],
            datasets: [{
                data: [
                    <?php echo json_encode($jc_open_count); ?>,
                    <?php echo json_encode($jc_in_progress_count); ?>,
                    <?php echo json_encode($jc_awaiting_quote_count); ?>
                ],
                backgroundColor: ['#f97316', '#22c55e', '#f59e0b'],
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
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed; }
                    }
                }
            }
        }
    });
})();

/* ── Trend area line chart (Market Value Trends equivalent) ── */
(function () {
    var canvas = document.getElementById('dashTrendChart');
    if (!canvas || !window.Chart) return;

    <?php if ($dash_show_revenue): ?>
    var labels = <?php echo json_encode($chart_month_labels, JSON_UNESCAPED_UNICODE); ?>;
    var values = <?php echo json_encode($chart_collected); ?>;
    var isMoney = true;
    <?php else: ?>
    var labels = <?php echo json_encode($chart_trend_labels, JSON_UNESCAPED_UNICODE); ?>;
    var values = <?php echo json_encode($chart_jc_counts); ?>;
    var isMoney = false;
    <?php endif; ?>

    var ctx = canvas.getContext('2d');
    var grad = ctx.createLinearGradient(0, 0, 0, 240);
    grad.addColorStop(0, 'rgba(249, 115, 22, 0.22)');
    grad.addColorStop(1, 'rgba(249, 115, 22, 0)');

    window.dashTrendChart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                data: values,
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
                        label: function (ctx) {
                            var v = ctx.parsed.y;
                            return isMoney ? '  ' + v.toLocaleString(undefined, {style:'currency', currency:'NAD'}) : '  ' + v + ' job card' + (v === 1 ? '' : 's');
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
                y: { beginAtZero: true, border: { display: false }, grid: { color: '#f1f5f9' }, ticks: { color: '#94a3b8', font: { size: 11 }, precision: isMoney ? undefined : 0, maxTicksLimit: 5 } }
            }
        }
    });
    if (document.documentElement.classList.contains('rd-dash-theme-dark')) window.dashUpdateChartsTheme(true);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
