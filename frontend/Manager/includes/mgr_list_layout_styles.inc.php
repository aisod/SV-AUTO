<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Admin/Quotation/quotations_layout.inc.php';
$mgr_st_css = __DIR__ . '/../../Admin/css/student-table.css';
$mgr_st_css_v = is_file($mgr_st_css) ? (string) filemtime($mgr_st_css) : '1';
?>
<link rel="stylesheet" href="../Admin/css/student-table.css?v=<?php echo htmlspecialchars($mgr_st_css_v, ENT_QUOTES, 'UTF-8'); ?>">
<style>
<?php echo qt_render_layout_blueprint_css(); ?>
    .mgr-list-page .mod-hero-grid--overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));}
    @media (max-width:1100px){.mgr-list-page .mod-hero-grid--overview{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:520px){.mgr-list-page .mod-hero-grid--overview{grid-template-columns:1fr;}}
    .mgr-list-page .mod-hero-card{position:relative;overflow:hidden;border-radius:12px;padding:14px 14px 12px;color:#fff;min-height:82px;border:1px solid rgba(255,255,255,.35);box-shadow:0 6px 16px rgba(15,23,42,.1);}
    .mgr-list-page .mod-hero-grid--overview .mod-hero-value{font-size:22px;}
    .mgr-list-page .mod-hero-grid--overview .mod-hero-icon{width:36px;height:36px;font-size:16px;}
    .mgr-list-page .mod-hero-card--green{background:linear-gradient(135deg,#16a34a 0%,#4ade80 100%);}
    .mgr-list-page .mod-hero-card--red{background:linear-gradient(135deg,#dc2626 0%,#f87171 100%);}
    .mgr-list-page .mod-hero-card--blue{background:linear-gradient(135deg,#2563eb 0%,#60a5fa 100%);}
    .mgr-list-page .mod-hero-card--amber{background:linear-gradient(135deg,#d97706 0%,#fbbf24 100%);}
    .mgr-list-page .mod-hero-card--orange{background:linear-gradient(135deg,#ff8a00 0%,#ffc107 100%);}
    .mgr-list-page .mod-hero-card--purple{background:linear-gradient(135deg,#7c3aed 0%,#a78bfa 100%);}
    .mgr-list-page .mod-hero-card--slate{background:linear-gradient(135deg,#475569 0%,#94a3b8 100%);}
    .mgr-list-page .mod-hero-card--cyan{background:linear-gradient(135deg,#0891b2 0%,#22d3ee 100%);}
    .mgr-list-page .mod-hero-card::after{content:'';position:absolute;right:-24%;bottom:-50%;width:65%;height:130%;background:rgba(255,255,255,.12);border-radius:50%;pointer-events:none;}
    .mgr-list-page .mod-hero-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;position:relative;z-index:1;}
    .mgr-list-page .mod-hero-label{font-size:12px;font-weight:600;opacity:.95;margin:0 0 6px;}
    .mgr-list-page .mod-hero-value{font-size:26px;font-weight:800;line-height:1;letter-spacing:-.02em;}
    .mgr-list-page .mod-hero-sub{font-size:11px;opacity:.9;margin-top:6px;line-height:1.35;}
    .mgr-list-page .mod-hero-icon{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.22);display:inline-flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .mgr-list-page .mod-hero-card.mgr-stat-clickable{cursor:pointer;transition:transform .12s ease,box-shadow .12s ease;}
    .mgr-list-page .mod-hero-card.mgr-stat-clickable:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(15,23,42,.14);}
    .mgr-list-page .mod-hero-card.is-active{box-shadow:0 0 0 3px rgba(255,255,255,.9),0 10px 22px rgba(15,23,42,.16);}
    .mgr-list-page .qt-list-card .erp-card-header{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .mgr-list-page .qt-list-meta{font-size:13px;font-weight:600;color:#64748b;}
    .mgr-list-page .mgr-list-table{width:100%;min-width:1100px;table-layout:fixed;border-collapse:collapse;}
    .mgr-list-page .mgr-list-table thead th{background:#f8fafc!important;color:#475569!important;border-bottom:1px solid #e2e8f0!important;}
    .mgr-list-page .mgr-list-table thead th.sortable:hover{background:#f1f5f9!important;}
    .mgr-list-page .mgr-list-table tbody td{color:#475569!important;border-top-color:#e2e8f0!important;}
    .mgr-list-page .mgr-list-table tbody tr:nth-child(odd) td{background:#fff!important;}
    .mgr-list-page .mgr-list-table tbody tr:nth-child(even) td{background:#f8fafc!important;}
    .mgr-list-page .mgr-list-table tbody tr[data-mgr-row="1"]:hover td,
    .mgr-list-page .mgr-list-table tbody tr.mgr-row-selected td{background:#fff7ed!important;}
    .mgr-list-page .mgr-list-table .st-cell-primary{color:#1e293b;}
    .mgr-list-page .mgr-id{font-size:15px;font-weight:800;color:#1e293b;}
    /* qt-status sizing: student-table.css blueprint */
    .mgr-list-page .qt-status-pending,.mgr-list-page .qt-status-open{background:#fff7ed;color:#c2410c;}
    .mgr-list-page .qt-status-approved,.mgr-list-page .qt-status-paid,.mgr-list-page .qt-status-complete{background:#ecfdf5;color:#15803d;}
    .mgr-list-page .qt-status-rejected,.mgr-list-page .qt-status-unpaid{background:#fef2f2;color:#b91c1c;}
    .mgr-list-page .qt-status-progress{background:#eff6ff;color:#1d4ed8;}
    .mgr-list-page .qt-status-partial{background:#fff7ed;color:#c2410c;}
    .mgr-list-page .qt-status-digital-new{background:#fef3c7;color:#b45309;}
    .mgr-list-page .qt-status-digital-viewed{background:#ecfdf5;color:#047857;}
    .mgr-list-page .qt-status-digital-wait{background:#f1f5f9;color:#64748b;}
    .mgr-list-page .qt-row-progress{display:flex;gap:4px;align-items:center;justify-content:center;}
    .mgr-list-page .qt-row-dot{width:10px;height:10px;border-radius:50%;background:#e2e8f0;border:1px solid #cbd5e1;flex-shrink:0;}
    .mgr-list-page .qt-row-dot.is-done{background:#22c55e;border-color:#16a34a;}
    .mgr-list-page .qt-row-dot.is-current{background:#fb923c;border-color:#ea580c;box-shadow:0 0 0 2px rgba(251,146,60,.35);}
    .mgr-list-page .qt-list-time{font-size:11px;font-weight:600;color:#64748b;margin-left:4px;white-space:nowrap;}
    .mgr-list-page .qt-search-hit td{background:#ffedd5!important;box-shadow:inset 0 0 0 1px #fdba74;}
    .mgr-list-page .qt-helper-row td{text-align:center;padding:28px 16px!important;color:#64748b!important;background:#f8fafc;}
    .mgr-list-page .mgr-list-alert{margin:0 0 16px;padding:10px 14px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:14px;}
    .mgr-list-page tr[data-mgr-row="1"][data-can-preview="1"]{cursor:pointer;}
    .mgr-list-page tr[data-mgr-row="1"][data-can-preview="0"]{cursor:default;}
    .mgr-list-page .qt-cell-muted{color:#64748b;font-size:11px;font-weight:600;}
    .ops-doc-page .mgr-list-toolbar{padding:12px 16px !important;gap:0 !important;margin-top:0 !important;}
    .mgr-list-toolbar-hint{margin:0 0 10px;font-size:12px;font-weight:500;line-height:1.4;color:#475569;}
    .mgr-list-toolbar-only,.mgr-list-filter-footer{margin-top:0 !important;padding-top:0 !important;border-top:none !important;}
    .mgr-list-filter-footer .ops-filter-sort{display:flex;align-items:center;gap:8px;}
    .mgr-list-sort-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94a3b8;}
    .mgr-list-filter-footer .ops-filter-result{font-size:13px;font-weight:600;color:#64748b;}
</style>
