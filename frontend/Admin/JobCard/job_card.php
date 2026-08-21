<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/jc_status.php';
require_once __DIR__ . '/../includes/student_table.inc.php';
require_once __DIR__ . '/../includes/operations_docs_layout.inc.php';
require_admin();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$business = getBusiness();
$currency = getCurrency();
$can_view_financial = erp_can_view_financial_summary();

/** Match overview SQL periods (created_at). */
function jc_created_in_period(?string $raw, string $period): bool
{
    if ($raw === null || trim($raw) === '') {
        return false;
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return false;
    }
    $ymd = date('Y-m-d', $ts);
    $nowYmd = date('Y-m-d');
    switch ($period) {
        case 'today':
            return $ymd === $nowYmd;
        case 'week':
            return jc_yearweek_mode1($ymd) === jc_yearweek_mode1($nowYmd);
        case 'month':
            return date('Y-m', $ts) === date('Y-m');
        case 'year':
            return date('Y', $ts) === date('Y');
        default:
            return true;
    }
}

function jc_list_date_cell(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '-';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '-';
    }
    return htmlspecialchars(date('d M Y', $ts), ENT_QUOTES, 'UTF-8');
}

function jc_list_text_snippet(?string $raw, int $max = 56): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
    if ($text === '') {
        return '-';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) > $max) {
            return htmlspecialchars(mb_substr($text, 0, $max - 1) . '…', ENT_QUOTES, 'UTF-8');
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    if (strlen($text) > $max) {
        return htmlspecialchars(substr($text, 0, $max - 1) . '…', ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function jc_service_type_label(?string $type): string
{
    $t = strtolower(trim((string) $type));
    if ($t === 'mobile') {
        return 'Mobile';
    }
    return 'In shop';
}

function jc_service_type_class(?string $type): string
{
    return strtolower(trim((string) $type)) === 'mobile' ? 'jc-service-mobile' : 'jc-service-inshop';
}

function jc_labor_hours_display(array $job): string
{
    $hours = (float) ($job['total_hours'] ?? 0);
    if ($hours <= 0) {
        $hours = (float) ($job['labor_hours'] ?? 0);
    }
    if ($hours <= 0) {
        return '-';
    }
    return rtrim(rtrim(number_format($hours, 2), '0'), '.') . ' h';
}

function jc_quote_status_label(?string $status): string
{
    $map = [
        'pending' => 'Pending',
        'pending_manager' => 'Mgr review',
        'sent_back_admin' => 'Sent back',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'draft' => 'Draft',
    ];
    $s = strtolower(trim((string) $status));
    return $map[$s] ?? ($s !== '' ? ucfirst(str_replace('_', ' ', $s)) : '');
}

function jc_quote_status_pill_class(?string $status): string
{
    $s = strtolower(trim((string) $status));
    if ($s === 'approved') {
        return 'jc-quote-pill--approved';
    }
    if ($s === 'rejected') {
        return 'jc-quote-pill--rejected';
    }
    if (in_array($s, ['pending', 'pending_manager', 'sent_back_admin', 'draft'], true)) {
        return 'jc-quote-pill--pending';
    }
    return '';
}

/** Job workflow dots for list rows (mirrors invoice / quotation progress column). */
function jc_workflow_from_row(string $status, bool $hasQuote): array
{
    $s = jc_status_normalize($status);
    $isComplete = in_array($s, ['completed', 'invoiced', 'paid'], true);
    $isInProgress = in_array($s, ['in_progress', 'waiting_parts'], true);
    $isOpen = !$isComplete;

    $steps = [
        ['done' => true, 'current' => false],
        ['done' => $isInProgress || $isComplete, 'current' => $isInProgress],
        ['done' => $hasQuote, 'current' => $isOpen && !$hasQuote],
        ['done' => $isComplete, 'current' => false],
    ];

    if ($isComplete) {
        $nextLabel = 'Complete';
        $nextHint = 'Job finished';
    } elseif ($hasQuote) {
        $nextLabel = 'Quoted';
        $nextHint = 'Quotation linked';
    } elseif ($isInProgress) {
        $nextLabel = 'In progress';
        $nextHint = 'Work underway';
    } elseif ($s === 'new') {
        $nextLabel = 'New';
        $nextHint = 'Awaiting start';
    } else {
        $nextLabel = 'Draft';
        $nextHint = 'Not saved yet';
    }

    return ['steps' => $steps, 'next_label' => $nextLabel, 'next_hint' => $nextHint];
}

$stmt = $pdo->query("SELECT 
    COUNT(DISTINCT jc.id) AS total_count,
    COUNT(DISTINCT CASE WHEN " . jc_status_open_sql('jc') . " THEN jc.id END) AS open_count,
    COUNT(DISTINCT CASE WHEN " . jc_status_pending_sql('jc') . " THEN jc.id END) AS pending_count,
    COUNT(DISTINCT CASE WHEN " . jc_status_in_progress_sql('jc') . " THEN jc.id END) AS in_progress_count,
    COUNT(DISTINCT CASE WHEN " . jc_status_complete_sql('jc') . " THEN jc.id END) AS complete_count,
    COALESCE(SUM(quote_totals.amount), 0) AS quoted_amount,
    COUNT(DISTINCT CASE WHEN DATE(jc.created_at) = CURDATE() THEN jc.id END) AS today_count,
    COALESCE(SUM(CASE WHEN DATE(jc.created_at) = CURDATE() THEN quote_totals.amount ELSE 0 END), 0) AS today_amount,
    COUNT(DISTINCT CASE WHEN YEARWEEK(DATE(jc.created_at), 1) = YEARWEEK(CURDATE(), 1) THEN jc.id END) AS week_count,
    COALESCE(SUM(CASE WHEN YEARWEEK(DATE(jc.created_at), 1) = YEARWEEK(CURDATE(), 1) THEN quote_totals.amount ELSE 0 END), 0) AS week_amount,
    COUNT(DISTINCT CASE WHEN YEAR(jc.created_at) = YEAR(CURDATE()) AND MONTH(jc.created_at) = MONTH(CURDATE()) THEN jc.id END) AS month_count,
    COALESCE(SUM(CASE WHEN YEAR(jc.created_at) = YEAR(CURDATE()) AND MONTH(jc.created_at) = MONTH(CURDATE()) THEN quote_totals.amount ELSE 0 END), 0) AS month_amount
FROM job_cards jc
LEFT JOIN (
    SELECT job_card_id, SUM(amount) AS amount
    FROM quotations
    WHERE deleted_at IS NULL
    GROUP BY job_card_id
) quote_totals ON jc.id = quote_totals.job_card_id
WHERE jc.deleted_at IS NULL");
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Overview cards — row 1: job status (like quotation workflow row); row 2: Today → week → month → All
$jc_dashboard_cards = [
    [
        'section' => 'status',
        'tone' => 'blue',
        'label' => 'Open Jobs',
        'value' => number_format((int) ($summary['open_count'] ?? 0)),
        'sub' => 'Active · not completed',
        'icon' => 'fa-tools',
        'view' => 'open',
    ],
    [
        'section' => 'status',
        'tone' => 'amber',
        'label' => 'Pending',
        'value' => number_format((int) ($summary['pending_count'] ?? 0)),
        'sub' => 'Not started yet',
        'icon' => 'fa-hourglass-half',
        'view' => 'pending',
    ],
    [
        'section' => 'status',
        'tone' => 'cyan',
        'label' => 'In Progress',
        'value' => number_format((int) ($summary['in_progress_count'] ?? 0)),
        'sub' => 'Work underway',
        'icon' => 'fa-wrench',
        'view' => 'in_progress',
    ],
    [
        'section' => 'status',
        'tone' => 'green',
        'label' => 'Completed',
        'value' => number_format((int) ($summary['complete_count'] ?? 0)),
        'sub' => 'Marked complete',
        'icon' => 'fa-check-double',
        'view' => 'complete',
    ],
    [
        'section' => 'period',
        'tone' => 'slate',
        'label' => 'Today',
        'value' => number_format((int) ($summary['today_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            date('d M Y'),
            date('d M Y') . ' · ' . formatMoney((float) ($summary['today_amount'] ?? 0))
        ),
        'icon' => 'fa-calendar-day',
        'view' => 'today',
    ],
    [
        'section' => 'period',
        'tone' => 'purple',
        'label' => 'This week',
        'value' => number_format((int) ($summary['week_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'Mon – Sun',
            'Mon – Sun · ' . formatMoney((float) ($summary['week_amount'] ?? 0))
        ),
        'icon' => 'fa-calendar-week',
        'view' => 'week',
    ],
    [
        'section' => 'period',
        'tone' => 'cyan',
        'label' => 'This month',
        'value' => number_format((int) ($summary['month_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            date('F Y'),
            date('F Y') . ' · ' . formatMoney((float) ($summary['month_amount'] ?? 0))
        ),
        'icon' => 'fa-calendar-alt',
        'view' => 'month',
    ],
    [
        'section' => 'period',
        'tone' => 'orange',
        'label' => 'All Job Cards',
        'value' => number_format((int) ($summary['total_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'All job cards',
            formatMoney((float) ($summary['quoted_amount'] ?? 0)) . ' quoted'
        ),
        'icon' => 'fa-clipboard-list',
        'view' => null,
    ],
];

$jc_view_base = app_url('Admin/JobCard/job_card.php');
$jc_overview_view = (string) ($_GET['view'] ?? '');
$jc_valid_overview_views = ['open', 'pending', 'in_progress', 'complete', 'today', 'week', 'month'];
if ($jc_overview_view !== '' && !in_array($jc_overview_view, $jc_valid_overview_views, true)) {
    $jc_overview_view = '';
}

$stmt = $pdo->query("SELECT 
    jc.id, jc.card_number, jc.extra_data, jc.progress, jc.labor_hours, jc.total_hours,
    jc.description, jc.service_type, jc.work_date, jc.started_at, jc.completed_at,
    jc.updated_at, jc.created_at, jc.status AS job_status,
    COALESCE(jc_client.name, v_client.name, 'Walk-in') AS client_name,
    v.reg_no, v.model,
    e.name AS technician_name,
    q.id AS quotation_id, q.status AS quote_status, q.amount AS quote_amount,
    inv.id AS invoice_id, inv.invoice_number, inv.status_paid AS invoice_status
FROM job_cards jc
LEFT JOIN clients jc_client ON jc.client_id = jc_client.id
LEFT JOIN vehicles v ON jc.vehicle_id = v.id
LEFT JOIN clients v_client ON v.client_id = v_client.id
LEFT JOIN employees e ON jc.technician_id = e.id
LEFT JOIN (
    SELECT q0.*
    FROM quotations q0
    INNER JOIN (
        SELECT job_card_id, MAX(id) AS max_qid
        FROM quotations
        WHERE deleted_at IS NULL
        GROUP BY job_card_id
    ) qpick ON q0.id = qpick.max_qid
) q ON q.job_card_id = jc.id
LEFT JOIN invoices inv ON inv.quotation_id = q.id
WHERE jc.deleted_at IS NULL
ORDER BY jc.id DESC");

$job_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jc_total_count = (int) ($summary['total_count'] ?? 0);
$jc_open_n = (int) ($summary['open_count'] ?? 0);
$jc_pending_n = (int) ($summary['pending_count'] ?? 0);
$jc_progress_n = (int) ($summary['in_progress_count'] ?? 0);
$jc_complete_n = (int) ($summary['complete_count'] ?? 0);
$jc_today_n = (int) ($summary['today_count'] ?? 0);

$jc_folders = [
    ['label' => 'Open Jobs', 'count' => $jc_open_n, 'unit' => 'cards', 'icon' => 'fa-folder-open', 'tone' => 'blue', 'clickable' => true, 'class' => 'jc-stat-clickable', 'attrs' => ['data-jc-card-view' => 'open', 'data-jc-card-label' => 'Open Jobs']],
    ['label' => 'Pending', 'count' => $jc_pending_n, 'unit' => 'cards', 'icon' => 'fa-folder', 'tone' => 'amber', 'clickable' => true, 'class' => 'jc-stat-clickable', 'attrs' => ['data-jc-card-view' => 'pending', 'data-jc-card-label' => 'Pending']],
    ['label' => 'In Progress', 'count' => $jc_progress_n, 'unit' => 'cards', 'icon' => 'fa-folder', 'tone' => 'cyan', 'clickable' => true, 'class' => 'jc-stat-clickable', 'attrs' => ['data-jc-card-view' => 'in_progress', 'data-jc-card-label' => 'In Progress']],
    ['label' => 'Completed', 'count' => $jc_complete_n, 'unit' => 'cards', 'icon' => 'fa-folder', 'tone' => 'green', 'clickable' => true, 'class' => 'jc-stat-clickable', 'attrs' => ['data-jc-card-view' => 'complete', 'data-jc-card-label' => 'Completed']],
    ['label' => 'Today', 'count' => $jc_today_n, 'unit' => 'cards', 'icon' => 'fa-folder', 'tone' => 'slate', 'clickable' => true, 'class' => 'jc-stat-clickable', 'attrs' => ['data-jc-card-view' => 'today', 'data-jc-card-label' => 'Today']],
    ['label' => 'All Job Cards', 'count' => $jc_total_count, 'unit' => 'cards', 'icon' => 'fa-folder', 'tone' => 'orange', 'clickable' => true, 'class' => 'jc-stat-clickable', 'attrs' => ['data-jc-card-view' => 'all', 'data-jc-card-label' => 'All Job Cards']],
];

$jc_sidebar_bars = ops_docs_pct_bars([
    ['label' => 'Open', 'count' => $jc_open_n, 'tone' => 'blue', 'icon' => 'fa-folder-open'],
    ['label' => 'In progress', 'count' => $jc_progress_n, 'tone' => 'amber', 'icon' => 'fa-wrench'],
    ['label' => 'Completed', 'count' => $jc_complete_n, 'tone' => 'green', 'icon' => 'fa-check'],
], max(1, $jc_total_count));

$jc_donut_segments = [
    ['label' => 'Open', 'value' => (string) number_format($jc_open_n), 'pct' => $jc_open_n, 'color' => '#2563eb'],
    ['label' => 'In progress', 'value' => (string) number_format($jc_progress_n), 'pct' => $jc_progress_n, 'color' => '#ea580c'],
    ['label' => 'Completed', 'value' => (string) number_format($jc_complete_n), 'pct' => $jc_complete_n, 'color' => '#059669'],
    ['label' => 'Pending', 'value' => (string) number_format($jc_pending_n), 'pct' => $jc_pending_n, 'color' => '#d97706'],
];

$jc_sidebar_recent = [];
foreach (array_slice($job_cards, 0, 5) as $jcRecent) {
    $clientName = trim((string) ($jcRecent['client_name'] ?? ''));
    $initials = $clientName !== '' ? strtoupper(substr($clientName, 0, 1)) : 'J';
    $jc_sidebar_recent[] = [
        'title' => (string) ($jcRecent['card_number'] ?? 'Job card'),
        'meta' => $clientName !== '' ? $clientName : 'Updated job card',
        'time' => ops_docs_time_ago($jcRecent['updated_at'] ?? $jcRecent['created_at'] ?? null),
        'href' => app_url('Admin/JobCard/view_job_card.php?id=' . (int) ($jcRecent['id'] ?? 0)),
        'initials' => $initials,
    ];
}

$jc_edit_base = app_url('Admin/JobCard/add_job_card.php?edit_id=');
$jc_quote_base = app_url('Admin/Quotation/add_quotation.php?id=');
$jc_invoice_base = app_url('Admin/view_invoice.php?id=');
$jc_table_colspan = 10;

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$jc_list_url = app_url('Admin/JobCard/job_card.php');

// Soft-delete handler (POST — same pattern as quotations list)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete_id']) || isset($_POST['delete_ids']))) {
    $deleteIds = [];
    if (isset($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
        foreach ($_POST['delete_ids'] as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $deleteIds[] = $id;
            }
        }
        $deleteIds = array_values(array_unique($deleteIds));
    } elseif (isset($_POST['delete_id'])) {
        $single = (int) $_POST['delete_id'];
        if ($single > 0) {
            $deleteIds = [$single];
        }
    }

    if ($deleteIds === []) {
        header('Location: ' . $jc_list_url . '?error=' . urlencode('Invalid job card'));
        exit;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
        $stmt = $pdo->prepare("UPDATE job_cards SET deleted_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
        $stmt->execute($deleteIds);
        $deletedCount = $stmt->rowCount();
        if ($deletedCount === 0) {
            header('Location: ' . $jc_list_url . '?error=' . urlencode('Job card not found or already deleted'));
            exit;
        }
        try {
            $log = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)');
            foreach ($deleteIds as $delete_id) {
                $log->execute([$_SESSION['user_id'], 'deleted_job_card', 'job_card', $delete_id]);
            }
        } catch (Exception $logEx) {
            error_log('Job card delete audit log failed: ' . $logEx->getMessage());
        }
        $successMsg = $deletedCount === 1
            ? 'Job card moved to recycle bin'
            : ($deletedCount . ' job cards moved to recycle bin');
        header('Location: ' . $jc_list_url . '?success=' . urlencode($successMsg));
        exit;
    } catch (Exception $e) {
        error_log('Job card delete failed: ' . $e->getMessage());
        header('Location: ' . $jc_list_url . '?error=' . urlencode('Failed to delete job card'));
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
        /* Delete modal — same as add_quotation.php (aq-delete-modal) */
        .aq-s1-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            border: 1px solid transparent;
            background: #fff;
            line-height: 1.2;
            white-space: nowrap;
            justify-content: center;
            text-align: center;
            min-height: 2.75rem;
        }
        .aq-s1-btn--gray { border-color: #cbd5e1; color: #334155; }
        .aq-s1-btn--gray:hover { border-color: #94a3b8; background: #fafafa; }
        .aq-s1-btn--danger { background: #dc2626; color: #fff; border: none; }
        .aq-s1-btn--danger:hover { background: #b91c1c; }
        .aq-name-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1200;
        }
        .aq-name-modal.show { display: flex; }
        .aq-name-modal-card {
            width: min(92vw, 460px);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
            padding: 18px;
        }
        .aq-name-modal-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .aq-name-modal-sub {
            font-size: 0.875rem;
            color: #475569;
            margin-bottom: 10px;
            line-height: 1.5;
        }
        .aq-name-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
        }
        .aq-name-modal-actions form { margin: 0; }

        .qt-col-hint{
            display:block;
            font-size:10px;
            font-weight:600;
            color:#94a3b8;
            text-transform:none;
            letter-spacing:0;
            margin-top:3px;
            line-height:1.2;
            white-space:normal;
        }
        #jobCardsTable.jc-list-table thead .qt-col-hint{
            font-size:9px;
        }
        /* Wide table must not expand .erp-content — keeps overview grid at 4 equal columns */
        .erp-content,
        .erp-main,
        .erp-page-body {
            max-width: 100% !important;
            width: 100% !important;
            min-width: 0;
        }
        /* Table scrolls inside the list card (erp-layout.css .qt-table-scroll) */
        #jcJobCardsList{
            scroll-margin-top:88px;
        }
        #jcJobCardsList .erp-card-body{
            min-width:0;
            max-width:100%;
            padding-bottom:4px;
        }
        #jobCardsTable.jc-list-table tbody tr:last-child td{
            border-bottom:1px solid #e2e8f0;
        }
        #jcJobCardsList .erp-table-container.qt-table-scroll{
            max-width:100%;
            width:100%;
            min-width:0;
            box-sizing:border-box;
            overflow:auto;
            scrollbar-width:thin;
            scrollbar-color:#e2e8f0 #f8fafc;
        }
        #jcJobCardsList .erp-table-container.qt-table-scroll::-webkit-scrollbar{
            width:6px;
            height:6px;
        }
        #jcJobCardsList .erp-table-container.qt-table-scroll::-webkit-scrollbar-track{
            background:#f8fafc;
            border-radius:4px;
        }
        #jcJobCardsList .erp-table-container.qt-table-scroll::-webkit-scrollbar-thumb{
            background:#e2e8f0;
            border-radius:4px;
        }
        #jcJobCardsList .erp-table-container.qt-table-scroll::-webkit-scrollbar-thumb:hover{
            background:#cbd5e1;
        }
        #jobCardsTable.jc-list-table{
            width:100%;
            min-width:960px;
            table-layout:fixed;
            border-collapse:collapse;
            border-spacing:0;
        }
        #jobCardsTable.jc-list-table tbody td{
            vertical-align:middle!important;
        }
        #jobCardsTable.jc-list-table thead th:not(.st-col-actions):not(.jc-actions-col) .st-th-text{
            display:block;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        #jobCardsTable.jc-list-table thead th.st-col-actions .st-th-text,
        #jobCardsTable.jc-list-table thead th.jc-actions-col .st-th-text{
            overflow:visible;
            text-overflow:clip;
        }
        #jobCardsTable.jc-list-table thead th.st-col-check,
        #jobCardsTable.jc-list-table thead th.jc-col-check{
            padding-left:10px!important;
            padding-right:10px!important;
        }
        /* cell padding: student-table.css */
        #jobCardsTable.jc-list-table th,
        #jobCardsTable.jc-list-table td{
            box-sizing:border-box;
            vertical-align:middle;
        }
        /* List table palette — job cards */
        #jobCardsTable.erp-student-table.jc-list-table thead th{
            background:#f8fafc!important;
            color:#475569!important;
            border-bottom:1px solid #e2e8f0!important;
        }
        #jobCardsTable.erp-student-table.jc-list-table thead th.sortable:hover{
            background:#f1f5f9!important;
        }
        #jobCardsTable.erp-student-table.jc-list-table thead th.st-col-actions,
        #jobCardsTable.erp-student-table.jc-list-table thead th.jc-actions-col,
        #jobCardsTable.erp-student-table.jc-list-table thead th.st-col-check,
        #jobCardsTable.erp-student-table.jc-list-table thead th.jc-col-check{
            background:#f1f5f9!important;
        }
        #jobCardsTable.erp-student-table.jc-list-table thead th:last-child{
            background:#f1f5f9!important;
            border-top-right-radius:10px;
        }
        #jobCardsTable.erp-student-table.jc-list-table thead th .st-th-text{color:#475569;}
        #jobCardsTable.erp-student-table.jc-list-table thead .qt-col-hint,
        #jobCardsTable.erp-student-table.jc-list-table .st-th-sub{color:#94a3b8;}
        #jobCardsTable.erp-student-table.jc-list-table tbody td{
            color:#475569!important;
            border-top-color:#e2e8f0!important;
            overflow:hidden;
        }
        #jobCardsTable.erp-student-table.jc-list-table tbody tr:nth-child(odd) td{
            background:#fff!important;
        }
        #jobCardsTable.erp-student-table.jc-list-table tbody tr:nth-child(even) td{
            background:#f8fafc!important;
        }
        #jobCardsTable.erp-student-table.jc-list-table tbody tr:nth-child(even) td:last-child,
        #jobCardsTable.erp-student-table.jc-list-table tbody td:last-child{
            background-color:inherit!important;
        }
        #jobCardsTable.erp-student-table.jc-list-table tbody tr.jc-clickable-row:hover td,
        #jobCardsTable.erp-student-table.jc-list-table tbody tr.qt-row-selected td{
            background:#fff7ed!important;
        }
        #jobCardsTable.erp-student-table .st-cell-primary{color:#1e293b;}
        #jobCardsTable.erp-student-table th.sort-asc .st-sort-up,
        #jobCardsTable.erp-student-table th.sort-desc .st-sort-down{color:#c2410c;}
        #jobCardsTable.erp-student-table .st-act-btn--delete{
            background:#fee2e2!important;
            color:#ef4444!important;
        }
        #jobCardsTable.erp-student-table .st-act-btn--delete:hover{
            background:#fecaca!important;
            color:#dc2626!important;
        }
        #jobCardsTable.jc-list-table tbody td.jc-col-ellipsis{
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        #jobCardsTable.jc-list-table .jc-col-check,
        #jobCardsTable.jc-list-table th.jc-col-check,
        #jobCardsTable.jc-list-table td.jc-col-check{
            text-align:center;
            padding-left:4px;
            padding-right:4px;
        }
        #jobCardsTable.jc-list-table .jc-col-card-no .jc-card-no{
            display:block;
            font-variant-numeric:tabular-nums;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        #jobCardsTable.jc-list-table .jc-col-progress,
        #jobCardsTable.jc-list-table th.jc-col-progress,
        #jobCardsTable.jc-list-table td.jc-col-progress{
            text-align:center;
            white-space:nowrap;
            overflow:visible;
        }
        #jobCardsTable.jc-list-table .jc-col-status,
        #jobCardsTable.jc-list-table .jc-col-client,
        #jobCardsTable.jc-list-table .jc-col-vehicle,
        #jobCardsTable.jc-list-table .jc-col-service,
        #jobCardsTable.jc-list-table .jc-col-work,
        #jobCardsTable.jc-list-table .jc-col-tech,
        #jobCardsTable.jc-list-table .jc-col-quote,
        #jobCardsTable.jc-list-table .jc-col-quote-status,
        #jobCardsTable.jc-list-table .jc-col-invoice,
        #jobCardsTable.jc-list-table .jc-col-created,
        #jobCardsTable.jc-list-table .jc-col-updated{
            text-align:left;
        }
        #jobCardsTable.jc-list-table .jc-col-status{
            white-space:nowrap;
        }
        #jobCardsTable.jc-list-table .jc-col-status .jc-status{
            padding:4px 8px;
            font-size:11px;
            gap:5px;
        }
        #jobCardsTable.jc-list-table .jc-col-service{
            white-space:nowrap;
        }
        #jobCardsTable.jc-list-table .jc-col-labor{
            text-align:center;
            white-space:nowrap;
        }
        #jobCardsTable.jc-list-table .jc-col-created,
        #jobCardsTable.jc-list-table .jc-col-updated{
            white-space:nowrap;
            font-size:11px;
        }
        .jc-cell-muted{color:#64748b;font-weight:600;}
        .jc-col-work-text{
            display:-webkit-box;
            -webkit-line-clamp:2;
            -webkit-box-orient:vertical;
            overflow:hidden;
            font-size:12px;
            font-weight:600;
            color:#475569;
            line-height:1.35;
        }
        .jc-service-tag{
            display:inline-block;
            font-size:10px;
            font-weight:700;
            padding:3px 6px;
            border-radius:6px;
            text-transform:uppercase;
            letter-spacing:.02em;
            max-width:100%;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        .jc-service-inshop{background:#eff6ff;color:#1d4ed8;}
        .jc-service-mobile{background:#fff7ed;color:#c2410c;}
        .jc-quote-pill{
            display:inline-block;
            font-size:11px;
            font-weight:700;
            padding:3px 8px;
            border-radius:999px;
            background:#f1f5f9;
            color:#475569;
        }
        .jc-quote-pill--approved{background:#ecfdf5;color:#15803d;}
        .jc-quote-pill--pending{background:#fff7ed;color:#c2410c;}
        .jc-quote-pill--rejected{background:#fef2f2;color:#b91c1c;}
        .jc-link-invoice{
            font-size:12px;
            font-weight:700;
            color:#0369a1;
            text-decoration:none;
        }
        .jc-link-invoice:hover{text-decoration:underline;}
        .jc-draft-badge{
            display:block;
            margin-top:3px;
            font-size:9px;
            font-weight:700;
            color:#7e22ce;
            background:#f3e8ff;
            padding:2px 5px;
            border-radius:4px;
            width:max-content;
        }
        #jobCardsTable.jc-list-table .jc-col-actions,
        #jobCardsTable.jc-list-table th.jc-actions-col,
        #jobCardsTable.jc-list-table td.jc-actions-cell,
        #jobCardsTable.jc-list-table td.st-col-actions,
        #jobCardsTable.jc-list-table th.st-col-actions{
            text-align:center;
            overflow:visible;
            padding:8px 10px!important;
        }
        .jc-vehicle-model{
            display:block;
            font-weight:700;
            font-size:12px;
            line-height:1.25;
            color:#1e293b;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        .jc-vehicle-reg{
            display:block;
            font-size:10px;
            font-weight:600;
            color:#64748b;
            line-height:1.2;
            margin-top:1px;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        .jc-quote-meta{
            display:block;
            font-size:10px;
            font-weight:600;
            color:#64748b;
            margin-top:1px;
            line-height:1.2;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        #jobCardsTable.jc-list-table .jc-col-quote .jc-link-quote{
            display:block;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            font-size:11px;
        }
        .qt-row-progress{
            display:inline-flex;
            align-items:center;
            gap:4px;
            justify-content:center;
        }
        .qt-row-dot{
            width:10px;height:10px;border-radius:50%;
            background:#e2e8f0;
            border:1px solid #cbd5e1;
            flex-shrink:0;
        }
        .qt-row-dot.is-done{background:#22c55e;border-color:#16a34a;}
        .qt-row-dot.is-current{background:#fb923c;border-color:#ea580c;box-shadow:0 0 0 2px rgba(251,146,60,.35);}
        .qt-row-progress[title]{cursor:help;}
        .jc-card-no{
            font-size:13px;
            font-weight:800;
            color:#1e293b;
        }
        .jc-status{
            display:inline-flex;
            align-items:center;
            gap:7px;
            border-radius:999px;
            padding:6px 12px;
            font-size:12px;
            font-weight:700;
            text-transform:capitalize;
            white-space:nowrap;
        }
        .jc-status::before{
            content:"";
            width:8px;
            height:8px;
            border-radius:50%;
            background:currentColor;
            opacity:.8;
        }
        .jc-status-new,.jc-status-pending{background:#f3e8ff;color:#7e22ce;}
        .jc-status-diagnosed{background:#eff6ff;color:#1d4ed8;}
        .jc-status-quoted{background:#f3e8ff;color:#7e22ce;}
        .jc-status-approved{background:#ecfdf5;color:#15803d;}
        .jc-status-in_progress{background:#fff7ed;color:#c2410c;}
        .jc-status-waiting_parts{background:#fff7ed;color:#c2410c;}
        .jc-status-complete,.jc-status-completed{background:#ecfdf5;color:#15803d;}
        .jc-status-invoiced{background:#e0f2fe;color:#0369a1;}
        .jc-status-paid{background:#dcfce7;color:#166534;}
        .jc-status-draft{background:#f3e8ff;color:#7e22ce;}
        .jc-status-default{background:#f1f5f9;color:#64748b;}
        .jc-clickable-row{
            cursor:pointer;
        }
        .jc-clickable-row td{transition:background .15s ease;}
        .qt-search-hit td,
        .jc-search-hit td{
            background:#ffedd5 !important;
            box-shadow:inset 0 0 0 1px #fdba74;
        }
        .jc-helper-row td{
            text-align:center;
            padding:28px 16px !important;
            color:#64748b !important;
            background:#f8fafc;
        }
        .jc-helper-row i{margin-right:8px;}
        .jc-loading-row td{
            color:#2563eb !important;
            font-weight:700 !important;
        }
        .jc-flash-wrap{
            position:fixed;inset:0;z-index:2200;display:flex;align-items:center;justify-content:center;
            background:rgba(15,23,42,.35);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
            opacity:0;pointer-events:none;transition:opacity .2s ease;
        }
        .jc-flash-wrap.show{opacity:1;pointer-events:auto}
        .jc-flash-card{
            width:min(92vw,420px);background:#fff;border:1px solid #e5e7eb;border-radius:16px;
            box-shadow:0 24px 55px rgba(0,0,0,.25);padding:18px 18px 14px;text-align:center;
            transform:translateY(8px);transition:transform .2s ease;
        }
        .jc-flash-wrap.show .jc-flash-card{transform:translateY(0)}
        .jc-flash-icon{
            width:44px;height:44px;border-radius:999px;margin:0 auto 10px;
            display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;
        }
        .jc-flash-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
        .jc-flash-body{font-size:.9rem;line-height:1.45}
        .jc-flash-card--success .jc-flash-icon{background:#ecfdf3;color:#16a34a;border:1px solid #bbf7d0}
        .jc-flash-card--success .jc-flash-body{color:#166534}
        .jc-flash-card--error .jc-flash-icon{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
        .jc-flash-card--error .jc-flash-body{color:#991b1b}
        .qt-section{margin-bottom:16px;}
        /* Find panel + filter pills: erp-layout.css */
        .qt-section-title{
            margin:0;
            font-size:13px;
            font-weight:900;
            letter-spacing:.05em;
            text-transform:uppercase;
            color:#334155;
        }
        .qt-list-card .erp-card-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
        }
        .qt-list-meta{
            font-size:13px;
            font-weight:600;
            color:#64748b;
        }
        .qt-table-toolbar{
            display:flex;
            align-items:center;
            flex-wrap:wrap;
            gap:12px 16px;
            padding:12px 16px;
            background:#f8fafc;
            border-bottom:1px solid #e2e8f0;
        }
        .qt-select-all{
            display:inline-flex;
            align-items:center;
            gap:8px;
            font-size:13px;
            font-weight:600;
            color:#334155;
            cursor:pointer;
            user-select:none;
            margin:0;
        }
        .qt-select-all input{
            width:16px;
            height:16px;
            margin:0;
            cursor:pointer;
            accent-color:var(--brand-primary);
        }
        .qt-selection-meta{
            font-size:13px;
            font-weight:600;
            color:#64748b;
        }
        .qt-col-check{
            width:1%;
            white-space:nowrap;
            text-align:center;
            vertical-align:middle;
        }
        .qt-col-check input{
            width:16px;
            height:16px;
            margin:0;
            cursor:pointer;
            accent-color:var(--brand-primary);
        }
        tr.qt-row-selected td{
            background:#fff7ed !important;
        }
        /* Scrollbar + max-height: see erp-layout.css (.erp-table-container.qt-table-scroll) */
        .jc-link-quote,.jc-link-invoice{
            font-size:12px;
            font-weight:700;
            color:#c2410c;
            text-decoration:none;
        }
        .jc-link-quote:hover,.jc-link-invoice:hover{text-decoration:underline;color:#9a3412;}
        .jc-link-invoice{color:#0369a1;}
        .jc-link-invoice:hover{color:#1d4ed8;}
        .jc-desc-cell{
            max-width:220px;
            font-size:13px;
            font-weight:500;
            color:#475569;
            line-height:1.4;
        }
        .jc-service-badge{
            display:inline-flex;
            align-items:center;
            padding:4px 10px;
            border-radius:999px;
            font-size:11px;
            font-weight:700;
            white-space:nowrap;
        }
        .jc-service-inshop{background:#eff6ff;color:#1d4ed8;}
        .jc-service-mobile{background:#fff7ed;color:#c2410c;}
        .jc-progress-cell{min-width:88px;}
        .jc-progress-bar{
            height:6px;
            border-radius:999px;
            background:#e2e8f0;
            overflow:hidden;
            margin-top:4px;
        }
        .jc-progress-bar span{
            display:block;
            height:100%;
            background:linear-gradient(90deg,#2563eb,#60a5fa);
            border-radius:999px;
        }
        .jc-progress-pct{
            font-size:12px;
            font-weight:700;
            color:#1e3a8a;
        }
        .jc-quote-meta{
            display:block;
            font-size:11px;
            font-weight:600;
            color:#64748b;
            margin-top:2px;
        }
        .jc-invoice-paid{
            display:inline-flex;
            align-items:center;
            padding:3px 8px;
            border-radius:999px;
            font-size:10px;
            font-weight:700;
            background:#dcfce7;
            color:#166534;
            margin-top:4px;
        }
        .jc-col-muted{
            font-size:12px;
            color:#64748b;
            font-weight:600;
        }
        .mod-hero-grid--overview {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        @media (max-width: 1100px) {
            .mod-hero-grid--overview {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (max-width: 520px) {
            .mod-hero-grid--overview {
                grid-template-columns: 1fr;
            }
        }
        .mod-hero-grid--overview .mod-hero-value { font-size: 22px; }
        .mod-hero-grid--overview .mod-hero-icon { width: 36px; height: 36px; font-size: 16px; }
        .mod-hero-card{
            position:relative;overflow:hidden;border-radius:12px;padding:14px 14px 12px;color:#fff;
            min-height:82px;border:1px solid rgba(255,255,255,.35);
            box-shadow:0 6px 16px rgba(15,23,42,.1);
        }
        .jc-stat-clickable{
            cursor:pointer;
            transition:transform .12s ease,box-shadow .12s ease;
        }
        .jc-stat-clickable:hover{
            transform:translateY(-2px);
            box-shadow:0 10px 22px rgba(15,23,42,.14);
        }
        .jc-stat-clickable:focus-visible{
            outline:none;
            box-shadow:0 0 0 3px rgba(255,255,255,.9),0 10px 22px rgba(15,23,42,.16);
        }
        .mod-hero-card--green{background:linear-gradient(135deg,#16a34a 0%,#4ade80 100%);}
        .mod-hero-card--red{background:linear-gradient(135deg,#dc2626 0%,#f87171 100%);}
        .mod-hero-card--blue{background:linear-gradient(135deg,#2563eb 0%,#60a5fa 100%);}
        .mod-hero-card--amber{background:linear-gradient(135deg,#d97706 0%,#fbbf24 100%);}
        .mod-hero-card--orange{background:linear-gradient(135deg,#ff8a00 0%,#ffc107 100%);}
        .mod-hero-card--purple{background:linear-gradient(135deg,#7c3aed 0%,#a78bfa 100%);}
        .mod-hero-card--slate{background:linear-gradient(135deg,#475569 0%,#94a3b8 100%);}
        .mod-hero-card--cyan{background:linear-gradient(135deg,#0891b2 0%,#22d3ee 100%);}
        .mod-hero-card::after{
            content:'';position:absolute;right:-24%;bottom:-50%;width:65%;height:130%;
            background:rgba(255,255,255,.12);border-radius:50%;pointer-events:none;
        }
        .mod-hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;position:relative;z-index:1;}
        .mod-hero-label{font-size:12px;font-weight:600;opacity:.95;margin:0 0 6px;}
        .mod-hero-value{font-size:26px;font-weight:800;line-height:1;letter-spacing:-.02em;}
        .mod-hero-sub{font-size:11px;opacity:.9;margin-top:6px;line-height:1.35;}
        .mod-hero-icon{
            width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.22);
            display:inline-flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;
        }
        .mod-hero-card.is-active{
            box-shadow:0 0 0 3px rgba(255,255,255,.9),0 10px 22px rgba(15,23,42,.16);
            transform:translateY(-1px);
        }
        .jc-actions-col{
            width:52px;
            padding-left:6px !important;
            padding-right:6px !important;
        }
        .jc-actions-cell{
            text-align:center;
            vertical-align:middle !important;
            padding:8px 6px !important;
        }
        .jc-del-icon{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:36px;
            height:36px;
            margin:0 auto;
            border:none;
            border-radius:999px;
            background:#fee2e2;
            color:#dc2626;
            border:1px solid #fecaca;
            cursor:pointer;
            font-size:14px;
            -webkit-tap-highlight-color:transparent;
            transition:transform .12s ease, background .12s ease, color .12s ease, border-color .12s ease;
        }
        .jc-del-icon:hover{
            background:#fecaca;
            border-color:#fca5a5;
            color:#b91c1c;
        }
        .jc-del-icon:active{
            transform:scale(0.9);
            background:#dc2626;
            border-color:#dc2626;
            color:#fff;
        }
        .jc-del-icon:focus{
            outline:none;
        }
        .jc-del-icon:focus-visible{
            outline:2px solid #fca5a5;
            outline-offset:2px;
        }
        #jobCardsTable .jc-del-icon,
        #jobCardsTable .jc-del-icon i{
            color:inherit;
        }
        .jc-actions-cell--empty{
            color:#cbd5e1;
            font-size:13px;
            font-weight:600;
        }
    </style>

<?php
ops_docs_render_header([
    'title' => 'Job Cards',
    'subtitle' => 'Manage workshop job cards, track progress, and link quotations.',
    'search_placeholder' => 'Search job cards…',
    'search_label' => 'Search job cards',
    'actions_html' => '<a href="' . htmlspecialchars(app_url('Admin/JobCard/add_job_card.php'), ENT_QUOTES, 'UTF-8') . '" class="ops-doc-primary-btn"><i class="fas fa-plus" aria-hidden="true"></i> New Job Card</a>',
]);
?>

<?php if ($success): ?>
<div class="jc-flash-wrap" id="jcFlashWrap">
    <div class="jc-flash-card jc-flash-card--success">
        <div class="jc-flash-icon"><i class="fas fa-check"></i></div>
        <div class="jc-flash-title">Success</div>
        <div class="jc-flash-body"><?php echo htmlspecialchars($success); ?></div>
    </div>
</div>
<?php elseif ($error): ?>
<div class="jc-flash-wrap" id="jcFlashWrap">
    <div class="jc-flash-card jc-flash-card--error">
        <div class="jc-flash-icon"><i class="fas fa-exclamation"></i></div>
        <div class="jc-flash-title">Error</div>
        <div class="jc-flash-body"><?php echo htmlspecialchars($error); ?></div>
    </div>
</div>
<?php endif; ?>

<?php ops_docs_layout_open(); ?>

<?php ops_docs_render_folders('Folders', $jc_folders, 'jcFoldersViewAll'); ?>

<?php ops_docs_files_section_open('Files', 'jcFilesViewAll'); ?>
<span id="jcTableTitle" hidden>All Job Cards</span>
<span id="jcTableMeta" hidden aria-hidden="true"></span>

<div class="qt-section qt-find-panel ops-filter-card">
    <div class="ops-filter-row">
        <span class="ops-filter-label">Status</span>
        <div class="qt-tabs" data-jc-tab-group="status">
            <button type="button" class="qt-tab active" data-filter="all" data-title="All Job Cards">All</button>
            <button type="button" class="qt-tab" data-filter="open" data-title="Open Jobs">Open</button>
            <button type="button" class="qt-tab" data-filter="pending" data-title="Pending">Pending</button>
            <button type="button" class="qt-tab" data-filter="in_progress" data-title="In Progress">In progress</button>
            <button type="button" class="qt-tab" data-filter="complete" data-title="Completed">Completed</button>
            <button type="button" class="qt-tab" data-filter="draft" data-title="Draft">Draft</button>
        </div>
    </div>

    <div class="ops-filter-row">
        <span class="ops-filter-label">Quotation</span>
        <div class="qt-tabs qt-tabs--secondary" data-jc-tab-group="quote">
            <button type="button" class="qt-tab" data-filter="no_quote" data-title="Awaiting Quotation">Awaiting quote</button>
            <button type="button" class="qt-tab" data-filter="has_quote" data-title="With Quotation">Has quote</button>
        </div>
    </div>

    <div class="ops-filter-footer">
        <label class="ops-filter-sort qt-control qt-control--sort">
            <select id="sortFilter" class="erp-input erp-input-sm" aria-label="Sort job cards">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="card_asc">Card no. (low → high)</option>
                <option value="card_desc">Card no. (high → low)</option>
            </select>
        </label>
        <p class="ops-filter-result" id="opsFilterResult"></p>
    </div>
</div>

<?php ops_docs_table_card_open('jcJobCardsList'); ?>
    <div class="erp-card-body erp-p-0">
        <div class="qt-table-toolbar" id="jcTableToolbar">
            <label class="qt-select-all">
                <input type="checkbox" id="jcSelectAll" aria-label="Select all visible job cards">
                <span>Select all</span>
            </label>
            <span class="qt-selection-meta" id="jcSelectionMeta">0 selected</span>
            <button type="button" class="erp-btn erp-btn-danger erp-btn-sm" id="jcDeleteSelected" disabled>
                <i class="fas fa-trash-alt" aria-hidden="true"></i> Delete selected
            </button>
        </div>
        <div class="erp-student-table-wrap">
        <div class="erp-table-container qt-table-scroll">
            <table class="erp-table erp-student-table jc-list-table" id="jobCardsTable" data-st-row-selector="tr[data-job-row], tr[data-draft-row]">
                <colgroup>
                    <col class="jc-col-check" style="width:48px">
                    <col class="jc-col-card-no" style="width:10%">
                    <col class="jc-col-status" style="width:11%">
                    <col class="jc-col-progress" style="width:8%">
                    <col class="jc-col-client" style="width:15%">
                    <col class="jc-col-vehicle" style="width:14%">
                    <col class="jc-col-work" style="width:18%">
                    <col class="jc-col-tech" style="width:12%">
                    <col class="jc-col-quote" style="width:10%">
                    <col class="jc-col-actions" style="width:56px">
                </colgroup>
                <thead>
                    <tr>
                        <th class="st-col-check jc-col-check jc-no-row-nav" scope="col">
                            <input type="checkbox" class="st-head-check" id="jcSelectAllHead" aria-label="Select all visible job cards">
                        </th>
                        <?php echo st_sortable_th('Card no.', 'jc-col-card-no'); ?>
                        <?php echo st_sortable_th('Status', 'jc-col-status'); ?>
                        <?php echo st_plain_th('Progress', 'jc-col-progress'); ?>
                        <?php echo st_sortable_th('Client', 'jc-col-client'); ?>
                        <?php echo st_sortable_th('Vehicle', 'jc-col-vehicle', true, 'Reg no.'); ?>
                        <?php echo st_sortable_th('Work', 'jc-col-work', true, 'Complaint'); ?>
                        <?php echo st_sortable_th('Technician', 'jc-col-tech'); ?>
                        <?php echo st_sortable_th('Quotation', 'jc-col-quote'); ?>
                        <?php echo st_plain_th('Action', 'st-col-actions jc-actions-col jc-no-row-nav'); ?>
                    </tr>
                </thead>
                <tbody>
                    <tr id="jcLoadingRow" class="jc-helper-row jc-loading-row">
                        <td colspan="<?php echo (int) $jc_table_colspan; ?>"><i class="fas fa-spinner fa-spin"></i> Loading job cards...</td>
                    </tr>
                    <?php foreach ($job_cards as $job):
                        $extra = json_decode($job['extra_data'] ?? '{}', true) ?: [];
                        $technician_raw = trim((string) ($extra['technician_no'] ?? $job['technician_name'] ?? ''));
                        $technician_display = $technician_raw !== '' ? $technician_raw : '-';
                        $status = jc_status_normalize($job['job_status'] ?? 'new');
                        $statusClass = jc_status_css_class($status);
                        $hasQuote = !empty($job['quotation_id']);
                        $jcWf = jc_workflow_from_row($status, $hasQuote);
                        $jcWfTitle = ($jcWf['next_label'] ?? '') . ' — ' . ($jcWf['next_hint'] ?? '');
                        $createdRaw = $job['created_at'] ?? null;
                        $updatedRaw = $job['updated_at'] ?? null;
                        $progressPct = max(0, min(100, (int) ($job['progress'] ?? 0)));
                        $descRaw = trim((string) ($job['description'] ?? ''));
                        if ($descRaw === '' && !empty($extra['complaint'])) {
                            $descRaw = trim((string) $extra['complaint']);
                        }
                        $descTitle = htmlspecialchars($descRaw !== '' ? $descRaw : '-', ENT_QUOTES, 'UTF-8');
                        $workSnippet = jc_list_text_snippet($descRaw, 72);
                        $serviceLabel = jc_service_type_label($job['service_type'] ?? '');
                        $serviceClass = jc_service_type_class($job['service_type'] ?? '');
                        $laborDisplay = jc_labor_hours_display($job);
                        $quoteStatusRaw = (string) ($job['quote_status'] ?? '');
                        $quoteStatusLabel = jc_quote_status_label($quoteStatusRaw);
                        $quoteStatusClass = jc_quote_status_pill_class($quoteStatusRaw);
                        $hasInvoice = !empty($job['invoice_id']);
                        $rowTitle = trim($jcWfTitle . ($descRaw !== '' ? ' · ' . $descRaw : '') . ($progressPct > 0 ? ' · ' . $progressPct . '% complete' : ''));
                        if ($technician_display !== '' && $technician_display !== '-') {
                            $rowTitle .= ' · Tech: ' . $technician_display;
                        }
                        $rowTitleAttr = htmlspecialchars($rowTitle, ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="jc-clickable-row" data-job-row="1" data-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" data-job-id="<?php echo (int) $job['id']; ?>" data-created-ts="<?php echo !empty($createdRaw) ? (int) strtotime($createdRaw) : 0; ?>" data-has-quote="<?php echo $hasQuote ? '1' : '0'; ?>" data-in-today="<?php echo jc_created_in_period($createdRaw, 'today') ? '1' : '0'; ?>" data-in-week="<?php echo jc_created_in_period($createdRaw, 'week') ? '1' : '0'; ?>" data-in-month="<?php echo jc_created_in_period($createdRaw, 'month') ? '1' : '0'; ?>" data-in-year="<?php echo jc_created_in_period($createdRaw, 'year') ? '1' : '0'; ?>" data-row-url="<?php echo htmlspecialchars($jc_edit_base . (int) $job['id'], ENT_QUOTES, 'UTF-8'); ?>" data-jc-label="<?php echo htmlspecialchars($job['card_number'], ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo $rowTitleAttr; ?>">
                        <td class="qt-col-check jc-col-check jc-no-row-nav" onclick="event.stopPropagation();">
                            <input type="checkbox" class="jc-row-check" value="<?php echo (int) $job['id']; ?>" aria-label="Select <?php echo htmlspecialchars($job['card_number'], ENT_QUOTES, 'UTF-8'); ?>">
                        </td>
                        <td class="jc-col-card-no"><span class="qt-id jc-card-no"><?php echo htmlspecialchars($job['card_number']); ?></span></td>
                        <td class="jc-col-status">
                            <span class="jc-status <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(jc_status_label($status), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td class="jc-col-progress" onclick="event.stopPropagation();">
                            <div class="qt-row-progress" title="<?php echo htmlspecialchars($jcWfTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php foreach ($jcWf['steps'] as $wfStep): ?>
                                <span class="qt-row-dot<?php echo !empty($wfStep['done']) ? ' is-done' : ''; ?><?php echo !empty($wfStep['current']) ? ' is-current' : ''; ?>" aria-hidden="true"></span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td class="jc-col-client jc-col-ellipsis"><span class="st-cell-primary"><?php echo htmlspecialchars($job['client_name'] ?? 'Walk-in'); ?></span></td>
                        <td class="jc-col-vehicle">
                            <span class="jc-vehicle-model"><?php echo htmlspecialchars($job['model'] ?? '-'); ?></span>
                            <span class="jc-vehicle-reg"><?php echo htmlspecialchars($job['reg_no'] ?? '-'); ?></span>
                        </td>
                        <td class="jc-col-work" title="<?php echo $descTitle; ?>">
                            <span class="jc-col-work-text"><?php echo $workSnippet; ?></span>
                        </td>
                        <td class="jc-col-tech jc-col-ellipsis" title="<?php echo htmlspecialchars($technician_raw !== '' ? $technician_raw : '-', ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if ($technician_raw !== ''): ?>
                            <span class="st-cell-primary"><?php echo jc_list_text_snippet($technician_raw, 48); ?></span>
                            <?php else: ?>
                            <span class="jc-cell-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="jc-col-quote" onclick="event.stopPropagation();">
                            <?php if ($hasQuote): ?>
                            <a href="<?php echo htmlspecialchars($jc_quote_base, ENT_QUOTES, 'UTF-8'); ?><?php echo (int) $job['quotation_id']; ?>" class="jc-link-quote">QTN #<?php echo (int) $job['quotation_id']; ?></a>
                            <span class="jc-quote-meta"><?php echo htmlspecialchars(formatMoney((float) ($job['quote_amount'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php else: ?>
                            <span class="jc-cell-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php
                        echo st_actions_cell([
                            'delete_attrs' => [
                                'data-jc-delete-id' => (string) (int) $job['id'],
                                'data-jc-delete-label' => $job['card_number'],
                            ],
                            'delete_label' => 'Delete job card ' . $job['card_number'],
                        ]);
                        ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($job_cards)): ?>
                    <tr class="jc-helper-row" id="jcEmptyStateRow">
                        <td colspan="<?php echo (int) $jc_table_colspan; ?>">
                            <div class="erp-empty-state">
                                <div class="erp-empty-icon"><i class="fas fa-clipboard-list"></i></div>
                                <div class="erp-empty-title">No job cards yet</div>
                                <div class="erp-empty-text">Create your first job card to get started</div>
                                <a href="<?php echo htmlspecialchars(app_url('Admin/JobCard/add_job_card.php')); ?>" class="erp-btn erp-btn-primary">New Job Card</a>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="jc-helper-row" id="jcNoMatchRow" style="display:none;">
                        <td colspan="<?php echo (int) $jc_table_colspan; ?>"><i class="fas fa-search"></i> No matching job cards found</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php st_render_table_footer('jobCardsTable'); ?>
        </div>
    </div>
</div>
<?php ops_docs_files_section_close(); ?>

<?php
ops_docs_render_sidebar([
    'overview_title' => 'Workshop load',
    'donut_segments' => $jc_donut_segments,
    'donut_center' => number_format($jc_total_count),
    'donut_sub' => 'total cards',
    'stats_title' => 'Quick stats',
    'bars' => $jc_sidebar_bars,
    'recent_title' => 'Recent activity',
    'recent' => $jc_sidebar_recent,
]);
ops_docs_init_script();
?>


<!-- Delete modal — same as add_quotation.php #aq-delete-modal -->
<div id="jcDeleteModal" class="aq-name-modal">
    <div class="aq-name-modal-card">
        <div class="aq-name-modal-title">Delete Job Card</div>
        <div class="aq-name-modal-sub" id="jcDeleteModalMessage">Move this job card to the recycle bin? It will be removed from the active job card list and can be restored later if needed.</div>
        <div class="aq-name-modal-actions">
            <button type="button" id="jcDeleteModalCancel" class="aq-s1-btn aq-s1-btn--gray">Cancel</button>
            <form method="POST" id="deleteJobCardForm" action="<?php echo htmlspecialchars($jc_list_url); ?>">
                <div id="jcDeleteIdsWrap"></div>
                <input type="hidden" name="delete_id" id="deleteJobCardId" value="">
                <button type="submit" class="aq-s1-btn aq-s1-btn--danger" id="jcDeleteSubmitBtn"><i class="fas fa-trash"></i> Move to Recycle Bin</button>
            </form>
        </div>
    </div></div>

<script>
const JC_ADD_URL = <?php echo json_encode(app_url('Admin/JobCard/add_job_card.php')); ?>;

(function initJobCardDelete() {
    const modal = document.getElementById('jcDeleteModal');
    const deleteIdInput = document.getElementById('deleteJobCardId');
    const deleteIdsWrap = document.getElementById('jcDeleteIdsWrap');
    const cancelBtn = document.getElementById('jcDeleteModalCancel');
    const submitBtn = document.getElementById('jcDeleteSubmitBtn');
    if (!modal || !deleteIdInput) return;

    const defaultMessage = 'Move this job card to the recycle bin? It will be removed from the active job card list and can be restored later if needed.';
    const messageEl = document.getElementById('jcDeleteModalMessage');

    function openDeleteModal(ids, labels) {
        const idList = Array.isArray(ids) ? ids : [ids];
        const labelList = Array.isArray(labels) ? labels : [labels];
        if (deleteIdsWrap) deleteIdsWrap.innerHTML = '';
        deleteIdInput.value = '';
        deleteIdInput.disabled = idList.length > 1;
        idList.forEach(function(id) {
            if (!deleteIdsWrap) return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_ids[]';
            input.value = String(id);
            deleteIdsWrap.appendChild(input);
        });
        if (idList.length === 1) {
            deleteIdInput.disabled = false;
            deleteIdInput.value = String(idList[0] || '');
            if (deleteIdsWrap) deleteIdsWrap.innerHTML = '';
        }
        if (messageEl) {
            if (idList.length === 1) {
                const cardRef = (labelList[0] || '').trim();
                messageEl.textContent = cardRef
                    ? ('Move job card ' + cardRef + ' to the recycle bin? It will be removed from the active job card list and can be restored later if needed.')
                    : defaultMessage;
            } else {
                messageEl.textContent = 'Move ' + idList.length + ' job cards to the recycle bin? They will be removed from the active list and can be restored later if needed.';
            }
        }
        if (submitBtn) {
            submitBtn.innerHTML = idList.length > 1
                ? '<i class="fas fa-trash"></i> Move ' + idList.length + ' to Recycle Bin'
                : '<i class="fas fa-trash"></i> Move to Recycle Bin';
        }
        modal.classList.add('show');
    }

    function closeDeleteModal() {
        modal.classList.remove('show');
        deleteIdInput.value = '';
        deleteIdInput.disabled = false;
        if (deleteIdsWrap) deleteIdsWrap.innerHTML = '';
        if (messageEl) messageEl.textContent = defaultMessage;
        if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-trash"></i> Move to Recycle Bin';
    }

    window.jcOpenDeleteModal = openDeleteModal;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-jc-delete-id]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        openDeleteModal(
            [Number(btn.getAttribute('data-jc-delete-id'))],
            [btn.getAttribute('data-jc-delete-label') || '']
        );
    }, true);

    if (cancelBtn) cancelBtn.addEventListener('click', closeDeleteModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeDeleteModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) closeDeleteModal();
    });
})();


const jcSelectAll = document.getElementById('jcSelectAll');
const jcSelectAllHead = document.getElementById('jcSelectAllHead');
const jcSelectionMeta = document.getElementById('jcSelectionMeta');
const jcDeleteSelected = document.getElementById('jcDeleteSelected');

function getVisibleJobRows() {
    if (!jobCardsTable) return [];
    const tbody = jobCardsTable.querySelector('tbody');
    if (!tbody) return [];
    return Array.from(tbody.querySelectorAll('tr[data-job-row="1"]')).filter(function(row) {
        return row.style.display !== 'none';
    });
}

function getSelectedJobRows() {
    return getVisibleJobRows().filter(function(row) {
        const cb = row.querySelector('.jc-row-check');
        return cb && cb.checked;
    });
}

function syncJcSelectAllState() {
    const visible = getVisibleJobRows();
    const selected = getSelectedJobRows();
    const allChecked = visible.length > 0 && selected.length === visible.length;
    if (jcSelectAll) jcSelectAll.checked = allChecked;
    if (jcSelectAllHead) jcSelectAllHead.checked = allChecked;
    if (jcSelectionMeta) {
        jcSelectionMeta.textContent = selected.length === 1
            ? '1 selected'
            : (selected.length + ' selected');
    }
    if (jcDeleteSelected) jcDeleteSelected.disabled = selected.length === 0;
    visible.forEach(function(row) {
        const cb = row.querySelector('.jc-row-check');
        row.classList.toggle('qt-row-selected', !!(cb && cb.checked));
    });
}

function setSelectAllVisibleJobs(checked) {
    getVisibleJobRows().forEach(function(row) {
        const cb = row.querySelector('.jc-row-check');
        if (cb) cb.checked = checked;
    });
    syncJcSelectAllState();
}

if (jcSelectAll) {
    jcSelectAll.addEventListener('change', function() {
        setSelectAllVisibleJobs(jcSelectAll.checked);
    });
}
if (jcSelectAllHead) {
    jcSelectAllHead.addEventListener('change', function() {
        setSelectAllVisibleJobs(jcSelectAllHead.checked);
        if (jcSelectAll) jcSelectAll.checked = jcSelectAllHead.checked;
    });
}
if (jcDeleteSelected) {
    jcDeleteSelected.addEventListener('click', function() {
        const rows = getSelectedJobRows();
        if (!rows.length || typeof window.jcOpenDeleteModal !== 'function') return;
        const ids = rows.map(function(row) { return Number(row.getAttribute('data-job-id')); });
        const labels = rows.map(function(row) { return row.getAttribute('data-jc-label') || ''; });
        window.jcOpenDeleteModal(ids, labels);
    });
}

const jobCardsTable = document.getElementById('jobCardsTable');
const searchInput = document.getElementById('searchInput');
const sortFilter = document.getElementById('sortFilter');
const noMatchRow = document.getElementById('jcNoMatchRow');
const emptyStateRow = document.getElementById('jcEmptyStateRow');
const loadingRow = document.getElementById('jcLoadingRow');
let activePeriod = '';
const JC_OVERVIEW_VIEW = <?php echo json_encode($jc_overview_view, JSON_UNESCAPED_UNICODE); ?>;
const JC_PERIOD_VIEWS = ['today', 'week', 'month'];
function getActiveTabInGroup(groupName) {
    const group = document.querySelector('.qt-tabs[data-jc-tab-group="' + groupName + '"]');
    if (!group) return null;
    return group.querySelector('.qt-tab.active');
}

function getStatusFilter() {
    const tab = getActiveTabInGroup('status');
    return tab ? (tab.dataset.filter || 'all') : 'all';
}

function getQuoteFilter() {
    const tab = getActiveTabInGroup('quote');
    return tab ? (tab.dataset.filter || '') : '';
}

function statusMatches(row, filter) {
    const isDraft = row.getAttribute('data-draft-row') === '1';
    if (isDraft) {
        if (!filter || filter === 'all') {
            return row.getAttribute('data-draft-primary') === '1';
        }
        return filter === 'draft';
    }
    if (filter === 'draft') return false;
    if (!filter || filter === 'all') return true;
    const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
    const terminal = ['completed', 'invoiced', 'paid', 'complete'];
    if (filter === 'open') return !terminal.includes(rowStatus);
    if (filter === 'pending') return rowStatus === 'new' || rowStatus === '';
    if (filter === 'in_progress') return rowStatus === 'in_progress' || rowStatus === 'waiting_parts';
    if (filter === 'complete') return terminal.includes(rowStatus);
    return rowStatus === filter;
}

function quoteMatches(row, filter) {
    if (!filter) return true;
    const hasQuote = (row.getAttribute('data-has-quote') || '') === '1';
    if (filter === 'has_quote') return hasQuote;
    if (filter === 'no_quote') return !hasQuote;
    return true;
}

function periodMatches(row) {
    if (!activePeriod) return true;
    return (row.getAttribute('data-in-' + activePeriod) || '') === '1';
}

function sortJobRows(rows, sortValue, tbody) {
    const byCardNumber = (row) => {
        const el = row.querySelector('.jc-card-no, .qt-id');
        const text = el ? el.textContent : '';
        const digits = String(text).replace(/[^0-9]/g, '');
        const n = Number(digits);
        return Number.isFinite(n) ? n : 0;
    };
    const byTs = (row) => Number(row.getAttribute('data-created-ts') || 0);
    rows.sort(function(a, b) {
        if (sortValue === 'oldest') return byTs(a) - byTs(b);
        if (sortValue === 'card_asc') return byCardNumber(a) - byCardNumber(b);
        if (sortValue === 'card_desc') return byCardNumber(b) - byCardNumber(a);
        return byTs(b) - byTs(a);
    });
    rows.forEach(function(r) { tbody.appendChild(r); });
}

function getListTitle() {
    const activeCard = document.querySelector('.jc-stat-clickable.is-active');
    if (activeCard) {
        return activeCard.getAttribute('data-jc-card-label') || 'Job Cards';
    }
    const statusTab = getActiveTabInGroup('status');
    const quoteTab = getActiveTabInGroup('quote');
    const parts = [];
    if (statusTab && (statusTab.dataset.filter || '') !== 'all') {
        parts.push(statusTab.getAttribute('data-title') || 'Status');
    }
    if (quoteTab) {
        parts.push(quoteTab.getAttribute('data-title') || 'Quotation');
    }
    return parts.length ? parts.join(' · ') : 'All Job Cards';
}

function updateTableHeading(visibleCount) {
    const titleEl = document.getElementById('jcTableTitle');
    const metaEl = document.getElementById('jcTableMeta');
    if (titleEl) titleEl.textContent = getListTitle();
    if (metaEl) {
        metaEl.textContent = visibleCount === 1 ? '1 job card' : (visibleCount + ' job cards');
    }
}

function setActiveTabInGroup(groupName, tab) {
    const group = document.querySelector('.qt-tabs[data-jc-tab-group="' + groupName + '"]');
    if (!group || !tab) return;
    group.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    tab.classList.add('active');
}

function applyFilters() {
    if (!jobCardsTable) return;
    const tbody = jobCardsTable.querySelector('tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr[data-job-row="1"], tr[data-draft-row="1"]'));
    const query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
    const statusFilter = getStatusFilter();
    const quoteFilter = getQuoteFilter();
    const sortBy = sortFilter ? sortFilter.value : 'newest';

    sortJobRows(rows, sortBy, tbody);

    let visibleCount = 0;
    rows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        const show = text.includes(query)
            && statusMatches(row, statusFilter)
            && quoteMatches(row, quoteFilter)
            && periodMatches(row);
        row.classList.remove('jc-search-hit', 'qt-search-hit');
        row.style.display = show ? '' : 'none';
        if (show) {
            visibleCount++;
            if (query) row.classList.add('qt-search-hit', 'jc-search-hit');
        }
    });

    if (noMatchRow) {
        noMatchRow.style.display = (!emptyStateRow && visibleCount === 0) ? '' : 'none';
    }
    updateTableHeading(visibleCount);
    syncJcSelectAllState();
    if (window.ErpStudentTable) {
        ErpStudentTable.sync('jobCardsTable');
    }
}

if (searchInput) searchInput.addEventListener('input', applyFilters);
if (sortFilter) sortFilter.addEventListener('change', applyFilters);

if (searchInput && jobCardsTable) {
    searchInput.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const firstMatch = jobCardsTable.querySelector('tbody tr.jc-clickable-row:not([style*="display: none"])');
        if (!firstMatch) return;
        const url = firstMatch.getAttribute('data-row-url');
        if (url) window.location.href = url;
    });
}

(function renderLocalDraftRows() {
    const tbody = document.querySelector('#jobCardsTable tbody');
    if (!tbody) return;
    const drafts = [];
    for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (!k) continue;
        if (!k.startsWith('draft_')) continue;
        if (k.indexOf('add_job_card.php') === -1) continue;
        try {
            const raw = localStorage.getItem(k);
            if (!raw) continue;
            const d = JSON.parse(raw);
            drafts.push({ key: k, data: d || {} });
        } catch (_) {}
    }
    if (!drafts.length) return;
    drafts.sort((a, b) => Number(b.data._saved_at || 0) - Number(a.data._saved_at || 0));

    if (emptyStateRow) emptyStateRow.style.display = 'none';

    const fmtDate = (ms) => {
        const n = Number(ms || 0);
        if (!n) return 'Draft';
        const dt = new Date(n);
        const d = String(dt.getDate()).padStart(2, '0');
        const m = dt.toLocaleString('en-US', { month: 'short' });
        const y = dt.getFullYear();
        return d + ' ' + m + ' ' + y;
    };
    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    const periodAttr = (ms) => {
        const n = Number(ms || 0);
        if (!n) return ' data-in-today="0" data-in-week="0" data-in-month="0" data-in-year="0"';
        const dt = new Date(n);
        const now = new Date();
        const sameDay = dt.toDateString() === now.toDateString();
        const sameWeek = jcClientYearWeek(dt) === jcClientYearWeek(now);
        const sameMonth = dt.getFullYear() === now.getFullYear() && dt.getMonth() === now.getMonth();
        const sameYear = dt.getFullYear() === now.getFullYear();
        return ' data-in-today="' + (sameDay ? '1' : '0') + '" data-in-week="' + (sameWeek ? '1' : '0') + '" data-in-month="' + (sameMonth ? '1' : '0') + '" data-in-year="' + (sameYear ? '1' : '0') + '"';
    };

    drafts.forEach((x, idx) => {
        const d = x.data || {};
        const card = d.card_number || d.jc_card_number || d.jc_card_number_input || 'Draft Job Card';
        const client = d.client_name_display || d.new_client_name || d.manual_client_name || 'Draft';
        const vehicle = d.model_reg || d.vehicle_model || '—';
        const reg = d.manual_vehicle_reg || d.vehicle_reg_no || '—';
        const work = d.description || d.complaint || d.jc_complaint || '—';
        const tech = d.technician_name || d.technician_no || '—';
        const svcRaw = (d.service_type || '').toString().toLowerCase();
        const svcLabel = svcRaw === 'mobile' ? 'Mobile' : 'In shop';
        const svcClass = svcRaw === 'mobile' ? 'jc-service-mobile' : 'jc-service-inshop';
        const when = fmtDate(d._saved_at);
        const tr = document.createElement('tr');
        tr.className = 'jc-clickable-row';
        tr.setAttribute('data-draft-row', '1');
        tr.setAttribute('data-draft-key', x.key);
        tr.setAttribute('data-draft-primary', idx === 0 ? '1' : '0');
        tr.setAttribute('data-status', 'draft');
        tr.setAttribute('data-has-quote', '0');
        tr.setAttribute('data-created-ts', String(Number(d._saved_at || 0)));
        tr.setAttribute('data-row-url', JC_ADD_URL);
        tr.setAttribute('title', 'Unsaved draft — click to resume');
        const p = periodAttr(d._saved_at);
        const cardHtml = esc(card) + (idx === 0 ? '<span class="jc-draft-badge">Unsaved</span>' : '');
        tr.innerHTML =
            '<td class="qt-col-check jc-col-check jc-no-row-nav"></td>' +
            '<td class="jc-col-card-no"><span class="qt-id jc-card-no">' + cardHtml + '</span></td>' +
            '<td class="jc-col-status"><span class="jc-status jc-status-draft">Draft</span></td>' +
            '<td class="jc-col-progress" onclick="event.stopPropagation();"><div class="qt-row-progress" title="Draft — Not saved yet">' +
            '<span class="qt-row-dot is-current" aria-hidden="true"></span><span class="qt-row-dot" aria-hidden="true"></span>' +
            '<span class="qt-row-dot" aria-hidden="true"></span><span class="qt-row-dot" aria-hidden="true"></span></div></td>' +
            '<td class="jc-col-client">' + esc(client) + '</td>' +
            '<td class="jc-col-vehicle"><span class="jc-vehicle-model">' + esc(vehicle) + '</span><span class="jc-vehicle-reg">' + esc(reg) + '</span></td>' +
            '<td class="jc-col-work"><span class="jc-col-work-text">' + esc(work) + '</span></td>' +
            '<td class="jc-col-tech"><span class="st-cell-primary">' + esc(tech) + '</span></td>' +
            '<td class="jc-col-quote"><span class="jc-cell-muted">—</span></td>' +
            '<td class="st-col-actions jc-col-actions jc-no-row-nav"><span class="jc-cell-muted">—</span></td>';
        if (p) {
            p.trim().split(/\s+/).forEach(function(pair) {
                const m = pair.match(/data-([^=]+)="([^"]*)"/);
                if (m) tr.setAttribute(m[1], m[2]);
            });
        }
        tbody.insertBefore(tr, tbody.firstChild);
    });
})();

if (loadingRow) loadingRow.style.display = 'none';

// --- Dashboard card helpers ---
function jcClearActiveCards() {
    document.querySelectorAll('.jc-stat-clickable').forEach(function(c) {
        c.classList.remove('is-active');
    });
}

function jcClearQuoteFilters() {
    const quoteGroup = document.querySelector('.qt-tabs[data-jc-tab-group="quote"]');
    if (!quoteGroup) return;
    quoteGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
}

/** ISO week (mode 1) — must match jc_created_in_period() / overview SQL. */
function jcClientYearWeek(d) {
    const dt = new Date(d.getTime());
    dt.setHours(0, 0, 0, 0);
    dt.setDate(dt.getDate() + 3 - ((dt.getDay() + 6) % 7));
    const isoYear = dt.getFullYear();
    const jan4 = new Date(isoYear, 0, 4);
    const week = 1 + Math.round(((dt - jan4) / 86400000 - 3 + ((jan4.getDay() + 6) % 7)) / 7);
    return isoYear * 100 + week;
}

function jcActivateCard(view) {
    jcClearActiveCards();
    if (!view || view === 'all') return;
    const card = document.querySelector('.jc-stat-clickable[data-jc-card-view="' + view + '"]');
    if (card) card.classList.add('is-active');
}

function jcActivateFromCard(card) {
    const view = card.getAttribute('data-jc-card-view') || 'all';
    const statusGroup = document.querySelector('.qt-tabs[data-jc-tab-group="status"]');

    if (card.classList.contains('is-active')) {
        jcClearActiveCards();
        activePeriod = '';
        jcClearQuoteFilters();
        if (statusGroup) {
            const allTab = statusGroup.querySelector('.qt-tab[data-filter="all"]');
            if (allTab) setActiveTabInGroup('status', allTab);
        }
        applyFilters();
        return;
    }

    jcClearActiveCards();
    jcClearQuoteFilters();
    card.classList.add('is-active');

    const periodViews = ['today', 'week', 'month'];
    const statusMap = {
        'open': 'open',
        'pending': 'pending',
        'in_progress': 'in_progress',
        'complete': 'complete'
    };

    if (periodViews.includes(view)) {
        activePeriod = view;
        if (statusGroup) {
            const allTab = statusGroup.querySelector('.qt-tab[data-filter="all"]');
            if (allTab) setActiveTabInGroup('status', allTab);
        }
    } else if (statusMap[view]) {
        activePeriod = '';
        if (statusGroup) {
            const matchTab = statusGroup.querySelector('.qt-tab[data-filter="' + statusMap[view] + '"]');
            if (matchTab) setActiveTabInGroup('status', matchTab);
        }
    } else {
        activePeriod = '';
        if (statusGroup) {
            const allTab = statusGroup.querySelector('.qt-tab[data-filter="all"]');
            if (allTab) setActiveTabInGroup('status', allTab);
        }
    }

    applyFilters();

    const list = document.getElementById('jcJobCardsList');
    if (list) {
        requestAnimationFrame(function() {
            list.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
}

function onFindTabClick(tab) {
    const group = tab.closest('.qt-tabs');
    if (!group) return;
    const groupName = group.getAttribute('data-jc-tab-group');
    setActiveTabInGroup(groupName, tab);

    if (groupName === 'status') {
        activePeriod = '';
        const filter = tab.getAttribute('data-filter') || 'all';
        jcClearActiveCards();
        if (filter !== 'all') {
            const card = document.querySelector('.jc-stat-clickable[data-jc-card-view="' + filter + '"]');
            if (card) card.classList.add('is-active');
        }
    }

    applyFilters();
}

document.querySelectorAll('.qt-tabs[data-jc-tab-group] .qt-tab').forEach(function(tab) {
    const newTab = tab.cloneNode(true);
    tab.parentNode.replaceChild(newTab, tab);
    newTab.addEventListener('click', function() { onFindTabClick(newTab); });
});

document.querySelectorAll('.jc-stat-clickable').forEach(function(card) {
    card.addEventListener('click', function() { jcActivateFromCard(card); });
    card.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            jcActivateFromCard(card);
        }
    });
});

(function initFromUrl() {
    const view = JC_OVERVIEW_VIEW || '';
    const statusGroup = document.querySelector('.qt-tabs[data-jc-tab-group="status"]');
    if (!statusGroup) { applyFilters(); return; }

    const periodViews = ['today', 'week', 'month'];
    const statusViews = ['open', 'pending', 'in_progress', 'complete'];

    if (periodViews.includes(view)) {
        activePeriod = view;
        const allTab = statusGroup.querySelector('.qt-tab[data-filter="all"]');
        if (allTab) setActiveTabInGroup('status', allTab);
        jcActivateCard(view);
    } else if (statusViews.includes(view)) {
        activePeriod = '';
        const matchTab = statusGroup.querySelector('.qt-tab[data-filter="' + view + '"]');
        if (matchTab) setActiveTabInGroup('status', matchTab);
        jcActivateCard(view);
        const list = document.getElementById('jcJobCardsList');
        if (list) list.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        activePeriod = '';
        const allTab = statusGroup.querySelector('.qt-tab[data-filter="all"]');
        if (allTab) setActiveTabInGroup('status', allTab);
    }

    applyFilters();
})();

function jcResetAllFilters() {
    activePeriod = '';
    const statusGroup = document.querySelector('.qt-tabs[data-jc-tab-group="status"]');
    const quoteGroup = document.querySelector('.qt-tabs[data-jc-tab-group="quote"]');
    if (statusGroup) {
        statusGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
        const allTab = statusGroup.querySelector('.qt-tab[data-filter="all"]');
        if (allTab) allTab.classList.add('active');
    }
    if (quoteGroup) {
        quoteGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    }
    document.querySelectorAll('.jc-stat-clickable.is-active').forEach(function(c) { c.classList.remove('is-active'); });
    if (searchInput) searchInput.value = '';
    applyFilters();
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.opsDocsSyncListMeta === 'function') window.opsDocsSyncListMeta();
    if (typeof window.opsDocsWireViewAll === 'function') {
        window.opsDocsWireViewAll('jcFoldersViewAll', jcResetAllFilters);
        window.opsDocsWireViewAll('jcFilesViewAll', jcResetAllFilters);
    }
});

if (jobCardsTable) {
    jobCardsTable.addEventListener('change', function(e) {
        if (e.target.classList.contains('jc-row-check')) syncJcSelectAllState();
    });
    jobCardsTable.addEventListener('click', function(e) {
        if (e.target.closest('.st-col-actions, .st-act-btn, .jc-no-row-nav, .st-no-row-nav, .qt-col-check, .st-col-check, a, button')) return;
        const row = e.target.closest('tr.jc-clickable-row');
        if (!row) return;
        const url = row.getAttribute('data-row-url');
        if (url) window.location.href = url;
    });
}


document.addEventListener('erp-student-tables-ready', function () {
    if (typeof applyFilters === 'function') applyFilters();
});

window.addEventListener('load', function () {
    const wrap = document.getElementById('jcFlashWrap');
    if (!wrap) return;
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    setTimeout(function () {
        wrap.classList.remove('show');
        setTimeout(function () { wrap.remove(); }, 220);
    }, 3200);
});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
