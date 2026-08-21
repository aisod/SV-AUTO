<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$business = getBusiness();
$currency = getCurrency();

// Fetch all clients + their vehicles
$stmt = $pdo->query("
    SELECT 
        c.id AS client_id,
        c.name AS client_name,
        v.id AS vehicle_id,
        v.reg_no,
        v.model,
        v.last_service_date
    FROM clients c
    LEFT JOIN vehicles v ON c.id = v.client_id
    ORDER BY c.name, v.reg_no
");
$clients_vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="services_update.php">Services Update</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Schedule Service</span>
    </div>
    <h1 class="erp-page-title">Schedule New Service</h1>
</div>

<!-- Alert Messages -->
<?php if ($success): ?>
    <div class="erp-alert erp-alert-success erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-check-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Success</div>
            <?php echo htmlspecialchars($success); ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="erp-alert erp-alert-danger erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Error</div>
            <?php echo htmlspecialchars($error); ?>
        </div>
    </div>
<?php endif; ?>

<form action="create_service.php" method="POST">

<!-- SERVICE DOCUMENT SHELL -->
<div style="background:white; border:2px solid #000; max-width:1000px; margin:0 auto 20px; font-family:Arial,sans-serif;">

    <!-- DOCUMENT HEADER -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; padding:12px 16px; border-bottom:2px solid #000;">
        <div>
            <?php $logoPath = '../assets/images/companylogo.jpeg'; if (file_exists($logoPath)): ?>
                <img src="<?php echo $logoPath . '?v=' . time(); ?>" style="height:55px; width:auto;">
            <?php endif; ?>
        </div>
        <div style="text-align:right; font-size:11px; line-height:1.7; color:#222;">
            <?php echo htmlspecialchars($business['address'] ?? 'Lafrenz Industrial • Rensburger Street'); ?><br>
            Cell: <?php echo htmlspecialchars($business['phone'] ?? ''); ?> •
            Email: <?php echo htmlspecialchars($business['email'] ?? ''); ?><br>
            PO Box 21292 • Windhoek • Namibia<br>
            Reg No: <?php echo htmlspecialchars($business['tax_number'] ?? ''); ?>
        </div>
    </div>

    <!-- TITLE BAR -->
    <div style="display:flex; justify-content:space-between; align-items:center; background:#f5f5f5; border-bottom:2px solid #000; padding:8px 16px;">
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">NEW SERVICE SCHEDULE</div>
        <div><i class="fas fa-calendar-plus" style="color:#F7A100; font-size:22px;"></i></div>
    </div>

    <!-- CLIENT & VEHICLE SELECTION ROW -->
    <div style="padding:12px 16px; border-bottom:2px solid #000; background:#FFF8F0;">
        <div style="display:flex; align-items:center; gap:16px; margin-bottom:12px;">
            <label style="font-size:11px; font-weight:700; text-transform:uppercase; color:#333; white-space:nowrap;">
                <i class="fas fa-car" style="color:#F7A100;"></i> Client & Vehicle
            </label>
        </div>
        <select name="client_vehicle" id="client_vehicle" required onchange="updateVehicles()"
            style="width:100%; border:1.5px solid #F7A100; border-radius:6px; padding:8px 12px; font-size:13px; font-weight:600; background:white; outline:none;">
            <option value="">-- Select Client --</option>
            <?php
            $current_client = 0;
            foreach ($clients_vehicles as $row):
                if ($row['client_id'] != $current_client):
                    if ($current_client != 0) echo "</optgroup>";
                    echo "<optgroup label='" . htmlspecialchars($row['client_name']) . "'>";
                    $current_client = $row['client_id'];
                endif;
                if ($row['vehicle_id']):
            ?>
                <option value="<?php echo $row['client_id']; ?>_<?php echo $row['vehicle_id']; ?>">
                    <?php echo htmlspecialchars($row['reg_no']); ?> • <?php echo htmlspecialchars($row['model']); ?>
                    <?php echo $row['last_service_date'] ? " • Last: " . date('d M Y', strtotime($row['last_service_date'])) : " • New Vehicle"; ?>
                </option>
            <?php
                endif;
            endforeach;
            if ($current_client != 0) echo "</optgroup>";
            ?>
        </select>
        <input type="hidden" name="client_id" id="hidden_client_id">
        <input type="hidden" name="vehicle_id" id="hidden_vehicle_id">

        <!-- Vehicle Info Preview -->
        <div id="vehicleInfo" style="display:none; margin-top:12px; background:white; border:1px solid #F7A100; border-radius:8px; padding:12px 16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Vehicle Reg</span>
                    <span id="selectedReg" style="font-size:14px; font-weight:700; color:#1A1A1A;">—</span>
                </div>
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Model</span>
                    <span id="selectedModel" style="font-size:14px; font-weight:700; color:#1A1A1A;">—</span>
                </div>
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Last Service</span>
                    <span id="lastService" style="font-size:14px; font-weight:700; color:#1A1A1A;">—</span>
                </div>
            </div>
        </div>
    </div>

    <!-- TWO-COLUMN FIELDS SECTION -->
    <div style="display:grid; grid-template-columns:1fr 1fr; border-bottom:2px solid #000;">
        <!-- LEFT: SERVICE DETAILS -->
        <div style="border-right:2px solid #000; padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">SERVICE DETAILS</div>
            <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Next Service Date</label>
            <input type="date" name="service_date" required
                min="<?php echo date('Y-m-d'); ?>"
                value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>"
                style="width:100%; border:none; border-bottom:1.5px solid #000; padding:4px 6px; font-size:13px; font-weight:600; background:transparent; outline:none;">
        </div>
        <!-- RIGHT: NOTES -->
        <div style="padding:12px 16px;">
            <div style="font-size:9px; font-weight:900; text-transform:uppercase; color:#555; margin-bottom:10px; letter-spacing:1px;">NOTES / RECOMMENDED SERVICES</div>
            <textarea name="notes" rows="4"
                placeholder="e.g. Full 60,000 km service • Replace timing belt • Oil change..."
                style="width:100%; border:1px solid #ddd; border-radius:4px; padding:8px; font-size:13px; font-family:Arial,sans-serif; resize:vertical; box-sizing:border-box;"></textarea>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
        <a href="services_update.php" style="background:#6B7280; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" style="background:#F7A100; color:white; border:none; border-radius:8px; padding:10px 28px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(247,161,0,0.4);">
            <i class="fas fa-calendar-plus"></i> Schedule Service
        </button>
    </div>

</div>
</form>

<script>
function updateVehicles() {
    const select = document.getElementById('client_vehicle');
    const infoBox = document.getElementById('vehicleInfo');
    const selected = select.options[select.selectedIndex];

    if (!selected || !selected.value) {
        infoBox.style.display = 'none';
        document.getElementById('hidden_client_id').value = '';
        document.getElementById('hidden_vehicle_id').value = '';
        return;
    }

    // Split the combined value "clientId_vehicleId"
    const parts = selected.value.split('_');
    const clientId = parts[0];
    const vehicleId = parts[1];

    // Set hidden fields
    document.getElementById('hidden_client_id').value = clientId;
    document.getElementById('hidden_vehicle_id').value = vehicleId;

    // Update info box
    const textParts = selected.text.split(' • ');
    const reg = textParts[0] || '—';
    const model = textParts[1] || '—';
    const last = textParts[2] ? textParts[2].replace('Last: ', '') : 'Never';

    document.getElementById('selectedReg').textContent = reg;
    document.getElementById('selectedModel').textContent = model;
    document.getElementById('lastService').textContent = last;

    infoBox.style.display = 'flex';
}
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
            notice.innerHTML = '📝 <strong>Draft restored</strong> — your unsaved changes have been recovered.';
            notice.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#F7A100; color:white; padding:14px 22px; border-radius:12px; font-size:14px; z-index:99999; box-shadow:0 6px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;';
            const closeBtn = document.createElement('span');
            closeBtn.textContent = '✕';
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
            indicator.textContent = '✓ Draft saved';
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
