<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

// Same report types, parameters and queries as export_report_pdf.php / export_report_excel.php.
$reportTypes = [
    'productivity' => 'Technician productivity',
    'financial'    => 'Financial summary',
    'inventory'    => 'Inventory status',
    'customers'    => 'Top customers',
];
$reportType = (string) ($_GET['report_type'] ?? 'productivity');
if (!isset($reportTypes[$reportType])) {
    $reportType = 'productivity';
}
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_from'] ?? '')) ? (string) $_GET['date_from'] : '';
$dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_to'] ?? '')) ? (string) $_GET['date_to'] : '';

$dateFilter = ($dateFrom && $dateTo) ? 'WHERE i.created_at BETWEEN ? AND ?' : '';
$dateParams = ($dateFrom && $dateTo) ? [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'] : [];

$rows = [];
$columns = [];
$error = '';
try {
    if ($reportType === 'productivity') {
        $rows = $pdo->query("
            SELECT e.name AS tech_name,
                   COUNT(jc.id) AS total_jobs,
                   COUNT(CASE WHEN jc.status IN ('complete','in_progress') THEN 1 END) AS complete_jobs,
                   COALESCE(SUM(i.amount), 0) AS revenue_generated,
                   COALESCE(ROUND(COUNT(CASE WHEN jc.status IN ('complete','in_progress') THEN 1 END) / NULLIF(COUNT(jc.id), 0) * 100, 1), 0) AS efficiency
            FROM employees e
            LEFT JOIN job_cards jc ON e.id = jc.technician_id
            LEFT JOIN quotations q ON jc.id = q.job_card_id
            LEFT JOIN invoices i ON q.id = i.quotation_id
            GROUP BY e.id, e.name
            HAVING total_jobs > 0
            ORDER BY efficiency DESC, revenue_generated DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        $columns = [
            ['label' => 'Technician', 'value' => fn ($r) => $r['tech_name']],
            ['label' => 'Total jobs', 'value' => fn ($r) => (string) $r['total_jobs']],
            ['label' => 'Completed / in progress', 'value' => fn ($r) => (string) $r['complete_jobs']],
            ['label' => 'Efficiency', 'value' => fn ($r) => $r['efficiency'] . '%'],
            ['label' => 'Revenue', 'value' => fn ($r) => formatMoney((float) $r['revenue_generated'])],
        ];
    } elseif ($reportType === 'financial') {
        $stmt = $pdo->prepare("
            SELECT i.id, i.amount, i.status, c.name AS client_name, COALESCE(SUM(p.amount), 0) AS paid_amount
            FROM invoices i
            LEFT JOIN quotations q ON i.quotation_id = q.id
            LEFT JOIN clients c ON q.client_id = c.id
            LEFT JOIN payments p ON i.id = p.invoice_id
            $dateFilter
            GROUP BY i.id
            ORDER BY i.created_at DESC
        ");
        $stmt->execute($dateParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = [
            ['label' => 'Invoice', 'value' => fn ($r) => '#' . (int) $r['id']],
            ['label' => 'Client', 'value' => fn ($r) => (string) ($r['client_name'] ?? 'Walk-in')],
            ['label' => 'Amount', 'value' => fn ($r) => formatMoney((float) $r['amount'])],
            ['label' => 'Paid', 'value' => fn ($r) => formatMoney((float) $r['paid_amount'])],
            ['label' => 'Status', 'html' => true, 'value' => fn ($r) => al_badge(ucfirst((string) $r['status']), $r['status'] === 'paid' ? 'green' : 'amber')],
        ];
    } elseif ($reportType === 'inventory') {
        $rows = $pdo->query('SELECT part_name, stock, price, (stock * price) AS total_value FROM inventory WHERE deleted_at IS NULL ORDER BY total_value DESC')
            ->fetchAll(PDO::FETCH_ASSOC);
        $columns = [
            ['label' => 'Part', 'value' => fn ($r) => $r['part_name']],
            ['label' => 'In stock', 'value' => fn ($r) => (string) $r['stock']],
            ['label' => 'Unit price', 'value' => fn ($r) => formatMoney((float) $r['price'])],
            ['label' => 'Total value', 'value' => fn ($r) => formatMoney((float) $r['total_value'])],
        ];
    } else {
        $stmt = $pdo->prepare("
            SELECT c.name AS client_name, COUNT(i.id) AS invoices_count, COALESCE(SUM(i.amount), 0) AS total_spent
            FROM clients c
            LEFT JOIN quotations q ON c.id = q.client_id
            LEFT JOIN invoices i ON q.id = i.quotation_id
            $dateFilter
            GROUP BY c.id
            HAVING invoices_count > 0
            ORDER BY total_spent DESC LIMIT 10
        ");
        $stmt->execute($dateParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = [
            ['label' => 'Client', 'value' => fn ($r) => $r['client_name']],
            ['label' => 'Invoices', 'value' => fn ($r) => (string) $r['invoices_count']],
            ['label' => 'Total spent', 'value' => fn ($r) => formatMoney((float) $r['total_spent'])],
        ];
    }
} catch (Throwable $e) {
    error_log('reports.php: ' . $e->getMessage());
    $error = 'This report could not be generated. Please try again or contact support.';
}

$exportQuery = http_build_query(['report_type' => $reportType, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
$GLOBALS['erp_resume_title'] = 'Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="erp-card erp-mt-2">
    <div class="erp-card-body">
        <form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;align-items:end;">
            <div>
                <label class="erp-label" for="rp-type">Report</label>
                <select id="rp-type" name="report_type" class="erp-input">
                    <?php foreach ($reportTypes as $key => $label): ?>
                        <option value="<?php echo al_h($key); ?>"<?php echo $key === $reportType ? ' selected' : ''; ?>><?php echo al_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="erp-label" for="rp-from">From</label>
                <input type="date" id="rp-from" name="date_from" class="erp-input" value="<?php echo al_h($dateFrom); ?>">
            </div>
            <div>
                <label class="erp-label" for="rp-to">To</label>
                <input type="date" id="rp-to" name="date_to" class="erp-input" value="<?php echo al_h($dateTo); ?>">
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="erp-btn erp-btn-primary"><i class="fas fa-filter"></i> Run</button>
                <a href="Report/export_report_excel.php?<?php echo al_h($exportQuery); ?>" class="erp-btn erp-btn-secondary"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="Report/export_report_pdf.php?<?php echo al_h($exportQuery); ?>" target="_blank" rel="noopener" class="erp-btn erp-btn-secondary"><i class="fas fa-file-pdf"></i> PDF</a>
            </div>
        </form>
        <?php if (in_array($reportType, ['productivity', 'inventory'], true)): ?>
            <p style="margin:10px 0 0;color:#64748b;font-size:13px;">This report covers all records, so the date range does not apply.</p>
        <?php endif; ?>
    </div>
</div>

<?php
al_render_list([
    'title' => $reportTypes[$reportType],
    'subtitle' => ($dateFrom && $dateTo && !in_array($reportType, ['productivity', 'inventory'], true))
        ? al_date($dateFrom) . ' to ' . al_date($dateTo)
        : 'All records',
    'icon' => 'fa-chart-bar',
    'table_id' => 'reportTable',
    'search' => '',
    'no_search' => true,
    'empty' => $error !== '' ? $error : 'No data for this report.',
    'columns' => $columns ?: [['label' => 'Report', 'value' => fn ($r) => '']],
    'keep_params' => ['report_type' => $reportType, 'date_from' => $dateFrom, 'date_to' => $dateTo],
], $rows);

include __DIR__ . '/../includes/footer.php';
