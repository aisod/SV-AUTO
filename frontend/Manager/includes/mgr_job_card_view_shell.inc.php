<?php
declare(strict_types=1);

/**
 * Manager job card view — Admin CRM blueprint (read-only + print actions).
 *
 * @var array<string,mixed> $jc
 * @var string $previewUrl
 */
$jc = $jc ?? [];
$previewUrl = (string) ($previewUrl ?? '');
$previewOpenUrl = mgr_nav_href('JobCard/mgr_job_card_preview.php?id=' . (int) ($jc['id'] ?? 0));

$addrDisplay = trim((string) ($jc['customer_address'] ?? ''));
if ($addrDisplay === '') {
    $addrDisplay = '—';
}
$vehicleAddrLines = array_filter((array) ($jc['vehicle_lines'] ?? []), static fn ($v) => trim((string) $v) !== '');
$hasVehicleCard = $vehicleAddrLines !== [];
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
    <aside class="crm-inv-sidebar no-print" aria-label="Job card details">
      <section class="crm-cust-card" id="mgr-jc-customer-card">
        <header class="crm-cust-hd">
          <h2 class="crm-cust-hd-title"><i class="fas fa-box-open" aria-hidden="true"></i> Customer Information</h2>
        </header>
        <div class="crm-cust-bd">
          <div class="crm-cust-actions-wrap" id="mgr-jc-actions-wrap">
            <button type="button" class="crm-cust-actions-btn" id="mgr-jc-actions-btn" aria-expanded="false" aria-haspopup="true">
              <i class="fas fa-bolt" aria-hidden="true"></i>
              <span>Actions</span>
              <i class="fas fa-chevron-down crm-cust-actions-chevron" aria-hidden="true"></i>
            </button>
            <div class="crm-cust-actions-menu" id="mgr-jc-actions-menu" hidden role="menu" aria-label="Job card actions">
              <div class="crm-cust-action-block">
                <span class="crm-cust-action-label"><i class="fas fa-print"></i> Document</span>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--print" id="mgr-jc-print-btn" role="menuitem"><i class="fas fa-print"></i> Print document</button>
              </div>
            </div>
          </div>

          <div class="crm-cust-profile">
            <div class="crm-cust-logo" aria-hidden="true">
              <span class="crm-cust-logo-initials"><?php echo htmlspecialchars((string) ($jc['initials'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="crm-cust-profile-text">
              <p class="crm-cust-name"><?php echo htmlspecialchars((string) ($jc['customer_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
              <p class="crm-cust-sub"><?php echo htmlspecialchars((string) ($jc['card_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <span class="crm-cust-badge crm-cust-badge--<?php echo htmlspecialchars((string) ($jc['status_badge'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($jc['status_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>

          <dl class="crm-cust-meta-grid">
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user" aria-hidden="true"></i><span>Name</span></dt>
                <dd><?php echo htmlspecialchars((string) ($jc['customer_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>Address</span></dt>
                <dd><?php echo htmlspecialchars((string) ($jc['customer_address'] ?? '') !== '' ? $jc['customer_address'] : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-phone" aria-hidden="true"></i><span>Contact No</span></dt>
                <dd><?php if (!empty($jc['customer_phone'])): ?><a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', (string) $jc['customer_phone']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $jc['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>—<?php endif; ?></dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user-tag" aria-hidden="true"></i><span>Contact Person</span></dt>
                <dd><?php echo htmlspecialchars((string) ($jc['contact_person'] ?? '') !== '' ? $jc['contact_person'] : '—', ENT_QUOTES, 'UTF-8'); ?></dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-envelope" aria-hidden="true"></i><span>Email Address</span></dt>
                <dd><?php if (!empty($jc['customer_email'])): ?><a href="mailto:<?php echo htmlspecialchars((string) $jc['customer_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $jc['customer_email'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?>—<?php endif; ?></dd>
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

          <?php if (!empty($jc['description'])): ?>
          <div class="crm-cust-section">
            <div class="crm-cust-section-hd">
              <h3><i class="fas fa-align-left" aria-hidden="true"></i> Work description</h3>
            </div>
            <article class="crm-cust-addr">
              <div class="crm-cust-addr-body">
                <p class="crm-cust-addr-line"><?php echo nl2br(htmlspecialchars((string) $jc['description'], ENT_QUOTES, 'UTF-8')); ?></p>
              </div>
            </article>
          </div>
          <?php endif; ?>
        </div>
      </section>
    </aside>

    <div class="crm-inv-main aq-manage-preview-main" aria-label="Job card preview">
      <section class="crm-inv-summary no-print mgr-crm-summary-card" id="mgr-jc-summary">
        <header class="crm-inv-summary-hd">
          <h1><i class="fas fa-clipboard-list" aria-hidden="true"></i> Job Cards</h1>
        </header>
        <div class="mgr-crm-summary-bd">
        <div class="crm-inv-summary-top">
          <div class="crm-inv-summary-stat">
            <strong><?php echo htmlspecialchars((string) ($jc['card_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Job card no.</span>
          </div>
          <div class="crm-inv-summary-stat">
            <strong><?php echo htmlspecialchars((string) ($jc['status_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Status</span>
          </div>
          <div class="crm-inv-summary-stat crm-inv-summary-stat--grand">
            <strong><?php echo htmlspecialchars((string) ($jc['created_display'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <span>Created</span>
          </div>
        </div>
        </div>
      </section>

      <section class="crm-inv-panel">
        <div class="crm-inv-panel-body aq-manage-preview-body">
          <div class="inv-doc-scroll aq-manage-preview-scroll-wrap mgr-qt-preview-scroll">
            <iframe class="mgr-qt-preview-frame" id="mgr-jc-preview-frame" src="<?php echo htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Job card preview"></iframe>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
