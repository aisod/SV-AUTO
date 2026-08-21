<?php
/** CRM layout shell (before tax invoice preview). Requires view.php variables. */
require_once __DIR__ . '/../Quotation/quotation_crm_action_btn_styles.inc.php';
?>
<style>
  .erp-content:has(.crm-inv-page) { padding: 0; background: #eef2f7; }
  .crm-inv-page { padding: 1rem 1.25rem 2rem; box-sizing: border-box; }
  .inv-flash-wrap {
    position: fixed; inset: 0; z-index: 2300; display: flex; align-items: center; justify-content: center;
    background: rgba(15, 23, 42, .35); backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px);
    opacity: 0; pointer-events: none; transition: opacity .2s ease;
  }
  .inv-flash-wrap.show { opacity: 1; pointer-events: auto; }
  .inv-flash-card {
    width: min(92vw, 420px); background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
    box-shadow: 0 24px 55px rgba(0, 0, 0, .25); padding: 18px 18px 14px; text-align: center;
    transform: translateY(8px); transition: transform .2s ease;
  }
  .inv-flash-wrap.show .inv-flash-card { transform: translateY(0); }
  .inv-flash-icon {
    width: 44px; height: 44px; border-radius: 999px; margin: 0 auto 10px;
    display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700;
  }
  .inv-flash-title { font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
  .inv-flash-body { font-size: .9rem; line-height: 1.45; }
  .inv-flash-card--success .inv-flash-icon { background: #ecfdf3; color: #16a34a; border: 1px solid #bbf7d0; }
  .inv-flash-card--success .inv-flash-body { color: #166534; }
  .inv-flash-card--error .inv-flash-icon { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
  .inv-flash-card--error .inv-flash-body { color: #991b1b; }
  .inv-flash-card--mgr {
    width: min(92vw, 440px);
    padding: 22px 22px 18px;
    text-align: left;
    position: relative;
  }
  .inv-flash-card--mgr .inv-flash-icon { margin: 0 0 14px; width: 48px; height: 48px; font-size: 20px; }
  .inv-flash-card--mgr.inv-flash-card--success .inv-flash-icon { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
  .inv-flash-card--mgr.inv-flash-card--success .inv-flash-title { color: #1e3a8a; }
  .inv-flash-card--mgr.inv-flash-card--success .inv-flash-body { color: #334155; }
  .inv-flash-card--mgr.inv-flash-card--error .inv-flash-icon { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
  .inv-flash-card--mgr .inv-flash-title { font-size: 1.125rem; margin-bottom: 8px; text-align: left; }
  .inv-flash-card--mgr .inv-flash-body { font-size: 0.9375rem; text-align: left; margin-bottom: 10px; }
  .inv-flash-card--mgr .inv-flash-hint {
    margin: 0 0 16px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 12px;
    line-height: 1.5;
    color: #64748b;
    text-align: left;
  }
  .inv-flash-card--mgr .inv-flash-hint strong { color: #475569; font-weight: 600; }
  .inv-flash-close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 8px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .inv-flash-close:hover { background: #e2e8f0; color: #334155; }
  .inv-flash-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 120px;
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s ease, box-shadow 0.15s ease;
  }
  .inv-flash-card--success .inv-flash-btn { background: #2563eb; color: #fff; box-shadow: 0 1px 2px rgba(37, 99, 235, 0.25); }
  .inv-flash-card--success .inv-flash-btn:hover { background: #1d4ed8; }
  .inv-flash-card--error .inv-flash-btn { background: #dc2626; color: #fff; }
  .inv-flash-card--error .inv-flash-btn:hover { background: #b91c1c; }
  .inv-flash-wrap[hidden] { display: none !important; }
  .crm-inv-layout { display: grid; grid-template-columns: minmax(320px, 380px) minmax(0, 1fr); gap: 1.25rem; align-items: stretch; max-width: 1600px; margin: 0 auto; }
  @media (max-width: 1100px) { .crm-inv-layout { grid-template-columns: 1fr; } }
  .crm-inv-sidebar { display: flex; flex-direction: column; gap: 0; min-width: 0; overflow: visible; position: relative; z-index: 20; }
  .crm-cust-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 1px 2px rgba(15,23,42,.04); overflow: visible; position: relative; z-index: 21; }
  .erp-content:has(.crm-inv-page) .erp-main,
  .erp-content:has(.crm-inv-page) .content-area { overflow: visible; }
  .crm-cust-hd { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
  .crm-cust-hd-title { margin: 0; font-size: 15px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; }
  .crm-cust-hd-title i { color: #64748b; font-size: 14px; }
  .crm-cust-hd-menu { position: relative; }
  .crm-cust-bd { padding: 0 16px 18px; }
  .crm-cust-hd + .crm-cust-bd .crm-cust-actions-wrap { margin-top: 0; padding-top: 14px; }
  .crm-cust-icon-btn { width: 32px; height: 32px; border: 1px solid #e5e7eb; background: #fff; color: #64748b; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .crm-cust-icon-btn:hover { background: #f8fafc; color: #334155; }
  .crm-cust-popover { position: absolute; top: calc(100% + 6px); right: 0; z-index: 30; min-width: 160px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 8px 24px rgba(15,23,42,.12); padding: 6px; }
  .crm-cust-popover a { display: block; padding: 8px 10px; font-size: 13px; font-weight: 600; color: #334155; text-decoration: none; border-radius: 6px; }
  .crm-cust-popover a:hover { background: #f1f5f9; }
  .crm-cust-actions-wrap { position: relative; padding: 12px 0 14px; border-bottom: 1px solid #f1f5f9; margin-bottom: 14px; z-index: 100; }
  .crm-cust-actions-btn { width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; font-size: 14px; font-weight: 600; color: #111827; cursor: pointer; box-sizing: border-box; font-family: "Segoe UI", system-ui, sans-serif; }
  .crm-cust-actions-btn:hover { background: #f8fafc; border-color: #94a3b8; }
  .crm-cust-actions-chevron { margin-left: auto; font-size: 11px; color: #64748b; transition: transform .15s; }
  .crm-cust-actions-wrap.is-open { z-index: 500; }
  .crm-cust-actions-wrap.is-open .crm-cust-actions-chevron { transform: rotate(180deg); }
  .crm-cust-actions-menu { position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 200; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 16px 40px rgba(15,23,42,.18); padding: 8px; min-width: 100%; width: 100%; max-height: min(70vh, 560px); overflow-x: hidden; overflow-y: auto; box-sizing: border-box; }
  .crm-cust-actions-menu[hidden] { display: none !important; }
  .crm-cust-actions-wrap.is-open .crm-cust-actions-menu { display: block !important; }
  .crm-cust-action-block { display: flex; flex-direction: column; gap: 8px; padding: 10px; margin-bottom: 8px; border: 1px solid #f1f5f9; border-radius: 8px; background: #fafbfc; }
  .crm-cust-action-block:last-child { margin-bottom: 0; }
  .crm-cust-action-block--links { background: #fff; border-color: #e5e7eb; gap: 6px; }
  .crm-cust-action-label { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin: 0; }
  .crm-cust-action-label i { width: 14px; text-align: center; color: #94a3b8; }
  .crm-cust-action-input { width: 100%; min-height: 38px; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 13px; font-weight: 600; color: #111827; background: #fff; box-sizing: border-box; font-family: inherit; }
  .crm-cust-action-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 38px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; color: #334155; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; box-sizing: border-box; font-family: inherit; }
  .crm-cust-action-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
  .crm-cust-profile { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
  .crm-cust-logo { width: 48px; height: 48px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .crm-cust-logo svg { width: 28px; height: 28px; }
  .crm-cust-logo--unpaid { background: #2563eb; }
  .crm-cust-logo--unpaid,
  .crm-cust-logo--paid,
  .crm-cust-logo--partial { background: transparent; }
  .crm-cust-logo .crm-cust-mood-icon { width: 48px; height: 48px; display: block; border-radius: 10px; }
  .crm-cust-payment-note {
    margin: 0 0 14px; padding: 10px 12px; border-radius: 10px;
    font-size: 12px; line-height: 1.45; border: 1px solid transparent;
  }
  .crm-cust-payment-note-text { margin: 0; font-size: 12px; font-weight: 600; line-height: 1.45; }
  .crm-cust-payment-note--paid { background: #ecfdf5; border-color: #bbf7d0; color: #166534; }
  .crm-cust-payment-note--partial { background: #fff7ed; border-color: #fdba74; color: #9a3412; }
  .crm-cust-profile-text { flex: 1; min-width: 0; }
  .crm-cust-name { margin: 0; font-size: 16px; font-weight: 800; color: #111827; line-height: 1.25; }
  .crm-cust-sub { margin: 2px 0 0; font-size: 12px; color: #94a3b8; font-weight: 600; }
  .crm-cust-badge { flex-shrink: 0; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
  .crm-cust-badge--active, .crm-cust-badge--paid { background: #dcfce7; color: #15803d; }
  .crm-cust-badge--unpaid { background: #fef2f2; color: #dc2626; }
  .crm-cust-badge--partial { background: #fff7ed; color: #c2410c; }
  .crm-cust-section { padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
  .crm-cust-section:last-of-type { border-bottom: none; }
  .crm-cust-section-hd { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
  .crm-cust-section-hd h3 { margin: 0; font-size: 13px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; }
  .crm-cust-section-hd h3 i { color: #64748b; font-size: 13px; }
  .crm-cust-meta-grid { display: flex; flex-direction: column; gap: 14px; margin: 0 0 18px; padding: 14px; border-bottom: 1px solid #f1f5f9; background: #fff; font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif; }
  .crm-cust-meta-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start; }
  .crm-cust-meta-item { min-width: 0; display: grid; grid-template-rows: 18px auto; gap: 4px; align-content: start; }
  .crm-cust-meta-item--empty { visibility: hidden; pointer-events: none; }
  .crm-cust-meta-item dt { margin: 0; font-size: 11px; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 6px; line-height: 1.3; height: 18px; min-height: 18px; }
  .crm-cust-meta-item dt i { width: 14px; flex-shrink: 0; text-align: center; font-size: 12px; color: #94a3b8; }
  .crm-cust-meta-item dt span { line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .crm-cust-meta-item dd { margin: 0; font-size: 13px; font-weight: 600; color: #111827; word-break: break-word; line-height: 1.4; min-height: 18px; padding-left: 20px; }
  .crm-cust-meta-item dd a { color: #111827; text-decoration: none; }
  .crm-cust-meta-item dd a:hover { color: #2563eb; text-decoration: underline; }
  .crm-cust-add-btn { width: 28px; height: 28px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f8fafc; color: #94a3b8; cursor: not-allowed; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; padding: 0; }
  .crm-cust-icon-btn--ghost { border: none; background: transparent; width: 28px; height: 28px; }
  .crm-cust-addr { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 8px; background: #fff; }
  .crm-cust-addr--active { border-color: #e5e7eb; background: #f9fafb; }
  .crm-cust-addr-body { flex: 1; min-width: 0; }
  .crm-cust-addr-menu { margin-left: auto; flex-shrink: 0; }
  .crm-cust-addr-icon { width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .crm-cust-addr--active .crm-cust-addr-icon { background: #f3f4f6; color: #475569; }
  .crm-cust-addr-title { margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #111827; }
  .crm-cust-addr-line { margin: 0; font-size: 12px; color: #475569; line-height: 1.45; }
  .crm-cust-section--files .crm-cust-file { margin-bottom: 8px; }
  .crm-cust-section--files .crm-cust-file:last-child { margin-bottom: 0; }
  .crm-cust-file { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
  .crm-cust-dl-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; background: #fff; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; text-decoration: none; font-size: 13px; }
  .crm-cust-dl-btn:hover { background: #f1f5f9; color: #334155; }
  .crm-cust-file-icon { width: 36px; height: 36px; border-radius: 8px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
  .crm-cust-file-body { flex: 1; min-width: 0; }
  .crm-cust-file-name { margin: 0; font-size: 13px; font-weight: 700; color: #111827; }
  .crm-cust-file-meta { margin: 2px 0 0; font-size: 11px; color: #94a3b8; }
  .crm-inv-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid #e5e7eb; background: #fff; color: #334155; text-decoration: none; width: 100%; box-sizing: border-box; }
  .crm-inv-btn--primary { background: #16a34a; border-color: #16a34a; color: #fff; }
  .crm-inv-btn--link { border: none; background: transparent; color: #64748b; width: auto; padding: 4px 8px; }
  .crm-inv-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
  .crm-inv-main.aq-manage-preview-main {
    flex: 1;
    min-width: 0;
    min-height: 0;
    max-height: calc(100vh - 5.5rem);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  .crm-inv-main.aq-manage-preview-main .crm-inv-summary {
    flex-shrink: 0;
  }
  .crm-inv-summary { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
  .crm-inv-summary h1 { margin: 0 0 14px; font-size: 1.35rem; font-weight: 800; color: #111827; }
  .crm-inv-summary-top { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px 24px; margin-bottom: 14px; }
  .crm-inv-summary-stat strong { display: block; font-size: 1.125rem; font-weight: 800; color: #111827; }
  .crm-inv-summary-stat span { font-size: 12px; color: #64748b; }
  .crm-inv-progress { display: flex; height: 8px; border-radius: 999px; overflow: hidden; background: #f1f5f9; margin-bottom: 12px; }
  .crm-inv-progress > span { display: block; height: 100%; }
  .crm-inv-progress-paid { background: #22c55e; }
  .crm-inv-progress-due { background: #f59e0b; }
  .crm-inv-progress-over { background: #ef4444; }
  .crm-inv-legend { display: flex; flex-wrap: wrap; gap: 16px 28px; }
  .crm-inv-legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748b; }
  .crm-inv-legend-item strong { color: #111827; font-weight: 700; }
  .crm-inv-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .crm-inv-dot--paid { background: #22c55e; }
  .crm-inv-dot--due { background: #f59e0b; }
  .crm-inv-dot--over { background: #ef4444; }
  .crm-inv-panel { background: transparent; border: none; border-radius: 0; box-shadow: none; display: flex; flex-direction: column; min-height: 0; overflow: visible; }
  .crm-inv-main.aq-manage-preview-main .crm-inv-panel {
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }
  .crm-inv-panel-hd { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 16px 18px; border-bottom: 1px solid #f1f5f9; }
  .crm-inv-panel-title { display: flex; align-items: center; gap: 10px; }
  .crm-inv-panel-title i { width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; font-size: 16px; }
  .crm-inv-panel-title h2 { margin: 0; font-size: 1.05rem; font-weight: 800; color: #111827; }
  .crm-inv-panel-title p { margin: 2px 0 0; font-size: 12px; color: #64748b; font-weight: 600; }
  .crm-inv-panel-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: #64748b; width: 100%; margin-top: 10px; }
  .crm-inv-panel-meta strong { color: #334155; }
  .crm-inv-panel-actions { display: flex; flex-wrap: wrap; gap: 8px; }
  .crm-inv-panel-actions .crm-inv-btn { width: auto; min-width: 7.5rem; }
  .crm-inv-panel-body { flex: 1; min-height: 0; }
  .crm-inv-main.aq-manage-preview-main .crm-inv-panel-body {
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .crm-inv-page .inv-doc-scroll.aq-manage-preview-scroll-wrap {
    flex: 1;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
    padding: 1.25rem;
    background: #eef2f7;
    border-radius: 12px;
    border: none;
    -webkit-overflow-scrolling: touch;
    scrollbar-gutter: stable;
  }
  .crm-inv-page .inv-doc-scroll.aq-manage-preview-scroll-wrap::-webkit-scrollbar {
    width: 10px;
  }
  .crm-inv-page .inv-doc-scroll.aq-manage-preview-scroll-wrap::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 6px;
  }
  /* Quotation discussion (invoice sidebar — chat-only, no layout overrides) */
  .qt-chat-bubble {
    max-width: 92%;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
  }
  .qt-chat-bubble--mine { margin-left: auto; background: #fffbeb; border-color: #fde68a; }
  .qt-chat-bubble--theirs { margin-right: auto; }
  .qt-chat-bubble--admin.qt-chat-bubble--theirs { background: #eff6ff; border-color: #bfdbfe; }
  .qt-chat-bubble--manager.qt-chat-bubble--theirs { background: #f0fdf4; border-color: #bbf7d0; }
  .qt-chat-bubble-meta {
    margin: 0 0 4px;
    font-size: 10px;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
  }
  .qt-chat-bubble-meta strong { color: #0f172a; font-size: 11px; }
  .qt-chat-bubble-text { margin: 0; font-size: 13px; color: #334155; line-height: 1.45; }
  .qt-chat-teaser {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: linear-gradient(135deg, #f8fafc 0%, #fff 100%);
  }
  .qt-chat-teaser-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    color: #d97706;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
  }
  .qt-chat-teaser-body { flex: 1; min-width: 140px; }
  .qt-chat-teaser-title { margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #0f172a; }
  .qt-chat-teaser-meta { margin: 0; font-size: 12px; color: #64748b; line-height: 1.4; }
  .qt-chat-teaser-btn { margin-left: auto; flex-shrink: 0; }
  body.qt-chat-modal-open { overflow: hidden; }
  .qt-chat-modal {
    position: fixed;
    inset: 0;
    z-index: 13000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .qt-chat-modal[hidden] { display: none !important; }
  .qt-chat-modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
  }
  .qt-chat-modal-dialog {
    position: relative;
    z-index: 1;
    width: min(520px, 100%);
    max-height: min(88vh, 720px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.28);
    overflow: hidden;
    border: 1px solid #e2e8f0;
  }
  .qt-chat-modal-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px 14px;
    border-bottom: 1px solid #f1f5f9;
    background: linear-gradient(180deg, #fffbeb 0%, #fff 100%);
  }
  .qt-chat-modal-eyebrow {
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #b45309;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .qt-chat-modal-title {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.25;
  }
  .qt-chat-modal-subtitle {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: #64748b;
    margin-top: 2px;
  }
  .qt-chat-modal-desc { margin: 6px 0 0; font-size: 12px; color: #64748b; }
  .qt-chat-modal-close {
    width: 36px;
    height: 36px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    color: #64748b;
    cursor: pointer;
    flex-shrink: 0;
  }
  .qt-chat-modal-close:hover { background: #f8fafc; color: #0f172a; }
  .qt-chat-modal-messages {
    flex: 1;
    min-height: 200px;
    max-height: min(50vh, 420px);
    overflow-y: auto;
    padding: 16px 18px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f8fafc;
  }
  .qt-chat-modal-empty {
    margin: auto;
    text-align: center;
    color: #64748b;
    padding: 24px 12px;
  }
  .qt-chat-modal-empty i { font-size: 28px; opacity: 0.45; margin-bottom: 10px; display: block; }
  .qt-chat-modal-empty p { margin: 0 0 4px; font-weight: 600; color: #475569; }
  .qt-chat-modal-empty-sub { margin: 0; font-size: 12px; }
  .qt-chat-modal-footer {
    padding: 14px 18px 18px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
  }
  .qt-chat-modal-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 8px;
  }
  .qt-chat-modal-compose {
    display: flex;
    gap: 10px;
    align-items: flex-end;
  }
  .qt-chat-modal-input {
    flex: 1;
    min-height: 72px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    box-sizing: border-box;
  }
  .qt-chat-modal-send { flex-shrink: 0; height: 42px; }
  .qt-chat-modal-error { margin: 8px 0 0; font-size: 12px; color: #b91c1c; }
</style>
<style id="inv-doc-print-styles">
<?php include __DIR__ . '/view_aq_print_styles.inc.php'; ?>
</style>

<div class="crm-inv-page">
  <?php if (!empty($_GET['success'])): ?>
  <div class="inv-flash-wrap no-print" id="invViewFlashWrap">
    <div class="inv-flash-card inv-flash-card--success">
      <div class="inv-flash-icon"><i class="fas fa-check" aria-hidden="true"></i></div>
      <div class="inv-flash-title">Success</div>
      <div class="inv-flash-body"><?php echo htmlspecialchars((string) $_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>
  <?php elseif (!empty($_GET['error'])): ?>
  <div class="inv-flash-wrap no-print" id="invViewFlashWrap">
    <div class="inv-flash-card inv-flash-card--error">
      <div class="inv-flash-icon"><i class="fas fa-exclamation" aria-hidden="true"></i></div>
      <div class="inv-flash-title">Error</div>
      <div class="inv-flash-body"><?php echo htmlspecialchars((string) $_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </div>
  <?php endif; ?>

  <div class="inv-flash-wrap no-print" id="invMgrSendModal" hidden aria-hidden="true">
    <div class="inv-flash-card inv-flash-card--mgr inv-flash-card--success" role="dialog" aria-modal="true" aria-labelledby="invMgrSendModalTitle">
      <button type="button" class="inv-flash-close" id="invMgrSendModalClose" aria-label="Close">&times;</button>
      <div class="inv-flash-icon" id="invMgrSendModalIcon" aria-hidden="true"><i class="fas fa-paper-plane"></i></div>
      <div class="inv-flash-title" id="invMgrSendModalTitle">Sent for manager preview</div>
      <div class="inv-flash-body" id="invMgrSendModalBody"></div>
      <p class="inv-flash-hint" id="invMgrSendModalHint"><strong>Manager preview (before paper sign-off)</strong> — The invoice is in the manager <em>Sent for review</em> inbox. They can open the document preview in their portal before you print for paper sign-off.</p>
      <button type="button" class="inv-flash-btn" id="invMgrSendModalOk">OK</button>
    </div>
  </div>

  <div class="crm-inv-layout">
    <aside class="crm-inv-sidebar no-print">
<?php include __DIR__ . '/view_crm_customer_sidebar.inc.php'; ?>
    </aside>

    <div class="crm-inv-main aq-manage-preview-main">
      <section class="crm-inv-summary no-print">
        <h1>Invoices</h1>
        <div class="crm-inv-summary-top">
          <div class="crm-inv-summary-stat">
            <strong><?php echo $sym . ' ' . number_format($clientStats['total'], 2); ?></strong>
            <span>Total invoice value &middot; <?php echo (int) $clientStats['count']; ?> invoice<?php echo $clientStats['count'] === 1 ? '' : 's'; ?></span>
          </div>
          <div class="crm-inv-summary-stat" style="text-align:right;">
            <strong><?php echo $sym . ' ' . number_format($invAmount, 2); ?></strong>
            <span>Current invoice &middot; <?php echo htmlspecialchars($invNumber !== '' ? $invNumber : ('#' . $invoiceId), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
        </div>
        <div class="crm-inv-progress" aria-hidden="true">
          <span class="crm-inv-progress-paid" style="width:<?php echo (int) $pctPaid; ?>%"></span>
          <span class="crm-inv-progress-due" style="width:<?php echo (int) $pctDue; ?>%"></span>
          <span class="crm-inv-progress-over" style="width:<?php echo (int) $pctOver; ?>%"></span>
        </div>
        <div class="crm-inv-legend">
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--paid"></span> Paid <strong><?php echo (int) $clientStats['paid_count']; ?></strong> &middot; <?php echo $sym . ' ' . number_format($clientStats['paid_amt'], 2); ?></div>
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--due"></span> Due <strong><?php echo (int) $clientStats['due_count']; ?></strong> &middot; <?php echo $sym . ' ' . number_format($clientStats['due_amt'], 2); ?></div>
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--over"></span> Overdue <strong><?php echo (int) $clientStats['overdue_count']; ?></strong> &middot; <?php echo $sym . ' ' . number_format($clientStats['overdue_amt'], 2); ?></div>
        </div>
      </section>

      <section class="crm-inv-panel">
        <div class="crm-inv-panel-body">
          <div class="inv-doc-scroll aq-manage-preview-scroll-wrap" id="inv-live-doc-scroll">
            <div id="inv-live-doc-mount">
