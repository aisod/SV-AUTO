<?php
/**
 * Server-side form drafts (resume work across logout / devices).
 */

function user_drafts_ensure_table(): void
{
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_drafts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        entity_type VARCHAR(32) NOT NULL,
        entity_id INT NULL DEFAULT NULL,
        draft_key VARCHAR(128) NOT NULL,
        page_url VARCHAR(512) NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        payload JSON NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_user_draft (user_id, draft_key),
        INDEX idx_user_updated (user_id, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function user_draft_save(
    int $userId,
    string $draftKey,
    string $entityType,
    ?int $entityId,
    string $pageUrl,
    string $title,
    array $payload
): bool {
    global $pdo;
    user_drafts_ensure_table();
    if ($userId <= 0 || $draftKey === '') {
        return false;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    $stmt = $pdo->prepare("INSERT INTO user_drafts
        (user_id, entity_type, entity_id, draft_key, page_url, title, payload)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            entity_type = VALUES(entity_type),
            entity_id = VALUES(entity_id),
            page_url = VALUES(page_url),
            title = VALUES(title),
            payload = VALUES(payload),
            updated_at = CURRENT_TIMESTAMP");
    return $stmt->execute([
        $userId,
        $entityType,
        $entityId,
        $draftKey,
        $pageUrl,
        $title,
        $json,
    ]);
}

function user_draft_delete(int $userId, string $draftKey): void
{
    global $pdo;
    user_drafts_ensure_table();
    if ($userId <= 0 || $draftKey === '') {
        return;
    }
    $stmt = $pdo->prepare('DELETE FROM user_drafts WHERE user_id = ? AND draft_key = ?');
    $stmt->execute([$userId, $draftKey]);
}

function user_draft_get(int $userId, string $draftKey): ?array
{
    global $pdo;
    user_drafts_ensure_table();
    if ($userId <= 0 || $draftKey === '') {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM user_drafts WHERE user_id = ? AND draft_key = ? LIMIT 1');
    $stmt->execute([$userId, $draftKey]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['payload'] = json_decode((string) ($row['payload'] ?? '{}'), true);
    if (!is_array($row['payload'])) {
        $row['payload'] = [];
    }
    return $row;
}

/** @return list<array<string,mixed>> */
function user_drafts_list(int $userId, int $limit = 8): array
{
    global $pdo;
    user_drafts_ensure_table();
    if ($userId <= 0) {
        return [];
    }
    $limit = max(1, min(20, $limit));
    $stmt = $pdo->prepare("SELECT id, entity_type, entity_id, draft_key, page_url, title, updated_at
        FROM user_drafts
        WHERE user_id = ?
          AND draft_key NOT LIKE 'resume:%'
        ORDER BY updated_at DESC
        LIMIT {$limit}");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function user_draft_title_from_payload(array $payload, string $fallback = 'Unsaved work'): string
{
    foreach (['card_number', 'jc_card_number', 'jc_card_number_input'] as $key) {
        $v = trim((string) ($payload[$key] ?? ''));
        if ($v !== '') {
            return $v;
        }
    }
    return $fallback;
}

function user_draft_time_ago(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' min ago';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' hr ago';
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);
        return $d . ' day' . ($d === 1 ? '' : 's') . ' ago';
    }
    return date('d M Y H:i', $ts);
}

const USER_RESUME_LAST_KEY = 'resume:last_page';

function user_resume_build_page_url(): string
{
    $parts = $GLOBALS['erp_admin_after_parts'] ?? [];
    $script = basename($_SERVER['PHP_SELF'] ?? '');
    if ($script === '') {
        return '';
    }
    $path = $parts === [] ? $script : implode('/', $parts) . '/' . $script;
    $qs = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    if ($qs !== '') {
        $path .= '?' . $qs;
    }
    return $path;
}

function user_resume_label_from_script(string $script): string
{
    $base = strtolower((string) pathinfo($script, PATHINFO_FILENAME));
    $labels = [
        'add_quotation' => 'New quotation',
        'edit_quotation' => 'Edit quotation',
        'view_quotation' => 'View quotation',
        'send_quotation' => 'Send quotation',
        'quotations' => 'Quotations',
        'add_job_card' => 'Job card',
        'view_job_card' => 'View job card',
        'job_card' => 'Job cards',
        'view_invoice' => 'View invoice',
        'pay_invoice' => 'Pay invoice',
        'invoices' => 'Invoices',
        'add_invoice' => 'New invoice',
    ];
    $label = $labels[$base] ?? ucwords(str_replace('_', ' ', $base));
    if (isset($_GET['id']) && (int) $_GET['id'] > 0) {
        $label .= ' #' . (int) $_GET['id'];
    } elseif (isset($_GET['edit_id']) && (int) $_GET['edit_id'] > 0) {
        $label .= ' #' . (int) $_GET['edit_id'];
    }
    return $label;
}

function user_resume_should_track(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return false;
    }
    $script = basename($_SERVER['PHP_SELF'] ?? '');
    if ($script === '' || in_array($script, ['login.php', 'logout.php', 'dashboard.php'], true)) {
        return false;
    }
    $parts = $GLOBALS['erp_admin_after_parts'] ?? [];
    if (!empty($parts) && strtolower((string) $parts[0]) === 'api') {
        return false;
    }
    return true;
}

function user_resume_save_page(int $userId, string $pageUrl, string $title): bool
{
    if ($userId <= 0 || $pageUrl === '' || $title === '') {
        return false;
    }
    return user_draft_save(
        $userId,
        USER_RESUME_LAST_KEY,
        'last_page',
        null,
        $pageUrl,
        $title,
        ['page_url' => $pageUrl, 'title' => $title]
    );
}

function user_resume_get_last(int $userId): ?array
{
    $row = user_draft_get($userId, USER_RESUME_LAST_KEY);
    if (!$row) {
        return null;
    }
    return [
        'page_url' => (string) ($row['page_url'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function user_resume_track_current_page(int $userId): void
{
    if (!user_resume_should_track()) {
        return;
    }
    $pageUrl = user_resume_build_page_url();
    if ($pageUrl === '') {
        return;
    }
    $title = trim((string) ($GLOBALS['erp_resume_title'] ?? ''));
    if ($title === '') {
        $title = user_resume_label_from_script(basename($_SERVER['PHP_SELF'] ?? ''));
    }
    user_resume_save_page($userId, $pageUrl, $title);
}
