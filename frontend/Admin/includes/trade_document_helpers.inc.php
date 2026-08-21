<?php
declare(strict_types=1);

if (!function_exists('formatInvoiceDate')) {
    function formatInvoiceDate($dateStr): string
    {
        if (!$dateStr || trim((string) $dateStr) === '') {
            return '';
        }
        try {
            $d = new DateTime((string) $dateStr);
            return $d->format('d-m-Y');
        } catch (Exception $e) {
            return (string) $dateStr;
        }
    }
}

if (!function_exists('inv_doc_h')) {
    function inv_doc_h($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('inv_doc_num')) {
    function inv_doc_num($value): string
    {
        $n = (float) ($value ?? 0);
        return $n > 0 ? number_format($n, 2) : '';
    }
}

if (!function_exists('inv_doc_hours')) {
    function inv_doc_hours($value): string
    {
        $n = (float) ($value ?? 0);
        if ($n <= 0) {
            return '';
        }
        return abs($n - round($n)) < 0.001 ? (string) (int) round($n) : rtrim(rtrim(number_format($n, 1), '0'), '.');
    }
}

if (!function_exists('inv_doc_qty')) {
    function inv_doc_qty($value): string
    {
        $n = (float) ($value ?? 0);
        if ($n <= 0) {
            return '';
        }
        return abs($n - round($n)) < 0.001 ? (string) (int) round($n) : rtrim(rtrim(number_format($n, 2), '0'), '.');
    }
}

if (!function_exists('inv_doc_bool')) {
    function inv_doc_bool($value, $default = true): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $value === true || $value === 1 || $value === '1';
    }
}

/** @return array{header:string,footer:string,header_url:string,footer_url:string} */
function trade_doc_public_urls(?string $adminDir = null): array
{
    $adminDir = $adminDir ?: dirname(__DIR__);
    $headerUrl = '';
    $footerUrl = '';
    $pathParts = array_values(array_filter(explode('/', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')))));
    $adminIdx = array_search('Admin', $pathParts, true);
    $publicWebPath = '/assets/';
    if ($adminIdx !== false) {
        $publicWebPath = '/' . implode('/', array_slice($pathParts, 0, $adminIdx)) . '/assets/';
    }
    $origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    foreach ([$adminDir . '/../assets/images/header.png', $adminDir . '/assets/images/header.png'] as $path) {
        if (is_file($path)) {
            $headerUrl = $origin . $publicWebPath . 'images/header.png';
            break;
        }
    }
    foreach ([$adminDir . '/../assets/images/footer.png', $adminDir . '/assets/images/footer.png'] as $path) {
        if (is_file($path)) {
            $footerUrl = $origin . $publicWebPath . 'images/footer.png';
            break;
        }
    }
    return ['header_url' => $headerUrl, 'footer_url' => $footerUrl];
}

/** @return array{header:string,footer:string} */
function trade_doc_assets(?string $adminDir = null): array
{
    $adminDir = $adminDir ?: dirname(__DIR__);
    $header = '';
    $footer = '';
    foreach ([$adminDir . '/../assets/images/header.png', $adminDir . '/assets/images/header.png'] as $path) {
        if (is_file($path)) {
            $header = 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
            break;
        }
    }
    foreach ([$adminDir . '/../assets/images/footer.png', $adminDir . '/assets/images/footer.png'] as $path) {
        if (is_file($path)) {
            $footer = 'data:image/png;base64,' . base64_encode((string) file_get_contents($path));
            break;
        }
    }
    return ['header' => $header, 'footer' => $footer];
}

/** @return array<string,mixed> */
function sv_doc_legacy_quote_payload(array $quote, array $notes, array $labourItems, array $partsItems, float $consumables): array
{
    $typeMap = [
        'normal_hours' => 'normal_time',
        'after_hours' => 'overtime',
        'holiday' => 'public_holiday',
    ];
    $labourRows = [];
    foreach ($labourItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $desc = trim((string) ($item['desc'] ?? ''));
        $hours = (float) ($item['hours'] ?? 0);
        $rate = (float) ($item['rate'] ?? 0);
        if ($desc === '' || $hours <= 0 || $rate <= 0) {
            continue;
        }
        $cat = $typeMap[$item['type'] ?? 'normal_hours'] ?? 'normal_time';
        $labourRows[] = [
            'category' => $cat,
            'description' => $desc,
            'hours' => $hours,
            'rate' => $rate,
            'total' => (float) ($item['total'] ?? ($hours * $rate)),
            'include_in_print' => true,
        ];
    }

    $partsRows = [];
    foreach ($partsItems as $part) {
        if (!is_array($part)) {
            continue;
        }
        $name = trim((string) ($part['name'] ?? $part['item_name'] ?? ''));
        $qty = (float) ($part['qty'] ?? 0);
        $unit = (float) ($part['unit_cost'] ?? 0);
        $total = (float) ($part['total'] ?? 0);
        if ($name === '' && $total <= 0) {
            continue;
        }
        $partsRows[] = [
            'item_name' => $name,
            'qty' => $qty,
            'unit_cost' => $unit,
            'total' => $total > 0 ? $total : ($qty * $unit),
            'include_in_print' => true,
        ];
    }

    $consRows = [];
    if ($consumables > 0) {
        $consRows[] = [
            'item_name' => '',
            'qty' => 1,
            'unit_cost' => $consumables,
            'total' => $consumables,
            'include_in_print' => true,
        ];
    }

    $subtotal = (float) ($quote['subtotal'] ?? 0);
    $vat = (float) ($quote['vat_amount'] ?? 0);
    $grand = (float) ($quote['total_amount'] ?? $quote['amount'] ?? 0);
    if ($subtotal <= 0 && $grand > 0) {
        $subtotal = $grand - $vat;
    }

    $quoteNumber = 'QTN-' . str_pad((string) ($quote['id'] ?? '0'), 6, '0', STR_PAD_LEFT);
    $submitted = (string) ($quote['submitted_at'] ?? date('Y-m-d'));

    return [
        'form' => [
            'quote_number' => $quoteNumber,
            'customer_name' => $quote['client_name'] ?? '',
            'customer_address' => $quote['client_address'] ?? '',
            'customer_phone' => $quote['client_phone'] ?? '',
            'customer_email' => $quote['client_email'] ?? '',
            'date' => substr($submitted, 0, 10),
            'vehicle_reg_no' => $quote['reg_no'] ?? '',
            'model' => $quote['model'] ?? '',
            'vin_no' => $notes['vin'] ?? $quote['vin_no'] ?? '',
            'kilometers' => $notes['km'] ?? '',
            'fleet_no' => $notes['fleet'] ?? '',
            'job_no' => $quote['card_number'] ?? '',
            'purchase_order' => $notes['po'] ?? '',
            'show_normal_time' => !empty($labourRows),
            'show_overtime' => false,
            'show_public_holiday' => false,
            'blank_normal_lines' => max(2, count($labourRows)),
            'blank_overtime_lines' => 0,
            'blank_holiday_lines' => 0,
            'blank_parts_top_lines' => 2,
            'blank_parts_lines' => max(1, count($partsRows)),
            'blank_cons_lines' => max(1, count($consRows)),
        ],
        'labour_rows' => $labourRows,
        'parts_rows' => $partsRows,
        'cons_rows' => $consRows,
        'totals' => [
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'grand_total' => $grand,
        ],
    ];
}

/** @return list<array{0:string,1:string}> */
function inv_doc_filter_info_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row) || count($row) < 2) {
            continue;
        }
        if (trim((string) $row[1]) === '') {
            continue;
        }
        $out[] = [(string) $row[0], (string) $row[1]];
    }
    return $out;
}

/**
 * Description column header labels from quotation/job-card form data (supports multiple rows).
 *
 * @param array<string, mixed> $formData
 * @return array<int, string>
 */
function inv_doc_labour_header_labels(array $formData): array
{
    if (!function_exists('jc_general_header_labels_from_extra')) {
        require_once __DIR__ . '/../JobCard/jc_form_helpers.inc.php';
    }
    $labels = jc_general_header_labels_from_extra($formData);
    if ($labels !== []) {
        return $labels;
    }

    return [''];
}

/** Company document table colours (labour + parts + totals). */
function inv_doc_table_hdr_bg(): string
{
    return '#a6a6a6';
}

/** Blank-template sub-header row (e.g. Diagnostic) under main labour header. */
function inv_doc_table_subhdr_bg(): string
{
    return '#d9d9d9';
}

function inv_doc_table_border(): string
{
    return '#000000';
}

function inv_doc_total_strip_spacer_style(): string
{
    return 'background:' . inv_doc_table_body_bg() . ';color:#000;border:1px solid ' . inv_doc_table_border() . ';border-top:none;padding:2px 4px;vertical-align:middle;';
}

function inv_doc_total_strip_hdr_style(): string
{
    return 'background:' . inv_doc_table_hdr_bg() . ';color:#000;border:1px solid ' . inv_doc_table_border() . ';font-weight:bold;font-size:7pt;padding:3px 3px;line-height:1.25;height:14px;vertical-align:middle;';
}

/** Width of Qty + Rate + Total columns (aligns footer sum box to table metrics). */
function inv_doc_sum_table_width_pct(): string
{
    return '42%';
}

/** Shared 5-column widths for labour and parts tables (must sum to 100%). */
function inv_doc_table_col_widths(): array
{
    return ['side' => '12%', 'name' => '56%', 'qty' => '10%', 'rate' => '11%', 'total' => '11%'];
}

/** Gap between data table and aligned total strip (keep minimal). */
function inv_doc_total_strip_margin_top(): string
{
    return '0';
}

function inv_doc_table_body_bg(): string
{
    return '#ffffff';
}

/**
 * Shared print/preview CSS for labour + parts data tables (one palette).
 */
function inv_doc_data_table_print_css(string $root = '#aq-print-inner'): string
{
    $hdr = inv_doc_table_hdr_bg();
    $bd = inv_doc_table_border();
    $body = inv_doc_table_body_bg();

    return $root . ' .aq-doc-data-table{width:100%;max-width:100%;table-layout:fixed;border-collapse:collapse;border-spacing:0;box-sizing:border-box;margin-bottom:0;font-size:7.5pt;border:1px solid ' . $bd . '}'
        . $root . ' .aq-doc-data-table.aq-labour-table,' . $root . ' .aq-doc-data-table.aq-doc-parts-section{margin-bottom:2mm;margin-top:0}'
        . $root . ' .aq-doc-data-table th,' . $root . ' .aq-doc-data-table td{box-sizing:border-box;border:1px solid ' . $bd . ';padding:3px 5px;vertical-align:middle;background:' . $body . '}'
        . $root . ' .aq-doc-data-table thead th{background:' . $hdr . '!important;color:#000;font-weight:bold;text-align:center;border:1px solid ' . $bd . '}'
        . $root . ' .aq-doc-data-table.aq-labour-table,' . $root . ' .aq-doc-data-table.aq-doc-parts-section{border-collapse:collapse;border-spacing:0}'
        . $root . ' .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-sub{background:' . inv_doc_table_subhdr_bg() . '!important;color:#000;font-weight:bold;text-align:center;border:1px solid ' . $bd . '}'
        . $root . ' .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-side,' . $root . ' .aq-doc-data-table.aq-doc-parts-section thead th{vertical-align:middle;text-align:center}'
        . $root . ' .aq-doc-data-table.aq-labour-table thead tr.aq-labour-hdr-label th{border-top:1px solid ' . $bd . '}'
        . $root . ' .aq-labour-title-cell,' . $root . ' .aq-part-type-cell{background:' . $body . ';border:1px solid ' . $bd . ';vertical-align:middle;text-align:center;font-size:7.5pt;padding:4px 6px}'
        . $root . ' .aq-labour-desc-cell,' . $root . ' .aq-parts-name-cell{background:' . $body . ';vertical-align:top;border:1px solid ' . $bd . ';font-size:7.5pt;line-height:1.45}'
        . $root . ' .aq-labour-metric-cell{background:' . $body . ';text-align:center;vertical-align:middle;border:1px solid ' . $bd . ';font-size:7.5pt}'
        . $root . ' .aq-doc-data-table tr.aq-doc-last-body-row td{border-bottom:none!important}'
        . $root . ' .aq-doc-data-table tr.aq-doc-total-row td{padding:1px 3px!important;line-height:1.1!important;font-size:7pt!important;vertical-align:middle;height:auto}'
        . $root . ' .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-spacer{background:#fff!important;color:#000;border:1px solid ' . $bd . '!important;border-top:1px solid ' . $bd . '!important;font-weight:normal;padding:0 3px!important;height:auto;min-height:0;font-size:0;line-height:0}'
        . $root . ' .aq-doc-data-table tr.aq-doc-total-gap td{height:4px;padding:0!important;border:none!important;background:#fff!important;font-size:0;line-height:0}'
        . $root . ' .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-hdr{background:' . $hdr . ';color:#000;border:1px solid ' . $bd . ';border-top:1px solid ' . $bd . ';font-weight:bold;text-align:center}'
        . $root . ' .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-metric{background:' . $body . ';color:#000;border:1px solid ' . $bd . ';border-top:1px solid ' . $bd . ';font-weight:normal;text-align:center}'
        . $root . ' .aq-doc-sum-table{width:' . inv_doc_sum_table_width_pct() . ';margin-left:auto;margin-top:0;border-collapse:collapse;font-size:7pt;border:1px solid ' . $bd . '}'
        . $root . ' .aq-doc-sum-table td{padding:1px 4px;line-height:1.1;vertical-align:middle;border:1px solid ' . $bd . '}'
        . $root . ' .aq-doc-data-table tr.aq-parts-thick-divider td{height:5px;padding:0!important;background:' . $bd . '!important;border:1px solid ' . $bd . '!important;border-top:1px solid ' . $bd . '!important;border-bottom:3px solid ' . $bd . '!important;font-size:0;line-height:0}';
}

/** @return array<string, mixed> */
function inv_doc_load_job_card_extra(PDO $pdo, int $quotationId): array
{
    if ($quotationId <= 0) {
        return [];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT jc.extra_data FROM quotations q
             INNER JOIN job_cards jc ON jc.id = q.job_card_id
             WHERE q.id = ? AND jc.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$quotationId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false || $raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @param array<string, mixed> $invoice
 * @param array<string, mixed> $formData
 * @param array<string, mixed> $jobExtra
 * @return array{phone:string,email:string,person:string}
 */
function inv_doc_resolve_customer_contact(array $invoice, array $formData, array $jobExtra = []): array
{
    $pick = static function (...$candidates): string {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    };

    return [
        'phone' => $pick(
            $invoice['client_phone'] ?? null,
            $formData['customer_phone'] ?? null,
            $formData['contact_no'] ?? null,
            $jobExtra['contact_no'] ?? null
        ),
        'email' => $pick(
            $invoice['client_email'] ?? null,
            $formData['customer_email'] ?? null,
            $formData['contact_email'] ?? null,
            $jobExtra['contact_email'] ?? null
        ),
        'person' => $pick(
            $invoice['contact_person_primary'] ?? null,
            $formData['contact_person'] ?? null,
            $jobExtra['contact_person'] ?? null,
            $invoice['contact_person_secondary'] ?? null
        ),
    ];
}

function inv_doc_info_box_table(string $title, array $rows, bool $vehicle = false, bool $withHeader = true, string $extraStyle = ''): string
{
    $hd = 'background:' . inv_doc_table_hdr_bg() . ';color:#000;border:1px solid ' . inv_doc_table_border() . ';font-weight:bold;text-align:center;padding:3px 5px;font-size:7.5pt';
    $lbl = 'font-weight:bold;color:#000;text-align:left;padding:2px 6px 2px 8px;vertical-align:top;font-size:7.5pt;line-height:1.2;white-space:nowrap';
    $val = 'color:#444;text-align:left;padding:2px 8px 2px 0;vertical-align:top;font-size:7.5pt;line-height:1.2;width:auto';
    $lblVeh = $lbl . ';padding:1px 6px 1px 8px;line-height:1.15;border-right:1px solid ' . inv_doc_table_border();
    $valVeh = 'color:#444;text-align:left;padding:1px 8px 1px 6px;vertical-align:top;font-size:7.5pt;line-height:1.25;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word';
    $layout = $vehicle ? 'fixed' : 'auto';
    $colgroup = $vehicle
        ? '<colgroup><col style="width:42%"/><col style="width:58%"/></colgroup>'
        : '<colgroup><col style="width:1%"/><col style="width:auto"/></colgroup>';
    $boxCls = $vehicle ? 'aq-doc-info-box aq-doc-info-box--vehicle' : 'aq-doc-info-box';

    $body = '';
    foreach ($rows as $row) {
        $ls = $vehicle ? $lblVeh : $lbl;
        $vs = $vehicle ? $valVeh : $val;
        $body .= '<tr><td class="aq-doc-info-lbl" style="' . $ls . '">' . inv_doc_h($row[0]) . '</td>'
            . '<td class="aq-doc-info-val" style="' . $vs . '">' . inv_doc_h($row[1]) . '</td></tr>';
    }

    $head = $withHeader
        ? '<thead><tr><th colspan="2" class="aq-doc-info-hd" style="' . $hd . '">' . inv_doc_h($title) . '</th></tr></thead>'
        : '';

    return '<table class="' . $boxCls . '" style="width:100%;border-collapse:collapse;table-layout:' . $layout . ';border:1px solid ' . inv_doc_table_border() . ';background:#fff;' . $extraStyle . '">'
        . $colgroup . $head . '<tbody>' . $body . '</tbody></table>';
}

/**
 * Customer + contact (left) and vehicle (right) — matches quotation preview layout.
 *
 * @param list<array{0:string,1:string}> $customerRows
 * @param list<array{0:string,1:string}> $contactRows
 * @param list<array{0:string,1:string}> $vehicleRows
 */
function inv_doc_render_info_section(array $customerRows, array $contactRows, array $vehicleRows): string
{
    $customerBox = inv_doc_info_box_table('Customer Details', $customerRows, false, true);
    $contactBox = inv_doc_info_box_table('', $contactRows, false, false, 'flex:1;height:100%;');
    $vehicleBox = inv_doc_info_box_table('Vehicle Details', $vehicleRows, true, true, 'height:100%;');

    $contactMargin = $contactRows !== [] ? 'margin-top:2mm;flex:1;display:flex' : '';

    return '<table class="aq-doc-info-section" style="width:100%;border-collapse:collapse;table-layout:fixed;border:none;margin-bottom:2mm">'
        . '<tr>'
        . '<td class="aq-doc-info-slot" style="width:46%;vertical-align:top;padding:0;border:none;height:1px">'
        . '<div class="aq-doc-info-slot-fit" style="display:flex;width:100%;height:100%;vertical-align:top;flex-direction:column">'
        . $customerBox
        . ($contactRows !== [] ? '<div style="' . $contactMargin . '">' . $contactBox . '</div>' : '')
        . '</div></td>'
        . '<td class="aq-doc-info-gap" style="width:8%;padding:0;border:none;font-size:0;line-height:0">&nbsp;</td>'
        . '<td class="aq-doc-info-slot" style="width:46%;vertical-align:top;padding:0;border:none;height:1px">' . $vehicleBox . '</td>'
        . '</tr></table>';
}

function inv_doc_trade_info_styles(): string
{
    $roots = ['#invoice-print-inner', '#quotation-print-inner', '#aq-print-inner'];
    $css = '';
    foreach ($roots as $root) {
        $css .= $root . ' .aq-doc-info-section{width:100%;border-collapse:collapse;table-layout:fixed;border:none;margin-bottom:2mm}';
        $css .= $root . ' .aq-doc-info-slot{width:46%;vertical-align:top;padding:0;border:none;height:1px}';
        $css .= $root . ' .aq-doc-info-gap{width:8%;padding:0;border:none;font-size:0;line-height:0}';
        $css .= $root . ' .aq-doc-info-slot-fit{display:flex;width:100%;height:100%;vertical-align:top;flex-direction:column}';
        $css .= $root . ' .aq-doc-info-box{width:100%;border-collapse:collapse;table-layout:fixed;border:1px solid ' . inv_doc_table_border() . ';background:#fff}';
        $css .= $root . ' .aq-doc-info-box:not(.aq-doc-info-box--vehicle){table-layout:auto}';
        $css .= $root . ' .aq-doc-info-hd{background:' . inv_doc_table_hdr_bg() . ';color:#000;font-weight:bold;text-align:center;padding:3px 5px;border:1px solid ' . inv_doc_table_border() . ';font-size:7.5pt}';
        $css .= $root . ' .aq-doc-info-lbl{font-weight:bold;color:#000;white-space:nowrap;text-align:left;padding:2px 6px 2px 8px;vertical-align:top;font-size:7.5pt;line-height:1.2;width:1%}';
        $css .= $root . ' .aq-doc-info-val{color:#444;font-weight:normal;text-align:left;padding:2px 8px 2px 0;vertical-align:top;font-size:7.5pt;line-height:1.2;width:auto}';
        $css .= $root . ' .aq-doc-info-box--vehicle .aq-doc-info-lbl,' . $root . ' .aq-doc-info-box--vehicle .aq-doc-info-val{padding-top:1px;padding-bottom:1px;line-height:1.15}';
        $css .= $root . ' .aq-doc-info-box--vehicle .aq-doc-info-lbl{border-right:1px solid ' . inv_doc_table_border() . '}';
        $css .= $root . ' .aq-doc-info-box--vehicle .aq-doc-info-val{word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word}';
        $css .= $root . ' .aq-doc-header-wrap{position:relative;margin-bottom:3mm;line-height:normal;min-height:22mm}';
        $css .= $root . ' .aq-doc-header-wrap img{width:100%;display:block;height:auto}';
        $css .= $root . ' .aq-doc-title-badge{position:absolute;right:10px;bottom:7mm;top:auto;z-index:2;font-family:Arial Black,Arial,sans-serif;font-size:16pt;font-weight:900;letter-spacing:2px;color:#000;line-height:1.1;text-align:right;white-space:nowrap;padding:2px 4px;background:rgba(255,255,255,.92)}';
        $css .= inv_doc_data_table_print_css($root);
    }
    return $css;
}

/** Styles for invoice/quotation PDF HTML head (header title, labour row height, info boxes). */
function inv_doc_print_layout_styles(): string
{
    return inv_doc_trade_info_styles();
}

/** Full print/PDF stylesheet for #aq-print-inner (matches add_quotation.php live preview). */
function aq_print_document_styles(): string
{
    return '@page{size:A4;margin:8mm 10mm}'
        . 'html,body{margin:0;padding:0;background:#fff}'
        . 'body{font-family:DejaVu Sans,Arial,Helvetica,sans-serif;font-size:8pt;color:#000}'
        . 'table{border-collapse:collapse}'
        . inv_doc_trade_info_styles()
        . '#aq-print-inner{max-width:100%;background:#fff;box-sizing:border-box}'
        . '#aq-print-inner img{max-width:100%;height:auto;display:block}'
        . '#aq-print-inner .aq-doc-block-hd{background:' . inv_doc_table_hdr_bg() . ';color:#000;font-weight:bold;text-align:center;padding:3px 5px;border:1px solid ' . inv_doc_table_border() . ';font-size:8pt}'
        . inv_doc_data_table_print_css('#aq-print-inner')
        . '#aq-print-inner .aq-doc-data-table.aq-labour-table,#aq-print-inner .aq-doc-data-table.aq-doc-parts-section{margin-bottom:2mm;margin-top:0}'
        . '#aq-print-inner .aq-doc-footer-block{margin-top:3mm}'
        . '#aq-print-inner .aq-doc-muted{color:#000}';
}

/**
 * Title for a labour print block (job card section label or category name).
 *
 * @param array<string,mixed> $row
 */
function inv_doc_labour_block_title(array $row): string
{
    $label = trim((string) ($row['section_label'] ?? ''));
    if ($label !== '') {
        return $label;
    }
    $cat = (string) ($row['category'] ?? 'normal_time');
    $map = [
        'diagnostic' => 'Diagnostic',
        'normal_time' => 'Normal Time',
        'overtime' => 'Overtime',
        'public_holiday' => 'Public Holiday',
    ];

    return $map[$cat] ?? 'Labour';
}

/**
 * Group labour rows into print blocks (supports multiple sections of the same type).
 *
 * @param array<int,array<string,mixed>> $labourRows
 * @return array<int,array{title:string,rows:array<int,array<string,mixed>>}>
 */
function inv_doc_build_labour_print_blocks(array $labourRows): array
{
    $blocks = [];
    $sorted = array_values(array_filter($labourRows, static function ($r) {
        return is_array($r) && !empty($r['include_in_print']);
    }));
    usort($sorted, static function ($a, $b) {
        return ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
    });

    foreach ($sorted as $row) {
        $cat = (string) ($row['category'] ?? 'normal_time');
        $sectionKey = array_key_exists('section_key', $row) ? (string) $row['section_key'] : '';
        $blockKey = $cat . "\0" . $sectionKey;
        $last = $blocks !== [] ? $blocks[count($blocks) - 1] : null;
        if ($last === null || ($last['block_key'] ?? '') !== $blockKey) {
            $blocks[] = [
                'block_key' => $blockKey,
                'title' => inv_doc_labour_block_title($row),
                'rows' => [$row],
            ];
        } else {
            $blocks[count($blocks) - 1]['rows'][] = $row;
        }
    }

    return $blocks;
}

/**
 * Hours/rate/total shown once per labour block (first row that carries metrics).
 *
 * @param array<int,array<string,mixed>|null> $rows
 * @return array{hours:float,rate:float,total:float,hours_fmt:string,rate_fmt:string,total_fmt:string}
 */
function inv_doc_labour_block_metrics(array $rows, bool $blank_only = false): array
{
    $hours = 0.0;
    $rate = 0.0;
    $total = 0.0;
    if (!$blank_only) {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $h = (float) ($row['hours'] ?? 0);
            $r = (float) ($row['rate'] ?? 0);
            $t = (float) ($row['total'] ?? 0);
            if ($h > 0 || $r > 0 || $t > 0) {
                $hours = $h;
                $rate = $r;
                $total = $t;
                break;
            }
        }
    }

    return [
        'hours' => $hours,
        'rate' => $rate,
        'total' => $total,
        'hours_fmt' => inv_doc_hours($hours),
        'rate_fmt' => inv_doc_num($rate),
        'total_fmt' => inv_doc_num($total),
    ];
}
