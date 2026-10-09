<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, name, position, status FROM employees WHERE deleted_at IS NULL';
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR position LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
$stmt = $pdo->prepare($sql . ' ORDER BY name ASC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['erp_resume_title'] = 'Employees';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Employees',
    'subtitle' => count($rows) . ' team member' . (count($rows) === 1 ? '' : 's'),
    'icon' => 'fa-users',
    'table_id' => 'employeesTable',
    'add_url' => 'Employee/add_employee.php',
    'add_label' => 'Add Employee',
    'search' => $q,
    'search_placeholder' => 'Search name or position...',
    'empty' => 'No employees found.',
    'columns' => [
        ['label' => 'Name', 'value' => fn ($r) => $r['name']],
        ['label' => 'Position', 'value' => fn ($r) => (string) ($r['position'] ?? '-')],
        ['label' => 'Status', 'html' => true, 'value' => function ($r) {
            $s = (string) ($r['status'] ?? 'active');
            $tone = ['active' => 'green', 'on_leave' => 'amber', 'on_field' => 'blue'][$s] ?? 'grey';
            return al_badge(ucwords(str_replace('_', ' ', $s)), $tone);
        }],
    ],
    'actions' => fn ($r) => [
        'view' => 'Employee/view_employee.php?id=' . (int) $r['id'],
        'edit' => 'Employee/edit_employee.php?id=' . (int) $r['id'],
        'print' => 'Employee/print_employee.php?id=' . (int) $r['id'],
        'delete' => 'Employee/delete_employee.php?id=' . (int) $r['id'],
        'delete_confirm' => 'Move ' . $r['name'] . ' to the recycle bin?',
    ],
], $rows);

include __DIR__ . '/../includes/footer.php';
