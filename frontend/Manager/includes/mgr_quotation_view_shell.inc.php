<?php
declare(strict_types=1);

/**
 * Manager quotation view — Admin CRM blueprint (read-only + print actions).
 *
 * @var array<string,mixed> $qv
 * @var bool $mgrCanPreview
 * @var string $previewUrl
 * @var string $pdfUrl
 */
$qv = $qv ?? [];
$mgrCanPreview = !empty($mgrCanPreview);
$previewUrl = (string) ($previewUrl ?? '');
$pdfUrl = (string) ($pdfUrl ?? '');

$addrDisplay = trim((string) ($qv['customer_address'] ?? ''));
if ($addrDisplay === '') {
    $addrDisplay = '—';
}
$vehicleAddrLines = array_filter((array) ($qv['vehicle_lines'] ?? []), static fn ($v) => trim((string) $v) !== '');
$hasVehicleCard = $vehicleAddrLines !== [];
$pdfName = (string) ($qv['pdf_name'] ?? 'quotation.pdf');
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
</style>
  <div class="crm-inv-layout aq-manage-layout aq-manage-layout--split no-print">
    <aside class="crm-inv-sidebar no-print" aria-label="Quotation details">
      <section class="crm-cust-card" id="mgr-qt-customer-card">
        <header class="crm-cust-hd">
          <h2 class="crm-cust-hd-title"><i class="fas fa-box-open" aria-hidden="true"></i> Customer Information</h2>
        </header>
        <div class="crm-cust-bd">
          <div class="crm-cust-actions-wrap" id="mgr-qt-actions-wrap">
            <button type="button" class="crm-cust-actions-btn" id="mgr-qt-actions-btn" aria-expanded="false" aria-haspopup="true">
              <i class="fas fa-bolt" aria-hidden="true"></i>
              <span>Actions</span>
              <i class="fas fa-chevron-down crm-cust-actions-chevron" aria-hidden="true"></i>
            </button>
            <div class="crm-cust-actions-menu" id="mgr-qt-actions-menu" hidden role="menu" aria-label="Quotation actions">
              <?php if ($mgrCanPreview): ?>
              <div class="crm-cust-action-block">
                <span class="crm-cust-action-label"><i class="fas fa-print"></i> Document</span>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--print" id="mgr-qt-print-btn" role="menuitem"><i class="fas fa-print"></i> Print document</button>
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
            <div class="crm-cust-logo" aria-hidden="true">
              <span class="crm-cust-logo-initials"><?php echo htmlspecialchars((string) ($qv['initials'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="crm-cust-profile-text">
              <p class="crm-cust-name"><?php echo htmlspecialchars((string) ($qv['customer_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
              <p class="crm-cust-sub"><?php echo htmlspecialchars((string) ($qv['quote_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <span class="crm-cust-badge crm-cust-badge--<?php echo htmlspecialchars((string) ($qv['review_badge'] ?? 'draft'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($qv['status_label'] ?? $qv['review_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>

          <dl class="crm-cust-meta-grid">
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user" aria-hidden="true"></i><span>Name</span></dt>
                <dd><?php echo htmlspecialchars((string) ($qv['customer_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>Address</span></dt>
                <dd><?php echo htmlspecialchars((string) ($qv['customer_address'] ?? '') !== '' ? $qv['customer_address'] : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-phone" aria-hidden="true"></i><span>Contact No</span></dt>
                <dd><?php if (!empty($qv['customer_phone'])): ?><a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', (string) $qv['customer_phone']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $qv['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>—<?php endif; ?></dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user-tag" aria-hidden="true"></i><span>Contact Person</span></dt>
                <dd><?php echo htmlspecialchars((string) ($qv['contact_person'] ?? '') !== '' ? $qv['contact_person'] : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-envelope" aria-hidden="true"></i><span>Email Address</span></dt>
                <dd><?php if (!empty($qv['customer_email'])): ?><a href="mailto:<?php echo htmlspecialchars((string) $qv['customer_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $qv['customer_email'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>—<?php endif; ?></dd>
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
                <?php if (!empty($qv['customer_phone'])): ?><p class="crm-cust-addr-line"><?php echo htmlspecialchars((string) $qv['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
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
                <p class="crm-cust-file-meta">Quotation PDF</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </aside>

    <div class="crm-inv-main aq-manage-preview-main" aria-label="Quotation preview">
      <section class="crm-inv-summary no-print mgr-crm-summary-card" id="aq-crm-summary">
        <header class="crm-inv-summary-hd">
          <h1><i class="fas fa-file-invoice" aria-hidden="true"></i> Quotations</h1>
        </header>
        <div class="mgr-crm-summary-bd">
        <div class="crm-inv-summary-top">
          <div class="crm-inv-summary-stat">
            <strong><?php echo htmlspecialchars(formatMoney((float) ($qv['labour_total'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Labour total</span>
          </div>
          <div class="crm-inv-summary-stat">
            <strong><?php echo htmlspecialchars(formatMoney((float) ($qv['parts_total'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Parts &amp; consumables</span>
          </div>
          <div class="crm-inv-summary-stat crm-inv-summary-stat--grand">
            <strong><?php echo htmlspecialchars(formatMoney((float) ($qv['grand_total'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars((string) ($qv['quote_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · Quotation total</span>
          </div>
        </div>
        <div class="crm-inv-progress" aria-hidden="true">
          <span class="crm-inv-progress-labour" style="width:<?php echo (int) ($qv['labour_pct'] ?? 50); ?>%"></span>
          <span class="crm-inv-progress-parts" style="width:<?php echo (int) ($qv['parts_pct'] ?? 50); ?>%"></span>
        </div>
        <div class="crm-inv-legend">
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--labour"></span> Subtotal <strong><?php echo htmlspecialchars(formatMoney((float) ($qv['subtotal'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong></div>
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--vat"></span> <span>VAT (<?php echo (int) ($qv['vat_pct'] ?? 0); ?>%)</span> <strong><?php echo htmlspecialchars(formatMoney((float) ($qv['vat_amount'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong></div>
        </div>
        </div>
      </section>

      <?php if ($mgrCanPreview): ?>
      <section class="crm-inv-panel">
        <div class="crm-inv-panel-body aq-manage-preview-body">
          <div class="inv-doc-scroll aq-manage-preview-scroll-wrap mgr-qt-preview-scroll">
            <iframe class="mgr-qt-preview-frame" id="mgr-qt-preview-frame" src="<?php echo htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Quotation preview"></iframe>
          </div>
        </div>
      </section>
      <?php else: ?>
      <section class="crm-inv-panel">
        <div class="crm-inv-panel-body">
          <div class="mgr-doc-blocked" role="status">
            <p><strong>Not sent for your review yet</strong></p>
            <p>Admin must send this quotation for manager review before you can open the document preview here.</p>
          </div>
        </div>
      </section>
      <?php endif; ?>
    </div>
  </div>
</div>
