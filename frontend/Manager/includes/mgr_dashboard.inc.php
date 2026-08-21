<?php
declare(strict_types=1);

require_once __DIR__ . '/mgr_review.inc.php';
require_once __DIR__ . '/../../../backend/config/notifications.php';
require_once __DIR__ . '/../../Admin/JobCard/jc_status.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_paper_signoff.inc.php';

/**
 * @return array<string,mixed>
 */
function mgr_load_dashboard_metrics(PDO $pdo): array
{
    $m = [
        'qt_new' => mgr_count_quotations_for_review($pdo, true),
        'qt_sent' => mgr_count_quotations_for_review($pdo, false),
        'inv_new' => mgr_count_invoices_for_review($pdo, true),
        'inv_sent' => mgr_count_invoices_for_review($pdo, false),
        'preview_waiting' => 0,
        'jc_open' => 0,
        'jc_in_progress' => 0,
        'inv_outstanding_count' => 0,
        'inv_outstanding_amount' => 0.0,
        'inv_overdue_count' => 0,
        'inv_overdue_amount' => 0.0,
        'inv_paid_amount' => 0.0,
        'inv_paid_month_amount' => 0.0,
        'inv_unpaid_amount' => 0.0,
        'inv_partial_amount' => 0.0,
        'chart_month_labels' => [],
        'chart_collected' => [],
        'chart_outstanding' => [],
        'top_clients' => [],
    ];
    $m['preview_waiting'] = $m['qt_new'] + $m['inv_new'];

    try {
        $row = $pdo->query('SELECT
            COUNT(CASE WHEN ' . jc_status_open_sql('jc') . ' THEN 1 END) AS open_count,
            COUNT(CASE WHEN ' . jc_status_in_progress_sql('jc') . ' THEN 1 END) AS in_progress_count
        FROM job_cards jc WHERE jc.deleted_at IS NULL')->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $m['jc_open'] = (int) ($row['open_count'] ?? 0);
            $m['jc_in_progress'] = (int) ($row['in_progress_count'] ?? 0);
        }

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
        FROM invoices WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $m['inv_outstanding_count'] = (int) ($row['open_count'] ?? 0);
            $m['inv_outstanding_amount'] = (float) ($row['outstanding_amount'] ?? 0);
            $m['inv_overdue_count'] = (int) ($row['overdue_count'] ?? 0);
            $m['inv_overdue_amount'] = (float) ($row['overdue_amount'] ?? 0);
            $m['inv_paid_amount'] = (float) ($row['paid_amount'] ?? 0);
            $m['inv_paid_month_amount'] = (float) ($row['paid_month_amount'] ?? 0);
            $m['inv_unpaid_amount'] = (float) ($row['unpaid_amount'] ?? 0);
            $m['inv_partial_amount'] = (float) ($row['partial_amount'] ?? 0);
        }

        $chartMonthKeys = [];
        for ($mo = 5; $mo >= 0; $mo--) {
            $ts = strtotime('-' . $mo . ' months');
            $chartMonthKeys[] = date('Y-m', $ts);
            $m['chart_month_labels'][] = date('M Y', $ts);
        }
        $m['chart_collected'] = array_fill(0, 6, 0.0);
        $m['chart_outstanding'] = array_fill(0, 6, 0.0);
        $since = date('Y-m-01', strtotime('-5 months'));

        $collectedRows = $pdo->query("SELECT DATE_FORMAT(COALESCE(paid_at, issued_date, created_at), '%Y-%m') AS ym,
            COALESCE(SUM(amount), 0) AS total FROM invoices
            WHERE deleted_at IS NULL AND status_paid = 'paid'
            AND COALESCE(paid_at, issued_date, created_at) >= '$since'
            GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($collectedRows as $cr) {
            $idx = array_search((string) ($cr['ym'] ?? ''), $chartMonthKeys, true);
            if ($idx !== false) {
                $m['chart_collected'][$idx] = (float) ($cr['total'] ?? 0);
            }
        }

        $outRows = $pdo->query("SELECT DATE_FORMAT(COALESCE(issued_date, created_at), '%Y-%m') AS ym,
            COALESCE(SUM(CASE WHEN status_paid IN ('unpaid', 'partial') THEN amount ELSE 0 END), 0) AS total
            FROM invoices WHERE deleted_at IS NULL
            AND COALESCE(issued_date, created_at) >= '$since' GROUP BY ym")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($outRows as $or) {
            $idx = array_search((string) ($or['ym'] ?? ''), $chartMonthKeys, true);
            if ($idx !== false) {
                $m['chart_outstanding'][$idx] = (float) ($or['total'] ?? 0);
            }
        }

        $m['top_clients'] = $pdo->query("
            SELECT
                COALESCE(c.id, 0) AS client_id,
                COALESCE(NULLIF(TRIM(c.name), ''), 'Walk-in') AS name,
                COUNT(i.id) AS invoice_count,
                COUNT(CASE WHEN i.status_paid IN ('unpaid', 'partial') THEN 1 END) AS open_invoice_count,
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
            ORDER BY paid_revenue DESC, outstanding DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('mgr_load_dashboard_metrics: ' . $e->getMessage());
    }

    return $m;
}

/**
 * Unviewed documents admin sent for manager preview.
 *
 * @return list<array<string,mixed>>
 */
function mgr_fetch_preview_queue(PDO $pdo, int $limit = 8): array
{
    $items = [];

    try {
        $qWhere = mgr_quotation_unviewed_where();
        $qStmt = $pdo->query("
            SELECT q.id, q.amount, q.details, c.name AS client_name
            FROM quotations q
            LEFT JOIN clients c ON c.id = q.client_id
            WHERE {$qWhere}
            ORDER BY q.id DESC
            LIMIT " . (int) max(1, $limit));
        while ($row = $qStmt->fetch(PDO::FETCH_ASSOC)) {
            $meta = mgr_quotation_review_row_meta((string) ($row['details'] ?? ''));
            $items[] = [
                'type' => 'quotation',
                'id' => (int) ($row['id'] ?? 0),
                'label' => 'QTN-' . str_pad((string) ($row['id'] ?? ''), 5, '0', STR_PAD_LEFT),
                'client_name' => trim((string) ($row['client_name'] ?? '')) ?: '—',
                'amount' => (float) ($row['amount'] ?? 0),
                'sent_at' => $meta['sent_at'],
                'url' => 'Quotation/view_quotation.php?id=' . (int) ($row['id'] ?? 0),
                'sort_ts' => $meta['sent_at'] !== '' ? strtotime($meta['sent_at']) : 0,
            ];
        }
    } catch (Throwable $e) {
        error_log('mgr_fetch_preview_queue quotations: ' . $e->getMessage());
    }

    mgr_ensure_invoice_review_columns($pdo);
    try {
        $iWhere = mgr_invoice_sent_for_review_where() . ' AND i.manager_review_viewed_at IS NULL AND i.deleted_at IS NULL';
        $iStmt = $pdo->query("
            SELECT i.id, i.invoice_number, i.amount, i.manager_review_sent_at, c.name AS client_name
            FROM invoices i
            LEFT JOIN quotations q ON q.id = i.quotation_id
            LEFT JOIN clients c ON c.id = q.client_id
            WHERE {$iWhere}
            ORDER BY i.manager_review_sent_at DESC
            LIMIT " . (int) max(1, $limit));
        while ($row = $iStmt->fetch(PDO::FETCH_ASSOC)) {
            $invNum = trim((string) ($row['invoice_number'] ?? ''));
            if ($invNum === '') {
                $invNum = 'INV-' . str_pad((string) ($row['id'] ?? ''), 6, '0', STR_PAD_LEFT);
            }
            $sentAt = trim((string) ($row['manager_review_sent_at'] ?? ''));
            $items[] = [
                'type' => 'invoice',
                'id' => (int) ($row['id'] ?? 0),
                'label' => $invNum,
                'client_name' => trim((string) ($row['client_name'] ?? '')) ?: '—',
                'amount' => (float) ($row['amount'] ?? 0),
                'sent_at' => $sentAt,
                'url' => 'Invoice/view_invoice.php?id=' . (int) ($row['id'] ?? 0),
                'sort_ts' => $sentAt !== '' ? strtotime($sentAt) : 0,
            ];
        }
    } catch (Throwable $e) {
        error_log('mgr_fetch_preview_queue invoices: ' . $e->getMessage());
    }

    usort($items, static function (array $a, array $b): int {
        return ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0);
    });

    return array_slice($items, 0, $limit);
}

/**
 * @param array<string,mixed> $m
 * @return list<array{title:string,meta:string,url:string,badge:string,badge_class:string}>
 */
function mgr_build_dashboard_tasks(PDO $pdo, array $m, int $userId = 0): array
{
    $tasks = [];
    $preview = (int) ($m['preview_waiting'] ?? 0);

    if ((int) ($m['qt_new'] ?? 0) > 0) {
        $tasks[] = [
            'title' => 'Quotations awaiting preview',
            'meta' => (int) $m['qt_new'] . ' new · open inbox to read before paper sign-off',
            'url' => 'manager_quotations.php?filter=inbox',
            'badge' => 'Preview',
            'badge_class' => 'rd-task-badge--urgent',
        ];
    }
    if ((int) ($m['inv_new'] ?? 0) > 0) {
        $tasks[] = [
            'title' => 'Invoices awaiting preview',
            'meta' => (int) $m['inv_new'] . ' new · review before payment processing',
            'url' => 'manager_invoices.php?filter=inbox',
            'badge' => 'Preview',
            'badge_class' => 'rd-task-badge--urgent',
        ];
    }
    if ((int) ($m['inv_overdue_count'] ?? 0) > 0) {
        $tasks[] = [
            'title' => 'Overdue invoices',
            'meta' => (int) $m['inv_overdue_count'] . ' past due · ' . formatMoney((float) ($m['inv_overdue_amount'] ?? 0)),
            'url' => 'manager_invoices.php',
            'badge' => 'Urgent',
            'badge_class' => 'rd-task-badge--urgent',
        ];
    }
    if ((int) ($m['jc_in_progress'] ?? 0) > 0) {
        $tasks[] = [
            'title' => 'Jobs in progress',
            'meta' => (int) $m['jc_in_progress'] . ' active in the workshop',
            'url' => 'manager_job_cards.php',
            'badge' => 'Active',
            'badge_class' => 'rd-task-badge--open',
        ];
    }
    if ((int) ($m['qt_sent'] ?? 0) > 0 && $preview === 0) {
        $tasks[] = [
            'title' => 'Quotations sent for review',
            'meta' => (int) $m['qt_sent'] . ' total · all previews up to date',
            'url' => 'manager_quotations.php',
            'badge' => 'Clear',
            'badge_class' => 'rd-task-badge--done',
        ];
    }

    if ($userId > 0) {
        $notifFollowup = erp_latest_unread_notification_task($pdo, $userId, static function (string $link): string {
            return mgr_notif_link($link);
        });
        if ($notifFollowup !== null) {
            array_unshift($tasks, $notifFollowup);
        }
    }

    if ($tasks === []) {
        $tasks[] = [
            'title' => 'All caught up',
            'meta' => 'No documents waiting for your preview',
            'url' => 'manager_dashboard.php',
            'badge' => 'Clear',
            'badge_class' => 'rd-task-badge--done',
        ];
    }

    return $tasks;
}
