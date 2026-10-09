<?php
// Approve / block / unblock customer portal sign-ups (clients.status: pending | approved | blocked).
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';
require_once __DIR__ . '/../../../backend/config/notifications.php';

require_role(['admin']);

$tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'blocked' => 'Blocked'];
$tab = (string) ($_GET['tab'] ?? 'pending');
if (!isset($tabs[$tab])) {
    $tab = 'pending';
}
$me = (int) ($_SESSION['user_id'] ?? 0);

if (empty($_SESSION['client_approval_csrf'])) {
    $_SESSION['client_approval_csrf'] = bin2hex(random_bytes(32));
}
$self = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?');

// Allowed moves: pending->approved|blocked, approved->blocked, blocked->approved
$transitions = [
    'approve' => ['to' => 'approved', 'from' => ['pending', 'blocked'], 'msg' => 'Client approved. They can use the customer portal now.'],
    'block'   => ['to' => 'blocked',  'from' => ['pending', 'approved'], 'msg' => 'Client blocked. They can no longer sign in.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $backTab = isset($tabs[(string) ($_POST['tab'] ?? '')]) ? (string) $_POST['tab'] : 'pending';

    if (!hash_equals($_SESSION['client_approval_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $msg = ['error', 'Your session expired. Please try again.'];
    } elseif (!isset($transitions[$action]) || $clientId <= 0) {
        $msg = ['error', 'Invalid request.'];
    } else {
        $t = $transitions[$action];
        $placeholders = implode(',', array_fill(0, count($t['from']), '?'));
        $stmt = $pdo->prepare("UPDATE clients SET status = ? WHERE id = ? AND password_hash IS NOT NULL AND status IN ($placeholders)");
        $stmt->execute(array_merge([$t['to'], $clientId], $t['from']));
        if ($stmt->rowCount() === 1) {
            try {
                $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)')
                    ->execute([$me, $action === 'approve' ? 'approved client' : 'blocked client', 'client', $clientId]);
            } catch (Throwable $e) {
                error_log('Client approval audit log failed: ' . $e->getMessage());
            }
            if ($action === 'approve') {
                // The customer portal reads notifications by the client's own id.
                createNotification($pdo, $clientId, 'Your account has been approved',
                    'Welcome to SV Auto. You can now view your quotations and invoices.', 'dashboard.php');
            }
            $emailNote = '';
            if ($action === 'approve') {
                $c = $pdo->prepare('SELECT name, first_name, email FROM clients WHERE id = ?');
                $c->execute([$clientId]);
                if ($client = $c->fetch(PDO::FETCH_ASSOC)) {
                    $origin = app_is_production() ? 'https://' . APP_PRODUCTION_HOST
                        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                    $sent = erp_send_client_approved_email(
                        (string) $client['email'],
                        trim((string) ($client['first_name'] ?: $client['name'])),
                        $origin . app_url('login.php')
                    );
                    $emailNote = $sent['ok'] ? ' An email was sent to ' . $client['email'] . '.' : ' (The email could not be sent: ' . $sent['error'] . ')';
                }
            }
            $msg = ['success', $t['msg'] . $emailNote];
        } else {
            $msg = ['error', 'That client was not found or is already in that state.'];
        }
    }
    header('Location: ' . $self . '?tab=' . $backTab . '&' . $msg[0] . '=' . urlencode($msg[1]));
    exit;
}

// Portal sign-ups only (they have a password hash); clients added by staff are not part of approvals.
$counts = array_fill_keys(array_keys($tabs), 0);
foreach ($pdo->query("SELECT status, COUNT(*) AS n FROM clients WHERE password_hash IS NOT NULL AND deleted_at IS NULL GROUP BY status") as $c) {
    if (isset($counts[$c['status']])) {
        $counts[$c['status']] = (int) $c['n'];
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, name, first_name, last_name, email, phone, google_id, status, created_at
        FROM clients
        WHERE password_hash IS NOT NULL AND deleted_at IS NULL AND status = ?';
$params = [$tab];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    array_push($params, '%' . $q . '%', '%' . $q . '%', '%' . $q . '%');
}
$stmt = $pdo->prepare($sql . ' ORDER BY created_at DESC, id DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tabHtml = '';
foreach ($tabs as $key => $label) {
    $active = $key === $tab;
    $tabHtml .= '<a href="' . al_h($self . '?tab=' . $key) . '" class="erp-btn ' . ($active ? 'erp-btn-primary' : 'erp-btn-secondary') . '">'
        . al_h($label) . ' (' . $counts[$key] . ')</a>';
}

$button = static function (string $action, int $id, string $label, string $icon, string $cls, string $confirm) use ($tab): string {
    return '<form method="post" style="display:inline;margin:0 4px 0 0;" onsubmit="return confirm(' . al_h(json_encode($confirm)) . ');">'
        . '<input type="hidden" name="csrf_token" value="' . al_h($_SESSION['client_approval_csrf']) . '">'
        . '<input type="hidden" name="action" value="' . al_h($action) . '">'
        . '<input type="hidden" name="client_id" value="' . $id . '">'
        . '<input type="hidden" name="tab" value="' . al_h($tab) . '">'
        . '<button type="submit" class="erp-btn erp-btn-sm ' . $cls . '"><i class="fas ' . $icon . '"></i> ' . al_h($label) . '</button></form>';
};

$GLOBALS['erp_resume_title'] = 'Client Approvals';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Client Approvals',
    'subtitle' => $counts['pending'] . ' waiting for approval · customers who registered on the website or with Google',
    'icon' => 'fa-user-check',
    'table_id' => 'clientApprovalsTable',
    'search' => $q,
    'search_placeholder' => 'Search name, email or phone...',
    'keep_params' => ['tab' => $tab],
    'header_html' => $tabHtml,
    'empty' => 'No ' . strtolower($tabs[$tab]) . ' clients.',
    'columns' => [
        ['label' => 'Name', 'value' => function ($r) {
            $n = trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? ''));
            return $n !== '' ? $n : (string) $r['name'];
        }],
        ['label' => 'Email', 'value' => fn ($r) => (string) ($r['email'] ?? '-')],
        ['label' => 'Phone', 'value' => fn ($r) => (string) (($r['phone'] ?? '') !== '' ? $r['phone'] : '-')],
        ['label' => 'Signed up with', 'value' => fn ($r) => !empty($r['google_id']) ? 'Google' : 'Email form'],
        ['label' => 'Registered', 'value' => fn ($r) => al_date($r['created_at'] ?? null)],
        ['label' => 'Decision', 'sort' => false, 'html' => true, 'value' => function ($r) use ($button) {
            $id = (int) $r['id'];
            $name = trim((string) ($r['first_name'] ?? '') . ' ' . (string) ($r['last_name'] ?? '')) ?: (string) $r['name'];
            switch ($r['status']) {
                case 'pending':
                    return $button('approve', $id, 'Approve', 'fa-check', 'erp-btn-primary', 'Approve ' . $name . '?')
                         . $button('block', $id, 'Block', 'fa-ban', 'erp-btn-secondary', 'Block ' . $name . '? They will not be able to sign in.');
                case 'approved':
                    return $button('block', $id, 'Block', 'fa-ban', 'erp-btn-secondary', 'Block ' . $name . '? They will not be able to sign in.');
                default:
                    return $button('approve', $id, 'Unblock', 'fa-unlock', 'erp-btn-primary', 'Unblock and approve ' . $name . '?');
            }
        }],
    ],
], $rows);

include __DIR__ . '/../includes/footer.php';
