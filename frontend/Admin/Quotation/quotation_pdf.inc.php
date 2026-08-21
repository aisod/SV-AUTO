<?php
declare(strict_types=1);

/**
 * Build full HTML document for quotation PDF (matches trade_document_body / live preview).
 */
function aq_build_quotation_pdf_html(PDO $pdo, int $quoteId): string
{
    require_once __DIR__ . '/quotation_paper_signoff.inc.php';
    require_once __DIR__ . '/../includes/trade_document_helpers.inc.php';

    $stmt = $pdo->prepare('
        SELECT q.*, jc.card_number, jc.description AS job_description, jc.extra_data,
            c.name AS client_name, c.phone AS client_phone,
            c.email AS client_email, c.address AS client_address,
            v.reg_no, v.model, v.vin_no
        FROM quotations q
        LEFT JOIN job_cards jc ON q.job_card_id = jc.id
        LEFT JOIN clients c ON q.client_id = c.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        WHERE q.id = ? AND q.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->execute([$quoteId]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        throw new RuntimeException('Quotation not found');
    }

    $notes = json_decode((string) ($quote['notes'] ?? '{}'), true) ?: [];
    $labour_items = json_decode((string) ($quote['labour_items'] ?? '[]'), true);
    $parts_items = json_decode((string) ($quote['parts_items'] ?? '[]'), true);
    if (!is_array($labour_items)) {
        $labour_items = [];
    }
    if (!is_array($parts_items)) {
        $parts_items = [];
    }

    $quoteData = qt_parse_quotation_details((string) ($quote['details'] ?? ''));
    if (!$quoteData) {
        $quoteData = sv_doc_legacy_quote_payload(
            $quote,
            $notes,
            $labour_items,
            $parts_items,
            (float) ($quote['consumables'] ?? 0)
        );
    }

    $formData = isset($quoteData['form']) && is_array($quoteData['form']) ? $quoteData['form'] : [];
    $totals = isset($quoteData['totals']) && is_array($quoteData['totals']) ? $quoteData['totals'] : [];

    $customerName = trim((string) ($formData['customer_name'] ?? $quote['client_name'] ?? ''));
    $customerAddress = trim((string) ($formData['customer_address'] ?? $quote['client_address'] ?? ''));
    $customerPhone = trim((string) ($formData['customer_phone'] ?? $quote['client_phone'] ?? ''));
    $customerEmail = trim((string) ($formData['customer_email'] ?? $quote['client_email'] ?? ''));
    $contactPerson = trim((string) ($formData['contact_person'] ?? ''));

    $vehicleRegNo = trim((string) ($formData['vehicle_reg_no'] ?? $quote['reg_no'] ?? ''));
    $vehicleModel = trim((string) ($formData['model'] ?? $quote['model'] ?? ''));
    $vehicleVinNo = trim((string) ($formData['vin_no'] ?? $notes['vin'] ?? $quote['vin_no'] ?? ''));
    $kilometers = trim((string) ($formData['kilometers'] ?? $notes['km'] ?? ''));
    $fleetNo = trim((string) ($formData['fleet_no'] ?? $notes['fleet'] ?? ''));
    $jobNo = trim((string) ($formData['job_no'] ?? $quote['card_number'] ?? ''));
    $purchaseOrder = trim((string) ($formData['purchase_order'] ?? $notes['po'] ?? ''));
    $quotationNumber = trim((string) ($formData['quote_number'] ?? ('QT-' . str_pad((string) $quote['id'], 5, '0', STR_PAD_LEFT))));
    $invoiceDate = trim((string) ($formData['date'] ?? substr((string) ($quote['submitted_at'] ?? ''), 0, 10)));

    $subtotal_amount = (float) ($totals['subtotal'] ?? $quote['subtotal'] ?? 0);
    $vat_amount = (float) ($totals['vat_amount'] ?? $quote['vat_amount'] ?? 0);
    $total_amount = (float) ($totals['grand_total'] ?? $quote['total_amount'] ?? $quote['amount'] ?? 0);
    if ($subtotal_amount <= 0 && $total_amount > 0) {
        $subtotal_amount = $total_amount - $vat_amount;
    }

    $docAssets = trade_doc_assets(__DIR__ . '/..');
    $header_base64 = $docAssets['header'];
    $footer_base64 = $docAssets['footer'];
    $header_img_url = '';
    $footer_img_url = '';

    $doc_title = 'QUOTATION';
    $show_invoice_number = false;
    $show_contact_box = false;
    $doc_quote_number = $quotationNumber;
    $show_quotation_ref_on_doc = false;
    $show_purchase_order_on_doc = false;
    $doc_wrapper_id = 'aq-print-inner';
    $doc_wrapper_class = 'invoice-wrapper';
    $doc_blank_only = false;
    $subTotal = $subtotal_amount;
    $vatTotal = $vat_amount;
    $grandTotal = $total_amount;

    ob_start();
    include __DIR__ . '/../includes/trade_document_body.inc.php';
    $body = ob_get_clean();

    $styles = aq_print_document_styles();

    $safeTitle = htmlspecialchars($quotationNumber !== '' ? $quotationNumber : 'Quotation', ENT_QUOTES, 'UTF-8');

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>'
        . $safeTitle
        . '</title><style>'
        . $styles
        . '</style></head><body>'
        . $body
        . '</body></html>';
}

function aq_stream_quotation_pdf(PDO $pdo, int $quoteId, ?string $filename = null): void
{
    $html = aq_build_quotation_pdf_html($pdo, $quoteId);

    if (!class_exists(\Dompdf\Dompdf::class)) {
        $autoloadCandidates = [
            __DIR__ . '/../../../vendor/autoload.php',
            __DIR__ . '/../../../vendor/autoload.php',
        ];
        $autoloadLoaded = false;
        foreach ($autoloadCandidates as $autoloadPath) {
            if (is_file($autoloadPath)) {
                require_once $autoloadPath;
                $autoloadLoaded = true;
                break;
            }
        }
        if (!$autoloadLoaded || !class_exists(\Dompdf\Dompdf::class)) {
            throw new RuntimeException(
                'PDF library is not installed. Run composer install in the project folder (SV Auto Truck Repair).'
            );
        }
    }

    $stmt = $pdo->prepare('SELECT details FROM quotations WHERE id = ? LIMIT 1');
    $stmt->execute([$quoteId]);
    $details = (string) ($stmt->fetchColumn() ?: '');
    $payload = qt_parse_quotation_details($details);
    $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
    $defaultName = trim((string) ($form['quote_number'] ?? ''));
    if ($defaultName === '') {
        $defaultName = 'QT-' . str_pad((string) $quoteId, 5, '0', STR_PAD_LEFT);
    }

    $filename = $filename !== null && $filename !== ''
        ? preg_replace('/[^\w\-]+/', '_', $filename)
        : preg_replace('/[^\w\-]+/', '_', $defaultName);
    if ($filename === '') {
        $filename = 'quotation';
    }

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('dpi', 120);
    $options->set('defaultMediaType', 'print');
    $chroot = realpath(__DIR__ . '/../..');
    if ($chroot !== false) {
        $options->setChroot($chroot);
    }

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $dompdf->output();
}
