<?php
declare(strict_types=1);

/**
 * Manager invoice view — Admin CRM blueprint (read-only + print actions).
 *
 * @var array<string,mixed> $iv
 * @var bool $mgrCanPreview
 * @var string $previewUrl
 * @var string $pdfUrl
 */
$iv = $iv ?? [];
$mgrCanPreview = !empty($mgrCanPreview);
$previewUrl = (string) ($previewUrl ?? '');
$pdfUrl = (string) ($pdfUrl ?? '');

$mgrInvStatus = (string) ($iv['status'] ?? 'unpaid');
$mgrInvUnpaid = $mgrInvStatus === 'unpaid';
$mgrInvPaid = $mgrInvStatus === 'paid';
$mgrInvPartial = $mgrInvStatus === 'partial';
$mgrPaidAtDisplay = trim((string) ($iv['paid_at_display'] ?? ''));
$addrDisplay = trim((string) ($iv['customer_address'] ?? ''));
if ($addrDisplay === '') {
    $addrDisplay = '—';
}
$vehicleAddrLines = array_filter((array) ($iv['vehicle_lines'] ?? []), static fn ($v) => trim((string) $v) !== '');
$hasVehicleCard = $vehicleAddrLines !== [];
$pdfName = (string) ($iv['pdf_name'] ?? 'invoice.pdf');
?>
<?php
require_once __DIR__ . '/mgr_doc_preview_fix.inc.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_crm_action_btn_styles.inc.php';
?>
<div class="crm-inv-page mgr-crm-doc-view">
<style>
<?php echo mgr_crm_summary_card_css(); ?>
html:not(.site-theme-dark) body:has(.mgr-crm-doc-view) .erp-content { padding: 0; background: #eef2f7; }
.mgr-crm-doc-view.crm-inv-page { padding: 1rem 1.25rem 2rem; box-sizing: border-box; }
#mgr-inv-customer-card .crm-cust-logo--unpaid,
#mgr-inv-customer-card .crm-cust-logo--paid,
#mgr-inv-customer-card .crm-cust-logo--partial { background: transparent; }
#mgr-inv-customer-card .crm-cust-logo .crm-cust-mood-icon { width: 48px; height: 48px; display: block; border-radius: 10px; }
#mgr-inv-customer-card .crm-cust-badge--unpaid { background: #fef2f2; color: #dc2626; }
#mgr-inv-customer-card .crm-cust-badge--paid { background: #dcfce7; color: #15803d; }
#mgr-inv-customer-card .crm-cust-badge--partial { background: #fff7ed; color: #c2410c; }
#mgr-inv-customer-card .crm-cust-payment-note {
  margin: 0 0 14px; padding: 10px 12px; border-radius: 10px;
  font-size: 12px; line-height: 1.45; border: 1px solid transparent;
}
#mgr-inv-customer-card .crm-cust-payment-note-text { margin: 0; font-size: 12px; font-weight: 600; line-height: 1.45; }
#mgr-inv-customer-card .crm-cust-payment-note--paid { background: #ecfdf5; border-color: #bbf7d0; color: #166534; }
#mgr-inv-customer-card .crm-cust-payment-note--partial { background: #fff7ed; border-color: #fdba74; color: #9a3412; }
</style>
  <div class="crm-inv-layout aq-manage-layout aq-manage-layout--split no-print">
    <aside class="crm-inv-sidebar no-print" aria-label="Invoice details">
      <section class="crm-cust-card" id="mgr-inv-customer-card">
        <header class="crm-cust-hd">
          <h2 class="crm-cust-hd-title"><i class="fas fa-box-open" aria-hidden="true"></i> Customer Information</h2>
        </header>
        <div class="crm-cust-bd">
          <div class="crm-cust-actions-wrap" id="mgr-inv-actions-wrap">
            <button type="button" class="crm-cust-actions-btn" id="mgr-inv-actions-btn" aria-expanded="false" aria-haspopup="true">
              <i class="fas fa-bolt" aria-hidden="true"></i>
              <span>Actions</span>
              <i class="fas fa-chevron-down crm-cust-actions-chevron" aria-hidden="true"></i>
            </button>
            <div class="crm-cust-actions-menu" id="mgr-inv-actions-menu" hidden role="menu" aria-label="Invoice actions">
              <?php if ($mgrCanPreview): ?>
              <div class="crm-cust-action-block">
                <span class="crm-cust-action-label"><i class="fas fa-print"></i> Document</span>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--print" id="mgr-inv-print-btn" role="menuitem"><i class="fas fa-print"></i> Print document</button>
              </div>
              <?php else: ?>
              <div class="crm-cust-action-block">
                <span class="crm-cust-action-label"><i class="fas fa-file-alt"></i> Document</span>
                <span class="crm-cust-action-btn crm-cust-action-btn--disabled" role="menuitem" aria-disabled="true"><i class="fas fa-eye-slash"></i> Preview unavailable</span>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="crm-cust-profile">
            <div class="crm-cust-logo<?php echo $mgrInvUnpaid ? ' crm-cust-logo--unpaid' : ($mgrInvPaid ? ' crm-cust-logo--paid' : ($mgrInvPartial ? ' crm-cust-logo--partial' : '')); ?>" aria-hidden="true">
              <?php if ($mgrInvUnpaid): ?>
              <svg class="crm-cust-mood-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="40" height="40" rx="10" fill="#2563eb"/><path d="M11 25 Q20 17 29 25" stroke="#fff" stroke-width="3" stroke-linecap="round" fill="none"/></svg>
              <?php elseif ($mgrInvPaid): ?>
              <svg class="crm-cust-mood-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="40" height="40" rx="10" fill="#93C5FD"/><path d="M10 23 Q20 33 30 23" stroke="#1D4ED8" stroke-width="3.5" stroke-linecap="round" fill="none"/></svg>
              <?php elseif ($mgrInvPartial): ?>
              <svg class="crm-cust-mood-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="40" height="40" rx="10" fill="#FDE68A"/><path d="M10 24 Q20 28 30 24" stroke="#EA580C" stroke-width="3.5" stroke-linecap="round" fill="none"/></svg>
              <?php else: ?>
              <span class="crm-cust-logo-initials"><?php echo htmlspecialchars((string) ($iv['initials'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endif; ?>
            </div>
            <div class="crm-cust-profile-text">
              <p class="crm-cust-name"><?php echo htmlspecialchars((string) ($iv['customer_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
              <p class="crm-cust-sub">From quotation</p>
            </div>
            <span class="crm-cust-badge crm-cust-badge--<?php echo htmlspecialchars((string) ($iv['status_badge'] ?? 'unpaid'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($iv['status_label'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>

          <?php if ($mgrInvPaid): ?>
          <div class="crm-cust-payment-note crm-cust-payment-note--paid" role="status">
            <p class="crm-cust-payment-note-text"><?php echo $mgrPaidAtDisplay !== '' ? 'Recorded ' . htmlspecialchars($mgrPaidAtDisplay, ENT_QUOTES, 'UTF-8') . '. Balance cleared in the system.' : 'Payment recorded for this invoice. Balance cleared in the system.'; ?></p>
          </div>
          <?php elseif ($mgrInvPartial): ?>
          <div class="crm-cust-payment-note crm-cust-payment-note--partial" role="status">
            <p class="crm-cust-payment-note-text"><?php echo $mgrPaidAtDisplay !== '' ? 'Last update ' . htmlspecialchars($mgrPaidAtDisplay, ENT_QUOTES, 'UTF-8') . '. ' : ''; ?>Part of this invoice is settled — confirm remaining balance with accounts.</p>
          </div>
          <?php endif; ?>

          <dl class="crm-cust-meta-grid">
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user" aria-hidden="true"></i><span>Name</span></dt>
                <dd><?php echo htmlspecialchars((string) ($iv['customer_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>Address</span></dt>
                <dd><?php echo htmlspecialchars((string) ($iv['customer_address'] ?? '') !== '' ? $iv['customer_address'] : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-phone" aria-hidden="true"></i><span>Contact No</span></dt>
                <dd><?php if (!empty($iv['customer_phone'])): ?><a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', (string) $iv['customer_phone']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $iv['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>—<?php endif; ?></dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user-tag" aria-hidden="true"></i><span>Contact Person</span></dt>
                <dd><?php echo htmlspecialchars((string) ($iv['contact_person'] ?? '') !== '' ? $iv['contact_person'] : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-envelope" aria-hidden="true"></i><span>Email Address</span></dt>
                <dd><?php if (!empty($iv['customer_email'])): ?><a href="mailto:<?php echo htmlspecialchars((string) $iv['customer_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $iv['customer_email'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>—<?php endif; ?></dd>
              </div>
              <div class="crm-cust-meta-item crm-cust-meta-item--empty" aria-hidden="true"></div>
            </div>
          </dl>

          <div class="crm-cust-section">
            <div class="crm-cust-section-hd">
              <h3><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Address</h3>
            </div>
            <article class="crm-cust-addr">
              <div class="crm-cust-addr-icon"><i class="fas fa-building" aria-hidden="true"></i></div>
              <div class="crm-cust-addr-body">
                <p class="crm-cust-addr-title">Billing Address</p>
                <p class="crm-cust-addr-line"><?php echo nl2br(htmlspecialchars($addrDisplay, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php if (!empty($iv['customer_phone'])): ?><p class="crm-cust-addr-line"><?php echo htmlspecialchars((string) $iv['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
              </div>
            </article>
            <?php if ($hasVehicleCard): ?>
            <article class="crm-cust-addr">
              <div class="crm-cust-addr-icon"><i class="fas fa-truck" aria-hidden="true"></i></div>
              <div class="crm-cust-addr-body">
                <p class="crm-cust-addr-title">Vehicle / Job</p>
                <?php foreach ($vehicleAddrLines as $line): ?>
                <p class="crm-cust-addr-line"><?php echo htmlspecialchars((string) $line, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endforeach; ?>
              </div>
            </article>
            <?php endif; ?>
          </div>

          <div class="crm-cust-section crm-cust-section--files">
            <div class="crm-cust-section-hd">
              <h3><i class="fas fa-folder" aria-hidden="true"></i> Files</h3>
            </div>
            <div class="crm-cust-file">
              <div class="crm-cust-file-icon"><i class="fas fa-file-pdf" aria-hidden="true"></i></div>
              <div class="crm-cust-file-body">
                <p class="crm-cust-file-name"><?php echo htmlspecialchars($pdfName, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="crm-cust-file-meta">Tax invoice</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </aside>

    <div class="crm-inv-main aq-manage-preview-main" aria-label="Invoice preview">
      <section class="crm-inv-summary no-print mgr-crm-summary-card" id="mgr-inv-summary">
        <header class="crm-inv-summary-hd">
          <h1><i class="fas fa-file-invoice-dollar" aria-hidden="true"></i> Invoices</h1>
        </header>
        <div class="mgr-crm-summary-bd">
        <div class="crm-inv-summary-top">
          <div class="crm-inv-summary-stat">
            <strong><?php echo htmlspecialchars(formatMoney((float) ($iv['paid_amount'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Paid / collected</span>
          </div>
          <div class="crm-inv-summary-stat">
            <strong><?php echo htmlspecialchars(formatMoney((float) ($iv['due_amount'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Outstanding</span>
          </div>
          <div class="crm-inv-summary-stat crm-inv-summary-stat--grand">
            <strong><?php echo htmlspecialchars(formatMoney((float) ($iv['amount'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars((string) ($iv['invoice_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · Current invoice</span>
          </div>
        </div>
        <div class="crm-inv-progress" aria-hidden="true">
          <span class="crm-inv-progress-paid" style="width:<?php echo (int) ($iv['paid_pct'] ?? 0); ?>%"></span>
          <span class="crm-inv-progress-due" style="width:<?php echo (int) ($iv['due_pct'] ?? 0); ?>%"></span>
        </div>
        <div class="crm-inv-legend">
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--paid"></span> Subtotal <strong><?php echo htmlspecialchars(formatMoney((float) ($iv['subtotal'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong></div>
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--vat"></span> <span>VAT (<?php echo (int) ($iv['vat_pct'] ?? 0); ?>%)</span> <strong><?php echo htmlspecialchars(formatMoney((float) ($iv['vat_amount'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong></div>
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--due"></span> Issued <strong><?php echo htmlspecialchars((string) ($iv['issued_display'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
        </div>
        </div>
      </section>

      <?php if ($mgrCanPreview): ?>
      <section class="crm-inv-panel">
        <div class="crm-inv-panel-body aq-manage-preview-body">
          <div class="inv-doc-scroll aq-manage-preview-scroll-wrap mgr-qt-preview-scroll">
            <iframe class="mgr-qt-preview-frame" id="mgr-inv-preview-frame" src="<?php echo htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Invoice preview"></iframe>
          </div>
        </div>
      </section>
      <?php else: ?>
      <section class="crm-inv-panel">
        <div class="crm-inv-panel-body">
          <div class="mgr-doc-blocked" role="status">
            <p><strong>Not sent for your review yet</strong></p>
            <p>Admin must send this invoice for manager review before you can open the document preview here.</p>
          </div>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </div>
</div>
