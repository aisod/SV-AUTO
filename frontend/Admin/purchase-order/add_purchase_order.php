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

// Fetch all job cards that can have POs (must have approved quotation)
$stmt = $pdo->query("
    SELECT 
        jc.id,
        jc.card_number,
        c.name AS client_name,
        COALESCE(v.reg_no, 'Walk-in') AS reg_no,
        v.model,
        q.details,
        q.amount
    FROM job_cards jc
    JOIN quotations q ON jc.id = q.job_card_id
    JOIN clients c ON q.client_id = c.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE q.status = 'approved'
    ORDER BY jc.id DESC
");
$job_cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to JSON for JavaScript
$job_cards_json = json_encode($job_cards);

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <h1 class="erp-page-title">Create New Purchase Order</h1>
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

<form action="create_purchase_order.php" method="POST">

<!-- PURCHASE ORDER DOCUMENT SHELL -->
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
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">NEW PURCHASE ORDER</div>
        <div><i class="fas fa-file-invoice" style="color:#F7A100; font-size:22px;"></i></div>
    </div>

    <!-- JOB CARD SELECTION ROW -->
    <div style="padding:12px 16px; border-bottom:2px solid #000; background:#FFF8F0;">
        <div style="display:flex; align-items:center; gap:16px;">
            <label style="font-size:11px; font-weight:700; text-transform:uppercase; color:#333; white-space:nowrap; min-width:120px;">
                <i class="fas fa-clipboard" style="color:#F7A100;"></i> Select Job Card
            </label>
            <select name="job_card_id" id="job_card_id" required onchange="updateJobCardInfo()"
                style="flex:1; border:1.5px solid #F7A100; border-radius:6px; padding:8px 12px; font-size:13px; font-weight:600; background:white; outline:none;">
                <option value="">-- Select Job Card --</option>
                <?php foreach ($job_cards as $jc): ?>
                <option value="<?php echo $jc['id']; ?>">
                    <?php echo htmlspecialchars("{$jc['card_number']} • {$jc['client_name']} • {$jc['reg_no']} • {$jc['model']}"); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Job Card Info Preview -->
        <div id="jobCardInfo" style="display:none; margin-top:12px; background:white; border:1px solid #F7A100; border-radius:8px; padding:12px 16px;">
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Job Card</span>
                    <span id="selectedCard" style="font-size:14px; font-weight:700; color:#1A1A1A;">—</span>
                </div>
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Client</span>
                    <span id="selectedClient" style="font-size:14px; font-weight:700; color:#1A1A1A;">—</span>
                </div>
                <div>
                    <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#888; display:block; margin-bottom:4px;">Vehicle</span>
                    <span id="selectedVehicle" style="font-size:14px; font-weight:700; color:#1A1A1A;">—</span>
                </div>
            </div>
        </div>
    </div>

    <!-- PARTS PREVIEW SECTION -->
    <div id="partsPreview" style="display:none; border-bottom:2px solid #000;">
        <div style="text-align:center; font-size:10px; font-weight:900; letter-spacing:4px; text-transform:uppercase; background:#f0f0f0; border-bottom:1px solid #000; padding:5px 0;">Parts & Services from Quotation</div>
        <div style="padding:0;">
            <table style="width:100%; border-collapse:collapse;" id="partsList">
                <tbody></tbody>
            </table>
            <div style="background:#F7A100; color:white; padding:12px 16px; text-align:right; font-size:16px; font-weight:700;" id="totalAmount"></div>
        </div>
    </div>

    <!-- FORM FIELDS -->
    <div style="padding:16px; border-bottom:2px solid #000;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div>
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Supplier Name</label>
                <input type="text" name="supplier_name" placeholder="e.g. Auto Parts Namibia, Windhoek Tyres Ltd" required
                    style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px; font-size:13px; font-weight:600; background:transparent; outline:none; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Amount (N$)</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0.01" placeholder="0.00" required readonly
                    style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px; font-size:13px; font-weight:600; background:#f5f5f5; outline:none; box-sizing:border-box;">
            </div>
        </div>

        <input type="hidden" name="details" id="details">

        <div style="margin-top:20px;">
            <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Additional Notes (Optional)</label>
            <textarea name="notes" rows="3" placeholder="Any special instructions..."
                style="width:100%; border:1px solid #ddd; border-radius:4px; padding:8px; font-size:13px; font-family:Arial,sans-serif; resize:vertical; box-sizing:border-box;"></textarea>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
        <a href="purchase_orders.php" style="background:#6B7280; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" style="background:#F7A100; color:white; border:none; border-radius:8px; padding:10px 28px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(247,161,0,0.4);">
            <i class="fas fa-check-circle"></i> Create Purchase Order
        </button>
    </div>

</div>
</form>

<script>
const jobCardsData = <?= $job_cards_json ?>;

function updateJobCardInfo() {
    const select = document.getElementById('job_card_id');
    const infoBox = document.getElementById('jobCardInfo');
    const partsPreview = document.getElementById('partsPreview');
    const selected = select.options[select.selectedIndex];

    if (!selected || !selected.value) {
        infoBox.style.display = 'none';
        partsPreview.style.display = 'none';
        return;
    }

    const jobCardId = parseInt(selected.value);
    const jobCard = jobCardsData.find(jc => jc.id === jobCardId);

    if (!jobCard) return;

    // Update job card info
    const text = selected.text;
    const parts = text.split(' • ');
    
    document.getElementById('selectedCard').textContent = parts[0] || '—';
    document.getElementById('selectedClient').textContent = parts[1] || '—';
    document.getElementById('selectedVehicle').textContent = (parts[2] || '') + (parts[3] ? ' • ' + parts[3] : '');
    infoBox.style.display = 'block';

    // Parse quotation details and display parts
    const details = jobCard.details || '';
    const amount = parseFloat(jobCard.amount) || 0;
    
    const items = parseQuotationDetails(details);
    displayParts(items, amount, details);
}

function parseQuotationDetails(details) {
    const items = [];
    let currentItem = null;
    
    const lines = details.split('\n');
    for (let line of lines) {
        line = line.trim();
        if (!line || line.includes('<strong>') || line.includes('═')) {
            if (line.includes('TOTAL:') && currentItem) {
                items.push(currentItem);
                currentItem = null;
            }
            continue;
        }
        
        const firstChar = line.charAt(0);
        if (firstChar === '•' || line.charCodeAt(0) > 127) {
            if (currentItem) items.push(currentItem);
            currentItem = {
                desc: line.substring(1).trim(),
                amount: ''
            };
        } else if (currentItem && line.match(/N\$\s*[0-9,.]+/)) {
            currentItem.amount = line;
        }
    }
    if (currentItem) items.push(currentItem);
    
    return items;
}

function displayParts(items, totalAmount, rawDetails) {
    const partsList = document.querySelector('#partsList tbody');
    const totalAmountDiv = document.getElementById('totalAmount');
    const partsPreview = document.getElementById('partsPreview');
    const amountInput = document.getElementById('amount');
    const detailsInput = document.getElementById('details');

    if (items.length === 0) {
        partsList.innerHTML = '<tr><td colspan="2" class="erp-empty-state">No itemized parts found in quotation</td></tr>';
    } else {
        partsList.innerHTML = items.map(item => `
            <tr>
                <td>${escapeHtml(item.desc)}</td>
                <td style="text-align: right; font-weight: 600; color: var(--brand-primary);">${escapeHtml(item.amount)}</td>
            </tr>
        `).join('');
    }

    totalAmountDiv.textContent = `TOTAL: N$ ${totalAmount.toFixed(2)}`;
    amountInput.value = totalAmount.toFixed(2);
    detailsInput.value = rawDetails;
    partsPreview.style.display = 'block';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.erp-alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
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
