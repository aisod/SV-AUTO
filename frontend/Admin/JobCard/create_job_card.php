<?php
// create_job_card.php â€” FIXED (2025)
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/jc_form_helpers.inc.php';
require_once __DIR__ . '/../Quotation/quotation_from_job_card.inc.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

/** @return array<int,string> */
function jc_post_trimmed_lines(string $key): array
{
    $raw = $_POST[$key] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    foreach ($raw as $v) {
        $out[] = trim(is_string($v) ? $v : (string) $v);
    }

    return $out;
}

function jc_post_bool(string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }
    $value = $_POST[$key];
    if (is_array($value)) {
        $value = reset($value);
    }
    return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add_job_card.php?error=Invalid+request+method');
    exit;
}

$submit_action = trim((string) ($_POST['submit_action'] ?? 'save_only'));
$edit_id = (int) ($_POST['edit_id'] ?? 0);
$quote_id = (int) ($_POST['quote_id'] ?? 0);
if ($edit_id > 0) {
    $quote_id = jc_sanitize_quote_id_for_job_card($pdo, $quote_id, $edit_id);
}

$card_number = trim($_POST['card_number'] ?? '');
if (empty($card_number)) {
    $editQ = $edit_id > 0 ? ('?edit_id=' . $edit_id . '&') : '?';
    header('Location: add_job_card.php' . $editQ . 'error=No+card+number+provided');
    exit;
}
if (!preg_match('/^\d{4}$/', $card_number)) {
    $editQ = $edit_id > 0 ? ('?edit_id=' . $edit_id . '&') : '?';
    header('Location: add_job_card.php' . $editQ . 'error=' . urlencode('Job card number must be exactly 4 digits.'));
    exit;
}

if (jc_find_job_card_number_conflict($pdo, $card_number, $edit_id) !== null) {
    jc_redirect_duplicate_job_card($pdo, $card_number, $edit_id);
}

try {
    $pdo->beginTransaction();

    if (jc_find_job_card_number_conflict($pdo, $card_number, $edit_id) !== null) {
        $pdo->rollBack();
        jc_redirect_duplicate_job_card($pdo, $card_number, $edit_id);
    }

    // ============= 1. CLIENT =============
    $client_id = null;
    $client_name_display = trim((string)($_POST['client_name_display'] ?? ''));
    if (!empty(trim($_POST['new_client_name'] ?? ''))) {
        // Walk-in client
        $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['new_client_name']),
            trim($_POST['new_client_phone'] ?? ''),
            trim($_POST['new_client_email'] ?? ''),
            trim($_POST['new_client_address'] ?? '')
        ]);
        $client_id = $pdo->lastInsertId();
    } elseif (!empty(trim($_POST['manual_client_name'] ?? ''))) {
        // Manual client typed in
        $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
        $stmt->execute([trim($_POST['manual_client_name'])]);
        $client_id = $pdo->lastInsertId();
    } elseif (!empty($_POST['client_id'])) {
        $client_id = (int)$_POST['client_id'];
    } elseif ($client_name_display !== '') {
        // Primary modern field from add_job_card.php
        $stmt = $pdo->prepare("INSERT INTO clients (name, phone, email) VALUES (?, ?, ?)");
        $stmt->execute([
            $client_name_display,
            trim((string)($_POST['contact_no'] ?? '')),
            trim((string)($_POST['contact_email'] ?? '')),
        ]);
        $client_id = $pdo->lastInsertId();
    }

    // ============= 2. VEHICLE =============
    $vehicle_id = null;
    if (!empty(trim($_POST['manual_vehicle_reg'] ?? ''))) {
        // Manual vehicle typed in - create client first if needed
        if (!$client_id) {
            $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
            $stmt->execute(['Walk-in Client']);
            $client_id = $pdo->lastInsertId();
        }
        
        $reg_no = strtoupper(trim($_POST['manual_vehicle_reg']));
        $model = trim($_POST['model_reg'] ?? '');
        $vin_no = trim($_POST['vin_no'] ?? '');
        
        $stmt = $pdo->prepare("INSERT INTO vehicles (client_id, reg_no, model, vin_no) VALUES (?, ?, ?, ?)");
        $stmt->execute([$client_id, $reg_no, $model, $vin_no]);
        $vehicle_id = $pdo->lastInsertId();
    } elseif (!empty($_POST['vehicle_id'])) {
        $vehicle_id = (int)$_POST['vehicle_id'];
    }

    // ============= 3. JOB DESCRIPTION =============
    $description_lines = jc_post_trimmed_lines('description_lines');
    $job_description = trim($_POST['job_description'] ?? '');
    if ($job_description === '') {
        $nonEmptyDesc = array_values(array_filter($description_lines, static fn ($ln) => $ln !== ''));
        $job_description = $nonEmptyDesc !== [] ? implode("\n", $nonEmptyDesc) : '';
    }

    $total_hours_post = (float) ($_POST['total_hours'] ?? 0);
    $labelFields = jc_general_header_labels_from_post($_POST);

    // ============= 4. COLLECT ALL EXTRA FIELDS =============
    $extra_data = [
        'to_line1' => trim($_POST['to_line1'] ?? ''),
        'contact_line1' => trim($_POST['contact_line1'] ?? ''),
        'contact_line2' => trim($_POST['contact_line2'] ?? ''),
        'email_line1' => trim($_POST['email_line1'] ?? ''),
        'email_line2' => trim($_POST['email_line2'] ?? ''),
        'person_line1' => trim($_POST['person_line1'] ?? ''),
        'person_line2' => trim($_POST['person_line2'] ?? ''),
        'contact_no' => trim($_POST['contact_no'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
        'client_name_display' => trim($_POST['client_name_display'] ?? ''),
        'job_date' => trim($_POST['job_date'] ?? ''),
        'vin_no' => trim($_POST['vin_no'] ?? ''),
        'kilometre' => trim($_POST['kilometre'] ?? ''),
        'fleet_no' => trim($_POST['fleet_no'] ?? ''),
        'model_reg' => trim($_POST['model_reg'] ?? ''),
        'purchase_order_no' => trim($_POST['purchase_order_no'] ?? ''),
        'quotation_no' => trim($_POST['quotation_no'] ?? ''),
        'invoice_no' => trim($_POST['invoice_no'] ?? ''),
        'desc_line1' => trim($_POST['desc_line1'] ?? ''),
        'desc_line2' => trim($_POST['desc_line2'] ?? ''),
        'desc_line3' => trim($_POST['desc_line3'] ?? ''),
        'to_line2' => trim($_POST['to_line2'] ?? ''),
        'to_line3' => trim($_POST['to_line3'] ?? ''),
        'description_lines' => $description_lines,
        'parts_left' => jc_post_trimmed_lines('parts_left'),
        'parts_right' => jc_post_trimmed_lines('parts_right'),
        'parts_desc' => $_POST['parts_desc'] ?? [],
        'parts_qty' => $_POST['parts_qty'] ?? [],
        'parts_unit' => $_POST['parts_unit'] ?? [],
        'work_details' => jc_post_trimmed_lines('work_details'),
        'time_allocated' => jc_post_trimmed_lines('time_allocated'),
        'labor_total_hours' => $total_hours_post,
        'rate_normal' => (float) ($_POST['rate_normal'] ?? 560),
        'rate_after' => (float) ($_POST['rate_after'] ?? 0),
        'rate_weekend' => (float) ($_POST['rate_weekend'] ?? 0),
        'rate_holiday' => (float) ($_POST['rate_holiday'] ?? 0),
        'vat_rate' => (float) ($_POST['vat_rate'] ?? 15),
        'general_header_labels' => $labelFields['general_header_labels'],
        'attend_to_service_label' => $labelFields['attend_to_service_label'],
        'diagnostic_label' => $labelFields['diagnostic_label'],
        'show_normal_time' => jc_post_bool('show_normal_time', false),
        'show_overtime' => jc_post_bool('show_overtime', false),
        'show_public_holiday' => jc_post_bool('show_public_holiday', false),
        'blank_normal_lines' => (int) ($_POST['blank_normal_lines'] ?? 5),
        'blank_overtime_lines' => (int) ($_POST['blank_overtime_lines'] ?? 5),
        'blank_holiday_lines' => (int) ($_POST['blank_holiday_lines'] ?? 5),
        'blank_parts_lines' => (int) ($_POST['blank_parts_lines'] ?? 6),
        'blank_cons_lines' => (int) ($_POST['blank_cons_lines'] ?? 3),
        'print_blank_show_labour' => jc_post_bool('print_blank_show_labour', true),
        'print_blank_show_parts' => jc_post_bool('print_blank_show_parts', true),
        'blank_col_lab_hours' => jc_post_bool('blank_col_lab_hours', true),
        'blank_col_lab_rate' => jc_post_bool('blank_col_lab_rate', true),
        'blank_col_lab_total' => jc_post_bool('blank_col_lab_total', true),
        'blank_col_part_qty' => jc_post_bool('blank_col_part_qty', true),
        'blank_col_part_cost' => jc_post_bool('blank_col_part_cost', true),
        'blank_col_part_total' => jc_post_bool('blank_col_part_total', true),
        'labour_print' => $_POST['labour_print'] ?? [],
        'labour_desc' => jc_post_trimmed_lines('labour_desc'),
        'labour_hours' => jc_post_trimmed_lines('labour_hours'),
        'labour_rate' => jc_post_trimmed_lines('labour_rate'),
        'labour_total' => jc_post_trimmed_lines('labour_total'),
        'parts_print' => $_POST['parts_print'] ?? [],
        'parts_item_name' => jc_post_trimmed_lines('parts_item_name'),
        'parts_qty2' => jc_post_trimmed_lines('parts_qty2'),
        'parts_unit_cost' => jc_post_trimmed_lines('parts_unit_cost'),
        'parts_total2' => jc_post_trimmed_lines('parts_total2'),
        'cons_print' => $_POST['cons_print'] ?? [],
        'cons_item_name' => jc_post_trimmed_lines('cons_item_name'),
        'cons_qty' => jc_post_trimmed_lines('cons_qty'),
        'cons_unit_cost' => jc_post_trimmed_lines('cons_unit_cost'),
        'cons_total' => jc_post_trimmed_lines('cons_total'),
        'distance_km' => (float) ($_POST['distance_km'] ?? 0),
        'service_location' => trim($_POST['service_location'] ?? ''),
        'mobile_callout' => (float) ($_POST['mobile_callout'] ?? 0),
        'mobile_per_km' => (float) ($_POST['mobile_per_km'] ?? 0),
        'mobile_min_dist' => (float) ($_POST['mobile_min_dist'] ?? 0),
        'callout_km' => trim($_POST['callout_km'] ?? ''),
        'callout_fee' => trim($_POST['callout_fee'] ?? ''),
        'callout_consumables' => trim($_POST['callout_consumables'] ?? ''),
        'callout_overtime' => trim($_POST['callout_overtime'] ?? ''),
        'callout_sunday' => trim($_POST['callout_sunday'] ?? ''),
        'normal_time' => trim($_POST['normal_time'] ?? ''),
        'overtime' => trim($_POST['overtime'] ?? ''),
        'sunday_holiday' => trim($_POST['sunday_holiday'] ?? ''),
        'technician_no' => trim($_POST['technician_no'] ?? ''),
        'coming_back' => trim($_POST['coming_back'] ?? ''),
        'customer_sig' => trim($_POST['customer_sig'] ?? ''),
        'quotation_labour_sections' => json_decode((string)($_POST['quotation_labour_sections'] ?? '[]'), true) ?: [],
        'quotation_parts' => json_decode((string)($_POST['quotation_parts'] ?? '[]'), true) ?: [],
        'show_consumables' => jc_post_bool('show_consumables', false),
        'consumables_label' => 'Call-out',
    ];

    // ============= 5. INSERT/UPDATE JOB CARD =============
    if ($edit_id > 0) {
        $stmt = $pdo->prepare("
            UPDATE job_cards SET
                card_number = ?,
                client_id = ?,
                vehicle_id = ?,
                description = ?,
                parts_supply = ?,
                extra_data = ?,
                service_type = ?,
                work_date = ?,
                work_start_time = ?,
                work_end_time = ?,
                total_hours = ?,
                distance_km = ?,
                labor_rate_applied = ?,
                labor_cost = ?,
                callout_fee = ?,
                travel_cost = ?
            WHERE id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([
            $card_number,
            $client_id,
            $vehicle_id,
            $job_description,
            trim($_POST['parts_supply'] ?? ''),
            json_encode($extra_data),
            trim($_POST['service_type'] ?? 'in_shop'),
            trim($_POST['work_date'] ?? null),
            trim($_POST['work_start_time'] ?? null),
            trim($_POST['work_end_time'] ?? null),
            (float)($_POST['total_hours'] ?? 0),
            (float)($_POST['distance_km'] ?? 0),
            (float)($_POST['labor_rate_applied'] ?? 0),
            (float)($_POST['labor_cost'] ?? 0),
            (float)($_POST['callout_fee'] ?? 0),
            (float)($_POST['travel_cost'] ?? 0),
            $edit_id
        ]);
        $job_card_id = $edit_id;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO job_cards 
                (card_number, client_id, vehicle_id, description, parts_supply, status, extra_data,
                 service_type, work_date, work_start_time, work_end_time, total_hours,
                 distance_km, labor_rate_applied, labor_cost, callout_fee, travel_cost) 
            VALUES (?, ?, ?, ?, ?, 'new', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $card_number,
            $client_id,
            $vehicle_id,
            $job_description,
            trim($_POST['parts_supply'] ?? ''),
            json_encode($extra_data),
            trim($_POST['service_type'] ?? 'in_shop'),
            trim($_POST['work_date'] ?? null),
            trim($_POST['work_start_time'] ?? null),
            trim($_POST['work_end_time'] ?? null),
            (float)($_POST['total_hours'] ?? 0),
            (float)($_POST['distance_km'] ?? 0),
            (float)($_POST['labor_rate_applied'] ?? 0),
            (float)($_POST['labor_cost'] ?? 0),
            (float)($_POST['callout_fee'] ?? 0),
            (float)($_POST['travel_cost'] ?? 0)
        ]);
        $job_card_id = $pdo->lastInsertId();
    }

    // ============= 6. AUDIT LOG (non-fatal) =============
    // Wrapped separately so a missing/changed audit_logs table never kills the save
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, 'job_card', ?)");
        $stmt->execute([$_SESSION['user_id'], $edit_id > 0 ? 'updated job card' : 'created job card', $job_card_id]);
    } catch (Exception $auditEx) {
        // Audit failure is non-fatal â€” job card is still saved
        error_log("Audit log insert failed (non-fatal): " . $auditEx->getMessage());
    }

    $pdo->commit();

    if ($submit_action === 'save_only') {
        $redirect = 'add_job_card.php?edit_id=' . (int)$job_card_id;
        $message = $edit_id > 0
            ? "Job card {$card_number} successfully updated."
            : "Job card {$card_number} successfully saved.";

        if ($quote_id <= 0) {
            $quote_id = jc_find_active_quotation_id_for_job_card($pdo, (int) $job_card_id);
        }
        $quote_id = jc_sanitize_quote_id_for_job_card($pdo, (int) $quote_id, (int) $job_card_id);

        if ($quote_id > 0) {
            $redirect .= '&quote_id=' . (int) $quote_id;
        }

        header('Location: ' . $redirect . '&success=' . rawurlencode($message));
        exit;
    }

    if ($submit_action === 'update_quotation') {
        jc_handle_update_quotation_after_save($pdo, (int) $job_card_id, $card_number, $quote_id);
    }

    if ($submit_action === 'open_quotation') {
        jc_handle_open_quotation_after_save($pdo, (int) $job_card_id, $card_number, $quote_id);
    }

    header(
        'Location: add_job_card.php?edit_id=' . (int) $job_card_id . '&success='
        . rawurlencode("Job card {$card_number} successfully saved.")
    );
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Job Card Creation Failed: " . $e->getMessage());
    $errMsg = $e->getMessage();
    if ((string)$e->getCode() === '23000' || stripos($errMsg, 'Duplicate entry') !== false) {
        jc_redirect_duplicate_job_card($pdo, $card_number, $edit_id);
    }
    $editQ = $edit_id > 0 ? ('?edit_id=' . $edit_id . '&') : '?';
    header("Location: add_job_card.php" . $editQ . "error=" . urlencode('Failed to save job card. Please check required fields and try again.'));
    exit;
}
?>
