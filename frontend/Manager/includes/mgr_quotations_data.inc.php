<?php
/**
 * Manager quotations list — data load (mirrors Admin quotations overview rules).
 */
declare(strict_types=1);

require_once __DIR__ . '/mgr_review.inc.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_paper_signoff.inc.php';

if (!function_exists('mgr_qt_list_submitted_cell')) {
    function mgr_qt_list_submitted_cell(?string $raw): string
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
}

if (!function_exists('mgr_qt_submitted_in_period')) {
    function mgr_qt_submitted_in_period(?string $raw, string $period): bool
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
}

$currency = getCurrency();
$listError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';

$summary = [];
try {
    $summary = $pdo->query("SELECT 
        COUNT(*) AS total_count,
        COALESCE(SUM(amount), 0) AS total_quoted,
        COUNT(CASE WHEN DATE(COALESCE(submitted_at, created_at)) = CURDATE() THEN 1 END) AS today_count,
        COALESCE(SUM(CASE WHEN DATE(COALESCE(submitted_at, created_at)) = CURDATE() THEN amount ELSE 0 END), 0) AS today_amount,
        COUNT(CASE WHEN YEARWEEK(DATE(COALESCE(submitted_at, created_at)), 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS week_count,
        COALESCE(SUM(CASE WHEN YEARWEEK(DATE(COALESCE(submitted_at, created_at)), 1) = YEARWEEK(CURDATE(), 1) THEN amount ELSE 0 END), 0) AS week_amount,
        COUNT(CASE WHEN YEAR(COALESCE(submitted_at, created_at)) = YEAR(CURDATE())
            AND MONTH(COALESCE(submitted_at, created_at)) = MONTH(CURDATE()) THEN 1 END) AS month_count,
        COALESCE(SUM(CASE WHEN YEAR(COALESCE(submitted_at, created_at)) = YEAR(CURDATE())
            AND MONTH(COALESCE(submitted_at, created_at)) = MONTH(CURDATE()) THEN amount ELSE 0 END), 0) AS month_amount
    FROM quotations WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('mgr_quotations summary: ' . $e->getMessage());
    $summary = [];
}

$quotations = [];
try {
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
    LEFT JOIN clients c ON c.id = q.client_id
    WHERE q.deleted_at IS NULL
    ORDER BY q.submitted_at DESC, q.id DESC");
    $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('mgr_quotations list: ' . $e->getMessage());
    $quotations = [];
}

foreach ($quotations as &$qRow) {
    $payload = qt_parse_quotation_details((string) ($qRow['details'] ?? ''));
    $form = (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) ? $payload['form'] : [];
    $paper = qt_paper_signoff_info($form);
    $rejection = qt_paper_rejection_info($form);
    $review = mgr_quotation_review_row_meta((string) ($qRow['details'] ?? ''));
    $qRow['paper_signed'] = $paper['signed'];
    $qRow['paper_signed_at'] = $paper['at'];
    $qRow['paper_signed_by'] = $paper['by'];
    $qRow['form_status'] = strtolower(trim((string) ($form['status'] ?? '')));
    $qRow['rejected_at'] = $rejection['at'];
    $qRow['rejected_by'] = $rejection['by'];
    $qRow['rejection_reason'] = $rejection['reason'];
    $qRow['review_sent'] = $review['sent'];
    $qRow['review_viewed'] = $review['viewed'];
    $qRow['workflow'] = qt_workflow_from_quotation_row([
        'details' => $qRow['details'],
        'status' => $qRow['status'] ?? 'draft',
        'client_status' => $qRow['client_status'] ?? '',
    ]);
    unset($qRow['details']);
}
unset($qRow);

$mgr_qt_period_counter = static function (?string $raw, string $period): bool {
    return mgr_qt_submitted_in_period($raw, $period);
};

$mgr_qt_new_review_count = mgr_count_quotations_for_review($pdo, true);

$mgr_qt_dashboard_cards = [
    [
        'tone' => 'orange',
        'label' => 'New for review',
        'value' => (string) number_format($mgr_qt_new_review_count),
        'sub' => 'Admin sent · open to preview',
        'icon' => 'fa-inbox',
        'tab' => null,
        'system_tabs' => 'all',
        'digital' => 'new_review',
        'period' => null,
        'list_title' => 'New for your review',
    ],
    [
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
        'tone' => 'slate',
        'label' => 'Today',
        'value' => '0',
        'sub' => date('d M Y') . ' · ' . formatMoney((float) ($summary['today_amount'] ?? 0)),
        'icon' => 'fa-calendar-day',
        'tab' => null,
        'period' => 'today',
        'list_title' => 'Submitted today',
    ],
    [
        'tone' => 'purple',
        'label' => 'This week',
        'value' => '0',
        'sub' => 'Mon – Sun · ' . formatMoney((float) ($summary['week_amount'] ?? 0)),
        'icon' => 'fa-calendar-week',
        'tab' => null,
        'period' => 'week',
        'list_title' => 'Submitted this week',
    ],
    [
        'tone' => 'cyan',
        'label' => 'This month',
        'value' => '0',
        'sub' => date('F Y') . ' · ' . formatMoney((float) ($summary['month_amount'] ?? 0)),
        'icon' => 'fa-calendar-alt',
        'tab' => null,
        'period' => 'month',
        'list_title' => 'Submitted this month',
    ],
    [
        'tone' => 'orange',
        'label' => 'All quotations',
        'value' => '0',
        'sub' => formatMoney((float) ($summary['total_quoted'] ?? 0)) . ' quoted',
        'icon' => 'fa-file-invoice',
        'tab' => 'all',
        'system_tabs' => 'all',
        'period' => null,
        'list_title' => 'All quotations',
    ],
];

foreach ($mgr_qt_dashboard_cards as &$mgrQtCard) {
    if (($mgrQtCard['digital'] ?? '') === 'new_review') {
        $mgrQtCard['value'] = number_format($mgr_qt_new_review_count);
        continue;
    }
    $mgrQtCard['value'] = number_format(qt_count_dashboard_card($quotations, $mgrQtCard, $mgr_qt_period_counter));
}
unset($mgrQtCard);

$mgr_qt_view_base = mgr_nav_href('Quotation/view_quotation.php?id=');
$mgr_qt_table_colspan = 11;

$mgr_qt_paper_pending = 0;
$mgr_qt_paper_signed = 0;
$mgr_qt_awaiting_client = 0;
$mgr_qt_rejected = 0;
foreach ($quotations as $qRow) {
    $ctx = [
        'status' => strtolower(trim((string) ($qRow['status'] ?? ''))),
        'client_status' => trim((string) ($qRow['client_status'] ?? '')),
        'paper_signed' => !empty($qRow['paper_signed']),
        'form_status' => strtolower(trim((string) ($qRow['form_status'] ?? ''))),
    ];
    if (qt_matches_paper_filter($ctx, 'paper_pending')) {
        $mgr_qt_paper_pending++;
    }
    if (qt_matches_paper_filter($ctx, 'paper_signed')) {
        $mgr_qt_paper_signed++;
    }
    if (qt_matches_paper_filter($ctx, 'awaiting_client')) {
        $mgr_qt_awaiting_client++;
    }
    if (($qRow['status'] ?? '') === 'rejected') {
        $mgr_qt_rejected++;
    }
}
$mgr_qt_total = count($quotations);

require_once __DIR__ . '/mgr_ops_docs_helpers.inc.php';
$mgr_qt_folders = mgr_dashboard_cards_to_folders($mgr_qt_dashboard_cards, 'qt-stat-clickable', 'quotes');
$mgr_qt_donut_segments = [
    ['label' => 'Awaiting sign-off', 'value' => (string) number_format($mgr_qt_paper_pending), 'pct' => $mgr_qt_paper_pending, 'color' => '#ea580c'],
    ['label' => 'Signed', 'value' => (string) number_format($mgr_qt_paper_signed), 'pct' => $mgr_qt_paper_signed, 'color' => '#059669'],
    ['label' => 'Client pending', 'value' => (string) number_format($mgr_qt_awaiting_client), 'pct' => $mgr_qt_awaiting_client, 'color' => '#2563eb'],
    ['label' => 'Declined', 'value' => (string) number_format($mgr_qt_rejected), 'pct' => $mgr_qt_rejected, 'color' => '#dc2626'],
];
$mgr_qt_sidebar_bars = ops_docs_pct_bars([
    ['label' => 'New for review', 'count' => $mgr_qt_new_review_count, 'tone' => 'amber', 'icon' => 'fa-inbox'],
    ['label' => 'Awaiting sign-off', 'count' => $mgr_qt_paper_pending, 'tone' => 'orange', 'icon' => 'fa-pen'],
    ['label' => 'Client pending', 'count' => $mgr_qt_awaiting_client, 'tone' => 'blue', 'icon' => 'fa-user-clock'],
], max(1, $mgr_qt_total));
$mgr_qt_sidebar_recent = mgr_build_ops_sidebar_recent($pdo, 6);
