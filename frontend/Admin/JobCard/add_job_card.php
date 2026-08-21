<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/user_drafts.php';
require_once __DIR__ . '/jc_form_helpers.inc.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$jc_erp_script_parts = array_values(array_filter(explode('/', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')))));
$jc_erp_admin_idx = array_search('Admin', $jc_erp_script_parts, true);
$jc_admin_web_base = ($jc_erp_admin_idx !== false)
    ? '/' . implode('/', array_slice($jc_erp_script_parts, 0, $jc_erp_admin_idx + 1)) . '/'
    : '/';
$jc_form_action = $jc_admin_web_base . 'JobCard/create_job_card.php';

if (isset($_GET['check_card'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $checkCard = trim((string) $_GET['check_card']);
    if (!preg_match('/^\d{4}$/', $checkCard)) {
        echo json_encode(['exists' => false, 'edit_id' => null]);
        exit;
    }
    $checkStmt = $pdo->prepare('SELECT id FROM job_cards WHERE card_number = ? AND deleted_at IS NULL LIMIT 1');
    $checkStmt->execute([$checkCard]);
    $existingId = (int) $checkStmt->fetchColumn();
    echo json_encode([
        'exists' => $existingId > 0,
        'edit_id' => $existingId > 0 ? $existingId : null,
        'edit_url' => $existingId > 0
            ? $jc_admin_web_base . 'JobCard/add_job_card.php?edit_id=' . $existingId
            : null,
    ]);
    exit;
}

$dup_flash = jc_consume_duplicate_flash();

/* Card number comes from the physical job card — admin/tech enters it in the form (not auto-generated). */

$clients = $pdo->query("SELECT id, name, phone, email, address FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$vehicles = $pdo->query("
    SELECT v.id, v.reg_no, v.model, v.fiscal_no, v.vin_no, 
           CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) AS client_name 
    FROM vehicles v 
    LEFT JOIN clients c ON v.client_id = c.id 
    WHERE v.deleted_at IS NULL
    ORDER BY v.reg_no ASC
")->fetchAll(PDO::FETCH_ASSOC);
$technicians = $pdo->query("
    SELECT id, name FROM employees 
    WHERE position = 'Technician'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);
$all_employees = $pdo->query("SELECT id, name FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$business = $pdo->query("SELECT * FROM business LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$edit_id = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;
$quote_id = isset($_GET['quote_id']) ? (int)$_GET['quote_id'] : 0;
$edit_payload = null;
if ($edit_id > 0) {
    $editStmt = $pdo->prepare("
        SELECT jc.*,
               COALESCE(jc_client.name, v_client.name, '') AS client_name,
               COALESCE(jc_client.phone, v_client.phone, '') AS client_phone,
               COALESCE(jc_client.email, v_client.email, '') AS client_email,
               COALESCE(v.reg_no, '') AS reg_no,
               COALESCE(v.model, '') AS vehicle_model
        FROM job_cards jc
        LEFT JOIN clients jc_client ON jc.client_id = jc_client.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        LEFT JOIN clients v_client ON v.client_id = v_client.id
        WHERE jc.id = :id AND jc.deleted_at IS NULL
        LIMIT 1
    ");
    $editStmt->execute([':id' => $edit_id]);
    $editJob = $editStmt->fetch(PDO::FETCH_ASSOC);
    if ($editJob) {
        $extra = json_decode($editJob['extra_data'] ?? '{}', true);
        if (!is_array($extra)) $extra = [];
        $edit_payload = [
            'id' => (int)$editJob['id'],
            'card_number' => (string)($editJob['card_number'] ?? ''),
            'client_id' => (string)($editJob['client_id'] ?? ''),
            'client_name_display' => (string)($extra['client_name_display'] ?? $editJob['client_name'] ?? ''),
            'contact_no' => (string)($extra['contact_no'] ?? $editJob['client_phone'] ?? ''),
            'contact_email' => (string)($extra['contact_email'] ?? $editJob['client_email'] ?? ''),
            'to_line1' => (string)($extra['to_line1'] ?? $extra['contact_line1'] ?? $extra['contact_email'] ?? ''),
            'to_line2' => (string)($extra['to_line2'] ?? $extra['contact_line2'] ?? ''),
            'to_line3' => (string)($extra['to_line3'] ?? $extra['email_line1'] ?? ''),
            'contact_person' => (string)($extra['contact_person'] ?? ''),
            'job_date' => (string)($extra['job_date'] ?? (!empty($editJob['created_at']) ? date('Y-m-d', strtotime($editJob['created_at'])) : '')),
            'vin_no' => (string)($extra['vin_no'] ?? ''),
            'kilometre' => (string)($extra['kilometre'] ?? ''),
            'fleet_no' => (string)($extra['fleet_no'] ?? ''),
            'vehicle_id' => (string)($editJob['vehicle_id'] ?? ''),
            'manual_vehicle_reg' => (string)($extra['manual_vehicle_reg'] ?? $editJob['reg_no'] ?? ''),
            'model_reg' => (string)($extra['model_reg'] ?? $editJob['vehicle_model'] ?? ''),
            'purchase_order_no' => (string)($extra['purchase_order_no'] ?? ''),
            'quotation_no' => (string)($extra['quotation_no'] ?? ''),
            'invoice_no' => (string)($extra['invoice_no'] ?? ''),
            'technician_no' => (string)($extra['technician_no'] ?? ''),
            'customer_sig' => (string)($extra['customer_sig'] ?? ''),
            'rate_normal' => (string)($extra['rate_normal'] ?? ''),
            'rate_after' => (string)($extra['rate_after'] ?? ''),
            'rate_holiday' => (string)($extra['rate_holiday'] ?? ''),
            'vat_rate' => (string)($extra['vat_rate'] ?? ''),
            'general_header_labels' => jc_general_header_labels_from_extra($extra),
            'attend_to_service_label' => (string)($extra['attend_to_service_label'] ?? ''),
            'diagnostic_label' => (string)($extra['diagnostic_label'] ?? ''),
            'show_normal_time' => (bool)($extra['show_normal_time'] ?? true),
            'show_overtime' => (bool)($extra['show_overtime'] ?? true),
            'show_public_holiday' => (bool)($extra['show_public_holiday'] ?? true),
            'blank_normal_lines' => (string)($extra['blank_normal_lines'] ?? ''),
            'blank_overtime_lines' => (string)($extra['blank_overtime_lines'] ?? ''),
            'blank_holiday_lines' => (string)($extra['blank_holiday_lines'] ?? ''),
            'blank_parts_lines' => (string)($extra['blank_parts_lines'] ?? ''),
            'blank_cons_lines' => (string)($extra['blank_cons_lines'] ?? ''),
            'print_blank_show_labour' => (bool)($extra['print_blank_show_labour'] ?? true),
            'print_blank_show_parts' => (bool)($extra['print_blank_show_parts'] ?? true),
            'blank_col_lab_hours' => (bool)($extra['blank_col_lab_hours'] ?? true),
            'blank_col_lab_rate' => (bool)($extra['blank_col_lab_rate'] ?? true),
            'blank_col_lab_total' => (bool)($extra['blank_col_lab_total'] ?? true),
            'blank_col_part_qty' => (bool)($extra['blank_col_part_qty'] ?? true),
            'blank_col_part_cost' => (bool)($extra['blank_col_part_cost'] ?? true),
            'blank_col_part_total' => (bool)($extra['blank_col_part_total'] ?? true),
            'labour_print' => (array)($extra['labour_print'] ?? []),
            'labour_desc' => (array)($extra['labour_desc'] ?? []),
            'labour_hours' => (array)($extra['labour_hours'] ?? []),
            'labour_rate' => (array)($extra['labour_rate'] ?? []),
            'labour_total' => (array)($extra['labour_total'] ?? []),
            'parts_print' => (array)($extra['parts_print'] ?? []),
            'parts_item_name' => (array)($extra['parts_item_name'] ?? []),
            'parts_qty2' => (array)($extra['parts_qty2'] ?? []),
            'parts_unit_cost' => (array)($extra['parts_unit_cost'] ?? []),
            'parts_total2' => (array)($extra['parts_total2'] ?? []),
            'cons_print' => (array)($extra['cons_print'] ?? []),
            'cons_item_name' => (array)($extra['cons_item_name'] ?? []),
            'cons_qty' => (array)($extra['cons_qty'] ?? []),
            'cons_unit_cost' => (array)($extra['cons_unit_cost'] ?? []),
            'cons_total' => (array)($extra['cons_total'] ?? []),
            'quotation_labour_sections' => (array)($extra['quotation_labour_sections'] ?? []),
            'quotation_parts' => (array)($extra['quotation_parts'] ?? []),
            'show_consumables' => (bool)($extra['show_consumables'] ?? false),
            'consumables_label' => (string)($extra['consumables_label'] ?? 'Call-out'),
        ];

        if ($quote_id <= 0) {
            $quote_id = jc_find_active_quotation_id_for_job_card($pdo, $edit_id);
        }
        $quote_id = jc_sanitize_quote_id_for_job_card($pdo, $quote_id, $edit_id);
    }
}

$prefill_payload = $edit_payload;
if (!$prefill_payload && isset($_GET['client_id'])) {
    $prefillClientId = (int) $_GET['client_id'];
    if ($prefillClientId > 0) {
        $prefillClientStmt = $pdo->prepare('SELECT id, name, phone, email, address, contact_person FROM clients WHERE id = ? LIMIT 1');
        $prefillClientStmt->execute([$prefillClientId]);
        $prefillClient = $prefillClientStmt->fetch(PDO::FETCH_ASSOC);
        if ($prefillClient) {
            $prefill_payload = [
                'client_id' => (string) ($prefillClient['id'] ?? ''),
                'client_name_display' => (string) ($prefillClient['name'] ?? ''),
                'contact_no' => (string) ($prefillClient['phone'] ?? ''),
                'contact_email' => (string) ($prefillClient['email'] ?? ''),
                'to_line1' => (string) ($prefillClient['address'] ?? ''),
                'contact_person' => (string) ($prefillClient['contact_person'] ?? ''),
            ];
        }
    }
}
if (!$prefill_payload && !empty($dup_flash['repersist_payload'])) {
    $prefill_payload = $dup_flash['repersist_payload'];
}

$jc_user_id = (int) $_SESSION['user_id'];
$jc_draft_key = $edit_id > 0 ? 'job_card:edit:' . $edit_id : 'job_card:new';
$jc_draft_page_url = 'JobCard/add_job_card.php' . ($edit_id > 0 ? '?edit_id=' . $edit_id : '');
$jc_server_draft = user_draft_get($jc_user_id, $jc_draft_key);

if ($edit_id <= 0) {
    $redirectCard = '';
    if (is_array($jc_server_draft['payload'] ?? null)) {
        $redirectCard = trim((string) ($jc_server_draft['payload']['card_number'] ?? ''));
    }
    if ($redirectCard === '' && !empty($dup_flash['repersist_payload']['card_number'])) {
        $redirectCard = trim((string) $dup_flash['repersist_payload']['card_number']);
    }
    if (preg_match('/^\d{4}$/', $redirectCard)) {
        $existingRow = jc_find_job_card_number_conflict($pdo, $redirectCard, 0);
        if ($existingRow) {
            $redirectUrl = $jc_admin_web_base . 'JobCard/add_job_card.php?edit_id=' . (int) $existingRow['id'];
            if ($quote_id > 0) {
                $redirectUrl .= '&quote_id=' . $quote_id;
            }
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
}

?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">

<style>
/* Job Card Shell Styles - Preserved for print format */
.jc-shell {
    background: #FFF9C4;
    border: 2px solid #000;
    max-width: 1100px;
    margin: 0 auto 16px;
    font-family: Arial, sans-serif;
}
.aq-ic{width:100%;border:1px solid #e5e7eb;border-radius:0.5rem;padding:.5rem .75rem;font-size:.875rem;font-weight:400;outline:none}
.aq-ic:focus{box-shadow:0 0 0 2px rgba(249,115,22,.45);border-color:#fdba74}
.jc-rates-card{
    background:#fff7ed !important;
    border:1px solid #fdba74 !important;
    box-shadow:0 10px 24px rgba(249,115,22,.10);
}
.jc-rates-toggle{
    color:#9a3412 !important;
    background:#ffedd5;
    border-radius:0.75rem 0.75rem 0 0;
}
.jc-rates-toggle:hover{background:#fed7aa !important;}
.jc-rates-panel{border-top-color:#fdba74 !important;}
.jc-rates-panel label,
.jc-rates-panel legend,
.jc-rates-panel h3{color:#9a3412 !important;}
.jc-rates-panel .aq-ic{
    border-color:#fdba74;
    background:#fff;
}
.jc-rates-panel .aq-ic:focus{
    border-color:#f97316;
    box-shadow:0 0 0 2px rgba(249,115,22,.28);
}
.jc-rates-panel input[type="checkbox"]{accent-color:#f97316;}

/* Quotation configuration — same width/layout as .jc-m-card blocks above */
.jc-qt-config{
    width:100%;
    max-width:none;
    margin:0 0 1.25rem;
    padding:1.25rem;
    box-sizing:border-box;
    font-family:inherit;
    font-size:1rem;
    line-height:1.45;
    color:#2d3748;
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:0.5rem;
    box-shadow:0 1px 2px rgba(15,23,42,.04);
}
.jc-qt-config-head{margin:0 0 1rem;padding:0;border:none}
.jc-qt-config-head h2{
    font-size:0.75rem;
    font-weight:600;
    text-transform:uppercase;
    letter-spacing:0.08em;
    color:#4a5568;
    margin:0 0 0.35rem;
    line-height:1.35;
}
.jc-qt-config-head p{margin:0 0 1rem;font-size:0.8125rem;color:#64748b;line-height:1.5}
.jc-qt-config-card{
    background:transparent;
    border:none;
    border-radius:0;
    margin:0 0 1.25rem;
    padding:0;
    box-shadow:none;
    overflow:visible;
}
.jc-qt-config-card:last-child{margin-bottom:0}
.jc-qt-config-card-header{
    margin:0 0 0.75rem;
    padding:0.5rem 0.75rem;
    background:#dbeafe;
    border:none;
    border-bottom:2px solid #6b7280;
    border-radius:0.35rem 0.35rem 0 0;
    font-size:0.82rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0.05em;
    color:#111827;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:0.75rem;
    flex-wrap:wrap;
}
.jc-qt-config-card-body{padding:0}
.jc-qt-config-card-body--tight{padding:0}
.jc-qt-config-form-row{display:flex;flex-wrap:wrap;gap:1rem 1.25rem;margin-bottom:0}
.jc-qt-config-form-group{display:flex;flex-direction:column;gap:0.35rem;flex:1;min-width:200px}
.jc-qt-config-form-group label{
    font-size:0.92rem;
    font-weight:700;
    color:#111827;
    text-transform:none;
    letter-spacing:normal;
}
.jc-qt-config-form-group input,.jc-qt-config-cons-label{
    width:100%;
    box-sizing:border-box;
    border:2px solid #cbd5e1;
    border-radius:0.5rem;
    padding:0.625rem 0.75rem;
    min-height:0;
    font-size:1rem;
    font-family:inherit;
    background:#fff;
    color:#0f172a;
}
.jc-qt-config-form-group input:focus,.jc-qt-config-cons-label:focus{
    outline:none;
    box-shadow:0 0 0 3px rgba(245,158,11,.2);
    border-color:#f59e0b;
}
.jc-qt-config-hint{font-size:0.8125rem;color:#64748b;margin:0.5rem 0 0;line-height:1.5}
.jc-qt-config-toggle-row{
    display:flex;
    align-items:center;
    gap:0.5rem 1rem;
    margin-bottom:0.75rem;
    flex-wrap:wrap;
    padding:0;
    background:transparent;
    border:none;
    border-radius:0;
}
.jc-qt-config-toggle-row label{font-size:0.92rem;font-weight:700;color:#111827;cursor:pointer}
.jc-qt-config-toggle-row input[type=checkbox]{width:1rem;height:1rem;accent-color:#f59e0b;flex-shrink:0}
.jc-qt-config-cons-label{max-width:none;flex:1 1 12rem}
.jc-qt-config-toggle-sep{font-size:0.8125rem;color:#64748b;white-space:nowrap;font-weight:500}
.jc-qt-config-table-wrap{overflow-x:auto;margin-top:0.5rem;border:1px solid #e2e8f0;border-radius:0.5rem}
.jc-qt-config-table{width:100%;border-collapse:collapse;margin-bottom:0;font-size:0.875rem}
.jc-qt-config-table thead th{
    background:#dbeafe;
    color:#111827;
    font-weight:800;
    text-align:center;
    padding:0.5rem 0.75rem;
    border:1px solid #cbd5e1;
    border-bottom:2px solid #1e293b;
    font-size:0.82rem;
    text-transform:uppercase;
    letter-spacing:0.05em;
}
.jc-qt-config-table td{
    padding:0.25rem 0.5rem;
    vertical-align:middle;
    background:#fff;
}
.jc-qt-config-labour-tbody tr .jc-qt-config-activity-cell{
    border-bottom:1px solid #e2e8f0;
}
.jc-qt-config-labour-tbody tr:last-child .jc-qt-config-activity-cell{
    border-bottom:none;
}
.jc-qt-config-sec-desc-cell,
.jc-qt-config-sec-metric,
.jc-qt-config-sec-total-cell{
    border-bottom:none !important;
}
.jc-qt-config-sec-label{
    background:#dbeafe;
    font-weight:800;
    color:#111827;
    text-align:center;
    font-size:0.82rem;
    text-transform:uppercase;
    letter-spacing:0.05em;
    padding:0.5rem 0.75rem;
    border-bottom:2px solid #1e293b;
}
.jc-qt-config-preview-band{
    font-weight:800;
    text-align:center;
    font-size:0.82rem;
    text-transform:uppercase;
    letter-spacing:0.05em;
    padding:0.5rem 0.75rem;
    vertical-align:middle;
}
.jc-qt-config-preview-head--dual thead tr.jc-qt-config-preview-hdr-main th{
    border-bottom:2px solid #374151;
}
.jc-qt-config-preview-head--dual thead tr.jc-qt-config-preview-hdr-diag th{
    border-top:none;
}
.jc-qt-config-preview-head--dual thead tr.jc-qt-config-preview-hdr-main th:nth-child(2),
.jc-qt-config-preview-head--dual thead tr.jc-qt-config-preview-hdr-diag th.jc-qt-config-preview-band{
    border-bottom:2px solid #1f2937;
}
.jc-qt-config-row-label{background:#f8fafc;font-weight:700;color:#111827;vertical-align:middle}
.jc-qt-config-sec-desc-cell{
    background:#f1f5f9;
    font-weight:800;
    color:#111827;
    vertical-align:middle;
    text-align:left;
    min-width:110px;
}
.jc-qt-config-col-metric{
    width:7.5rem;
    min-width:7.5rem;
    max-width:7.5rem;
}
.jc-qt-config-sec-metric{
    background:#f8fafc;
    vertical-align:middle;
    text-align:center;
    width:7.5rem;
    min-width:7.5rem;
    max-width:7.5rem;
    padding:0.4rem 0.5rem;
}
.jc-qt-config-sec-metric .jc-qt-config-cell-inp{
    width:100%;
    min-width:0;
    min-height:2.35rem;
    padding:0.5rem 0.5rem;
    font-size:1rem;
    line-height:1.25;
    text-align:center;
}
.jc-qt-config-sec-rate-col .jc-qt-config-cell-inp{text-align:right}
.jc-qt-config-sec-total-cell{
    vertical-align:middle;
    text-align:right;
    width:7.5rem;
    min-width:7.5rem;
    max-width:7.5rem;
    padding:0.4rem 0.5rem;
    font-size:1rem;
}
.jc-qt-config-foot{background:#f8fafc;font-weight:800;font-size:0.875rem}
.jc-qt-config-total{font-weight:800;color:#111827;text-align:right}
.jc-qt-config-cell-inp,.jc-qt-config-callout-type{
    width:100%;
    box-sizing:border-box;
    border:1px solid #cbd5e1;
    border-radius:0.35rem;
    background:#fff;
    font-size:1rem;
    font-family:inherit;
    padding:0.5rem 0.45rem;
    min-height:0;
    outline:none;
}
.jc-qt-config-cell-inp:focus,.jc-qt-config-callout-type:focus{
    box-shadow:0 0 0 2px rgba(245,158,11,.15);
    border-color:#f59e0b;
}
.jc-qt-config-callout-cell{background:#f8fafc;min-width:11rem}
.jc-qt-config-callout-type{display:block;width:100%;min-width:10rem;cursor:pointer}
.jc-qt-config-callout-group-row .jc-qt-config-sec-label{text-align:left;padding:0.5rem 0.75rem}
.jc-qt-config-parts-group-row .jc-qt-config-parts-supply-label{text-align:left}
.jc-qt-config-parts-note{margin:0 0 0.75rem}
#jcQcPartsTable tbody tr.jc-qt-config-parts-data-row td,
#jcQcPartsTable tbody tr.jc-qt-config-callout-data-row td{
    border-bottom:1px solid #e2e8f0 !important;
    background:#fff;
}
#jcQcPartsTable tbody tr.jc-qt-config-parts-data-row:last-of-type td,
#jcQcPartsTable tbody tr.jc-qt-config-callout-data-row:last-of-type td{
    border-bottom:1px solid #cbd5e1 !important;
}
#jcQcPartsTable tbody tr.jc-qt-config-parts-group-row td,
#jcQcPartsTable tbody tr.jc-qt-config-callout-group-row td{
    border-bottom:2px solid #1e293b !important;
}
#jcQcPartsTable tbody tr.jc-qt-config-parts-data-row .jc-qt-config-row-label{
    background:#f8fafc;
    border-right:1px solid #e2e8f0;
}
.jc-qt-config-callout-toggle{margin-bottom:0.75rem}
.jc-qt-config-btn:disabled{opacity:0.4;cursor:not-allowed}
.jc-qt-config-muted{color:#9ca3af;text-align:center;font-size:0.875rem}
.jc-qt-config-sec-static{font-weight:800;color:#111827;font-size:0.875rem}
.jc-qt-config-section-block{margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid #e2e8f0}
.jc-qt-config-section-block:last-child{border-bottom:none;margin-bottom:0;padding-bottom:0}
.jc-qt-config-sec-controls{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:0.5rem 0.75rem;
    margin-bottom:0.75rem;
    font-size:0.875rem;
    padding:0.75rem;
    background:#f8fafc;
    border-radius:0.5rem;
    border:1px solid #e2e8f0;
}
.jc-qt-config-sec-controls label{color:#111827;font-weight:700;font-size:0.8125rem;text-transform:none}
.jc-qt-config-sec-controls select,.jc-qt-config-sec-controls input[type=text],.jc-qt-config-sec-controls input[type=number]{
    border:1px solid #cbd5e1;
    border-radius:0.35rem;
    padding:0.5rem 0.45rem;
    min-height:0;
    font-size:1rem;
    font-family:inherit;
    background:#fff;
}
.jc-qt-config-sec-num{font-weight:800;color:#111827;min-width:5rem;font-size:0.875rem}
.jc-qt-config-section-actions{display:flex;gap:0.5rem;flex-wrap:wrap}
.jc-qt-config-btn{
    padding:0.5rem 0.85rem;
    border-radius:0.375rem;
    font-size:0.8125rem;
    font-weight:600;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:0.35rem;
    line-height:1.2;
    font-family:inherit;
}
.jc-qt-config-btn-light{background:#fff;color:#475569;border:1px solid #cbd5e1}
.jc-qt-config-btn-light:hover{background:#f8fafc;border-color:#94a3b8}
.jc-qt-config-btn-danger{background:#dc2626;color:#fff;border:1px solid #dc2626}
.jc-qt-config-btn-danger:hover{background:#b91c1c}
.jc-qt-config-btn-sm{padding:0.4rem 0.65rem;font-size:0.75rem}
.jc-qt-config-btn-xs{padding:0.25rem 0.45rem;font-size:0.75rem;min-width:1.75rem;justify-content:center}
.jc-qt-config-action-bar{display:flex;gap:0.5rem;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid #e2e8f0}
.jc-qt-config-preview-head{margin-bottom:0.75rem}
.jc-qt-config-preview-head.jc-qt-config-table-wrap{margin-top:0}
.jc-qt-config-preview-head .jc-qt-config-table,
.jc-qt-config-labour-sections .jc-qt-config-table,
#jcQcPartsTable{table-layout:fixed}
.jc-qt-config-table td.jc-qt-config-col-metric{
    vertical-align:middle;
    padding:0.4rem 0.5rem;
}
.jc-qt-config-table td.jc-qt-config-col-metric .jc-qt-config-cell-inp{
    min-height:2.35rem;
    padding:0.5rem;
    font-size:1rem;
    line-height:1.25;
    text-align:center;
}
.jc-qt-config-table td.jc-qt-config-col-metric-rate .jc-qt-config-cell-inp{text-align:right}
.jc-qt-config-unit-cost-inp.jc-qt-config-unit-cost-error{border-color:#dc2626;background:#fef2f2}
.jc-qt-config-table td.jc-qt-config-col-metric.jc-qt-config-total{
    font-size:1rem;
    padding:0.4rem 0.5rem;
}
.jc-qt-config-total-row{
    display:flex;
    justify-content:flex-end;
    margin-top:0.75rem;
}
.jc-qt-config-labour-sections{margin-top:0.75rem}
.jc-qt-config-card-total{
    display:inline-flex;
    justify-content:flex-end;
    align-items:center;
    gap:0.75rem 1rem;
    width:auto;
    max-width:100%;
    padding:0.55rem 1rem;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:0.5rem;
    font-size:0.875rem;
    white-space:nowrap;
}
.jc-qt-config-card-total span{font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:0.04em;font-size:0.75rem}
.jc-qt-config-card-total strong{font-weight:800;color:#111827;font-size:1rem;min-width:4.5rem;text-align:right}
.jc-qt-config-summary-box{
    background:#f8fafc;
    border:1px solid #e2e8f0;
    border-radius:0.5rem;
    padding:1rem 1.15rem;
    max-width:20rem;
}
.jc-qt-config-summary-title{
    margin:0 0 0.75rem;
    font-size:0.75rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:0.05em;
    color:#64748b;
}
.jc-qt-config-summary-lines{display:flex;flex-direction:column;gap:0.5rem}
.jc-qt-config-summary-line{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:1rem;
    font-size:0.875rem;
    color:#475569;
}
.jc-qt-config-summary-line span:last-child{font-weight:600;color:#111827}
.jc-qt-config-summary-line--grand{
    margin-top:0.35rem;
    padding-top:0.5rem;
    border-top:1px solid #e2e8f0;
    font-weight:800;
    font-size:1rem;
    color:#111827;
}
.jc-qt-config-summary-line--grand span:last-child{font-weight:800}
.jc-qt-config-summary-hint{margin:0.75rem 0 0}
.jc-qt-config-labour-sections .jc-qt-config-section-block > .jc-qt-config-table{
    border:1px solid #e2e8f0;
    border-radius:0.5rem;
    overflow:hidden;
}

.jc-shell-p2 {
    background: #FFF9C4;
    border: 2px solid #000;
    max-width: 1100px;
    margin: 0 auto 20px;
    font-family: Arial, sans-serif;
}
/* Walk-in and other custom styles preserved */
.walkin-toggle {
    max-width:900px;
    margin: 0 auto 10px;
    display:flex;
    align-items:center;
    gap:10px;
            font-size:15px; font-weight:700; color:var(--s);
        }
        .walkin-toggle input[type=checkbox] { width:18px; height:18px; cursor:pointer; }

        /* â”€â”€ CARD SHELL â”€â”€ */
        .jc-shell {
            background:#FFF9C4;
            border:2px solid #000;
            max-width:1100px;
            margin:0 auto 16px;
            font-family:Arial,sans-serif;
        }

        /* PAGE 2 SHELL */
        .jc-shell-p2 {
            background:#FFF9C4;
            border:2px solid #000;
            max-width:1100px;
            margin:0 auto 20px;
            font-family:Arial,sans-serif;
        }

        /* HEADER */
        .header {
            display: grid;
            grid-template-columns: 200px 1fr;
            align-items: center;
            padding: 12px;
            border-bottom: none;
        }
        .header-logo img {
            width: 190px;
            height: auto;
        }
        .header-contact {
            text-align: right;
            font-size: 13px;
            line-height: 1.8;
            color: #000;
            font-family: Arial, sans-serif;
            font-weight: 500;
        }

        /* TITLE ROW */
        .title-row {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 8px 12px;
            border-top: none;
            border-bottom: 2px solid #000;
        }
        .title-row h1 {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 4px;
            font-family: Arial Black, sans-serif;
            text-align: center;
            flex: 1;
        }
        .title-row .card-no {
            position: absolute;
            right: 12px;
            font-size: 20px;
            font-weight: 900;
            font-family: Arial, sans-serif;
        }

        /* TWO COLUMN BODY - REMOVED, NOW USING TABLE */

        /* FIELD ROW */
        .jc-field {
            display:flex; align-items:flex-end;
            margin-bottom:7px; gap:6px;
        }
        .jc-field label {
            font-size:14px; font-weight:700;
            text-transform:uppercase; white-space:nowrap;
            color:#333; min-width:110px;
            padding-bottom:3px; letter-spacing:0.3px;
        }
        .jc-field input,
        .jc-field select {
            flex:1; border:none;
            border-bottom:1.5px solid #000;
            border-radius:0; padding:3px 5px;
            font-size:15px; background:transparent;
            font-family:Arial,sans-serif; color:#111; outline:none;
        }
        .jc-field input:focus,
        .jc-field select:focus {
            background:#fffdf0;
            border-bottom-color:var(--p);
        }

        /* TO field */
        .to-field { margin-bottom:2px; }
        .to-field label {
            font-size:13px; font-weight:700;
            text-transform:uppercase; color:#333;
            display:block; margin-bottom:2px;
        }
        .to-field select {
            width:100%; border:none;
            border-bottom:1.5px solid #000;
            border-radius:0; padding:3px 5px;
            font-size:15px; background:transparent;
            font-family:Arial,sans-serif; outline:none;
        }
        .to-field select:focus { background:#fffdf0; border-bottom-color:var(--p); }

        /* Blank lines below TO */
        .blank-lines { margin:0 0 10px; }
        .blank-line { border-bottom:1px solid #ccc; height:28px; }

        /* Spacer lines between contact fields */
        .spacer-lines { margin:0 0 6px; }
        .spacer-line { border-bottom:1px solid #ddd; height:26px; }

        /* Walk-in panel */
        .walkin-panel {
            display:none; background:#FFF8F0;
            border:1px dashed var(--p); border-radius:6px;
            padding:8px; margin-bottom:8px;
        }
        .walkin-panel.active { display:block; }
        .walkin-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
        .mini-field label {
            font-size:13px; font-weight:700;
            text-transform:uppercase; color:#555;
            display:block; margin-bottom:2px;
        }
        .mini-field input {
            width:100%; border:1.5px solid #ccc;
            border-radius:4px; padding:5px 8px;
            font-size:15px; background:white; outline:none;
        }
        .mini-field input:focus { border-color:var(--p); }

        /* SECTION TITLE */
        .jc-section {
            text-align:center; font-size:13px; font-weight:900;
            letter-spacing:4px; text-transform:uppercase;
            background:#FFF9C4; border-top:2px solid #000;
            border-bottom:1px solid #000; padding:4px 0;
            color:#000;
        }

        /* DESCRIPTION TEXTAREA */
        .jc-textarea-wrap { padding:4px 14px 0; }
        .jc-textarea-wrap textarea {
            width:100%; border:none; border-radius:0;
            resize:none; font-size:15px; font-family:Arial,sans-serif;
            color:#111; background:transparent; padding:4px;
            outline:none; line-height:22px;
        }
        .jc-textarea-wrap textarea:focus { background:#fffdf0; }

        /* Description lined rows */
        .desc-lined { padding:0 14px; }
        .desc-line { border-bottom:1px solid #ccc; height:22px; }

        /* PARTS SUPPLY â€” table format */
        .parts-table {
            width:100%;
            border-collapse:collapse;
            margin:0;
        }
        .parts-table th {
            background:#F7A100;
            border:1px solid #999;
            padding:5px 8px;
            font-size:12px;
            font-weight:900;
            text-transform:uppercase;
            letter-spacing:1px;
            text-align:left;
            color:#000;
        }
        .parts-table td {
            border:1px solid #ccc;
            padding:0;
            height:30px;
        }
        .parts-table td input {
            width:100%; height:100%;
            border:none; outline:none;
            padding:0 6px; font-size:14px;
            background:transparent; font-family:Arial,sans-serif;
        }
        .parts-table td input:focus { background:#fffdf0; }
        .parts-table .qty-col { width:80px; text-align:center; }
        .parts-table .unit-col { width:100px; }



        /* CONDITIONS */
        .jc-conditions {
            border-top:2px solid #000; padding:6px 12px;
            font-size:7px; line-height:1.4; color:#555; background:#fafafa;
        }
        .jc-conditions strong { font-size:7.5px; color:#222; }

        /* â”€â”€ PAGE 2 STYLES â”€â”€ */
        .p2-title {
            text-align:center; font-size:13px; font-weight:900;
            letter-spacing:2px; color:var(--s);
            padding:8px 0 4px;
        }

        .work-table {
            width:100%; border-collapse:collapse;
        }
        .work-table th {
            border:1px solid #000; padding:5px 8px;
            font-size:13px; font-weight:900;
            text-align:center; background:#F7A100;
            letter-spacing:2px; text-transform:uppercase;
            color:#000;
        }
        .work-table td {
            border:1px solid #ccc; height:30px; padding:0;
        }
        .work-table td input {
            width:100%; height:100%; border:none; outline:none;
            padding:0 6px; font-size:14px;
            background:transparent; font-family:Arial,sans-serif;
        }
        .work-table td input:focus { background:#fffdf0; }
        .work-table .time-col { width:140px; }

        /* CALL OUT OF TOWN */
        .callout-box {
            border:2px solid #000; margin:10px 14px;
        }
        .callout-title {
            text-align:center; font-size:13px; font-weight:900;
            letter-spacing:3px; text-transform:uppercase;
            background:#f0f0f0; border-bottom:1px solid #000;
            padding:4px 0;
        }
        .callout-body { padding:8px 12px; }
        .callout-row { display:flex; gap:16px; margin-bottom:8px; }
        .callout-field { flex:1; }
        .callout-field label {
            font-size:13px; font-weight:700;
            text-transform:uppercase; color:#333;
            display:block; margin-bottom:2px;
        }
        .callout-field input {
            width:100%; border:none;
            border-bottom:1.5px solid #000;
            padding:3px 4px; font-size:14px;
            background:transparent; outline:none; font-family:Arial,sans-serif;
        }
        .callout-field input:focus { background:#fffdf0; }

        /* BOTTOM OF PAGE 2 */
        .p2-bottom {
            border-top:2px solid #000;
            display:grid;
            grid-template-columns:1fr 1fr 1fr;
        }
        .p2-bottom-cell {
            padding:8px 12px;
            border-right:1px solid #000;
        }
        .p2-bottom-cell:last-child { border-right:none; }
        .p2-bottom-cell label {
            font-size:13px; font-weight:700;
            text-transform:uppercase; color:#333;
            display:block; margin-bottom:4px;
        }
        .p2-bottom-cell input {
            width:100%; border:none;
            border-bottom:1.5px solid #000;
            padding:3px 4px; font-size:14px;
            background:transparent; outline:none; font-family:Arial,sans-serif;
        }
        .p2-bottom-cell input:focus { background:#fffdf0; }

        .sig-row {
            border-top:2px solid #000;
            display:grid; grid-template-columns:1fr 1fr;
        }
        .sig-cell {
            padding:10px 14px;
            border-right:1px solid #000;
            min-height:60px;
        }
        .sig-cell:last-child { border-right:none; }
        .sig-cell label {
            font-size:9px; font-weight:700;
            text-transform:uppercase; color:#333;
            display:block; margin-bottom:30px;
        }
        .sig-line {
            border-top:1px solid #000;
            margin-top:4px;
        }

        /* SUBMIT BAR */
        .submit-bar {
            max-width:900px; margin:0 auto 40px;
            display:flex; gap:12px;
        }
        .btn-create {
            flex:1; background:#F7A100;
            color:white; padding:13px 24px; border:none; border-radius:10px;
            font-size:15px; font-weight:700; cursor:pointer;
            box-shadow:0 6px 20px rgba(247,161,0,0.35);
            transition:transform .2s,box-shadow .2s;
            display:flex; align-items:center; justify-content:center; gap:10px;
        }
        .btn-create:hover { transform:translateY(-2px); box-shadow:0 10px 28px rgba(247,161,0,0.5); }
        .btn-back {
            background:#95a5a6; color:white; padding:13px 24px;
            border:none; border-radius:10px; font-size:15px; font-weight:700;
            cursor:pointer; text-decoration:none;
            display:flex; align-items:center; gap:8px;
        }

        /* CALL OUT OF TOWN */
        .callout-box {
            border:2px solid #000; margin:10px 14px;
        }
        .callout-title {
            text-align:center; font-size:10px; font-weight:900;
            letter-spacing:3px; text-transform:uppercase;
            background:#f0f0f0; border-bottom:1px solid #000;
            padding:4px 0;
        }
        .callout-body { padding:8px 12px; }
        .callout-row { display:flex; gap:16px; margin-bottom:8px; }
        .callout-field { flex:1; }
        .callout-field label {
            font-size:13px; font-weight:700;
            text-transform:uppercase; color:#333;
            display:block; margin-bottom:2px;
        }
        .callout-field input {
            width:100%; border:none;
            border-bottom:1.5px solid #000;
            padding:3px 4px; font-size:14px;
            background:transparent; outline:none; font-family:Arial,sans-serif;
        }
        .callout-field input:focus { background:#fffdf0; }

        /* BOTTOM OF PAGE 2 */
        .p2-bottom {
            border-top:2px solid #000;
            display:grid;
            grid-template-columns:1fr 1fr 1fr;
        }
        .p2-bottom-cell {
            padding:8px 12px;
            border-right:1px solid #000;
        }
        .p2-bottom-cell:last-child { border-right:none; }
        .p2-bottom-cell label {
            font-size:13px; font-weight:700;
            text-transform:uppercase; color:#333;
            display:block; margin-bottom:4px;
        }
        .p2-bottom-cell input {
            width:100%; border:none;
            border-bottom:1.5px solid #000;
            padding:3px 4px; font-size:14px;
            background:transparent; outline:none; font-family:Arial,sans-serif;
        }
        .p2-bottom-cell input:focus { background:#fffdf0; }

        /* PRINT STYLES */
        @media print {
            /* Hide everything except the job card */
            .erp-header, .erp-sidebar, .erp-main > *:not(.erp-content),
            .erp-sidebar-footer, .sidebar-overlay,
            .header, .sidebar, .overlay, .menu-toggle,
            .walkin-toggle, .submit-bar, .walkin-panel,
            .jc-modern-top, .jc-modern-actions, .jc-card,
            .alert-success, .alert-error,
            #draft-save-indicator,
            .erp-breadcrumb, .erp-page-header { 
                display:none !important; 
            }

            .no-print { display: none !important; }

            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
            }

            .jc-shell, .jc-shell-p2 {
                background: #FFF9C4 !important;
            }

            .jc-section {
                background: #FFF9C4 !important;
            }

            .erp-content, .erp-main, .content-wrapper, .erp-page-header,
            form > *:not(.jc-shell):not(.jc-shell-p2) {
                margin: 0 !important;
                padding: 0 !important;
            }

            /* PAGE 1 */
            .jc-shell {
                page-break-after: always !important;
                page-break-inside: avoid !important;
                margin: 0 !important;
                background: #FFF9C4 !important;
            }

            /* PAGE 2 */
            .jc-shell-p2 {
                page-break-before: always !important;
                page-break-inside: avoid !important;
                margin: 0 !important;
                background: #FFF9C4 !important;
            }

            /* Hide labor tracking section on print - keep it clean */
            .labor-tracking-section, #laborTrackingSection { 
                display: none !important; 
            }

            /* Clean up all inputs for print */
            input, select, textarea {
                border: none !important;
                border-bottom: 1px solid #000 !important;
                background: transparent !important;
                outline: none !important;
                box-shadow: none !important;
                -webkit-appearance: none !important;
                appearance: none !important;
                font-size: 11px !important;
                font-family: Arial, sans-serif !important;
                color: #000 !important;
            }

            /* Hide select arrow on print */
            select {
                color: #000 !important;
                padding-right: 0 !important;
            }

            /* Hide placeholders */
            input::placeholder, textarea::placeholder {
                color: transparent !important;
                opacity: 0 !important;
            }

            /* Hide manual input fields */
            #manualClientField, #manualVehicleInput {
                display: none !important;
            }

            /* Parts table clean */
            .parts-table td, .parts-table th {
                border: 1px solid #999 !important;
                padding: 4px 6px !important;
            }
            .parts-table td input {
                border: none !important;
                border-bottom: none !important;
            }

            /* Work table clean */
            .work-table td, .work-table th {
                border: 1px solid #999 !important;
            }
            .work-table td input {
                border: none !important;
                border-bottom: none !important;
            }

            /* Header background */
            .jc-section {
                background: #FFF9C4 !important;
                border-top: 2px solid #000 !important;
                border-bottom: 1px solid #000 !important;
            }

            .callout-title {
                background: #f0f0f0 !important;
            }

            /* Page settings */
            @page { 
                size: A4 portrait; 
                margin: 8mm 10mm 8mm 10mm; 
            }

            @page :first {
                margin: 8mm 10mm 8mm 10mm;
            }

            /* Print-safe font sizes and heights */
            .jc-field label { font-size: 9px !important; }
            .jc-field input, .jc-field select { font-size: 11px !important; }
            .to-field label { font-size: 9px !important; }
            .to-field select { font-size: 11px !important; }
            .jc-section { font-size: 10px !important; }
            .blank-line { height: 20px !important; }
            .spacer-line { height: 18px !important; }
            .jc-shell { max-width: 100% !important; }
            .jc-shell-p2 { max-width: 100% !important; }
        }

        /* ========== Screen UI (matches quotation modern shell) ========== */
        html, body {
            background: #fff9c4 !important;
        }
        .content-wrapper {
            background: #fff9c4 !important;
            min-height: 100vh;
            padding: 1rem 0;
        }
        .erp-content,
        .erp-main {
            background: #fff9c4 !important;
        }
        .jc-modern-app {
            font-family: "Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem 2.5rem;
            background: #fff9c4;
            color: #2d3748;
            border-radius: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .jc-modern-top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin: 0 0 1.6rem;
            min-height: 120px;
            padding: 1.35rem 0 1.1rem;
        }
        .jc-modern-top-left {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            position: relative;
            min-height: 72px;
        }
        .jc-modern-back {
            padding: 0.5rem;
            color: #9ca3af;
            text-decoration: none;
            border-radius: 0.375rem;
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
        }
        .jc-modern-back:hover { color: #4b5563; background: #f3f4f6; }
        .jc-modern-title {
            font-size: 2.45rem;
            font-weight: 900;
            color: #0f172a;
            margin: 0;
            line-height: 1.22;
            letter-spacing: -0.01em;
            text-align: center;
            width: 100%;
            display: block;
            transform: translateY(2px);
        }
        .jc-modern-sub { font-size: 0.875rem; color: #4a5568; margin: 0.35rem 0 0; line-height: 1.5; }
        .jc-modern-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 0.5rem; width: 100%; }
        .jc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.75rem;
            padding: 0.65rem 1.05rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            text-decoration: none;
            line-height: 1.2;
            min-height: 46px;
        }
        .jc-btn:hover:not(:disabled) { filter: brightness(.98); }
        .jc-btn-orange {
            background: #f97316;
            border: 1px solid #ea580c;
            color: #ffffff;
        }
        .jc-btn-orange:hover:not(:disabled) { background: #ea580c; border-color: #c2410c; }
        .jc-del-modal{
            position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:2200;
        }
        .jc-del-modal.show{display:flex}
        .jc-del-card{
            width:min(92vw,460px);background:#fff;border:1px solid #e5e7eb;border-radius:14px;
            box-shadow:0 20px 50px rgba(0,0,0,.25);padding:18px;
        }
        .jc-del-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
        .jc-del-body{font-size:.9375rem;color:#334155;line-height:1.45}
        .jc-del-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}
        .jc-del-cancel{border-color:#cbd5e1;color:#334155}
        .jc-del-confirm{background:#dc2626;border-color:#b91c1c;color:#fff}
        .jc-del-confirm:hover{background:#b91c1c;border-color:#991b1b}
        .jc-so-confirm{background:#f97316;border-color:#ea580c;color:#fff}
        .jc-so-confirm:hover{background:#ea580c;border-color:#c2410c}
        .jc-flash-wrap{
            position:fixed;inset:0;z-index:2300;display:flex;align-items:center;justify-content:center;
            background:rgba(15,23,42,.35);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
            opacity:0;pointer-events:none;transition:opacity .2s ease;
        }
        .jc-flash-wrap.show{opacity:1;pointer-events:auto}
        .jc-flash-card{
            position:relative;
            width:min(92vw,420px);background:#fff;border:1px solid #e5e7eb;border-radius:16px;
            box-shadow:0 24px 55px rgba(0,0,0,.25);padding:18px 18px 14px;text-align:center;
            transform:translateY(8px);transition:transform .2s ease;
        }
        .jc-flash-close{
            position:absolute;top:10px;right:10px;
            width:32px;height:32px;border:none;border-radius:8px;
            background:#f1f5f9;color:#64748b;font-size:20px;line-height:1;
            cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
        }
        .jc-flash-close:hover{background:#e2e8f0;color:#334155}
        .jc-flash-wrap.show .jc-flash-card{transform:translateY(0)}
        .jc-flash-icon{
            width:44px;height:44px;border-radius:999px;margin:0 auto 10px;
            display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;
        }
        .jc-flash-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
        .jc-flash-body{font-size:.9rem;line-height:1.45}
        .jc-flash-card--success .jc-flash-icon{background:#ecfdf3;color:#16a34a;border:1px solid #bbf7d0}
        .jc-flash-card--success .jc-flash-body{color:#166534}
        .jc-flash-card--error .jc-flash-icon{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
        .jc-flash-card--error .jc-flash-body{color:#991b1b}
        .jc-flash-link{
            display:inline-flex;align-items:center;gap:6px;margin-top:10px;
            font-weight:600;color:#b91c1c;text-decoration:underline;
        }
        .jc-flash-link:hover{color:#7f1d1d}
        .jc-flash-card--info .jc-flash-icon{background:#fff7ed;color:#ea580c;border:1px solid #fdba74}
        .jc-flash-card--info .jc-flash-body{color:#9a3412}
        .jc-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .jc-doc-outer {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }
        .jc-modern-app .alert-success,
        .jc-modern-app .alert-error {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }
        /* ========== Screen-first modern form (not paper facsimile) ========== */
        .jc-form-modern {
            --p: #f59e0b;
            --s: #1e293b;
        }
        .jc-section1 .jc-section1-addr {
            font-size: 0.75rem;
            color: #64748b;
            text-align: right;
            line-height: 1.45;
            margin: 0 0 0.75rem;
            max-width: none;
        }
        .jc-section1-title-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem 1rem;
            margin-bottom: 0.35rem;
            padding-bottom: 0.65rem;
            border-bottom: 2px solid #1e293b;
        }
        .jc-section1-doc-title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #1e293b;
        }
        .jc-section1-no {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .jc-section1-no label {
            font-size: 0.875rem;
            font-weight: 700;
            color: #374151;
            margin: 0;
        }
        .jc-section1-no .jc-m-input {
            width: auto;
            min-width: 8rem;
            max-width: 12rem;
        }
        .jc-section1-col-title {
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #1f2937;
            margin: 0 0 0.75rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .jc-m-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .jc-m-card > h2 {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #4a5568;
            margin: 0 0 1rem;
            line-height: 1.35;
        }
        .jc-m-field { margin-bottom: 0.85rem; }
        .jc-m-field:last-child { margin-bottom: 0; }
        .jc-address-stack .jc-address-stack-line { margin-top: 0.5rem; }
        .jc-m-field label {
            display: block;
            font-size: 0.92rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.35rem;
            line-height: 1.45;
        }
        .jc-m-input, .jc-m-textarea, .jc-form-modern select.jc-m-input {
            width: 100%;
            box-sizing: border-box;
            border: 2px solid #cbd5e1;
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            font-family: inherit;
            background: #fff;
            color: #0f172a;
            line-height: 1.45;
        }
        .jc-m-input::placeholder, .jc-m-textarea::placeholder { color: #6b7280; opacity: 1; }
        .jc-m-input:focus, .jc-m-textarea:focus, .jc-form-modern select.jc-m-input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
            border-color: #f59e0b;
        }
        .jc-m-textarea { resize: vertical; min-height: 6rem; }
        .jc-m-grid-2 { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
        @media (min-width: 900px) { .jc-m-grid-2 { grid-template-columns: 1fr 1fr; } }
        .jc-m-grid-3 { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .jc-m-hint { font-size: 0.8125rem; color: #64748b; margin: 0 0 1rem; line-height: 1.5; }
        .jc-m-details summary { cursor: pointer; font-size: 0.875rem; font-weight: 600; color: #475569; margin-bottom: 0.75rem; }
        .jc-m-table-wrap { overflow-x: auto; margin-top: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem; }
        .jc-m-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .jc-m-table th {
            background: #f8fafc;
            color: #111827;
            font-weight: 800;
            text-align: left;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .jc-m-table td { border-bottom: 1px solid #f1f5f9; padding: 0.25rem 0.5rem; vertical-align: middle; }
        .jc-m-table tr:last-child td { border-bottom: none; }
        .jc-m-table td .jc-m-input {
            border: 1px solid #cbd5e1;
            box-shadow: none;
            padding: 0.5rem 0.45rem;
            background: #ffffff;
        }
        .jc-m-table td .jc-m-input:focus {
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.15);
            border: 1px solid #f59e0b;
            border-radius: 0.35rem;
        }
        .jc-m-conditions { font-size: 0.75rem; line-height: 1.5; color: #64748b; }
        .jc-m-conditions strong { color: #475569; }
        .jc-row-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; align-items: center; }
        /* Section 2: paper-style blocks (DESCRIPTION + PARTS SUPPLY) */
        .jc-m-table.jc-m-table--section2-head thead th {
            text-align: center;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            background: #dbeafe;
            color: #111827;
            border-bottom: 2px solid #1e293b;
        }
        .jc-m-table.jc-m-table-desc tbody td { border-left: none; border-right: none; }
        .jc-m-table.jc-m-table-parts tbody td:first-child { border-right: 2px solid #cbd5e1; }
        /* Section 3: work/call-out/signature paper layout */
        .jc-m-table.jc-m-table-work thead th:first-child { width: 78%; text-align: center; }
        .jc-m-table.jc-m-table-work thead th:last-child { width: 22%; text-align: center; }
        .jc-m-table.jc-m-table-callout thead th,
        .jc-m-table.jc-m-table-signoff thead th {
            text-align: center;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            background: #dbeafe;
            color: #111827;
            border-bottom: 2px solid #1e293b;
        }
        .jc-m-table.jc-m-table-callout td,
        .jc-m-table.jc-m-table-signoff td { padding: 0; }
        .jc-m-table.jc-m-table-callout td .jc-m-input,
        .jc-m-table.jc-m-table-signoff td .jc-m-input { padding: 0.45rem 0.5rem; }
        .jc-m-table.jc-m-table-signoff td textarea.jc-m-input {
            min-height: 2.25rem;
            resize: vertical;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            line-height: 1.3;
        }
        .jc-m-table.jc-m-table-signoff th,
        .jc-m-table.jc-m-table-signoff td {
            border-right: 1px solid #cbd5e1;
        }
        .jc-m-table.jc-m-table-signoff th:last-child,
        .jc-m-table.jc-m-table-signoff td:last-child {
            border-right: none;
        }
        .jc-sign-choice {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.45rem 0.55rem;
            font-size: 0.8125rem;
            color: #334155;
            white-space: nowrap;
        }
        .jc-sign-choice input[type="radio"] {
            margin: 0;
            width: 14px;
            height: 14px;
            accent-color: #1e293b;
        }
        /* Labor include: make it look like our cards */
        .jc-form-modern .jc-field label { color: #4a5568; font-size: 0.8125rem; font-weight: 500; }
        .jc-form-modern .jc-field input, .jc-form-modern .jc-field select {
            border: 1px solid #e2e8f0 !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem 0.75rem !important;
            background: #fff !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }

        /* Job card preview overlay (paper layout) */
        .jc-preview-shell {
            position: fixed;
            inset: 0;
            z-index: 10050;
            display: none;
            overflow: auto;
            background: rgba(15, 23, 42, 0.48);
            -webkit-overflow-scrolling: touch;
        }
        .jc-preview-shell.is-open {
            display: block;
        }
        .jc-preview-shell-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100%;
            padding: 1rem 0.75rem 2rem;
            box-sizing: border-box;
        }
        .jc-preview-toolbar {
            width: 100%;
            max-width: 900px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.625rem;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
            text-align: center;
        }
        .jc-preview-toolbar-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            letter-spacing: -0.01em;
        }
        .jc-preview-toolbar-sub {
            margin: 0.15rem 0 0;
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }
        .jc-preview-doc-frame {
            width: 100%;
            max-width: 900px;
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 8px 32px rgba(15, 23, 42, 0.18);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .jc-preview-doc-frame iframe {
            display: block;
            width: 100%;
            min-height: min(78vh, 920px);
            border: none;
            background: #fff9c4;
        }
        .jc-preview-footer {
            width: 100%;
            max-width: 900px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 0.65rem;
            padding: 1rem 0 0.25rem;
        }
        .jc-preview-footer .aq-s1-btn {
            min-width: 9.5rem;
            justify-content: center;
        }
        @media (max-width: 640px) {
            .jc-preview-footer {
                flex-direction: column;
                align-items: stretch;
            }
            .jc-preview-footer .aq-s1-btn {
                width: 100%;
                min-width: 0;
            }
        }
        @media print {
            .jc-modern-app { background: transparent !important; padding: 0 !important; max-width: none !important; }
            .jc-modern-top, .jc-modern-actions, .jc-card, .jc-form-modern .jc-m-card {
                background: transparent !important;
                box-shadow: none !important;
                border: 1px solid #e5e7eb !important;
                padding: 0.75rem !important;
            }
            .jc-scroll-jump { display: none !important; }
        }
        .jc-scroll-jump {
            position: fixed;
            right: 18px;
            bottom: 22px;
            z-index: 850;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .jc-scroll-jump-btn {
            width: 38px;
            height: 38px;
            padding: 0;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #fff;
            color: #475569;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }
        .jc-scroll-jump-btn:hover:not(:disabled) {
            background: #fff7ed;
            border-color: #f7a100;
            color: #c2410c;
            box-shadow: 0 4px 12px rgba(247, 161, 0, 0.18);
        }
        .jc-scroll-jump-btn:focus-visible {
            outline: 2px solid #f7a100;
            outline-offset: 2px;
        }
        .jc-scroll-jump-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
            box-shadow: none;
        }
        @media (max-width: 640px) {
            .jc-scroll-jump {
                right: 12px;
                bottom: 14px;
            }
            .jc-scroll-jump-btn {
                width: 36px;
                height: 36px;
                font-size: 13px;
            }
        }
    </style>

<div class="content-wrapper">

<div class="jc-modern-app">
    <div class="jc-modern-top" data-jc-jump>
        <div class="jc-modern-top-left">
            <div style="text-align:center;">
                <h1 class="jc-modern-title"><?php echo $edit_payload ? 'Edit Job Card' : 'New Job Card'; ?></h1>
            </div>
        </div>
        <div class="jc-modern-actions no-print">
            <?php if (!$edit_payload): ?>
            <button type="button" class="aq-s1-btn aq-s1-btn--gray jc-start-over-btn" title="Clear draft and start a new job card">
                <i class="fas fa-rotate-right" aria-hidden="true"></i> Start over
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] !== ''): ?>
        <div class="jc-flash-wrap" id="jcFlashWrap">
            <div class="jc-flash-card jc-flash-card--success">
                <div class="jc-flash-icon"><i class="fas fa-check"></i></div>
                <div class="jc-flash-title">Success</div>
                <div class="jc-flash-body"><?php echo htmlspecialchars($_GET['success']); ?></div>
            </div>
        </div>
    <?php elseif ($dup_flash['message'] !== '' || (isset($_GET['error']) && $_GET['error'] !== '')): ?>
        <?php
        $flash_error_msg = $dup_flash['message'] !== '' ? $dup_flash['message'] : (string) $_GET['error'];
        $existing_job_id = $dup_flash['existing_job_id'] > 0 ? $dup_flash['existing_job_id'] : (int) ($_GET['existing_job_id'] ?? 0);
        $existing_quote_id = $dup_flash['existing_quote_id'] > 0 ? $dup_flash['existing_quote_id'] : (int) ($_GET['existing_quote_id'] ?? 0);
        ?>
        <div class="jc-flash-wrap jc-flash-wrap--dup" id="jcDupFlashWrap" role="dialog" aria-modal="true" aria-labelledby="jcDupFlashTitle">
            <div class="jc-flash-card jc-flash-card--error">
                <button type="button" class="jc-flash-close" id="jcDupFlashClose" aria-label="Close">&times;</button>
                <div class="jc-flash-icon"><i class="fas fa-exclamation"></i></div>
                <div class="jc-flash-title" id="jcDupFlashTitle"><?php echo $existing_job_id > 0 ? 'Job card already used' : 'Error'; ?></div>
                <div class="jc-flash-body">
                    <?php echo htmlspecialchars($flash_error_msg); ?>
                    <?php if ($existing_job_id > 0): ?>
                        <?php $jc_existing_edit_href = $erp_admin_base_path . 'JobCard/add_job_card.php?edit_id=' . (int) $existing_job_id; ?>
                        <br><a href="<?php echo htmlspecialchars($jc_existing_edit_href, ENT_QUOTES, 'UTF-8'); ?>" class="jc-flash-link" id="jcDupEditLink"><i class="fas fa-pen-to-square"></i> Edit existing job card</a>
                    <?php endif; ?>
                    <?php if ($existing_quote_id > 0): ?>
                        <?php $jc_existing_quote_href = $erp_admin_base_path . 'Quotation/add_quotation.php?id=' . (int) $existing_quote_id; ?>
                        <br><a href="<?php echo htmlspecialchars($jc_existing_quote_href, ENT_QUOTES, 'UTF-8'); ?>" class="jc-flash-link" id="jcDupQuoteLink"><i class="fas fa-file-invoice"></i> Open existing quotation</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="jc-flash-wrap" id="jcOpenQuoteWrap" aria-hidden="true">
        <div class="jc-flash-card jc-flash-card--info">
            <div class="jc-flash-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="jc-flash-title">Preparing Quotation</div>
            <div class="jc-flash-body">Saving the job card and opening quotation...</div>
        </div>
    </div>

    <form id="jobCardForm" action="<?php echo htmlspecialchars($jc_form_action, ENT_QUOTES, 'UTF-8'); ?>" method="POST">
        <?php if ($edit_payload): ?>
            <input type="hidden" name="edit_id" value="<?php echo (int)$edit_payload['id']; ?>">
        <?php endif; ?>
        <?php if ($quote_id > 0): ?>
            <input type="hidden" name="quote_id" value="<?php echo (int)$quote_id; ?>">
        <?php endif; ?>

        <!-- SECTION 1: matches physical job card (header + two columns) -->
        <div class="jc-form-modern">

        <div class="jc-m-card jc-section1" data-jc-jump>
            <p class="jc-section1-addr">Lafrenz Industrial &middot; Rensburger Street &middot; Erf 174LL &middot; Unit 18<br>
                Cell: +264 81 446 9962 &middot; svautotruckrepairs@gmail.com<br>
                PO Box 21292 &middot; Windhoek &middot; Namibia &middot; Reg cc/2015/13178</p>
            <div class="jc-section1-title-row">
                <h2 class="jc-section1-doc-title">Job card</h2>
                <div class="jc-section1-no">
                    <label for="jc_card_number_input">No.</label>
                    <input type="text" class="jc-m-input" name="card_number" id="jc_card_number_input" required autocomplete="off" placeholder="e.g. 5988">
                </div>
            </div>
            <p class="jc-m-hint" style="margin:0 0 1rem;">Enter the number already printed on the physical job card.</p>
            <?php if (!$edit_payload): ?>
            <div id="jcExistingCardNotice" class="hidden" style="margin:0 0 1rem;padding:0.65rem 0.85rem;background:#fff7ed;border:1px solid #fdba74;border-radius:0.5rem;font-size:0.875rem;color:#9a3412;"></div>
            <?php endif; ?>

            <div class="jc-m-grid-2 jc-section1-cols">
                <div class="jc-section1-col">
                    <h3 class="jc-section1-col-title">To</h3>
                    <div class="jc-m-field"><label for="clientNameDisplay">Bill to / client name</label>
                        <input type="text" class="jc-m-input" name="client_name_display" id="clientNameDisplay" list="clientsList" placeholder="Type to search saved clients" autocomplete="off">
                        <input type="hidden" name="client_id" id="clientIdHidden">
                        <datalist id="clientsList">
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>" data-id="<?= $c['id'] ?>" data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>" data-address="<?= htmlspecialchars($c['address'] ?? '') ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="jc-m-field"><label>Contact No.</label><input class="jc-m-input" type="text" name="contact_no" id="contactNo"></div>
                    <div class="jc-m-field jc-address-stack">
                        <label>Address</label>
                        <input class="jc-m-input" type="text" name="to_line1" id="to_line1" placeholder="Line 1" autocomplete="street-address">
                        <input class="jc-m-input jc-address-stack-line" type="text" name="to_line2" id="to_line2" placeholder="Line 2" autocomplete="address-line2">
                        <input class="jc-m-input jc-address-stack-line" type="text" name="to_line3" id="to_line3" placeholder="Line 3" autocomplete="address-line3">
                    </div>
                    <div class="jc-m-field"><label>Contact person</label><input class="jc-m-input" type="text" name="contact_person"></div>
                </div>
                <div class="jc-section1-col">
                    <h3 class="jc-section1-col-title">Vehicle &amp; references</h3>
                    <div class="jc-m-field"><label>Date</label><input class="jc-m-input" type="date" name="job_date" value="<?= date('Y-m-d') ?>"></div>
                    <div class="jc-m-field"><label>VIN No.</label><input class="jc-m-input" type="text" name="vin_no" id="vinNo"></div>
                    <div class="jc-m-field"><label>Kilometres</label><input class="jc-m-input" type="text" name="kilometre"></div>
                    <div class="jc-m-field"><label>Fleet No.</label><input class="jc-m-input" type="text" name="fleet_no"></div>
                    <div class="jc-m-field"><label>Vehicle Reg. No.</label>
                        <select class="jc-m-input" name="vehicle_id" id="vehicleSelect" onchange="handleVehicleChange()">
                            <option value="">Select vehicle...</option>
                            <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" data-vin="<?= htmlspecialchars($v['vin_no'] ?? '') ?>" data-model="<?= htmlspecialchars($v['model']) ?>"><?= htmlspecialchars($v['reg_no']) ?></option>
                            <?php endforeach; ?>
                            <option value="other">+ Enter registration manually</option>
                        </select>
                        <input type="text" class="jc-m-input" name="manual_vehicle_reg" id="manualVehicleInput" style="display:none;margin-top:0.5rem;" placeholder="Registration number">
                    </div>
                    <div class="jc-m-field"><label>Model</label><input class="jc-m-input" type="text" name="model_reg" id="modelReg"></div>
                    <div class="jc-m-field"><label>Purchase order No.</label><input class="jc-m-input" type="text" name="purchase_order_no"></div>
                    <div class="jc-m-field"><label>Quotation No.</label><input class="jc-m-input" type="text" name="quotation_no"></div>
                    <div class="jc-m-field"><label>Invoice No.</label><input class="jc-m-input" type="text" name="invoice_no"></div>
                </div>
            </div>
        </div>

        <div class="jc-m-card" data-jc-jump>
            <div class="jc-m-table-wrap">
                <table class="jc-m-table jc-m-table-signoff">
                    <thead><tr><th>Technician Name</th><th>Customer signed</th><th>Customer did not sign</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><textarea class="jc-m-input" name="technician_no" rows="2"></textarea></td>
                            <td><label class="jc-sign-choice"><input type="radio" name="customer_sig" value="Customer signed"><span>Yes</span></label></td>
                            <td><label class="jc-sign-choice"><input type="radio" name="customer_sig" value="Customer did not sign"><span>No</span></label></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        </div><!-- jc-form-modern -->

<?php
$jc_cfg = is_array($prefill_payload) ? $prefill_payload : [];
include __DIR__ . '/jc_quotation_config.inc.php';
?>
<script>
<?php
$jc_qc_js_path = __DIR__ . '/jc_quotation_config.js';
$jc_qc_js = is_readable($jc_qc_js_path) ? (string) file_get_contents($jc_qc_js_path) : '';
if ($jc_qc_js !== '') {
    $jc_qc_js = str_replace(
        "            var tr = document.createElement('tr');\n            tr.innerHTML =\n                '<td class=\"jc-qt-config-row-label\">",
        "            var tr = document.createElement('tr');\n            tr.className = 'jc-qt-config-parts-data-row';\n            tr.innerHTML =\n                '<td class=\"jc-qt-config-row-label\">",
        $jc_qc_js
    );
    $jc_qc_js = str_replace(
        "                var trCall = document.createElement('tr');\n                trCall.innerHTML =",
        "                var trCall = document.createElement('tr');\n                trCall.className = 'jc-qt-config-callout-data-row';\n                trCall.innerHTML =",
        $jc_qc_js
    );
}
echo $jc_qc_js;
?>
</script>





        <div class="no-print bg-white rounded-xl border border-gray-200 p-5 mt-4 shadow-sm mb-6" id="jcJumpActions" data-jc-jump>
            <div class="flex flex-wrap gap-3 justify-end items-center">
                    <?php if (!$edit_payload): ?>
                    <button type="button" class="aq-s1-btn aq-s1-btn--gray jc-start-over-btn" title="Clear draft and start a new job card">
                        <i class="fas fa-rotate-right" aria-hidden="true"></i> Start over
                    </button>
                    <?php endif; ?>
                    <button type="button" id="jc-preview-btn" class="aq-s1-btn aq-s1-btn--blue"><i class="fas fa-eye"></i> Preview</button>
                    <?php if ($edit_payload): ?>
                        <button type="submit" id="jc-update-job-card-btn" name="submit_action" value="save_only" class="aq-s1-btn aq-s1-btn--save"><i class="fas fa-save"></i> Update Job Card</button>
                        <?php if ($quote_id > 0): ?>
                            <button type="submit" name="submit_action" value="update_quotation" class="aq-s1-btn aq-s1-btn--save"><i class="fas fa-file-signature"></i> Update Quotation</button>
                        <?php else: ?>
                            <button type="submit" name="submit_action" value="open_quotation" class="aq-s1-btn aq-s1-btn--save"><i class="fas fa-file-invoice"></i> Open Quotation</button>
                        <?php endif; ?>
                        <a class="aq-s1-btn aq-s1-btn--danger" id="jc-edit-del-btn" href="<?php echo htmlspecialchars($jc_admin_web_base . 'JobCard/delete_job_card.php?id=' . (int) $edit_payload['id'], ENT_QUOTES, 'UTF-8'); ?>" onclick="return openEditDeleteModal(event);"><i class="fas fa-trash"></i> Delete</a>
                    <?php else: ?>
                        <button id="jc-save-job-card-btn" type="submit" name="submit_action" value="save_only" class="aq-s1-btn aq-s1-btn--save"><i class="fas fa-save"></i> Save Job Card</button>
                        <button type="button" id="jc-open-quotation-disabled-btn" class="aq-s1-btn aq-s1-btn--green-outline" disabled title="Save Job Card first"><i class="fas fa-file-invoice"></i> Open Quotation</button>
                    <?php endif; ?>
            </div>
        </div>
        <div class="jc-del-modal" id="jcEditDelModal" aria-hidden="true">
            <div class="jc-del-card" role="dialog" aria-modal="true" aria-labelledby="jcEditDelTitle">
                <div class="jc-del-title" id="jcEditDelTitle">Confirm Deletion</div>
                <div class="jc-del-body">Are you sure you want to continue deleting this job card? This action moves it to the recycle bin.</div>
                <div class="jc-del-actions">
                    <button type="button" class="aq-s1-btn jc-del-cancel" onclick="closeEditDeleteModal()">Cancel</button>
                    <a id="jcEditDelConfirmBtn" class="aq-s1-btn jc-del-confirm" href="#">Yes, Delete</a>
                </div>
            </div>
        </div>
        <div class="jc-del-modal" id="jcStartOverModal" aria-hidden="true">
            <div class="jc-del-card" role="dialog" aria-modal="true" aria-labelledby="jcStartOverTitle">
                <div class="jc-del-title" id="jcStartOverTitle"><i class="fas fa-rotate-right" aria-hidden="true" style="margin-right:6px;color:#ea580c;"></i> Start over</div>
                <div class="jc-del-body">Clear this draft and start a new job card? All unsaved fields will be removed.</div>
                <div class="jc-del-actions">
                    <button type="button" class="aq-s1-btn jc-del-cancel" id="jcStartOverCancelBtn">Cancel</button>
                    <button type="button" class="aq-s1-btn jc-so-confirm" id="jcStartOverConfirmBtn">Yes, start over</button>
                </div>
            </div>
        </div>

    </form>
    <nav class="jc-scroll-jump no-print" id="jcScrollJump" aria-label="Jump between sections">
        <button type="button" class="jc-scroll-jump-btn" id="jcScrollJumpUp" title="Previous section" aria-label="Previous section">
            <i class="fas fa-chevron-up" aria-hidden="true"></i>
        </button>
        <button type="button" class="jc-scroll-jump-btn" id="jcScrollJumpDown" title="Next section" aria-label="Next section">
            <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
    </nav>
</div><!-- jc-modern-app -->
</div><!-- content-wrapper -->

<div id="jc-preview-shell" class="jc-preview-shell no-print" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="jc-preview-title">
  <div class="jc-preview-shell-inner">
    <div class="jc-preview-toolbar">
      <h2 id="jc-preview-title" class="jc-preview-toolbar-title">Job Card Preview</h2>
      <p class="jc-preview-toolbar-sub">Paper layout — matches what you print or give the customer</p>
    </div>
    <div class="jc-preview-doc-frame">
      <iframe id="jc-preview-frame" title="Job card document preview"></iframe>
    </div>
    <div class="jc-preview-footer">
      <button type="button" id="jc-preview-print-blank" class="aq-s1-btn aq-s1-btn--blue"><i class="fas fa-print"></i> Print Blank</button>
      <button type="button" id="jc-preview-close" class="aq-s1-btn aq-s1-btn--gray"><i class="fas fa-times"></i> Close</button>
      <button type="button" id="jc-preview-print" class="aq-s1-btn aq-s1-btn--green"><i class="fas fa-print"></i> Print Job Card</button>
    </div>
  </div>
</div>

<script>
function openEditDeleteModal(event) {
    if (event) event.preventDefault();
    const modal = document.getElementById('jcEditDelModal');
    const confirmBtn = document.getElementById('jcEditDelConfirmBtn');
    const deleteLink = document.getElementById('jc-edit-del-btn');
    if (confirmBtn && deleteLink) confirmBtn.href = deleteLink.href;
    if (modal) modal.classList.add('show');
    return false;
}
function closeEditDeleteModal() {
    const modal = document.getElementById('jcEditDelModal');
    if (modal) modal.classList.remove('show');
}
document.addEventListener('click', function (e) {
    const modal = document.getElementById('jcEditDelModal');
    if (!modal || !modal.classList.contains('show')) return;
    if (e.target === modal) closeEditDeleteModal();
});
function openStartOverModal() {
    const modal = document.getElementById('jcStartOverModal');
    if (modal) {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }
}
function closeStartOverModal() {
    const modal = document.getElementById('jcStartOverModal');
    if (modal) {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }
}
document.addEventListener('click', function (e) {
    const modal = document.getElementById('jcStartOverModal');
    if (!modal || !modal.classList.contains('show')) return;
    if (e.target === modal) closeStartOverModal();
});
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    const modal = document.getElementById('jcStartOverModal');
    if (modal && modal.classList.contains('show')) closeStartOverModal();
});
function closeDupFlash() {
    const dupWrap = document.getElementById('jcDupFlashWrap');
    if (!dupWrap) return;
    dupWrap.classList.remove('show');
    setTimeout(function () { dupWrap.remove(); }, 220);
    try {
        const url = new URL(window.location.href);
        url.searchParams.delete('error');
        url.searchParams.delete('existing_job_id');
        url.searchParams.delete('dup_err');
        window.history.replaceState({}, '', url.pathname + url.search);
    } catch (e) {}
}
window.addEventListener('load', function () {
    const dupWrap = document.getElementById('jcDupFlashWrap');
    if (dupWrap) {
        requestAnimationFrame(function () { dupWrap.classList.add('show'); });
        dupWrap.addEventListener('click', function (e) {
            if (e.target === dupWrap) closeDupFlash();
        });
        const dupCloseBtn = document.getElementById('jcDupFlashClose');
        if (dupCloseBtn) dupCloseBtn.addEventListener('click', closeDupFlash);
        document.addEventListener('keydown', function onDupEsc(e) {
            if (e.key === 'Escape') {
                closeDupFlash();
                document.removeEventListener('keydown', onDupEsc);
            }
        });
        return;
    }
    const wrap = document.getElementById('jcFlashWrap');
    if (!wrap) return;
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    setTimeout(function () {
        wrap.classList.remove('show');
        setTimeout(function () { wrap.remove(); }, 220);
    }, 3200);
});

let __jcOpenQuotationSubmitting = false;
const __jcForm = document.getElementById('jobCardForm');
if (__jcForm) {
    const updateJobCardBtn = document.getElementById('jc-update-job-card-btn');
    if (updateJobCardBtn) {
        updateJobCardBtn.addEventListener('click', function (event) {
            event.preventDefault();
            const cardNo = document.getElementById('jc_card_number_input');
            if (cardNo && cardNo.value.trim() === '') {
                cardNo.setCustomValidity('Job card number is required.');
                cardNo.reportValidity();
                return;
            }
            if (cardNo) cardNo.setCustomValidity('');
            let action = __jcForm.querySelector('input[type="hidden"][name="submit_action"]');
            if (!action) {
                action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'submit_action';
                __jcForm.appendChild(action);
            }
            action.value = 'save_only';
            updateJobCardBtn.disabled = true;
            updateJobCardBtn.innerHTML = '<i class="fas fa-save"></i> Updating...';
            __jcForm.submit();
        });
    }

    // Ensure the Save button for non-edit mode reliably submits the form in all browsers
    const saveJobCardBtn = document.getElementById('jc-save-job-card-btn');
    if (saveJobCardBtn) {
        saveJobCardBtn.addEventListener('click', function (event) {
            // Let native validation run first; if invalid, browser will stop submission.
            // But for robustness, ensure card number validity and explicitly submit.
            const cardNo = document.getElementById('jc_card_number_input');
            if (cardNo && cardNo.value.trim() === '') {
                cardNo.setCustomValidity('Job card number is required.');
                cardNo.reportValidity();
                event.preventDefault();
                return;
            }
            if (cardNo) cardNo.setCustomValidity('');

            // create/ensure hidden submit_action input exists for older browsers
            let action = __jcForm.querySelector('input[type="hidden"][name="submit_action"]');
            if (!action) {
                action = document.createElement('input');
                action.type = 'hidden';
                action.name = 'submit_action';
                __jcForm.appendChild(action);
            }
            action.value = 'save_only';

            // disable the button to prevent duplicate clicks and submit
            saveJobCardBtn.disabled = true;
            saveJobCardBtn.innerHTML = '<i class="fas fa-save"></i> Saving...';

            // Use requestSubmit when available so event.submitter is set; fallback to submit()
            if (typeof __jcForm.requestSubmit === 'function') {
                __jcForm.requestSubmit(saveJobCardBtn);
            } else {
                __jcForm.submit();
            }
        });
    }

    __jcForm.addEventListener('submit', function (event) {
        const submitter = event.submitter;
        if (!submitter || __jcOpenQuotationSubmitting) return;
        if ((submitter.name || '') !== 'submit_action' || (submitter.value || '') !== 'open_quotation') return;
        event.preventDefault();
        const wrap = document.getElementById('jcOpenQuoteWrap');
        if (wrap) wrap.classList.add('show');
        setTimeout(function () {
            __jcOpenQuotationSubmitting = true;
            if (typeof __jcForm.requestSubmit === 'function') {
                __jcForm.requestSubmit(submitter);
            } else {
                __jcForm.submit();
            }
        }, 450);
    });
}
</script>

<script>
document.getElementById('clientNameDisplay').addEventListener('input', function() {
    const val = this.value;
    const options = document.querySelectorAll('#clientsList option');
    const hiddenId = document.getElementById('clientIdHidden');
    const line1 = document.getElementById('to_line1');
    const line2 = document.getElementById('to_line2');
    const line3 = document.getElementById('to_line3');
    let matched = false;
    options.forEach(opt => {
        if (opt.value === val) {
            matched = true;
            hiddenId.value = opt.dataset.id || '';
            document.getElementById('contactNo').value = opt.dataset.phone || '';
            const addrParts = String(opt.dataset.address || '').split(/\r?\n/).map((s) => s.trim()).filter(Boolean);
            if (line1) line1.value = addrParts[0] || '';
            if (line2) line2.value = addrParts[1] || '';
            if (line3) line3.value = addrParts.length > 2 ? addrParts.slice(2).join(', ') : '';
        }
    });
    if (!matched) {
        hiddenId.value = '';
    }
});

function handleVehicleChange() {
    const select = document.getElementById('vehicleSelect');
    const input = document.getElementById('manualVehicleInput');
    const opt = select.selectedOptions[0];
    
    if (select.value === 'other') {
        select.style.display = 'none';
        input.style.display = 'block';
        document.getElementById('vinNo').value = '';
        document.getElementById('modelReg').value = '';
    } else {
        select.style.display = 'block';
        input.style.display = 'none';
        if (opt && opt.value) {
            document.getElementById('vinNo').value = opt.dataset.vin || '';
            document.getElementById('modelReg').value = opt.dataset.model || '';
        } else {
            document.getElementById('vinNo').value = '';
            document.getElementById('modelReg').value = '';
        }
    }
}

(function enforceJobCardRules() {
    var form = document.getElementById('jobCardForm');
    if (!form) return;

    // Labor & service details are optional for saving a job card.
    ['serviceType', 'workDate', 'startTime', 'endTime'].forEach(function (id) {
        var field = document.getElementById(id);
        if (field) field.removeAttribute('required');
    });

    var cardNo = document.getElementById('jc_card_number_input');
    if (!cardNo) return;

    cardNo.addEventListener('input', function () {
        if (cardNo.value.trim() === '') {
            cardNo.setCustomValidity('Job card number is required.');
        } else {
            cardNo.setCustomValidity('');
        }
    });

    if (!window.__JC_EDIT_MODE) {
        var existingNotice = document.getElementById('jcExistingCardNotice');
        var lookupTimer;
        function hideExistingNotice() {
            if (existingNotice) {
                existingNotice.classList.add('hidden');
                existingNotice.innerHTML = '';
            }
        }
        function showExistingNotice(editUrl, cardNum) {
            if (!existingNotice || !editUrl) return;
            existingNotice.classList.remove('hidden');
            existingNotice.innerHTML = 'Job card <strong>' + cardNum + '</strong> already exists. '
                + '<a href="' + editUrl + '" style="color:#c2410c;font-weight:600;text-decoration:underline;">Open in edit mode</a> '
                + 'to update it (use <strong>Update Job Card</strong>, not Save).';
        }
        function lookupExistingCard() {
            var cn = cardNo.value.trim();
            if (!/^\d{4}$/.test(cn)) {
                hideExistingNotice();
                return;
            }
            fetch('JobCard/add_job_card.php?check_card=' + encodeURIComponent(cn), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.exists && data.edit_url) {
                        showExistingNotice(data.edit_url, cn);
                    } else {
                        hideExistingNotice();
                    }
                })
                .catch(function () { hideExistingNotice(); });
        }
        cardNo.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(lookupExistingCard, 400);
        });
        cardNo.addEventListener('blur', lookupExistingCard);
        if (/^\d{4}$/.test(cardNo.value.trim())) {
            lookupExistingCard();
        }
    }

    form.addEventListener('submit', function (e) {
        // Keep this mandatory regardless of other optional sections.
        if (cardNo.value.trim() === '') {
            cardNo.setCustomValidity('Job card number is required.');
            cardNo.reportValidity();
            e.preventDefault();
            return;
        }
        cardNo.setCustomValidity('');
    });
})();

(function initRatesPanelToggle_removed() {
    var btn = document.getElementById('aq-toggle-rates');
    var panel = document.getElementById('aq-rates-panel');
    var chevron = document.getElementById('aq-chevron');
    if (!btn || !panel || !chevron) return;

    btn.addEventListener('click', function () {
        panel.classList.toggle('hidden');
        chevron.style.transform = panel.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
    });
})();

(function initJcQuotationConfigRowDelete() {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.jcq-del-row');
        if (!btn) return;
        var tr = btn.closest('tr');
        var tbody = tr ? tr.parentElement : null;
        if (!tr || !tbody) return;
        if (tbody.querySelectorAll('tr').length <= 1) return;
        tr.remove();
    });
})();
</script>

<script>
(function initJobCardPreviewAndBlankPrint() {
    const previewBtn = document.getElementById('jc-preview-btn');
    const form = document.getElementById('jobCardForm');
    if (!form || !previewBtn) return;

    const logoUrl = '' . app_url('assets/') . 'companylogo2.png';

    function esc(v) {
        return String(v ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    function val(name) {
        const el = form.querySelector(`[name="${name}"]`);
        return el ? el.value : '';
    }
    function buildTemplate(blankMode) {
        const clientName = blankMode ? '' : val('client_name_display');
        const address1 = blankMode ? '' : val('to_line1');
        const address2 = blankMode ? '' : val('to_line2');
        const address3 = blankMode ? '' : val('to_line3');
        const contactNo = blankMode ? '' : val('contact_no');
        const contactPerson = blankMode ? '' : val('contact_person');

        const vehicleSel = form.querySelector('[name="vehicle_id"]');
        let regNo = '';
        if (!blankMode && vehicleSel) {
            const so = vehicleSel.selectedOptions && vehicleSel.selectedOptions[0];
            regNo = so && so.value && so.value !== 'other' ? so.textContent : val('manual_vehicle_reg');
        }

        const rightRows = [
            blankMode ? '' : val('job_date'),
            blankMode ? '' : val('vin_no'),
            blankMode ? '' : val('kilometre'),
            blankMode ? '' : val('fleet_no'),
            blankMode ? '' : regNo,
            blankMode ? '' : val('model_reg'),
            blankMode ? '' : val('purchase_order_no'),
            blankMode ? '' : val('quotation_no'),
            blankMode ? '' : val('invoice_no')
        ];

        const technicianNo = blankMode ? '' : val('technician_no');
        const sig = blankMode ? '' : (form.querySelector('[name="customer_sig"]:checked')?.value || '');
        const cardNo = blankMode ? '' : val('card_number').trim();

        return `<!doctype html>
<html>
<head>
<meta charset="utf-8"/>
<title>Job Card ${blankMode ? 'Blank' : 'Preview'}</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: Arial, sans-serif; background: #fff; color: #111; }
  .wrap { padding: 8px; }
  .page { width: 100%; max-width: 860px; margin: 0 auto 10px; background: #fff9c4; border: 2px solid #111; padding: 6px; }
  .header { display: grid; grid-template-columns: 335px 1fr; align-items: center; gap: 12px; margin-top: 6px; }
  .header img { max-width: 320px; height: auto; }
  .hdr-txt { text-align: center; font-size: 15px; font-weight: 700; line-height: 1.28; padding-right: 6px; }
  .title { position: relative; text-align: center; margin: 14px 0 8px; min-height: 56px; }
  .title-main { font-size: 46px; font-weight: 900; letter-spacing: 1.5px; line-height: 1; display: inline-block; }
  .title-no { position: absolute; right: 14px; top: 56%; transform: translateY(-50%); font-size: 18px; font-weight: 800; }
  .title-no-val { color: #b91c1c; font-weight: 900; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  td, th { border: 1px solid #111; padding: 2px 4px; font-size: 12px; height: 22px; vertical-align: middle; }
  .tight td, .tight th { height: 20px; font-size: 11px; }
  .main-info td { height: 24px; font-size: 12px; }
  .attr { font-weight: 400; padding-left: 6px; }
  .val  { padding-left: 8px; font-weight: 800; }
  .val-inline { font-weight: 800 !important; }
  .sec { font-weight: 900; text-align: center; text-transform: uppercase; letter-spacing: 1px; }
  .cond { font-size: 9px; line-height: 1.25; border: 1px solid #111; border-top: none; padding: 4px; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
  .page-break { page-break-before: always; }
  @media print { body { background:#fff; } .page { margin:0 auto 0; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="page">
    <div class="header">
      <img src="${logoUrl}" alt="SV Auto logo"/>
      <div class="hdr-txt">
        Lafrenz Industrial · Rensburger Street · Erf 174LL · Unit 18<br>
        Cell: +264 81 44 9962 · svautotruckrepairs@gmail.com<br>
        PO Box 21292 · Windhoek · Namibia<br>
        Reg No. cc/2015/1378
      </div>
    </div>

    <div class="title">
      <span class="title-main">JOB CARD</span>
      <span class="title-no">No.&nbsp;<span class="title-no-val">${esc(cardNo)}</span></span>
    </div>

    <table class="tight main-info">
      <colgroup>
        <col style="width:12%">
        <col style="width:38%">
        <col style="width:18%">
        <col style="width:32%">
      </colgroup>
      <tr><td class="attr" colspan="2">To</td><td class="attr">Date</td><td class="val">${esc(rightRows[0])}</td></tr>
      <tr><td class="val" colspan="2">${esc(clientName)}</td><td class="attr">VIN No.</td><td class="val">${esc(rightRows[1])}</td></tr>
      <tr><td class="val" colspan="2">${esc(address1)}</td><td class="attr">Kilometres</td><td class="val">${esc(rightRows[2])}</td></tr>
      <tr><td class="val" colspan="2">${esc(address2)}</td><td class="attr">Fleet No.</td><td class="val">${esc(rightRows[3])}</td></tr>
      <tr><td class="attr">Contact No.</td><td class="val">${esc(contactNo)}</td><td class="attr">Vehicle Reg. No.</td><td class="val">${esc(rightRows[4])}</td></tr>
      <tr><td></td><td></td><td class="attr">Model</td><td class="val">${esc(rightRows[5])}</td></tr>
      <tr><td class="attr">Address</td><td class="val">${esc(address3)}</td><td class="attr">Purchase Order No.</td><td class="val">${esc(rightRows[6])}</td></tr>
      <tr><td></td><td></td><td class="attr">Quotation No.</td><td class="val">${esc(rightRows[7])}</td></tr>
      <tr><td class="attr">Contact person</td><td class="val">${esc(contactPerson)}</td><td class="attr">Invoice No.</td><td class="val">${esc(rightRows[8])}</td></tr>
      <tr><td></td><td></td><td></td><td></td></tr>
    </table>

    <div style="height:14px;"></div>
    <table class="tight">
      <tr>
        <td colspan="3">Technician Name: <span class="val-inline">${esc(technicianNo)}</span></td>
      </tr>
      <tr>
        <td colspan="3">Customer signature: <span class="val-inline">${esc(sig)}</span></td>
      </tr>
    </table>
  </div>
</div>
</body>
</html>`;
    }

    function openPrintPreview(blankMode) {
        const popup = window.open('', '_blank');
        if (!popup) return;
        popup.document.open();
        popup.document.write(buildTemplate(blankMode));
        popup.document.close();
        const runPrint = function () {
            popup.focus();
            popup.print();
        };
        if (popup.document.readyState === 'complete') {
            setTimeout(runPrint, 250);
        } else {
            popup.onload = function () { setTimeout(runPrint, 250); };
        }
        popup.onafterprint = function () {
            try { popup.close(); } catch (e) {}
        };
    }

    function printBlankFromPreview() {
        closePreviewShell();
        setTimeout(function () { openPrintPreview(true); }, 80);
    }

    const previewShell = document.getElementById('jc-preview-shell');
    const previewFrame = document.getElementById('jc-preview-frame');

    function openPreviewShell() {
        if (!previewShell || !previewFrame) return;
        previewFrame.srcdoc = buildTemplate(false);
        previewShell.classList.add('is-open');
        previewShell.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closePreviewShell() {
        if (!previewShell) return;
        previewShell.classList.remove('is-open');
        previewShell.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        if (previewFrame) previewFrame.srcdoc = '';
    }

    function printFromPreview() {
        closePreviewShell();
        const w = window.open('', '_blank');
        if (!w) return;
        w.document.open();
        w.document.write(buildTemplate(false));
        w.document.close();
        const runPrint = function () {
            w.focus();
            w.print();
        };
        if (w.document.readyState === 'complete') {
            setTimeout(runPrint, 250);
        } else {
            w.onload = function () { setTimeout(runPrint, 250); };
        }
        w.onafterprint = function () {
            try { w.close(); } catch (e) {}
        };
    }

    previewBtn.addEventListener('click', openPreviewShell);
    const closeBtn = document.getElementById('jc-preview-close');
    if (closeBtn) closeBtn.addEventListener('click', closePreviewShell);
    const printBlankBtn = document.getElementById('jc-preview-print-blank');
    if (printBlankBtn) printBlankBtn.addEventListener('click', printBlankFromPreview);
    const printLiveBtn = document.getElementById('jc-preview-print');
    if (printLiveBtn) printLiveBtn.addEventListener('click', printFromPreview);

    if (previewShell) {
        previewShell.addEventListener('click', function (e) {
            if (e.target === previewShell) closePreviewShell();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && previewShell && previewShell.classList.contains('is-open')) {
            closePreviewShell();
        }
    });
})();
</script>

<?php if ($prefill_payload): ?>
<script>
(function initEditModePrefill() {
    const payload = <?php echo json_encode($prefill_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const form = document.getElementById('jobCardForm');
    if (!form || !payload) return;
    <?php if ($edit_payload): ?>
    window.__JC_EDIT_MODE = true;
    <?php else: ?>
    window.__JC_REPERSIST_MODE = true;
    <?php endif; ?>

    const setSingle = (name, value) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) return;
        el.value = value == null ? '' : String(value);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };

    setSingle('card_number', payload.card_number);
    setSingle('client_id', payload.client_id);
    setSingle('client_name_display', payload.client_name_display);
    setSingle('contact_no', payload.contact_no);
    setSingle('to_line1', payload.to_line1);
    setSingle('to_line2', payload.to_line2);
    setSingle('to_line3', payload.to_line3);
    setSingle('contact_person', payload.contact_person);
    setSingle('job_date', payload.job_date);
    setSingle('vehicle_id', payload.vehicle_id);
    setSingle('vin_no', payload.vin_no);
    setSingle('kilometre', payload.kilometre);
    setSingle('fleet_no', payload.fleet_no);
    setSingle('manual_vehicle_reg', payload.manual_vehicle_reg);
    setSingle('model_reg', payload.model_reg);
    setSingle('purchase_order_no', payload.purchase_order_no);
    setSingle('quotation_no', payload.quotation_no);
    setSingle('invoice_no', payload.invoice_no);
    setSingle('technician_no', payload.technician_no);
    setSingle('rate_normal', payload.rate_normal);
    setSingle('rate_after', payload.rate_after);
    setSingle('rate_holiday', payload.rate_holiday);
    setSingle('vat_rate', payload.vat_rate);
    setSingle('attend_to_service_label', payload.attend_to_service_label);
    setSingle('diagnostic_label', payload.diagnostic_label);
    setSingle('blank_normal_lines', payload.blank_normal_lines);
    setSingle('blank_overtime_lines', payload.blank_overtime_lines);
    setSingle('blank_holiday_lines', payload.blank_holiday_lines);
    setSingle('blank_parts_lines', payload.blank_parts_lines);
    setSingle('blank_cons_lines', payload.blank_cons_lines);

    const setChecked = (name, value, fallback = true) => {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) return;
        el.checked = value == null ? fallback : !!value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
    };
    setChecked('show_normal_time', payload.show_normal_time);
    setChecked('show_overtime', payload.show_overtime);
    setChecked('show_public_holiday', payload.show_public_holiday);
    setChecked('print_blank_show_labour', payload.print_blank_show_labour);
    setChecked('print_blank_show_parts', payload.print_blank_show_parts);
    setChecked('blank_col_lab_hours', payload.blank_col_lab_hours);
    setChecked('blank_col_lab_rate', payload.blank_col_lab_rate);
    setChecked('blank_col_lab_total', payload.blank_col_lab_total);
    setChecked('blank_col_part_qty', payload.blank_col_part_qty);
    setChecked('blank_col_part_cost', payload.blank_col_part_cost);
    setChecked('blank_col_part_total', payload.blank_col_part_total);

    if (payload.customer_sig) {
        const radio = form.querySelector(`input[name="customer_sig"][value="${payload.customer_sig.replace(/"/g, '\\"')}"]`);
        if (radio) radio.checked = true;
    }
    if (window.jcQcInit) window.jcQcInit(payload);
    if (window.__JC_REPERSIST_MODE) {
        try {
            const snap = JSON.stringify(payload);
            const baseKey = 'draft_' + window.location.pathname;
            localStorage.setItem(baseKey, snap);
            localStorage.setItem(baseKey + window.location.search, snap);
        } catch (_) {}
    }
})();
</script>
<?php endif; ?>

<script>
window.__JC_DRAFT_API = <?php echo json_encode('../api/user_draft.php', JSON_UNESCAPED_SLASHES); ?>;
window.__JC_DRAFT_KEY = <?php echo json_encode($jc_draft_key, JSON_UNESCAPED_UNICODE); ?>;
window.__JC_DRAFT_META = {
    entity_type: 'job_card',
    entity_id: <?php echo $edit_id > 0 ? (int) $edit_id : 'null'; ?>,
    page_url: <?php echo json_encode($jc_draft_page_url, JSON_UNESCAPED_SLASHES); ?>
};
window.__JC_SERVER_DRAFT = <?php echo $jc_server_draft ? json_encode($jc_server_draft['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null'; ?>;
window.__JC_SERVER_DRAFT_AT = <?php echo $jc_server_draft ? json_encode((string) ($jc_server_draft['updated_at'] ?? ''), JSON_UNESCAPED_UNICODE) : 'null'; ?>;
</script>
<script>
(function() {
    const PAGE_KEY = 'draft_' + window.location.pathname + window.location.search;
    const BASE_PAGE_KEY = 'draft_' + window.location.pathname;
    const form = document.getElementById('jobCardForm') || document.querySelector('form');
    if (!form) return;

    const skipLocalRestore = !!(window.__JC_EDIT_MODE || window.__JC_REPERSIST_MODE);
    const skipServerDraftSave = !!window.__JC_REPERSIST_MODE;
    const draftApi = window.__JC_DRAFT_API || '../api/user_draft.php';
    const draftKey = window.__JC_DRAFT_KEY || 'job_card:new';
    const draftMeta = window.__JC_DRAFT_META || { entity_type: 'job_card', entity_id: null, page_url: 'JobCard/add_job_card.php' };
    const params = new URLSearchParams(window.location.search);

    function collectFormData() {
        if (window.jcQcSyncToForm) window.jcQcSyncToForm();
        const data = {};
        const multi = {};
        form.querySelectorAll('input, select, textarea').forEach(field => {
            if (!field.name) return;
            if (field.type === 'password' || field.type === 'file') return;
            const name = field.name;
            if (field.type === 'hidden' && name !== 'quotation_labour_sections' && name !== 'quotation_parts') return;
            if (field.type === 'checkbox') {
                data[name] = field.checked;
                return;
            }
            if (field.type === 'radio') {
                if (field.checked) data[name] = field.value;
                return;
            }
            if (name.endsWith('[]')) {
                if (!multi[name]) multi[name] = [];
                multi[name].push(field.value);
                return;
            }
            if (!(name in data)) data[name] = field.value;
        });
        Object.keys(multi).forEach(function (k) { data[k] = multi[k]; });
        const secEl = document.getElementById('jcQcSectionsJson');
        const partsEl = document.getElementById('jcQcPartsJson');
        if (secEl && secEl.value) {
            try { data.quotation_labour_sections = JSON.parse(secEl.value); } catch (_) {}
        }
        if (partsEl && partsEl.value) {
            try { data.quotation_parts = JSON.parse(partsEl.value); } catch (_) {}
        }
        return data;
    }

    function applyDraftData(data) {
        if (!data || typeof data !== 'object') return;
        if (data.quotation_labour_sections || data.quotation_parts) {
            if (window.jcQcInit) window.jcQcInit(data);
        }
        Object.keys(data).forEach(name => {
            if (name === 'quotation_labour_sections' || name === 'quotation_parts') return;
            const fields = form.querySelectorAll(`[name="${name}"]`);
            if (name.endsWith('[]') && Array.isArray(data[name])) {
                return;
            }
            fields.forEach(field => {
                if (!field) return;
                if (field.type === 'checkbox') {
                    field.checked = data[name] === true || data[name] === '1' || data[name] === field.value;
                } else if (field.type === 'radio') {
                    field.checked = data[name] === field.value;
                } else if (field.tagName === 'SELECT') {
                    field.value = data[name];
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    field.value = data[name];
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });
        if (window.jcQcSyncToForm) window.jcQcSyncToForm();
    }

    function hideDraftFlash() {
        const wrap = document.getElementById('jcDraftFlashWrap');
        if (!wrap) return;
        wrap.classList.remove('show');
        setTimeout(function () { wrap.remove(); }, 220);
    }

    function showDraftFlash(opts) {
        opts = opts || {};
        const kind = opts.kind === 'success' || opts.kind === 'error' ? opts.kind : 'info';
        const title = (opts.title || 'Notice').toString();
        const body = (opts.body || '').toString();
        const iconClass = kind === 'error' ? 'fa-exclamation' : (kind === 'success' ? 'fa-check' : 'fa-file-lines');
        const cardClass = kind === 'error' ? 'jc-flash-card--error' : (kind === 'success' ? 'jc-flash-card--success' : 'jc-flash-card--info');

        hideDraftFlash();
        const wrap = document.createElement('div');
        wrap.id = 'jcDraftFlashWrap';
        wrap.className = 'jc-flash-wrap';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-modal', 'true');
        wrap.setAttribute('aria-labelledby', 'jcDraftFlashTitle');

        const card = document.createElement('div');
        card.className = 'jc-flash-card ' + cardClass;

        const icon = document.createElement('div');
        icon.className = 'jc-flash-icon';
        icon.innerHTML = '<i class="fas ' + iconClass + '" aria-hidden="true"></i>';

        const titleEl = document.createElement('div');
        titleEl.className = 'jc-flash-title';
        titleEl.id = 'jcDraftFlashTitle';
        titleEl.textContent = title;

        const bodyEl = document.createElement('div');
        bodyEl.className = 'jc-flash-body';
        bodyEl.textContent = body;

        card.appendChild(icon);
        card.appendChild(titleEl);
        card.appendChild(bodyEl);
        wrap.appendChild(card);
        wrap.addEventListener('click', function (e) {
            if (e.target === wrap) hideDraftFlash();
        });
        document.body.appendChild(wrap);
        requestAnimationFrame(function () { wrap.classList.add('show'); });
        setTimeout(hideDraftFlash, typeof opts.duration === 'number' ? opts.duration : 3200);
    }

    function deleteServerDraft() {
        if (!draftKey) return;
        const body = new URLSearchParams({ action: 'delete', draft_key: draftKey });
        fetch(draftApi, { method: 'POST', body, credentials: 'same-origin' }).catch(() => {});
    }

    function saveServerDraft(data) {
        if (skipServerDraftSave || !draftKey) return;
        const title = (data.card_number || data.jc_card_number || '').toString().trim();
        const body = new URLSearchParams({
            action: 'save',
            draft_key: draftKey,
            entity_type: draftMeta.entity_type || 'job_card',
            page_url: draftMeta.page_url || 'JobCard/add_job_card.php',
            title: title || (draftMeta.entity_id ? 'Job card #' + draftMeta.entity_id : 'New job card'),
            payload: JSON.stringify(data)
        });
        if (draftMeta.entity_id != null) {
            body.set('entity_id', String(draftMeta.entity_id));
        }
        fetch(draftApi, { method: 'POST', body, credentials: 'same-origin' }).catch(() => {});
    }

    if (params.get('success')) {
        try {
            localStorage.removeItem(PAGE_KEY);
            localStorage.removeItem(BASE_PAGE_KEY);
            localStorage.removeItem('draft_' + window.location.pathname);
            localStorage.removeItem('draft_' + window.location.pathname + '?');
        } catch (_) {}
        deleteServerDraft();
    }

    if (!skipLocalRestore) {
            let saved = localStorage.getItem(PAGE_KEY) || localStorage.getItem(BASE_PAGE_KEY);
            if (saved) {
                try {
                    applyDraftData(JSON.parse(saved));
                    if (!params.get('error') && !params.get('dup_err')) {
                        showDraftFlash({ kind: 'info', title: 'Draft restored', body: 'Your unsaved job card changes were loaded into this form.' });
                    }
                } catch (_) {}
            } else if (window.__JC_SERVER_DRAFT) {
                try {
                    applyDraftData(window.__JC_SERVER_DRAFT);
                    if (!params.get('error') && !params.get('dup_err')) {
                        showDraftFlash({ kind: 'info', title: 'Draft restored', body: 'Your last saved session was loaded from the server.' });
                    }
                } catch (_) {}
            }
        } else if (window.__JC_SERVER_DRAFT && window.__JC_EDIT_MODE) {
            const when = window.__JC_SERVER_DRAFT_AT ? String(window.__JC_SERVER_DRAFT_AT) : '';
            const banner = document.createElement('div');
            banner.style.cssText = 'margin:0 auto 16px; max-width:1100px; padding:12px 16px; background:#fff7ed; border:2px solid #fdba74; border-radius:12px; font-size:14px; color:#9a3412; display:flex; flex-wrap:wrap; align-items:center; gap:12px;';
            banner.innerHTML = '<strong>Unsaved edits on server</strong>' + (when ? ' <span style="font-weight:600;opacity:.85">(' + when.replace('T', ' ').slice(0, 16) + ')</span>' : '') + ' &mdash; restore will overwrite fields on this form.';
            const restoreBtn = document.createElement('button');
            restoreBtn.type = 'button';
            restoreBtn.textContent = 'Restore';
            restoreBtn.style.cssText = 'background:#ea580c;color:#fff;border:none;padding:8px 14px;border-radius:8px;font-weight:700;cursor:pointer;';
            restoreBtn.onclick = function () {
                applyDraftData(window.__JC_SERVER_DRAFT);
                banner.remove();
                showDraftFlash({ kind: 'success', title: 'Draft restored', body: 'The saved server draft was applied to this form.' });
            };
            const discardBtn = document.createElement('button');
            discardBtn.type = 'button';
            discardBtn.textContent = 'Discard';
            discardBtn.style.cssText = 'background:#fff;color:#9a3412;border:1px solid #fdba74;padding:8px 14px;border-radius:8px;font-weight:600;cursor:pointer;';
            discardBtn.onclick = function () {
                deleteServerDraft();
                banner.remove();
            };
            banner.appendChild(restoreBtn);
            banner.appendChild(discardBtn);
            const shell = document.querySelector('.jc-modern-app') || form;
            if (shell && shell.parentNode) {
                shell.parentNode.insertBefore(banner, shell);
                } else {
                form.prepend(banner);
                }
    }

    let serverSaveTimeout;
    let saveTimeout;
    function showSavedIndicator() {
            let indicator = document.getElementById('draft-save-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'draft-save-indicator';
                indicator.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#22C55E; color:white; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.15); opacity:0; transition:opacity 0.3s;';
                document.body.appendChild(indicator);
            }
            indicator.textContent = 'Draft saved locally';
            indicator.style.opacity = '1';
            clearTimeout(indicator._hideTimer);
            indicator._hideTimer = setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
        }

        function doSave() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                const data = collectFormData();
                const payload = JSON.stringify(data);
                try {
                    localStorage.setItem(PAGE_KEY, payload);
                    localStorage.setItem(BASE_PAGE_KEY, payload);
                } catch (_) {}
                if (!skipServerDraftSave) {
                    clearTimeout(serverSaveTimeout);
                    serverSaveTimeout = setTimeout(() => saveServerDraft(data), 1200);
                }
                showSavedIndicator();
        }, 800);
    }

    form.addEventListener('input', doSave);
    form.addEventListener('change', doSave);
    form.addEventListener('submit', function () {
        clearTimeout(saveTimeout);
        clearTimeout(serverSaveTimeout);
        const data = collectFormData();
        try {
            localStorage.setItem(PAGE_KEY, JSON.stringify(data));
            localStorage.setItem(BASE_PAGE_KEY, JSON.stringify(data));
        } catch (_) {}
        if (!skipServerDraftSave) saveServerDraft(data);
    });

    function executeStartOver() {
        closeStartOverModal();
        try {
            localStorage.removeItem(PAGE_KEY);
            localStorage.removeItem(BASE_PAGE_KEY);
            localStorage.removeItem('draft_' + window.location.pathname);
            localStorage.removeItem('draft_' + window.location.pathname + '?');
        } catch (_) {}
        const cleanUrl = window.location.pathname;
        if (!draftKey) {
            window.location.href = cleanUrl;
            return;
        }
        const body = new URLSearchParams({ action: 'delete', draft_key: draftKey });
        fetch(draftApi, { method: 'POST', body, credentials: 'same-origin' })
            .catch(function () {})
            .finally(function () {
                window.location.href = cleanUrl;
            });
    }

    document.querySelectorAll('.jc-start-over-btn').forEach(function (btn) {
        btn.addEventListener('click', openStartOverModal);
    });
    const startOverConfirmBtn = document.getElementById('jcStartOverConfirmBtn');
    const startOverCancelBtn = document.getElementById('jcStartOverCancelBtn');
    if (startOverConfirmBtn) startOverConfirmBtn.addEventListener('click', executeStartOver);
    if (startOverCancelBtn) startOverCancelBtn.addEventListener('click', closeStartOverModal);
})();

(function () {
    var upBtn = document.getElementById('jcScrollJumpUp');
    var downBtn = document.getElementById('jcScrollJumpDown');
    if (!upBtn || !downBtn) return;

    var scrollOffset = 88;

    function getSections() {
        var list = [];
        var top = document.querySelector('.jc-modern-top[data-jc-jump]');
        if (top) list.push(top);
        document.querySelectorAll(
            '#jobCardForm .jc-form-modern [data-jc-jump], #jcQtConfigRoot, #jcJumpActions'
        ).forEach(function (el) {
            list.push(el);
        });
        return list;
    }

    function currentIndex(sections) {
        var y = window.scrollY + scrollOffset;
        var idx = 0;
        for (var i = 0; i < sections.length; i++) {
            if (sections[i].offsetTop <= y + 4) idx = i;
        }
        return idx;
    }

    function scrollToSection(el) {
        if (!el) return;
        var top = el.getBoundingClientRect().top + window.scrollY - scrollOffset;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    function refreshButtons() {
        var sections = getSections();
        if (!sections.length) {
            upBtn.disabled = true;
            downBtn.disabled = true;
            return;
        }
        var idx = currentIndex(sections);
        upBtn.disabled = idx <= 0;
        downBtn.disabled = idx >= sections.length - 1;
    }

    upBtn.addEventListener('click', function () {
        var sections = getSections();
        var idx = currentIndex(sections);
        scrollToSection(sections[Math.max(0, idx - 1)]);
    });

    downBtn.addEventListener('click', function () {
        var sections = getSections();
        var idx = currentIndex(sections);
        scrollToSection(sections[Math.min(sections.length - 1, idx + 1)]);
    });

    window.addEventListener('scroll', refreshButtons, { passive: true });
    window.addEventListener('resize', refreshButtons);
    refreshButtons();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
