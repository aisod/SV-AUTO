<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
if (!in_array($role, ['admin', 'manager'], true)) {
    echo json_encode([]);
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') {
    echo json_encode([]);
    exit;
}

$term = '%' . $q . '%';
$results = [];

try {
    $stmt = $pdo->prepare("
        SELECT jc.id, jc.card_number,
               COALESCE(c.name, 'Walk-in') AS client_name,
               v.reg_no
        FROM job_cards jc
        LEFT JOIN clients c ON jc.client_id = c.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        WHERE jc.deleted_at IS NULL
          AND (jc.card_number LIKE ? OR c.name LIKE ? OR v.reg_no LIKE ?)
        LIMIT 8
    ");
    $stmt->execute([$term, $term, $term]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'Job Card',
            'title' => (string) $row['card_number'],
            'subtitle' => (string) $row['client_name'] . (!empty($row['reg_no']) ? ' · ' . $row['reg_no'] : ''),
            'url' => 'JobCard/view_job_card.php?id=' . (int) $row['id'],
            'icon' => 'fas fa-tools',
        ];
    }

    $stmt = $pdo->prepare("
        SELECT id, name, email, phone
        FROM clients
        WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?
        LIMIT 8
    ");
    $stmt->execute([$term, $term, $term]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'Client',
            'title' => (string) $row['name'],
            'subtitle' => trim((string) ($row['email'] ?? '') . ($row['phone'] ? ' · ' . $row['phone'] : '')),
            'url' => 'Client/clients.php?search=' . urlencode((string) $row['name']),
            'icon' => 'fas fa-user',
        ];
    }

    $stmt = $pdo->prepare("
        SELECT i.id, i.invoice_number, COALESCE(c.name, '—') AS client_name,
               i.amount, i.status_paid
        FROM invoices i
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE (i.deleted_at IS NULL OR i.deleted_at = '')
          AND (i.invoice_number LIKE ? OR c.name LIKE ? OR i.status_paid LIKE ? OR CAST(i.id AS CHAR) LIKE ?)
        LIMIT 8
    ");
    $stmt->execute([$term, $term, $term, $term]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $invLabel = trim((string) ($row['invoice_number'] ?? ''));
        if ($invLabel === '') {
            $invLabel = 'INV-' . str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT);
        }
        $results[] = [
            'type' => 'Invoice',
            'title' => $invLabel,
            'subtitle' => (string) $row['client_name'] . ' · N$' . number_format((float) $row['amount'], 2)
                . ' · ' . ucfirst((string) ($row['status_paid'] ?? 'unpaid')),
            'url' => 'Invoice/view_invoice.php?id=' . (int) $row['id'],
            'icon' => 'fas fa-receipt',
        ];
    }

    $stmt = $pdo->prepare("
        SELECT q.id, COALESCE(c.name, '—') AS client_name, q.amount, q.status
        FROM quotations q
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE q.deleted_at IS NULL
          AND (c.name LIKE ? OR q.status LIKE ? OR CAST(q.id AS CHAR) LIKE ?)
        LIMIT 8
    ");
    $stmt->execute([$term, $term, $term]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'type' => 'Quotation',
            'title' => 'QTN-' . str_pad((string) $row['id'], 5, '0', STR_PAD_LEFT),
            'subtitle' => (string) $row['client_name'] . ' · N$' . number_format((float) $row['amount'], 2)
                . ' · ' . ucfirst((string) ($row['status'] ?? '')),
            'url' => 'Quotation/add_quotation.php?id=' . (int) $row['id'],
            'icon' => 'fas fa-file-invoice',
        ];
    }

    if ($role === 'admin') {
        $stmt = $pdo->prepare("
            SELECT id, name, position, status
            FROM employees
            WHERE name LIKE ? OR position LIKE ?
            LIMIT 5
        ");
        $stmt->execute([$term, $term]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'Employee',
                'title' => (string) $row['name'],
                'subtitle' => (string) $row['position'] . ' · ' . ucfirst(str_replace('_', ' ', (string) $row['status'])),
                'url' => 'HR/view_employee.php?id=' . (int) $row['id'],
                'icon' => 'fas fa-user-tie',
            ];
        }

        $stmt = $pdo->prepare("
            SELECT id, part_name, stock, price
            FROM inventory
            WHERE part_name LIKE ?
            LIMIT 5
        ");
        $stmt->execute([$term]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $results[] = [
                'type' => 'Inventory',
                'title' => (string) $row['part_name'],
                'subtitle' => 'Stock: ' . (string) $row['stock'] . ' · N$' . number_format((float) $row['price'], 2),
                'url' => 'Inventory/inventory.php?search=' . urlencode((string) $row['part_name']),
                'icon' => 'fas fa-boxes',
            ];
        }
    }
} catch (Throwable $e) {
    error_log('Admin search: ' . $e->getMessage());
    echo json_encode(['error' => 'Search failed']);
    exit;
}

echo json_encode($results);
