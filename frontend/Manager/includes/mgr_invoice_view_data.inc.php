<?php
declare(strict_types=1);

require_once __DIR__ . '/mgr_review.inc.php';
require_once __DIR__ . '/mgr_quotation_view_data.inc.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_paper_signoff.inc.php';

function mgr_format_review_datetime(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    return $ts !== false ? date('d M Y, g:i A', $ts) : $raw;
}

/**
 * @return array<string, mixed>|null
 */
function mgr_load_invoice_view(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    mgr_ensure_invoice_review_columns($pdo);

    $stmt = $pdo->prepare('
        SELECT i.*, q.id AS quotation_id, q.details AS quote_details,
               jc.id AS job_card_id, jc.card_number,
               c.name AS client_name, c.phone AS client_phone,
               c.email AS client_email, c.address AS client_address,
               c.contact_person AS contact_person_primary,
               v.reg_no, v.model, v.vin_no
        FROM invoices i
        LEFT JOIN quotations q ON i.quotation_id = q.id
        LEFT JOIN job_cards jc ON q.job_card_id = jc.id
        LEFT JOIN clients c ON c.id = q.client_id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        WHERE i.id = ? AND (i.deleted_at IS NULL OR i.deleted_at = "")
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inv) {
        return null;
    }

    $quoteData = [];
    $quotationId = (int) ($inv['quotation_id'] ?? 0);
    if ($quotationId > 0 && !empty($inv['quote_details'])) {
        $parsed = qt_parse_quotation_details((string) $inv['quote_details']);
        if (is_array($parsed)) {
            $quoteData = $parsed;
        }
    }

    $form = is_array($quoteData['form'] ?? null) ? $quoteData['form'] : [];
    $managerComments = $quotationId > 0 && $quoteData !== []
        ? qt_quotation_messages_list($quoteData)
        : [];

    $sentRaw = trim((string) ($inv['manager_review_sent_at'] ?? ''));
    $viewedRaw = trim((string) ($inv['manager_review_viewed_at'] ?? ''));
    $review = [
        'sent' => $sentRaw !== '',
        'sent_at' => mgr_format_review_datetime($sentRaw),
        'sent_by' => trim((string) ($inv['manager_review_sent_by'] ?? '')),
        'viewed_at' => mgr_format_review_datetime($viewedRaw),
        'viewed' => $viewedRaw !== '',
    ];

    $reviewLabel = 'Awaiting send';
    $reviewBadge = 'draft';
    if ($review['sent']) {
        if ($review['viewed']) {
            $reviewLabel = 'Viewed';
            $reviewBadge = 'approved';
        } else {
            $reviewLabel = 'New for review';
            $reviewBadge = 'sent';
        }
    }

    $customerName = trim((string) ($form['customer_name'] ?? $inv['client_name'] ?? 'Walk-in'));
    if ($customerName === '') {
        $customerName = 'Walk-in';
    }

    $invNumber = trim((string) ($inv['invoice_number'] ?? ''));
    if ($invNumber === '') {
        $invNumber = 'INV-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    $invStatus = strtolower(trim((string) ($inv['status_paid'] ?? 'unpaid')));
    $statusLabels = ['unpaid' => 'Unpaid', 'partial' => 'Partially paid', 'paid' => 'Paid'];
    $statusLabel = $statusLabels[$invStatus] ?? ucfirst($invStatus);
    $statusBadge = in_array($invStatus, ['paid', 'partial', 'unpaid'], true) ? $invStatus : 'unpaid';

    $invAmount = (float) ($inv['amount'] ?? 0);
    $vatAmount = (float) ($inv['vat_amount'] ?? 0);
    $subtotal = max(0, $invAmount - $vatAmount);
    $vatPct = 0;
    if ($subtotal > 0 && $vatAmount > 0) {
        $vatPct = (int) round(100 * $vatAmount / $subtotal);
    }

    $paidAmount = $invStatus === 'paid' ? $invAmount : ($invStatus === 'partial' ? max(0, $invAmount * 0.5) : 0.0);
    $dueAmount = max(0, $invAmount - $paidAmount);
    $paidPct = $invAmount > 0 ? (int) round(100 * $paidAmount / $invAmount) : 0;
    $duePct = max(0, 100 - $paidPct);

    $dueRaw = trim((string) ($inv['due_date'] ?? ''));
    $isOverdue = $invStatus !== 'paid' && $dueRaw !== '' && strtotime($dueRaw) !== false && strtotime($dueRaw) < strtotime('today');
    $dueDisplay = $dueRaw !== '' && strtotime($dueRaw) !== false ? date('d M Y', strtotime($dueRaw)) : '—';
    $issuedDisplay = !empty($inv['issued_date']) && strtotime((string) $inv['issued_date']) !== false
        ? date('d M Y', strtotime((string) $inv['issued_date']))
        : '—';
    $paidAtRaw = trim((string) ($inv['paid_at'] ?? ''));
    $paidAtDisplay = $paidAtRaw !== '' && strtotime($paidAtRaw) !== false
        ? date('d M Y', strtotime($paidAtRaw))
        : '';

    $vehicleLines = [];
    $reg = trim((string) ($form['vehicle_reg_no'] ?? $inv['reg_no'] ?? ''));
    if ($reg !== '') {
        $vehicleLines[] = 'Reg no. ' . $reg;
    }
    $model = trim((string) ($form['model'] ?? $inv['model'] ?? ''));
    if ($model !== '') {
        $vehicleLines[] = $model;
    }
    $vin = trim((string) ($form['vin_no'] ?? $inv['vin_no'] ?? ''));
    if ($vin !== '') {
        $vehicleLines[] = 'VIN ' . $vin;
    }
    $jobNo = trim((string) ($form['job_no'] ?? $inv['card_number'] ?? ''));
    if ($jobNo !== '') {
        $vehicleLines[] = 'Job no. ' . $jobNo;
    }
    $quoteNumber = trim((string) ($form['quote_number'] ?? ''));
    if ($quoteNumber !== '') {
        $vehicleLines[] = 'Quote no. ' . $quoteNumber;
    }

    return [
        'id' => $id,
        'invoice_number' => $invNumber,
        'quotation_id' => $quotationId,
        'quote_number' => $quoteNumber,
        'job_card_id' => (int) ($inv['job_card_id'] ?? 0),
        'card_number' => trim((string) ($inv['card_number'] ?? '')),
        'customer_name' => $customerName,
        'customer_address' => trim((string) ($form['customer_address'] ?? $inv['client_address'] ?? '')),
        'customer_phone' => trim((string) ($form['customer_phone'] ?? $inv['client_phone'] ?? '')),
        'customer_email' => trim((string) ($form['customer_email'] ?? $inv['client_email'] ?? '')),
        'contact_person' => trim((string) ($form['contact_person'] ?? $inv['contact_person_primary'] ?? '')),
        'vehicle_lines' => $vehicleLines,
        'purchase_order' => trim((string) ($form['purchase_order'] ?? '')),
        'status' => $invStatus,
        'status_label' => $statusLabel,
        'status_badge' => $statusBadge,
        'is_overdue' => $isOverdue,
        'review' => $review,
        'review_label' => $reviewLabel,
        'review_badge' => $reviewBadge,
        'initials' => mgr_quotation_view_initials($customerName),
        'amount' => $invAmount,
        'subtotal' => $subtotal,
        'vat_amount' => $vatAmount,
        'vat_pct' => $vatPct,
        'paid_amount' => $paidAmount,
        'due_amount' => $dueAmount,
        'paid_pct' => $paidPct,
        'due_pct' => $duePct,
        'issued_display' => $issuedDisplay,
        'due_display' => $dueDisplay,
        'paid_at_display' => $paidAtDisplay,
        'pdf_name' => preg_replace('/[^\w\-\.]+/', '_', $invNumber) . '.pdf',
        'manager_comments' => $managerComments,
        'chat_enabled' => $quotationId > 0,
    ];
}
