<?php
/**
 * Print document body — matches add_quotation.php preview layout (#6b7280 tables, rowspan metrics).
 * Do not replace with view_invoice.php inline markup; that uses a different layout.
 */
?>
  <div id="<?php echo inv_doc_h($doc_wrapper_id ?? 'invoice-print-inner'); ?>" class="<?php echo inv_doc_h($doc_wrapper_class ?? 'invoice-wrapper'); ?>">

    <div class="aq-doc-header-wrap" style="position:relative;margin-bottom:3mm;line-height:normal;min-height:22mm;">
      <?php
      $headerSrc = trim((string) ($header_img_url ?? ''));
      if ($headerSrc === '' && !empty($header_base64)) {
          $headerSrc = (string) $header_base64;
      }
      ?>
      <?php if ($headerSrc !== ''): ?>
        <img style="width:100%;display:block;height:auto;" src="<?php echo inv_doc_h($headerSrc); ?>" alt="Header"/>
      <?php else: ?>
        <div style="text-align:right;font-weight:bold;padding:20px 0;">SV Auto Truck Repair CC</div>
      <?php endif; ?>
      <div class="aq-doc-title-badge" style="position:absolute;right:10px;bottom:7mm;top:auto;z-index:2;font-family:Arial Black,Arial,sans-serif;font-size:16pt;font-weight:900;letter-spacing:2px;color:#000;line-height:1.1;text-align:right;white-space:nowrap;padding:2px 4px;background:rgba(255,255,255,.92);"><?php echo inv_doc_h($doc_title ?? 'Tax Invoice'); ?></div>
    </div>

    <?php
    $customerRows = [['Name', (string) ($customerName ?? '')]];
    $addrParts = preg_split('/\r\n|\r|\n/', (string) ($customerAddress ?? ''));
    $addrParts = array_values(array_filter(array_map('trim', is_array($addrParts) ? $addrParts : []), static fn ($s) => $s !== ''));
    if ($addrParts === []) {
        $customerRows[] = ['Address', ''];
    } else {
        foreach ($addrParts as $i => $part) {
            $customerRows[] = [$i === 0 ? 'Address' : '', $part];
        }
    }
    $contactRows = [];
    if (!isset($show_contact_box) || $show_contact_box) {
        $contactRows = inv_doc_filter_info_rows([
            ['Contact No', (string) ($customerPhone ?? '')],
            ['Contact Person', (string) ($contactPerson ?? '')],
            ['Email Address', (string) ($customerEmail ?? '')],
        ]);
    }
    $vehicleRowsAll = [
        ['Date', formatInvoiceDate($invoiceDate ?? '')],
    ];
    if (!empty($show_invoice_number)) {
        $vehicleRowsAll[] = ['Invoice Number', (string) ($doc_invoice_number ?? '')];
    } elseif (trim((string) ($doc_quote_number ?? '')) !== '') {
        $vehicleRowsAll[] = ['Quote Number', (string) $doc_quote_number];
    }
    $vehicleRowsAll = array_merge($vehicleRowsAll, [
        ['Kilometers', (string) ($kilometers ?? '')],
        ['Vin No.', (string) ($vehicleVinNo ?? '')],
        ['Fleet No.', (string) ($fleetNo ?? '')],
        ['Vehicle Reg No.', (string) ($vehicleRegNo ?? '')],
        ['Model', (string) ($vehicleModel ?? '')],
    ]);
    if (!isset($show_quotation_ref_on_doc) || $show_quotation_ref_on_doc) {
        if (trim((string) ($quotationNumber ?? '')) !== '') {
            $vehicleRowsAll[] = ['Quotation No.', (string) $quotationNumber];
        }
    }
    $vehicleRowsAll[] = ['Job No.', (string) ($jobNo ?? '')];
    $vehicleRows = inv_doc_filter_info_rows($vehicleRowsAll);
    if (!isset($show_purchase_order_on_doc) || $show_purchase_order_on_doc) {
        $vehicleRows[] = ['Purchase Order', trim((string) ($purchaseOrder ?? ''))];
    }
    echo inv_doc_render_info_section($customerRows, $contactRows, $vehicleRows);
    ?>

    <?php
    $labourRows = isset($quoteData['labour_rows']) && is_array($quoteData['labour_rows']) ? $quoteData['labour_rows'] : [];
    $partsRows = isset($quoteData['parts_rows']) && is_array($quoteData['parts_rows']) ? $quoteData['parts_rows'] : [];
    $consRows = isset($quoteData['cons_rows']) && is_array($quoteData['cons_rows']) ? $quoteData['cons_rows'] : [];
    $totals = isset($quoteData['totals']) && is_array($quoteData['totals']) ? $quoteData['totals'] : [];

    $includeLabour = inv_doc_bool($formData['print_blank_show_labour'] ?? null, true);
    $includeParts = inv_doc_bool($formData['print_blank_show_parts'] ?? null, true);
    $normalLines = max(2, (int)($formData['blank_normal_lines'] ?? 5));
    $overtimeLines = (int)($formData['blank_overtime_lines'] ?? 5);
    $holidayLines = (int)($formData['blank_holiday_lines'] ?? 5);
    $partsLines = max(1, (int)($formData['blank_parts_lines'] ?? 7));
    $partsTopLines = max(0, (int)($formData['blank_parts_top_lines'] ?? 2));
    $consLines = max(1, (int)($formData['blank_cons_lines'] ?? 1));

    $diagnosticRows = array_values(array_filter($labourRows, function ($r) {
        return is_array($r) && !empty($r['include_in_print']) && (($r['category'] ?? '') === 'diagnostic');
    }));
    $normalTimeRows = array_values(array_filter($labourRows, function ($r) {
        return is_array($r) && !empty($r['include_in_print']) && (!isset($r['category']) || $r['category'] === '' || $r['category'] === 'normal_time');
    }));
    $overtimeRows = array_values(array_filter($labourRows, function ($r) {
        return is_array($r) && !empty($r['include_in_print']) && (($r['category'] ?? '') === 'overtime');
    }));
    $publicHolidayRows = array_values(array_filter($labourRows, function ($r) {
        return is_array($r) && !empty($r['include_in_print']) && (($r['category'] ?? '') === 'public_holiday');
    }));

    $doc_blank_only = !empty($doc_blank_only);

    $showNormalTime = !empty($formData['show_normal_time']) || count($normalTimeRows) > 0;
    $showOvertime = !empty($formData['show_overtime']) || count($overtimeRows) > 0;
    $showPublicHoliday = !empty($formData['show_public_holiday']) || count($publicHolidayRows) > 0;
    $normalRowsToShow = $includeLabour && $showNormalTime ? max(count($normalTimeRows), $normalLines) : 0;
    $overtimeRowsToShow = $includeLabour && $showOvertime ? max(count($overtimeRows), $overtimeLines) : 0;
    $holidayRowsToShow = $includeLabour && $showPublicHoliday ? max(count($publicHolidayRows), $holidayLines) : 0;
    $labourRowsForBlocks = array_values(array_filter($labourRows, static function ($r) {
        return is_array($r) && (($r['category'] ?? '') !== 'diagnostic');
    }));
    $labourPrintBlocks = !$doc_blank_only ? inv_doc_build_labour_print_blocks($labourRowsForBlocks) : [];
    $labourHeaderLabelsEarly = inv_doc_labour_header_labels(is_array($formData) ? $formData : []);
    $hasLabourSection = $doc_blank_only
        ? ($normalRowsToShow > 0 || $overtimeRowsToShow > 0 || $holidayRowsToShow > 0)
        : ($includeLabour && ($labourPrintBlocks !== [] || $labourHeaderLabelsEarly !== []));

    $partTypeLabelsForFilter = ['consumables', 'call out fee', 'kilometres', 'consumable', 'call-out', 'call out'];
    $partRowHasContent = static function ($row) use ($partTypeLabelsForFilter): bool {
        if (!is_array($row) || empty($row['include_in_print'])) {
            return false;
        }
        $name = trim((string)($row['item_name'] ?? ''));
        $callout = trim((string)($row['callout_type'] ?? ''));
        if ($callout === '' && $name !== '' && in_array(strtolower($name), $partTypeLabelsForFilter, true)) {
            $callout = $name;
            $name = '';
        }
        return $name !== '' || $callout !== ''
            || (float)($row['qty'] ?? 0) > 0
            || (float)($row['unit_cost'] ?? 0) > 0
            || (float)($row['total'] ?? 0) > 0;
    };
    $partsFiltered = array_values(array_filter($partsRows, $partRowHasContent));
    $consFiltered = array_values(array_filter($consRows, $partRowHasContent));
    if ($includeParts) {
        if ($doc_blank_only) {
            $partsRowsToShow = $partsLines;
            $consRowsToShow = $consLines;
        } else {
            $partsRowsToShow = count($partsFiltered);
            $consRowsToShow = count($consFiltered);
        }
    } else {
        $partsRowsToShow = 0;
        $consRowsToShow = 0;
    }

    $labourHeaderLabels = inv_doc_labour_header_labels(is_array($formData) ? $formData : []);
    $docCols = inv_doc_table_col_widths();
    $colDesc = $docCols['side'];
    $colName = $docCols['name'];
    $colPartType = $docCols['side'];
    $colPartName = $docCols['name'];
    $colQty = $docCols['qty'];
    $colRate = $docCols['rate'];
    $colTot = $docCols['total'];
    $colPct = static function (string $width): float {
        return (float) rtrim($width, '%');
    };
    $totalStripWidth = 100.0 - $colPct($colDesc);
    $totalNameCol = ($colPct($colName) / $totalStripWidth * 100) . '%';
    $totalQtyCol = ($colPct($colQty) / $totalStripWidth * 100) . '%';
    $totalRateCol = ($colPct($colRate) / $totalStripWidth * 100) . '%';
    $totalTotCol = ($colPct($colTot) / $totalStripWidth * 100) . '%';
    $docHdr = inv_doc_table_hdr_bg();
    $docBd = inv_doc_table_border();
    $docBody = inv_doc_table_body_bg();
    $hdrStyle = 'background:' . $docHdr . ';color:#000;border:1px solid ' . $docBd . ';font-weight:bold;font-size:8pt;padding:3px 5px;vertical-align:middle;text-align:center;white-space:normal;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;';
    $subHdrStyle = 'background:' . inv_doc_table_subhdr_bg() . ';color:#000;border:1px solid ' . $docBd . ';font-weight:bold;font-size:8pt;padding:3px 5px;vertical-align:middle;text-align:center;white-space:normal;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;';
    $totalStripCell = inv_doc_total_strip_hdr_style();
    $totalMetricCell = 'background:' . $docBody . ';color:#000;border:1px solid ' . $docBd . ';font-size:7pt;padding:1px 3px;line-height:1.1;vertical-align:middle;text-align:center;';
    $docSumTableWidth = inv_doc_sum_table_width_pct();
    $docSumCell = 'background:' . $docHdr . ';color:#000;border:1px solid ' . $docBd . ';font-weight:bold;font-size:7pt;padding:1px 4px;line-height:1.1;vertical-align:middle;';
    $docSumValCell = $docSumCell . 'text-align:right;';
    $labDescCell = 'padding:4px 6px;vertical-align:top;background:' . $docBody . ';border:1px solid ' . $docBd . ';font-size:7.5pt;line-height:1.45;white-space:normal;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;';
    $labDataCell = $labDescCell;
    $labPartsNameCell = $labDescCell;
    $partTypeCellBase = 'background:' . $docBody . ';border:1px solid ' . $docBd . ';vertical-align:middle;text-align:center;font-size:7.5pt;padding:4px 6px;white-space:nowrap;line-height:1.2;word-break:keep-all;';
    $partTypeLabel = static function (string $text): string {
        return inv_doc_h(str_replace(' ', "\u{00a0}", trim($text)));
    };
    $labourTotal = 0.0;
    $labourHours = 0.0;
    $labourRateSum = 0.0;
    $partsTotal = 0.0;
    ?>

    <?php if ($hasLabourSection): ?>
    <table class="aq-doc-data-table aq-labour-table" style="width:100%;max-width:100%;box-sizing:border-box;border-collapse:collapse;table-layout:fixed;margin-bottom:1mm;border:1px solid <?php echo $docBd; ?>;font-size:8pt;">
      <colgroup>
        <col style="width:<?php echo $colDesc; ?>"/>
        <col style="width:<?php echo $colName; ?>"/>
        <col style="width:<?php echo $colQty; ?>"/>
        <col style="width:<?php echo $colRate; ?>"/>
        <col style="width:<?php echo $colTot; ?>"/>
      </colgroup>
      <thead>
        <?php
        $labourHdrCount = max(1, count($labourHeaderLabels));
        foreach ($labourHeaderLabels as $labelIndex => $headerLabel):
            $hdrRowClass = $labelIndex === 0 ? 'aq-labour-hdr-cols' : 'aq-labour-hdr-label';
            ?>
        <?php $labelHdrStyle = ($doc_blank_only && $labelIndex > 0) ? $subHdrStyle : $hdrStyle; ?>
        <?php $labelHdrClass = ($doc_blank_only && $labelIndex > 0) ? ' aq-lab-hdr-sub' : ''; ?>
        <tr class="<?php echo $hdrRowClass; ?>">
          <?php if ($labelIndex === 0): ?>
          <th rowspan="<?php echo (int) $labourHdrCount; ?>" class="aq-lab-hdr-side" style="<?php echo $hdrStyle; ?>">Description</th>
          <th class="<?php echo trim($labelHdrClass); ?>" style="<?php echo $labelHdrStyle; ?>"><?php echo inv_doc_h($headerLabel); ?></th>
          <th rowspan="<?php echo (int) $labourHdrCount; ?>" class="aq-lab-hdr-side" style="<?php echo $hdrStyle; ?>">Hour</th>
          <th rowspan="<?php echo (int) $labourHdrCount; ?>" class="aq-lab-hdr-side" style="<?php echo $hdrStyle; ?>">Rate</th>
          <th rowspan="<?php echo (int) $labourHdrCount; ?>" class="aq-lab-hdr-side" style="<?php echo $hdrStyle; ?>">Total</th>
          <?php elseif ($doc_blank_only): ?>
          <th colspan="4" class="<?php echo trim($labelHdrClass); ?>" style="<?php echo $labelHdrStyle; ?>"><?php echo inv_doc_h($headerLabel); ?></th>
          <?php else: ?>
          <th class="<?php echo trim($labelHdrClass); ?>" style="<?php echo $labelHdrStyle; ?>"><?php echo inv_doc_h($headerLabel); ?></th>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </thead>
      <tbody>
        <?php
        $emitLabourBlock = function ($title, array $rows, $isFirstBlock, $isLastBlock) use (&$labourTotal, &$labourHours, &$labourRateSum, $labDataCell, $doc_blank_only, $docBd, $docBody) {
            $rowCount = count($rows);
            if ($rowCount <= 0) {
                return;
            }
            $sep = $isFirstBlock ? '' : 'border-top:1px solid ' . $docBd . ';';
            $blockEnd = '';
            $metrics = inv_doc_labour_block_metrics($rows, $doc_blank_only);
            if (!$doc_blank_only) {
                $labourTotal += $metrics['total'];
                $labourHours += $metrics['hours'];
                $labourRateSum += $metrics['rate'];
            }
            $metricSpan = ($isFirstBlock ? 'border-top:1px solid ' . $docBd . ';' : $sep) . $blockEnd;
            $titleCellStyle = 'background:' . $docBody . ';border:1px solid ' . $docBd . ';text-align:center;vertical-align:middle;padding:4px 6px;font-size:7.5pt;line-height:1.45;';
            if ($title !== '') {
                $titleCellStyle .= 'font-weight:bold;font-size:8pt;';
            }
            $metricCellStyle = 'padding:3px 5px;text-align:center;vertical-align:middle;background:' . $docBody . ';border:1px solid ' . $docBd . ';font-size:7.5pt;'
                . ($isFirstBlock ? 'border-top:1px solid ' . $docBd . ';' : $sep) . $blockEnd;
            for ($i = 0; $i < $rowCount; $i++) {
                $row = $rows[$i] ?? null;
                $desc = is_array($row) ? inv_doc_h($row['description'] ?? '') : '&nbsp;';
                if ($desc === '') {
                    $desc = '&nbsp;';
                }
                $descBorder = $i > 0 ? 'border-top:1px solid ' . $docBd . ';' : ($isFirstBlock ? '' : $sep);
                $lastBodyRow = $isLastBlock && $i === $rowCount - 1;
                echo '<tr' . ($lastBodyRow ? ' class="aq-doc-last-body-row"' : '') . '>';
                if ($i === 0) {
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-labour-title-cell" style="' . $titleCellStyle . $metricSpan . '">' . ($title !== '' ? inv_doc_h($title) : '&nbsp;') . '</td>';
                    echo '<td class="aq-labour-desc-cell" style="' . $labDataCell . $descBorder . '">' . $desc . '</td>';
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-labour-metric-cell" style="' . $metricCellStyle . '">' . inv_doc_h($metrics['hours_fmt']) . '</td>';
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-labour-metric-cell" style="' . $metricCellStyle . '">' . inv_doc_h($metrics['rate_fmt']) . '</td>';
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-labour-metric-cell" style="' . $metricCellStyle . '">' . inv_doc_h($metrics['total_fmt']) . '</td>';
                } else {
                    echo '<td class="aq-labour-desc-cell" style="' . $labDataCell . $descBorder . '">' . $desc . '</td>';
                }
                echo '</tr>';
            }
        };

        if ($doc_blank_only) {
            $labBlocks = [];
            if ($normalRowsToShow > 0) {
                $labBlocks[] = ['title' => 'Normal Time', 'rows' => array_fill(0, $normalRowsToShow, null)];
            }
            if ($overtimeRowsToShow > 0) {
                $labBlocks[] = ['title' => 'Overtime', 'rows' => array_fill(0, $overtimeRowsToShow, null)];
            }
            if ($holidayRowsToShow > 0) {
                $labBlocks[] = ['title' => 'Public Holiday', 'rows' => array_fill(0, $holidayRowsToShow, null)];
            }
            foreach ($labBlocks as $idx => $block) {
                $emitLabourBlock($block['title'], $block['rows'], $idx === 0, $idx === count($labBlocks) - 1);
            }
        } else {
            foreach ($labourPrintBlocks as $idx => $block) {
                $emitLabourBlock((string)($block['title'] ?? ''), $block['rows'], $idx === 0, $idx === count($labourPrintBlocks) - 1);
            }
        }
        ?>
      </tbody>
    </table>
    <div style="height:4px;font-size:0;line-height:0;background:#fff;"></div>
    <table class="aq-total-strip-table" style="width:42%;margin-left:auto;border-collapse:collapse;table-layout:fixed;font-size:7pt;line-height:1.1;margin-bottom:2mm;">
      <colgroup><col style="width:23.8095%"><col style="width:23.8095%"><col style="width:26.1905%"><col style="width:26.1905%"></colgroup>
      <tbody><tr>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>">Total</td>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>"><?php echo $doc_blank_only || $labourHours <= 0 ? '' : inv_doc_hours($labourHours); ?></td>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>"><?php echo $doc_blank_only || $labourRateSum <= 0 ? '' : number_format($labourRateSum, 2); ?></td>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>"><?php echo $doc_blank_only || $labourTotal <= 0 ? '' : inv_doc_num($labourTotal); ?></td>
      </tr></tbody>
    </table>
    <?php endif; ?>

    <?php
    $partBlocks = [];
    $consumablesLabel = trim((string)($formData['consumables_label'] ?? '')) ?: 'Consumables';
    if (!$doc_blank_only) {
        if ($partsRowsToShow > 0) {
            foreach ($partsFiltered as $pidx => $prow) {
                $partBlocks[] = [
                    'label' => $pidx === 0 ? 'Parts Supply' : '',
                    'count' => 1,
                    'getter' => static function () use ($prow) {
                        return $prow;
                    },
                ];
            }
        }
        foreach ($consFiltered as $crow) {
            $ctype = trim((string)($crow['callout_type'] ?? ''));
            $cname = trim((string)($crow['item_name'] ?? ''));
            if ($ctype === '' && $cname !== '' && in_array(strtolower($cname), $partTypeLabelsForFilter, true)) {
                $ctype = $cname;
            }
            $partBlocks[] = [
                'label' => $ctype !== '' ? $ctype : 'Call-out',
                'count' => 1,
                'getter' => static function () use ($crow) {
                    return $crow;
                },
            ];
        }
    }
    $hasPartsSection = $includeParts && ($doc_blank_only
        ? ($partsTopLines > 0 || $partsRowsToShow > 0 || $consRowsToShow > 0)
        : count($partBlocks) > 0);
    ?>
    <?php if ($hasPartsSection): ?>
    <table class="aq-doc-data-table aq-doc-parts-section" style="width:100%;max-width:100%;box-sizing:border-box;border-collapse:collapse;table-layout:fixed;margin-top:0;margin-bottom:2mm;border:1px solid <?php echo $docBd; ?>;font-size:8pt;">
      <colgroup>
        <col style="width:<?php echo $colPartType; ?>"/>
        <col style="width:<?php echo $colPartName; ?>"/>
        <col style="width:<?php echo $colQty; ?>"/>
        <col style="width:<?php echo $colRate; ?>"/>
        <col style="width:<?php echo $colTot; ?>"/>
      </colgroup>
      <thead>
        <tr>
          <th style="<?php echo $hdrStyle; ?>"></th>
          <th style="<?php echo $hdrStyle; ?>">Item Name</th>
          <th style="<?php echo $hdrStyle; ?>">Qty</th>
          <th style="<?php echo $hdrStyle; ?>">Unit Cost</th>
          <th style="<?php echo $hdrStyle; ?>">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $partLabelsToHide = $partTypeLabelsForFilter;
        $partItemDisplay = function ($row) use ($partLabelsToHide) {
            if (!is_array($row)) {
                return '&nbsp;';
            }
            $name = trim((string)($row['item_name'] ?? ''));
            if ($name !== '' && in_array(strtolower($name), $partLabelsToHide, true)) {
                $name = '';
            }
            if (strtolower($name) === 'parts supply') {
                $name = '';
            }
            return $name !== '' ? inv_doc_h($name) : '&nbsp;';
        };
        $appendPartSection = function ($label, $rowCount, $getter, $isFirstBlock, $isLastBlock) use (&$partsTotal, $partItemDisplay, $labPartsNameCell, $docBd, $partTypeCellBase, $partTypeLabel) {
            if ($rowCount <= 0) {
                return;
            }
            $sep = $isFirstBlock ? '' : 'border-top:1px solid ' . $docBd . ';';
            $blockEnd = '';
            $metricSpan = ($isFirstBlock ? 'border-top:1px solid ' . $docBd . ';' : $sep) . $blockEnd;
            $rowLabel = trim((string) $label);
            $titleCellStyle = $partTypeCellBase . $metricSpan . ($rowLabel !== '' ? 'font-weight:bold;font-size:8pt;' : '');
            $metricCellStyle = 'padding:3px 5px;text-align:center;vertical-align:middle;background:' . inv_doc_table_body_bg() . ';border:1px solid ' . $docBd . ';font-size:7.5pt;' . $metricSpan;
            for ($i = 0; $i < $rowCount; $i++) {
                $row = $getter($i);
                if (is_array($row)) {
                    $partsTotal += (float)($row['total'] ?? 0);
                }
                $metric = ['qty' => '', 'unit_cost' => '', 'total' => ''];
                if (is_array($row)) {
                    if ((float)($row['qty'] ?? 0) > 0) {
                        $metric['qty'] = inv_doc_qty($row['qty'] ?? 0);
                    }
                    if ((float)($row['unit_cost'] ?? 0) > 0) {
                        $metric['unit_cost'] = inv_doc_num($row['unit_cost'] ?? 0);
                    }
                    if ((float)($row['total'] ?? 0) > 0) {
                        $metric['total'] = inv_doc_num($row['total'] ?? 0);
                    }
                }
                $name = $partItemDisplay($row);
                $descBorder = $i > 0 ? 'border-top:1px solid ' . $docBd . ';' : ($isFirstBlock ? '' : $sep);
                $typeLabel = ($i === 0 && $rowLabel !== '') ? $partTypeLabel($rowLabel) : '&nbsp;';
                $lastPartBodyRow = $isLastBlock && $i === $rowCount - 1;
                echo '<tr' . ($lastPartBodyRow ? ' class="aq-doc-last-body-row"' : '') . '>';
                if ($i === 0) {
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-part-type-cell" style="' . $titleCellStyle . '">' . $typeLabel . '</td>';
                    echo '<td class="aq-parts-name-cell" style="' . $labPartsNameCell . $descBorder . '">' . $name . '</td>';
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-labour-metric-cell" style="' . $metricCellStyle . '">' . inv_doc_h($metric['qty']) . '</td>';
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-labour-metric-cell" style="' . $metricCellStyle . '">' . inv_doc_h($metric['unit_cost']) . '</td>';
                    echo '<td rowspan="' . (int)$rowCount . '" class="aq-labour-metric-cell" style="' . $metricCellStyle . '">' . inv_doc_h($metric['total']) . '</td>';
                } else {
                    echo '<td class="aq-parts-name-cell" style="' . $labPartsNameCell . $descBorder . '">' . $name . '</td>';
                }
                echo '</tr>';
            }
        };
        if ($doc_blank_only) {
            $blankPartCell = 'background:' . $docBody . ';border:1px solid ' . $docBd . ';font-size:7.5pt;padding:4px 6px;vertical-align:middle;';
            for ($ti = 0; $ti < $partsTopLines; $ti++) {
                echo '<tr>';
                if ($ti === 0) {
                    echo '<td rowspan="' . (int) $partsTopLines . '" class="aq-part-type-cell" style="' . $blankPartCell . '">&nbsp;</td>';
                }
                echo '<td class="aq-parts-name-cell" style="' . $blankPartCell . '">&nbsp;</td>';
                echo '<td class="aq-labour-metric-cell" style="' . $blankPartCell . '">&nbsp;</td>';
                echo '<td class="aq-labour-metric-cell" style="' . $blankPartCell . '">&nbsp;</td>';
                echo '<td class="aq-labour-metric-cell" style="' . $blankPartCell . '">&nbsp;</td>';
                echo '</tr>';
            }
            if ($partsTopLines > 0 && ($partsRowsToShow > 0 || $consRowsToShow > 0)) {
                echo '<tr class="aq-parts-thick-divider"><td colspan="5" style="height:5px;padding:0;margin:0;background:' . $docBd . ';border:1px solid ' . $docBd . ';border-top:1px solid ' . $docBd . ';border-bottom:3px solid ' . $docBd . ';font-size:0;line-height:0;"></td></tr>';
            }
            $blankPartIdx = 0;
            if ($partsRowsToShow > 0) {
                $appendPartSection('Parts Supply', $partsRowsToShow, static function () {
                    return null;
                }, $blankPartIdx === 0, $consRowsToShow <= 0);
                $blankPartIdx++;
            }
            if ($consRowsToShow > 0) {
                $appendPartSection($consumablesLabel, $consRowsToShow, static function () {
                    return null;
                }, $blankPartIdx === 0, true);
            }
        } else {
            foreach ($partBlocks as $idx => $block) {
                $appendPartSection($block['label'], $block['count'], $block['getter'], $idx === 0, $idx === count($partBlocks) - 1);
            }
        }
        $partsQtySum = 0.0;
        $partsCostSum = 0.0;
        foreach ($partsFiltered as $r) {
            $partsQtySum += (float)($r['qty'] ?? 0);
            $partsCostSum += (float)($r['unit_cost'] ?? 0);
        }
        foreach ($consFiltered as $r) {
            $partsQtySum += (float)($r['qty'] ?? 0);
            $partsCostSum += (float)($r['unit_cost'] ?? 0);
        }
        ?>
      </tbody>
    </table>
    <div style="height:4px;font-size:0;line-height:0;background:#fff;"></div>
    <table class="aq-total-strip-table" style="width:42%;margin-left:auto;border-collapse:collapse;table-layout:fixed;font-size:7pt;line-height:1.1;margin-bottom:2mm;">
      <colgroup><col style="width:23.8095%"><col style="width:23.8095%"><col style="width:26.1905%"><col style="width:26.1905%"></colgroup>
      <tbody><tr>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>">Total Parts</td>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>"><?php echo $doc_blank_only || $partsQtySum <= 0 ? '' : inv_doc_qty($partsQtySum); ?></td>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>"><?php echo $doc_blank_only || $partsCostSum <= 0 ? '' : number_format($partsCostSum, 2); ?></td>
        <td class="aq-total-strip-hdr" style="<?php echo $totalStripCell; ?>"><?php echo $doc_blank_only || $partsTotal <= 0 ? '' : inv_doc_num($partsTotal); ?></td>
      </tr></tbody>
    </table>
    <?php endif; ?>

    <?php
    if (!isset($subTotal)) {
        $subTotal = isset($totals['subtotal']) ? (float) $totals['subtotal'] : ((float) ($doc_amount ?? 0) - (float) ($doc_vat_amount ?? 0));
    }
    if (!isset($vatTotal)) {
        $vatTotal = isset($totals['vat_amount']) ? (float) $totals['vat_amount'] : (float) ($doc_vat_amount ?? 0);
    }
    if (!isset($grandTotal)) {
        $grandTotal = isset($totals['grand_total']) ? (float) $totals['grand_total'] : (float) ($doc_amount ?? 0);
    }
    ?>
    <div class="aq-doc-footer-block">
    <table style="width:100%;border-collapse:collapse;font-size:7.5pt;margin-bottom:1.5mm;"><tbody><tr>
      <td style="vertical-align:top;width:40%;padding:0 6px 0 0;border:none;">
        <div style="font-style:italic;font-weight:bold;margin-bottom:3px;font-size:8pt">Note: Unforseen is not quoted for.</div>
        <table style="border-collapse:collapse;font-size:8pt;width:auto;max-width:90%"><tbody>
          <tr>
            <td style="border:1px solid <?php echo $docBd; ?>;background:#fff;color:#dc2626;font-weight:bold;padding:3px 5px;font-size:8pt;width:50%">New Banking Details:</td>
            <td style="border:1px solid <?php echo $docBd; ?>;background:#fff;width:50%"></td>
          </tr>
          <tr>
            <td colspan="2" style="border:1px solid <?php echo $docBd; ?>;border-top:none;padding:5px 8px;background:#fff;font-size:8pt;line-height:1.6">
              <div style="font-weight:bold;">S.V Auto Truck Repair cc</div>
              <div style="font-weight:bold;">Bank Windhoek Limited</div>
              <div style="font-weight:bold;">Acc: CHK: 8040770120</div>
              <div style="font-weight:bold;">Branch code: 486-372</div>
              <div style="font-weight:bold;">Business Cheque Account</div>
            </td>
          </tr>
        </tbody></table>
      </td>
      <td style="vertical-align:top;width:60%;padding:0 0 0 8mm;border:none;">
        <table class="aq-doc-sum-table" style="width:<?php echo $docSumTableWidth; ?>;border-collapse:collapse;font-size:7pt;margin-left:auto;margin-top:0;margin-bottom:8mm;border:1px solid <?php echo $docBd; ?>">
          <colgroup><col style="width:58%"><col style="width:42%"></colgroup>
          <tbody>
          <tr>
            <td style="<?php echo $docSumCell; ?>">Subtotal</td>
            <td style="<?php echo $docSumValCell; ?>"><?php echo $doc_blank_only ? '' : number_format($subTotal, 2); ?></td>
          </tr>
          <tr>
            <td style="<?php echo $docSumCell; ?>">VAT</td>
            <td style="<?php echo $docSumValCell; ?>"><?php echo $doc_blank_only ? '' : number_format($vatTotal, 2); ?></td>
          </tr>
          <tr>
            <td style="<?php echo $docSumCell; ?>">Total</td>
            <td style="<?php echo $docSumValCell; ?>"><?php echo $doc_blank_only ? '' : number_format($grandTotal, 2); ?></td>
          </tr>
        </tbody></table>
        <?php
        // Manager signs on paper; system sign-off fields are not printed on the document.
        ?>
        <div style="margin-bottom:7px;font-size:8pt;display:flex;align-items:center;gap:6px;line-height:1.1;">
          <span style="min-width:60px;">Approved</span>
          <span style="flex:1;border-bottom:1px solid #4b5563;padding-left:6px;font-weight:bold;color:#111;"></span>
        </div>
        <div style="font-size:8pt;display:flex;align-items:center;gap:6px;line-height:1.1;">
          <span style="min-width:60px;">Date</span>
          <span style="flex:1;border-bottom:1px solid #4b5563;padding-left:6px;font-weight:bold;color:#111;"></span>
        </div>
      </td>
    </tr></tbody></table>

    <?php
    $isTaxInvoiceDoc = stripos((string) ($doc_title ?? ''), 'invoice') !== false;
    $payNoteKind = strtolower((string) ($invoice_payment_note_kind ?? ''));
    if ($isTaxInvoiceDoc && !empty($show_invoice_payment_note) && in_array($payNoteKind, ['paid', 'partial'], true)):
        $payNoteDate = !empty($paid_at) ? formatInvoiceDate($paid_at) : '';
    ?>
    <?php if ($payNoteKind === 'paid'): ?>
    <div style="text-align:center;padding:14px 16px;background:#E8F5E9;border:1px solid #A5D6A7;border-radius:8px;color:#2E7D32;font-weight:700;margin-bottom:10mm;font-size:11pt;">
      <span style="display:inline-block;margin-right:6px;">&#10003;</span> PAID<?php echo $payNoteDate !== '' ? ' on ' . inv_doc_h($payNoteDate) : ''; ?> — Thank you for your payment.
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:14px 16px;background:#FFF7ED;border:1px solid #FDBA74;border-radius:8px;color:#C2410C;font-weight:700;margin-bottom:10mm;font-size:11pt;">
      PARTIALLY PAID<?php echo $payNoteDate !== '' ? ' — updated ' . inv_doc_h($payNoteDate) : ''; ?> — Balance may remain on this invoice.
    </div>
    <?php endif; ?>
    <?php elseif (!empty($show_paid_banner) && !empty($paid_at)): ?>
    <div style="text-align:center;padding:15px;background:#E8F5E9;border-radius:8px;color:#388E3C;font-weight:600;margin-bottom:10mm;">
      <i class="fas fa-check-circle"></i> PAID on <?php echo inv_doc_h(formatInvoiceDate($paid_at)); ?>
    </div>
    <?php endif; ?>

    <?php
    $footerSrc = trim((string) ($footer_img_url ?? ''));
    if ($footerSrc === '' && !empty($footer_base64)) {
        $footerSrc = (string) $footer_base64;
    }
    ?>
    <?php if ($footerSrc !== ''): ?>
    <div style="margin-top:1mm">
      <img style="width:100%;max-height:13mm;object-fit:contain;display:block" src="<?php echo inv_doc_h($footerSrc); ?>" alt="Footer"/>
    </div>
    <?php endif; ?>
    <div style="text-align:center;font-style:italic;font-size:8.5pt;border-top:.5px solid #ccc;padding-top:3px">
      <em>Essence of perfection - Thank you for doing business with us</em>
    </div>
    </div>

  </div>
