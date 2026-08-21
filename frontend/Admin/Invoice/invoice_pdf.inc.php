<?php
declare(strict_types=1);

/**
 * Build full HTML document for invoice PDF (Manager preview / download).
 */
function inv_build_invoice_pdf_html(PDO $pdo, int $invoiceId): string
{
    require_once __DIR__ . '/../../../backend/config/functions.php';
    require_once __DIR__ . '/../Quotation/quotation_paper_signoff.inc.php';
    require_once __DIR__ . '/../includes/trade_document_helpers.inc.php';

    $invoice = get_invoice($invoiceId);
    if (!$invoice) {
        throw new RuntimeException('Invoice not found');
    }

    $currency = getCurrency();
    $sym = htmlspecialchars((string) ($currency['symbol'] ?? 'N$'), ENT_QUOTES, 'UTF-8');
    $docAssets = trade_doc_assets(__DIR__ . '/..');
    $header_base64 = $docAssets['header'];
    $footer_base64 = $docAssets['footer'];

    $quoteData = [];
    $quotationId = (int) ($invoice['quotation_id'] ?? 0);
    if ($quotationId > 0) {
        $qStmt = $pdo->prepare('SELECT details FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $qStmt->execute([$quotationId]);
        $qDetails = $qStmt->fetchColumn();
        if ($qDetails !== false && $qDetails !== null && $qDetails !== '') {
            $parsed = qt_parse_quotation_details((string) $qDetails);
            if (is_array($parsed)) {
                $quoteData = $parsed;
            }
        }
    }
    if ($quoteData === [] && !empty($invoice['quote_details'])) {
        $parsed = qt_parse_quotation_details((string) $invoice['quote_details']);
        if (is_array($parsed)) {
            $quoteData = $parsed;
        }
    }

    $formData = isset($quoteData['form']) && is_array($quoteData['form']) ? $quoteData['form'] : [];
    $jobExtra = inv_doc_load_job_card_extra($pdo, $quotationId);
    $contactResolved = inv_doc_resolve_customer_contact($invoice, $formData, $jobExtra);
    $customerName = $invoice['client_name'] ?? ($formData['customer_name'] ?? '');
    $customerAddress = $invoice['client_address'] ?? ($formData['customer_address'] ?? '');
    $customerPhone = $contactResolved['phone'];
    $customerEmail = $contactResolved['email'];
    $contactPerson = $contactResolved['person'];
    $vehicleRegNo = $formData['vehicle_reg_no'] ?? $invoice['vehicle_reg_no'] ?? '';
    $vehicleModel = $formData['model'] ?? $invoice['vehicle_model'] ?? '';
    $vehicleVinNo = $formData['vin_no'] ?? $invoice['vehicle_vin_no'] ?? '';
    $kilometers = $formData['kilometers'] ?? '';
    $fleetNo = $formData['fleet_no'] ?? '';
    $jobNo = $formData['job_no'] ?? $invoice['job_card_number'] ?? '';
    $purchaseOrder = $formData['purchase_order'] ?? '';
    $quotationNumber = $formData['quote_number'] ?? '';
    $invoiceDate = $formData['date'] ?? $invoice['issued_date'] ?? '';

    $labourRows = $quoteData['labour_rows'] ?? [];
    $partsRows = array_merge($quoteData['parts_rows'] ?? [], $quoteData['cons_rows'] ?? []);
    $totals = $quoteData['totals'] ?? [];
    $subtotal_amount = (float) ($totals['subtotal'] ?? 0);
    $vat_amount = (float) ($totals['vat_amount'] ?? ($invoice['vat_amount'] ?? 0));
    $total_amount = (float) ($totals['grand_total'] ?? ($invoice['amount'] ?? 0));
    if ($subtotal_amount <= 0 && $total_amount > 0) {
        $subtotal_amount = $total_amount - $vat_amount;
    }

    $doc_blank_only = false;
    $doc_title = 'Tax Invoice';
    $doc_wrapper_id = 'aq-print-inner';
    $doc_wrapper_class = 'aq-print-inner';
    $show_invoice_number = true;
    $doc_invoice_number = $invoice['invoice_number'] ?? '';
    $paid_at = $invoice['paid_at'] ?? '';
    $invStatus = strtolower(trim((string) ($invoice['status_paid'] ?? 'unpaid')));
    $invoice_payment_note_kind = in_array($invStatus, ['paid', 'partial'], true) ? $invStatus : '';
    $show_invoice_payment_note = $invoice_payment_note_kind !== '';
    $show_paid_banner = ($invStatus === 'paid');

    ob_start();
    include __DIR__ . '/../includes/trade_document_body.inc.php';
    $body = (string) ob_get_clean();

    $layoutCss = inv_doc_print_layout_styles();

    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; margin: 12mm; color: #111; }
        table { border-collapse: collapse; }
        ' . $layoutCss . '
    </style></head><body>' . $body . '</body></html>';
}
