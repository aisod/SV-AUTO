<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$business = getBusiness();
$currency = getCurrency();

$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;
$report_type = $_GET['report_type'] ?? 'productivity';

$date_filter = $date_from && $date_to ? "WHERE i.created_at BETWEEN ? AND ?" : "";
$date_params = $date_from && $date_to ? [$date_from . ' 00:00:00', $date_to . ' 23:59:59'] : [];

// Fetch data based on report type
if ($report_type === 'productivity') {
    $stmt = $pdo->prepare("
        SELECT 
            e.name AS tech_name,
            COUNT(jc.id) as total_jobs,
            COUNT(CASE WHEN jc.status IN ('complete', 'in_progress') THEN 1 END) as complete_jobs,
            COALESCE(SUM(i.amount), 0) as revenue_generated,
            COALESCE(ROUND(COUNT(CASE WHEN jc.status IN ('complete', 'in_progress') THEN 1 END) / NULLIF(COUNT(jc.id), 0) * 100, 1), 0) as efficiency
        FROM employees e
        LEFT JOIN job_cards jc ON e.id = jc.technician_id
        LEFT JOIN quotations q ON jc.id = q.job_card_id
        LEFT JOIN invoices i ON q.id = i.quotation_id
        GROUP BY e.id, e.name
        HAVING total_jobs > 0
        ORDER BY efficiency DESC, revenue_generated DESC
    ");
    $stmt->execute([]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $report_title = "Technician Productivity Report";
    $headers = ['Technician', 'Total Jobs', 'Completed', 'Revenue', 'Efficiency'];
    $columns = ['tech_name', 'total_jobs', 'complete_jobs', 'revenue_generated', 'efficiency'];
} elseif ($report_type === 'financial') {
    $stmt = $pdo->prepare("
        SELECT 
            i.id, i.amount, i.status, c.name as client_name,
            COALESCE(SUM(p.amount), 0) as paid_amount
        FROM invoices i
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN clients c ON q.client_id = c.id
        LEFT JOIN payments p ON i.id = p.invoice_id
        $date_filter
        GROUP BY i.id
        ORDER BY i.created_at DESC
    ");
    $stmt->execute($date_params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $report_title = "Financial Summary Report";
    $headers = ['Invoice', 'Client', 'Amount', 'Paid', 'Status'];
    $columns = ['id', 'client_name', 'amount', 'paid_amount', 'status'];
} elseif ($report_type === 'inventory') {
    $stmt = $pdo->query("SELECT part_name, stock, price, (stock * price) as total_value FROM inventory ORDER BY total_value DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $report_title = "Inventory Status Report";
    $headers = ['Part Name', 'Stock', 'Unit Price', 'Total Value'];
    $columns = ['part_name', 'stock', 'price', 'total_value'];
} else {
    $stmt = $pdo->prepare("
        SELECT 
            c.name as client_name,
            COUNT(i.id) as invoices_count,
            COALESCE(SUM(i.amount), 0) as total_spent
        FROM clients c
        LEFT JOIN quotations q ON c.id = q.client_id
        LEFT JOIN invoices i ON q.id = i.quotation_id
        $date_filter
        GROUP BY c.id
        HAVING invoices_count > 0
        ORDER BY total_spent DESC LIMIT 10
    ");
    $stmt->execute($date_params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $report_title = "Top Customers Report";
    $headers = ['Rank', 'Client', 'Invoices', 'Total Spent'];
    $columns = ['rank', 'client_name', 'invoices_count', 'total_spent'];
}

// Set headers for CSV download
$filename = str_replace(' ', '-', $report_title) . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Write BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write title and date range
fputcsv($output, [$business['name']]);
fputcsv($output, [$report_title]);
if ($date_from && $date_to) {
    fputcsv($output, ['Period: ' . date('d M Y', strtotime($date_from)) . ' - ' . date('d M Y', strtotime($date_to))]);
}
fputcsv($output, []); // Empty row

// Write headers
fputcsv($output, $headers);

// Write data rows
foreach ($data as $i => $row) {
    $csv_row = [];
    foreach ($columns as $col) {
        if ($col === 'rank') {
            $csv_row[] = '#' . ($i + 1);
        } elseif ($col === 'id') {
            $csv_row[] = 'INV-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT);
        } elseif (in_array($col, ['amount', 'revenue_generated', 'paid_amount', 'price', 'total_value', 'total_spent'])) {
            $csv_row[] = formatMoney($row[$col]);
        } elseif ($col === 'efficiency') {
            $csv_row[] = $row[$col] . '%';
        } elseif ($col === 'client_name' && empty($row[$col])) {
            $csv_row[] = 'Walk-in';
        } else {
            $csv_row[] = $row[$col] ?? '';
        }
    }
    fputcsv($output, $csv_row);
}

fclose($output);
exit;

