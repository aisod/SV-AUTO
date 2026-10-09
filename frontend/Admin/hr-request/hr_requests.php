<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT h.id, h.type, h.details, h.status, h.submitted_at, e.name AS employee_name
        FROM hr_forms h
        LEFT JOIN employees e ON e.id = h.employee_id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (e.name LIKE ? OR h.type LIKE ? OR h.details LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%', '%' . $q . '%'];
}
$stmt = $pdo->prepare($sql . ' ORDER BY h.submitted_at DESC, h.id DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['erp_resume_title'] = 'HR Requests';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'HR Requests',
    'subtitle' => count($rows) . ' request' . (count($rows) === 1 ? '' : 's') . ' (overtime, loans, leave, warnings)',
    'icon' => 'fa-clipboard-check',
    'table_id' => 'hrRequestsTable',
    'add_url' => 'hr-request/add_hr_request.php',
    'add_label' => 'New HR Request',
    'search' => $q,
    'search_placeholder' => 'Search employee, type or details...',
    'empty' => 'No HR requests yet.',
    'columns' => [
        ['label' => 'Submitted', 'value' => fn ($r) => al_date($r['submitted_at'])],
        ['label' => 'Employee', 'value' => fn ($r) => (string) ($r['employee_name'] ?? '-')],
        ['label' => 'Type', 'value' => fn ($r) => ucfirst((string) ($r['type'] ?? '-'))],
        ['label' => 'Details', 'value' => fn ($r) => (string) ($r['details'] ?? '')],
        ['label' => 'Status', 'html' => true, 'value' => function ($r) {
            $s = (string) ($r['status'] ?? 'pending');
            return al_badge(ucfirst($s), ['approved' => 'green', 'rejected' => 'red'][$s] ?? 'amber');
        }],
    ],
], $rows);

include __DIR__ . '/../includes/footer.php';
