<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

/* Card number comes from the physical job card — admin/tech enters it in the form (not auto-generated). */

$clients = $pdo->query("SELECT id, name, phone, email FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
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

// Load labor rates for labor_tracking_section.php
$laborRates = [];
try {
    $stmt = $pdo->query("SELECT rate_type, hourly_rate FROM labor_rates WHERE is_active = 1");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $laborRates[$row['rate_type']] = $row['hourly_rate'];
    }
} catch (Exception $e) {
    // Table doesn't exist yet - use defaults
    $laborRates = [
        'normal_hours' => 150,
        'after_hours' => 225,
        'weekend' => 300,
        'holiday' => 450
    ];
}

// Load mobile service rates
$mobileRates = ['callout_fee' => 500, 'per_km_rate' => 15, 'min_callout_distance' => 5];
try {
    $stmt = $pdo->query("SELECT * FROM mobile_service_rates WHERE id = 1 LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $mobileRates = $row;
    }
} catch (Exception $e) {
    // Table doesn't exist yet - use defaults
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<style>
/* Job Card Shell Styles - Preserved for print format */
.jc-shell {
    background: #FFF9C4;
    border: 2px solid #000;
    max-width: 1100px;
    margin: 0 auto 16px;
    font-family: Arial, sans-serif;
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
        .jc-modern-app {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .jc-modern-top-left { display: flex; align-items: center; gap: 0.75rem; }
        .jc-modern-back {
            padding: 0.5rem;
            color: #9ca3af;
            text-decoration: none;
            border-radius: 0.375rem;
        }
        .jc-modern-back:hover { color: #4b5563; background: #f3f4f6; }
        .jc-modern-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a202c;
            margin: 0;
            line-height: 1.25;
            letter-spacing: -0.02em;
        }
        .jc-modern-sub { font-size: 0.875rem; color: #4a5568; margin: 0.35rem 0 0; line-height: 1.5; }
        .jc-modern-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }
        .jc-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #2d3748;
            text-decoration: none;
            line-height: 1.4;
        }
        .jc-btn:hover:not(:disabled) { border-color: #cbd5e1; }
        .jc-btn-orange {
            background: #f59e0b;
            border: 1px solid #d97706;
            color: #fff;
        }
        .jc-btn-orange:hover:not(:disabled) { background: #d97706; border-color: #b45309; }
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
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #4a5568;
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
        .jc-m-field label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 0.35rem;
            line-height: 1.45;
        }
        .jc-m-input, .jc-m-textarea, .jc-form-modern select.jc-m-input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            font-size: 0.9375rem;
            font-family: inherit;
            background: #fff;
            color: #1a202c;
            line-height: 1.45;
        }
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
            color: #4a5568;
            font-weight: 600;
            text-align: left;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .jc-m-table td { border-bottom: 1px solid #f1f5f9; padding: 0.25rem 0.5rem; vertical-align: middle; }
        .jc-m-table tr:last-child td { border-bottom: none; }
        .jc-m-table td .jc-m-input { border: none; box-shadow: none; padding: 0.5rem 0.35rem; }
        .jc-m-table td .jc-m-input:focus { box-shadow: none; border: 1px solid #f59e0b; border-radius: 0.25rem; }
        .jc-m-conditions { font-size: 0.75rem; line-height: 1.5; color: #64748b; }
        .jc-m-conditions strong { color: #475569; }
        .jc-row-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.75rem; align-items: center; }
        /* Section 2: paper-style blocks (DESCRIPTION + PARTS SUPPLY) */
        .jc-m-table.jc-m-table--section2-head thead th {
            text-align: center;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
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
        @media print {
            .jc-modern-app { background: transparent !important; padding: 0 !important; max-width: none !important; }
            .jc-modern-top, .jc-modern-actions, .jc-card, .jc-form-modern .jc-m-card {
                background: transparent !important;
                box-shadow: none !important;
                border: 1px solid #e5e7eb !important;
                padding: 0.75rem !important;
            }
        }
    </style>

<div class="content-wrapper">

<div class="jc-modern-app">
    <div class="jc-modern-top">
        <div class="jc-modern-top-left">
            <a class="jc-modern-back" href="job_card.php" title="Back to job cards"><i class="fas fa-arrow-left"></i></a>
            <div>
                <h1 class="jc-modern-title">Create New Job Card</h1>
                <p class="jc-modern-sub">Section 1 matches the printed card: contact line, <strong>Job card</strong> title and <strong>No.</strong>, then To / vehicle columns.</p>
            </div>
        </div>
        <div class="jc-modern-actions no-print">
            <button type="button" class="jc-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            <button type="submit" form="jobCardForm" class="jc-btn jc-btn-orange"><i class="fas fa-file-invoice"></i> Save job card &amp; open quotation</button>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <form id="jobCardForm" action="/SV Auto Truck Repair/Admin/JobCard/create_job_card.php" method="POST">

        <!-- SECTION 1: matches physical job card (header + two columns) -->
        <div class="jc-form-modern">

        <div class="jc-m-card jc-section1">
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

            <div class="jc-m-grid-2 jc-section1-cols">
                <div class="jc-section1-col">
                    <h3 class="jc-section1-col-title">To</h3>
                    <div class="jc-m-field"><label for="clientNameDisplay">Bill to / client name</label>
                        <input type="text" class="jc-m-input" name="client_name_display" id="clientNameDisplay" list="clientsList" placeholder="Type to search saved clients" autocomplete="off">
                        <input type="hidden" name="client_id" id="clientIdHidden">
                        <datalist id="clientsList">
                            <?php foreach ($clients as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>" data-id="<?= $c['id'] ?>" data-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>" data-email="<?= htmlspecialchars($c['email'] ?? '') ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="jc-m-field"><label>Address line 2</label><input class="jc-m-input" type="text" name="to_line2" placeholder="Optional"></div>
                    <div class="jc-m-field"><label>Address line 3</label><input class="jc-m-input" type="text" name="to_line3" placeholder="Optional"></div>
                    <div class="jc-m-field"><label>Contact No.</label><input class="jc-m-input" type="text" name="contact_no" id="contactNo"></div>
                    <div class="jc-m-field"><label>Email address</label><input class="jc-m-input" type="email" name="contact_email" id="contactEmail"></div>
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

        <!-- SECTION 2: Description + Parts supply + Conditions (matches printed card) -->
        <div class="jc-m-card">
            <div class="jc-m-table-wrap">
                <table class="jc-m-table jc-m-table--section2-head jc-m-table-desc" id="jcDescTable">
                    <thead><tr><th>Description</th></tr></thead>
                    <tbody id="jcDescTbody">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <tr>
                            <td><input type="text" class="jc-m-input" name="description_lines[]" value="" placeholder="" autocomplete="off"></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="jc-row-actions no-print">
                <button type="button" class="jc-btn" id="jcAddDescRow"><i class="fas fa-plus"></i> Add description line</button>
                <button type="button" class="jc-btn" id="jcRemoveDescRow"><i class="fas fa-minus"></i> Remove last line</button>
            </div>
        </div>

        <div class="jc-m-card">
            <div class="jc-m-table-wrap">
                <table class="jc-m-table jc-m-table--section2-head jc-m-table-parts" id="jcPartsTable">
                    <thead><tr><th colspan="2">Parts supply</th></tr></thead>
                    <tbody id="jcPartsTbody">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <tr>
                            <td><input type="text" class="jc-m-input" name="parts_left[]" placeholder=""></td>
                            <td><input type="text" class="jc-m-input" name="parts_right[]" placeholder=""></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="jc-row-actions no-print">
                <button type="button" class="jc-btn" id="jcAddPartRow"><i class="fas fa-plus"></i> Add parts row</button>
                <button type="button" class="jc-btn" id="jcRemovePartRow"><i class="fas fa-minus"></i> Remove last row</button>
            </div>
        </div>

        <div class="jc-m-card jc-m-conditions">
            <strong>Conditions of Service:</strong>
            <p style="margin:0.5rem 0 0;">By signing this Job Card, the customer gives our mechanic authorization to inspect, test drive and do diagnosis on vehicle. Our mechanics will not be held liable for any valuable goods left in the vehicle by customer or existing faults after vehicle is checked into workshop or at roadside assistance. The problem of the vehicle should be clearly stipulated on the Job Card by client. Customer should be prepared to pay 100% of the invoiced amount when collecting vehicles unless prior arrangements has been made with our finance department.</p>
        </div>

        <?php include __DIR__ . '/../labor_tracking_section.php'; ?>

        <div class="jc-m-card">
            <div class="jc-m-table-wrap">
                <table class="jc-m-table jc-m-table--section2-head jc-m-table-work" id="jcWorkTable">
                    <thead><tr><th>Work details</th><th>Time allocated</th></tr></thead>
                    <tbody id="jcWorkTbody">
                    <?php for ($wi = 0; $wi < 4; $wi++): ?>
                    <tr>
                        <td><input type="text" class="jc-m-input" name="work_details[]" value=""></td>
                        <td><input type="text" class="jc-m-input" name="time_allocated[]" value=""></td>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="jc-row-actions no-print">
                <button type="button" class="jc-btn" id="jcAddWorkRow"><i class="fas fa-plus"></i> Add work row</button>
                <button type="button" class="jc-btn" id="jcRemoveWorkRow"><i class="fas fa-minus"></i> Remove last row</button>
            </div>
        </div>

        <div class="jc-m-card">
            <div class="jc-m-table-wrap">
                <table class="jc-m-table jc-m-table-callout">
                    <thead><tr><th colspan="2">Call out of town</th></tr></thead>
                    <tbody>
                        <tr><td style="width:35%;"><input class="jc-m-input" type="text" value="Kilometres" readonly></td><td><input class="jc-m-input" type="text" name="callout_km"></td></tr>
                        <tr><td><input class="jc-m-input" type="text" value="Call out fee" readonly></td><td><input class="jc-m-input" type="text" name="callout_fee"></td></tr>
                        <tr><td><input class="jc-m-input" type="text" value="Consumables" readonly></td><td><input class="jc-m-input" type="text" name="callout_consumables"></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="jc-m-card">
            <div class="jc-m-table-wrap">
                <table class="jc-m-table jc-m-table-signoff">
                    <thead><tr><th>Normal Time</th><th>Overtime</th><th>Sunday / Public Holiday</th><th>Technician No.</th><th>Customer signed</th><th>Customer did not sign</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><input class="jc-m-input" type="text" name="normal_time"></td>
                            <td><input class="jc-m-input" type="text" name="overtime"></td>
                            <td><input class="jc-m-input" type="text" name="sunday_holiday"></td>
                            <td><textarea class="jc-m-input" name="technician_no" rows="2"></textarea></td>
                            <td><label class="jc-sign-choice"><input type="radio" name="customer_sig" value="Customer signed"><span>Yes</span></label></td>
                            <td><label class="jc-sign-choice"><input type="radio" name="customer_sig" value="Customer did not sign"><span>No</span></label></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        </div><!-- jc-form-modern -->

    </form>
</div><!-- jc-modern-app -->
</div><!-- content-wrapper -->

<script>
document.getElementById('clientNameDisplay').addEventListener('input', function() {
    const val = this.value;
    const options = document.querySelectorAll('#clientsList option');
    options.forEach(opt => {
        if (opt.value === val) {
            document.getElementById('clientIdHidden').value = opt.dataset.id || '';
            document.getElementById('contactNo').value = opt.dataset.phone || '';
            document.getElementById('contactEmail').value = opt.dataset.email || '';
        }
    });
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

(function initJcDynamicRows() {
    var MIN_ROWS = 1;
    var descTbody = document.getElementById('jcDescTbody');
    var addDescBtn = document.getElementById('jcAddDescRow');
    var remDescBtn = document.getElementById('jcRemoveDescRow');
    function appendDescRow() {
        if (!descTbody) return;
        var tr = document.createElement('tr');
        var td = document.createElement('td');
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'jc-m-input';
        inp.name = 'description_lines[]';
        inp.setAttribute('autocomplete', 'off');
        td.appendChild(inp);
        tr.appendChild(td);
        descTbody.appendChild(tr);
    }
    function removeLastDescRow() {
        if (!descTbody) return;
        var rows = descTbody.querySelectorAll('tr');
        if (rows.length <= MIN_ROWS) return;
        rows[rows.length - 1].remove();
    }
    if (addDescBtn) addDescBtn.addEventListener('click', appendDescRow);
    if (remDescBtn) remDescBtn.addEventListener('click', removeLastDescRow);

    var partsTbody = document.getElementById('jcPartsTbody');
    var addPartBtn = document.getElementById('jcAddPartRow');
    var remPartBtn = document.getElementById('jcRemovePartRow');
    function appendPartRow() {
        if (!partsTbody) return;
        var tr = document.createElement('tr');
        var tdL = document.createElement('td');
        var tdR = document.createElement('td');
        var inL = document.createElement('input');
        inL.type = 'text';
        inL.className = 'jc-m-input';
        inL.name = 'parts_left[]';
        var inR = document.createElement('input');
        inR.type = 'text';
        inR.className = 'jc-m-input';
        inR.name = 'parts_right[]';
        tdL.appendChild(inL);
        tdR.appendChild(inR);
        tr.appendChild(tdL);
        tr.appendChild(tdR);
        partsTbody.appendChild(tr);
    }
    function removeLastPartRow() {
        if (!partsTbody) return;
        var rows = partsTbody.querySelectorAll('tr');
        if (rows.length <= MIN_ROWS) return;
        rows[rows.length - 1].remove();
    }
    if (addPartBtn) addPartBtn.addEventListener('click', appendPartRow);
    if (remPartBtn) remPartBtn.addEventListener('click', removeLastPartRow);

    var workTbody = document.getElementById('jcWorkTbody');
    var addWorkBtn = document.getElementById('jcAddWorkRow');
    var remWorkBtn = document.getElementById('jcRemoveWorkRow');
    function appendWorkRow() {
        if (!workTbody) return;
        var tr = document.createElement('tr');
        var td1 = document.createElement('td');
        var td2 = document.createElement('td');
        var d = document.createElement('input');
        d.type = 'text';
        d.className = 'jc-m-input';
        d.name = 'work_details[]';
        var ta = document.createElement('input');
        ta.type = 'text';
        ta.className = 'jc-m-input';
        ta.name = 'time_allocated[]';
        td1.appendChild(d);
        td2.appendChild(ta);
        tr.appendChild(td1);
        tr.appendChild(td2);
        workTbody.appendChild(tr);
    }
    function onAddWorkRow() {
        appendWorkRow();
    }
    function onRemoveWorkRow() {
        if (!workTbody) return;
        var rows = workTbody.querySelectorAll('tr');
        if (rows.length <= MIN_ROWS) return;
        rows[rows.length - 1].remove();
    }
    if (addWorkBtn) addWorkBtn.addEventListener('click', onAddWorkRow);
    if (remWorkBtn) remWorkBtn.addEventListener('click', onRemoveWorkRow);
})();
</script>


<script>
(function() {
    const PAGE_KEY = 'draft_' + window.location.pathname + window.location.search;
    const form = document.querySelector('form');
    if (!form) return;

    // Restore draft on page load
    const saved = localStorage.getItem(PAGE_KEY);
    if (saved) {
        try {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(name => {
                const fields = form.querySelectorAll(`[name="${name}"]`);
                fields.forEach(field => {
                    if (!field) return;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = data[name] === true || data[name] === field.value;
                    } else if (field.tagName === 'SELECT') {
                        field.value = data[name];
                        field.dispatchEvent(new Event('change'));
                    } else {
                        field.value = data[name];
                        field.dispatchEvent(new Event('input'));
                    }
                });
            });
            // Show restored notice
            const notice = document.createElement('div');
            notice.innerHTML = 'ðŸ“ <strong>Draft restored</strong> â€” your unsaved changes have been recovered.';
            notice.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#F7A100; color:white; padding:14px 22px; border-radius:12px; font-size:14px; z-index:99999; box-shadow:0 6px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;';
            const closeBtn = document.createElement('span');
            closeBtn.textContent = 'âœ•';
            closeBtn.style.cssText = 'cursor:pointer; margin-left:10px; font-size:16px; opacity:0.8;';
            closeBtn.onclick = () => notice.remove();
            notice.appendChild(closeBtn);
            document.body.appendChild(notice);
            setTimeout(() => notice.remove(), 5000);
        } catch(e) {}
    }

    // Auto-save on any input change
    let saveTimeout;
    function doSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const data = {};
            form.querySelectorAll('input, select, textarea').forEach(field => {
                if (!field.name) return;
                if (field.type === 'password' || field.type === 'file' || field.type === 'hidden') return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    data[field.name] = field.checked;
                } else {
                    data[field.name] = field.value;
                }
            });
            localStorage.setItem(PAGE_KEY, JSON.stringify(data));

            // Show saved indicator
            let indicator = document.getElementById('draft-save-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'draft-save-indicator';
                indicator.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#22C55E; color:white; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.15); opacity:0; transition:opacity 0.3s;';
                document.body.appendChild(indicator);
            }
            indicator.textContent = 'âœ“ Draft saved';
            indicator.style.opacity = '1';
            clearTimeout(indicator._hideTimer);
            indicator._hideTimer = setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
        }, 800);
    }

    form.addEventListener('input', doSave);
    form.addEventListener('change', doSave);

    // Clear draft on successful form submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(PAGE_KEY);
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>













