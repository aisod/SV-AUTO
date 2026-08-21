<?php
/**
 * Bulk restore / permanent delete for recycle bin (included from recycle_bin.php).
 */

$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$tab = strtolower(trim((string) ($_POST['tab'] ?? 'all')));
$itemsRaw = $_POST['items'] ?? [];
if (!is_array($itemsRaw)) {
    $itemsRaw = [];
}

$entityMap = [
    'job_cards' => ['table' => 'job_cards', 'audit_restore' => 'restored_job_card', 'audit_entity' => 'job_card'],
    'quotations' => ['table' => 'quotations', 'audit_restore' => 'restored_quotation', 'audit_entity' => 'quotation'],
    'expenses' => ['table' => 'expenses', 'audit_restore' => 'restored_expense', 'audit_entity' => 'expense'],
    'employees' => ['table' => 'employees', 'audit_restore' => 'restored_employee', 'audit_entity' => 'employee'],
    'inventory' => ['table' => 'inventory', 'audit_restore' => 'restored_inventory', 'audit_entity' => 'inventory'],
    'statutory' => ['table' => 'statutory_docs', 'audit_restore' => 'restored_statutory', 'audit_entity' => 'statutory_doc'],
];

$grouped = [];
foreach ($itemsRaw as $token) {
    $token = trim((string) $token);
    if ($token === '' || strpos($token, ':') === false) {
        continue;
    }
    [$type, $idStr] = explode(':', $token, 2);
    $type = strtolower(trim($type));
    $id = (int) $idStr;
    if ($id <= 0 || !isset($entityMap[$type])) {
        continue;
    }
    $grouped[$type][$id] = $id;
}

$rb_redirect = static function (string $query = '') use ($tab): void {
    $parts = [];
    if ($tab !== 'all') {
        $parts[] = 'tab=' . rawurlencode($tab);
    }
    if ($query !== '') {
        $parts[] = ltrim($query, '&?');
    }
    $qs = $parts !== [] ? '?' . implode('&', $parts) : '';
    header('Location: recycle_bin.php' . $qs);
    exit;
};

if ($grouped === [] || !in_array($action, ['restore', 'purge'], true)) {
    $rb_redirect('error=' . urlencode('No items selected'));
}

$userId = (int) $_SESSION['user_id'];
$restored = 0;
$purged = 0;

function rb_ids_placeholder(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
    if ($ids === []) {
        return [[], ''];
    }
    return [$ids, implode(',', array_fill(0, count($ids), '?'))];
}

function rb_safe_exec(PDO $pdo, string $sql, array $params = []): void
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } catch (Throwable $e) {
        error_log('Recycle bin SQL skipped: ' . $e->getMessage() . ' | ' . $sql);
    }
}

function rb_delete_invoices_for_quotations(PDO $pdo, array $quotationIds): void
{
    [$quotationIds, $qPh] = rb_ids_placeholder($quotationIds);
    if ($qPh === '') {
        return;
    }
    $invStmt = $pdo->prepare("SELECT id FROM invoices WHERE quotation_id IN ($qPh)");
    $invStmt->execute($quotationIds);
    $invoiceIds = $invStmt->fetchAll(PDO::FETCH_COLUMN);
    [$invoiceIds, $iPh] = rb_ids_placeholder($invoiceIds);
    if ($iPh === '') {
        return;
    }
    rb_safe_exec($pdo, "DELETE FROM payments WHERE invoice_id IN ($iPh)", $invoiceIds);
    rb_safe_exec($pdo, "DELETE FROM invoice_items WHERE invoice_id IN ($iPh)", $invoiceIds);
    rb_safe_exec($pdo, "DELETE FROM invoices WHERE id IN ($iPh)", $invoiceIds);
}

function rb_purge_quotation_ids(PDO $pdo, array $ids, bool $recycleOnly = true): int
{
    [$ids, $ph] = rb_ids_placeholder($ids);
    if ($ph === '') {
        return 0;
    }
    rb_delete_invoices_for_quotations($pdo, $ids);
    $sql = "DELETE FROM quotations WHERE id IN ($ph)";
    if ($recycleOnly) {
        $sql .= ' AND deleted_at IS NOT NULL';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    return $stmt->rowCount();
}

function rb_purge_job_cards(PDO $pdo, array $ids): int
{
    [$ids, $ph] = rb_ids_placeholder($ids);
    if ($ph === '') {
        return 0;
    }
    $qStmt = $pdo->prepare("SELECT id FROM quotations WHERE job_card_id IN ($ph)");
    $qStmt->execute($ids);
    $linkedQuoteIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($linkedQuoteIds !== []) {
        rb_purge_quotation_ids($pdo, $linkedQuoteIds, false);
    }
    $poStmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE job_card_id IN ($ph)");
    $poStmt->execute($ids);
    $poIds = $poStmt->fetchAll(PDO::FETCH_COLUMN);
    [$poIds, $poPh] = rb_ids_placeholder($poIds);
    if ($poPh !== '') {
        rb_safe_exec($pdo, "DELETE FROM expenses WHERE purchase_order_id IN ($poPh)", $poIds);
        rb_safe_exec($pdo, "DELETE FROM purchase_orders WHERE id IN ($poPh)", $poIds);
    }
    rb_safe_exec($pdo, "DELETE FROM service_updates WHERE job_card_id IN ($ph)", $ids);
    $stmt = $pdo->prepare("DELETE FROM job_cards WHERE id IN ($ph) AND deleted_at IS NOT NULL");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

function rb_purge_simple(PDO $pdo, string $table, array $ids): int
{
    [$ids, $ph] = rb_ids_placeholder($ids);
    if ($ph === '') {
        return 0;
    }
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id IN ($ph) AND deleted_at IS NOT NULL");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

function rb_audit_restore(PDO $pdo, int $userId, array $cfg, array $idList): void
{
    try {
        $log = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)');
        foreach ($idList as $eid) {
            $log->execute([$userId, $cfg['audit_restore'], $cfg['audit_entity'], $eid]);
        }
    } catch (Throwable $e) {
    }
}

try {
    if ($action === 'restore') {
        foreach ($grouped as $type => $ids) {
            $cfg = $entityMap[$type];
            $table = $cfg['table'];
            [$idList, $ph] = rb_ids_placeholder(array_values($ids));
            if ($ph === '') {
                continue;
            }
            $stmt = $pdo->prepare("UPDATE {$table} SET deleted_at = NULL WHERE id IN ($ph) AND deleted_at IS NOT NULL");
            $stmt->execute($idList);
            $restored += $stmt->rowCount();
            rb_audit_restore($pdo, $userId, $cfg, $idList);
        }
    } else {
        foreach (['quotations', 'expenses', 'employees', 'inventory', 'statutory', 'job_cards'] as $type) {
            if (empty($grouped[$type])) {
                continue;
            }
            $idList = array_values($grouped[$type]);
            if ($type === 'quotations') {
                $purged += rb_purge_quotation_ids($pdo, $idList, true);
            } elseif ($type === 'job_cards') {
                $purged += rb_purge_job_cards($pdo, $idList);
            } elseif ($type === 'employees') {
                [$ids, $ph] = rb_ids_placeholder($idList);
                if ($ph !== '') {
                    $sel = $pdo->prepare("SELECT id, photo_url FROM employees WHERE id IN ($ph) AND deleted_at IS NOT NULL");
                    $sel->execute($ids);
                    while ($row = $sel->fetch(PDO::FETCH_ASSOC)) {
                        if (!empty($row['photo_url'])) {
                            $photoPath = __DIR__ . '/../' . ltrim((string) $row['photo_url'], '/');
                            if (is_file($photoPath)) {
                                @unlink($photoPath);
                            }
                        }
                    }
                }
                $purged += rb_purge_simple($pdo, 'employees', $idList);
            } else {
                $purged += rb_purge_simple($pdo, $entityMap[$type]['table'], $idList);
            }
        }
    }
} catch (Throwable $e) {
    error_log('Recycle bin bulk action failed: ' . $e->getMessage());
    $rb_redirect('error=' . urlencode('Operation failed. Please try again.'));
}

$count = $action === 'restore' ? $restored : $purged;
if ($count > 0) {
    $msg = $action === 'restore'
        ? ($count === 1 ? '1 item restored successfully' : $count . ' items restored successfully')
        : ($count === 1 ? '1 item permanently deleted' : $count . ' items permanently deleted');
    $rb_redirect('success=' . urlencode($msg));
}

$rb_redirect('error=' . urlencode('No matching items were updated'));
