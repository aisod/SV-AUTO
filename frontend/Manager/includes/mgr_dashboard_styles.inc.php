<?php
declare(strict_types=1);
/** Manager dashboard — vt-dashboard layout + command-center lower section */
?>
<style>
    html:not(.site-theme-dark):not(.rd-dash-theme-dark) body:has(.mgr-vt-dashboard) .erp-content{
        background:#f4f6f9;
        padding:20px 24px 28px;
    }
    body:has(.mgr-vt-dashboard) .erp-header{
        height:var(--header-height);min-height:var(--header-height);max-height:var(--header-height);
        padding-top:0;padding-bottom:0;box-sizing:border-box;
        border-bottom:1px solid var(--gray-200);box-shadow:none;
    }
    body:has(.mgr-vt-dashboard) .erp-header-left .erp-page-title{display:none!important;}
    .rd-dash-welcome{
        margin:0;font-size:1.35rem;font-weight:700;line-height:1.25;
        color:#0f172a;letter-spacing:-.02em;white-space:nowrap;
    }
    .rd-dash-welcome-sub{margin:4px 0 0;font-size:13px;font-weight:500;color:#64748b;}
    html.rd-dash-theme-dark .rd-dash-welcome{color:var(--rd-dm-text);}
    html.rd-dash-theme-dark .rd-dash-welcome-sub{color:var(--rd-dm-text-muted);}

    .mgr-vt-stat-row--4{grid-template-columns:repeat(4,minmax(0,1fr));}
    @media (max-width:1100px){.mgr-vt-stat-row--4{grid-template-columns:repeat(2,minmax(0,1fr));}}
    @media (max-width:560px){.mgr-vt-stat-row--4{grid-template-columns:1fr;}}

    .mgr-vt-dashboard .vt-donut-center-num{font-size:1rem;line-height:1.2;max-width:88%;text-align:center;word-break:break-word;}

    /* Follow-up — compact horizontal strip */
    .mgr-dash-followup-bar{
        background:#fff;border:1px solid #e5e7eb;border-radius:16px;
        padding:14px 16px 16px;box-shadow:0 1px 3px rgba(15,23,42,.05);
    }
    .mgr-dash-followup-bar-head{
        display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;
    }
    .mgr-dash-followup-bar-title{margin:0;font-size:15px;font-weight:800;color:#0f172a;}
    .mgr-dash-followup-bar-track{
        display:flex;flex-wrap:wrap;gap:10px;
    }
    .mgr-dash-followup-chip{
        display:flex;align-items:center;gap:10px;flex:1 1 220px;max-width:100%;
        padding:10px 12px;border-radius:12px;border:1px solid #e2e8f0;background:#f8fafc;
        text-decoration:none;color:inherit;transition:border-color .12s ease,background .12s ease;
    }
    .mgr-dash-followup-chip:hover{background:#fff7ed;border-color:#fed7aa;}
    .mgr-dash-followup-chip-icon{
        width:36px;height:36px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px;
    }
    .mgr-dash-followup-chip-icon--orange{background:#fff7ed;color:#ea580c;}
    .mgr-dash-followup-chip-icon--red{background:#fef2f2;color:#dc2626;}
    .mgr-dash-followup-chip-icon--blue{background:#eff6ff;color:#2563eb;}
    .mgr-dash-followup-chip-icon--violet{background:#f5f3ff;color:#7c3aed;}
    .mgr-dash-followup-chip-icon--green{background:#ecfdf5;color:#16a34a;}
    .mgr-dash-followup-chip-icon--amber{background:#fffbeb;color:#d97706;}
    .mgr-dash-followup-chip-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px;}
    .mgr-dash-followup-chip-title{font-size:13px;font-weight:700;color:#0f172a;line-height:1.3;}
    .mgr-dash-followup-chip-meta{font-size:11px;font-weight:500;color:#64748b;line-height:1.35;}

    /* Lower workspace — clients primary, preview sidebar */
    .mgr-dash-lower{
        display:grid;grid-template-columns:minmax(0,1.45fr) minmax(260px,0.75fr);
        gap:20px;align-items:start;
    }
    .mgr-dash-lower--wide-main{grid-template-columns:minmax(0,1fr) minmax(240px,320px);}
    @media (max-width:960px){.mgr-dash-lower,.mgr-dash-lower--wide-main{grid-template-columns:1fr;}}

    .mgr-dash-panel{
        background:#fff;border:1px solid #e5e7eb;border-radius:16px;
        overflow:hidden;min-width:0;box-shadow:0 1px 3px rgba(15,23,42,.05);
    }
    .mgr-dash-panel--wide{grid-column:1/-1;}
    .mgr-dash-panel-head{
        display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
        padding:16px 18px 14px;border-bottom:1px solid #f1f5f9;
    }
    .mgr-dash-panel-title{margin:0;font-size:16px;font-weight:800;color:#0f172a;}
    .mgr-dash-panel-sub{margin:4px 0 0;font-size:12px;font-weight:500;color:#94a3b8;}

    .mgr-dash-inbox-clear{padding:28px 20px 24px;text-align:center;}
    .mgr-dash-inbox-clear-icon{
        width:48px;height:48px;margin:0 auto 12px;border-radius:50%;
        background:#ecfdf5;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:22px;
    }
    .mgr-dash-inbox-clear-title{margin:0 0 6px;font-size:15px;font-weight:800;color:#0f172a;}
    .mgr-dash-inbox-clear-text{margin:0 0 14px;font-size:12px;line-height:1.5;color:#64748b;}
    .mgr-dash-inbox-links{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;}
    .mgr-dash-inbox-links a{font-size:12px;font-weight:700;color:#ea580c;text-decoration:none;}
    .mgr-dash-inbox-links a:hover{text-decoration:underline;}

    .mgr-dash-preview-list{list-style:none;margin:0;padding:8px 10px 12px;}
    .mgr-dash-preview-item{
        display:flex;align-items:center;gap:10px;padding:10px 8px;border-radius:10px;
        text-decoration:none;color:inherit;transition:background .12s ease;
    }
    .mgr-dash-preview-item:hover{background:#f8fafc;}
    .mgr-dash-preview-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px;}
    .mgr-dash-preview-ref{font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .mgr-dash-preview-client{font-size:11px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .mgr-dash-preview-amount{font-size:12px;font-weight:700;color:#475569;white-space:nowrap;}
    .mgr-dash-preview-chevron{font-size:10px;color:#cbd5e1;flex-shrink:0;}

    .mgr-doc-type-badge{
        display:inline-flex;align-items:center;padding:4px 8px;border-radius:999px;
        font-size:10px;font-weight:700;letter-spacing:.02em;flex-shrink:0;
    }
    .mgr-doc-type-badge--qt{background:#f5f3ff;color:#6d28d9;}
    .mgr-doc-type-badge--inv{background:#fff7ed;color:#c2410c;}

    .rd-client-cell{display:flex;align-items:center;gap:10px;min-width:0;}
    .rd-client-avatar{
        width:32px;height:32px;border-radius:50%;flex-shrink:0;
        display:inline-flex;align-items:center;justify-content:center;
        font-size:12px;font-weight:700;color:#fff;
    }
    .rd-client-avatar--0{background:#8b5cf6;}
    .rd-client-avatar--1{background:#14b8a6;}
    .rd-client-avatar--2{background:#f59e0b;}
    .rd-client-avatar--3{background:#ec4899;}
    .rd-client-avatar--4{background:#3b82f6;}

    .rd-dash-clients-wrap .qt-status{
        display:inline-flex;align-items:center;border-radius:999px;
        padding:4px 8px;font-size:11px;font-weight:600;margin-right:6px;
    }
    .rd-dash-clients-wrap .qt-status-paid{background:#ecfdf5;color:#15803d;}
    .rd-dash-clients-wrap .qt-status-unpaid{background:#fef2f2;color:#dc2626;}
    .rd-dash-clients-wrap .qt-status-partial{background:#fff7ed;color:#c2410c;}

    #dashClientsTable.erp-student-table.rd-dash-clients-table{
        width:100%;table-layout:fixed;border-collapse:collapse;
    }
    #dashClientsTable.erp-student-table.rd-dash-clients-table thead th{
        background:#f8fafc!important;color:#64748b!important;
        border-bottom:1px solid #e2e8f0!important;padding:10px 14px!important;
        font-size:11px!important;font-weight:700!important;text-transform:uppercase;
    }
    #dashClientsTable.erp-student-table.rd-dash-clients-table tbody td{
        padding:0!important;border-top:1px solid #f1f5f9!important;vertical-align:middle;
    }
    #dashClientsTable.erp-student-table .qt-td-inner{
        display:flex;align-items:center;min-height:58px;padding:8px 14px;box-sizing:border-box;
    }
    #dashClientsTable.erp-student-table .qt-td-inner--end{justify-content:flex-end;}

    .mgr-activity-feed{
        list-style:none;margin:0;padding:8px 12px 12px;
        display:flex;flex-direction:column;
    }
    .mgr-activity-feed-item{border-bottom:1px solid #f1f5f9;}
    .mgr-activity-feed-item:last-child{border-bottom:none;}
    .mgr-activity-feed-link{
        display:flex;align-items:flex-start;gap:12px;padding:12px 10px;
        text-decoration:none;color:inherit;transition:background .12s ease;
    }
    .mgr-activity-feed-link:hover{background:#f8fafc;}
    .mgr-activity-feed-link--static{cursor:default;}
    .mgr-activity-feed-link--static:hover{background:transparent;}
    .mgr-activity-feed-dot{
        width:8px;height:8px;border-radius:50%;background:#22c55e;
        flex-shrink:0;margin-top:6px;
    }
    .mgr-activity-feed-body{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px;}
    .mgr-activity-feed-who{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
    .mgr-activity-feed-who strong{font-size:14px;color:#0f172a;}
    .mgr-activity-feed-role{
        font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;
        color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:999px;
    }
    .mgr-activity-feed-what{font-size:13px;color:#475569;line-height:1.4;}
    .mgr-activity-feed-meta{font-size:12px;color:#94a3b8;font-weight:500;}
    .mgr-activity-feed-time{
        flex-shrink:0;font-size:11px;color:#94a3b8;white-space:nowrap;padding-top:4px;
    }

    html.site-theme-dark .mgr-dash-followup-bar,
    html.rd-dash-theme-dark .mgr-dash-followup-bar,
    html.site-theme-dark .mgr-dash-panel,
    html.rd-dash-theme-dark .mgr-dash-panel{
        background:var(--rd-dm-surface);border-color:var(--rd-dm-border);
    }
    html.site-theme-dark .mgr-dash-followup-chip,
    html.rd-dash-theme-dark .mgr-dash-followup-chip{
        background:var(--rd-dm-surface-2);border-color:var(--rd-dm-border);
    }
    html.site-theme-dark .mgr-dash-followup-chip-title,
    html.rd-dash-theme-dark .mgr-dash-followup-chip-title,
    html.site-theme-dark .mgr-dash-panel-title,
    html.rd-dash-theme-dark .mgr-dash-panel-title{color:var(--rd-dm-text);}
    html.site-theme-dark .mgr-activity-feed-item,
    html.rd-dash-theme-dark .mgr-activity-feed-item{border-bottom-color:var(--rd-dm-border);}
    html.site-theme-dark .mgr-activity-feed-link:hover,
    html.rd-dash-theme-dark .mgr-activity-feed-link:hover{background:var(--rd-dm-surface-hover);}
    html.site-theme-dark .mgr-activity-feed-who strong,
    html.rd-dash-theme-dark .mgr-activity-feed-who strong{color:var(--rd-dm-text);}
</style>
