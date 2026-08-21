<?php
declare(strict_types=1);

require_once __DIR__ . '/mgr_review.inc.php';
require_once __DIR__ . '/../../Admin/Quotation/quotation_paper_signoff.inc.php';

/**
 * @return array<string, mixed>|null
 */
function mgr_load_quotation_view(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('
        SELECT q.*, jc.id AS job_card_id, jc.card_number,
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
    $stmt->execute([$id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        return null;
    }

    $notes = json_decode((string) ($quote['notes'] ?? '{}'), true) ?: [];
    $quoteData = qt_parse_quotation_details((string) ($quote['details'] ?? ''));
    $form = [];
    $totals = [];
    if (is_array($quoteData)) {
        $form = is_array($quoteData['form'] ?? null) ? $quoteData['form'] : [];
        $totals = is_array($quoteData['totals'] ?? null) ? $quoteData['totals'] : [];
    }

    $managerComments = qt_quotation_messages_list(is_array($quoteData) ? $quoteData : null);
    $review = mgr_quotation_review_row_meta((string) ($quote['details'] ?? ''));
    $paper = qt_paper_signoff_info($form);
    $rejection = qt_paper_rejection_info($form);

    $customerName = trim((string) ($form['customer_name'] ?? $quote['client_name'] ?? 'Walk-in'));
    if ($customerName === '') {
        $customerName = 'Walk-in';
    }

    $labourTotal = (float) ($totals['labour_total'] ?? 0);
    $partsTotal = (float) ($totals['parts_total'] ?? 0);
    $subtotal = (float) ($totals['subtotal'] ?? $quote['subtotal'] ?? 0);
    $vatAmount = (float) ($totals['vat_amount'] ?? $quote['vat_amount'] ?? 0);
    $grandTotal = (float) ($totals['grand_total'] ?? $quote['total_amount'] ?? $quote['amount'] ?? 0);
    if ($subtotal <= 0 && $grandTotal > 0) {
        $subtotal = max(0, $grandTotal - $vatAmount);
    }
    if ($labourTotal <= 0 && $partsTotal <= 0 && $subtotal > 0) {
        $labourTotal = $subtotal;
    }

    $vatPct = 0;
    if ($subtotal > 0 && $vatAmount > 0) {
        $vatPct = (int) round(100 * $vatAmount / $subtotal);
    }

    $splitSum = $labourTotal + $partsTotal;
    $labourPct = $splitSum > 0 ? (int) round(100 * $labourTotal / $splitSum) : 50;
    $partsPct = max(0, 100 - $labourPct);

    $quoteNumber = trim((string) ($form['quote_number'] ?? ''));
    if ($quoteNumber === '') {
        $quoteNumber = 'QTN-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    $status = strtolower(trim((string) ($quote['status'] ?? 'pending')));
    $statusLabel = 'Pending Approval';
    $statusBadge = 'pending';
    if ($status === 'approved') {
        $statusLabel = 'Approved';
        $statusBadge = 'approved';
    } elseif ($status === 'rejected') {
        $statusLabel = 'Rejected';
        $statusBadge = 'rejected';
    } elseif ($status === 'sent_back_admin') {
        $statusLabel = 'Sent Back';
        $statusBadge = 'pending';
    }

    $paperLabel = 'Awaiting';
    $paperBadge = 'pending';
    if ($status === 'rejected' || $rejection['rejected']) {
        $paperLabel = 'Declined';
        $paperBadge = 'rejected';
    } elseif ($paper['signed']) {
        $paperLabel = 'Signed';
        $paperBadge = 'approved';
    }

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

    $initials = mgr_quotation_view_initials($customerName);

    $vehicleLines = [];
    $reg = trim((string) ($form['vehicle_reg_no'] ?? $quote['reg_no'] ?? ''));
    if ($reg !== '') {
        $vehicleLines[] = 'Reg no. ' . $reg;
    }
    $model = trim((string) ($form['model'] ?? $quote['model'] ?? ''));
    if ($model !== '') {
        $vehicleLines[] = $model;
    }
    $vin = trim((string) ($form['vin_no'] ?? $notes['vin'] ?? $quote['vin_no'] ?? ''));
    if ($vin !== '') {
        $vehicleLines[] = 'VIN ' . $vin;
    }
    $fleet = trim((string) ($form['fleet_no'] ?? $notes['fleet'] ?? ''));
    if ($fleet !== '') {
        $vehicleLines[] = 'Fleet no. ' . $fleet;
    }
    $km = trim((string) ($form['kilometers'] ?? $notes['km'] ?? ''));
    if ($km !== '') {
        $vehicleLines[] = $km . (stripos($km, 'km') !== false ? '' : ' KM');
    }
    $jobNo = trim((string) ($form['job_no'] ?? $quote['card_number'] ?? ''));
    if ($jobNo !== '') {
        $vehicleLines[] = 'Job no. ' . $jobNo;
    }
    if ($quoteNumber !== '') {
        $vehicleLines[] = 'Quote no. ' . $quoteNumber;
    }

    return [
        'id' => $id,
        'quote_number' => $quoteNumber,
        'customer_name' => $customerName,
        'customer_address' => trim((string) ($form['customer_address'] ?? $quote['client_address'] ?? '')),
        'customer_phone' => trim((string) ($form['customer_phone'] ?? $quote['client_phone'] ?? '')),
        'customer_email' => trim((string) ($form['customer_email'] ?? $quote['client_email'] ?? '')),
        'contact_person' => trim((string) ($form['contact_person'] ?? '')),
        'vehicle_lines' => $vehicleLines,
        'job_card_id' => (int) ($quote['job_card_id'] ?? 0),
        'card_number' => trim((string) ($quote['card_number'] ?? '')),
        'status' => $status,
        'status_label' => $statusLabel,
        'status_badge' => $statusBadge,
        'paper_label' => $paperLabel,
        'paper_badge' => $paperBadge,
        'paper_signed_at' => $paper['at'],
        'paper_signed_by' => $paper['by'],
        'review' => $review,
        'review_label' => $reviewLabel,
        'review_badge' => $reviewBadge,
        'initials' => $initials,
        'labour_total' => $labourTotal,
        'parts_total' => $partsTotal,
        'subtotal' => $subtotal,
        'vat_amount' => $vatAmount,
        'vat_pct' => $vatPct,
        'grand_total' => $grandTotal,
        'labour_pct' => $labourPct,
        'parts_pct' => $partsPct,
        'pdf_name' => preg_replace('/[^\w\-\.]+/', '_', $quoteNumber) . '.pdf',
        'manager_comments' => $managerComments,
    ];
}

function mgr_quotation_view_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($parts[0], 0, 2));
}
