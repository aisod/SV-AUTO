<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

// Generate next Job Card number
$last = $pdo->query("SELECT card_number FROM job_cards ORDER BY id DESC LIMIT 1")->fetchColumn();
$next = $last ? (int)substr($last, 3) + 1 : 1;
$card_number = 'JC-' . str_pad($next, 5, '0', STR_PAD_LEFT);

// Load data
$clients = $pdo->query("SELECT id, name, phone FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$vehicles = $pdo->query("SELECT v.id, v.reg_no, v.model, c.name AS client_name FROM vehicles v JOIN clients c ON v.client_id = c.id ORDER BY v.reg_no")->fetchAll(PDO::FETCH_ASSOC);
$technicians = $pdo->query("SELECT id, name FROM employees WHERE position = 'Technician' AND status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$employees = $pdo->query("SELECT id, name FROM employees WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/../sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Job Card • SV Auto Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root {
            --p: #F5A623;
            --s: #1a1a1a;
            --a: #FFF8EC;
            --t: #1a1a1a;
            --g: #2e7d32;
            --r: #c62828;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #fdfcfb 0%, #f9f0e6 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--t);
            min-height: 100vh;
        }
        .content-wrapper {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px;
        }
        .page-title {
            font-size: 48px;
            color: var(--s);
            margin: 40px 0 30px;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .page-title i {
            color: var(--p);
            font-size: 50px;
        }
        .form-card {
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(139, 69, 19, 0.2);
            border-top: 8px solid var(--p);
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            font-weight: 900;
            font-size: 16px;
            margin-bottom: 10px;
            display: block;
            color: var(--dark);
        }
        input, select, textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--a);
            border-radius: 8px;
            font-size: 16px;
            background: white;
            font-family: inherit;
            transition: all 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--p);
            box-shadow: 0 0 0 8px rgba(210, 105, 30, 0.1);
        }
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-row.full {
            grid-template-columns: 1fr;
        }
        .card-number {
            background: #f9f8f7;
            font-size: 24px;
            text-align: center;
            letter-spacing: 3px;
            color: var(--s);
            font-weight: 900;
            border: 3px solid var(--p);
            padding: 20px !important;
            border-radius: 10px;
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--p), var(--s));
            color: white;
            padding: 18px 40px;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 900;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(139, 69, 19, 0.4);
        }
        .btn-submit:active {
            transform: translateY(-1px);
        }
        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .hidden {
            display: none;
        }
        .section-title {
            font-size: 20px;
            font-weight: 900;
            color: var(--s);
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--p);
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            .page-title {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

<div class="content-wrapper">
    <h2 class="page-title">
        <i class="fas fa-file-invoice-dollar"></i> CREATE NEW JOB CARD
    </h2>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="create_job_card.php" id="jobCardForm">
            
            <!-- Card Number -->
            <div class="form-group">
                <label>Job Card Number</label>
                <input type="text" value="<?php echo $card_number; ?>" readonly class="card-number">
                <input type="hidden" name="card_number" value="<?php echo $card_number; ?>">
            </div>

            <!-- Client Section -->
            <div class="section-title">Client Information</div>
            <div class="form-group">
                <label>Select Client *</label>
                <select name="client_id" required>
                    <option value="">-- Choose Client --</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?php echo $c['id']; ?>">
                            <?php echo htmlspecialchars($c['name'] . ' (' . $c['phone'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Vehicle Section -->
            <div class="section-title">Vehicle Information</div>
            <div class="form-group">
                <label>Select Vehicle *</label>
                <select name="vehicle_id" required>
                    <option value="">-- Choose Vehicle --</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?php echo $v['id']; ?>">
                            <?php echo htmlspecialchars($v['reg_no'] . ' • ' . $v['model'] . ' (' . $v['client_name'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Technician Section -->
            <div class="section-title">Technician Assignment</div>
            <div class="form-group">
                <label>Assign Technician *</label>
                <select name="technician_id" required>
                    <option value="">-- Choose Technician --</option>
                    <?php foreach ($technicians as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            <?php echo htmlspecialchars($t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Parts Requester -->
            <div class="form-group">
                <label>Parts Requester *</label>
                <select name="requester_id" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>">
                            <?php echo htmlspecialchars($emp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Job Details Section -->
            <div class="section-title">Job Details</div>
            <div class="form-group">
                <label>Job Description / Customer Complaint *</label>
                <textarea name="job_description" required placeholder="Describe the work required..."></textarea>
            </div>

            <div class="form-group">
                <label>Parts Required / Supplied</label>
                <textarea name="parts_supply" placeholder="List parts needed or supplied by client..."></textarea>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i>
                CREATE JOB CARD
                <i class="fas fa-arrow-right"></i>
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('jobCardForm').addEventListener('submit', function(e) {
    console.log('Form submitted');
    
    // Basic validation
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.style.borderColor = '#c62828';
            console.log('Missing field:', field.name);
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        alert('Please fill in all required fields (marked with *)');
        return false;
    }
    
    console.log('Form is valid, submitting...');
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

</body>
</html>
