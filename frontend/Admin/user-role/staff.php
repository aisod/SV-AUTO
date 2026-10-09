<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT u.*, r.name AS role_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE LOWER(r.name) <> 'client'";
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.username LIKE ? OR u.email LIKE ? OR r.name LIKE ?)';
    $params = array_fill(0, 3, '%' . $q . '%');
}
$stmt = $pdo->prepare($sql . ' ORDER BY u.id ASC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$me = (int) ($_SESSION['user_id'] ?? 0);

$GLOBALS['erp_resume_title'] = 'Staff Accounts';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Staff Accounts',
    'subtitle' => count($rows) . ' staff login' . (count($rows) === 1 ? '' : 's') . ' · people who sign in to the admin system',
    'icon' => 'fa-id-badge',
    'table_id' => 'staffTable',
    'add_url' => 'user-role/staff_form.php',
    'add_label' => 'Add Staff',
    'search' => $q,
    'search_placeholder' => 'Search name, email or role...',
    'empty' => 'No staff accounts yet.',
    'header_html' => '<a href="user-role/user_roles.php" class="erp-btn erp-btn-secondary"><i class="fas fa-user-shield"></i> Roles</a>',
    'columns' => [
        ['label' => 'Username', 'value' => fn ($r) => $r['username'] . ((int) $r['id'] === $me ? ' (you)' : '')],
        ['label' => 'Email', 'value' => fn ($r) => (string) ($r['email'] ?? '-')],
        ['label' => 'Role', 'html' => true, 'value' => fn ($r) => al_badge((string) $r['role_name'], strtolower((string) $r['role_name']) === 'admin' ? 'blue' : 'grey')],
        ['label' => 'Status', 'html' => true, 'value' => fn ($r) => (int) ($r['is_active'] ?? 1) === 1 ? al_badge('Active', 'green') : al_badge('Deactivated', 'red')],
        ['label' => 'Created', 'value' => fn ($r) => al_date($r['created_at'] ?? null)],
    ],
    'actions' => fn ($r) => [
        'edit' => 'user-role/staff_form.php?id=' . (int) $r['id'],
    ],
], $rows);

include __DIR__ . '/../includes/footer.php';
