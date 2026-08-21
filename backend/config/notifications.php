<?php
// Notification Helper Functions

/**
 * Create a notification for a user
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID to notify
 * @param string $title Notification title
 * @param string $message Notification message (optional)
 * @param string $link Link to redirect when clicked (optional)
 * @return bool Success status
 */
function createNotification($pdo, $userId, $title, $message = '', $link = '') {
    try {
        // Ensure table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT,
            link VARCHAR(255),
            is_read TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $title, $message, $link]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user ID by client ID (for notifying clients)
 * 
 * @param PDO $pdo Database connection
 * @param int $clientId Client ID
 * @return int|null User ID or null if not found
 */
function getUserIdByClientId($pdo, $clientId) {
    try {
        // First try to find user by client_id in users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE client_id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            return $userId;
        }
        
        // Fallback: find client email, then find user with that email
        $stmt = $pdo->prepare("SELECT email FROM clients WHERE id = ? LIMIT 1");
        $stmt->execute([$clientId]);
        $email = $stmt->fetchColumn();
        
        if ($email) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            return $stmt->fetchColumn();
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Failed to get user ID by client ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Notify admin users (for client responses)
 * 
 * @param PDO $pdo Database connection
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string $link Link to redirect
 * @return int Number of notifications created
 */
/**
 * Short preview for dashboard / lists.
 */
function erp_notification_preview(string $message, int $maxLen = 100): string
{
    $message = trim(preg_replace('/\s+/u', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $message)));
    if ($message === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($message) > $maxLen) {
        return mb_substr($message, 0, $maxLen - 1) . '…';
    }
    if (strlen($message) > $maxLen) {
        return substr($message, 0, $maxLen - 1) . '…';
    }
    return $message;
}

/**
 * Latest unread notification as a Follow-up dashboard task (null if none).
 *
 * @param callable(string): string $resolveUrl
 * @return array{title:string,meta:string,url:string,badge:string,badge_class:string}|null
 */
function erp_latest_unread_notification_task(PDO $pdo, int $userId, callable $resolveUrl): ?array
{
    if ($userId <= 0) {
        return null;
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT id, title, message, link, created_at FROM notifications
             WHERE user_id = ? AND is_read = 0
             ORDER BY created_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = 'New notification';
        }
        $meta = erp_notification_preview((string) ($row['message'] ?? ''));
        if ($meta === '') {
            $meta = 'Tap to open';
        }
        $created = trim((string) ($row['created_at'] ?? ''));
        if ($created !== '') {
            $meta .= ' · ' . date('M j, g:i A', strtotime($created));
        }
        $link = trim((string) ($row['link'] ?? ''));
        $link = erp_notification_link_with_intent($link, $title);
        $url = $link !== '' ? $resolveUrl($link) : '#';

        return [
            'title' => $title,
            'meta' => $meta,
            'url' => $url,
            'badge' => 'New',
            'badge_class' => 'rd-task-badge--urgent',
        ];
    } catch (Throwable $e) {
        error_log('erp_latest_unread_notification_task: ' . $e->getMessage());
        return null;
    }
}

/**
 * Mark notifications read when a quotation thread is opened.
 */
/**
 * Append open_chat=1 for discussion-related notifications.
 */
function erp_notification_link_with_intent(string $link, string $title): string
{
    $link = trim($link);
    if ($link === '' || $link === '#') {
        return $link;
    }
    $t = strtolower($title);
    $isChat = str_contains($t, 'message') || str_contains($t, 'comment');
    if (!$isChat) {
        return $link;
    }
    if (str_contains($link, 'open_chat=1')) {
        return $link;
    }
    return $link . (str_contains($link, '?') ? '&' : '?') . 'open_chat=1';
}

function erp_mark_notifications_read_for_quotation(PDO $pdo, int $userId, int $quoteId): int
{
    if ($userId <= 0 || $quoteId <= 0) {
        return 0;
    }
    try {
        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1
             WHERE user_id = ? AND is_read = 0
             AND (
                link LIKE ? OR link LIKE ?
             )"
        );
        $stmt->execute([
            $userId,
            '%add_quotation.php?id=' . $quoteId . '%',
            '%view_quotation.php?id=' . $quoteId . '%',
        ]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        error_log('erp_mark_notifications_read_for_quotation: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Ensure notifications table exists (idempotent).
 */
function erp_ensure_notifications_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(255),
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Header bell feed: unread count + recent items for live polling.
 *
 * @param callable(string,string): string $resolveLink (raw link, title) => href
 * @return array{count:int,items:list<array<string,mixed>>}
 */
function erp_notifications_header_feed(PDO $pdo, int $userId, callable $resolveLink, int $limit = 10): array
{
    if ($userId <= 0) {
        return ['count' => 0, 'items' => []];
    }
    try {
        erp_ensure_notifications_table($pdo);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $countStmt->execute([$userId]);
        $count = (int) $countStmt->fetchColumn();

        $limit = max(1, min(20, $limit));
        $recentStmt = $pdo->prepare(
            'SELECT id, title, message, link, is_read, created_at
             FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit
        );
        $recentStmt->execute([$userId]);
        $rows = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            $rawLink = trim((string) ($row['link'] ?? ''));
            $rawLink = erp_notification_link_with_intent($rawLink, $title);
            $href = $rawLink !== '' ? $resolveLink($rawLink, $title) : '#';
            $created = trim((string) ($row['created_at'] ?? ''));
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'title' => $title !== '' ? $title : 'Notification',
                'message' => trim((string) ($row['message'] ?? '')),
                'link' => $href,
                'is_read' => !empty($row['is_read']),
                'time' => $created !== '' ? date('M j, Y g:i A', strtotime($created)) : '',
            ];
        }

        return ['count' => $count, 'items' => $items];
    } catch (Throwable $e) {
        error_log('erp_notifications_header_feed: ' . $e->getMessage());
        return ['count' => 0, 'items' => []];
    }
}

function notifyAdmins($pdo, $title, $message, $link = '') {
    try {
        // Get all admin users
        $stmt = $pdo->query("SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE LOWER(r.name) IN ('admin', 'manager')");
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $count = 0;
        foreach ($admins as $adminId) {
            if (createNotification($pdo, $adminId, $title, $message, $link)) {
                $count++;
            }
        }
        return $count;
    } catch (Exception $e) {
        error_log("Failed to notify admins: " . $e->getMessage());
        return 0;
    }
}
