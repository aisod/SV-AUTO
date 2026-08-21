<?php
declare(strict_types=1);

/**
 * Keep linked invoice header totals in sync when a quotation is saved or updated.
 */
if (!function_exists('qt_parse_quotation_details')) {
    require_once __DIR__ . '/quotation_paper_signoff.inc.php';
}

/**
 * Copy quotation totals onto active invoice(s) for this quotation_id.
 *
 * @return int Number of invoice rows updated
 */
function aq_sync_linked_invoice_totals(PDO $pdo, int $quotationId, ?float $grandTotal = null, ?float $vatAmount = null): int
{
    if ($quotationId <= 0) {
        return 0;
    }

    if ($grandTotal === null || $vatAmount === null) {
        $qStmt = $pdo->prepare('SELECT amount, details FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $qStmt->execute([$quotationId]);
        $qRow = $qStmt->fetch(PDO::FETCH_ASSOC);
        if (!$qRow) {
            return 0;
        }

        $payload = qt_parse_quotation_details((string) ($qRow['details'] ?? ''));
        $totals = (is_array($payload) && isset($payload['totals']) && is_array($payload['totals']))
            ? $payload['totals']
            : [];

        if ($grandTotal === null) {
            $grandTotal = (float) ($totals['grand_total'] ?? $qRow['amount'] ?? 0);
        }
        if ($vatAmount === null) {
            $vatAmount = (float) ($totals['vat_amount'] ?? 0);
        }
    }

    $grandTotal = round(max(0, $grandTotal), 2);
    $vatAmount = round(max(0, $vatAmount), 2);

    static $invoiceCols = null;
    if ($invoiceCols === null) {
        $invoiceCols = [];
        try {
            $colsStmt = $pdo->query('SHOW COLUMNS FROM invoices');
            while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
                $invoiceCols[(string) ($col['Field'] ?? '')] = true;
            }
        } catch (Throwable $e) {
            return 0;
        }
    }

    if ($invoiceCols === [] || empty($invoiceCols['quotation_id'])) {
        return 0;
    }

    $setParts = [];
    $params = [];
    if (!empty($invoiceCols['amount'])) {
        $setParts[] = '`amount` = ?';
        $params[] = $grandTotal;
    }
    if (!empty($invoiceCols['vat_amount'])) {
        $setParts[] = '`vat_amount` = ?';
        $params[] = $vatAmount;
    }
    if (!empty($invoiceCols['updated_at'])) {
        $setParts[] = '`updated_at` = NOW()';
    }
    if ($setParts === []) {
        return 0;
    }

    $where = 'quotation_id = ?';
    $params[] = $quotationId;
    if (!empty($invoiceCols['deleted_at'])) {
        $where .= ' AND deleted_at IS NULL';
    }

    $sql = 'UPDATE invoices SET ' . implode(', ', $setParts) . ' WHERE ' . $where;
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    return (int) $upd->rowCount();
}
