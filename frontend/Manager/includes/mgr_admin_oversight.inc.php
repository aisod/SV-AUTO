<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../Admin/JobCard/jc_status.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_paper_signoff.inc.php';

/**
 * Link manager can open to inspect what an admin changed.
 */
function mgr_activity_href(string $entityType, int $entityId): string
{
    if ($entityId <= 0) {
        return '';
    }
    $entityType = strtolower(trim($entityType));
    switch ($entityType) {
        case 'quotation':
            return 'Quotation/view_quotation.php?id=' . $entityId;
        case 'job_card':
            return 'JobCard/job_card.php';
        case 'invoice':
            return 'Invoice/view_invoice.php?id=' . $entityId;
        case 'employee':
            return 'Employee/employees.php';
        case 'expense':
            return 'Expense/expenses.php';
        case 'inventory':
            return 'Inventory/inventory.php';
        default:
            return '';
    }
}

function mgr_humanize_audit_action(string $action, string $entityType, int $entityId): string
{
    $action = trim($action);
    $entityType = strtolower(trim($entityType));
    $label = $action !== '' ? ucwords(str_replace('_', ' ', $action)) : 'Action';

    if ($entityType !== '' && $entityId > 0) {
        $ref = match ($entityType) {
            'quotation' => 'Quotation #' . $entityId,
            'job_card' => 'Job card #' . $entityId,
            'invoice' => 'Invoice #' . $entityId,
            'employee' => 'Employee #' . $entityId,
            default => ucfirst($entityType) . ' #' . $entityId,
        };
        $label .= ' — ' . $ref;
    }

    return $label;
}

/**
 * Full feed of admin-team actions for manager oversight (nothing filtered by revenue or role except manager/client).
 *
 * @return list<array{
 *   id:int,username:string,role_name:string,action:string,entity_type:string,entity_id:int,
 *   created_at:string,label:string,href:string,time_label:string
 * }>
 */
function mgr_fetch_admin_activity_feed(PDO $pdo, int $limit = 60): array
{
    $limit = max(1, min(100, $limit));
    $out = [];

    try {
        $stmt = $pdo->prepare("
            SELECT al.id, al.action, al.entity_type, al.entity_id, al.created_at,
                   u.username, r.name AS role_name
            FROM audit_logs al
            INNER JOIN users u ON u.id = al.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE LOWER(TRIM(r.name)) NOT IN ('manager', 'client')
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT " . (int) $limit);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('mgr_fetch_admin_activity_feed: ' . $e->getMessage());
        return [];
    }

    foreach ($rows as $row) {
        $entityType = strtolower(trim((string) ($row['entity_type'] ?? '')));
        $entityId = (int) ($row['entity_id'] ?? 0);
        $action = trim((string) ($row['action'] ?? ''));
        $createdAt = (string) ($row['created_at'] ?? '');

        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'username' => trim((string) ($row['username'] ?? 'User')),
            'role_name' => trim((string) ($row['role_name'] ?? 'Staff')),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => $createdAt,
            'label' => mgr_humanize_audit_action($action, $entityType, $entityId),
            'href' => mgr_activity_href($entityType, $entityId),
            'time_label' => mgr_format_activity_time($createdAt),
        ];
    }

    return $out;
}

/**
 * One row per admin user — their most recent action only (no duplicate lines per save).
 *
 * @return list<array{
 *   user_id:int,username:string,role_name:string,action:string,entity_type:string,entity_id:int,
 *   created_at:string,label:string,href:string,time_label:string,actions_today:int
 * }>
 */
function mgr_fetch_admin_latest_by_user(PDO $pdo): array
{
    $out = [];

    try {
        $stmt = $pdo->query("
            SELECT
                u.id AS user_id,
                u.username,
                r.name AS role_name,
                al.action,
                al.entity_type,
                al.entity_id,
                al.created_at,
                (
                    SELECT COUNT(*)
                    FROM audit_logs al2
                    WHERE al2.user_id = u.id
                      AND DATE(al2.created_at) = CURDATE()
                ) AS actions_today
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            INNER JOIN audit_logs al ON al.id = (
                SELECT al3.id
                FROM audit_logs al3
                WHERE al3.user_id = u.id
                ORDER BY al3.created_at DESC, al3.id DESC
                LIMIT 1
            )
            WHERE LOWER(TRIM(r.name)) NOT IN ('manager', 'client')
            ORDER BY al.created_at DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('mgr_fetch_admin_latest_by_user: ' . $e->getMessage());
        return [];
    }

    foreach ($rows as $row) {
        $entityType = strtolower(trim((string) ($row['entity_type'] ?? '')));
        $entityId = (int) ($row['entity_id'] ?? 0);
        $action = trim((string) ($row['action'] ?? ''));
        $createdAt = (string) ($row['created_at'] ?? '');
        $actionsToday = (int) ($row['actions_today'] ?? 0);

        $out[] = [
            'user_id' => (int) ($row['user_id'] ?? 0),
            'username' => trim((string) ($row['username'] ?? 'User')),
            'role_name' => trim((string) ($row['role_name'] ?? 'Staff')),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => $createdAt,
            'label' => mgr_humanize_audit_action($action, $entityType, $entityId),
            'href' => mgr_activity_href($entityType, $entityId),
            'time_label' => mgr_format_activity_time($createdAt),
            'actions_today' => $actionsToday,
        ];
    }

    return $out;
}

function mgr_count_admin_actions_today(PDO $pdo): int
{
    try {
        $stmt = $pdo->query("
            SELECT COUNT(*) FROM audit_logs al
            INNER JOIN users u ON u.id = al.user_id
            INNER JOIN roles r ON r.id = u.role_id
            WHERE LOWER(TRIM(r.name)) NOT IN ('manager', 'client')
              AND DATE(al.created_at) = CURDATE()");
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * @return list<array{show:bool,title:string,meta:string,url:string,badge:string,badge_class:string}>
 */
function mgr_build_admin_operations_tasks(PDO $pdo): array
{
    $jcAwaitingQuote = 0;
    $jcInProgress = 0;
    $qtPending = 0;
    $invOverdue = 0;

    try {
        $row = $pdo->query('SELECT COUNT(*) AS awaiting_quote_count
            FROM job_cards jc
            WHERE jc.deleted_at IS NULL
              AND ' . jc_status_open_sql('jc') . '
              AND NOT EXISTS (
                  SELECT 1 FROM quotations q
                  WHERE q.job_card_id = jc.id AND q.deleted_at IS NULL
              )')->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $jcAwaitingQuote = (int) ($row['awaiting_quote_count'] ?? 0);
        }

        $row = $pdo->query('SELECT COUNT(CASE WHEN ' . jc_status_in_progress_sql('jc') . ' THEN 1 END) AS c
            FROM job_cards jc WHERE jc.deleted_at IS NULL')->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $jcInProgress = (int) ($row['c'] ?? 0);
        }

        $row = $pdo->query("SELECT COUNT(*) AS pending_count FROM quotations
            WHERE deleted_at IS NULL
              AND status IN ('pending', 'pending_manager', 'sent_back_admin')")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $qtPending = (int) ($row['pending_count'] ?? 0);
        }

        $row = $pdo->query("SELECT COUNT(CASE WHEN status_paid IN ('unpaid', 'partial')
                AND due_date IS NOT NULL AND due_date < CURDATE() THEN 1 END) AS overdue_count
            FROM invoices WHERE deleted_at IS NULL")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $invOverdue = (int) ($row['overdue_count'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('mgr_build_admin_operations_tasks: ' . $e->getMessage());
    }

    $items = [
        ['show' => $jcAwaitingQuote > 0, 'title' => 'Job cards need quotations', 'meta' => $jcAwaitingQuote . ' waiting on admin', 'url' => 'manager_job_cards.php', 'badge' => 'Admin', 'badge_class' => 'rd-task-badge--review'],
        ['show' => $qtPending > 0, 'title' => 'Quotations in admin workflow', 'meta' => $qtPending . ' in progress', 'url' => 'manager_quotations.php', 'badge' => 'Admin', 'badge_class' => 'rd-task-badge--review'],
        ['show' => $invOverdue > 0, 'title' => 'Overdue invoices', 'meta' => $invOverdue . ' past due', 'url' => 'manager_invoices.php', 'badge' => 'Urgent', 'badge_class' => 'rd-task-badge--urgent'],
        ['show' => $jcInProgress > 0, 'title' => 'Workshop jobs active', 'meta' => $jcInProgress . ' in progress', 'url' => 'manager_job_cards.php', 'badge' => 'Jobs', 'badge_class' => 'rd-task-badge--open'],
    ];

    $tasks = [];
    foreach ($items as $item) {
        if (empty($item['show'])) {
            continue;
        }
        $tasks[] = [
            'title' => (string) $item['title'],
            'meta' => (string) $item['meta'],
            'url' => (string) $item['url'],
            'badge' => (string) $item['badge'],
            'badge_class' => (string) $item['badge_class'],
        ];
    }

    return $tasks;
}

/** @deprecated Use mgr_fetch_admin_activity_feed */
function mgr_fetch_recent_admin_activity(PDO $pdo, int $limit = 20): array
{
    return mgr_fetch_admin_activity_feed($pdo, $limit);
}

function mgr_fetch_admin_team_summary(PDO $pdo): array
{
    try {
        $stmt = $pdo->query("
            SELECT u.username, r.name AS role_name, COUNT(al.id) AS actions_7d
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN audit_logs al ON al.user_id = u.id
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE LOWER(TRIM(r.name)) NOT IN ('manager', 'client')
            GROUP BY u.id, u.username, r.name
            ORDER BY actions_7d DESC, u.username ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('mgr_fetch_admin_team_summary: ' . $e->getMessage());
        return [];
    }
}

function mgr_format_activity_time(string $datetime): string
{
    $datetime = trim($datetime);
    if ($datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }
    return date('j M Y, H:i', $ts);
}
