<?php
declare(strict_types=1);

require_once __DIR__ . '/mgr_review.inc.php';

mgr_ensure_invoice_review_columns($pdo);

if (!function_exists('mgr_inv_list_date_cell')) {
    function mgr_inv_list_date_cell(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '-';
        }
        $date = date('d M Y', $ts);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($raw))) {
            return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
        }
        return htmlspecialchars($date, ENT_QUOTES, 'UTF-8')
            . ' <span class="qt-list-time">' . htmlspecialchars(date('H:i', $ts), ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('mgr_inv_in_period')) {
    function mgr_inv_in_period(?string $raw, string $period): bool
    {
        if ($raw === null || trim($raw) === '') {
            return false;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return false;
        }
        $ymd = date('Y-m-d', $ts);
        switch ($period) {
            case 'today':
                return $ymd === date('Y-m-d');
            case 'week':
                $dt = new DateTime($ymd);
                $now = new DateTime(date('Y-m-d'));
                return $dt->format('oW') === $now->format('oW');
            case 'month':
                return date('Y-m', $ts) === date('Y-m');
            default:
                return true;
        }
    }
}

if (!function_exists('mgr_inv_normalize_paid')) {
    function mgr_inv_normalize_paid(string $status): string
    {
        $s = strtolower(trim($status));
        if (in_array($s, ['paid', 'partial', 'unpaid'], true)) {
            return $s;
        }
        return 'unpaid';
    }
}

if (!function_exists('mgr_inv_paid_label')) {
    function mgr_inv_paid_label(string $status): string
    {
        $s = mgr_inv_normalize_paid($status);
        if ($s === 'paid') {
            return 'Paid';
        }
        if ($s === 'partial') {
            return 'Partial';
        }
        return 'Unpaid';
    }
}

if (!function_exists('mgr_inv_progress_dots')) {
    /** @return list<array{done: bool, current: bool}> */
    function mgr_inv_progress_dots(bool $sent, bool $viewed, string $paidStatus): array
    {
        $paid = mgr_inv_normalize_paid($paidStatus) === 'paid';
        $step = 0;
        if ($sent) {
            $step = 1;
        }
        if ($sent && $viewed) {
            $step = 2;
        }
        if ($paid) {
            $step = 3;
        }
        $dots = [];
        for ($i = 0; $i < 4; $i++) {
            $dots[] = [
                'done' => $step > $i || ($step === 3 && $i === 3),
                'current' => $step === $i && $step < 3,
            ];
        }
        return $dots;
    }
}

$listError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';

$summary = [
    'total_count' => 0,
    'total_amount' => 0,
    'today_count' => 0,
    'today_amount' => 0,
    'week_count' => 0,
    'week_amount' => 0,
    'month_count' => 0,
    'month_amount' => 0,
];
try {
    $summary = $pdo->query("
        SELECT COUNT(*) AS total_count,
            COALESCE(SUM(amount), 0) AS total_amount,
            COUNT(CASE WHEN DATE(COALESCE(issued_date, manager_review_sent_at, created_at)) = CURDATE() THEN 1 END) AS today_count,
            COALESCE(SUM(CASE WHEN DATE(COALESCE(issued_date, manager_review_sent_at, created_at)) = CURDATE() THEN amount ELSE 0 END), 0) AS today_amount,
            COUNT(CASE WHEN YEARWEEK(DATE(COALESCE(issued_date, manager_review_sent_at, created_at)), 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS week_count,
            COALESCE(SUM(CASE WHEN YEARWEEK(DATE(COALESCE(issued_date, manager_review_sent_at, created_at)), 1) = YEARWEEK(CURDATE(), 1) THEN amount ELSE 0 END), 0) AS week_amount,
            COUNT(CASE WHEN YEAR(COALESCE(issued_date, manager_review_sent_at, created_at)) = YEAR(CURDATE())
                AND MONTH(COALESCE(issued_date, manager_review_sent_at, created_at)) = MONTH(CURDATE()) THEN 1 END) AS month_count,
            COALESCE(SUM(CASE WHEN YEAR(COALESCE(issued_date, manager_review_sent_at, created_at)) = YEAR(CURDATE())
                AND MONTH(COALESCE(issued_date, manager_review_sent_at, created_at)) = MONTH(CURDATE()) THEN amount ELSE 0 END), 0) AS month_amount
        FROM invoices
        WHERE deleted_at IS NULL OR deleted_at = ''
    ")->fetch(PDO::FETCH_ASSOC) ?: $summary;
} catch (Throwable $e) {
    error_log('mgr_invoices summary: ' . $e->getMessage());
}

$invoices = [];
try {
    $stmt = $pdo->query("
        SELECT i.id, i.invoice_number, i.amount, i.status_paid, i.issued_date,
               i.manager_review_sent_at, i.manager_review_viewed_at,
               COALESCE(c_q.name, 'Walk-in') AS client_name
        FROM invoices i
        LEFT JOIN quotations q ON q.id = i.quotation_id
        LEFT JOIN clients c_q ON c_q.id = q.client_id
        WHERE i.deleted_at IS NULL OR i.deleted_at = ''
        ORDER BY COALESCE(i.manager_review_sent_at, i.issued_date, i.id) DESC, i.id DESC
        LIMIT 500
    ");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('mgr_invoices list: ' . $e->getMessage());
}

$mgr_inv_new_review = 0;
$mgr_inv_viewed = 0;
$mgr_inv_awaiting_send = 0;
$mgr_inv_unpaid = 0;
$mgr_inv_paid = 0;

foreach ($invoices as &$inv) {
    $sent = trim((string) ($inv['manager_review_sent_at'] ?? '')) !== '';
    $viewed = trim((string) ($inv['manager_review_viewed_at'] ?? '')) !== '';
    $inv['review_sent'] = $sent;
    $inv['review_viewed'] = $viewed;
    $paid = mgr_inv_normalize_paid((string) ($inv['status_paid'] ?? ''));
    $inv['paid_norm'] = $paid;

    if ($sent && !$viewed) {
        $mgr_inv_new_review++;
    } elseif ($sent && $viewed) {
        $mgr_inv_viewed++;
    } else {
        $mgr_inv_awaiting_send++;
    }
    if ($paid === 'paid') {
        $mgr_inv_paid++;
    } elseif ($paid !== 'paid') {
        $mgr_inv_unpaid++;
    }
}
unset($inv);

$mgr_inv_dashboard_cards = [
    [
        'tone' => 'amber',
        'label' => 'New for your review',
        'value' => (string) $mgr_inv_new_review,
        'sub' => 'Sent by admin · not previewed yet',
        'icon' => 'fa-inbox',
        'digital' => 'new_review',
        'payment' => null,
        'period' => null,
        'list_title' => 'New for your review',
    ],
    [
        'tone' => 'green',
        'label' => 'Previewed',
        'value' => (string) $mgr_inv_viewed,
        'sub' => 'You opened the invoice preview',
        'icon' => 'fa-eye',
        'digital' => 'viewed',
        'payment' => null,
        'period' => null,
        'list_title' => 'Already previewed',
    ],
    [
        'tone' => 'blue',
        'label' => 'Awaiting send',
        'value' => (string) $mgr_inv_awaiting_send,
        'sub' => 'Admin has not sent for review yet',
        'icon' => 'fa-paper-plane',
        'digital' => 'not_sent',
        'payment' => null,
        'period' => null,
        'list_title' => 'Awaiting send',
    ],
    [
        'tone' => 'red',
        'label' => 'Outstanding',
        'value' => (string) $mgr_inv_unpaid,
        'sub' => 'Unpaid or partial balance',
        'icon' => 'fa-exclamation-circle',
        'digital' => null,
        'payment' => 'unpaid_group',
        'period' => null,
        'list_title' => 'Outstanding invoices',
    ],
    [
        'tone' => 'slate',
        'label' => 'Today',
        'value' => (string) ((int) ($summary['today_count'] ?? 0)),
        'sub' => date('d M Y') . ' · ' . formatMoney((float) ($summary['today_amount'] ?? 0)),
        'icon' => 'fa-calendar-day',
        'digital' => null,
        'payment' => null,
        'period' => 'today',
        'list_title' => 'Issued today',
    ],
    [
        'tone' => 'purple',
        'label' => 'This week',
        'value' => (string) ((int) ($summary['week_count'] ?? 0)),
        'sub' => 'Mon – Sun · ' . formatMoney((float) ($summary['week_amount'] ?? 0)),
        'icon' => 'fa-calendar-week',
        'digital' => null,
        'payment' => null,
        'period' => 'week',
        'list_title' => 'Issued this week',
    ],
    [
        'tone' => 'cyan',
        'label' => 'This month',
        'value' => (string) ((int) ($summary['month_count'] ?? 0)),
        'sub' => date('F Y') . ' · ' . formatMoney((float) ($summary['month_amount'] ?? 0)),
        'icon' => 'fa-calendar-alt',
        'digital' => null,
        'payment' => null,
        'period' => 'month',
        'list_title' => 'Issued this month',
    ],
    [
        'tone' => 'orange',
        'label' => 'All invoices',
        'value' => (string) ((int) ($summary['total_count'] ?? 0)),
        'sub' => formatMoney((float) ($summary['total_amount'] ?? 0)) . ' total',
        'icon' => 'fa-file-invoice-dollar',
        'digital' => 'all',
        'payment' => 'all',
        'period' => null,
        'list_title' => 'All invoices',
    ],
];

$mgr_inv_view_base = mgr_nav_href('Invoice/view_invoice.php?id=');
$mgr_inv_table_colspan = 8;
$mgr_inv_new_review_count = $mgr_inv_new_review;

$mgr_inv_total = (int) ($summary['total_count'] ?? count($invoices));
$mgr_inv_paid_count = $mgr_inv_paid;
$mgr_inv_unpaid_count = 0;
$mgr_inv_partial_count = 0;
foreach ($invoices as $invRow) {
    $paidNorm = (string) ($invRow['paid_norm'] ?? 'unpaid');
    if ($paidNorm === 'partial') {
        $mgr_inv_partial_count++;
    } elseif ($paidNorm !== 'paid') {
        $mgr_inv_unpaid_count++;
    }
}

require_once __DIR__ . '/mgr_ops_docs_helpers.inc.php';
$mgr_inv_folders = mgr_dashboard_cards_to_folders($mgr_inv_dashboard_cards, 'mgr-stat-clickable', 'invoices');
$mgr_inv_donut_segments = [
    ['label' => 'Paid', 'value' => (string) number_format($mgr_inv_paid_count), 'pct' => $mgr_inv_paid_count, 'color' => '#059669'],
    ['label' => 'Unpaid', 'value' => (string) number_format($mgr_inv_unpaid_count), 'pct' => $mgr_inv_unpaid_count, 'color' => '#ea580c'],
    ['label' => 'Partial', 'value' => (string) number_format($mgr_inv_partial_count), 'pct' => $mgr_inv_partial_count, 'color' => '#2563eb'],
];
$mgr_inv_sidebar_bars = ops_docs_pct_bars([
    ['label' => 'New for review', 'count' => $mgr_inv_new_review, 'tone' => 'amber', 'icon' => 'fa-inbox'],
    ['label' => 'Outstanding', 'count' => $mgr_inv_unpaid, 'tone' => 'red', 'icon' => 'fa-clock'],
    ['label' => 'Paid', 'count' => $mgr_inv_paid_count, 'tone' => 'green', 'icon' => 'fa-check'],
], max(1, $mgr_inv_total));
$mgr_inv_sidebar_recent = mgr_build_ops_sidebar_recent($pdo, 6);
