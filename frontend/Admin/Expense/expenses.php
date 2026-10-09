<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT e.id, e.description, e.amount, e.created_at, e.purchase_order_id, po.supplier_name
        FROM expenses e
        LEFT JOIN purchase_orders po ON po.id = e.purchase_order_id
        WHERE e.deleted_at IS NULL';
$params = [];
if ($q !== '') {
    $sql .= ' AND (e.description LIKE ? OR po.supplier_name LIKE ?)';
    $params = ['%' . $q . '%', '%' . $q . '%'];
}
$stmt = $pdo->prepare($sql . ' ORDER BY e.created_at DESC, e.id DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = array_sum(array_map(fn ($r) => (float) $r['amount'], $rows));

$GLOBALS['erp_resume_title'] = 'Expenses';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Expenses',
    'subtitle' => count($rows) . ' expense' . (count($rows) === 1 ? '' : 's') . ' · total ' . formatMoney($total),
    'icon' => 'fa-money-bill-wave',
    'table_id' => 'expensesTable',
    'add_url' => 'Expense/add_expense.php',
    'add_label' => 'Add Expense',
    'search' => $q,
    'search_placeholder' => 'Search description or supplier...',
    'empty' => 'No expenses recorded.',
    'columns' => [
        ['label' => 'Date', 'value' => fn ($r) => al_date($r['created_at'])],
        ['label' => 'Description', 'value' => fn ($r) => (string) ($r['description'] ?? '-')],
        ['label' => 'Supplier', 'value' => fn ($r) => (string) ($r['supplier_name'] ?? '-')],
        ['label' => 'Purchase order', 'value' => fn ($r) => $r['purchase_order_id'] ? 'PO #' . (int) $r['purchase_order_id'] : '-'],
        ['label' => 'Amount', 'value' => fn ($r) => formatMoney((float) $r['amount'])],
    ],
    'actions' => fn ($r) => [
        'edit' => 'Expense/edit_expense.php?id=' . (int) $r['id'],
        'delete' => 'Expense/delete_expense.php?id=' . (int) $r['id'],
        'delete_confirm' => 'Move this expense to the recycle bin?',
    ],
], $rows);

include __DIR__ . '/../includes/footer.php';
