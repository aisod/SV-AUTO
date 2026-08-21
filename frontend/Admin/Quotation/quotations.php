<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';

// Both admin and manager can view quotations
require_role(['admin', 'manager']);
require_once __DIR__ . '/quotation_paper_signoff.inc.php';
require_once __DIR__ . '/quotations_layout.inc.php';
require_once __DIR__ . '/../includes/student_table.inc.php';
require_once __DIR__ . '/../includes/operations_docs_layout.inc.php';

qt_sync_paper_signoff_system_status($pdo);

function qt_list_submitted_cell(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '-';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '-';
    }
    $date = date('d M Y', $ts);
    $isDateOnly = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($raw));
    if ($isDateOnly) {
        return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
    }
    $time = date('H:i', $ts);
    return htmlspecialchars($date, ENT_QUOTES, 'UTF-8')
        . ' <span class="qt-list-time">' . htmlspecialchars($time, ENT_QUOTES, 'UTF-8') . '</span>';
}

/** Match overview SQL periods (submitted_at date). */
function qt_submitted_in_period(?string $raw, string $period): bool
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
            $dt = new DateTime($ymd);
            $now = new DateTime($nowYmd);
            return $dt->format('oW') === $now->format('oW');
        case 'month':
            return date('Y-m', $ts) === date('Y-m');
        case 'year':
            return date('Y', $ts) === date('Y');
        default:
            return true;
    }
}

$is_manager_user = strtolower($_SESSION['role_name'] ?? '') === 'manager';
$is_admin_user = is_admin();
$can_view_financial = erp_can_view_financial_summary();

$currency = getCurrency();
$business = getBusiness();

// Paper sign-off (manager signed printed copy) — admin only, non-blocking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paper_signoff_action']) && $is_admin_user) {
    $quoteId = (int) ($_POST['quote_id'] ?? 0);
    $action = (string) ($_POST['paper_signoff_action'] ?? '');
    try {
        if ($quoteId <= 0) {
            throw new RuntimeException('Invalid quotation.');
        }
        if ($action === 'mark_signed') {
            $signedAt = trim((string) ($_POST['paper_signed_at'] ?? ''));
            $signedBy = trim((string) ($_POST['paper_signed_by'] ?? ''));
            if ($signedAt === '') {
                $signedAt = date('Y-m-d');
            }
            if (!qt_update_paper_signoff($pdo, $quoteId, $signedAt, $signedBy)) {
                throw new RuntimeException('Could not update quotation.');
            }
            header('Location: quotations.php?success=' . urlencode('Paper sign-off recorded.'));
            exit;
        }
        if ($action === 'clear') {
            if (!qt_clear_paper_signoff($pdo, $quoteId)) {
                throw new RuntimeException('Could not update quotation.');
            }
            header('Location: quotations.php?success=' . urlencode('Paper sign-off cleared.'));
            exit;
        }
        if ($action === 'mark_rejected') {
            $rejectedAt = trim((string) ($_POST['rejected_at'] ?? ''));
            $rejectedBy = trim((string) ($_POST['rejected_by'] ?? ''));
            $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
            if ($rejectedAt === '') {
                $rejectedAt = date('Y-m-d');
            }
            if ($reason === '') {
                throw new RuntimeException('Rejection reason is required.');
            }
            if (!qt_update_paper_rejection($pdo, $quoteId, $rejectedAt, $rejectedBy, $reason)) {
                throw new RuntimeException('Could not update quotation.');
            }
            header('Location: quotations.php?success=' . urlencode('Manager rejection recorded.'));
            exit;
        }
        throw new RuntimeException('Unknown action.');
    } catch (Exception $e) {
        header('Location: quotations.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}

// âœ… DELETE HANDLER â€” ADMIN ONLY (Soft Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_admin() && (isset($_POST['delete_id']) || isset($_POST['delete_ids']))) {
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

    if ($deleteIds !== []) {
        try {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
            $stmt = $pdo->prepare("UPDATE quotations SET deleted_at = NOW() WHERE id IN ($placeholders) AND deleted_at IS NULL");
            $stmt->execute($deleteIds);

        $log = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, entity_type, entity_id) 
            VALUES (?, 'deleted_quotation', 'quotation', ?)
        ");
            foreach ($deleteIds as $delete_id) {
        $log->execute([$_SESSION['user_id'], $delete_id]);
            }

            $count = count($deleteIds);
            $msg = $count === 1
                ? 'Quotation moved to recycle bin.'
                : ($count . ' quotations moved to recycle bin.');
            header('Location: quotations.php?success=' . urlencode($msg));
        exit;
    } catch (Exception $e) {
            header('Location: quotations.php?error=' . urlencode('Failed to delete quotation(s).'));
        exit;
        }
    }
}

// Summary stats (exclude soft-deleted) — use submitted_at, fall back to created_at
$stmt = $pdo->query("SELECT 
    COUNT(*) AS total_count,
    COALESCE(SUM(amount), 0) AS total_quoted,
    COUNT(CASE WHEN status IN ('pending', 'pending_manager', 'sent_back_admin') THEN 1 END) AS pending_count,
    COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS approved_amount,
    COUNT(CASE WHEN DATE(COALESCE(submitted_at, created_at)) = CURDATE() THEN 1 END) AS today_count,
    COALESCE(SUM(CASE WHEN DATE(COALESCE(submitted_at, created_at)) = CURDATE() THEN amount ELSE 0 END), 0) AS today_amount,
    COUNT(CASE WHEN YEARWEEK(DATE(COALESCE(submitted_at, created_at)), 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS week_count,
    COALESCE(SUM(CASE WHEN YEARWEEK(DATE(COALESCE(submitted_at, created_at)), 1) = YEARWEEK(CURDATE(), 1) THEN amount ELSE 0 END), 0) AS week_amount,
    COUNT(CASE WHEN YEAR(COALESCE(submitted_at, created_at)) = YEAR(CURDATE())
        AND MONTH(COALESCE(submitted_at, created_at)) = MONTH(CURDATE()) THEN 1 END) AS month_count,
    COALESCE(SUM(CASE WHEN YEAR(COALESCE(submitted_at, created_at)) = YEAR(CURDATE())
        AND MONTH(COALESCE(submitted_at, created_at)) = MONTH(CURDATE()) THEN amount ELSE 0 END), 0) AS month_amount,
    COUNT(CASE WHEN YEAR(COALESCE(submitted_at, created_at)) = YEAR(CURDATE()) THEN 1 END) AS year_count,
    COALESCE(SUM(CASE WHEN YEAR(COALESCE(submitted_at, created_at)) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) AS year_amount
FROM quotations
WHERE deleted_at IS NULL");
$summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

// All Quotations (exclude soft-deleted)
$orderBy = $is_manager_user
    ? "ORDER BY CASE 
            WHEN q.status = 'pending_manager' THEN 0
            WHEN q.status = 'pending' THEN 1
            WHEN q.status = 'sent_back_admin' THEN 2
            ELSE 3
        END, q.submitted_at DESC"
    : "ORDER BY q.submitted_at DESC";
$stmt = $pdo->query("SELECT 
    q.id AS quote_id,
    jc.card_number AS job_card,
    c.name AS client_name,
    q.amount,
    q.submitted_at AS date,
    q.status,
    q.client_status,
    q.details
FROM quotations q
LEFT JOIN job_cards jc ON q.job_card_id = jc.id
LEFT JOIN clients c ON q.client_id = c.id
WHERE q.deleted_at IS NULL
" . $orderBy);
$quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($quotations as &$qRow) {
    $payload = qt_parse_quotation_details((string) ($qRow['details'] ?? ''));
    $form = (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) ? $payload['form'] : [];
    $paper = qt_paper_signoff_info($form);
    $rejection = qt_paper_rejection_info($form);
    $qRow['paper_signed'] = $paper['signed'];
    $qRow['paper_signed_at'] = $paper['at'];
    $qRow['paper_signed_by'] = $paper['by'];
    $qRow['form_status'] = strtolower(trim((string) ($form['status'] ?? '')));
    $qRow['rejected_at'] = $rejection['at'];
    $qRow['rejected_by'] = $rejection['by'];
    $qRow['rejection_reason'] = $rejection['reason'];
    $qRow['workflow'] = qt_workflow_from_quotation_row([
        'details' => $qRow['details'],
        'status' => $qRow['status'] ?? 'draft',
        'client_status' => $qRow['client_status'] ?? '',
    ]);
    unset($qRow['details']);
}
unset($qRow);

$qt_period_counter = static function (?string $raw, string $period): bool {
    return qt_submitted_in_period($raw, $period);
};

// Dashboard cards — action queues first, then period snapshots (counts use same rules as list filters)
$qt_dashboard_cards = [
    [
        'section' => 'action',
        'tone' => 'amber',
        'label' => 'Awaiting manager signature',
        'value' => '0',
        'sub' => 'Print out · not signed on paper yet',
        'icon' => 'fa-file-signature',
        'tab' => 'paper_pending',
        'system_tabs' => 'pending_group',
        'period' => null,
        'list_title' => 'Awaiting manager signature',
    ],
    [
        'section' => 'action',
        'tone' => 'green',
        'label' => 'Manager signed (paper)',
        'value' => '0',
        'sub' => 'Sign-off saved · approved in system',
        'icon' => 'fa-check-circle',
        'tab' => 'paper_signed',
        'system_tabs' => 'approved',
        'period' => null,
        'list_title' => 'Manager signed (paper)',
    ],
    [
        'section' => 'action',
        'tone' => 'blue',
        'label' => 'Awaiting client response',
        'value' => '0',
        'sub' => 'Approved · client has not answered yet',
        'icon' => 'fa-paper-plane',
        'tab' => 'awaiting_client',
        'system_tabs' => 'approved',
        'period' => null,
        'list_title' => 'Awaiting client response',
    ],
    [
        'section' => 'action',
        'tone' => 'red',
        'label' => 'Manager declined',
        'value' => '0',
        'sub' => 'Rejected on paper',
        'icon' => 'fa-times-circle',
        'tab' => null,
        'system_tabs' => 'rejected',
        'period' => null,
        'list_title' => 'Manager declined',
    ],
    [
        'section' => 'period',
        'tone' => 'slate',
        'label' => 'Today',
        'value' => '0',
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            date('d M Y'),
            date('d M Y') . ' · ' . formatMoney((float) ($summary['today_amount'] ?? 0))
        ),
        'icon' => 'fa-calendar-day',
        'tab' => null,
        'period' => 'today',
        'list_title' => 'Submitted today',
    ],
    [
        'section' => 'period',
        'tone' => 'purple',
        'label' => 'This week',
        'value' => '0',
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'Mon – Sun',
            'Mon – Sun · ' . formatMoney((float) ($summary['week_amount'] ?? 0))
        ),
        'icon' => 'fa-calendar-week',
        'tab' => null,
        'period' => 'week',
        'list_title' => 'Submitted this week',
    ],
    [
        'section' => 'period',
        'tone' => 'cyan',
        'label' => 'This month',
        'value' => '0',
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            date('F Y'),
            date('F Y') . ' · ' . formatMoney((float) ($summary['month_amount'] ?? 0))
        ),
        'icon' => 'fa-calendar-alt',
        'tab' => null,
        'period' => 'month',
        'list_title' => 'Submitted this month',
    ],
    [
        'section' => 'period',
        'tone' => 'orange',
        'label' => 'All quotations',
        'value' => '0',
        'sub' => erp_overview_card_sub(
            $can_view_financial,
            'All quotations',
            formatMoney((float) ($summary['total_quoted'] ?? 0)) . ' quoted'
        ),
        'icon' => 'fa-file-invoice',
        'tab' => 'all',
        'system_tabs' => 'all',
        'period' => null,
        'list_title' => 'All quotations',
    ],
];

foreach ($qt_dashboard_cards as &$qtCard) {
    $qtCard['value'] = number_format(qt_count_dashboard_card($quotations, $qtCard, $qt_period_counter));
}
unset($qtCard);

$qt_total_count = (int) ($summary['total_count'] ?? 0);
$qt_pending_sidebar = (int) ($summary['pending_count'] ?? 0);
$qt_approved_sidebar = 0;
foreach ($quotations as $qtSideRow) {
    if (strtolower((string) ($qtSideRow['status'] ?? '')) === 'approved') {
        $qt_approved_sidebar++;
    }
}

$qt_folders = [];
foreach ([0, 1, 2, 3, 6, 8] as $qtFolderIdx) {
    if (!isset($qt_dashboard_cards[$qtFolderIdx])) {
        continue;
    }
    $card = $qt_dashboard_cards[$qtFolderIdx];
    $attrs = ['data-qt-list-title' => (string) ($card['list_title'] ?? 'Quotations')];
    if (!empty($card['tab'])) {
        $attrs['data-qt-tab'] = (string) $card['tab'];
    }
    if (!empty($card['system_tabs'])) {
        $attrs['data-qt-system-tabs'] = (string) $card['system_tabs'];
    }
    if (!empty($card['period'])) {
        $attrs['data-qt-period'] = (string) $card['period'];
    }
    $qt_folders[] = [
        'label' => (string) ($card['label'] ?? 'Folder'),
        'count' => (int) str_replace(',', '', (string) ($card['value'] ?? '0')),
        'unit' => 'quotes',
        'icon' => 'fa-folder',
        'tone' => (string) ($card['tone'] ?? 'orange'),
        'clickable' => true,
        'class' => 'qt-stat-clickable',
        'attrs' => $attrs,
    ];
}

$qt_sidebar_bars = ops_docs_pct_bars([
    ['label' => 'Pending approval', 'count' => $qt_pending_sidebar, 'tone' => 'amber', 'icon' => 'fa-clock'],
    ['label' => 'Approved', 'count' => $qt_approved_sidebar, 'tone' => 'green', 'icon' => 'fa-check'],
    ['label' => 'All quotations', 'count' => $qt_total_count, 'tone' => 'blue', 'icon' => 'fa-file-invoice'],
], max(1, $qt_total_count));

$qt_donut_segments = [
    ['label' => 'Pending', 'value' => (string) number_format($qt_pending_sidebar), 'pct' => $qt_pending_sidebar, 'color' => '#d97706'],
    ['label' => 'Approved', 'value' => (string) number_format($qt_approved_sidebar), 'pct' => $qt_approved_sidebar, 'color' => '#059669'],
    ['label' => 'Other', 'value' => (string) number_format(max(0, $qt_total_count - $qt_pending_sidebar - $qt_approved_sidebar)), 'pct' => max(0, $qt_total_count - $qt_pending_sidebar - $qt_approved_sidebar), 'color' => '#94a3b8'],
];

$qt_sidebar_recent = [];
foreach (array_slice($quotations, 0, 5) as $qtRecent) {
    $qtId = (int) ($qtRecent['quote_id'] ?? 0);
    $clientName = trim((string) ($qtRecent['client_name'] ?? ''));
    $qt_sidebar_recent[] = [
        'title' => 'Quotation #' . $qtId,
        'meta' => $clientName !== '' ? $clientName : 'Quotation updated',
        'time' => ops_docs_time_ago($qtRecent['date'] ?? null),
        'href' => app_url('Admin/Quotation/add_quotation.php?id=' . $qtId),
        'initials' => $clientName !== '' ? strtoupper(substr($clientName, 0, 1)) : 'Q',
    ];
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

include __DIR__ . '/../includes/header.php';

$qt_quote_view_base = ($erp_admin_base_path ?? '') . 'Quotation/add_quotation.php?id=';
$qt_list_url = function_exists('app_url') ? app_url('Admin/Quotation/quotations.php') : 'quotations.php';
$qt_table_colspan = $is_admin_user ? 11 : 9;
?>

<style>
<?php echo qt_render_layout_blueprint_css(); ?>
    /* List table palette — quotations */
    #quotationTable.erp-student-table.qt-list-table thead th{
        background:#f8fafc!important;
        color:#475569!important;
        border-bottom:1px solid #e2e8f0!important;
    }
    #quotationTable.erp-student-table.qt-list-table thead th.sortable:hover{
        background:#f1f5f9!important;
    }
    #quotationTable.erp-student-table.qt-list-table thead th.st-col-actions,
    #quotationTable.erp-student-table.qt-list-table thead th.qt-col-actions,
    #quotationTable.erp-student-table.qt-list-table thead th.st-col-check,
    #quotationTable.erp-student-table.qt-list-table thead th.qt-col-check{
        background:#f1f5f9!important;
    }
    #quotationTable.erp-student-table.qt-list-table thead th .st-th-text{color:#475569;}
    #quotationTable.erp-student-table.qt-list-table thead .qt-col-hint,
    #quotationTable.erp-student-table.qt-list-table .st-th-sub{color:#94a3b8;}
    #quotationTable.erp-student-table.qt-list-table tbody td{
        color:#475569!important;
        border-top-color:#e2e8f0!important;
    }
    #quotationTable.erp-student-table.qt-list-table tbody tr:nth-child(odd) td{background:#fff!important;}
    #quotationTable.erp-student-table.qt-list-table tbody tr:nth-child(even) td{background:#f8fafc!important;}
    #quotationTable.erp-student-table.qt-list-table tbody tr[data-quote-row="1"]:hover td,
    #quotationTable.erp-student-table.qt-list-table tbody tr.qt-row-selected td{background:#fff7ed!important;}
    #quotationTable.erp-student-table .st-cell-primary{color:#1e293b;}
    #quotationTable.erp-student-table th.sort-asc .st-sort-up,
    #quotationTable.erp-student-table th.sort-desc .st-sort-down{color:#c2410c;}
    #quotationTable.erp-student-table .st-act-btn--delete{
        background:#fee2e2!important;
        color:#ef4444!important;
    }
    #quotationTable.erp-student-table .st-act-btn--delete:hover{
        background:#fecaca!important;
        color:#dc2626!important;
    }
    .qt-id{
        font-size:15px;
        font-weight:800;
        color:#1e293b;
        line-height:1.35;
    }
    /* qt-status shell: student-table.css blueprint */
    .qt-status-pending{background:#fff7ed;color:#c2410c;}
    .qt-status-approved{background:#ecfdf5;color:#15803d;}
    .qt-status-rejected{background:#fef2f2;color:#b91c1c;}
    .qt-status-default{background:#f1f5f9;color:#64748b;}
    .qt-status-paper-pending{background:#fff7ed;color:#c2410c;}
    .qt-status-paper-signed{background:#ecfdf5;color:#15803d;}
    .qt-page-lead{
        margin:-6px 0 18px;
        max-width:720px;
        font-size:14px;
        line-height:1.55;
        color:#64748b;
        font-weight:500;
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
    .qt-list-time{
        font-size:11px;
        font-weight:600;
        color:#64748b;
        margin-left:4px;
        white-space:nowrap;
    }
    .qt-section{
        margin-bottom:0;
    }
    /* Find panel + filter pills: erp-layout.css */
    .qt-section-head{
        display:flex;
        align-items:flex-end;
        justify-content:space-between;
        gap:12px;
        margin-bottom:10px;
        flex-wrap:wrap;
    }
    .qt-section-title{
        margin:0;
        font-size:13px;
        font-weight:900;
        letter-spacing:.05em;
        text-transform:uppercase;
        color:#334155;
    }
    .qt-section-hint{
        margin:0;
        font-size:12px;
        color:#94a3b8;
        font-weight:500;
    }
    .qt-dashboard-label{
        font-size:12px;
        font-weight:900;
        letter-spacing:.06em;
        text-transform:uppercase;
        color:#64748b;
    }
    .qt-dashboard-hint{
        font-size:12px;
        color:#64748b;
        line-height:1.45;
    }
    .mod-hero-grid--overview{
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
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
    /* Scrollbar + max-height: see erp-layout.css (.erp-table-container.qt-table-scroll) */
    .qt-col-check,
    .qt-col-actions{
        width:1%;
        white-space:nowrap;
        text-align:center;
        vertical-align:middle;
    }
    #quotationTable.erp-student-table thead th.st-col-actions,
    #quotationTable.erp-student-table thead th.qt-col-actions,
    #quotationTable.erp-student-table tbody td.st-col-actions,
    #quotationTable.erp-student-table tbody td.qt-col-actions{
        width:52px!important;
        min-width:52px!important;
        max-width:52px!important;
        padding:0!important;
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
    /* Quotations list table — see student-table.css #quotationTable */
    #quotationTable.qt-list-table{
        width:100%;
        min-width:1140px;
        table-layout:fixed;
        border-collapse:collapse;
    }
    #quotationTable.qt-list-table .qt-cell-muted{
        color:#64748b;
        font-weight:600;
    }
    #quotationTable.qt-list-table .qt-paper-cell{
        flex-direction:row;
        flex-wrap:nowrap;
        justify-content:space-between;
        gap:8px;
    }
    #quotationTable.qt-list-table .qt-paper-line{
        display:flex;
        flex-wrap:nowrap;
        align-items:center;
        gap:5px;
        min-width:0;
        flex:1 1 auto;
    }
    #quotationTable.qt-list-table .qt-paper-meta{
        font-size:10px;
        font-weight:600;
        color:#64748b;
        white-space:nowrap;
        line-height:1.2;
    }
    #quotationTable.qt-list-table .qt-paper-actions{
        display:flex;
        flex-wrap:nowrap;
        align-items:center;
        gap:4px;
        margin:0;
        flex:0 0 auto;
    }
    #quotationTable.qt-list-table .qt-paper-btn{
        padding:3px 7px;
        font-size:10px;
        border-radius:6px;
        line-height:1.2;
    }
    #quotationTable.qt-list-table .qt-status{
        max-width:100%;
        overflow:hidden;
        text-overflow:ellipsis;
        flex-shrink:1;
        min-width:0;
    }
    #quotationTable.qt-list-table .qt-status::before{
        width:6px;
        height:6px;
        flex-shrink:0;
    }
    #quotationTable.qt-list-table .erp-badge{
        display:inline-flex;
        align-items:center;
        padding:3px 8px;
        font-size:11px;
        font-weight:700;
        white-space:nowrap;
        max-width:100%;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .qt-col-hint{
        display:block;
        font-size:10px;
        font-weight:600;
        color:#94a3b8;
        text-transform:none;
        letter-spacing:0;
        margin-top:2px;
    }
    .qt-paper-actions{display:flex;flex-wrap:wrap;gap:6px;align-items:center;}
    .qt-paper-btn{
        border:1px solid #fdba74;background:#fff;
        color:#c2410c;border-radius:8px;
        padding:5px 10px;font-size:11px;font-weight:700;cursor:pointer;
    }
    .qt-paper-btn:hover{background:#ffedd5;}
    .qt-paper-btn--signed{border-color:#86efac;color:#15803d;}
    .qt-paper-btn--signed:hover{background:#ecfdf5;}
    .qt-filter-row{
        display:none;
    }
    .qt-toolbar{
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:10px;
        flex-wrap:wrap;
        width:100%;
    }
    .qt-search-hit td{
        background:#ffedd5 !important;
        box-shadow:inset 0 0 0 1px #fdba74;
    }
    .qt-helper-row td{
        text-align:center;
        padding:28px 16px !important;
        color:#64748b !important;
        background:#f8fafc;
    }
    .qt-flash-wrap{
        position:fixed;inset:0;z-index:2200;display:flex;align-items:center;justify-content:center;
        background:rgba(15,23,42,.35);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
        opacity:0;pointer-events:none;transition:opacity .2s ease;
    }
    .qt-flash-wrap.show{opacity:1;pointer-events:auto}
    .qt-flash-card{
        width:min(92vw,420px);background:#fff;border:1px solid #e5e7eb;border-radius:16px;
        box-shadow:0 24px 55px rgba(0,0,0,.25);padding:18px 18px 14px;text-align:center;
        transform:translateY(8px);transition:transform .2s ease;
    }
    .qt-flash-wrap.show .qt-flash-card{transform:translateY(0)}
    .qt-flash-icon{
        width:44px;height:44px;border-radius:999px;margin:0 auto 10px;
        display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;
    }
    .qt-flash-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
    .qt-flash-body{font-size:.9rem;line-height:1.45}
    .qt-flash-card--success .qt-flash-icon{background:#ecfdf3;color:#16a34a;border:1px solid #bbf7d0}
    .qt-flash-card--success .qt-flash-body{color:#166534}
    .qt-flash-card--error .qt-flash-icon{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
    .qt-flash-card--error .qt-flash-body{color:#991b1b}
</style>

<?php
ops_docs_render_header([
    'title' => 'Quotations',
    'subtitle' => 'Review manager sign-offs, client responses, and quotation workflow.',
    'search_placeholder' => 'Search quotations…',
    'search_label' => 'Search quotations',
    'actions_html' => '<a href="' . htmlspecialchars(app_url('Admin/JobCard/job_card.php'), ENT_QUOTES, 'UTF-8') . '" class="ops-doc-primary-btn"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Job Cards</a>',
]);
?>

<?php if ($success): ?>
<div class="qt-flash-wrap" id="qtFlashWrap">
    <div class="qt-flash-card qt-flash-card--success">
        <div class="qt-flash-icon"><i class="fas fa-check"></i></div>
        <div class="qt-flash-title">Success</div>
        <div class="qt-flash-body"><?php echo htmlspecialchars($success); ?></div>
    </div>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="qt-flash-wrap" id="qtFlashWrap">
    <div class="qt-flash-card qt-flash-card--error">
        <div class="qt-flash-icon"><i class="fas fa-exclamation"></i></div>
        <div class="qt-flash-title">Error</div>
        <div class="qt-flash-body"><?php echo htmlspecialchars($error); ?></div>
    </div>
    </div>
<?php endif; ?>

<?php ops_docs_layout_open(); ?>

<div class="qt-quotations-page" data-qt-layout-version="<?php echo htmlspecialchars(QT_LAYOUT_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="qt-page-stack">

<?php ops_docs_render_folders('Folders', $qt_folders, 'qtFoldersViewAll'); ?>

<?php ops_docs_files_section_open('Files', 'qtFilesViewAll'); ?>
<span id="qtTableTitle" hidden>Quotations</span>
<span id="qtTableMeta" hidden aria-hidden="true"></span>

<div class="qt-section qt-find-panel ops-filter-card" aria-label="Find quotations">
    <div class="ops-filter-row">
        <span class="ops-filter-label">Paper workflow</span>
        <div class="qt-tabs" data-qt-tab-group="paper">
            <button type="button" class="qt-tab active" data-filter="all" data-title="All quotations">All</button>
            <button type="button" class="qt-tab" data-filter="paper_pending" data-title="Awaiting manager signature">Awaiting sign-off</button>
            <button type="button" class="qt-tab" data-filter="paper_signed" data-title="Manager signed (paper)">Signed</button>
            <button type="button" class="qt-tab" data-filter="awaiting_client" data-title="Awaiting client response">Client pending</button>
        </div>
    </div>

    <div class="ops-filter-row">
        <span class="ops-filter-label">Saved status</span>
        <div class="qt-tabs qt-tabs--secondary" data-qt-tab-group="system">
            <button type="button" class="qt-tab" data-filter="pending_group" data-title="Awaiting manager decision">Pending approval</button>
            <button type="button" class="qt-tab" data-filter="approved" data-title="Already approved">Approved</button>
            <button type="button" class="qt-tab" data-filter="rejected" data-title="Manager declined">Declined</button>
        </div>
    </div>

    <div class="ops-filter-footer">
        <label class="ops-filter-sort qt-control qt-control--sort">
            <select id="sortFilter" class="erp-input erp-input-sm" aria-label="Sort Quotations">
                <option value="newest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="amount_high">Amount (high → low)</option>
                <option value="amount_low">Amount (low → high)</option>
            </select>
        </label>
        <p class="ops-filter-result" id="opsFilterResult"></p>
    </div>
</div>

<?php ops_docs_table_card_open('qtQuotationsList'); ?>
    <div class="erp-card-body erp-p-0">
        <?php if ($is_admin_user): ?>
        <div class="qt-table-toolbar" id="qtTableToolbar">
            <label class="qt-select-all">
                <input type="checkbox" id="qtSelectAll" aria-label="Select all visible quotations">
                <span>Select all</span>
            </label>
            <span class="qt-selection-meta" id="qtSelectionMeta">0 selected</span>
            <button type="button" class="erp-btn erp-btn-danger erp-btn-sm" id="qtDeleteSelected" disabled>
                <i class="fas fa-trash-alt" aria-hidden="true"></i> Delete selected
            </button>
        </div>
        <?php endif; ?>
        <div class="erp-student-table-wrap">
        <div class="erp-table-container qt-table-scroll">
            <table class="erp-table erp-student-table qt-list-table" id="quotationTable" data-st-row-selector="tr[data-quote-row=&quot;1&quot;]">
                <colgroup>
                    <?php if ($is_admin_user): ?><col class="qt-col-check" style="width:44px"><?php endif; ?>
                    <col class="qt-col-id" style="width:96px">
                    <col class="qt-col-progress" style="width:68px">
                    <col class="qt-col-job" style="width:72px">
                    <col class="qt-col-client" style="width:128px">
                    <col class="qt-col-amount" style="width:108px">
                    <col class="qt-col-date" style="width:128px">
                    <col class="qt-col-saved" style="width:112px">
                    <col class="qt-col-paper" style="width:220px">
                    <col class="qt-col-client-resp" style="width:112px">
                    <?php if ($is_admin_user): ?><col class="qt-col-actions" style="width:52px"><?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <?php if ($is_admin_user): ?>
                        <th class="st-col-check qt-col-check" scope="col">
                            <input type="checkbox" class="st-head-check" id="qtSelectAllHead" aria-label="Select all visible quotations">
                        </th>
                        <?php endif; ?>
                        <?php echo st_sortable_th('ID', 'qt-col-id'); ?>
                        <?php echo st_plain_th('Progress', 'qt-col-progress'); ?>
                        <?php echo st_sortable_th('Job card', 'qt-col-job'); ?>
                        <?php echo st_sortable_th('Client', 'qt-col-client'); ?>
                        <?php echo st_sortable_th('Amount', 'qt-col-amount'); ?>
                        <?php echo st_sortable_th('Date', 'qt-col-date', true, 'Submitted'); ?>
                        <?php echo st_sortable_th('Saved status', 'qt-col-saved', true, 'In system'); ?>
                        <?php echo st_plain_th('Paper sign-off', 'qt-col-paper', true, 'Manager on paper'); ?>
                        <?php echo st_plain_th('Client response', 'qt-col-client-resp'); ?>
                        <?php if ($is_admin_user): ?>
                        <?php echo st_plain_th('Action', 'st-col-actions qt-col-actions st-no-row-nav'); ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $q): 
                        $status_badge = 'erp-badge-pending';
                        if ($q['status'] === 'approved') $status_badge = 'erp-badge-success';
                        elseif ($q['status'] === 'rejected') $status_badge = 'erp-badge-danger';
                        $status_text = 'Pending';
                        if ($q['status'] === 'pending_manager') {
                            $status_text = 'Pending Approval';
                        } elseif ($q['status'] === 'sent_back_admin') {
                            $status_text = 'Sent Back to Admin';
                        } elseif ($q['status'] === 'approved') {
                            $status_text = 'Approved';
                        } elseif ($q['status'] === 'rejected') {
                            $status_text = 'Rejected';
                        }
                    ?>
                    <?php
                        $qtWf = $q['workflow'] ?? qt_workflow_from_quotation_row($q);
                        $qtWfTitle = ($qtWf['next_label'] ?? '') . ' — ' . ($qtWf['next_hint'] ?? '');
                    ?>
                    <?php
                        $qtRowDate = $q['date'] ?? null;
                    ?>
                    <?php $qtLabel = 'QTN-' . str_pad((string) $q['quote_id'], 5, '0', STR_PAD_LEFT); ?>
                    <tr data-quote-row="1" data-status="<?php echo strtolower($q['status']); ?>" data-client-status="<?php echo htmlspecialchars((string)($q['client_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-form-status="<?php echo htmlspecialchars((string)($q['form_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-quote-id="<?php echo (int)$q['quote_id']; ?>" data-quote-label="<?php echo htmlspecialchars($qtLabel, ENT_QUOTES, 'UTF-8'); ?>" data-date-ts="<?php echo !empty($qtRowDate) ? (int)strtotime($qtRowDate) : 0; ?>" data-in-today="<?php echo qt_submitted_in_period($qtRowDate, 'today') ? '1' : '0'; ?>" data-in-week="<?php echo qt_submitted_in_period($qtRowDate, 'week') ? '1' : '0'; ?>" data-in-month="<?php echo qt_submitted_in_period($qtRowDate, 'month') ? '1' : '0'; ?>" data-in-year="<?php echo qt_submitted_in_period($qtRowDate, 'year') ? '1' : '0'; ?>" data-paper-signed="<?php echo !empty($q['paper_signed']) ? '1' : '0'; ?>" data-amount="<?php echo (float)$q['amount']; ?>" data-row-url="<?php echo htmlspecialchars($qt_quote_view_base, ENT_QUOTES, 'UTF-8'); ?><?php echo (int)$q['quote_id']; ?>" style="cursor:pointer;">
                        <?php if ($is_admin_user): ?>
                        <td class="qt-col-check st-no-row-nav" onclick="event.stopPropagation();">
                            <input type="checkbox" class="qt-row-check" value="<?php echo (int) $q['quote_id']; ?>" aria-label="Select <?php echo htmlspecialchars($qtLabel, ENT_QUOTES, 'UTF-8'); ?>">
                        </td>
                        <?php endif; ?>
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
                        <td class="qt-col-job"><div class="qt-td-inner"><?php echo htmlspecialchars($q['job_card'] ?? '-'); ?></div></td>
                        <td class="qt-col-client"><div class="qt-td-inner"><span class="st-cell-primary"><?php echo htmlspecialchars($q['client_name'] ?? 'Walk-in'); ?></span></div></td>
                        <td class="qt-col-amount"><div class="qt-td-inner qt-td-inner--end"><?php echo formatMoney($q['amount']); ?></div></td>
                        <td class="qt-col-date"><div class="qt-td-inner"><?php echo qt_list_submitted_cell($q['date'] ?? null); ?></div></td>
                        <td class="qt-col-saved">
                            <div class="qt-td-inner">
                            <span class="qt-status <?php echo $q['status'] === 'approved' ? 'qt-status-approved' : ($q['status'] === 'rejected' ? 'qt-status-rejected' : ($q['status'] === 'pending' || $q['status'] === 'pending_manager' || $q['status'] === 'sent_back_admin' ? 'qt-status-pending' : 'qt-status-default')); ?>">
                                <?php echo $status_text; ?>
                            </span>
                            </div>
                        </td>
                        <td class="qt-col-paper" onclick="event.stopPropagation();">
                            <div class="qt-paper-cell">
                                <div class="qt-paper-line">
                                    <?php if (($q['status'] ?? '') === 'rejected'):
                                        $rejAt = trim((string) ($q['rejected_at'] ?? ''));
                                        $rejTitle = ($q['rejection_reason'] ?? '') !== '' ? $q['rejection_reason'] : ($q['rejected_by'] ?? '');
                                    ?>
                                    <span class="qt-status qt-status-rejected" title="<?php echo htmlspecialchars($rejTitle, ENT_QUOTES, 'UTF-8'); ?>">Declined</span>
                                    <?php if ($rejAt !== ''): ?>
                                    <span class="qt-paper-meta"><?php echo htmlspecialchars(qt_format_paper_signoff_date($rejAt), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php elseif (!empty($q['paper_signed'])): ?>
                                    <span class="qt-status qt-status-paper-signed" title="<?php echo htmlspecialchars($q['paper_signed_by'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">Signed</span>
                                    <?php if (!empty($q['paper_signed_at'])): ?>
                                    <span class="qt-paper-meta"><?php echo htmlspecialchars(qt_format_paper_signoff_date($q['paper_signed_at']), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="qt-status qt-status-paper-pending">Awaiting</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($is_admin_user): ?>
                                <div class="qt-paper-actions">
                                <?php if (empty($q['paper_signed'])): ?>
                                <button type="button" class="qt-paper-btn" data-paper-mark="<?php echo (int)$q['quote_id']; ?>" data-paper-label="QTN-<?php echo str_pad($q['quote_id'], 5, '0', STR_PAD_LEFT); ?>">Record</button>
                                <?php else: ?>
                                <button type="button" class="qt-paper-btn qt-paper-btn--signed" data-paper-edit="<?php echo (int)$q['quote_id']; ?>" data-paper-at="<?php echo htmlspecialchars($q['paper_signed_at']); ?>" data-paper-by="<?php echo htmlspecialchars($q['paper_signed_by']); ?>" data-paper-label="QTN-<?php echo str_pad($q['quote_id'], 5, '0', STR_PAD_LEFT); ?>">Edit</button>
                                <button type="button" class="qt-paper-btn" data-paper-clear="<?php echo (int)$q['quote_id']; ?>" data-paper-label="QTN-<?php echo str_pad($q['quote_id'], 5, '0', STR_PAD_LEFT); ?>">Clear</button>
                                <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="qt-col-client-resp">
                            <div class="qt-td-inner">
                            <?php if ($q['status'] === 'approved'): ?>
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
                        <?php if ($is_admin_user):
                            echo st_actions_cell([
                                'delete_attrs' => [
                                    'data-qt-delete-id' => (string) (int) $q['quote_id'],
                                    'data-qt-delete-label' => $qtLabel,
                                ],
                                'delete_label' => 'Delete ' . $qtLabel,
                            ]);
                        endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($quotations)): ?>
                    <tr class="qt-helper-row" id="qtEmptyRow">
                        <td colspan="<?php echo (int) $qt_table_colspan; ?>">
                            <div class="erp-empty-state">
                                <div class="erp-empty-icon"><i class="fas fa-file-invoice"></i></div>
                                <div class="erp-empty-title">No quotations yet</div>
                                <div class="erp-empty-text">Quotations are automatically created from job cards</div>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr class="qt-helper-row" id="qtNoMatchRow" style="display:none;">
                        <td colspan="<?php echo (int) $qt_table_colspan; ?>" id="qtNoMatchCell"><i class="fas fa-search"></i> <span id="qtNoMatchMessage">No matching quotations found</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php st_render_table_footer('quotationTable'); ?>
        </div>
    </div>
</div>
<?php ops_docs_files_section_close(); ?>
</div>
</div>

<?php
ops_docs_render_sidebar([
    'overview_title' => 'Pipeline',
    'donut_segments' => $qt_donut_segments,
    'donut_center' => number_format($qt_total_count),
    'donut_sub' => 'quotations',
    'stats_title' => 'Quick stats',
    'bars' => $qt_sidebar_bars,
    'recent_title' => 'Recent activity',
    'recent' => $qt_sidebar_recent,
]);
ops_docs_init_script();
?>


<!-- Paper sign-off modal -->
<div class="erp-modal-overlay" id="paperSignoffModal">
    <div class="erp-modal" style="max-width:440px;">
        <div class="erp-modal-header">
            <h3 class="erp-modal-title">Record paper sign-off</h3>
            <button type="button" class="erp-modal-close" onclick="closePaperModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="paperSignoffForm">
            <input type="hidden" name="paper_signoff_action" id="paperSignoffAction" value="mark_signed">
            <input type="hidden" name="quote_id" id="paperSignoffQuoteId" value="">
            <div class="erp-modal-body">
                <p id="paperSignoffLabel" style="margin:0 0 12px;color:var(--gray-600);font-weight:600;"></p>
                <p style="font-size:13px;color:var(--gray-500);margin:0 0 14px;">Enter the date and name from the manager&rsquo;s signed printout.</p>
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Sign-off date</label>
                <input type="date" name="paper_signed_at" id="paperSignedAt" class="erp-input" style="width:100%;margin-bottom:12px;" required>
                <label style="display:block;font-size:12px;font-weight:700;margin-bottom:4px;">Manager name (on paper)</label>
                <input type="text" name="paper_signed_by" id="paperSignedBy" class="erp-input" style="width:100%;" placeholder="Optional">
            </div>
            <div class="erp-modal-footer">
                <button type="button" class="erp-btn erp-btn-secondary" onclick="closePaperModal()">Cancel</button>
                <button type="submit" class="erp-btn erp-btn-primary">Save sign-off</button>
            </div>
        </form>
    </div>
</div>

<form method="POST" id="paperClearForm" style="display:none;">
    <input type="hidden" name="paper_signoff_action" value="clear">
    <input type="hidden" name="quote_id" id="paperClearQuoteId" value="">
</form>

<?php if ($is_admin_user): ?>
<!-- Delete modal — same blueprint as job_card.php -->
<div id="qtDeleteModal" class="aq-name-modal">
    <div class="aq-name-modal-card">
        <div class="aq-name-modal-title">Delete Quotation</div>
        <div class="aq-name-modal-sub" id="qtDeleteModalMessage">Move this quotation to the recycle bin? It will be removed from the active quotation list and can be restored later if needed.</div>
        <div class="aq-name-modal-actions">
            <button type="button" id="qtDeleteModalCancel" class="aq-s1-btn aq-s1-btn--gray">Cancel</button>
            <form method="POST" id="deleteQuotationForm" action="<?php echo htmlspecialchars($qt_list_url, ENT_QUOTES, 'UTF-8'); ?>">
                <div id="deleteIdsWrap"></div>
                <input type="hidden" name="delete_id" id="deleteId" value="">
                <button type="submit" class="aq-s1-btn aq-s1-btn--danger" id="deleteSubmitBtn"><i class="fas fa-trash"></i> Move to Recycle Bin</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const IS_MANAGER_USER = <?php echo $is_manager_user ? 'true' : 'false'; ?>;
const IS_ADMIN_USER = <?php echo $is_admin_user ? 'true' : 'false'; ?>;
const QT_VIEW_STORAGE_KEY = 'sv_auto_quotations_view_v1_u<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>';
const quotationTable = document.getElementById('quotationTable');
const searchInput = document.getElementById('searchInput');
const sortFilter = document.getElementById('sortFilter');
const noMatchRow = document.getElementById('qtNoMatchRow');
const emptyRow = document.getElementById('qtEmptyRow');
const statusTabs = Array.from(document.querySelectorAll('.qt-tab'));
const dashCards = Array.from(document.querySelectorAll('.qt-stat-clickable'));
let activePeriod = null;

function getSelectedFiltersInGroup(groupName) {
    const group = document.querySelector('.qt-tabs[data-qt-tab-group="' + groupName + '"]');
    if (!group) return ['all'];
    const selected = Array.from(group.querySelectorAll('.qt-tab.active'))
        .map(function(t) { return t.getAttribute('data-filter') || ''; })
        .filter(Boolean);
    if (!selected.length) return ['all'];
    return selected;
}

function isShowingAllQuotations() {
    return getPaperFilters().indexOf('all') !== -1 && getSystemFilters().indexOf('all') !== -1;
}

function setShowAllFilters() {
    setFiltersInGroup('paper', ['all']);
    setFiltersInGroup('system', ['all']);
}

function getPaperFilters() {
    return getSelectedFiltersInGroup('paper');
}

function getSystemFilters() {
    return getSelectedFiltersInGroup('system');
}

function setFiltersInGroup(groupName, filters) {
    const group = document.querySelector('.qt-tabs[data-qt-tab-group="' + groupName + '"]');
    if (!group) return;
    let list = (filters && filters.length) ? filters.slice() : ['all'];
    if (groupName === 'system' && list.indexOf('all') === -1 && list.length > 1) {
        list = [list[0]];
    }
    group.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
    if (list.indexOf('all') !== -1 || !list.length) {
        const allTab = group.querySelector('.qt-tab[data-filter="all"]');
        if (allTab) allTab.classList.add('active');
        return;
    }
    list.forEach(function(filter) {
        const tab = group.querySelector('.qt-tab[data-filter="' + filter + '"]');
        if (tab) tab.classList.add('active');
    });
}

function toggleFilterTab(tab) {
    const group = tab.closest('.qt-tabs');
    if (!group) return;
    const groupName = group.getAttribute('data-qt-tab-group');
    const filter = tab.getAttribute('data-filter') || '';
    if (groupName === 'paper' && filter === 'all') {
        setShowAllFilters();
        return;
    }
    if (groupName === 'system') {
        const wasActive = tab.classList.contains('active');
        group.querySelectorAll('.qt-tab').forEach(function(t) { t.classList.remove('active'); });
        if (!wasActive) {
            tab.classList.add('active');
        }
    } else {
        const allTab = group.querySelector('.qt-tab[data-filter="all"]');
        if (allTab) allTab.classList.remove('active');
        tab.classList.toggle('active');
        if (!group.querySelector('.qt-tab.active') && allTab) {
            allTab.classList.add('active');
        }
    }
}

let qtSaveViewTimer = null;
function scheduleSaveViewState() {
    clearTimeout(qtSaveViewTimer);
    qtSaveViewTimer = setTimeout(saveQtViewState, 250);
}

function saveQtViewState() {
    try {
        const dashCard = document.querySelector('.qt-stat-clickable.is-active');
        let dash = null;
        if (dashCard) {
            const period = dashCard.getAttribute('data-qt-period');
            const tab = dashCard.getAttribute('data-qt-tab');
            if (period) dash = 'period:' + period;
            else if (tab) dash = 'tab:' + tab;
        }
        localStorage.setItem(QT_VIEW_STORAGE_KEY, JSON.stringify({
            paperFilters: getPaperFilters(),
            systemFilters: getSystemFilters(),
            period: activePeriod || null,
            dash: dash,
            search: searchInput ? searchInput.value : '',
            sort: sortFilter ? sortFilter.value : 'newest',
            savedAt: Date.now()
        }));
    } catch (e) { /* ignore quota / private mode */ }
}

function restoreQtViewState() {
    try {
        const raw = localStorage.getItem(QT_VIEW_STORAGE_KEY);
        if (!raw) return false;
        const state = JSON.parse(raw);
        if (!state || typeof state !== 'object') return false;

        if (searchInput && typeof state.search === 'string') searchInput.value = state.search;
        if (sortFilter && state.sort) sortFilter.value = state.sort;
        activePeriod = state.period || null;

        clearDashCardActive();
        if (state.dash) {
            let card = null;
            if (state.dash.indexOf('period:') === 0) {
                card = document.querySelector('.qt-stat-clickable[data-qt-period="' + state.dash.slice(7) + '"]');
            } else if (state.dash.indexOf('tab:') === 0) {
                card = document.querySelector('.qt-stat-clickable[data-qt-tab="' + state.dash.slice(4) + '"]');
            }
            if (card) card.classList.add('is-active');
        }

        if (Array.isArray(state.paperFilters)) {
            setFiltersInGroup('paper', state.paperFilters);
        } else if (state.paperFilter) {
            setFiltersInGroup('paper', [state.paperFilter]);
        } else {
            setFiltersInGroup('paper', ['all']);
        }

        if (Array.isArray(state.systemFilters)) {
            const sysOnly = state.systemFilters.filter(function(f) { return f && f !== 'all'; });
            setFiltersInGroup('system', sysOnly.length ? [sysOnly[0]] : ['all']);
        } else if (state.systemFilter) {
            setFiltersInGroup('system', [state.systemFilter]);
        } else {
            setFiltersInGroup('system', ['all']);
        }

        applyFilters();
        return true;
    } catch (e) {
        return false;
    }
}

function applyRoleDefaultView() {
    if (IS_MANAGER_USER) {
        setFiltersInGroup('paper', ['all']);
        setFiltersInGroup('system', ['pending_group']);
    } else if (IS_ADMIN_USER) {
        setFiltersInGroup('paper', ['paper_pending']);
        setFiltersInGroup('system', ['pending_group']);
        const card = document.querySelector('.qt-stat-clickable[data-qt-tab="paper_pending"]');
        if (card) card.classList.add('is-active');
    } else {
        setShowAllFilters();
    }
    applyFilters();
}

function closePaperModal() {
    const m = document.getElementById('paperSignoffModal');
    if (m) m.classList.remove('show');
}

function openPaperModal(opts) {
    const m = document.getElementById('paperSignoffModal');
    if (!m) return;
    document.getElementById('paperSignoffQuoteId').value = opts.id || '';
    document.getElementById('paperSignoffLabel').textContent = opts.label || '';
    document.getElementById('paperSignedAt').value = opts.at || new Date().toISOString().slice(0, 10);
    document.getElementById('paperSignedBy').value = opts.by || '';
    m.classList.add('show');
}

document.addEventListener('click', function(e) {
    const mark = e.target.closest('[data-paper-mark]');
    if (mark) {
        e.stopPropagation();
        openPaperModal({ id: mark.getAttribute('data-paper-mark'), label: mark.getAttribute('data-paper-label') });
        return;
    }
    const edit = e.target.closest('[data-paper-edit]');
    if (edit) {
        e.stopPropagation();
        openPaperModal({
            id: edit.getAttribute('data-paper-edit'),
            label: edit.getAttribute('data-paper-label'),
            at: (edit.getAttribute('data-paper-at') || '').slice(0, 10),
            by: edit.getAttribute('data-paper-by') || ''
        });
        return;
    }
    const clearBtn = e.target.closest('[data-paper-clear]');
    if (clearBtn) {
        e.stopPropagation();
        const label = clearBtn.getAttribute('data-paper-label') || 'this quotation';
        if (!confirm('Clear paper sign-off for ' + label + '?')) return;
        document.getElementById('paperClearQuoteId').value = clearBtn.getAttribute('data-paper-clear');
        document.getElementById('paperClearForm').submit();
    }
});

const paperModal = document.getElementById('paperSignoffModal');
if (paperModal) {
    paperModal.addEventListener('click', function(e) {
        if (e.target === paperModal) closePaperModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && paperModal.classList.contains('show')) closePaperModal();
    });
}

function qtRowFilterCtx(row) {
    return {
        status: (row.getAttribute('data-status') || '').toLowerCase(),
        clientStatus: row.getAttribute('data-client-status') || '',
        paperSigned: (row.getAttribute('data-paper-signed') || '') === '1',
        formStatus: (row.getAttribute('data-form-status') || '').toLowerCase()
    };
}

function qtIsPendingManagerStatus(status) {
    return ['pending', 'pending_manager', 'sent_back_admin'].indexOf(status) !== -1;
}

function paperFilterMatches(row, paperFilters) {
    if (!paperFilters || paperFilters.indexOf('all') !== -1) return true;
    const ctx = qtRowFilterCtx(row);
    return paperFilters.some(function(paperFilter) {
        if (ctx.status === 'rejected') return false;
        if (paperFilter === 'paper_pending') {
            return !ctx.paperSigned && qtIsPendingManagerStatus(ctx.status);
        }
        if (paperFilter === 'paper_signed') {
            return ctx.paperSigned && ctx.status === 'approved';
        }
        if (paperFilter === 'awaiting_client') {
            return ctx.status === 'approved'
                && ctx.paperSigned
                && ctx.clientStatus === ''
                && ctx.formStatus !== 'sent_to_client';
        }
        return false;
    });
}

function systemFilterMatches(row, systemFilters) {
    if (!systemFilters || systemFilters.indexOf('all') !== -1) return true;
    const ctx = qtRowFilterCtx(row);
    return systemFilters.some(function(systemFilter) {
        if (systemFilter === 'pending_group') {
            return qtIsPendingManagerStatus(ctx.status);
        }
        if (systemFilter === 'approved') return ctx.status === 'approved';
        if (systemFilter === 'rejected') return ctx.status === 'rejected';
        return false;
    });
}

function getFilterConflictMessage(paperFilters, systemFilters) {
    if (!paperFilters || !systemFilters) return '';
    if (paperFilters.indexOf('all') !== -1 || systemFilters.indexOf('all') !== -1) return '';
    const paperPending = paperFilters.indexOf('paper_pending') !== -1;
    const paperSigned = paperFilters.indexOf('paper_signed') !== -1;
    const awaitingClient = paperFilters.indexOf('awaiting_client') !== -1;
    const sysPending = systemFilters.indexOf('pending_group') !== -1;
    const sysApproved = systemFilters.indexOf('approved') !== -1;
    const sysRejected = systemFilters.indexOf('rejected') !== -1;
    if (paperPending && sysApproved) {
        return 'No quotation can be “awaiting manager signature” on paper and “already approved” in the system. Turn off one filter, or click a card above.';
    }
    if (paperSigned && sysPending) {
        return 'No quotation can be “manager signed on paper” and still “awaiting manager decision” in the system. Turn off one filter, or click a card above.';
    }
    if (awaitingClient && sysPending) {
        return '“Awaiting client response” only applies after manager approval. Clear “awaiting manager decision” or use the Awaiting client response card.';
    }
    if ((paperPending || paperSigned || awaitingClient) && sysRejected) {
        return 'Paper workflow filters do not apply to quotations declined on paper. Use the Manager declined card instead.';
    }
    return '';
}

function getListTitle() {
    const parts = [];
    const paperGroup = document.querySelector('.qt-tabs[data-qt-tab-group="paper"]');
    const systemGroup = document.querySelector('.qt-tabs[data-qt-tab-group="system"]');
    if (paperGroup) {
        Array.from(paperGroup.querySelectorAll('.qt-tab.active')).forEach(function(tab) {
            parts.push(tab.getAttribute('data-title') || 'Paper');
        });
    }
    if (systemGroup) {
        Array.from(systemGroup.querySelectorAll('.qt-tab.active')).forEach(function(tab) {
            parts.push(tab.getAttribute('data-title') || 'Status');
        });
    }
    if (activePeriod) {
        const periodLabels = { today: 'Submitted today', week: 'Submitted this week', month: 'Submitted this month', year: 'Submitted this year' };
        parts.push(periodLabels[activePeriod] || activePeriod);
    }
    if (parts.length) {
        return parts.join(' · ');
    }
    if (isShowingAllQuotations() && !activePeriod) {
        return 'All quotations';
    }
    const activeCard = document.querySelector('.qt-stat-clickable.is-active');
    if (activeCard) {
        return activeCard.getAttribute('data-qt-list-title') || 'Quotations';
    }
    return 'Quotations';
}

function updateTableHeading(visibleCount) {
    const titleEl = document.getElementById('qtTableTitle');
    const metaEl = document.getElementById('qtTableMeta');
    if (titleEl) titleEl.textContent = getListTitle();
    if (metaEl) {
        metaEl.textContent = visibleCount === 1 ? '1 Quotation' : (visibleCount + ' Quotations');
    }
}

function clearDashCardActive() {
    document.querySelectorAll('.qt-stat-clickable.is-active').forEach(function(c) {
        c.classList.remove('is-active');
    });
}

function syncDashCardWithPaperTab(tab) {
    clearDashCardActive();
    if (activePeriod) {
        const periodCard = document.querySelector('.qt-stat-clickable[data-qt-period="' + activePeriod + '"]');
        if (periodCard) periodCard.classList.add('is-active');
        return;
    }
    if (!tab) return;
    const filter = tab.getAttribute('data-filter') || '';
    if (!filter) return;
    const card = document.querySelector('.qt-stat-clickable[data-qt-tab="' + filter + '"]');
    if (card) card.classList.add('is-active');
}

function onFindTabClick(tab) {
    const group = tab.closest('.qt-tabs');
    if (!group) return;
    const groupName = group.getAttribute('data-qt-tab-group');
    const filter = tab.getAttribute('data-filter') || '';
    toggleFilterTab(tab);
    activePeriod = null;
    clearDashCardActive();
    if (groupName === 'paper' && filter !== 'all') {
        const paperTab = group.querySelector('.qt-tab.active');
        if (paperTab) syncDashCardWithPaperTab(paperTab);
    }
    applyFilters();
}

function scrollToQuotationsList() {
    const el = document.getElementById('qtQuotationsList');
    if (!el) return;
    requestAnimationFrame(function() {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function activateFromDashboardCard(card) {
    if (!card) return;
    clearDashCardActive();
    card.classList.add('is-active');
    activePeriod = card.getAttribute('data-qt-period') || null;
    const tabFilter = card.getAttribute('data-qt-tab');
    const systemTabs = card.getAttribute('data-qt-system-tabs');
    if (tabFilter === 'all') {
        setShowAllFilters();
    } else {
        if (tabFilter) {
            setFiltersInGroup('paper', [tabFilter]);
        } else {
            setFiltersInGroup('paper', ['all']);
        }
        if (systemTabs) {
            setFiltersInGroup('system', systemTabs.split(',').map(function(s) { return s.trim(); }).filter(Boolean));
        } else {
            setFiltersInGroup('system', ['all']);
        }
    }
    applyFilters();
    scrollToQuotationsList();
}

function periodMatches(row) {
    if (!activePeriod) return true;
    return (row.getAttribute('data-in-' + activePeriod) || '') === '1';
}

function sortQuotationRows(rows, sortValue, tbody) {
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

function applyFilters() {
    if (!quotationTable) return;
    const tbody = quotationTable.querySelector('tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr[data-quote-row="1"]'));
    const query = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
    const paperFilters = getPaperFilters();
    const systemFilters = getSystemFilters();
    const sortBy = sortFilter ? sortFilter.value : 'newest';

    sortQuotationRows(rows, sortBy, tbody);

    const conflictMsg = getFilterConflictMessage(paperFilters, systemFilters);
    let visibleCount = 0;
    rows.forEach(function(row) {
        const text = row.textContent.toLowerCase();
        const show = !conflictMsg
            && text.includes(query)
            && paperFilterMatches(row, paperFilters)
            && systemFilterMatches(row, systemFilters)
            && periodMatches(row);
        row.classList.remove('qt-search-hit');
        row.style.display = show ? '' : 'none';
        if (show) {
            visibleCount++;
            if (query) row.classList.add('qt-search-hit');
        }
    });

    if (noMatchRow) {
        const noMatchMessage = document.getElementById('qtNoMatchMessage');
        if (noMatchMessage) {
            noMatchMessage.textContent = conflictMsg || 'No matching quotations found';
        }
        noMatchRow.style.display = (!emptyRow && visibleCount === 0) ? '' : 'none';
    }
    updateTableHeading(visibleCount);
    if (IS_ADMIN_USER) syncSelectAllState();
    scheduleSaveViewState();
    if (window.ErpStudentTable) {
        ErpStudentTable.sync('quotationTable');
    }
}

// Row selection & delete (admin)
const qtSelectAll = document.getElementById('qtSelectAll');
const qtSelectAllHead = document.getElementById('qtSelectAllHead');
const qtSelectionMeta = document.getElementById('qtSelectionMeta');
const qtDeleteSelected = document.getElementById('qtDeleteSelected');
function getVisibleDataRows() {
    if (!quotationTable) return [];
    const tbody = quotationTable.querySelector('tbody');
    if (!tbody) return [];
    return Array.from(tbody.querySelectorAll('tr[data-quote-row="1"]')).filter(function(row) {
        return row.style.display !== 'none';
    });
}

function getSelectedRows() {
    return getVisibleDataRows().filter(function(row) {
        const cb = row.querySelector('.qt-row-check');
        return cb && cb.checked;
    });
}

function syncSelectAllState() {
    const visible = getVisibleDataRows();
    const selected = getSelectedRows();
    const allChecked = visible.length > 0 && selected.length === visible.length;
    if (qtSelectAll) qtSelectAll.checked = allChecked;
    if (qtSelectAllHead) qtSelectAllHead.checked = allChecked;
    if (qtSelectionMeta) {
        qtSelectionMeta.textContent = selected.length === 1
            ? '1 selected'
            : (selected.length + ' selected');
    }
    if (qtDeleteSelected) qtDeleteSelected.disabled = selected.length === 0;
    visible.forEach(function(row) {
        const cb = row.querySelector('.qt-row-check');
        row.classList.toggle('qt-row-selected', !!(cb && cb.checked));
    });
}

function setSelectAllVisible(checked) {
    getVisibleDataRows().forEach(function(row) {
        const cb = row.querySelector('.qt-row-check');
        if (cb) cb.checked = checked;
    });
    syncSelectAllState();
}

(function initQtDelete() {
    const modal = document.getElementById('qtDeleteModal');
    const deleteIdInput = document.getElementById('deleteId');
    const deleteIdsWrap = document.getElementById('deleteIdsWrap');
    const cancelBtn = document.getElementById('qtDeleteModalCancel');
    const submitBtn = document.getElementById('deleteSubmitBtn');
    const messageEl = document.getElementById('qtDeleteModalMessage');
    if (!modal || !deleteIdInput) return;

    const defaultMessage = 'Move this quotation to the recycle bin? It will be removed from the active quotation list and can be restored later if needed.';

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
                const quoteRef = (labelList[0] || '').trim();
                messageEl.textContent = quoteRef
                    ? ('Move quotation ' + quoteRef + ' to the recycle bin? It will be removed from the active quotation list and can be restored later if needed.')
                    : defaultMessage;
            } else {
                messageEl.textContent = 'Move ' + idList.length + ' quotations to the recycle bin? They will be removed from the active list and can be restored later if needed.';
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

    window.qtOpenDeleteModal = openDeleteModal;

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-qt-delete-id]');
        if (!btn || !quotationTable || !quotationTable.contains(btn)) return;
        e.preventDefault();
        e.stopPropagation();
        openDeleteModal(
            [Number(btn.getAttribute('data-qt-delete-id'))],
            [btn.getAttribute('data-qt-delete-label') || '']
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

if (qtSelectAll) {
    qtSelectAll.addEventListener('change', function() {
        setSelectAllVisible(qtSelectAll.checked);
    });
}
if (qtSelectAllHead) {
    qtSelectAllHead.addEventListener('change', function() {
        setSelectAllVisible(qtSelectAllHead.checked);
        if (qtSelectAll) qtSelectAll.checked = qtSelectAllHead.checked;
    });
}
if (qtDeleteSelected) {
    qtDeleteSelected.addEventListener('click', function() {
        const rows = getSelectedRows();
        if (!rows.length) return;
        const ids = rows.map(function(row) { return Number(row.getAttribute('data-quote-id')); });
        const labels = rows.map(function(row) {
            return row.getAttribute('data-quote-label') || '';
        });
        if (window.qtOpenDeleteModal) window.qtOpenDeleteModal(ids, labels);
    });
}

if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
}
if (sortFilter) sortFilter.addEventListener('change', applyFilters);
statusTabs.forEach(function(tab){
    tab.addEventListener('click', function(){
        onFindTabClick(tab);
    });
});
dashCards.forEach(function(card){
    card.addEventListener('click', function(){
        activateFromDashboardCard(card);
    });
    card.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            activateFromDashboardCard(card);
        }
    });
});
if (!restoreQtViewState()) {
    applyRoleDefaultView();
}

function qtResetAllFilters() {
    setShowAllFilters();
    activePeriod = null;
    document.querySelectorAll('.qt-stat-clickable.is-active').forEach(function(c) { c.classList.remove('is-active'); });
    if (searchInput) searchInput.value = '';
    applyFilters();
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.opsDocsSyncListMeta === 'function') window.opsDocsSyncListMeta();
    if (typeof window.opsDocsWireViewAll === 'function') {
        window.opsDocsWireViewAll('qtFoldersViewAll', qtResetAllFilters);
        window.opsDocsWireViewAll('qtFilesViewAll', qtResetAllFilters);
    }
});

if (quotationTable) {
    quotationTable.addEventListener('click', function(e) {
        if (e.target.closest('.st-col-actions, .qt-col-actions, .st-act-btn, .st-no-row-nav, .qt-col-check, .st-col-check, a, button, input, label')) return;
        const row = e.target.closest('tr[data-quote-row="1"]');
        if (!row) return;
        const url = row.getAttribute('data-row-url');
        if (url) window.location.href = url;
    });
    quotationTable.addEventListener('change', function(e) {
        if (e.target.classList.contains('qt-row-check')) {
            syncSelectAllState();
        }
    });
}

document.addEventListener('erp-student-tables-ready', function () {
    if (typeof applyFilters === 'function') applyFilters();
});

window.addEventListener('load', function () {
    const wrap = document.getElementById('qtFlashWrap');
    if (!wrap) return;
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    setTimeout(function () {
        wrap.classList.remove('show');
        setTimeout(function () { wrap.remove(); }, 220);
    }, 3200);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
