<?php
/** Shared tinted styles for Customer Information → Actions dropdown buttons (Admin + Manager). */
?>
<style>
  .crm-cust-card .crm-cust-action-btn {
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
  }
  .crm-cust-card .crm-cust-action-btn i { font-size: 14px; }
  .crm-cust-card .crm-cust-action-btn--preview {
    border-color: #2563eb;
    background: #eff6ff;
    color: #1d4ed8;
  }
  .crm-cust-card .crm-cust-action-btn--preview:hover { background: #dbeafe; border-color: #1d4ed8; }
  .crm-cust-card .crm-cust-action-btn--print {
    border-color: #059669;
    background: #ecfdf5;
    color: #047857;
  }
  .crm-cust-card .crm-cust-action-btn--print:hover { background: #d1fae5; border-color: #047857; }
  .crm-cust-card .crm-cust-action-btn--print-blank {
    border-color: #64748b;
    background: #f8fafc;
    color: #475569;
  }
  .crm-cust-card .crm-cust-action-btn--print-blank:hover { background: #f1f5f9; border-color: #334155; }
  .crm-cust-card .crm-cust-action-btn--download {
    border-color: #7c3aed;
    background: #f5f3ff;
    color: #6d28d9;
  }
  .crm-cust-card .crm-cust-action-btn--download:hover { background: #ede9fe; border-color: #5b21b6; }
  .crm-cust-card .crm-cust-action-btn--edit {
    border-color: #ea580c;
    background: #fff7ed;
    color: #c2410c;
  }
  .crm-cust-card .crm-cust-action-btn--edit:hover { background: #ffedd5; border-color: #9a3412; }
  .crm-cust-card .crm-cust-action-btn--quote-no {
    border-color: #0891b2;
    background: #ecfeff;
    color: #0e7490;
  }
  .crm-cust-card .crm-cust-action-btn--quote-no:hover { background: #cffafe; border-color: #0e7490; }
  .crm-cust-card .crm-cust-action-btn--chat {
    border-color: #f7a100;
    background: #fffbeb;
    color: #b45309;
  }
  .crm-cust-card .crm-cust-action-btn--chat:hover { background: #fef3c7; border-color: #d97706; }
  .crm-cust-card .crm-cust-action-btn--nav,
  .crm-cust-card .crm-cust-action-btn--link {
    border-color: #cbd5e1;
    background: #f8fafc;
    color: #475569;
  }
  .crm-cust-card .crm-cust-action-btn--nav:hover,
  .crm-cust-card .crm-cust-action-btn--link:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
  }
  .crm-cust-card .crm-cust-action-btn--disabled,
  .crm-cust-card .crm-cust-action-btn[aria-disabled="true"] {
    opacity: 0.55;
    cursor: not-allowed;
    pointer-events: none;
    border-color: #e5e7eb;
    background: #f8fafc;
    color: #94a3b8;
  }
  .crm-cust-card .crm-cust-action-btn.is-active {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.18);
  }
</style>
