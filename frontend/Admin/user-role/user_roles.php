<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT r.id, r.name, r.permissions, COUNT(u.id) AS user_count
        FROM roles r
        LEFT JOIN users u ON u.role_id = r.id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE r.name LIKE ? OR r.permissions LIKE ?';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
$stmt = $pdo->prepare($sql . ' GROUP BY r.id ORDER BY r.id ASC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['erp_resume_title'] = 'User Roles';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'User Roles',
    'subtitle' => count($rows) . ' role' . (count($rows) === 1 ? '' : 's') . ' and what each can access',
    'icon' => 'fa-user-shield',
    'table_id' => 'rolesTable',
    'add_url' => 'user-role/create_role.php',
    'add_label' => 'Create Role',
    'search' => $q,
    'search_placeholder' => 'Search roles...',
    'empty' => 'No roles defined.',
    'columns' => [
        ['label' => 'Role', 'value' => fn ($r) => $r['name']],
        ['label' => 'Permissions', 'html' => true, 'value' => function ($r) {
            $perms = array_filter(array_map('trim', explode(',', (string) ($r['permissions'] ?? ''))));
            if ($perms === []) {
                return '<span style="color:#94a3b8;">None</span>';
            }
            return implode(' ', array_map(fn ($p) => al_badge(ucwords(str_replace('_', ' ', $p)), $p === 'full_access' ? 'blue' : 'grey'), $perms));
        }],
        ['label' => 'Users', 'value' => fn ($r) => (string) (int) $r['user_count']],
    ],
    'actions' => fn ($r) => [
        'view' => 'user-role/view_role.php?id=' . (int) $r['id'],
        'edit' => 'user-role/edit_role.php?id=' . (int) $r['id'],
    ],
], $rows);

include __DIR__ . '/../includes/footer.php';
