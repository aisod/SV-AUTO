<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../backend/config/notifications.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/quotation_paper_signoff.inc.php';

/**
 * @return array{sent: bool, sent_at: string, sent_by: string, viewed_at: string}
 */
function qt_manager_review_info(array $form): array
{
    return [
        'sent' => trim((string) ($form['manager_review_sent_at'] ?? '')) !== '',
        'sent_at' => trim((string) ($form['manager_review_sent_at'] ?? '')),
        'sent_by' => trim((string) ($form['manager_review_sent_by'] ?? '')),
        'viewed_at' => trim((string) ($form['manager_review_viewed_at'] ?? '')),
    ];
}

/**
 * @return list<int>
 */
function qt_manager_user_ids(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE LOWER(TRIM(r.name)) = 'manager'"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
    } catch (Throwable $e) {
        error_log('qt_manager_user_ids: ' . $e->getMessage());
        return [];
    }
}

function qt_notify_managers_quotation_review(PDO $pdo, int $quoteId, string $quoteLabel, string $clientName): int
{
    $link = app_url('Manager/Quotation/view_quotation.php?id=' . $quoteId);
    $title = 'Quotation ready for your review';
    $message = $quoteLabel;
    if ($clientName !== '') {
        $message .= ' — ' . $clientName;
    }
    $message .= '. Preview the document before the paper copy is signed.';

    $count = 0;
    foreach (qt_manager_user_ids($pdo) as $userId) {
        if (createNotification($pdo, $userId, $title, $message, $link)) {
            $count++;
        }
    }
    return $count;
}

/**
 * Mark quotation as sent for manager portal preview (not paper sign-off).
 *
 * @return array{ok: bool, error?: string, id?: int, sent_at?: string, notified?: int}
 */
function qt_send_to_manager_review(PDO $pdo, int $quoteId, int $sentByUserId, string $sentByUsername): array
{
    if ($quoteId <= 0) {
        return ['ok' => false, 'error' => 'Invalid quotation.'];
    }

    $stmt = $pdo->prepare('SELECT id, details, status, client_id FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$quoteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Quotation not found.'];
    }

    if ((string) ($row['status'] ?? '') === 'rejected') {
        return ['ok' => false, 'error' => 'Rejected quotations cannot be sent for manager review.'];
    }

    $payload = qt_parse_quotation_details((string) ($row['details'] ?? ''));
    if (!$payload) {
        return ['ok' => false, 'error' => 'Quotation data could not be read.'];
    }

    $form = isset($payload['form']) && is_array($payload['form']) ? $payload['form'] : [];
    $sentAt = date('Y-m-d H:i:s');
    $sentBy = trim($sentByUsername) !== '' ? trim($sentByUsername) : 'Admin';

    $form['manager_review_sent_at'] = $sentAt;
    $form['manager_review_sent_by'] = $sentBy;
    $form['status'] = 'pending_manager';
    $payload['form'] = $form;

    $quoteNum = trim((string) ($form['quote_number'] ?? ''));
    if ($quoteNum === '') {
        $quoteNum = 'QT-' . str_pad((string) $quoteId, 5, '0', STR_PAD_LEFT);
    }

    $clientName = '';
    if (!empty($row['client_id'])) {
        $cStmt = $pdo->prepare('SELECT name FROM clients WHERE id = ? LIMIT 1');
        $cStmt->execute([(int) $row['client_id']]);
        $clientName = trim((string) ($cStmt->fetchColumn() ?: ''));
    }
    if ($clientName === '') {
        $clientName = trim((string) ($form['customer_name'] ?? ''));
    }

    $totals = isset($payload['totals']) && is_array($payload['totals']) ? $payload['totals'] : [];
    $grandTotal = (float) ($totals['grand_total'] ?? 0);
    $details = Q_JSON_MARKER . json_encode($payload, JSON_UNESCAPED_UNICODE)
        . "\n\n— Line summary —\nQuot. " . $quoteNum . "\nTotal " . number_format($grandTotal, 2);

    $pdo->prepare(
        'UPDATE quotations SET details = ?, status = ?, submitted_at = COALESCE(NULLIF(submitted_at, ""), ?) WHERE id = ?'
    )->execute([
        $details,
        'pending_manager',
        substr($sentAt, 0, 10),
        $quoteId,
    ]);

    $notified = qt_notify_managers_quotation_review($pdo, $quoteId, $quoteNum, $clientName);
    erp_audit_log($pdo, $sentByUserId, 'sent quotation for manager review', 'quotation', $quoteId);

    return [
        'ok' => true,
        'id' => $quoteId,
        'sent_at' => $sentAt,
        'sent_by' => $sentBy,
        'notified' => $notified,
    ];
}

/**
 * Record that a manager opened the portal preview.
 *
 * @return array{ok: bool, error?: string, viewed_at?: string, already?: bool}
 */
function qt_mark_manager_review_viewed(PDO $pdo, int $quoteId, int $viewerUserId, string $viewerName): array
{
    if ($quoteId <= 0) {
        return ['ok' => false, 'error' => 'Invalid quotation.'];
    }

    $stmt = $pdo->prepare('SELECT id, details FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$quoteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Quotation not found.'];
    }

    $payload = qt_parse_quotation_details((string) ($row['details'] ?? ''));
    if (!$payload) {
        return ['ok' => false, 'error' => 'Quotation data could not be read.'];
    }

    $form = isset($payload['form']) && is_array($payload['form']) ? $payload['form'] : [];
    if (trim((string) ($form['manager_review_sent_at'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'This quotation was not sent for manager review.'];
    }
    if (trim((string) ($form['manager_review_viewed_at'] ?? '')) !== '') {
        return ['ok' => true, 'already' => true, 'viewed_at' => trim((string) $form['manager_review_viewed_at'])];
    }

    $viewedAt = date('Y-m-d H:i:s');
    $form['manager_review_viewed_at'] = $viewedAt;
    $form['manager_review_viewed_by'] = trim($viewerName) !== '' ? trim($viewerName) : 'Manager';
    $payload['form'] = $form;

    $quoteNum = trim((string) ($form['quote_number'] ?? ''));
    if ($quoteNum === '') {
        $quoteNum = 'QT-' . str_pad((string) $quoteId, 5, '0', STR_PAD_LEFT);
    }
    $totals = isset($payload['totals']) && is_array($payload['totals']) ? $payload['totals'] : [];
    $grandTotal = (float) ($totals['grand_total'] ?? 0);
    $details = Q_JSON_MARKER . json_encode($payload, JSON_UNESCAPED_UNICODE)
        . "\n\n— Line summary —\nQuot. " . $quoteNum . "\nTotal " . number_format($grandTotal, 2);

    $pdo->prepare('UPDATE quotations SET details = ? WHERE id = ?')->execute([$details, $quoteId]);

    try {
        $admins = $pdo->query(
            "SELECT u.id FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE LOWER(TRIM(r.name)) = 'admin'"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($admins as $adminId) {
            createNotification(
                $pdo,
                (int) $adminId,
                'Manager viewed quotation',
                trim($viewerName) . ' previewed ' . $quoteNum . ' in the manager portal.',
                app_url('Admin/Quotation/add_quotation.php?id=' . $quoteId)
            );
        }
    } catch (Throwable $e) {
        error_log('qt_mark_manager_review_viewed notify: ' . $e->getMessage());
    }

    return ['ok' => true, 'viewed_at' => $viewedAt];
}

/**
 * @return list<int>
 */
function qt_admin_user_ids(PDO $pdo): array
{
    try {
        $stmt = $pdo->query(
            "SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE LOWER(TRIM(r.name)) = 'admin'"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
    } catch (Throwable $e) {
        error_log('qt_admin_user_ids: ' . $e->getMessage());
        return [];
    }
}

/**
 * @param array<string, mixed>|null $payload
 * @return list<array<string, mixed>>
 */
function qt_quotation_messages_list(?array $payload): array
{
    if (!is_array($payload)) {
        return [];
    }
    $raw = $payload['manager_comments'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $text = trim((string) ($row['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $role = strtolower(trim((string) ($row['by_role'] ?? '')));
        if ($role !== 'admin' && $role !== 'manager') {
            $role = 'manager';
        }
        $out[] = [
            'id' => (string) ($row['id'] ?? ''),
            'at' => trim((string) ($row['at'] ?? '')),
            'by_user_id' => (int) ($row['by_user_id'] ?? 0),
            'by_name' => trim((string) ($row['by_name'] ?? ($role === 'admin' ? 'Admin' : 'Manager'))),
            'by_role' => $role,
            'text' => $text,
        ];
    }
    usort($out, static function (array $a, array $b): int {
        return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
    });
    return $out;
}

/** @deprecated Use qt_quotation_messages_list */
function qt_manager_comments_list(?array $payload): array
{
    return qt_quotation_messages_list($payload);
}

function qt_rebuild_quotation_details_string(string $details, array $payload, int $quoteId): ?string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $form = isset($payload['form']) && is_array($payload['form']) ? $payload['form'] : [];
    $quoteNum = trim((string) ($form['quote_number'] ?? ''));
    if ($quoteNum === '') {
        $quoteNum = 'QT-' . str_pad((string) $quoteId, 5, '0', STR_PAD_LEFT);
    }
    $totals = isset($payload['totals']) && is_array($payload['totals']) ? $payload['totals'] : [];
    $grandTotal = (float) ($totals['grand_total'] ?? 0);

    return Q_JSON_MARKER . $json
        . "\n\n— Line summary —\nQuot. " . $quoteNum
        . "\nTotal " . number_format($grandTotal, 2);
}

/**
 * @return array{ok: bool, error?: string, message?: array<string, mixed>, notified?: int}
 */
function qt_add_quotation_message(
    PDO $pdo,
    int $quoteId,
    int $userId,
    string $userName,
    string $text,
    string $authorRole
): array {
    $text = trim($text);
    $authorRole = strtolower(trim($authorRole));
    if (!in_array($authorRole, ['admin', 'manager'], true)) {
        return ['ok' => false, 'error' => 'Invalid role.'];
    }
    if ($quoteId <= 0) {
        return ['ok' => false, 'error' => 'Invalid quotation.'];
    }
    if ($text === '') {
        return ['ok' => false, 'error' => 'Please enter a message.'];
    }
    if (function_exists('mb_strlen') && mb_strlen($text) > 2000) {
        return ['ok' => false, 'error' => 'Message is too long (maximum 2000 characters).'];
    }
    if (strlen($text) > 2000) {
        return ['ok' => false, 'error' => 'Message is too long (maximum 2000 characters).'];
    }

    $stmt = $pdo->prepare('SELECT id, details, client_id FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$quoteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['ok' => false, 'error' => 'Quotation not found.'];
    }

    $details = (string) ($row['details'] ?? '');
    $payload = qt_parse_quotation_details($details);
    if (!$payload) {
        return ['ok' => false, 'error' => 'Quotation data could not be read.'];
    }

    $defaultName = $authorRole === 'admin' ? 'Admin' : 'Manager';
    $author = trim($userName) !== '' ? trim($userName) : $defaultName;
    $message = [
        'id' => 'mc_' . bin2hex(random_bytes(8)),
        'at' => date('Y-m-d H:i:s'),
        'by_user_id' => $userId,
        'by_name' => $author,
        'by_role' => $authorRole,
        'text' => $text,
    ];

    if (!isset($payload['manager_comments']) || !is_array($payload['manager_comments'])) {
        $payload['manager_comments'] = [];
    }
    $payload['manager_comments'][] = $message;

    $newDetails = qt_rebuild_quotation_details_string($details, $payload, $quoteId);
    if ($newDetails === null) {
        return ['ok' => false, 'error' => 'Could not save message.'];
    }

    $pdo->prepare('UPDATE quotations SET details = ? WHERE id = ?')->execute([$newDetails, $quoteId]);

    $form = isset($payload['form']) && is_array($payload['form']) ? $payload['form'] : [];
    $quoteNum = trim((string) ($form['quote_number'] ?? ''));
    if ($quoteNum === '') {
        $quoteNum = 'QT-' . str_pad((string) $quoteId, 5, '0', STR_PAD_LEFT);
    }

    $clientName = '';
    if (!empty($row['client_id'])) {
        $cStmt = $pdo->prepare('SELECT name FROM clients WHERE id = ? LIMIT 1');
        $cStmt->execute([(int) $row['client_id']]);
        $clientName = trim((string) ($cStmt->fetchColumn() ?: ''));
    }

    $snippet = $text;
    if (function_exists('mb_strlen') && mb_strlen($snippet) > 120) {
        $snippet = mb_substr($snippet, 0, 117) . '…';
    } elseif (strlen($snippet) > 120) {
        $snippet = substr($snippet, 0, 117) . '…';
    }

    $notifMessage = $quoteNum;
    if ($clientName !== '') {
        $notifMessage .= ' · ' . $clientName;
    }
    $notifMessage .= "\n" . $author . ' wrote:' . "\n" . '"' . $snippet . '"';

    $notified = 0;
    if ($authorRole === 'manager') {
        $adminLink = erp_notification_link_with_intent(
            app_url('Admin/Quotation/add_quotation.php?id=' . $quoteId),
            'Manager message on quotation'
        );
        foreach (qt_admin_user_ids($pdo) as $adminId) {
            if ($adminId === $userId) {
                continue;
            }
            if (createNotification($pdo, $adminId, 'Manager message on quotation', $notifMessage, $adminLink)) {
                $notified++;
            }
        }
    } else {
        $mgrLink = erp_notification_link_with_intent(
            app_url('Manager/Quotation/view_quotation.php?id=' . $quoteId),
            'Admin message on quotation'
        );
        foreach (qt_manager_user_ids($pdo) as $mgrId) {
            if ($mgrId === $userId) {
                continue;
            }
            if (createNotification($pdo, $mgrId, 'Admin message on quotation', $notifMessage, $mgrLink)) {
                $notified++;
            }
        }
    }

    return ['ok' => true, 'message' => $message, 'comment' => $message, 'notified' => $notified];
}

/**
 * @return array{ok: bool, error?: string, comment?: array<string, mixed>}
 */
function qt_add_manager_comment(PDO $pdo, int $quoteId, int $userId, string $userName, string $text): array
{
    return qt_add_quotation_message($pdo, $quoteId, $userId, $userName, $text, 'manager');
}

/**
 * @return array{ok: bool, error?: string, message?: array<string, mixed>}
 */
function qt_add_admin_quotation_message(PDO $pdo, int $quoteId, int $userId, string $userName, string $text): array
{
    return qt_add_quotation_message($pdo, $quoteId, $userId, $userName, $text, 'admin');
}
