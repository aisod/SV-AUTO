<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: job_card.php');
    exit;
}

$job_card_id = (int)($_POST['job_card_id'] ?? 0);
if ($job_card_id <= 0) {
    header('Location: job_card.php?error=Invalid job card ID');
    exit;
}

try {
    // Collect form data
    $client_name = trim($_POST['client_name'] ?? '');
    $vehicle_reg = trim($_POST['vehicle_reg'] ?? '');
    $description = trim($_POST['job_description'] ?? '');
    
    // Find or create client
    $client_id = null;
    if (!empty($client_name)) {
        // Check if client exists
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE name = ? LIMIT 1");
        $stmt->execute([$client_name]);
        $existing_client = $stmt->fetch();
        
        if ($existing_client) {
            $client_id = $existing_client['id'];
        } else {
            // Create new client
            $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
            $stmt->execute([$client_name]);
            $client_id = $pdo->lastInsertId();
        }
    }
    
    // Find or create vehicle
    $vehicle_id = null;
    if (!empty($vehicle_reg)) {
        // Check if vehicle exists
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE reg_no = ? LIMIT 1");
        $stmt->execute([$vehicle_reg]);
        $existing_vehicle = $stmt->fetch();
        
        if ($existing_vehicle) {
            $vehicle_id = $existing_vehicle['id'];
        } else {
            // Create new vehicle
            if (!$client_id) {
                $stmt = $pdo->prepare("INSERT INTO clients (name) VALUES (?)");
                $stmt->execute(['Walk-in Client']);
                $client_id = $pdo->lastInsertId();
            }
            $stmt = $pdo->prepare("INSERT INTO vehicles (client_id, reg_no, model) VALUES (?, ?, ?)");
            $stmt->execute([
                $client_id,
                $vehicle_reg,
                trim($_POST['model_reg'] ?? '')
            ]);
            $vehicle_id = $pdo->lastInsertId();
        }
    }
    
    // Collect extra data
    $extra_data = [
        'to_line1' => trim($_POST['to_line1'] ?? ''),
        'to_line2' => trim($_POST['to_line2'] ?? ''),
        'to_line3' => trim($_POST['to_line3'] ?? ''),
        'contact_line1' => trim($_POST['contact_line1'] ?? ''),
        'contact_line2' => trim($_POST['contact_line2'] ?? ''),
        'email_line1' => trim($_POST['email_line1'] ?? ''),
        'email_line2' => trim($_POST['email_line2'] ?? ''),
        'person_line1' => trim($_POST['person_line1'] ?? ''),
        'person_line2' => trim($_POST['person_line2'] ?? ''),
        'contact_no' => trim($_POST['contact_no'] ?? ''),
        'contact_email' => trim($_POST['contact_email'] ?? ''),
        'contact_person' => trim($_POST['contact_person'] ?? ''),
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
        'desc_line4' => trim($_POST['desc_line4'] ?? ''),
        'desc_line5' => trim($_POST['desc_line5'] ?? ''),
        'desc_line6' => trim($_POST['desc_line6'] ?? ''),
        'parts_desc' => $_POST['parts_desc'] ?? [],
        'work_details' => $_POST['work_details'] ?? [],
        'time_allocated' => $_POST['time_allocated'] ?? [],
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
        'customer_sig' => trim($_POST['customer_sig'] ?? '')
    ];
    
    // Update job card
    $stmt = $pdo->prepare("
        UPDATE job_cards 
        SET client_id = ?,
            vehicle_id = ?, 
            description = ?, 
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
            travel_cost = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $client_id,
        $vehicle_id,
        $description,
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
        $job_card_id
    ]);
    
    // Audit log
    $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'updated_job_card', 'job_card', ?)");
    $log->execute([$_SESSION['user_id'], $job_card_id]);
    
    header('Location: job_card.php?success=Job card updated successfully');
    exit;

} catch (Exception $e) {
    error_log("Job Card Update Failed: " . $e->getMessage());
    header('Location: add_job_card.php?edit_id=' . $job_card_id . '&error=' . urlencode($e->getMessage()));
    exit;
}

