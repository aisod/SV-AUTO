<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Admin/JobCard/jc_status.php';
require_once __DIR__ . '/mgr_quotation_view_data.inc.php';

/**
 * @return array<string, mixed>|null
 */
function mgr_load_job_card_view(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT jc.id, jc.card_number, jc.status, jc.created_at, jc.description,
               jc.client_id,
               COALESCE(jc_client.name, v_client.name, 'Walk-in') AS client_name,
               COALESCE(jc_client.phone, v_client.phone, '') AS client_phone,
               COALESCE(jc_client.email, v_client.email, '') AS client_email,
               COALESCE(jc_client.address, v_client.address, '') AS client_address,
               COALESCE(jc_client.contact_person, v_client.contact_person, '') AS contact_person,
               COALESCE(v.reg_no, '') AS reg_no,
               COALESCE(v.model, '') AS model,
               COALESCE(v.vin_no, '') AS vin_no,
               e.name AS technician_name
        FROM job_cards jc
        LEFT JOIN clients jc_client ON jc.client_id = jc_client.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        LEFT JOIN clients v_client ON v.client_id = v_client.id
        LEFT JOIN employees e ON jc.technician_id = e.id
        WHERE jc.id = ? AND jc.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $customerName = trim((string) ($row['client_name'] ?? 'Walk-in'));
    if ($customerName === '') {
        $customerName = 'Walk-in';
    }

    $cardNumber = trim((string) ($row['card_number'] ?? ''));
    if ($cardNumber === '') {
        $cardNumber = 'JC-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
    $statusLabel = jc_status_label($status);
    $statusBadge = in_array($status, ['completed', 'in_progress', 'pending', 'cancelled'], true) ? $status : 'pending';
    if ($status === 'completed') {
        $statusBadge = 'approved';
    } elseif ($status === 'in_progress') {
        $statusBadge = 'sent';
    } elseif ($status === 'cancelled') {
        $statusBadge = 'rejected';
    }

    $vehicleLines = [];
    $reg = trim((string) ($row['reg_no'] ?? ''));
    if ($reg !== '') {
        $vehicleLines[] = 'Reg no. ' . $reg;
    }
    $model = trim((string) ($row['model'] ?? ''));
    if ($model !== '') {
        $vehicleLines[] = $model;
    }
    $vin = trim((string) ($row['vin_no'] ?? ''));
    if ($vin !== '') {
        $vehicleLines[] = 'VIN ' . $vin;
    }

    $quotationId = 0;
    $quoteNumber = '';
    try {
        $qStmt = $pdo->prepare('SELECT id FROM quotations WHERE job_card_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1');
        $qStmt->execute([$id]);
        $quotationId = (int) ($qStmt->fetchColumn() ?: 0);
        if ($quotationId > 0) {
            $quoteNumber = 'QTN-' . str_pad((string) $quotationId, 5, '0', STR_PAD_LEFT);
        }
    } catch (Throwable $e) {
        $quotationId = 0;
    }

    $createdDisplay = !empty($row['created_at']) && strtotime((string) $row['created_at']) !== false
        ? date('d M Y', strtotime((string) $row['created_at']))
        : '—';

    return [
        'id' => $id,
        'card_number' => $cardNumber,
        'customer_name' => $customerName,
        'customer_address' => trim((string) ($row['client_address'] ?? '')),
        'customer_phone' => trim((string) ($row['client_phone'] ?? '')),
        'customer_email' => trim((string) ($row['client_email'] ?? '')),
        'contact_person' => trim((string) ($row['contact_person'] ?? '')),
        'vehicle_lines' => $vehicleLines,
        'technician_name' => trim((string) ($row['technician_name'] ?? '')),
        'description' => trim((string) ($row['description'] ?? '')),
        'status' => $status,
        'status_label' => $statusLabel,
        'status_badge' => $statusBadge,
        'created_display' => $createdDisplay,
        'quotation_id' => $quotationId,
        'quote_number' => $quoteNumber,
        'initials' => mgr_quotation_view_initials($customerName),
    ];
}
