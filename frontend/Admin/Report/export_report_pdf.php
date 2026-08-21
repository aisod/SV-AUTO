<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$business = getBusiness();
$currency = getCurrency();

// Logo as base64
$logo_base64 = '';
$logo_path = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logo_path)) {
    $logo_data = base64_encode(file_get_contents($logo_path));
    $logo_base64 = 'data:image/jpeg;base64,' . $logo_data;
}

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
} elseif ($report_type === 'inventory') {
    $stmt = $pdo->query("SELECT part_name, stock, price, (stock * price) as total_value FROM inventory ORDER BY total_value DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $report_title = "Inventory Status Report";
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
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #4a4a4a;
        background: #fff;
        padding: 20px 25px;
        line-height: 1.4;
    }
    
    /* HEADER */
    .header {
        padding: 25px 20px;
        margin-bottom: 30px;
        border-bottom: 3px solid #F5A623;
        position: relative;
        text-align: center;
    }
    .logo {
        position: absolute;
        left: 20px;
        top: 20px;
        max-width: 100px;
        max-height: 100px;
        width: auto;
        height: auto;
    }
    .company-name {
        font-size: 28px;
        font-weight: 900;
        margin: 0;
        letter-spacing: 2px;
        color: #4a4a4a;
    }
    .clearfix {
        clear: both;
    }
    
    /* REPORT TITLE */
    .report-title-section {
        margin: 30px 0 25px 0;
        text-align: center;
    }
    .report-main-title {
        font-size: 22px;
        color: #4a4a4a;
        margin: 0;
        font-weight: 900;
        letter-spacing: 1px;
    }
    
    .date-range {
        font-size: 12px;
        margin: 8px 0 25px 0;
        color: #666;
        font-weight: 600;
        text-align: center;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    th {
        background: #F5A623;
        color: white;
        padding: 12px 10px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        border-bottom: 3px solid #1a1a1a;
    }
    td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        font-size: 10px;
        font-weight: 500;
        color: #4a4a4a;
    }
    tr:nth-child(even) {
        background: #FFF8F0;
    }
    
    /* FOOTER */
    .footer {
        background: #4a4a4a;
        color: white;
        text-align: center;
        padding: 14px;
        font-size: 9px;
        line-height: 1.7;
        margin-top: 30px;
        border-top: 3px solid #F5A623;
        font-weight: 500;
    }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <?php if ($logo_base64): ?>
        <img src="<?= $logo_base64 ?>" class="logo" alt="Logo">
    <?php endif; ?>
    <div class="company-name"><?= htmlspecialchars($business['name'] ?? 'SV Auto Services') ?></div>
</div>

<!-- REPORT TITLE -->
<div class="report-title-section">
    <div class="report-main-title"><?= $report_title ?></div>
</div>

<?php if ($date_from && $date_to): ?>
<div class="date-range">Period: <?= date('d M Y', strtotime($date_from)) ?> - <?= date('d M Y', strtotime($date_to)) ?></div>
<?php endif; ?>

<table>
<?php
// Table headers and rows based on report type
if ($report_type === 'productivity') {
    echo '<thead><tr><th>Technician</th><th>Total Jobs</th><th>Completed</th><th>Revenue</th><th>Efficiency</th></tr></thead><tbody>';
    foreach ($data as $row) {
        echo '<tr>
            <td><strong>' . htmlspecialchars($row['tech_name']) . '</strong></td>
            <td>' . $row['total_jobs'] . '</td>
            <td>' . $row['complete_jobs'] . '</td>
            <td>' . formatMoney($row['revenue_generated']) . '</td>
            <td>' . $row['efficiency'] . '%</td>
        </tr>';
    }
} elseif ($report_type === 'financial') {
    echo '<thead><tr><th>Invoice</th><th>Client</th><th>Amount</th><th>Paid</th><th>Status</th></tr></thead><tbody>';
    foreach ($data as $row) {
        echo '<tr>
            <td><strong>INV-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT) . '</strong></td>
            <td>' . htmlspecialchars($row['client_name'] ?? 'Walk-in') . '</td>
            <td>' . formatMoney($row['amount']) . '</td>
            <td>' . formatMoney($row['paid_amount']) . '</td>
            <td>' . ucfirst($row['status']) . '</td>
        </tr>';
    }
} elseif ($report_type === 'inventory') {
    echo '<thead><tr><th>Part Name</th><th>Stock</th><th>Unit Price</th><th>Total Value</th></tr></thead><tbody>';
    foreach ($data as $row) {
        echo '<tr>
            <td><strong>' . htmlspecialchars($row['part_name']) . '</strong></td>
            <td>' . $row['stock'] . '</td>
            <td>' . formatMoney($row['price']) . '</td>
            <td>' . formatMoney($row['total_value']) . '</td>
        </tr>';
    }
} else {
    echo '<thead><tr><th>Rank</th><th>Client</th><th>Invoices</th><th>Total Spent</th></tr></thead><tbody>';
    foreach ($data as $i => $row) {
        echo '<tr>
            <td><strong>#' . ($i + 1) . '</strong></td>
            <td>' . htmlspecialchars($row['client_name']) . '</td>
            <td>' . $row['invoices_count'] . '</td>
            <td>' . formatMoney($row['total_spent']) . '</td>
        </tr>';
    }
}
?>
</tbody>
</table>

<!-- FOOTER -->
<div class="footer">
    <strong><?= htmlspecialchars($business['name']) ?></strong><br>
    <?= htmlspecialchars($business['address']) ?> • <?= htmlspecialchars($business['phone']) ?> • <?= htmlspecialchars($business['email']) ?>
    <?php if (!empty($business['tax_number'])): ?> • VAT: <?= htmlspecialchars($business['tax_number']) ?><?php endif; ?><br>
    Generated on <?= date('d M Y H:i') ?>
</div>

</body>
</html>
<?php
$html = ob_get_clean();

// Generate PDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = str_replace(' ', '-', $report_title) . '-' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);

