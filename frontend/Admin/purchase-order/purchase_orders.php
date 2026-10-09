<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT po.id, po.supplier_name, po.amount, po.status, po.created_at, jc.card_number
        FROM purchase_orders po
        LEFT JOIN job_cards jc ON jc.id = po.job_card_id
        WHERE po.deleted_at IS NULL';
$params = [];
if ($q !== '') {
    $sql .= ' AND (po.supplier_name LIKE ? OR jc.card_number LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
$stmt = $pdo->prepare($sql . ' ORDER BY po.created_at DESC, po.id DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['erp_resume_title'] = 'Purchase Orders';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Purchase Orders',
    'subtitle' => count($rows) . ' purchase order' . (count($rows) === 1 ? '' : 's'),
    'icon' => 'fa-shopping-cart',
    'table_id' => 'purchaseOrdersTable',
    'add_url' => 'purchase-order/add_purchase_order.php',
    'add_label' => 'New Purchase Order',
    'search' => $q,
    'search_placeholder' => 'Search supplier or job card...',
    'empty' => 'No purchase orders yet.',
    'columns' => [
        ['label' => 'PO #', 'value' => fn ($r) => 'PO-' . str_pad((string) (int) $r['id'], 4, '0', STR_PAD_LEFT)],
        ['label' => 'Date', 'value' => fn ($r) => al_date($r['created_at'])],
        ['label' => 'Supplier', 'value' => fn ($r) => (string) ($r['supplier_name'] ?? '-')],
        ['label' => 'Job card', 'value' => fn ($r) => (string) ($r['card_number'] ?? '-')],
        ['label' => 'Amount', 'value' => fn ($r) => formatMoney((float) $r['amount'])],
        ['label' => 'Status', 'html' => true, 'value' => fn ($r) => al_badge(ucfirst((string) $r['status']), $r['status'] === 'approved' ? 'green' : 'amber')],
    ],
    'actions' => function ($r) {
        $a = [
            'view' => 'purchase-order/view_purchase_order.php?id=' . (int) $r['id'],
            'print' => 'purchase-order/print_purchase_order.php?id=' . (int) $r['id'],
        ];
        if (($r['status'] ?? '') === 'pending' && is_admin()) {
            $a['approve'] = 'purchase-order/approve_purchase_order.php?id=' . (int) $r['id'];
            $a['approve_confirm'] = 'Approve this purchase order?';
        }
        return $a;
    },
], $rows);

include __DIR__ . '/../includes/footer.php';
