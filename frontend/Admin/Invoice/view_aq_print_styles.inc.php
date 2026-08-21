  /* Formal A4 tax invoice — scoped to invoice view mount only */
  #inv-live-doc-mount {
    display: flex;
    justify-content: center;
    width: 100%;
    padding: 1.25rem 0.75rem 2rem;
    box-sizing: border-box;
  }
  #inv-live-doc-mount .aq-print-inner,
  #inv-live-doc-mount #aq-print-inner {
    width: 210mm;
    min-height: 297mm;
    max-width: 210mm;
    margin: 0 auto;
    background: #fff;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    line-height: 1.35;
    padding: 10mm 12mm;
    box-sizing: border-box;
    box-shadow: 0 4px 28px rgba(15, 23, 42, 0.18);
    border: 1px solid #94a3b8;
    border-radius: 0;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-block {
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    margin-bottom: 3mm;
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
    font-size: 8pt;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-section {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: none;
    margin-bottom: 2mm;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-slot {
    width: 46%;
    vertical-align: top;
    padding: 0;
    border: none;
    height: 1px;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-gap {
    width: 8%;
    padding: 0;
    border: none;
    font-size: 0;
    line-height: 0;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-slot-fit {
    display: flex;
    width: 100%;
    height: 100%;
    vertical-align: top;
    flex-direction: column;
  }
  /* Left column: two separate boxes (Customer Details + contact) with clear gap */
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-section > tbody > tr > td:first-child .aq-doc-info-slot-fit > .aq-doc-info-box {
    display: table;
    margin: 0 0 2mm;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-section > tbody > tr > td:first-child .aq-doc-info-slot-fit > div {
    display: flex;
    flex: 1;
    margin: 0 !important;
    padding: 0;
    width: 100%;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-section > tbody > tr > td:first-child .aq-doc-info-slot-fit > div .aq-doc-info-box {
    display: table;
    margin: 0;
    height: 100% !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    background: #fff;
    box-sizing: border-box;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box:not(.aq-doc-info-box--vehicle) {
    table-layout: auto !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-inner {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-hd {
    background: <?php echo inv_doc_table_hdr_bg(); ?> !important;
    color: #000 !important;
    font-weight: bold;
    text-align: center;
    padding: 4px 6px;
    border: 1px solid <?php echo inv_doc_table_border(); ?> !important;
    font-size: 7.5pt;
    line-height: 1.25;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box:not(.aq-doc-info-box--vehicle) .aq-doc-info-lbl {
    font-weight: bold;
    color: #000 !important;
    white-space: nowrap;
    text-align: left;
    padding: 3px 8px !important;
    vertical-align: top;
    font-size: 7.5pt;
    line-height: 1.3 !important;
    width: 1% !important;
    max-width: none !important;
    border: none !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box:not(.aq-doc-info-box--vehicle) .aq-doc-info-val {
    color: #6b7280 !important;
    font-weight: normal;
    text-align: left;
    padding: 3px 8px 3px 4px !important;
    vertical-align: top;
    font-size: 7.5pt;
    line-height: 1.3 !important;
    width: auto !important;
    border: none !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box:not(.aq-doc-info-box--vehicle) tbody td {
    border: none !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-section > tbody > tr > td {
    vertical-align: top;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box--vehicle {
    table-layout: fixed !important;
    min-height: 100%;
    height: 100%;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box--vehicle .aq-doc-info-lbl {
    font-weight: bold;
    color: #000 !important;
    white-space: nowrap;
    text-align: left;
    padding: 3px 6px 3px 8px !important;
    vertical-align: top;
    font-size: 7.5pt;
    line-height: 1.3 !important;
    width: 42% !important;
    max-width: 42%;
    border: none !important;
    border-right: none !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box--vehicle .aq-doc-info-val {
    color: #6b7280 !important;
    font-weight: normal;
    text-align: left;
    padding: 3px 8px 3px 4px !important;
    vertical-align: top;
    font-size: 7.5pt;
    line-height: 1.3 !important;
    width: 58% !important;
    border: none !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-info-box--vehicle tbody td {
    border: none !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-block-hd {
    background: <?php echo inv_doc_table_hdr_bg(); ?>;
    color: #000;
    font-weight: bold;
    text-align: center;
    padding: 5px 6px;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    font-size: 8.5pt;
    letter-spacing: 0.02em;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 1.5mm;
    font-size: 7.5pt;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table th,
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table td {
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    padding: 3px 5px;
    vertical-align: middle;
    color: #000;
    background: #ffffff;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table thead th {
    background: <?php echo inv_doc_table_hdr_bg(); ?> !important;
    color: #000;
    font-weight: bold;
    text-align: center;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-labour-table,
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-doc-parts-section {
    border-collapse: collapse;
    border-spacing: 0;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-sub {
    background: <?php echo inv_doc_table_subhdr_bg(); ?> !important;
    color: #000;
    font-weight: bold;
    text-align: center;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table tr.aq-doc-last-body-row td {
    border-bottom: none !important;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td {
    padding: 1px 3px !important;
    line-height: 1.1 !important;
    font-size: 7pt !important;
    height: auto;
    vertical-align: middle;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-sum-table {
    width: 32%;
    margin-left: auto;
    margin-top: 0;
    border-collapse: collapse;
    font-size: 7pt;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-sum-table td {
    padding: 1px 4px;
    line-height: 1.1;
    vertical-align: middle;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-spacer {
    background: #fff !important;
    color: #000;
    border: 1px solid <?php echo inv_doc_table_border(); ?> !important;
    border-top: 1px solid <?php echo inv_doc_table_border(); ?> !important;
    font-weight: normal;
    padding: 0 3px !important;
    min-height: 0;
    font-size: 0;
    line-height: 0;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-metric {
    background: <?php echo inv_doc_table_body_bg(); ?> !important;
    color: #000;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    border-top: 1px solid <?php echo inv_doc_table_border(); ?>;
    font-weight: normal;
    text-align: center;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table tr.aq-parts-thick-divider td {
    height: 5px;
    padding: 0 !important;
    background: <?php echo inv_doc_table_border(); ?> !important;
    border: 1px solid <?php echo inv_doc_table_border(); ?> !important;
    border-top: 1px solid <?php echo inv_doc_table_border(); ?> !important;
    border-bottom: 3px solid <?php echo inv_doc_table_border(); ?> !important;
    font-size: 0;
    line-height: 0;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table tr.aq-doc-total-gap td {
    height: 4px;
    padding: 0 !important;
    border: none !important;
    background: #fff !important;
    font-size: 0;
    line-height: 0;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-hdr {
    background: <?php echo inv_doc_table_hdr_bg(); ?>;
    color: #000;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    border-top: 1px solid <?php echo inv_doc_table_border(); ?>;
    font-weight: bold;
    text-align: center;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-side,
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-doc-parts-section thead th {
    vertical-align: middle;
    text-align: center;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-labour-table thead tr.aq-labour-hdr-label th {
    border-top: 1px solid <?php echo inv_doc_table_border(); ?>;
  }
  #inv-live-doc-mount #aq-print-inner .aq-labour-title-cell,
  #inv-live-doc-mount #aq-print-inner .aq-part-type-cell {
    background: #ffffff;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    text-align: center;
    vertical-align: middle;
  }
  #inv-live-doc-mount #aq-print-inner .aq-labour-desc-cell,
  #inv-live-doc-mount #aq-print-inner .aq-parts-name-cell {
    background: #ffffff;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
    vertical-align: top;
  }
  #inv-live-doc-mount #aq-print-inner .aq-labour-metric-cell {
    background: #ffffff;
    text-align: center;
    vertical-align: middle;
    border: 1px solid <?php echo inv_doc_table_border(); ?>;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-labour-table,
  #inv-live-doc-mount #aq-print-inner .aq-doc-data-table.aq-doc-parts-section {
    margin-bottom: 2mm;
    margin-top: 0;
    max-width: 100%;
    box-sizing: border-box;
  }
  #inv-live-doc-mount #aq-print-inner .aq-doc-muted {
    color: #000;
  }
  #inv-live-doc-mount #aq-print-inner img {
    max-width: 100%;
    height: auto;
  }
  @media print {
    @page { size: A4 portrait; margin: 10mm; }
    body { margin: 0; background: #fff !important; }
    .no-print { display: none !important; }
    .crm-inv-page, .crm-inv-panel, .crm-inv-panel-body, .inv-doc-scroll {
      padding: 0 !important;
      margin: 0 !important;
      background: #fff !important;
      border: none !important;
      box-shadow: none !important;
    }
    #inv-live-doc-mount {
      padding: 0 !important;
    }
    #inv-live-doc-mount #aq-print-inner {
      width: 100% !important;
      max-width: none !important;
      min-height: auto !important;
      margin: 0 !important;
      padding: 0 !important;
      box-shadow: none !important;
      border: none !important;
    }
    #inv-live-doc-mount #aq-print-inner .aq-doc-parts-section {
      page-break-before: auto;
    }
    #inv-live-doc-mount #aq-print-inner .aq-doc-footer-block {
      page-break-inside: avoid;
    }
  }
