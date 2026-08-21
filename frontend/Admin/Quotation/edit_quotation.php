<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/quotation_sync_invoice.inc.php';

// Only admin can edit quotations
require_admin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: quotations.php?error=Invalid quotation ID');
    exit;
}

$quote_id = (int)$_GET['id'];
$business = getBusiness();
$currency = getCurrency();

// LOAD THE QUOTATION FIRST (needed for both GET and POST)
$stmt = $pdo->prepare("
    SELECT 
        q.*,
        jc.id AS job_card_id,
        jc.vehicle_id,
        jc.card_number,
        jc.description AS job_description,
        jc.extra_data,
        c.id AS client_id,
        c.name AS client_name,
        c.phone AS client_phone,
        c.email AS client_email,
        c.address AS client_address,
        v.reg_no,
        v.model,
        v.vin_no
    FROM quotations q
    LEFT JOIN job_cards jc ON q.job_card_id = jc.id
    LEFT JOIN clients c ON q.client_id = c.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE q.id = ?
    LIMIT 1
");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header('Location: quotations.php?error=Quotation not found');
    exit;
}

// Allow editing of all quotations regardless of status
// (Removed status check - admins can edit any quotation)

// HANDLE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== QUOTATION UPDATE DEBUG ===");
    error_log("POST data received: " . print_r($_POST, true));
    
    $client_name = trim($_POST['client_name'] ?? '');
    $client_address = trim($_POST['client_address'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $quote_date = trim($_POST['quote_date'] ?? '');
    $job_card_no = trim($_POST['job_card_no'] ?? '');
    $reg_no = trim($_POST['reg_no'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $vin_no = trim($_POST['vin_no'] ?? '');
    $kilometre = trim($_POST['kilometre'] ?? '');
    $complaint = trim($_POST['complaint'] ?? '');
    $items = $_POST['items'] ?? [];
    $notes = trim($_POST['notes'] ?? '');
    
    error_log("Items count: " . count($items));
    
    if (empty($items)) {
        $error = "Please add at least one item or service.";
        error_log("ERROR: No items provided");
    } else {
        $valid_items = [];
        foreach ($items as $item) {
            $desc  = trim($item['desc'] ?? '');
            $qty   = max(1, (float)($item['qty'] ?? 1));
            $price = (float)($item['price'] ?? 0);

            if ($desc !== '' && $price > 0) {
                $valid_items[] = compact('desc', 'qty', 'price');
            }
        }

        error_log("Valid items count: " . count($valid_items));

        if (empty($valid_items)) {
            $error = "Please complete at least one item with description and price.";
            error_log("ERROR: No valid items");
        } else {
            $total = 0;
            $details = "<strong>QUOTATION â€“ REPAIR & SERVICE</strong>\n";
            $details .= str_repeat("â•", 55) . "\n\n";

            foreach ($valid_items as $i) {
                $line = $i['qty'] * $i['price'];
                $total += $line;
                $details .= "â€¢ {$i['desc']}\n";
                $details .= $i['qty'] > 1 
                    ? "   Qty: {$i['qty']} Ã— {$currency['symbol']}" . number_format($i['price'], 2) . " = {$currency['symbol']}" . number_format($line, 2) . "\n\n"
                    : "   {$currency['symbol']}" . number_format($i['price'], 2) . "\n\n";
            }

            if ($notes) $details .= "<strong>Notes:</strong>\n{$notes}\n\n";
            $details .= str_repeat("â•", 55) . "\n";
            $details .= "<strong>TOTAL: {$currency['symbol']}" . number_format($total, 2) . "</strong>\n";
            $details .= "Valid 30 days â€¢ 50% deposit required\nThank you â€“ " . ($business['name'] ?? 'SV Auto');

            error_log("Total calculated: " . $total);
            error_log("Details built: " . substr($details, 0, 200));

            try {
                // Update quotation with all editable fields
                $stmt = $pdo->prepare("UPDATE quotations SET amount = ?, details = ?, submitted_at = ? WHERE id = ?");
                $result = $stmt->execute([$total, $details, $quote_date, $quote_id]);
                error_log("Quotation update result: " . ($result ? 'SUCCESS' : 'FAILED'));
                error_log("Rows affected: " . $stmt->rowCount());

                if ($result) {
                    aq_sync_linked_invoice_totals($pdo, $quote_id, (float) $total, 0.0);
                }

                // Update CLIENT information
                if (!empty($quote['client_id'])) {
                    $client_stmt = $pdo->prepare("UPDATE clients SET name = ?, address = ?, phone = ?, email = ? WHERE id = ?");
                    $client_result = $client_stmt->execute([$client_name, $client_address, $client_phone, $client_email, $quote['client_id']]);
                    error_log("Client update result: " . ($client_result ? 'SUCCESS' : 'FAILED'));
                }

                // Update VEHICLE information
                if (!empty($quote['vehicle_id'])) {
                    $vehicle_stmt = $pdo->prepare("UPDATE vehicles SET reg_no = ?, model = ?, vin_no = ? WHERE id = (SELECT vehicle_id FROM job_cards WHERE id = ?)");
                    $vehicle_result = $vehicle_stmt->execute([$reg_no, $model, $vin_no, $quote['job_card_id']]);
                    error_log("Vehicle update result: " . ($vehicle_result ? 'SUCCESS' : 'FAILED'));
                }

                // Update job card description (customer complaint)
                if (!empty($quote['job_card_id'])) {
                    $jc_stmt = $pdo->prepare("UPDATE job_cards SET description = ? WHERE id = ?");
                    $jc_result = $jc_stmt->execute([$complaint, $quote['job_card_id']]);
                    error_log("Job card description update: " . ($jc_result ? 'SUCCESS' : 'FAILED'));
                    
                    // Update extra_data with kilometre
                    $extra_update = json_decode($quote['extra_data'] ?? '{}', true) ?: [];
                    $extra_update['kilometre'] = $kilometre;
                    $extra_stmt = $pdo->prepare("UPDATE job_cards SET extra_data = ? WHERE id = ?");
                    $extra_result = $extra_stmt->execute([json_encode($extra_update), $quote['job_card_id']]);
                    error_log("Job card extra_data update: " . ($extra_result ? 'SUCCESS' : 'FAILED'));
                }

                $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, 'updated_quotation', 'quotation', ?)");
                $log->execute([$_SESSION['user_id'], $quote_id]);

                error_log("Redirecting to view_quotation.php");
                header("Location: view_quotation.php?id=$quote_id&success=Quotation updated successfully");
                exit;
            } catch (Exception $e) {
                error_log("Quotation update error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $error = "Failed to update quotation. Error: " . $e->getMessage();
            }
        }
    }
}

// Decode extra_data from job card
$extra = json_decode($quote['extra_data'] ?? '{}', true) ?: [];

// Build full description - use ONLY the main description field
// (desc_line1-6 are already part of the description in the job card)
$full_description = $quote['job_description'] ?? '';

// Debug logging
error_log("EDIT QUOTATION - Full description: " . $full_description);

// Parse items from details - SIMPLE APPROACH
$items = [];

// Extract all lines between the separator and TOTAL
if (preg_match_all('/â€¢\s*(.+?)\s*\n\s*N\$\s*([0-9,.]+)/s', $quote['details'], $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $items[] = [
            'desc' => trim($match[1]),
            'qty' => 1,
            'price' => (float)str_replace(',', '', $match[2])
        ];
    }
}

// If no items found, add empty row
if (empty($items)) {
    $items[] = ['desc' => '', 'qty' => 1, 'price' => 0];
}

// Load inventory for dropdown
$inventory = $pdo->query("SELECT part_name, price, stock FROM inventory WHERE stock > 0 ORDER BY part_name")->fetchAll(PDO::FETCH_ASSOC);

$logo_base64 = '';
$logo_path = __DIR__ . '/../assets/images/companylogo.jpeg';
if (file_exists($logo_path)) {
    $logo_base64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<style>
    :root {
        --p:#F5A623; --s:#1a1a1a; --a:#FFF8EC; --t:#1a1a1a;
        --g:#2e7d32; --r:#c62828;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { background:#FFFBF5; font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; color:var(--t); }

    .page-title {
        font-size:22px; color:var(--s); margin-bottom:16px;
        font-weight:900; display:flex; align-items:center; gap:10px;
    }

    /* QUOTATION SHELL */
    .quote-shell {
        background:white;
        border:2px solid #000;
        max-width:900px;
        margin:0 auto 16px;
        font-family:Arial,sans-serif;
    }

    /* HEADER */
    .quote-head {
        display:flex; justify-content:space-between;
        align-items:flex-start; padding:8px 12px;
        border-bottom:2px solid #000;
    }
    .quote-head img { height:50px; width:auto; }
    .quote-head-address {
        text-align:right; font-size:9px;
        line-height:1.5; color:#222;
    }

    /* TITLE BAR */
    .quote-title-bar {
        display:flex; justify-content:space-between; align-items:center;
        background:#f5f5f5; border-bottom:2px solid #000; padding:6px 14px;
    }
    .quote-title-bar .title {
        font-size:20px; font-weight:900;
        letter-spacing:5px; text-transform:uppercase;
    }
    .quote-title-bar .quote-no {
        font-size:14px; font-weight:900;
        color:var(--s); letter-spacing:2px;
    }

    /* TWO COLUMN BODY */
    .quote-body {
        display:grid;
        grid-template-columns:1fr 1fr;
        border-bottom:2px solid #000;
    }
    .quote-col-left {
        border-right:2px solid #000;
        padding:8px 12px;
    }
    .quote-col-right { padding:8px 12px; }

    /* FIELD ROW */
    .quote-field {
        display:flex; align-items:flex-end;
        margin-bottom:7px; gap:6px;
    }
    .quote-field label {
        font-size:9px; font-weight:700;
        text-transform:uppercase; white-space:nowrap;
        color:#333; min-width:110px;
        padding-bottom:3px; letter-spacing:0.3px;
    }
    .quote-field .value {
        flex:1; border-bottom:1.5px solid #000;
        padding:3px 5px;
        font-size:12px; color:#111;
        min-height:20px;
        font-weight:600;
    }

    /* SECTION TITLE */
    .quote-section {
        text-align:center; font-size:10px; font-weight:900;
        letter-spacing:4px; text-transform:uppercase;
        background:#f0f0f0; border-top:2px solid #000;
        border-bottom:1px solid #000; padding:4px 0;
    }

    /* ITEMS TABLE */
    .items-table {
        width:100%;
        border-collapse:collapse;
        margin:0;
    }
    .items-table th {
        background:#f0f0f0;
        border:1px solid #999;
        padding:5px 8px;
        font-size:9px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:1px;
        text-align:left;
    }
    .items-table td {
        border:1px solid #ccc;
        padding:4px 6px;
        min-height:22px;
        font-size:11px;
        color:#111;
        font-weight:600;
        vertical-align:top;
    }
    .items-table input, .items-table select {
        width:100%;
        border:none;
        background:transparent;
        font-size:11px;
        padding:2px;
        font-weight:600;
    }
    .items-table input:focus, .items-table select:focus {
        background:white;
        outline:1px solid var(--p);
    }
    
    /* EDITABLE FIELDS */
    .editable-input {
        width:100%;
        border:1px solid #ccc;
        background:white;
        font-size:12px;
        padding:4px 6px;
        font-weight:600;
        border-radius:4px;
    }
    .editable-input:focus {
        outline:none;
        border-color:var(--p);
        box-shadow:0 0 0 2px rgba(210,105,30,0.2);
    }
    .editable-textarea {
        width:100%;
        border:1px solid #ccc;
        background:white;
        font-size:11px;
        padding:8px;
        font-family:Arial,sans-serif;
        min-height:60px;
        border-radius:4px;
        font-weight:600;
    }
    .editable-textarea:focus {
        outline:none;
        border-color:var(--p);
        box-shadow:0 0 0 2px rgba(210,105,30,0.2);
    }
    .items-table .qty-col { width:80px; }
    .items-table .price-col { width:100px; }
    .items-table .total-col { width:100px; text-align:right; }
    .items-table .remove-col { width:40px; text-align:center; }
    .remove-btn {
        color:var(--r);
        font-size:18px;
        cursor:pointer;
        font-weight:bold;
    }

    /* TOTAL BOX */
    .total-box {
        background:#f0f0f0;
        border:2px solid #000;
        padding:12px 14px;
        text-align:right;
        font-size:16px;
        font-weight:900;
        color:var(--s);
    }

    /* ADD BUTTON */
    .add-btn {
        background:var(--p);
        color:white;
        padding:10px 24px;
        border:none;
        border-radius:8px;
        font-weight:700;
        cursor:pointer;
        margin:12px;
        font-size:14px;
    }

    /* NOTES */
    .notes-section {
        padding:12px;
        border-bottom:2px solid #000;
    }
    .notes-section textarea {
        width:100%;
        border:1px solid #ccc;
        padding:8px;
        font-size:11px;
        font-family:Arial,sans-serif;
        min-height:60px;
    }

    /* BUTTONS */
    .btn-bar {
        max-width:900px; margin:20px auto 40px;
        display:flex; gap:12px;
    }
    .btn {
        padding:12px 24px;
        border:none;
        border-radius:8px;
        color:white;
        font-weight:700;
        font-size:15px;
        cursor:pointer;
        text-decoration:none;
        display:inline-flex;
        align-items:center;
        gap:8px;
    }
    .btn-cancel { background:#666; }
    .btn-save { background:#27ae60; }

    /* ALERT */
    .alert {
        max-width:900px;
        margin:20px auto;
        padding:20px;
        border-radius:12px;
        font-weight:700;
        display:flex;
        align-items:center;
        gap:12px;
    }
    .alert-error { background:#ffebee; color:#c62828; border:3px solid #f44336; }
</style>

    <h2 class="page-title">
        <i class="fas fa-edit"></i> Edit Quotation: QTN-<?php echo str_pad($quote['id'], 6, '0', STR_PAD_LEFT); ?>
    </h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="font-size:20px;"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="editForm">
    
    <!-- QUOTATION DOCUMENT -->
    <div class="quote-shell">

        <!-- HEADER -->
        <div class="quote-head">
            <div>
                <?php if ($logo_base64): ?>
                    <img src="<?php echo $logo_base64; ?>" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="quote-head-address">
                <?php echo htmlspecialchars($business['address'] ?? 'Lafrenz Industrial â€¢ Rensburger Street â€¢ Erf 174LL â€¢ Unit 18'); ?><br>
                Cell: <?php echo htmlspecialchars($business['phone'] ?? ''); ?> â€¢
                Email: <?php echo htmlspecialchars($business['email'] ?? ''); ?><br>
                PO Box 21292 â€¢ Windhoek â€¢ Namibia<br>
                Reg No: <?php echo htmlspecialchars($business['tax_number'] ?? ''); ?>
            </div>
        </div>

        <!-- TITLE BAR -->
        <div class="quote-title-bar">
            <div class="title">Quotation</div>
            <div class="quote-no">
                QTN-<?php echo str_pad($quote['id'], 6, '0', STR_PAD_LEFT); ?>
                <span style="background:#fff4e5; color:#d97706; padding:4px 12px; border-radius:12px; font-size:10px; margin-left:8px;">
                    EDITING
                </span>
            </div>
        </div>

        <!-- TWO COLUMN BODY (EDITABLE) -->
        <div class="quote-body">

            <!-- LEFT: CLIENT INFO -->
            <div class="quote-col-left">
                <div class="quote-field">
                    <label>To</label>
                    <div class="value">
                        <input type="text" name="client_name" class="editable-input" value="<?php echo htmlspecialchars($quote['client_name'] ?? ''); ?>" placeholder="Client name">
                    </div>
                </div>
                <div class="quote-field">
                    <label>Address</label>
                    <div class="value">
                        <input type="text" name="client_address" class="editable-input" value="<?php echo htmlspecialchars($quote['client_address'] ?? ''); ?>" placeholder="Client address">
                    </div>
                </div>
                <div class="quote-field">
                    <label>Contact No.</label>
                    <div class="value">
                        <input type="text" name="client_phone" class="editable-input" value="<?php echo htmlspecialchars($quote['client_phone'] ?? ''); ?>" placeholder="Phone number">
                    </div>
                </div>
                <div class="quote-field">
                    <label>Email Address</label>
                    <div class="value">
                        <input type="email" name="client_email" class="editable-input" value="<?php echo htmlspecialchars($quote['client_email'] ?? ''); ?>" placeholder="Email address">
                    </div>
                </div>
            </div>

            <!-- RIGHT: VEHICLE & JOB INFO -->
            <div class="quote-col-right">
                <div class="quote-field">
                    <label>Date</label>
                    <div class="value">
                        <input type="date" name="quote_date" class="editable-input" value="<?php echo date('Y-m-d', strtotime($quote['submitted_at'])); ?>" required>
                    </div>
                </div>
                <div class="quote-field">
                    <label>Job Card No.</label>
                    <div class="value">
                        <input type="text" name="job_card_no" class="editable-input" value="<?php echo htmlspecialchars($quote['card_number'] ?? ''); ?>" readonly style="background:#f5f5f5;">
                    </div>
                </div>
                <div class="quote-field">
                    <label>Vehicle Reg. No.</label>
                    <div class="value">
                        <input type="text" name="reg_no" class="editable-input" value="<?php echo htmlspecialchars($quote['reg_no'] ?? ''); ?>" placeholder="Registration number">
                    </div>
                </div>
                <div class="quote-field">
                    <label>Model</label>
                    <div class="value">
                        <input type="text" name="model" class="editable-input" value="<?php echo htmlspecialchars($quote['model'] ?? ''); ?>" placeholder="Vehicle model">
                    </div>
                </div>
                <div class="quote-field">
                    <label>VIN No.</label>
                    <div class="value">
                        <input type="text" name="vin_no" class="editable-input" value="<?php echo htmlspecialchars($quote['vin_no'] ?? ''); ?>" placeholder="VIN number">
                    </div>
                </div>
                <div class="quote-field">
                    <label>Kilometre</label>
                    <div class="value">
                        <input type="text" name="kilometre" class="editable-input" value="<?php echo htmlspecialchars($extra['kilometre'] ?? ''); ?>" placeholder="Kilometre reading">
                    </div>
                </div>
            </div>
        </div>

        <!-- DESCRIPTION (EDITABLE) -->
        <div class="quote-section">Customer Complaint / Work Required (Editable)</div>
        <div style="padding:8px 12px; border-bottom:2px solid #000;">
            <textarea name="complaint" class="editable-textarea" placeholder="Enter customer complaint or work required..."><?php echo htmlspecialchars($full_description); ?></textarea>
        </div>

        <!-- ITEMS & SERVICES (EDITABLE) -->
        <div class="quote-section">Items & Services (Editable)</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="qty-col">Qty</th>
                    <th class="price-col">Unit Price</th>
                    <th class="total-col">Total</th>
                    <th class="remove-col"></th>
                </tr>
            </thead>
            <tbody id="itemsBody">
                <?php foreach ($items as $index => $item): ?>
                <tr>
                    <td>
                        <select class="part-select" onchange="fillFromPart(this, <?php echo $index; ?>)">
                            <option value="">â€“ Select from inventory or type â€“</option>
                            <?php foreach ($inventory as $p): ?>
                                <option value="<?= $p['price'] ?>" data-name="<?= htmlspecialchars($p['part_name']) ?>">
                                    <?= htmlspecialchars($p['part_name']) ?> (Stock: <?= $p['stock'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="items[<?php echo $index; ?>][desc]" value="<?php echo htmlspecialchars($item['desc']); ?>" required placeholder="Item description">
                    </td>
                    <td class="qty-col">
                        <input type="number" name="items[<?php echo $index; ?>][qty]" class="qty" value="<?php echo $item['qty']; ?>" min="1" step="0.01" required onchange="calcLine(this)">
                    </td>
                    <td class="price-col">
                        <input type="number" name="items[<?php echo $index; ?>][price]" class="price" value="<?php echo $item['price']; ?>" step="0.01" min="0" required onchange="calcLine(this)">
                    </td>
                    <td class="total-col line-total"><?php echo $currency['symbol'] . number_format($item['qty'] * $item['price'], 2); ?></td>
                    <td class="remove-col">
                        <span class="remove-btn" onclick="this.closest('tr').remove();calcTotal();updateIndexes()">Ã—</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="button" class="add-btn" onclick="addRow()">
            <i class="fas fa-plus"></i> Add Another Item
        </button>

        <!-- TOTAL -->
        <div class="total-box">
            TOTAL AMOUNT: <span id="grandTotal"><?php echo $currency['symbol'] . number_format($quote['amount'], 2); ?></span>
        </div>

        <!-- NOTES -->
        <div class="notes-section">
            <label style="font-size:9px; font-weight:700; text-transform:uppercase; display:block; margin-bottom:4px;">Additional Notes / Terms</label>
            <textarea name="notes" placeholder="Valid for 30 days â€¢ 50% deposit required â€¢ Warranty: 3 months on parts"></textarea>
        </div>

    </div><!-- END QUOTATION DOCUMENT -->

    <!-- BUTTON BAR -->
    <div class="btn-bar">
        <a href="Quotation/view_quotation.php?id=<?php echo $quote['id']; ?>" class="btn btn-cancel">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" class="btn btn-save">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>

    </form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
let rowIndex = <?php echo count($items); ?>;
const symbol = '<?= $currency['symbol'] ?>';

function fillFromPart(select, index) {
    const row = select.closest('tr');
    const input = row.querySelector('input[name*="[desc]"]');
    if (select.value && select.value !== 'manual') {
        const option = select.selectedOptions[0];
        input.value = option.dataset.name;
        row.querySelector('.price').value = select.value;
    }
    calcLine(row.querySelector('.qty'));
}

function calcLine(input) {
    const row = input.closest('tr');
    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const price = parseFloat(row.querySelector('.price').value) || 0;
    const total = qty * price;
    row.querySelector('.line-total').textContent = symbol + total.toFixed(2);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('.line-total').forEach(el => {
        total += parseFloat(el.textContent.replace(/[^\d.-]/g, '')) || 0;
    });
    document.getElementById('grandTotal').textContent = symbol + total.toFixed(2);
}

function updateIndexes() {
    document.querySelectorAll('#itemsBody tr').forEach((tr, i) => {
        tr.querySelectorAll('input, select').forEach(input => {
            if (input.name) {
                input.name = input.name.replace(/\[\d+\]/, `[${i}]`);
            }
        });
    });
    rowIndex = document.querySelectorAll('#itemsBody tr').length;
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="part-select" onchange="fillFromPart(this, ${rowIndex})">
                <option value="">â€“ Select from inventory or type â€“</option>
                <?php foreach ($inventory as $p): ?>
                    <option value="<?= $p['price'] ?>" data-name="<?= htmlspecialchars($p['part_name']) ?>">
                        <?= htmlspecialchars($p['part_name']) ?> (Stock: <?= $p['stock'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="items[${rowIndex}][desc]" required placeholder="Item description">
        </td>
        <td class="qty-col">
            <input type="number" name="items[${rowIndex}][qty]" class="qty" value="1" min="1" step="0.01" required onchange="calcLine(this)">
        </td>
        <td class="price-col">
            <input type="number" name="items[${rowIndex}][price]" class="price" step="0.01" min="0" required onchange="calcLine(this)">
        </td>
        <td class="total-col line-total">${symbol}0.00</td>
        <td class="remove-col">
            <span class="remove-btn" onclick="this.closest('tr').remove();calcTotal();updateIndexes()">Ã—</span>
        </td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
}

// Initialize calculations
document.addEventListener('DOMContentLoaded', function() {
    calcTotal();
});
</script>


<script>
(function() {
    const PAGE_KEY = 'draft_' + window.location.pathname + window.location.search;
    const form = document.querySelector('form');
    if (!form) return;

    // Restore draft on page load
    const saved = localStorage.getItem(PAGE_KEY);
    if (saved) {
        try {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(name => {
                const fields = form.querySelectorAll(`[name="${name}"]`);
                fields.forEach(field => {
                    if (!field) return;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = data[name] === true || data[name] === field.value;
                    } else if (field.tagName === 'SELECT') {
                        field.value = data[name];
                        field.dispatchEvent(new Event('change'));
                    } else {
                        field.value = data[name];
                        field.dispatchEvent(new Event('input'));
                    }
                });
            });
            // Show restored notice
            const notice = document.createElement('div');
            notice.innerHTML = 'ðŸ“ <strong>Draft restored</strong> â€” your unsaved changes have been recovered.';
            notice.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#F7A100; color:white; padding:14px 22px; border-radius:12px; font-size:14px; z-index:99999; box-shadow:0 6px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;';
            const closeBtn = document.createElement('span');
            closeBtn.textContent = 'âœ•';
            closeBtn.style.cssText = 'cursor:pointer; margin-left:10px; font-size:16px; opacity:0.8;';
            closeBtn.onclick = () => notice.remove();
            notice.appendChild(closeBtn);
            document.body.appendChild(notice);
            setTimeout(() => notice.remove(), 5000);
        } catch(e) {}
    }

    // Auto-save on any input change
    let saveTimeout;
    function doSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const data = {};
            form.querySelectorAll('input, select, textarea').forEach(field => {
                if (!field.name) return;
                if (field.type === 'password' || field.type === 'file' || field.type === 'hidden') return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    data[field.name] = field.checked;
                } else {
                    data[field.name] = field.value;
                }
            });
            localStorage.setItem(PAGE_KEY, JSON.stringify(data));

            // Show saved indicator
            let indicator = document.getElementById('draft-save-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'draft-save-indicator';
                indicator.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#22C55E; color:white; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.15); opacity:0; transition:opacity 0.3s;';
                document.body.appendChild(indicator);
            }
            indicator.textContent = 'âœ“ Draft saved';
            indicator.style.opacity = '1';
            clearTimeout(indicator._hideTimer);
            indicator._hideTimer = setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
        }, 800);
    }

    form.addEventListener('input', doSave);
    form.addEventListener('change', doSave);

    // Clear draft on successful form submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(PAGE_KEY);
    });
})();
</script>

