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

// ============== PROCESS FORM SUBMISSION ==============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $part_name = trim($_POST['part_name'] ?? '');
    $stock     = (int)($_POST['stock'] ?? 0);
    $price     = (float)($_POST['price'] ?? 0);

    if (empty($part_name)) {
        $error = "Part name is required.";
    } elseif ($stock < 0) {
        $error = "Stock cannot be negative.";
    } elseif ($price < 0) {
        $error = "Price cannot be negative.";
    } else {
        try {
            // Prevent duplicate parts (case-insensitive)
            $check = $pdo->prepare("SELECT id FROM inventory WHERE LOWER(part_name) = LOWER(?)");
            $check->execute([$part_name]);
            if ($check->fetch()) {
                $error = "Part '$part_name' already exists!";
            } else {
                // ONLY INSERT COLUMNS THAT EXIST IN YOUR TABLE
                $stmt = $pdo->prepare("INSERT INTO inventory (part_name, stock, price) VALUES (?, ?, ?)");
                $stmt->execute([$part_name, $stock, $price]);

                header('Location: inventory.php?success=' . urlencode("'$part_name' added successfully!"));
                exit;
            }
        } catch (PDOException $e) {
            // Show actual SQL error in development (remove in production)
            error_log("Add inventory error: " . $e->getMessage());
            $error = "Database error: " . $e->getMessage(); // ← Remove this line in production
        }
    }
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="inventory.php">Inventory</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Add Part</span>
    </div>
    <div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
        <h1 class="erp-page-title">Add New Inventory Item</h1>
    </div>
</div>

<form method="POST">

<!-- INVENTORY DOCUMENT SHELL -->
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
        <div style="font-size:22px; font-weight:900; letter-spacing:5px; text-transform:uppercase;">NEW INVENTORY ITEM</div>
        <div><i class="fas fa-box" style="color:#F7A100; font-size:22px;"></i></div>
    </div>

    <!-- ERROR ALERT -->
    <?php if (isset($error)): ?>
        <div style="padding:12px 16px;">
            <div class="erp-alert erp-alert-danger erp-mb-4">
                <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="erp-alert-content">
                    <div class="erp-alert-title">Error</div>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- FORM FIELDS -->
    <div style="padding:12px 16px; border-bottom:2px solid #000;">
        <div style="display:grid; grid-template-columns:1fr; gap:16px; margin-bottom:16px;">
            <div>
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Part Name</label>
                <input type="text" name="part_name" id="part_name" required placeholder="e.g. Bosch Aerotwin Wiper Blades"
                    value="<?php echo htmlspecialchars($_POST['part_name'] ?? ''); ?>"
                    style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px 6px; font-size:13px; font-weight:600; background:transparent; outline:none; box-sizing:border-box;">
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Initial Stock</label>
                <input type="number" name="stock" id="stock" required min="0" value="<?php echo $_POST['stock'] ?? '0'; ?>"
                    style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px 6px; font-size:13px; font-weight:600; background:transparent; outline:none; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:10px; font-weight:700; text-transform:uppercase; color:#555; display:block; margin-bottom:6px; letter-spacing:1px;">Unit Price (<?= $currency['symbol'] ?>)</label>
                <input type="number" name="price" id="price" step="0.01" required min="0" placeholder="0.00"
                    value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                    style="width:100%; border:none; border-bottom:1.5px solid #000; padding:6px 6px; font-size:13px; font-weight:600; background:transparent; outline:none; box-sizing:border-box;">
            </div>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div style="padding:16px; display:flex; justify-content:space-between; align-items:center; background:#fafafa; border-top:2px solid #000;">
        <a href="inventory.php" style="background:#6B7280; color:white; padding:10px 20px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:700; display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-times"></i> Cancel
        </a>
        <button type="submit" style="background:#F7A100; color:white; border:none; border-radius:8px; padding:10px 28px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(247,161,0,0.4);">
            <i class="fas fa-save"></i> Add to Inventory
        </button>
    </div>

</div>
</form>


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
