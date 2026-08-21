<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_manager_review.inc.php';

/** SQL: quotation was sent for manager portal preview. */
function mgr_quotation_sent_for_review_where(): string
{
    return "q.deleted_at IS NULL
        AND q.details LIKE '%manager_review_sent_at%'
        AND q.details NOT LIKE '%\"manager_review_sent_at\":\"\"%'
        AND q.details NOT LIKE '%\"manager_review_sent_at\": \"\"%'";
}

function mgr_quotation_unviewed_where(): string
{
    return mgr_quotation_sent_for_review_where() . "
        AND (q.details NOT LIKE '%manager_review_viewed_at%'
             OR q.details LIKE '%\"manager_review_viewed_at\":\"\"%'
             OR q.details LIKE '%\"manager_review_viewed_at\": \"\"%')";
}

function mgr_count_quotations_for_review(PDO $pdo, bool $unviewedOnly = false): int
{
    $where = $unviewedOnly ? mgr_quotation_unviewed_where() : mgr_quotation_sent_for_review_where();
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM quotations q WHERE ' . $where);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('mgr_count_quotations_for_review: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Parse viewed_at from quotation details for list display.
 */
function mgr_quotation_review_row_meta(string $details): array
{
    $payload = qt_parse_quotation_details($details);
    $form = (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) ? $payload['form'] : [];
    $info = qt_manager_review_info($form);
    $sentAt = $info['sent_at'];
    return [
        'sent' => $sentAt !== '',
        'sent_at' => $sentAt,
        'sent_by' => $info['sent_by'],
        'viewed_at' => $info['viewed_at'],
        'viewed' => $info['viewed_at'] !== '',
    ];
}

/** True when admin used “Send to manager for review” on this quotation. */
function mgr_quotation_sent_for_review(string $details): bool
{
    return mgr_quotation_review_row_meta($details)['sent'];
}

function mgr_ensure_invoice_review_columns(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $columns = [
        'manager_review_sent_at' => 'DATETIME NULL',
        'manager_review_sent_by' => 'VARCHAR(120) NULL',
        'manager_review_viewed_at' => 'DATETIME NULL',
    ];
    try {
        $existing = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM invoices');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existing[(string) ($row['Field'] ?? '')] = true;
        }
        foreach ($columns as $col => $def) {
            if (!isset($existing[$col])) {
                $pdo->exec('ALTER TABLE invoices ADD COLUMN `' . $col . '` ' . $def);
            }
        }
    } catch (Throwable $e) {
        error_log('mgr_ensure_invoice_review_columns: ' . $e->getMessage());
    }
    $done = true;
}

function mgr_notify_managers_document(PDO $pdo, string $title, string $message, string $link): int
{
    $count = 0;
    foreach (qt_manager_user_ids($pdo) as $userId) {
        if (createNotification($pdo, $userId, $title, $message, $link)) {
            $count++;
        }
    }
    return $count;
}

/**
 * @return array{ok: bool, error?: string, id?: int, sent_at?: string, notified?: int}
 */
function inv_send_to_manager_review(PDO $pdo, int $invoiceId, string $sentByUsername): array
{
    if ($invoiceId <= 0) {
        return ['ok' => false, 'error' => 'Invalid invoice.'];
    }
    mgr_ensure_invoice_review_columns($pdo);

    try {
        $stmt = $pdo->prepare(
            'SELECT i.id, i.invoice_number, i.amount, q.client_id
             FROM invoices i
             LEFT JOIN quotations q ON q.id = i.quotation_id
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('inv_send_to_manager_review load: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not load invoice.'];
    }
    if (!$row) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }

    $sentAt = date('Y-m-d H:i:s');
    $sentBy = trim($sentByUsername) !== '' ? trim($sentByUsername) : 'Admin';
    try {
        $upd = $pdo->prepare(
            'UPDATE invoices SET manager_review_sent_at = ?, manager_review_sent_by = ?, manager_review_viewed_at = NULL WHERE id = ?'
        );
        $upd->execute([$sentAt, $sentBy, $invoiceId]);
    } catch (Throwable $e) {
        error_log('inv_send_to_manager_review update: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not update invoice (database columns may be missing). Run migrations or contact support.'];
    }

    $invNum = trim((string) ($row['invoice_number'] ?? ''));
    if ($invNum === '') {
        $invNum = 'INV-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT);
    }
    $clientName = '';
    if (!empty($row['client_id'])) {
        try {
            $cStmt = $pdo->prepare('SELECT name FROM clients WHERE id = ? LIMIT 1');
            $cStmt->execute([(int) $row['client_id']]);
            $clientName = trim((string) ($cStmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            error_log('inv_send_to_manager_review client: ' . $e->getMessage());
        }
    }

    $message = $invNum;
    if ($clientName !== '') {
        $message .= ' — ' . $clientName;
    }
    $message .= '. Preview before payment processing.';

    $notified = 0;
    try {
        $notified = mgr_notify_managers_document(
            $pdo,
            'Invoice ready for your review',
            $message,
            app_url('Manager/Invoice/view_invoice.php?id=' . $invoiceId)
        );
    } catch (Throwable $e) {
        error_log('inv_send_to_manager_review notify: ' . $e->getMessage());
    }

    $actorId = (int) ($_SESSION['user_id'] ?? 0);
    if ($actorId > 0) {
        erp_audit_log($pdo, $actorId, 'sent invoice for manager review', 'invoice', $invoiceId);
    }

    return ['ok' => true, 'id' => $invoiceId, 'sent_at' => $sentAt, 'notified' => $notified];
}

/**
 * @return array{ok: bool, error?: string}
 */
function inv_mark_manager_review_viewed(PDO $pdo, int $invoiceId, int $viewerUserId, string $viewerName): array
{
    if ($invoiceId <= 0) {
        return ['ok' => false, 'error' => 'Invalid invoice.'];
    }
    mgr_ensure_invoice_review_columns($pdo);

    $stmt = $pdo->prepare('SELECT id, manager_review_sent_at, manager_review_viewed_at FROM invoices WHERE id = ? LIMIT 1');
    $stmt->execute([$invoiceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Invoice not found.'];
    }
    if (trim((string) ($row['manager_review_sent_at'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'This invoice was not sent for manager review.'];
    }
    if (trim((string) ($row['manager_review_viewed_at'] ?? '')) !== '') {
        return ['ok' => true, 'already' => true];
    }

    $viewedAt = date('Y-m-d H:i:s');
    $pdo->prepare('UPDATE invoices SET manager_review_viewed_at = ? WHERE id = ?')->execute([$viewedAt, $invoiceId]);

    try {
        $admins = $pdo->query(
            "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE LOWER(TRIM(r.name)) = 'admin'"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $invStmt = $pdo->prepare('SELECT invoice_number FROM invoices WHERE id = ? LIMIT 1');
        $invStmt->execute([$invoiceId]);
        $invNum = trim((string) ($invStmt->fetchColumn() ?: ''));
        if ($invNum === '') {
            $invNum = 'INV-' . $invoiceId;
        }
        foreach ($admins as $adminId) {
            createNotification(
                $pdo,
                (int) $adminId,
                'Manager viewed invoice',
                trim($viewerName) . ' previewed invoice ' . $invNum . '.',
                app_url('Admin/Invoice/view.php?id=' . $invoiceId)
            );
        }
    } catch (Throwable $e) {
        error_log('inv_mark_manager_review_viewed notify: ' . $e->getMessage());
    }

    return ['ok' => true, 'viewed_at' => $viewedAt];
}

function mgr_invoice_sent_for_review_where(): string
{
    return 'i.manager_review_sent_at IS NOT NULL';
}

function mgr_count_invoices_for_review(PDO $pdo, bool $unviewedOnly = false): int
{
    mgr_ensure_invoice_review_columns($pdo);
    $where = mgr_invoice_sent_for_review_where();
    if ($unviewedOnly) {
        $where .= ' AND i.manager_review_viewed_at IS NULL';
    }
    try {
        $stmt = $pdo->query('SELECT COUNT(*) FROM invoices i WHERE ' . $where);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
