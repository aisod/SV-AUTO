<?php

declare(strict_types=1);

/**
 * Convert job card Quotation Table Configuration labour sections → add_quotation labour_rows.
 *
 * @param array<int,array<string,mixed>> $sections
 * @return array<int,array<string,mixed>>
 */
function sv_jc_build_labour_rows_from_sections(
    array $sections,
    callable $uid,
    float $rateNormal,
    float $rateOvertime,
    float $rateHoliday
): array {
    $labourRows = [];
    $order = 0;

    foreach ($sections as $secIdx => $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $type = (string) ($sec['section_type'] ?? 'normal_time');
        if ($type === 'custom') {
            $type = 'normal_time';
        }
        $sectionLabel = trim((string) ($sec['section_label'] ?? ''));
        $sectionKey = (int) $secIdx;
        $hours = (float) ($sec['hours'] ?? 0);
        $rate = isset($sec['rate']) && $sec['rate'] !== '' && $sec['rate'] !== null
            ? (float) $sec['rate']
            : 0.0;
        if ($rate <= 0) {
            if ($type === 'overtime' && $rateOvertime > 0) {
                $rate = $rateOvertime;
            } elseif ($type === 'public_holiday') {
                $rate = $rateHoliday > 0 ? $rateHoliday : $rateNormal;
            } else {
                $rate = $rateNormal;
            }
        }

        $rows = isset($sec['rows']) && is_array($sec['rows']) ? $sec['rows'] : [];
        if ($rows === []) {
            $rows = [['activity' => '']];
        }

        $metricsAssigned = false;
        foreach ($rows as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }
            $desc = trim((string) ($row['activity'] ?? ''));
            $rowHours = 0.0;
            $rowRate = 0.0;
            $rowTotal = 0.0;

            if ($rowIndex === 0 && ($hours > 0 || $rate > 0)) {
                $rowHours = $hours;
                $rowRate = $rate;
                $rowTotal = round($hours * $rate, 2);
                $metricsAssigned = true;
            }

            $labourRows[] = [
                'id' => $uid('lr'),
                'category' => $type,
                'description' => $desc,
                'hours' => $rowHours,
                'rate' => $rowRate,
                'total' => $rowTotal,
                'include_in_print' => true,
                'sort_order' => $order++,
                'section_key' => $sectionKey,
                'section_label' => $sectionLabel,
            ];
        }

        if (!$metricsAssigned && $hours > 0 && $rate > 0) {
            $labourRows[] = [
                'id' => $uid('lr'),
                'category' => $type,
                'description' => $sectionLabel !== '' ? $sectionLabel : 'Labour',
                'hours' => $hours,
                'rate' => $rate,
                'total' => round($hours * $rate, 2),
                'include_in_print' => true,
                'sort_order' => $order++,
                'section_key' => $sectionKey,
                'section_label' => $sectionLabel,
            ];
        }
    }

    return $labourRows;
}

/**
 * @return array{0: array<int,array<string,mixed>>, 1: array<int,array<string,mixed>>}
 */
function sv_jc_build_parts_and_cons_from_quotation_parts(array $parts, callable $uid): array
{
    $partsRows = [];
    $consRows = [];
    $pOrder = 0;
    $cOrder = 0;

    foreach ($parts as $p) {
        if (!is_array($p)) {
            continue;
        }
        $sectionType = (string) ($p['section_type'] ?? 'parts_supply');
        $qty = (float) ($p['qty'] ?? 0);
        $unitCost = (float) ($p['unit_cost'] ?? 0);
        $storedLineTotal = isset($p['total']) ? (float) $p['total'] : 0.0;
        if ($sectionType === 'callout' || $sectionType === 'consumables') {
            $total = ($qty > 0 || $unitCost > 0) ? round($qty * $unitCost, 2) : 0.0;
        } elseif ($storedLineTotal > 0) {
            $total = round($storedLineTotal, 2);
        } else {
            $total = ($qty > 0 || $unitCost > 0) ? round($qty * $unitCost, 2) : 0.0;
        }

        if ($sectionType === 'callout' || $sectionType === 'consumables') {
            $itemName = trim((string) ($p['item_name'] ?? ''));
            $calloutType = trim((string) ($p['callout_type'] ?? ''));
            // Item column stays blank when user did not enter a description (type shows in section header).
            $displayName = $itemName;
            $typeLabels = ['consumables', 'call out fee', 'kilometres', 'consumable', 'call-out', 'call out'];
            if ($displayName !== '' && in_array(strtolower($displayName), $typeLabels, true)) {
                $displayName = '';
            }
            if ($displayName === '' && $calloutType === '' && $qty <= 0 && $unitCost <= 0) {
                continue;
            }
            $resolvedCallout = $calloutType;
            if ($resolvedCallout === '' && $displayName !== '') {
                $lowerName = strtolower($displayName);
                if (in_array($lowerName, $typeLabels, true)) {
                    $resolvedCallout = $displayName;
                    $displayName = '';
                }
            }
            $consRows[] = [
                'id' => $uid('cr'),
                'category' => 'consumables',
                'item_name' => $displayName,
                'callout_type' => $resolvedCallout,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total' => $total,
                'include_in_print' => true,
                'sort_order' => $cOrder++,
            ];
            continue;
        }

        $name = trim((string) ($p['item_name'] ?? ''));
        if ($name === '' && $qty <= 0 && $unitCost <= 0) {
            continue;
        }
        $partsRows[] = [
            'id' => $uid('pr'),
            'category' => 'parts_supply',
            'item_name' => $name,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total' => $total,
            'include_in_print' => true,
            'sort_order' => $pOrder++,
        ];
    }

    return [$partsRows, $consRows];
}

/**
 * @param array<int,array<string,mixed>> $sections
 * @return array{show_normal: bool, show_overtime: bool, show_holiday: bool, blank_normal: int, blank_overtime: int, blank_holiday: int}
 */
function sv_jc_labour_section_flags(array $sections): array
{
    $showNormal = false;
    $showOvertime = false;
    $showHoliday = false;
    $blankNormal = 0;
    $blankOvertime = 0;
    $blankHoliday = 0;

    foreach ($sections as $sec) {
        if (!is_array($sec)) {
            continue;
        }
        $type = (string) ($sec['section_type'] ?? 'normal_time');
        if ($type === 'custom') {
            $type = 'normal_time';
        }
        $rowCount = isset($sec['rows']) && is_array($sec['rows']) ? count($sec['rows']) : 0;
        $rowCount = max(1, $rowCount);

        if ($type === 'overtime') {
            $showOvertime = true;
            $blankOvertime += $rowCount;
        } elseif ($type === 'public_holiday') {
            $showHoliday = true;
            $blankHoliday += $rowCount;
        } else {
            $showNormal = true;
            $blankNormal += $rowCount;
        }
    }

    $blankNormal = max(2, $blankNormal);
    $blankOvertime = max(2, $blankOvertime);
    $blankHoliday = max(2, $blankHoliday);

    return [
        'show_normal' => $showNormal,
        'show_overtime' => $showOvertime,
        'show_holiday' => $showHoliday,
        'blank_normal' => $blankNormal,
        'blank_overtime' => $blankOvertime,
        'blank_holiday' => $blankHoliday,
    ];
}

/**
 * Ensure quotation print flags match built labour rows (job cards often save show_* as false).
 *
 * @param array<string,mixed> $form
 * @param array<int,array<string,mixed>> $labourRows
 */
function sv_jc_patch_labour_visibility_flags(array &$form, array $labourRows): void
{
    if ($labourRows === []) {
        return;
    }

    $form['print_blank_show_labour'] = true;
    foreach ($labourRows as $row) {
        if (empty($row['include_in_print'])) {
            continue;
        }
        $category = (string) ($row['category'] ?? 'normal_time');
        if ($category === 'overtime') {
            $form['show_overtime'] = true;
        } elseif ($category === 'public_holiday') {
            $form['show_public_holiday'] = true;
        } else {
            $form['show_normal_time'] = true;
        }
    }
}

/**
 * Build and INSERT a quotations row from a newly saved job card (same JSON shape as add_quotation.php save).
 *
 * @return int New quotation id
 */
function sv_auto_create_quotation_from_job_card(PDO $pdo, int $jobCardId): int
{
    $qJsonMarker = "QUOTATION_JSON_V1\n";

    $dup = $pdo->prepare('SELECT id FROM quotations WHERE job_card_id = ? AND deleted_at IS NULL LIMIT 1');
    $dup->execute([$jobCardId]);
    if ($dup->fetchColumn()) {
        throw new RuntimeException('Quotation already exists for this job card.');
    }

    $stmt = $pdo->prepare('
        SELECT jc.*, c.name AS client_name, c.phone AS client_phone, c.email AS client_email, c.address AS client_address,
               v.reg_no, v.model, v.vin_no
        FROM job_cards jc
        LEFT JOIN clients c ON jc.client_id = c.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        WHERE jc.id = ? AND jc.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->execute([$jobCardId]);
    $jc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$jc) {
        throw new RuntimeException('Job card not found.');
    }

    $xd = [];
    if (!empty($jc['extra_data'])) {
        $decoded = json_decode((string) $jc['extra_data'], true);
        $xd = is_array($decoded) ? $decoded : [];
    }

    $currency = function_exists('getCurrency') ? getCurrency() : ['symbol' => 'N$'];
    $sym = $currency['symbol'] ?? 'N$';

    $quoteNum = sv_auto_next_quote_number($pdo);

    $rateNormal = (float) ($xd['rate_normal'] ?? 560);
    if ($rateNormal <= 0) {
        $rateNormal = 560;
    }
    $rateOvertime = (float) ($xd['rate_after'] ?? 0);
    $rateHoliday = (float) ($xd['rate_holiday'] ?? 1500);
    $vatRateConfig = (float) ($xd['vat_rate'] ?? 15);

    $boolConfig = static function (string $key, bool $default = true) use ($xd): bool {
        if (!array_key_exists($key, $xd)) {
            return $default;
        }
        $value = $xd[$key];
        if (is_bool($value)) {
            return $value;
        }
        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    };
    $intConfig = static function (string $key, int $default, int $min, int $max) use ($xd): int {
        $value = (int)($xd[$key] ?? $default);
        return max($min, min($max, $value));
    };

    $labTotalHours = (float) ($xd['labor_total_hours'] ?? $jc['total_hours'] ?? 0);
    $labRateApplied = (float) ($xd['labor_rate_applied'] ?? $jc['labor_rate_applied'] ?? 0);
    $labCost = (float) ($xd['labor_cost'] ?? $jc['labor_cost'] ?? 0);

    $descLines = [];
    if (!empty($xd['description_lines']) && is_array($xd['description_lines'])) {
        foreach ($xd['description_lines'] as $ln) {
            $ln = trim((string) $ln);
            if ($ln !== '') {
                $descLines[] = $ln;
            }
        }
    }
    $descBlob = $descLines !== [] ? implode("\n", $descLines) : trim((string) ($jc['description'] ?? ''));

    /** @var array<int,array<string,mixed>> $labourRows */
    $labourRows = [];

    $uid = static function (string $prefix): string {
        return $prefix . '-' . bin2hex(random_bytes(6));
    };

    $quotationSections = isset($xd['quotation_labour_sections']) && is_array($xd['quotation_labour_sections'])
        ? $xd['quotation_labour_sections']
        : [];
    $quotationParts = isset($xd['quotation_parts']) && is_array($xd['quotation_parts'])
        ? $xd['quotation_parts']
        : [];
    $sectionFlags = sv_jc_labour_section_flags($quotationSections);

    if ($quotationSections !== []) {
        $labourRows = sv_jc_build_labour_rows_from_sections(
            $quotationSections,
            $uid,
            $rateNormal,
            $rateOvertime,
            $rateHoliday
        );
    }

    $postedLabourDesc = isset($xd['labour_desc']) && is_array($xd['labour_desc']) ? $xd['labour_desc'] : [];
    $postedLabourHours = isset($xd['labour_hours']) && is_array($xd['labour_hours']) ? $xd['labour_hours'] : [];
    $postedLabourRates = isset($xd['labour_rate']) && is_array($xd['labour_rate']) ? $xd['labour_rate'] : [];
    $postedLabourTotals = isset($xd['labour_total']) && is_array($xd['labour_total']) ? $xd['labour_total'] : [];
    $postedLabourCount = max(count($postedLabourDesc), count($postedLabourHours), count($postedLabourRates), count($postedLabourTotals));
    if ($labourRows === []) {
    for ($i = 0; $i < $postedLabourCount; $i++) {
        $desc = trim((string)($postedLabourDesc[$i] ?? ''));
        $hours = (float)($postedLabourHours[$i] ?? 0);
        $rate = (float)($postedLabourRates[$i] ?? 0);
        $total = (float)($postedLabourTotals[$i] ?? 0);
        if ($desc === '' && $hours <= 0 && $rate <= 0 && $total <= 0) {
            continue;
        }
        if ($rate <= 0) {
            $rate = $rateNormal;
        }
        if ($total <= 0 && ($hours > 0 || $rate > 0)) {
            $total = round($hours * $rate, 2);
        }
        $labourRows[] = [
            'id' => $uid('lr'),
            'category' => 'normal_time',
            'description' => $desc,
            'hours' => $hours,
            'rate' => $rate,
            'total' => $total,
            'include_in_print' => true,
            'sort_order' => $i,
        ];
    }
    }

    if ($labourRows === [] && ($labTotalHours > 0 || $labCost > 0)) {
        $hours = $labTotalHours;
        $rate = $labRateApplied > 0 ? $labRateApplied : $rateNormal;
        if ($hours <= 0 && $labCost > 0) {
            $hours = 1.0;
            $rate = $labCost;
        }
        $total = round($hours * $rate, 2);
        $labourRows[] = [
            'id' => $uid('lr'),
            'category' => 'normal_time',
            'description' => $descBlob !== '' ? $descBlob : 'Labour (from job card)',
            'hours' => $hours,
            'rate' => $rate,
            'total' => $total,
            'include_in_print' => true,
            'sort_order' => 0,
        ];
    } elseif ($labourRows === []) {
        $wd = isset($xd['work_details']) && is_array($xd['work_details']) ? $xd['work_details'] : [];
        $ta = isset($xd['time_allocated']) && is_array($xd['time_allocated']) ? $xd['time_allocated'] : [];
        $max = max(count($wd), count($ta));
        $order = 0;
        for ($i = 0; $i < $max; $i++) {
            $line = trim((string) ($wd[$i] ?? ''));
            $tRaw = trim((string) ($ta[$i] ?? ''));
            if ($line === '' && $tRaw === '') {
                continue;
            }
            $hours = is_numeric($tRaw) ? (float) $tRaw : (float) filter_var($tRaw, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_SCIENTIFIC);
            if (!is_finite($hours)) {
                $hours = 0.0;
            }
            $rate = $rateNormal;
            $labourRows[] = [
                'id' => $uid('lr'),
                'category' => 'normal_time',
                'description' => $line !== '' ? $line : 'Work detail',
                'hours' => $hours,
                'rate' => $rate,
                'total' => round($hours * $rate, 2),
                'include_in_print' => true,
                'sort_order' => $order++,
            ];
        }
    }

    if ($labourRows === []) {
        $labourRows[] = [
            'id' => $uid('lr'),
            'category' => 'normal_time',
            'description' => $descBlob !== '' ? $descBlob : 'Attend to service (see job card)',
            'hours' => 0,
            'rate' => $rateNormal,
            'total' => 0,
            'include_in_print' => true,
            'sort_order' => 0,
        ];
        $labourRows[] = [
            'id' => $uid('lr'),
            'category' => 'normal_time',
            'description' => '',
            'hours' => 0,
            'rate' => $rateNormal,
            'total' => 0,
            'include_in_print' => true,
            'sort_order' => 1,
        ];
    }

    /** @var array<int,array<string,mixed>> $partsRows */
    $partsRows = [];
    /** @var array<int,array<string,mixed>> $consRows */
    $consRows = [];

    if ($quotationParts !== []) {
        [$partsRows, $consRows] = sv_jc_build_parts_and_cons_from_quotation_parts($quotationParts, $uid);
    }

    $postedPartNames = isset($xd['parts_item_name']) && is_array($xd['parts_item_name']) ? $xd['parts_item_name'] : [];
    $postedPartQty = isset($xd['parts_qty2']) && is_array($xd['parts_qty2']) ? $xd['parts_qty2'] : [];
    $postedPartCost = isset($xd['parts_unit_cost']) && is_array($xd['parts_unit_cost']) ? $xd['parts_unit_cost'] : [];
    $postedPartTotal = isset($xd['parts_total2']) && is_array($xd['parts_total2']) ? $xd['parts_total2'] : [];
    $postedPartMax = max(count($postedPartNames), count($postedPartQty), count($postedPartCost), count($postedPartTotal));
    $pOrder = count($partsRows);
    if ($partsRows === []) {
    for ($i = 0; $i < $postedPartMax; $i++) {
        $name = trim((string)($postedPartNames[$i] ?? ''));
        $qty = (float)($postedPartQty[$i] ?? 0);
        $unitCost = (float)($postedPartCost[$i] ?? 0);
        $total = (float)($postedPartTotal[$i] ?? 0);
        if ($name === '' && $qty <= 0 && $unitCost <= 0 && $total <= 0) {
            continue;
        }
        if ($total <= 0 && ($qty > 0 || $unitCost > 0)) {
            $total = round($qty * $unitCost, 2);
        }
        $partsRows[] = [
            'id' => $uid('pr'),
            'category' => 'parts_supply',
            'item_name' => $name,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total' => $total,
            'include_in_print' => true,
            'sort_order' => $pOrder++,
        ];
    }
    }
    if ($partsRows === []) {
    $left = isset($xd['parts_left']) && is_array($xd['parts_left']) ? $xd['parts_left'] : [];
    $right = isset($xd['parts_right']) && is_array($xd['parts_right']) ? $xd['parts_right'] : [];
    $pMax = max(count($left), count($right));
    for ($i = 0; $i < $pMax; $i++) {
        $ln = trim((string) ($left[$i] ?? ''));
        $rn = trim((string) ($right[$i] ?? ''));
        if ($ln === '' && $rn === '') {
            continue;
        }
        $name = $ln . ($rn !== '' ? (' — ' . $rn) : '');
        $partsRows[] = [
            'id' => $uid('pr'),
            'category' => 'parts_supply',
            'item_name' => $name,
            'qty' => 0,
            'unit_cost' => 0,
            'total' => 0,
            'include_in_print' => true,
            'sort_order' => $pOrder++,
        ];
    }
    }
    if ($partsRows === [] && $consRows === [] && $quotationParts === []) {
        $blankPartRows = min(2, $intConfig('blank_parts_lines', 2, 1, 40));
        for ($i = 0; $i < $blankPartRows; $i++) {
            $partsRows[] = [
                'id' => $uid('pr'),
                'category' => 'parts_supply',
                'item_name' => '',
                'qty' => 0,
                'unit_cost' => 0,
                'total' => 0,
                'include_in_print' => true,
                'sort_order' => $i,
            ];
        }
    }

    $postedConsNames = isset($xd['cons_item_name']) && is_array($xd['cons_item_name']) ? $xd['cons_item_name'] : [];
    $postedConsQty = isset($xd['cons_qty']) && is_array($xd['cons_qty']) ? $xd['cons_qty'] : [];
    $postedConsCost = isset($xd['cons_unit_cost']) && is_array($xd['cons_unit_cost']) ? $xd['cons_unit_cost'] : [];
    $postedConsTotal = isset($xd['cons_total']) && is_array($xd['cons_total']) ? $xd['cons_total'] : [];
    $postedConsMax = max(count($postedConsNames), count($postedConsQty), count($postedConsCost), count($postedConsTotal));
    if ($consRows === []) {
    for ($i = 0; $i < $postedConsMax; $i++) {
        $name = trim((string)($postedConsNames[$i] ?? ''));
        $qty = (float)($postedConsQty[$i] ?? 0);
        $unitCost = (float)($postedConsCost[$i] ?? 0);
        $total = (float)($postedConsTotal[$i] ?? 0);
        if ($name === '' && $qty <= 0 && $unitCost <= 0 && $total <= 0) {
            continue;
        }
        if ($total <= 0 && ($qty > 0 || $unitCost > 0)) {
            $total = round($qty * $unitCost, 2);
        }
        $consRows[] = [
            'id' => $uid('pr'),
            'category' => 'consumables',
            'item_name' => $name,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'total' => $total,
            'include_in_print' => true,
            'sort_order' => $i,
        ];
    }
    }
    if ($consRows === [] && $quotationParts === []) {
        for ($i = 0; $i < $intConfig('blank_cons_lines', 0, 0, 40); $i++) {
            $consRows[] = [
                'id' => $uid('cr'),
                'category' => 'consumables',
                'item_name' => '',
                'callout_type' => '',
                'qty' => 0,
                'unit_cost' => 0,
                'total' => 0,
                'include_in_print' => true,
                'sort_order' => $i,
            ];
        }
    }

    $addrParts = array_filter([
        trim((string) ($xd['to_line1'] ?? '')),
        trim((string) ($xd['to_line2'] ?? '')),
        trim((string) ($xd['to_line3'] ?? '')),
    ], static fn ($s) => $s !== '');
    if ($addrParts === []) {
        $addrParts = array_filter([
            trim((string) ($jc['client_address'] ?? '')),
            trim((string) ($xd['contact_email'] ?? $jc['client_email'] ?? '')),
        ], static fn ($s) => $s !== '');
    }
    $customerAddress = implode("\n", $addrParts);

    $jobDate = '';
    if (!empty($xd['job_date'])) {
        $jobDate = substr((string) $xd['job_date'], 0, 10);
    }
    if ($jobDate === '' && !empty($jc['created_at'])) {
        $jobDate = substr((string) $jc['created_at'], 0, 10);
    }
    if ($jobDate === '') {
        $jobDate = date('Y-m-d');
    }

    $purchaseOrder = trim((string) ($xd['purchase_order_no'] ?? $xd['purchase_order'] ?? ''));
    $headerLabels = [];
    if (!empty($xd['general_header_labels']) && is_array($xd['general_header_labels'])) {
        foreach ($xd['general_header_labels'] as $ln) {
            $ln = trim((string) $ln);
            if ($ln !== '') {
                $headerLabels[] = $ln;
            }
        }
    }
    if ($headerLabels === []) {
        $primary = trim((string) ($xd['attend_to_service_label'] ?? ''));
        $secondary = trim((string) ($xd['diagnostic_label'] ?? ''));
        if ($primary !== '') {
            $headerLabels[] = $primary;
        }
        if ($secondary !== '') {
            $headerLabels[] = $secondary;
        }
    }

    $form = [
        'quote_number' => $quoteNum,
        'customer_name' => trim((string) ($jc['client_name'] ?? '')),
        'customer_address' => $customerAddress,
        'customer_email' => trim((string) ($xd['contact_email'] ?? $jc['client_email'] ?? '')),
        'customer_phone' => trim((string) ($xd['contact_no'] ?? $jc['client_phone'] ?? '')),
        'contact_person' => trim((string) ($xd['contact_person'] ?? '')),
        'date' => $jobDate,
        'vehicle_reg_no' => trim((string) ($jc['reg_no'] ?? '')),
        'vin_no' => trim((string) ($jc['vin_no'] ?? $xd['vin_no'] ?? '')),
        'fleet_no' => trim((string) ($xd['fleet_no'] ?? '')),
        'model' => trim((string) ($jc['model'] ?? $xd['model_reg'] ?? '')),
        'kilometers' => trim((string) ($xd['kilometre'] ?? '')),
        'job_no' => trim((string) ($jc['card_number'] ?? '')),
        'purchase_order' => $purchaseOrder,
        'normal_time_rate' => $rateNormal,
        'overtime_rate' => $rateOvertime,
        'public_holiday_rate' => $rateHoliday,
        'general_header_labels' => $headerLabels,
        'attend_to_service_label' => $headerLabels[0] ?? trim((string) ($xd['attend_to_service_label'] ?? '')),
        'diagnostic_label' => $headerLabels[1] ?? trim((string) ($xd['diagnostic_label'] ?? '')),
        'consumables_label' => trim((string)($xd['consumables_label'] ?? 'Call-out')) ?: 'Call-out',
        'show_normal_time' => $quotationSections !== []
            ? $sectionFlags['show_normal']
            : $boolConfig('show_normal_time', true),
        'show_overtime' => $quotationSections !== []
            ? $sectionFlags['show_overtime']
            : $boolConfig('show_overtime', true),
        'show_public_holiday' => $quotationSections !== []
            ? $sectionFlags['show_holiday']
            : $boolConfig('show_public_holiday', true),
        'approved_at' => '',
        'approved_by' => '',
        'rejected_at' => '',
        'rejected_by' => '',
        'rejection_reason' => '',
        'sent_back_at' => '',
        'sent_back_by' => '',
        'sent_back_reason' => '',
        'vat_rate' => $vatRateConfig,
        'status' => 'pending_manager',
        'blank_normal_lines' => $quotationSections !== []
            ? $sectionFlags['blank_normal']
            : $intConfig('blank_normal_lines', 5, 2, 40),
        'blank_overtime_lines' => $quotationSections !== []
            ? $sectionFlags['blank_overtime']
            : $intConfig('blank_overtime_lines', 5, 2, 40),
        'blank_holiday_lines' => $quotationSections !== []
            ? $sectionFlags['blank_holiday']
            : $intConfig('blank_holiday_lines', 5, 2, 40),
        'blank_parts_lines' => count($partsRows) > 0 ? count($partsRows) : ($consRows !== [] ? 0 : max(1, $intConfig('blank_parts_lines', 2, 1, 40))),
        'blank_cons_lines' => count($consRows),
        'print_blank_show_labour' => $boolConfig('print_blank_show_labour', true),
        'print_blank_show_parts' => $boolConfig('print_blank_show_parts', true),
        'blank_col_lab_hours' => $boolConfig('blank_col_lab_hours', true),
        'blank_col_lab_rate' => $boolConfig('blank_col_lab_rate', true),
        'blank_col_lab_total' => $boolConfig('blank_col_lab_total', true),
        'blank_col_part_qty' => $boolConfig('blank_col_part_qty', true),
        'blank_col_part_cost' => $boolConfig('blank_col_part_cost', true),
        'blank_col_part_total' => $boolConfig('blank_col_part_total', true),
    ];

    sv_jc_patch_labour_visibility_flags($form, $labourRows);

    $labSum = 0.0;
    foreach ($labourRows as $r) {
        if (!is_array($r) || empty($r['include_in_print'])) {
            continue;
        }
        $labSum += (float) ($r['total'] ?? 0);
    }
    $partSum = 0.0;
    foreach ($partsRows as $r) {
        if (!is_array($r) || empty($r['include_in_print'])) {
            continue;
        }
        $partSum += (float) ($r['total'] ?? 0);
    }
    foreach ($consRows as $r) {
        if (!is_array($r) || empty($r['include_in_print'])) {
            continue;
        }
        $partSum += (float) ($r['total'] ?? 0);
    }

    $vatRate = (float) ($form['vat_rate'] ?? 15);
    $subtotal = $labSum + $partSum;
    $vatAmount = round($subtotal * ($vatRate / 100), 2);
    $grandTotal = round($subtotal + $vatAmount, 2);

    $persist = [
        'v' => 1,
        'form' => $form,
        'labour_rows' => $labourRows,
        'parts_rows' => $partsRows,
        'cons_rows' => $consRows,
        'totals' => [
            'labour_total' => $labSum,
            'parts_total' => $partSum,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'grand_total' => $grandTotal,
        ],
    ];

    $details = $qJsonMarker . json_encode($persist, JSON_UNESCAPED_UNICODE)
        . "\n\n— Line summary —\nQuot. " . $quoteNum . "\nTotal " . ($sym . number_format($grandTotal, 2));

    $clientId = isset($jc['client_id']) && $jc['client_id'] ? (int) $jc['client_id'] : null;
    $submittedAt = date('Y-m-d H:i:s');

    try {
        $pdo->prepare(
            'INSERT INTO quotations (job_card_id, client_id, amount, details, status, submitted_at, created_by)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $jobCardId,
            $clientId,
            $grandTotal,
            $details,
            'pending_manager',
            $submittedAt,
            $_SESSION['user_id'] ?? null,
        ]);
    } catch (PDOException $e) {
        $isUnknownColumn = ((string) $e->getCode() === '42S22')
            || (strpos((string) $e->getMessage(), '1054') !== false)
            || (stripos((string) $e->getMessage(), 'Unknown column') !== false);
        if (!$isUnknownColumn) {
            throw $e;
        }
        $pdo->prepare(
            'INSERT INTO quotations (job_card_id, client_id, amount, details, status, submitted_at)
             VALUES (?,?,?,?,?,?)'
        )->execute([
            $jobCardId,
            $clientId,
            $grandTotal,
            $details,
            'pending_manager',
            $submittedAt,
        ]);
    }

    return (int) $pdo->lastInsertId();
}

/**
 * Get next quotation number using max numeric suffix found in existing quote numbers.
 */
function sv_auto_next_quote_number(PDO $pdo): string
{
    $maxNum = 0;
    $stmt = $pdo->query('SELECT details FROM quotations WHERE deleted_at IS NULL');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $details = (string)($row['details'] ?? '');
        if ($details === '') {
            continue;
        }
        $quoteNumber = '';
        $marker = "QUOTATION_JSON_V1\n";
        if (strpos($details, $marker) === 0) {
            $json = substr($details, strlen($marker));
            $summaryPos = strpos($json, "\n\n— Line summary —");
            if ($summaryPos !== false) {
                $json = substr($json, 0, $summaryPos);
            }
            $payload = json_decode($json, true);
            if (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) {
                $quoteNumber = trim((string)($payload['form']['quote_number'] ?? ''));
            }
        }
        if ($quoteNumber === '' && preg_match('/Quot\.\s*([^\r\n]+)/i', $details, $m)) {
            $quoteNumber = trim((string)$m[1]);
        }
        if ($quoteNumber !== '' && preg_match('/(\d+)(?!.*\d)/', $quoteNumber, $m)) {
            $n = (int)$m[1];
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }
    }
    return 'QT-' . str_pad((string)($maxNum + 1), 5, '0', STR_PAD_LEFT);
}

/**
 * Refresh an existing quotation from latest job card values while preserving the same quotation id.
 *
 * This keeps links stable (e.g. add_quotation.php?id=26) by rebuilding quotation data and
 * copying it back to the existing row.
 */
function sv_auto_update_quotation_from_job_card(PDO $pdo, int $quoteId, int $jobCardId): int
{
    $q = $pdo->prepare('SELECT id, status, client_status FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $q->execute([$quoteId]);
    $existing = $q->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        throw new RuntimeException('Quotation not found.');
    }

    $previousStatus = (string)($existing['status'] ?? 'pending');
    $previousClientStatus = $existing['client_status'] ?? null;

    $pdo->beginTransaction();
    try {
        // Temporarily soft-delete current row so duplicate job_card guard can rebuild safely.
        $pdo->prepare('UPDATE quotations SET deleted_at = NOW() WHERE id = ?')->execute([$quoteId]);

        $newId = sv_auto_create_quotation_from_job_card($pdo, $jobCardId);

        $freshStmt = $pdo->prepare(
            'SELECT job_card_id, client_id, amount, details, submitted_at
             FROM quotations
             WHERE id = ? AND deleted_at IS NULL
             LIMIT 1'
        );
        $freshStmt->execute([$newId]);
        $fresh = $freshStmt->fetch(PDO::FETCH_ASSOC);
        if (!$fresh) {
            throw new RuntimeException('Failed to build refreshed quotation.');
        }

        // Restore original id row with refreshed content.
        $pdo->prepare(
            'UPDATE quotations
             SET deleted_at = NULL,
                 job_card_id = ?,
                 client_id = ?,
                 amount = ?,
                 details = ?,
                 status = ?,
                 client_status = ?,
                 submitted_at = ?
             WHERE id = ?'
        )->execute([
            (int)($fresh['job_card_id'] ?? $jobCardId),
            !empty($fresh['client_id']) ? (int)$fresh['client_id'] : null,
            (float)($fresh['amount'] ?? 0),
            (string)($fresh['details'] ?? ''),
            in_array($previousStatus, ['approved', 'rejected', 'sent_back_admin', 'sent_to_client'], true)
                ? $previousStatus
                : 'pending_manager',
            $previousClientStatus,
            date('Y-m-d H:i:s'),
            $quoteId,
        ]);

        // Hide temporary regenerated row.
        $pdo->prepare('UPDATE quotations SET deleted_at = NOW() WHERE id = ?')->execute([$newId]);

        $pdo->commit();
        return $quoteId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
