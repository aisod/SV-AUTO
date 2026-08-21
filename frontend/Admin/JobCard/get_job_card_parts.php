<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$job_card_id = (int)($_GET['job_card_id'] ?? 0);

if ($job_card_id <= 0) {
    echo json_encode(['error' => 'Invalid job card ID']);
    exit;
}

try {
    // Fetch job card with all details
    $stmt = $pdo->prepare("
        SELECT jc.*, 
               v.reg_no, v.model, v.vin_no, v.fiscal_no,
               COALESCE(NULLIF(CONCAT(TRIM(c.first_name), ' ', TRIM(c.last_name)), ' '), c.name, '') AS client_name,
               COALESCE(c.address, '') AS client_address,
               COALESCE(c.phone, '') AS client_phone,
               COALESCE(c.email, '') AS client_email
        FROM job_cards jc
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        LEFT JOIN clients c ON c.id = COALESCE(v.client_id, jc.client_id)
        WHERE jc.id = ? AND jc.deleted_at IS NULL
    ");
    $stmt->execute([$job_card_id]);
    $job_card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job_card) {
        echo json_encode(['error' => 'Job card not found']);
        exit;
    }
    
    // Parse extra_data
    $extra = [];
    if (!empty($job_card['extra_data'])) {
        $decoded = json_decode($job_card['extra_data'], true);
        if (is_array($decoded)) {
            $extra = $decoded;
        }
    }
    
    // Extract parts from extra_data
    $parts = [];
    if (isset($extra['parts_desc']) && is_array($extra['parts_desc'])) {
        $parts_desc = $extra['parts_desc'];
        $parts_qty = $extra['parts_qty'] ?? [];
        $parts_unit = $extra['parts_unit'] ?? [];
        
        foreach ($parts_desc as $index => $desc) {
            $desc = trim($desc);
            if ($desc !== '') {
                $parts[] = [
                    'name' => $desc,
                    'qty' => $parts_qty[$index] ?? 1,
                    'unit' => $parts_unit[$index] ?? 'pcs'
                ];
            }
        }
    }
    
    // Build response
    $response = [
        'success' => true,
        'job_card' => [
            'id' => $job_card['id'],
            'card_number' => $job_card['card_number'],
            'description' => $job_card['description'],
        ],
        'client' => [
            'name' => $job_card['client_name'],
            'address' => $job_card['client_address'],
            'phone' => $job_card['client_phone'],
            'email' => $job_card['client_email'],
        ],
        'vehicle' => [
            'reg_no' => $job_card['reg_no'] ?? '',
            'model' => $job_card['model'] ?? '',
            'vin_no' => $job_card['vin_no'] ?? ($extra['vin_no'] ?? ''),
            'fiscal_no' => $job_card['fiscal_no'] ?? ($extra['fleet_no'] ?? ''),
        ],
        'additional' => [
            'kilometers' => $extra['kilometers'] ?? ($extra['km'] ?? ''),
            'purchase_order' => $extra['purchase_order'] ?? ($extra['po'] ?? ''),
        ],
        'parts' => $parts
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching job card parts: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}

