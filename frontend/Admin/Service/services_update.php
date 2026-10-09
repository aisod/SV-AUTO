<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT s.id, s.service_date, s.status, s.notes, v.reg_no, v.model, c.name AS client_name
        FROM services s
        LEFT JOIN vehicles v ON v.id = s.vehicle_id
        LEFT JOIN clients c ON c.id = v.client_id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (v.reg_no LIKE ? OR v.model LIKE ? OR c.name LIKE ? OR s.notes LIKE ?)';
    $params = array_fill(0, 4, '%' . $q . '%');
}
$stmt = $pdo->prepare($sql . ' ORDER BY s.service_date DESC, s.id DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['erp_resume_title'] = 'Services Update';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Services Update',
    'subtitle' => count($rows) . ' scheduled service' . (count($rows) === 1 ? '' : 's'),
    'icon' => 'fa-wrench',
    'table_id' => 'servicesTable',
    'add_url' => 'Service/add_service.php',
    'add_label' => 'Schedule Service',
    'search' => $q,
    'search_placeholder' => 'Search vehicle, client or notes...',
    'empty' => 'No services scheduled.',
    'columns' => [
        ['label' => 'Ref', 'value' => fn ($r) => 'SVC-' . str_pad((string) (int) $r['id'], 4, '0', STR_PAD_LEFT)],
        ['label' => 'Service date', 'value' => fn ($r) => al_date($r['service_date'])],
        ['label' => 'Client', 'value' => fn ($r) => (string) ($r['client_name'] ?? '-')],
        ['label' => 'Vehicle', 'value' => fn ($r) => trim((string) ($r['reg_no'] ?? '') . ' ' . (string) ($r['model'] ?? '')) ?: '-'],
        ['label' => 'Notes', 'value' => fn ($r) => (string) ($r['notes'] ?? '')],
        ['label' => 'Status', 'html' => true, 'value' => fn ($r) => al_badge(ucfirst((string) $r['status']), $r['status'] === 'approved' ? 'green' : 'amber')],
    ],
    'actions' => function ($r) {
        $a = [
            'view' => 'Service/view_service.php?id=' . (int) $r['id'],
            'edit' => 'Service/edit_service.php?id=' . (int) $r['id'],
            'print' => 'Service/print_service.php?id=' . (int) $r['id'],
        ];
        if (($r['status'] ?? '') === 'pending' && is_admin()) {
            $a['approve'] = 'Service/approve_service.php?id=' . (int) $r['id'];
            $a['approve_confirm'] = 'Approve this service?';
        }
        return $a;
    },
], $rows);

include __DIR__ . '/../includes/footer.php';
