<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';

// Only admin can create quotations
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: add_quotation.php?error=Invalid request method');
    exit;
}

$job_card_id = (int)($_POST['job_card_id'] ?? 0);
$client_name = trim($_POST['client_name'] ?? '');
$client_address = trim($_POST['client_address'] ?? '');
$client_phone = trim($_POST['client_phone'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$quote_date = trim($_POST['quote_date'] ?? '');
$reg_no = trim($_POST['reg_no'] ?? '');
$model = trim($_POST['model'] ?? '');
$vin_no = trim($_POST['vin_no'] ?? '');
$kilometre = trim($_POST['kilometre'] ?? '');
$complaint = trim($_POST['complaint'] ?? '');
$items = $_POST['items'] ?? [];
$notes = trim($_POST['notes'] ?? '');

// Validation
if ($job_card_id <= 0) {
    header('Location: add_quotation.php?error=' . urlencode('Please select a job card'));
    exit;
}

if (empty($items)) {
    header('Location: add_quotation.php?error=' . urlencode('Please add at least one item or service'));
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get job card details
    $stmt = $pdo->prepare("
        SELECT jc.*, c.id AS client_id, v.id AS vehicle_id
        FROM job_cards jc
        LEFT JOIN clients c ON jc.client_id = c.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        WHERE jc.id = ?
    ");
    $stmt->execute([$job_card_id]);
    $job_card = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$job_card) {
        throw new Exception('Job card not found');
    }
    
    // Check if quotation already exists for this job card
    $stmt = $pdo->prepare("SELECT id FROM quotations WHERE job_card_id = ?");
    $stmt->execute([$job_card_id]);
    if ($stmt->fetch()) {
        throw new Exception('A quotation already exists for this job card');
    }
    
    // Process items
    $valid_items = [];
    foreach ($items as $item) {
        $desc  = trim($item['desc'] ?? '');
        $qty   = max(1, (float)($item['qty'] ?? 1));
        $price = (float)($item['price'] ?? 0);

        if ($desc !== '' && $price > 0) {
            $valid_items[] = compact('desc', 'qty', 'price');
        }
    }

    if (empty($valid_items)) {
        throw new Exception('Please complete at least one item with description and price');
    }
    
    // Calculate total and build details
    $currency = getCurrency();
    $business = getBusiness();
    $total = 0;
    $details = "<strong>QUOTATION – REPAIR & SERVICE</strong>\n";
    $details .= str_repeat("═", 55) . "\n\n";

    foreach ($valid_items as $i) {
        $line = $i['qty'] * $i['price'];
        $total += $line;
        $details .= "• {$i['desc']}\n";
        $details .= $i['qty'] > 1 
            ? "   Qty: {$i['qty']} × {$currency['symbol']}" . number_format($i['price'], 2) . " = {$currency['symbol']}" . number_format($line, 2) . "\n\n"
            : "   {$currency['symbol']}" . number_format($i['price'], 2) . "\n\n";
    }

    if ($notes) {
        $details .= "<strong>Notes:</strong>\n{$notes}\n\n";
    }
    
    $details .= str_repeat("═", 55) . "\n";
    $details .= "<strong>TOTAL: {$currency['symbol']}" . number_format($total, 2) . "</strong>\n";
    $details .= "Valid 30 days • 50% deposit required\nThank you – " . ($business['name'] ?? 'SV Auto');
    
    // Update client information if exists
    if (!empty($job_card['client_id'])) {
        $stmt = $pdo->prepare("UPDATE clients SET name = ?, address = ?, phone = ?, email = ? WHERE id = ?");
        $stmt->execute([$client_name, $client_address, $client_phone, $client_email, $job_card['client_id']]);
    }
    
    // Update vehicle information if exists
    if (!empty($job_card['vehicle_id'])) {
        $stmt = $pdo->prepare("UPDATE vehicles SET reg_no = ?, model = ?, vin_no = ? WHERE id = ?");
        $stmt->execute([$reg_no, $model, $vin_no, $job_card['vehicle_id']]);
    }
    
    // Update job card description and extra_data
    $extra_data = json_decode($job_card['extra_data'] ?? '{}', true) ?: [];
    $extra_data['kilometre'] = $kilometre;
    
    $stmt = $pdo->prepare("UPDATE job_cards SET description = ?, extra_data = ? WHERE id = ?");
    $stmt->execute([$complaint, json_encode($extra_data), $job_card_id]);
    
    // Insert quotation
    $stmt = $pdo->prepare("
        INSERT INTO quotations 
        (job_card_id, client_id, amount, details, status, submitted_at, created_by) 
        VALUES (?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([
        $job_card_id,
        $job_card['client_id'],
        $total,
        $details,
        $quote_date,
        $_SESSION['user_id']
    ]);
    
    $quotation_id = $pdo->lastInsertId();
    
    // Audit log
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'created_quotation', 'quotation', ?)");
        $stmt->execute([$_SESSION['user_id'], $quotation_id]);
    } catch (Exception $e) {
        // Non-fatal
        error_log("Audit log failed: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    header("Location: view_quotation.php?id=$quotation_id&success=" . urlencode('Quotation created successfully'));
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Quotation creation failed: " . $e->getMessage());
    header("Location: add_quotation.php?error=" . urlencode($e->getMessage()));
    exit;
}

