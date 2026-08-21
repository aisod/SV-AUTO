<?php
/** CRM layout styles (invoice view parity) — does not style #aq-print-inner document content. */
require_once __DIR__ . '/quotation_crm_action_btn_styles.inc.php';
?>
<style>
  .erp-content:has(.crm-inv-page) { padding: 0; background: #eef2f7; }
  .crm-inv-page { padding: 1rem 1.25rem 2rem; box-sizing: border-box; }
  .crm-inv-page .aq-quote-page-inner { max-width: none; margin: 0; padding: 0; }
  .crm-inv-page #aq-app { max-width: none; width: 100%; }
  .crm-inv-layout { display: grid; grid-template-columns: minmax(360px, 420px) minmax(0, 1fr); gap: 1.25rem; align-items: start; max-width: 1600px; margin: 0 auto; }
  .crm-inv-layout:not(.aq-manage-layout--split) { grid-template-columns: minmax(0, 1fr); max-width: 88rem; }
  @media (max-width: 1100px) { .crm-inv-layout { grid-template-columns: 1fr; } }
  .crm-inv-sidebar { display: flex; flex-direction: column; gap: 0.75rem; min-width: 0; overflow: visible; position: relative; z-index: 20; }
  .erp-content:has(.crm-inv-page) .erp-main,
  .erp-content:has(.crm-inv-page) .content-area { overflow: visible; }
  .crm-cust-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 1px 2px rgba(15,23,42,.04); overflow: visible; position: relative; z-index: 21; }
  .crm-cust-hd { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; }
  .crm-cust-hd-title { margin: 0; font-size: 15px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; }
  .crm-cust-hd-title i { color: #64748b; font-size: 14px; }
  .crm-cust-hd-menu { position: relative; }
  .crm-cust-icon-btn { width: 32px; height: 32px; border: 1px solid #e5e7eb; background: #fff; color: #64748b; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; text-decoration: none; }
  .crm-cust-icon-btn:hover { background: #f8fafc; color: #334155; }
  .crm-cust-icon-btn--ghost { border: none; background: transparent; }
  .crm-cust-bd { padding: 0 16px 18px; }
  .crm-cust-hd + .crm-cust-bd .crm-cust-actions-wrap { margin-top: 0; padding-top: 14px; }
  .crm-cust-actions-wrap { position: relative; padding: 12px 0 14px; border-bottom: 1px solid #f1f5f9; margin-bottom: 14px; z-index: 100; }
  .crm-cust-actions-btn { width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; font-size: 14px; font-weight: 600; color: #111827; cursor: pointer; box-sizing: border-box; font-family: inherit; }
  .crm-cust-actions-btn:hover { background: #f8fafc; border-color: #94a3b8; }
  .crm-cust-actions-chevron { margin-left: auto; font-size: 11px; color: #64748b; transition: transform .15s; }
  .crm-cust-actions-wrap.is-open { z-index: 500; }
  .crm-cust-actions-wrap.is-open .crm-cust-actions-chevron { transform: rotate(180deg); }
  .crm-cust-actions-menu { position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 200; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 16px 40px rgba(15,23,42,.18); padding: 8px; min-width: 100%; max-height: min(70vh, 560px); overflow-y: auto; box-sizing: border-box; }
  .crm-cust-actions-menu[hidden] { display: none !important; }
  .crm-cust-actions-wrap.is-open .crm-cust-actions-menu { display: block !important; }
  .crm-cust-action-block { display: flex; flex-direction: column; gap: 8px; padding: 10px; margin-bottom: 8px; border: 1px solid #f1f5f9; border-radius: 8px; background: #fafbfc; }
  .crm-cust-action-block:last-child { margin-bottom: 0; }
  .crm-cust-action-block--links { background: #fff; border-color: #e5e7eb; gap: 6px; }
  .crm-cust-action-label { display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin: 0; }
  .crm-cust-action-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 38px; padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; color: #334155; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; box-sizing: border-box; font-family: inherit; }
  .crm-cust-action-btn:hover { background: #f8fafc; border-color: #cbd5e1; }
  .crm-cust-profile { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
  .crm-cust-logo { width: 48px; height: 48px; border-radius: 10px; background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; font-weight: 800; position: relative; }
  .crm-cust-logo svg { width: 28px; height: 28px; }
  .crm-cust-logo-initials { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; }
  .crm-cust-popover { position: absolute; right: 0; top: calc(100% + 6px); z-index: 300; min-width: 180px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 12px 32px rgba(15,23,42,.15); padding: 6px; }
  .crm-cust-popover[hidden] { display: none !important; }
  .crm-cust-popover a { display: block; padding: 8px 12px; border-radius: 8px; color: #334155; font-size: 13px; font-weight: 600; text-decoration: none; }
  .crm-cust-popover a:hover { background: #f8fafc; color: #111827; }
  .crm-cust-profile-text { flex: 1; min-width: 0; }
  .crm-cust-name { margin: 0; font-size: 16px; font-weight: 800; color: #111827; line-height: 1.25; }
  .crm-cust-sub { margin: 2px 0 0; font-size: 12px; color: #94a3b8; font-weight: 600; }
  .crm-cust-badge { flex-shrink: 0; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
  .crm-cust-badge--draft { background: #f1f5f9; color: #475569; }
  .crm-cust-badge--pending { background: #fff7ed; color: #c2410c; }
  .crm-cust-badge--approved { background: #dcfce7; color: #15803d; }
  .crm-cust-badge--rejected { background: #fef2f2; color: #dc2626; }
  .crm-cust-badge--sent { background: #dbeafe; color: #1d4ed8; }
  .crm-cust-section { padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
  .crm-cust-section:last-of-type { border-bottom: none; padding-bottom: 0; }
  .crm-cust-section-hd { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
  .crm-cust-section-hd h3 { margin: 0; font-size: 13px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 8px; }
  .crm-cust-section-hd h3 i { color: #64748b; font-size: 13px; }
  .crm-cust-meta-grid { display: flex; flex-direction: column; gap: 14px; margin: 0 0 18px; padding: 14px; border-bottom: 1px solid #f1f5f9; background: #fff; }
  .crm-cust-meta-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start; }
  .crm-cust-meta-item { min-width: 0; display: grid; grid-template-rows: 18px auto; gap: 4px; align-content: start; }
  .crm-cust-meta-item--empty { visibility: hidden; pointer-events: none; }
  .crm-cust-meta-item dt { margin: 0; font-size: 11px; font-weight: 600; color: #64748b; display: flex; align-items: center; gap: 6px; line-height: 1.3; min-height: 18px; }
  .crm-cust-meta-item dt i { width: 14px; flex-shrink: 0; text-align: center; font-size: 12px; color: #94a3b8; }
  .crm-cust-meta-item dt span { line-height: 1.3; }
  .crm-cust-meta-item dd { margin: 0; font-size: 13px; font-weight: 600; color: #111827; word-break: break-word; overflow-wrap: anywhere; line-height: 1.4; min-height: 18px; padding-left: 20px; }
  .crm-cust-meta-item dd a { color: #111827; text-decoration: none; }
  .crm-cust-meta-item dd a:hover { color: #2563eb; text-decoration: underline; }
  .crm-cust-addr { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 8px; background: #fff; }
  .crm-cust-addr:last-child { margin-bottom: 0; }
  .crm-cust-addr-icon { width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .crm-cust-addr-body { flex: 1; min-width: 0; }
  .crm-cust-addr-title { margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #111827; }
  .crm-cust-addr-line { margin: 0 0 2px; font-size: 12px; color: #475569; line-height: 1.45; }
  .crm-cust-addr-line--muted { color: #94a3b8; }
  .crm-cust-addr-line:last-child { margin-bottom: 0; }
  .crm-inv-main { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
  .crm-inv-layout.aq-manage-layout--split { align-items: stretch; }
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
  .crm-inv-main.aq-manage-preview-main .crm-inv-summary,
  .crm-inv-main.aq-manage-preview-main .mgr-review-banner {
    flex-shrink: 0;
  }
  .crm-inv-summary { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 18px 20px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
  .crm-inv-summary-hd { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
  .crm-inv-summary-hd h1 { margin: 0; font-size: 1.35rem; font-weight: 800; color: #111827; }
  .crm-inv-summary-actions { flex-shrink: 0; }
  .crm-inv-summary-actions .aq-s1-btn { min-width: 7.5rem; min-height: 2.5rem; }
  .crm-inv-summary-top { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px 24px; margin-bottom: 14px; }
  .crm-inv-summary-stat strong { display: block; font-size: 1.125rem; font-weight: 800; color: #111827; }
  .crm-inv-summary-stat span { font-size: 12px; color: #64748b; }
  .crm-inv-summary-stat--grand { text-align: right; }
  .crm-inv-summary-stat--grand strong { font-size: 1.25rem; }
  .crm-inv-progress { display: flex; height: 8px; border-radius: 999px; overflow: hidden; background: #f1f5f9; margin-bottom: 12px; }
  .crm-inv-progress > span { display: block; height: 100%; min-width: 0; transition: width .25s ease; }
  .crm-inv-progress-labour { background: #3b82f6; }
  .crm-inv-progress-parts { background: #f59e0b; }
  .crm-inv-legend { display: flex; flex-wrap: wrap; gap: 16px 28px; }
  .crm-inv-legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #64748b; }
  .crm-inv-legend-item strong { color: #111827; font-weight: 700; }
  .crm-inv-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .crm-inv-dot--labour { background: #3b82f6; }
  .crm-inv-dot--vat { background: #8b5cf6; }
  .crm-inv-main.aq-manage-preview-main .crm-inv-panel {
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
  }
  .crm-inv-main.aq-manage-preview-main .crm-inv-panel-body {
    flex: 1;
    min-height: 0;
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
  .crm-inv-page .mgr-qt-preview-scroll {
    overflow: hidden;
    padding: 1.25rem;
  }
  .crm-inv-page .mgr-qt-preview-frame {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 420px;
    border: 0;
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
  }
  .crm-inv-page #aq-live-doc-mount { display: flex; justify-content: center; width: 100%; }
  .crm-inv-page #aq-live-doc-mount .aq-print-inner,
  .crm-inv-page #aq-live-doc-mount #aq-print-inner {
    margin: 0 auto;
    max-width: 780px;
    width: 100%;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
    border-radius: 4px;
    border: 1px solid #e2e8f0;
  }
  .crm-cust-section--files .crm-cust-file { margin-bottom: 0; }
  .crm-cust-file { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; }
  .crm-cust-file-icon { width: 36px; height: 36px; border-radius: 8px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
  .crm-cust-file-body { flex: 1; min-width: 0; }
  .crm-cust-file-name { margin: 0; font-size: 13px; font-weight: 700; color: #111827; word-break: break-word; }
  .crm-cust-file-meta { margin: 2px 0 0; font-size: 11px; color: #94a3b8; }
  .crm-cust-dl-btn { width: 32px; height: 32px; border-radius: 50%; border: 1px solid #e5e7eb; background: #fff; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; font-size: 13px; }
  .crm-cust-dl-btn:hover { background: #f1f5f9; color: #334155; }
  .crm-inv-main .aq-crm-manage-panel {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
    overflow: visible;
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    margin-bottom: 0;
  }
  .crm-inv-main .aq-crm-manage-panel .aq-mq-panel-head,
  .crm-inv-sidebar .aq-crm-manage-panel .aq-mq-panel-head {
    border: none;
    border-bottom: 1px solid #e5e7eb;
    border-radius: 0;
    background: #f8fafc;
    box-shadow: none;
    margin: 0;
    padding: 14px 16px 12px;
  }
  .crm-inv-main .aq-crm-manage-panel .aq-mq-panel-title,
  .crm-inv-sidebar .aq-crm-manage-panel .aq-mq-panel-title,
  .crm-inv-main .aq-crm-manage-panel .aq-bolt-section--head .erp-page-title {
    margin: 0;
    font-size: 1.0625rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: #0f172a;
    text-transform: none;
    line-height: 1.3;
  }
  .crm-inv-sidebar .aq-crm-manage-panel {
    gap: 0;
    padding: 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    overflow: hidden;
  }
  .crm-inv-sidebar .aq-crm-manage-panel .aq-mq-next-banner {
    border-radius: 0;
    border: none;
    border-bottom: 1px solid #d1e9ff;
    box-shadow: none;
    margin: 0;
  }
  .crm-inv-sidebar .aq-crm-manage-panel .aq-mq-tabs-card {
    border: none;
    border-radius: 0;
    box-shadow: none;
  }
  .crm-inv-main .aq-crm-manage-panel .aq-mq-tabs-card,
  .crm-inv-main .aq-crm-manage-panel .aq-mq-next-banner {
    border-radius: 0;
    border: none;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
    box-shadow: none;
    margin: 0;
  }
  .crm-inv-main .aq-crm-manage-panel .aq-mq-next-banner {
    padding: 12px 14px;
    background: #eef7ff;
    border-bottom: 1px solid #d1e9ff;
  }
  .crm-inv-main .aq-crm-manage-panel .aq-mq-tabs-wrap {
    padding: 10px 12px 0;
  }
  .crm-inv-main .aq-crm-manage-panel .aq-mq-tab-panels {
    padding: 12px 14px 16px;
  }
  .crm-inv-main .aq-crm-manage-panel .aq-mq-tab {
    font-size: 12px;
    padding: 8px 10px;
  }
  .aq-preview-only .crm-inv-main .aq-crm-manage-panel { display: none; }
  .aq-preview-only .crm-inv-layout { grid-template-columns: 1fr; }
  .aq-preview-only .crm-inv-main { order: 0; }
  body.print-open .crm-inv-page,
  body.print-open .aq-manage-layout,
  body.print-open .crm-inv-sidebar { display: none !important; }
  @media (max-width: 768px) {
    .crm-inv-summary-hd { flex-direction: column; align-items: stretch; }
    .crm-inv-summary-actions { justify-content: stretch; }
    .crm-inv-summary-actions .aq-s1-btn { flex: 1; }
    .crm-inv-summary-stat--grand { text-align: left; }
  }
  .qt-chat-panel {
    margin-top: 0;
    padding: 12px 0 0;
    border-top: 1px solid #f1f5f9;
  }
  .qt-chat-panel--compact { padding-top: 0; border-top: none; }
  .qt-chat-head { margin-bottom: 8px; }
  .qt-chat-head h3 {
    margin: 0 0 4px;
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .qt-chat-hint { font-size: 11px; color: #64748b; display: block; }
  .qt-chat-messages {
    max-height: 220px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 10px;
    padding: 4px 2px;
  }
  .qt-chat-empty { margin: 0; font-size: 12px; color: #64748b; line-height: 1.45; }
  .qt-chat-bubble {
    max-width: 92%;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
  }
  .qt-chat-bubble--mine {
    margin-left: auto;
    background: #fffbeb;
    border-color: #fde68a;
  }
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
  .qt-chat-compose { display: flex; flex-direction: column; gap: 6px; }
  .qt-chat-label { font-size: 11px; font-weight: 700; color: #475569; }
  .qt-chat-input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 10px;
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    min-height: 64px;
  }
  .qt-chat-error { margin: 0; font-size: 12px; color: #b91c1c; }
  .qt-chat-send { align-self: flex-end; }
  .aq-manage-sidebar .qt-chat-panel {
    padding: 14px 16px;
    border-top: 1px solid #e5e7eb;
    background: #fffbeb;
  }
  .aq-manage-sidebar .qt-chat-messages { max-height: 200px; }
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
