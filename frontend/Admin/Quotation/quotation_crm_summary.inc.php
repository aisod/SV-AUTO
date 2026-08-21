<?php
/** Quotation overview (invoice summary parity). Totals updated via JS. */
?>
      <section class="crm-inv-summary no-print" id="aq-crm-summary">
        <div class="crm-inv-summary-hd">
          <h1>Quotation</h1>
        </div>
        <div class="crm-inv-summary-top">
          <div class="crm-inv-summary-stat">
            <strong id="aq-crm-summary-labour"><?php echo $sym; ?>0.00</strong>
            <span>Labour total</span>
          </div>
          <div class="crm-inv-summary-stat">
            <strong id="aq-crm-summary-parts"><?php echo $sym; ?>0.00</strong>
            <span>Parts &amp; consumables</span>
          </div>
          <div class="crm-inv-summary-stat crm-inv-summary-stat--grand">
            <strong id="aq-crm-summary-grand"><?php echo $sym; ?>0.00</strong>
            <span id="aq-crm-summary-ref"><?php echo $aq_quote_ref_label !== '' ? htmlspecialchars($aq_quote_ref_label, ENT_QUOTES, 'UTF-8') . ' · Quotation total' : 'Quotation total'; ?></span>
          </div>
        </div>
        <div class="crm-inv-progress" id="aq-crm-summary-progress" aria-hidden="true">
          <span class="crm-inv-progress-labour" id="aq-crm-progress-labour" style="width:50%"></span>
          <span class="crm-inv-progress-parts" id="aq-crm-progress-parts" style="width:50%"></span>
        </div>
        <div class="crm-inv-legend">
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--labour"></span> Subtotal <strong id="aq-crm-summary-sub"><?php echo $sym; ?>0.00</strong></div>
          <div class="crm-inv-legend-item"><span class="crm-inv-dot crm-inv-dot--vat"></span> <span id="aq-crm-summary-vat-label">VAT (0%)</span> <strong id="aq-crm-summary-vat"><?php echo $sym; ?>0.00</strong></div>
        </div>
      </section>
