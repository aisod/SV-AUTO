<?php
/** Customer Information sidebar — invoice view parity. Values synced via JS. */
$aq_crm_quotations_url = ($erp_admin_base_path ?? '') . 'Quotation/quotations.php';
$aq_crm_jc_url = ($linkedJobCardId > 0 && $editId)
    ? ($erp_admin_base_path ?? '') . 'JobCard/add_job_card.php?edit_id=' . (int) $linkedJobCardId . '&quote_id=' . (int) $editId
    : '';
$aq_crm_pdf_name = ($aq_quote_ref_label !== '' ? $aq_quote_ref_label : 'quotation') . '.pdf';
?>
      <section class="crm-cust-card no-print" id="aq-crm-customer-card">
        <header class="crm-cust-hd">
          <h2 class="crm-cust-hd-title"><i class="fas fa-box-open" aria-hidden="true"></i> Customer Information</h2>
          <div class="crm-cust-hd-menu">
            <button type="button" class="crm-cust-icon-btn crm-cust-icon-btn--ghost" id="aq-crm-card-menu" aria-label="More options" aria-expanded="false" aria-haspopup="true"><i class="fas fa-ellipsis-v"></i></button>
            <div class="crm-cust-popover" id="aq-crm-card-popover" hidden>
              <?php if ($aq_crm_jc_url !== ''): ?>
              <a href="<?php echo htmlspecialchars($aq_crm_jc_url, ENT_QUOTES, 'UTF-8'); ?>">Edit on job card</a>
              <?php endif; ?>
              <a href="<?php echo htmlspecialchars($aq_crm_quotations_url, ENT_QUOTES, 'UTF-8'); ?>">All quotations</a>
            </div>
          </div>
        </header>
        <div class="crm-cust-bd">
          <div class="crm-cust-actions-wrap">
            <button type="button" class="crm-cust-actions-btn" id="aq-crm-actions-btn" aria-expanded="false" aria-haspopup="true">
              <i class="fas fa-bolt" aria-hidden="true"></i>
              <span>Actions</span>
              <i class="fas fa-chevron-down crm-cust-actions-chevron" aria-hidden="true"></i>
            </button>
            <div class="crm-cust-actions-menu" id="aq-crm-actions-menu" hidden role="menu" aria-label="Quotation actions">
              <div class="crm-cust-action-block aq-doc-actions-block">
                <span class="crm-cust-action-label"><i class="fas fa-file-alt"></i> Document</span>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--download" id="aq-crm-action-download" role="menuitem"><i class="fas fa-download"></i> Download PDF</button>
              </div>
              <?php if ($editId): ?>
              <div class="crm-cust-action-block">
                <span class="crm-cust-action-label"><i class="fas fa-comment-dots"></i> Feedback</span>
                <button type="button" class="crm-cust-action-btn crm-cust-action-btn--chat" data-qt-open-chat role="menuitem"><i class="fas fa-comment"></i> Open discussion</button>
              </div>
              <?php endif; ?>
              <div class="crm-cust-action-block crm-cust-action-block--links">
                <?php if ($aq_crm_jc_url !== ''): ?>
                <a href="<?php echo htmlspecialchars($aq_crm_jc_url, ENT_QUOTES, 'UTF-8'); ?>" class="crm-cust-action-btn crm-cust-action-btn--edit" role="menuitem"><i class="fas fa-pen"></i> Edit on job card</a>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($aq_crm_quotations_url, ENT_QUOTES, 'UTF-8'); ?>" class="crm-cust-action-btn crm-cust-action-btn--nav" role="menuitem"><i class="fas fa-list"></i> All quotations</a>
              </div>
            </div>
          </div>

          <div class="crm-cust-profile">
            <div class="crm-cust-logo" id="aq-crm-logo" aria-hidden="true">
              <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 28c4-8 8-12 14-12s10 4 14 12" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><path d="M8 22c3-5 7-8 12-8s9 3 12 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity=".7"/></svg>
              <span class="crm-cust-logo-initials" id="aq-crm-initials" hidden>?</span>
            </div>
            <div class="crm-cust-profile-text">
              <p class="crm-cust-name" id="aq-crm-customer-name">—</p>
              <p class="crm-cust-sub" id="aq-crm-quote-ref"><?php echo $aq_quote_ref_label !== '' ? htmlspecialchars($aq_quote_ref_label, ENT_QUOTES, 'UTF-8') : 'New quotation'; ?></p>
            </div>
            <span id="aq-crm-status-badge" class="crm-cust-badge crm-cust-badge--draft">Draft</span>
          </div>

          <dl class="crm-cust-meta-grid">
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user" aria-hidden="true"></i><span>Name</span></dt>
                <dd id="aq-crm-meta-name">—</dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span>Address</span></dt>
                <dd id="aq-crm-meta-address">—</dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-phone" aria-hidden="true"></i><span>Contact No</span></dt>
                <dd id="aq-crm-meta-phone">—</dd>
              </div>
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-user-tag" aria-hidden="true"></i><span>Contact Person</span></dt>
                <dd id="aq-crm-meta-contact">—</dd>
              </div>
            </div>
            <div class="crm-cust-meta-row">
              <div class="crm-cust-meta-item">
                <dt><i class="fas fa-envelope" aria-hidden="true"></i><span>Email Address</span></dt>
                <dd id="aq-crm-meta-email">—</dd>
              </div>
              <div class="crm-cust-meta-item crm-cust-meta-item--empty" aria-hidden="true"></div>
            </div>
          </dl>

          <?php if ($editId): ?>
          <div class="crm-cust-section" id="aq-qt-chat-section">
            <?php
            $qt_chat_messages = $aq_manager_comments ?? [];
            $qt_chat_viewer_role = 'admin';
            require __DIR__ . '/quotation_chat_teaser.inc.php';
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
                <p class="crm-cust-addr-line" id="aq-crm-address">—</p>
                <p class="crm-cust-addr-line" id="aq-crm-addr-phone" hidden></p>
              </div>
            </article>
            <article class="crm-cust-addr" id="aq-crm-vehicle-card">
              <div class="crm-cust-addr-icon"><i class="fas fa-truck" aria-hidden="true"></i></div>
              <div class="crm-cust-addr-body" id="aq-crm-vehicle-lines">
                <p class="crm-cust-addr-title">Vehicle / Job</p>
                <p class="crm-cust-addr-line crm-cust-addr-line--muted">—</p>
              </div>
            </article>
          </div>

          <?php if ($editId): ?>
          <div class="crm-cust-section crm-cust-section--files">
            <div class="crm-cust-section-hd">
              <h3><i class="fas fa-folder" aria-hidden="true"></i> Files</h3>
            </div>
            <div class="crm-cust-file">
              <div class="crm-cust-file-icon"><i class="fas fa-file-pdf" aria-hidden="true"></i></div>
              <div class="crm-cust-file-body">
                <p class="crm-cust-file-name" id="aq-crm-file-name"><?php echo htmlspecialchars($aq_crm_pdf_name, ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="crm-cust-file-meta">Quotation PDF</p>
              </div>
              <button type="button" class="crm-cust-dl-btn" id="aq-crm-file-download" title="Download PDF" aria-label="Download PDF"><i class="fas fa-download"></i></button>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </section>
