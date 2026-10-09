<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, part_name, stock, price FROM inventory WHERE deleted_at IS NULL';
$params = [];
if ($q !== '') {
    $sql .= ' AND part_name LIKE ?';
    $params[] = '%' . $q . '%';
}
$stmt = $pdo->prepare($sql . ' ORDER BY part_name ASC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['erp_resume_title'] = 'Inventory';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Inventory',
    'subtitle' => count($rows) . ' part' . (count($rows) === 1 ? '' : 's') . ' in stock records',
    'icon' => 'fa-boxes',
    'table_id' => 'inventoryTable',
    'add_url' => 'Inventory/add_inventory.php',
    'add_label' => 'Add Part',
    'search' => $q,
    'search_placeholder' => 'Search parts...',
    'empty' => 'No parts found.',
    'columns' => [
        ['label' => 'Part', 'value' => fn ($r) => $r['part_name']],
        ['label' => 'In stock', 'value' => fn ($r) => (string) (int) $r['stock']],
        ['label' => 'Unit price', 'value' => fn ($r) => formatMoney((float) $r['price'])],
        ['label' => 'Stock value', 'value' => fn ($r) => formatMoney((int) $r['stock'] * (float) $r['price'])],
        ['label' => 'Status', 'html' => true, 'value' => function ($r) {
            $s = (int) $r['stock'];
            if ($s === 0) { return al_badge('Out of stock', 'red'); }
            if ($s < 10)  { return al_badge('Critical', 'amber'); }
            if ($s < 25)  { return al_badge('Low stock', 'amber'); }
            return al_badge('In stock', 'green');
        }],
    ],
    'actions' => fn ($r) => [
        'view' => 'Inventory/view_inventory.php?id=' . (int) $r['id'],
        'edit' => 'Inventory/edit_inventory.php?id=' . (int) $r['id'],
        'delete' => 'Inventory/delete_inventory.php?id=' . (int) $r['id'],
        'delete_confirm' => 'Move "' . $r['part_name'] . '" to the recycle bin? (Only works when stock is 0.)',
    ],
], $rows);

include __DIR__ . '/../includes/footer.php';
