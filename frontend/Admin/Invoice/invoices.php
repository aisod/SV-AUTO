<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/student_table.inc.php';
require_once __DIR__ . '/../includes/operations_docs_layout.inc.php';

require_role(['admin', 'manager']);

$is_admin_user = is_admin();
$can_view_financial = erp_can_view_financial_summary();
$inv_list_url = function_exists('app_url') ? app_url('Admin/Invoice/invoices.php') : 'invoices.php';

/** MySQL YEARWEEK(date, 1) — matches overview summary SQL. */
function inv_yearweek_mode1(string $ymd): int
{
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$dt) {
        return 0;
    }
    return (int) ($dt->format('o') . $dt->format('W'));
}

/** Match overview SQL periods (issued_date). */
function inv_issued_in_period(?string $raw, string $period): bool
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
            return inv_yearweek_mode1($ymd) === inv_yearweek_mode1($nowYmd);
        case 'month':
            return date('Y-m', $ts) === date('Y-m');
        case 'year':
            return date('Y', $ts) === date('Y');
        default:
            return true;
    }
}

function inv_list_date_cell(?string $raw): string
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

/** Payment workflow dots for list rows (mirrors quotation progress column). */
function inv_workflow_from_row(string $statusPaid, bool $overdue): array
{
    $status = strtolower($statusPaid);
    $isPaid = ($status === 'paid');
    $isOpen = in_array($status, ['unpaid', 'partial'], true);
    $steps = [
        ['done' => true, 'current' => false],
        ['done' => $isPaid, 'current' => $isOpen && !$overdue],
        ['done' => false, 'current' => $overdue && $isOpen],
        ['done' => $isPaid, 'current' => false],
    ];
    if ($isPaid) {
        $nextLabel = 'Paid';
        $nextHint = 'Complete';
    } elseif ($overdue) {
        $nextLabel = 'Overdue';
        $nextHint = 'Past due date';
    } elseif ($isOpen) {
        $nextLabel = 'Awaiting payment';
        $nextHint = ucfirst($status);
    } else {
        $nextLabel = 'Invoice';
        $nextHint = '';
    }
    return ['steps' => $steps, 'next_label' => $nextLabel, 'next_hint' => $nextHint];
}

$currency = getCurrency();
$business = getBusiness();

// Soft-delete handler — admin only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin_user && (isset($_POST['delete_id']) || isset($_POST['delete_ids']))) {
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
        header('Location: ' . $inv_list_url . '?error=' . urlencode('Invalid invoice'));
        exit;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
        $stmt = $pdo->prepare("UPDATE invoices SET deleted_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
        $stmt->execute($deleteIds);
        $deletedCount = $stmt->rowCount();
        if ($deletedCount === 0) {
            header('Location: ' . $inv_list_url . '?error=' . urlencode('Invoice not found or already deleted'));
            exit;
        }
        try {
            $log = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)');
            foreach ($deleteIds as $delete_id) {
                $log->execute([$_SESSION['user_id'], 'deleted_invoice', 'invoice', $delete_id]);
            }
        } catch (Exception $logEx) {
            error_log('Invoice delete audit log failed: ' . $logEx->getMessage());
        }
        $successMsg = $deletedCount === 1
            ? 'Invoice moved to recycle bin'
            : ($deletedCount . ' invoices moved to recycle bin');
        header('Location: ' . $inv_list_url . '?success=' . urlencode($successMsg));
        exit;
    } catch (Exception $e) {
        error_log('Invoice delete failed: ' . $e->getMessage());
        header('Location: ' . $inv_list_url . '?error=' . urlencode('Failed to delete invoice'));
        exit;
    }
}

// Summary stats
$stmt = $pdo->query("SELECT 
    COUNT(*) AS total_count,
    COALESCE(SUM(amount), 0) AS total_invoiced,
    COUNT(CASE WHEN status_paid IN ('unpaid', 'partial') THEN 1 END) AS open_count,
    COALESCE(SUM(CASE WHEN status_paid IN ('unpaid', 'partial') THEN amount ELSE 0 END), 0) AS outstanding_amount,
    COUNT(CASE WHEN status_paid = 'unpaid' THEN 1 END) AS unpaid_count,
    COUNT(CASE WHEN status_paid = 'partial' THEN 1 END) AS partial_count,
    COALESCE(SUM(CASE WHEN status_paid = 'partial' THEN amount ELSE 0 END), 0) AS partial_amount,
    COUNT(CASE WHEN status_paid = 'paid' THEN 1 END) AS paid_count,
    COALESCE(SUM(CASE WHEN status_paid = 'paid' THEN amount ELSE 0 END), 0) AS paid_amount,
    COUNT(CASE WHEN status_paid IN ('unpaid', 'partial')
        AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 END) AS overdue_count,
    COALESCE(SUM(CASE WHEN status_paid IN ('unpaid', 'partial')
        AND due_date IS NOT NULL AND due_date < CURDATE() THEN amount ELSE 0 END), 0) AS overdue_amount,
    COUNT(CASE WHEN issued_date IS NOT NULL AND DATE(issued_date) = CURDATE() THEN 1 END) AS today_count,
    COALESCE(SUM(CASE WHEN issued_date IS NOT NULL AND DATE(issued_date) = CURDATE() THEN amount ELSE 0 END), 0) AS today_amount,
    COUNT(CASE WHEN issued_date IS NOT NULL
        AND YEARWEEK(DATE(issued_date), 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS week_count,
    COALESCE(SUM(CASE WHEN issued_date IS NOT NULL
        AND YEARWEEK(DATE(issued_date), 1) = YEARWEEK(CURDATE(), 1) THEN amount ELSE 0 END), 0) AS week_amount,
    COUNT(CASE WHEN issued_date IS NOT NULL
        AND YEAR(issued_date) = YEAR(CURDATE()) AND MONTH(issued_date) = MONTH(CURDATE()) THEN 1 END) AS month_count,
    COALESCE(SUM(CASE WHEN issued_date IS NOT NULL
        AND YEAR(issued_date) = YEAR(CURDATE()) AND MONTH(issued_date) = MONTH(CURDATE()) THEN amount ELSE 0 END), 0) AS month_amount,
    COUNT(CASE WHEN issued_date IS NOT NULL AND YEAR(issued_date) = YEAR(CURDATE()) THEN 1 END) AS year_count,
    COALESCE(SUM(CASE WHEN issued_date IS NOT NULL AND YEAR(issued_date) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) AS year_amount
FROM invoices
WHERE deleted_at IS NULL");
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Overview cards — row 1: payment status; row 2: Today → week → month → All (same as quotations)
$inv_dashboard_cards = [
    [
        'section' => 'status',
        'tone' => 'red',
        'label' => 'Overdue',
        'value' => number_format((int) ($summary['overdue_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'Requires follow-up',
            formatMoney((float) ($summary['overdue_amount'] ?? 0)) . ' past due'
        ),
        'icon' => 'fa-exclamation-circle',
        'tab' => 'overdue',
        'period' => null,
        'list_title' => 'Overdue',
    ],
    [
        'section' => 'status',
        'tone' => 'amber',
        'label' => 'Outstanding',
        'value' => number_format((int) ($summary['open_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'Unpaid and part-paid',
            formatMoney((float) ($summary['outstanding_amount'] ?? 0)) . ' unpaid + partial'
        ),
        'icon' => 'fa-clock',
        'tab' => 'open',
        'period' => null,
        'list_title' => 'Outstanding',
    ],
    [
        'section' => 'status',
        'tone' => 'blue',
        'label' => 'Partial',
        'value' => number_format((int) ($summary['partial_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'Part-paid invoices',
            formatMoney((float) ($summary['partial_amount'] ?? 0)) . ' part paid'
        ),
        'icon' => 'fa-adjust',
        'tab' => 'partial',
        'period' => null,
        'list_title' => 'Partial',
    ],
    [
        'section' => 'status',
        'tone' => 'green',
        'label' => 'Paid',
        'value' => number_format((int) ($summary['paid_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'Marked paid',
            formatMoney((float) ($summary['paid_amount'] ?? 0)) . ' collected'
        ),
        'icon' => 'fa-check-circle',
        'tab' => 'paid',
        'period' => null,
        'list_title' => 'Paid',
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
        'tab' => null,
        'period' => 'today',
        'list_title' => 'Today',
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
        'tab' => null,
        'period' => 'week',
        'list_title' => 'This week',
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
        'tab' => null,
        'period' => 'month',
        'list_title' => 'This month',
    ],
    [
        'section' => 'period',
        'tone' => 'orange',
        'label' => 'All Invoices',
        'value' => number_format((int) ($summary['total_count'] ?? 0)),
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'All issued invoices',
            formatMoney((float) ($summary['total_invoiced'] ?? 0)) . ' invoiced'
        ),
        'icon' => 'fa-file-invoice-dollar',
        'tab' => 'all',
        'period' => null,
        'list_title' => 'All Invoices',
    ],
];

// All Invoices
$stmt = $pdo->query("SELECT 
    i.id AS invoice_id,
    i.invoice_number,
    i.amount,
    i.status_paid,
    i.issued_date,
    i.due_date,
    i.paid_at,
    q.id AS quotation_id,
    jc.card_number AS job_card,
    c.name AS client_name
FROM invoices i
LEFT JOIN quotations q ON i.quotation_id = q.id
LEFT JOIN job_cards jc ON q.job_card_id = jc.id
LEFT JOIN clients c ON q.client_id = c.id
WHERE i.deleted_at IS NULL
ORDER BY i.issued_date DESC, i.id DESC");
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$inv_total_count = (int) ($summary['total_count'] ?? 0);
$inv_overdue_n = (int) ($summary['overdue_count'] ?? 0);
$inv_open_n = (int) ($summary['open_count'] ?? 0);
$inv_partial_n = (int) ($summary['partial_count'] ?? 0);
$inv_paid_n = (int) ($summary['paid_count'] ?? 0);
$inv_month_n = (int) ($summary['month_count'] ?? 0);

$inv_folders = [];
foreach ([0, 1, 2, 3, 6, 7] as $invFolderIdx) {
    if (!isset($inv_dashboard_cards[$invFolderIdx])) {
        continue;
    }
    $card = $inv_dashboard_cards[$invFolderIdx];
    $attrs = ['data-inv-list-title' => (string) ($card['list_title'] ?? 'Invoices')];
    if (!empty($card['tab'])) {
        $attrs['data-inv-tab'] = (string) $card['tab'];
    }
    if (!empty($card['period'])) {
        $attrs['data-inv-period'] = (string) $card['period'];
    }
    if ($invFolderIdx === 7) {
        $attrs['data-inv-tab'] = 'all';
    }
    $inv_folders[] = [
        'label' => (string) ($card['label'] ?? 'Folder'),
        'count' => (int) str_replace(',', '', (string) ($card['value'] ?? '0')),
        'unit' => 'invoices',
        'icon' => 'fa-folder',
        'tone' => (string) ($card['tone'] ?? 'orange'),
        'clickable' => true,
        'class' => 'qt-stat-clickable',
        'attrs' => $attrs,
    ];
}

$inv_sidebar_bars = ops_docs_pct_bars([
    ['label' => 'Overdue', 'count' => $inv_overdue_n, 'tone' => 'red', 'icon' => 'fa-exclamation-circle'],
    ['label' => 'Outstanding', 'count' => $inv_open_n, 'tone' => 'amber', 'icon' => 'fa-clock'],
    ['label' => 'Paid', 'count' => $inv_paid_n, 'tone' => 'green', 'icon' => 'fa-check-circle'],
], max(1, $inv_total_count));

$inv_donut_segments = [
    ['label' => 'Overdue', 'value' => (string) number_format($inv_overdue_n), 'pct' => $inv_overdue_n, 'color' => '#dc2626'],
    ['label' => 'Outstanding', 'value' => (string) number_format($inv_open_n), 'pct' => $inv_open_n, 'color' => '#ea580c'],
    ['label' => 'Paid', 'value' => (string) number_format($inv_paid_n), 'pct' => $inv_paid_n, 'color' => '#059669'],
];

$inv_sidebar_recent = [];
foreach (array_slice($invoices, 0, 5) as $invRecent) {
    $invId = (int) ($invRecent['id'] ?? 0);
    $clientName = trim((string) ($invRecent['client_name'] ?? ''));
    $inv_sidebar_recent[] = [
        'title' => (string) ($invRecent['invoice_number'] ?? ('Invoice #' . $invId)),
        'meta' => $clientName !== '' ? $clientName : 'Invoice issued',
        'time' => ops_docs_time_ago($invRecent['issued_date'] ?? null),
        'href' => app_url('Admin/Invoice/view_invoice.php?id=' . $invId),
        'initials' => $clientName !== '' ? strtoupper(substr($clientName, 0, 1)) : 'I',
    ];
}

$inv_view_base = 'view_invoice.php?id=';
$inv_quote_base = ($erp_admin_base_path ?? '') . 'Quotation/add_quotation.php?id=';
$inv_table_colspan = $is_admin_user ? 10 : 9;

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* List table palette — invoices */
    #invoiceTable.erp-student-table.inv-list-table thead th{
        background:#f8fafc!important;
        color:#475569!important;
        border-bottom:1px solid #e2e8f0!important;
    }
    #invoiceTable.erp-student-table.inv-list-table thead th.sortable:hover{
        background:#f1f5f9!important;
    }
    #invoiceTable.erp-student-table.inv-list-table thead th.st-col-actions,
    #invoiceTable.erp-student-table.inv-list-table thead th.inv-col-progress{
        background:#f1f5f9!important;
    }
    #invoiceTable.erp-student-table.inv-list-table thead th .st-th-text{color:#475569;}
    #invoiceTable.erp-student-table.inv-list-table thead .qt-col-hint,
    #invoiceTable.erp-student-table.inv-list-table .st-th-sub{color:#94a3b8;}
    #invoiceTable.erp-student-table.inv-list-table tbody td{
        color:#475569!important;
        border-top-color:#e2e8f0!important;
    }
    #invoiceTable.erp-student-table.inv-list-table tbody tr:nth-child(odd) td{background:#fff!important;}
    #invoiceTable.erp-student-table.inv-list-table tbody tr:nth-child(even) td{background:#f8fafc!important;}
    #invoiceTable.erp-student-table.inv-list-table tbody tr[data-inv-row="1"]:hover td{background:#fff7ed!important;}
    #invoiceTable.erp-student-table .st-cell-primary{color:#1e293b;}
    #invoiceTable.erp-student-table th.sort-asc .st-sort-up,
    #invoiceTable.erp-student-table th.sort-desc .st-sort-down{color:#c2410c;}
    #invoiceTable.erp-student-table .st-act-btn--delete{
        background:#fee2e2!important;
        color:#ef4444!important;
    }
    #invoiceTable.erp-student-table .st-act-btn--delete:hover{
        background:#fecaca!important;
        color:#dc2626!important;
    }
    .qt-id,
    .inv-id{
        font-size:15px;
        font-weight:800;
        color:#1e293b;
    }
    .qt-row-progress{
        display:flex;
        gap:4px;
        align-items:center;
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
    .qt-col-hint{
        display:block;
        font-size:10px;
        font-weight:600;
        color:#94a3b8;
        text-transform:none;
        letter-spacing:0;
        margin-top:2px;
    }
    .qt-col-actions{
        width:1%;
        white-space:nowrap;
        text-align:center;
        vertical-align:middle;
    }
    /* Delete modal — same blueprint as job_card.php */
    .aq-s1-btn{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 14px;
        border-radius:8px;
        font-size:.875rem;
        font-weight:600;
        font-family:inherit;
        cursor:pointer;
        border:1px solid transparent;
        background:#fff;
        line-height:1.2;
        white-space:nowrap;
        justify-content:center;
        text-align:center;
        min-height:2.75rem;
    }
    .aq-s1-btn--gray{border-color:#cbd5e1;color:#334155;}
    .aq-s1-btn--gray:hover{border-color:#94a3b8;background:#fafafa;}
    .aq-s1-btn--danger{background:#dc2626;color:#fff;border:none;}
    .aq-s1-btn--danger:hover{background:#b91c1c;}
    .aq-name-modal{
        position:fixed;
        inset:0;
        background:rgba(15,23,42,.45);
        display:none;
        align-items:center;
        justify-content:center;
        z-index:1200;
    }
    .aq-name-modal.show{display:flex;}
    .aq-name-modal-card{
        width:min(92vw,460px);
        background:#fff;
        border:1px solid #e5e7eb;
        border-radius:14px;
        box-shadow:0 20px 50px rgba(0,0,0,.25);
        padding:18px;
    }
    .aq-name-modal-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px;}
    .aq-name-modal-sub{font-size:.875rem;color:#475569;margin-bottom:10px;line-height:1.5;}
    .aq-name-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px;}
    .aq-name-modal-actions form{margin:0;}
    #invoiceTable.inv-list-table{
        width:100%;
        min-width:1100px;
        table-layout:fixed;
        border-collapse:collapse;
    }
    .inv-cell-muted{color:#64748b;font-weight:600;}
    /* qt-status / inv-status shell: student-table.css blueprint */
    .inv-status::before{
        content:"";
        width:7px;
        height:7px;
        border-radius:50%;
        background:currentColor;
        opacity:.85;
    }
    .qt-status-unpaid,.inv-status-unpaid{background:#fef2f2;color:#dc2626;}
    .qt-status-partial,.inv-status-partial{background:#fff7ed;color:#c2410c;}
    .qt-status-paid,.inv-status-paid{background:#ecfdf5;color:#15803d;}
    .qt-section{margin-bottom:18px;}
    /* Find panel + filter pills: erp-layout.css */
    .qt-section-title{
        margin:0;
        font-size:13px;
        font-weight:900;
        letter-spacing:.05em;
        text-transform:uppercase;
        color:#334155;
    }
    .qt-search-hit td,.inv-search-hit td{
        background:#ffedd5 !important;
        box-shadow:inset 0 0 0 1px #fdba74;
    }
    #invInvoicesList{scroll-margin-top:88px;}
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
    /* Scrollbar + max-height: see erp-layout.css (.erp-table-container.qt-table-scroll) */
    #invoiceTable .inv-link-quote{
        font-size:12px;
        font-weight:700;
        color:#c2410c;
        text-decoration:none;
    }
    #invoiceTable .inv-link-quote:hover{text-decoration:underline;color:#9a3412;}
    .qt-helper-row td,.inv-helper-row td{
        text-align:center;
        padding:28px 16px !important;
        color:#64748b !important;
        background:#f8fafc;
    }
    .inv-flash-wrap{
        position:fixed;top:0;right:0;bottom:0;left:var(--sidebar-width);z-index:900;
        display:flex;align-items:center;justify-content:center;
        background:rgba(15,23,42,.35);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
        opacity:0;pointer-events:none;transition:opacity .2s ease;
    }
    body.erp-sidebar-collapsed .inv-flash-wrap{left:var(--sidebar-collapsed);}
    @media (max-width:1024px){.inv-flash-wrap{left:0;}}
    .inv-flash-wrap.show{opacity:1;pointer-events:auto}
    .inv-flash-card{
        width:min(92vw,420px);background:#fff;border:1px solid #e5e7eb;border-radius:16px;
        box-shadow:0 24px 55px rgba(0,0,0,.25);padding:18px 18px 14px;text-align:center;
        transform:translateY(8px);transition:transform .2s ease;
    }
    .inv-flash-wrap.show .inv-flash-card{transform:translateY(0)}
    .inv-flash-icon{
        width:44px;height:44px;border-radius:999px;margin:0 auto 10px;
        display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;
    }
    .inv-flash-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
    .inv-flash-body{font-size:.9rem;line-height:1.45}
    .inv-flash-card--success .inv-flash-icon{background:#ecfdf3;color:#16a34a;border:1px solid #bbf7d0}
    .inv-flash-card--success .inv-flash-body{color:#166534}
    .inv-flash-card--error .inv-flash-icon{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
    .inv-flash-card--error .inv-flash-body{color:#991b1b}
    .qt-dashboard-label{
        margin:0 0 10px;
        font-size:12px;
        font-weight:900;
        letter-spacing:.06em;
        text-transform:uppercase;
        color:#64748b;
    }
    .mod-hero-grid--overview{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
        margin-bottom:20px;
    }
    @media (max-width:1100px){.mod-hero-grid--overview{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:520px){.mod-hero-grid--overview{grid-template-columns:1fr;}}
    .mod-hero-card{
        position:relative;overflow:hidden;border-radius:12px;padding:14px 14px 12px;color:#fff;
        min-height:82px;border:1px solid rgba(255,255,255,.35);
        box-shadow:0 6px 16px rgba(15,23,42,.1);
    }
    .mod-hero-grid--overview .mod-hero-value{font-size:22px;}
    .mod-hero-grid--overview .mod-hero-icon{width:36px;height:36px;font-size:16px;}
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
    .mod-hero-card.qt-stat-clickable{cursor:pointer;transition:transform .12s ease,box-shadow .12s ease;}
    .mod-hero-card.qt-stat-clickable:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(15,23,42,.14);}
    .mod-hero-card.is-active{box-shadow:0 0 0 3px rgba(255,255,255,.9),0 10px 22px rgba(15,23,42,.16);}
    tr[data-inv-row="1"].inv-row-overdue td{
        background:#fef2f2!important;
    }
    tr[data-inv-row="1"].inv-row-overdue:hover td{
        background:#fee2e2!important;
    }
    tr[data-inv-row="1"].inv-row-overdue .inv-id{
        color:#dc2626;
    }
</style>

<?php
ops_docs_render_header([
    'title' => 'Invoices',
    'subtitle' => 'Track billing, payment status, and overdue collections.',
    'search_placeholder' => 'Search invoices…',
    'search_label' => 'Search invoices',
    'actions_html' => '',
]);
?>

<?php if ($success): ?>
<div class="inv-flash-wrap" id="invFlashWrap">
    <div class="inv-flash-card inv-flash-card--success">
        <div class="inv-flash-icon"><i class="fas fa-check"></i></div>
        <div class="inv-flash-title">Success</div>
        <div class="inv-flash-body"><?php echo htmlspecialchars($success); ?></div>
    </div>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="inv-flash-wrap" id="invFlashWrap">
    <div class="inv-flash-card inv-flash-card--error">
        <div class="inv-flash-icon"><i class="fas fa-exclamation"></i></div>
        <div class="inv-flash-title">Error</div>
        <div class="inv-flash-body"><?php echo htmlspecialchars($error); ?></div>
    </div>
</div>
<?php endif; ?>

<?php ops_docs_layout_open(); ?>

<?php ops_docs_render_folders('Folders', $inv_folders, 'invFoldersViewAll'); ?>

<?php ops_docs_files_section_open('Files', 'invFilesViewAll'); ?>
<span id="invTableTitle" hidden>All Invoices</span>
<span id="invTableMeta" hidden aria-hidden="true"></span>

<div class="qt-section qt-find-panel ops-filter-card">
    <div class="ops-filter-row">
        <span class="ops-filter-label">Collections</span>
        <div class="qt-tabs" data-inv-tab-group="collections">
            <button type="button" class="qt-tab active" data-filter="all" data-title="All Invoices">All</button>
            <button type="button" class="qt-tab" data-filter="overdue" data-title="Overdue">Overdue</button>
            <button type="button" class="qt-tab" data-filter="open" data-title="Outstanding">Outstanding</button>
        </div>
    </div>

    <div class="ops-filter-row">
        <span class="ops-filter-label">Payment</span>
        <div class="qt-tabs qt-tabs--secondary" data-inv-tab-group="payment">
            <button type="button" class="qt-tab" data-filter="unpaid" data-title="Unpaid">Unpaid</button>
            <button type="button" class="qt-tab" data-filter="partial" data-title="Partial">Partial</button>
            <button type="button" class="qt-tab" data-filter="paid" data-title="Paid">Paid</button>
        </div>
    </div>

    <div class="ops-filter-footer">
        <label class="ops-filter-sort qt-control qt-control--sort">
            <select id="sortFilter" class="erp-input erp-input-sm" aria-label="Sort invoices">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="amount_high">Amount (high → low)</option>
                <option value="amount_low">Amount (low → high)</option>
            </select>
        </label>
        <p class="ops-filter-result" id="opsFilterResult"></p>
    </div>
</div>

<?php ops_docs_table_card_open('invInvoicesList'); ?>
    <div class="erp-card-body erp-p-0">
        <div class="erp-student-table-wrap">
        <div class="erp-table-container qt-table-scroll">
            <table class="erp-table erp-student-table inv-list-table" id="invoiceTable" data-st-row-selector="tr[data-inv-row=&quot;1&quot;]">
                <colgroup>
                    <col class="inv-col-id" style="width:128px">
                    <col class="inv-col-progress" style="width:68px">
                    <col class="inv-col-job" style="width:72px">
                    <col class="inv-col-client" style="width:128px">
                    <col class="inv-col-amount" style="width:108px">
                    <col class="inv-col-issued" style="width:100px">
                    <col class="inv-col-due" style="width:100px">
                    <col class="inv-col-payment" style="width:100px">
                    <col class="inv-col-quote" style="width:88px">
                    <?php if ($is_admin_user): ?><col class="inv-col-actions" style="width:52px"><?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <?php echo st_sortable_th('Invoice #', 'inv-col-id'); ?>
                        <?php echo st_plain_th('Progress', 'inv-col-progress'); ?>
                        <?php echo st_sortable_th('Job card', 'inv-col-job'); ?>
                        <?php echo st_sortable_th('Client', 'inv-col-client'); ?>
                        <?php echo st_sortable_th('Amount', 'inv-col-amount'); ?>
                        <?php echo st_sortable_th('Issued', 'inv-col-issued', true, 'Date'); ?>
                        <?php echo st_sortable_th('Due', 'inv-col-due', true, 'Date'); ?>
                        <?php echo st_sortable_th('Payment', 'inv-col-payment', true, 'Status'); ?>
                        <?php echo st_plain_th('Quotation', 'inv-col-quote st-no-row-nav'); ?>
                        <?php if ($is_admin_user): ?>
                        <?php echo st_plain_th('Action', 'st-col-actions qt-col-actions st-no-row-nav'); ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv):
                        $invIssued = $inv['issued_date'] ?? null;
                        $invStatus = strtolower((string) ($inv['status_paid'] ?? ''));
                        $invOverdue = false;
                        if (in_array($invStatus, ['unpaid', 'partial'], true) && !empty($inv['due_date'])) {
                            $dueTs = strtotime((string) $inv['due_date']);
                            $invOverdue = ($dueTs !== false && $dueTs < strtotime(date('Y-m-d')));
                        }
                        $invWf = inv_workflow_from_row($invStatus, $invOverdue);
                        $invWfTitle = ($invWf['next_label'] ?? '') . ' — ' . ($invWf['next_hint'] ?? '');
                        $rowClass = trim($invOverdue ? 'inv-row-overdue' : '');
                    ?>
                    <tr data-inv-row="1" data-status="<?php echo htmlspecialchars($invStatus, ENT_QUOTES, 'UTF-8'); ?>" data-inv-id="<?php echo (int)$inv['invoice_id']; ?>" data-date-ts="<?php echo !empty($invIssued) ? (int)strtotime($invIssued) : 0; ?>" data-amount="<?php echo (float)$inv['amount']; ?>" data-overdue="<?php echo $invOverdue ? '1' : '0'; ?>" data-in-today="<?php echo inv_issued_in_period($invIssued, 'today') ? '1' : '0'; ?>" data-in-week="<?php echo inv_issued_in_period($invIssued, 'week') ? '1' : '0'; ?>" data-in-month="<?php echo inv_issued_in_period($invIssued, 'month') ? '1' : '0'; ?>" data-in-year="<?php echo inv_issued_in_period($invIssued, 'year') ? '1' : '0'; ?>" data-row-url="<?php echo htmlspecialchars($inv_view_base, ENT_QUOTES, 'UTF-8'); ?><?php echo (int)$inv['invoice_id']; ?>"<?php if ($rowClass !== ''): ?> class="<?php echo $rowClass; ?>"<?php endif; ?> style="cursor:pointer;">
                        <td class="inv-col-id"><div class="qt-td-inner"><span class="qt-id"><?php echo htmlspecialchars($inv['invoice_number']); ?></span></div></td>
                        <td class="inv-col-progress" onclick="event.stopPropagation();">
                            <div class="qt-td-inner qt-td-inner--center">
                            <div class="qt-row-progress" title="<?php echo htmlspecialchars($invWfTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php foreach ($invWf['steps'] as $wfStep): ?>
                                <span class="qt-row-dot<?php echo !empty($wfStep['done']) ? ' is-done' : ''; ?><?php echo !empty($wfStep['current']) ? ' is-current' : ''; ?>" aria-hidden="true"></span>
                                <?php endforeach; ?>
                            </div>
                            </div>
                        </td>
                        <td class="inv-col-job"><div class="qt-td-inner"><?php echo htmlspecialchars($inv['job_card'] ?? '-'); ?></div></td>
                        <td class="inv-col-client"><div class="qt-td-inner"><span class="st-cell-primary"><?php echo htmlspecialchars($inv['client_name'] ?? 'Walk-in'); ?></span></div></td>
                        <td class="inv-col-amount"><div class="qt-td-inner qt-td-inner--end"><?php echo formatMoney($inv['amount']); ?></div></td>
                        <td class="inv-col-issued"><div class="qt-td-inner"><?php echo inv_list_date_cell($invIssued); ?></div></td>
                        <td class="inv-col-due"><div class="qt-td-inner"><?php echo inv_list_date_cell($inv['due_date'] ?? null); ?></div></td>
                        <td class="inv-col-payment">
                            <div class="qt-td-inner">
                            <span class="qt-status qt-status-<?php echo htmlspecialchars($invStatus, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo ucfirst($inv['status_paid']); ?>
                            </span>
                            </div>
                        </td>
                        <td class="inv-col-quote st-no-row-nav" onclick="event.stopPropagation();">
                            <div class="qt-td-inner">
                            <?php if (!empty($inv['quotation_id'])): ?>
                            <a href="<?php echo htmlspecialchars($inv_quote_base, ENT_QUOTES, 'UTF-8'); ?><?php echo (int)$inv['quotation_id']; ?>" class="inv-link-quote">QTN #<?php echo (int)$inv['quotation_id']; ?></a>
                            <?php else: ?>
                            <span class="inv-cell-muted">—</span>
                            <?php endif; ?>
                            </div>
                        </td>
                        <?php if ($is_admin_user):
                            echo st_actions_cell([
                                'delete_attrs' => [
                                    'data-inv-delete-id' => (string) (int) $inv['invoice_id'],
                                    'data-inv-delete-label' => $inv['invoice_number'],
                                ],
                                'delete_label' => 'Delete invoice ' . $inv['invoice_number'],
                            ]);
                        endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                    <tr class="qt-helper-row" id="invEmptyRow">
                        <td colspan="<?php echo (int) $inv_table_colspan; ?>">
                            <div class="erp-empty-state">
                                <div class="erp-empty-icon"><i class="fas fa-file-invoice"></i></div>
                                <div class="erp-empty-title">No invoices yet</div>
                                <div class="erp-empty-text">Invoices will appear here once you create them from saved quotations</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="qt-helper-row" id="invNoMatchRow" style="display:none;">
                        <td colspan="<?php echo (int) $inv_table_colspan; ?>"><i class="fas fa-search"></i> No matching invoices found</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php st_render_table_footer('invoiceTable'); ?>
        </div>
    </div>
</div>
<?php ops_docs_files_section_close(); ?>

<?php
ops_docs_render_sidebar([
    'overview_title' => 'Collections',
    'donut_segments' => $inv_donut_segments,
    'donut_center' => number_format($inv_total_count),
    'donut_sub' => 'invoices',
    'stats_title' => 'Quick stats',
    'bars' => $inv_sidebar_bars,
    'recent_title' => 'Recent activity',
    'recent' => $inv_sidebar_recent,
]);
ops_docs_init_script();
?>

<?php if ($is_admin_user): ?>
<!-- Delete modal — same blueprint as job_card.php -->
<div id="invDeleteModal" class="aq-name-modal">
    <div class="aq-name-modal-card">
        <div class="aq-name-modal-title">Delete Invoice</div>
        <div class="aq-name-modal-sub" id="invDeleteModalMessage">Move this invoice to the recycle bin? It will be removed from the active invoice list and can be restored later if needed.</div>
        <div class="aq-name-modal-actions">
            <button type="button" id="invDeleteModalCancel" class="aq-s1-btn aq-s1-btn--gray">Cancel</button>
            <form method="POST" id="deleteInvoiceForm" action="<?php echo htmlspecialchars($inv_list_url, ENT_QUOTES, 'UTF-8'); ?>">
                <div id="invDeleteIdsWrap"></div>
                <input type="hidden" name="delete_id" id="invDeleteId" value="">
                <button type="submit" class="aq-s1-btn aq-s1-btn--danger" id="invDeleteSubmitBtn"><i class="fas fa-trash"></i> Move to Recycle Bin</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const invoiceTable = document.getElementById('invoiceTable');
const searchInput = document.getElementById('searchInput');
const sortFilter = document.getElementById('sortFilter');
const noMatchRow = document.getElementById('invNoMatchRow');
const emptyRow = document.getElementById('invEmptyRow');
const dashCards = Array.from(document.querySelectorAll('.qt-stat-clickable'));
let activePeriod = null;

function getActiveTabInGroup(groupName) {
    const group = document.querySelector('.qt-tabs[data-inv-tab-group="' + groupName + '"]');
    if (!group) return null;
    return group.querySelector('.qt-tab.active');
}

function getCollectionsFilter() {
    const tab = getActiveTabInGroup('collections');
    return tab ? (tab.dataset.filter || 'all') : 'all';
}

function getPaymentFilter() {
    const tab = getActiveTabInGroup('payment');
    return tab ? (tab.dataset.filter || '') : '';
}

function collectionsMatches(row, filter) {
    if (!filter || filter === 'all') return true;
    if (filter === 'overdue') return (row.getAttribute('data-overdue') || '') === '1';
    if (filter === 'open') {
        const s = (row.getAttribute('data-status') || '').toLowerCase();
        return s === 'unpaid' || s === 'partial';
    }
    return true;
}

function paymentMatches(row, filter) {
    if (!filter) return true;
    return (row.getAttribute('data-status') || '').toLowerCase() === filter;
}

function periodMatches(row) {
    if (!activePeriod) return true;
    return (row.getAttribute('data-in-' + activePeriod) || '') === '1';
}

function sortInvoiceRows(rows, sortValue, tbody) {
    rows.sort(function(a, b) {
        const dateA = Number(a.getAttribute('data-date-ts') || 0);
        const dateB = Number(b.getAttribute('data-date-ts') || 0);
        const amountA = Number(a.getAttribute('data-amount') || 0);
        const amountB = Number(b.getAttribute('data-amount') || 0);
        if (sortValue === 'oldest') return dateA - dateB;
        if (sortValue === 'amount_high') return amountB - amountA;
        if (sortValue === 'amount_low') return amountA - amountB;
        return dateB - dateA;
    });
    rows.forEach(function(r) { tbody.appendChild(r); });
}

function getListTitle() {
    const activeCard = document.querySelector('.qt-stat-clickable.is-active');
    if (activeCard) {
        return activeCard.getAttribute('data-inv-list-title') || 'Invoices';
    }
    const parts = [];
    const colTab = getActiveTabInGroup('collections');
    const payTab = getActiveTabInGroup('payment');
    if (colTab && (colTab.dataset.filter || '') !== 'all') {
        parts.push(colTab.getAttribute('data-title') || 'Collections');
    }
    if (payTab) {
        parts.push(payTab.getAttribute('data-title') || 'Payment');
    }
    if (activePeriod) {
        const periodLabels = { today: 'Today', week: 'This week', month: 'This month', year: 'This year' };
        parts.push(periodLabels[activePeriod] || activePeriod);
    }
    return parts.length ? parts.join(' · ') : 'All Invoices';
}

function updateTableHeading(visibleCount) {
    const titleEl = document.getElementById('invTableTitle');
    const metaEl = document.getElementById('invTableMeta');
    if (titleEl) titleEl.textContent = getListTitle();
    if (metaEl) {
        metaEl.textContent = visibleCount === 1 ? '1 invoice' : (visibleCount + ' invoices');
    }
}

function clearDashCardActive() {
    document.querySelectorAll('.qt-stat-clickable.is-active').forEach(function(c) {
        c.classList.remove('is-active');
    });
}

function setActiveTabInGroup(groupName, tab) {
    const group = document.querySelector('.qt-tabs[data-inv-tab-group="' + groupName + '"]');
    if (!group || !tab) return;
    group.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    tab.classList.add('active');
}

function syncDashCardWithFilters() {
    clearDashCardActive();
    if (activePeriod) {
        const periodCard = document.querySelector('.qt-stat-clickable[data-inv-period="' + activePeriod + '"]');
        if (periodCard) periodCard.classList.add('is-active');
        return;
    }
    const col = getCollectionsFilter();
    const pay = getPaymentFilter();
    let card = null;
    if (col === 'overdue') card = document.querySelector('.qt-stat-clickable[data-inv-tab="overdue"]');
    else if (col === 'open') card = document.querySelector('.qt-stat-clickable[data-inv-tab="open"]');
    else if (pay === 'partial') card = document.querySelector('.qt-stat-clickable[data-inv-tab="partial"]');
    else if (pay === 'paid') card = document.querySelector('.qt-stat-clickable[data-inv-tab="paid"]');
    else if (col === 'all' && !pay) card = document.querySelector('.qt-stat-clickable[data-inv-tab="all"]');
    if (card) card.classList.add('is-active');
}

function scrollToInvoicesList() {
    const el = document.getElementById('invInvoicesList');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function activateFromDashboardCard(card) {
    clearDashCardActive();
    card.classList.add('is-active');
    activePeriod = card.getAttribute('data-inv-period') || null;
    const tabFilter = card.getAttribute('data-inv-tab');
    if (tabFilter === 'overdue' || tabFilter === 'open') {
        const colTab = document.querySelector('.qt-tabs[data-inv-tab-group="collections"] .qt-tab[data-filter="' + tabFilter + '"]');
        if (colTab) setActiveTabInGroup('collections', colTab);
        const payGroup = document.querySelector('.qt-tabs[data-inv-tab-group="payment"]');
        if (payGroup) payGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    } else if (tabFilter === 'partial' || tabFilter === 'paid') {
        const payTab = document.querySelector('.qt-tabs[data-inv-tab-group="payment"] .qt-tab[data-filter="' + tabFilter + '"]');
        if (payTab) setActiveTabInGroup('payment', payTab);
        const allCol = document.querySelector('.qt-tabs[data-inv-tab-group="collections"] .qt-tab[data-filter="all"]');
        if (allCol) setActiveTabInGroup('collections', allCol);
    } else if (tabFilter === 'all') {
        const allCol = document.querySelector('.qt-tabs[data-inv-tab-group="collections"] .qt-tab[data-filter="all"]');
        if (allCol) setActiveTabInGroup('collections', allCol);
        const payGroup = document.querySelector('.qt-tabs[data-inv-tab-group="payment"]');
        if (payGroup) payGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    } else if (activePeriod) {
        const allCol = document.querySelector('.qt-tabs[data-inv-tab-group="collections"] .qt-tab[data-filter="all"]');
        if (allCol) setActiveTabInGroup('collections', allCol);
        const payGroup = document.querySelector('.qt-tabs[data-inv-tab-group="payment"]');
        if (payGroup) payGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    }
    applyFilters();
    scrollToInvoicesList();
}

function applyFilters() {
    if (!invoiceTable) return;
    const tbody = invoiceTable.querySelector('tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr[data-inv-row="1"]'));
    const query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
    const colFilter = getCollectionsFilter();
    const payFilter = getPaymentFilter();
    const sortBy = sortFilter ? sortFilter.value : 'newest';

    sortInvoiceRows(rows, sortBy, tbody);

    let visibleCount = 0;
    rows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        const show = text.includes(query)
            && collectionsMatches(row, colFilter)
            && paymentMatches(row, payFilter)
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
    if (window.ErpStudentTable) {
        ErpStudentTable.sync('invoiceTable');
    }
}

function onFindTabClick(tab) {
    const group = tab.closest('.qt-tabs');
    if (!group) return;
    const groupName = group.getAttribute('data-inv-tab-group');
    setActiveTabInGroup(groupName, tab);
    if (groupName === 'collections') activePeriod = null;
    clearDashCardActive();
    syncDashCardWithFilters();
    applyFilters();
}

document.querySelectorAll('.qt-tabs[data-inv-tab-group] .qt-tab').forEach(function(tab) {
    tab.addEventListener('click', function() { onFindTabClick(tab); });
});

if (searchInput) searchInput.addEventListener('input', applyFilters);
if (sortFilter) sortFilter.addEventListener('change', applyFilters);

dashCards.forEach(function(card) {
    card.addEventListener('click', function() { activateFromDashboardCard(card); });
    card.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            activateFromDashboardCard(card);
        }
    });
});

(function initDefaultView() {
    const allCol = document.querySelector('.qt-tabs[data-inv-tab-group="collections"] .qt-tab[data-filter="all"]');
    if (allCol) setActiveTabInGroup('collections', allCol);
    const allCard = document.querySelector('.qt-stat-clickable[data-inv-tab="all"]');
    if (allCard) allCard.classList.add('is-active');
})();
applyFilters();

function invResetAllFilters() {
    activePeriod = null;
    const colGroup = document.querySelector('.qt-tabs[data-inv-tab-group="collections"]');
    const payGroup = document.querySelector('.qt-tabs[data-inv-tab-group="payment"]');
    if (colGroup) {
        colGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
        const allTab = colGroup.querySelector('.qt-tab[data-filter="all"]');
        if (allTab) allTab.classList.add('active');
    }
    if (payGroup) {
        payGroup.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    }
    document.querySelectorAll('.qt-stat-clickable.is-active').forEach(function(c) { c.classList.remove('is-active'); });
    if (searchInput) searchInput.value = '';
    applyFilters();
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.opsDocsSyncListMeta === 'function') window.opsDocsSyncListMeta();
    if (typeof window.opsDocsWireViewAll === 'function') {
        window.opsDocsWireViewAll('invFoldersViewAll', invResetAllFilters);
        window.opsDocsWireViewAll('invFilesViewAll', invResetAllFilters);
    }
});

document.addEventListener('erp-student-tables-ready', function () {
    if (typeof applyFilters === 'function') applyFilters();
});

(function initInvDelete() {
    const modal = document.getElementById('invDeleteModal');
    const deleteIdInput = document.getElementById('invDeleteId');
    const deleteIdsWrap = document.getElementById('invDeleteIdsWrap');
    const cancelBtn = document.getElementById('invDeleteModalCancel');
    const submitBtn = document.getElementById('invDeleteSubmitBtn');
    const messageEl = document.getElementById('invDeleteModalMessage');
    if (!modal || !deleteIdInput) return;

    const defaultMessage = 'Move this invoice to the recycle bin? It will be removed from the active invoice list and can be restored later if needed.';

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
                const ref = (labelList[0] || '').trim();
                messageEl.textContent = ref
                    ? ('Move invoice ' + ref + ' to the recycle bin? It will be removed from the active invoice list and can be restored later if needed.')
                    : defaultMessage;
            } else {
                messageEl.textContent = 'Move ' + idList.length + ' invoices to the recycle bin? They will be removed from the active list and can be restored later if needed.';
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

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-inv-delete-id]');
        if (!btn || !invoiceTable || !invoiceTable.contains(btn)) return;
        e.preventDefault();
        e.stopPropagation();
        openDeleteModal(
            [Number(btn.getAttribute('data-inv-delete-id'))],
            [btn.getAttribute('data-inv-delete-label') || '']
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

if (invoiceTable) {
    invoiceTable.addEventListener('click', function(e) {
        if (e.target.closest('.st-col-actions, .qt-col-actions, .st-act-btn, .st-no-row-nav, a, button, input, label')) return;
        const row = e.target.closest('tr[data-inv-row="1"]');
        if (!row) return;
        const url = row.getAttribute('data-row-url');
        if (url) window.location.href = url;
    });
}

window.addEventListener('load', function () {
    const wrap = document.getElementById('invFlashWrap');
    if (!wrap) return;
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    setTimeout(function () {
        wrap.classList.remove('show');
        setTimeout(function () { wrap.remove(); }, 220);
    }, 3200);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
