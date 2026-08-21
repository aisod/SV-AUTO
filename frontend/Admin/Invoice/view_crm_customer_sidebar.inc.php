<?php
/** Customer Information sidebar — data from quotation snapshot. */
$quotationEditUrl = $quotationId > 0 ? '../Quotation/add_quotation.php?id=' . (int) $quotationId : '';
$badgeClass = $statusClass === 'paid' ? 'paid' : ($statusClass === 'partial' ? 'partial' : 'unpaid');
$invIsUnpaid = ($invStatus === 'unpaid');
$invIsPaid = ($invStatus === 'paid');
$invIsPartial = ($invStatus === 'partial');
$invPaidAtRaw = trim((string) ($invoice['paid_at'] ?? ''));
$invPaidAtDisplay = $invPaidAtRaw !== '' && strtotime($invPaidAtRaw) !== false
    ? date('d M Y', strtotime($invPaidAtRaw))
    : '';
$addrDisplay = trim((string) $customerAddress);
if ($addrDisplay === '') {
    $addrDisplay = '-';
}
$poDisplay = trim((string) $purchaseOrder);
$quoteNoDisplay = trim((string) $quotationNumber);
$regModel = trim($vehicleRegNo . ($vehicleModel !== '' ? ($vehicleRegNo !== '' ? ' · ' : '') . $vehicleModel : ''));
$vehicleAddrLines = array_filter([
    $regModel !== '' ? $regModel : '',
    $vehicleVinNo !== '' ? 'VIN ' . $vehicleVinNo : '',
    $fleetNo !== '' ? 'Fleet ' . $fleetNo : '',
    $kilometers !== '' ? $kilometers . ' KM' : '',
    $jobNo !== '' ? 'Job ' . $jobNo : '',
    $invNumber !== '' ? 'Invoice ' . $invNumber : '',
], static fn ($v) => $v !== '');
$hasVehicleCard = $vehicleAddrLines !== [];
$pdfName = ($invNumber !== '' ? $invNumber : 'invoice') . '.pdf';

$metaRows = [
    ['fas fa-user', 'Name', $customerName],
    ['fas fa-map-marker-alt', 'Address', $customerAddress],
    ['fas fa-phone', 'Contact No', $customerPhone],
    ['fas fa-user-tag', 'Contact Person', $contactPerson],
    ['far fa-envelope', 'Email Address', $customerEmail],
];
?>
      <section class="crm-cust-card" id="inv-crm-customer-card">
        <header class="crm-cust-hd">
          <h2 class="crm-cust-hd-title"><i class="fas fa-box-open" aria-hidden="true"></i> Customer Information</h2>
          <div class="crm-cust-hd-menu">
            <button type="button" class="crm-cust-icon-btn crm-cust-icon-btn--ghost" id="crm-cust-card-menu" aria-label="More options" aria-expanded="false" aria-haspopup="true"><i class="fas fa-ellipsis-v"></i></button>
            <div class="crm-cust-popover" id="crm-cust-card-popover" hidden>
              <?php if ($quotationEditUrl !== ''): ?>
              <a href="<?php echo htmlspecialchars($quotationEditUrl, ENT_QUOTES, 'UTF-8'); ?>">Open quotation</a>
              <?php endif; ?>
              <a href="invoices.php">All invoices</a>
            </div>
          </div>
        </header>

        <div class="crm-cust-bd">
          <div class="crm-cust-actions-wrap">
            <button type="button" class="crm-cust-actions-btn" id="crm-cust-actions-btn" aria-expanded="false" aria-haspopup="true">
              <i class="fas fa-bolt" aria-hidden="true"></i>
              <span>Actions</span>
              <i class="fas fa-chevron-down crm-cust-actions-chevron" aria-hidden="true"></i>
            </button>
            <div class="crm-cust-actions-menu" id="crm-cust-actions-menu" hidden role="menu" aria-label="Invoice actions">
              <form method="POST" class="crm-cust-action-block">
                <label class="crm-cust-action-label" for="crm-cust-purchase-order"><i class="fas fa-receipt"></i> Purchase order no.</label>
                <input type="text" name="purchase_order" id="crm-cust-purchase-order" class="crm-cust-action-input" value="<?php echo htmlspecialchars($poDisplay, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Enter PO number" maxlength="64" autocomplete="off">
                <button type="submit" name="update_purchase_order" value="1" class="crm-cust-action-btn crm-cust-action-btn--quote-no"><i class="fas fa-save"></i> Save purchase order</button>
              </form>

              <form method="POST" class="crm-cust-action-block" id="crm-cust-status-form">
                <label class="crm-cust-action-label" for="crm-cust-payment-status"><i class="fas fa-wallet"></i> Payment status</label>
                <select name="payment_status" id="crm-cust-payment-status" class="crm-cust-action-input" required>
                  <option value="unpaid" <?php echo $invStatus === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                  <option value="partial" <?php echo $invStatus === 'partial' ? 'selected' : ''; ?>>Partially paid</option>
                  <option value="paid" <?php echo $invStatus === 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
                <button type="submit" name="update_status" value="1" class="crm-cust-action-btn crm-cust-action-btn--print"><i class="fas fa-check"></i> Update status</button>
              </form>

              <?php if (!empty($is_admin_user)): ?>
              <div class="crm-cust-action-block" id="inv-mgr-review-block">
                <span class="crm-cust-action-label"><i class="fas fa-eye"></i> Manager preview</span>
                <p class="crm-cust-action-hint" style="margin:0 0 8px;font-size:12px;color:#64748b;">Send PDF to manager portal before payment follow-up.</p>
                <p id="inv-mgr-review-meta" style="margin:0 0 8px;font-size:12px;color:#475569;<?php echo empty($inv_mgr_sent_at) ? 'display:none;' : ''; ?>">
                  <?php if (!empty($inv_mgr_sent_at)): ?>
                  Last sent <?php echo htmlspecialchars($inv_mgr_sent_at, ENT_QUOTES, 'UTF-8'); ?>
                  <?php if (!empty($inv_mgr_sent_by)): ?> by <?php echo htmlspecialchars($inv_mgr_sent_by, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                  <?php if (!empty($inv_mgr_viewed_at)): ?> · Manager viewed <?php echo htmlspecialchars($inv_mgr_viewed_at, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                  <?php endif; ?>
                </p>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--preview" id="inv-send-manager-review"><i class="fas fa-paper-plane"></i> <?php echo !empty($inv_mgr_sent_at) ? 'Send again to manager' : 'Send to manager for review'; ?></button>
              </div>
              <?php endif; ?>

              <div class="crm-cust-action-block">
                <span class="crm-cust-action-label"><i class="fas fa-print"></i> Print</span>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--print-blank" id="crm-cust-print-blank" data-inv-print-blank="1"><i class="fas fa-file"></i> Print blank</button>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--download" data-inv-download="1"><i class="fas fa-download"></i> Download PDF</button>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--print" data-inv-print="1"><i class="fas fa-print"></i> Print invoice</button>
              </div>

              <div class="crm-cust-action-block crm-cust-action-block--links">
                <?php if ($quotationEditUrl !== ''): ?>
                <a href="<?php echo htmlspecialchars($quotationEditUrl, ENT_QUOTES, 'UTF-8'); ?>" class="crm-cust-action-btn crm-cust-action-btn--edit"><i class="fas fa-file-alt"></i> View quotation</a>
                <?php endif; ?>
                <a href="invoices.php" class="crm-cust-action-btn crm-cust-action-btn--nav"><i class="fas fa-list"></i> Back to invoices</a>
              </div>
            </div>
          </div>

          <div class="crm-cust-profile">
            <div class="crm-cust-logo<?php echo $invIsUnpaid ? ' crm-cust-logo--unpaid' : ($invIsPaid ? ' crm-cust-logo--paid' : ($invIsPartial ? ' crm-cust-logo--partial' : '')); ?>" aria-hidden="true">
              <?php if ($invIsUnpaid): ?>
              <svg class="crm-cust-mood-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="40" height="40" rx="10" fill="#2563eb"/><path d="M11 25 Q20 17 29 25" stroke="#fff" stroke-width="3" stroke-linecap="round" fill="none"/></svg>
              <?php elseif ($invIsPaid): ?>
              <svg class="crm-cust-mood-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="40" height="40" rx="10" fill="#93C5FD"/><path d="M10 23 Q20 33 30 23" stroke="#1D4ED8" stroke-width="3.5" stroke-linecap="round" fill="none"/></svg>
              <?php elseif ($invIsPartial): ?>
              <svg class="crm-cust-mood-icon" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><rect width="40" height="40" rx="10" fill="#FDE68A"/><path d="M10 24 Q20 28 30 24" stroke="#EA580C" stroke-width="3.5" stroke-linecap="round" fill="none"/></svg>
              <?php else: ?>
              <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 28c4-8 8-12 14-12s10 4 14 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><path d="M8 22c3-5 7-8 12-8s9 3 12 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".7"/></svg>
              <?php endif; ?>
            </div>
            <div class="crm-cust-profile-text">
              <p class="crm-cust-name"><?php echo htmlspecialchars($customerName !== '' ? $customerName : 'Customer', ENT_QUOTES, 'UTF-8'); ?></p>
              <p class="crm-cust-sub">From quotation</p>
            </div>
            <span class="crm-cust-badge crm-cust-badge--<?php echo htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>

          <?php if ($invStatus === 'paid'): ?>
          <div class="crm-cust-payment-note crm-cust-payment-note--paid" role="status">
            <p class="crm-cust-payment-note-text"><?php echo $invPaidAtDisplay !== '' ? 'Recorded ' . htmlspecialchars($invPaidAtDisplay, ENT_QUOTES, 'UTF-8') . '. Balance cleared in the system.' : 'Payment recorded for this invoice. Balance cleared in the system.'; ?></p>
          </div>
          <?php elseif ($invStatus === 'partial'): ?>
          <div class="crm-cust-payment-note crm-cust-payment-note--partial" role="status">
            <p class="crm-cust-payment-note-text"><?php echo $invPaidAtDisplay !== '' ? 'Last update ' . htmlspecialchars($invPaidAtDisplay, ENT_QUOTES, 'UTF-8') . '. ' : ''; ?>Part of this invoice is settled — confirm remaining balance with accounts.</p>
          </div>
          <?php endif; ?>

          <dl class="crm-cust-meta-grid">
            <?php foreach (array_chunk($metaRows, 2) as $metaPair): ?>
            <div class="crm-cust-meta-row">
            <?php foreach ($metaPair as $meta): ?>
            <?php
            $icon = $meta[0];
            $label = $meta[1];
            $val = trim((string) $meta[2]);
            $labelKey = strtolower($label);
            ?>
            <div class="crm-cust-meta-item">
              <dt><i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i><span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span></dt>
              <dd><?php
              if ($val === '') {
                  echo '-';
              } elseif ($labelKey === 'email address' || $labelKey === 'email') {
                  echo '<a href="mailto:' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</a>';
              } elseif ($labelKey === 'contact no' || $labelKey === 'phone') {
                  echo '<a href="tel:' . htmlspecialchars(preg_replace('/\s+/', '', $val), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</a>';
              } else {
                  echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
              }
              ?></dd>
            </div>
            <?php endforeach; ?>
            <?php if (count($metaPair) === 1): ?>
              <div class="crm-cust-meta-item crm-cust-meta-item--empty" aria-hidden="true"></div>
            <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </dl>

          <?php if ($quotationId > 0): ?>
          <div class="crm-cust-section" id="inv-qt-chat-section">
            <?php
            $qt_chat_messages = $inv_manager_comments ?? [];
            $qt_chat_viewer_role = 'admin';
            require __DIR__ . '/../Quotation/quotation_chat_teaser.inc.php';
            ?>
          </div>
          <?php endif; ?>

          <div class="crm-cust-section">
            <div class="crm-cust-section-hd">
              <h3><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Address</h3>
            </div>
            <article class="crm-cust-addr">
              <div class="crm-cust-addr-icon"><i class="fas fa-building" aria-hidden="true"></i></div>
              <div class="crm-cust-addr-body">
                <p class="crm-cust-addr-title">Billing Address</p>
                <p class="crm-cust-addr-line"><?php echo nl2br(htmlspecialchars($addrDisplay, ENT_QUOTES, 'UTF-8')); ?></p>
                <?php if ($customerPhone !== ''): ?><p class="crm-cust-addr-line"><?php echo htmlspecialchars($customerPhone, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
              </div>
            </article>
            <?php if ($hasVehicleCard): ?>
            <article class="crm-cust-addr">
              <div class="crm-cust-addr-icon"><i class="fas fa-truck" aria-hidden="true"></i></div>
              <div class="crm-cust-addr-body">
                <p class="crm-cust-addr-title">Vehicle / Job</p>
                <?php foreach ($vehicleAddrLines as $line): ?>
                <p class="crm-cust-addr-line"><?php echo htmlspecialchars($line, ENT_QUOTES, 'UTF-8'); ?></p>
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
              <button type="button" class="crm-cust-dl-btn" data-inv-download="1" title="Download PDF" aria-label="Download PDF"><i class="fas fa-download"></i></button>
            </div>
          </div>
        </div>
      </section>

