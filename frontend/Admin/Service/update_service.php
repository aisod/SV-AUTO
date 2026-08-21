<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: job_card.php?error=Invalid job card ID');
    exit;
}

$job_card_id = (int)$_GET['id'];

// Load job card details
$stmt = $pdo->prepare("
    SELECT jc.*, v.reg_no, v.model, c.name AS client_name, c.phone AS client_phone
    FROM job_cards jc
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    LEFT JOIN clients c ON v.client_id = c.id
    WHERE jc.id = ?
");
$stmt->execute([$job_card_id]);
$job_card = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job_card) {
    header('Location: job_card.php?error=Job card not found');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? $job_card['status'];
    $progress = (int)($_POST['progress'] ?? 0);
    $labor_hours = (float)($_POST['labor_hours'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $update_type = $_POST['update_type'] ?? 'progress';
    
    try {
        $pdo->beginTransaction();
        
        // Update job card
        $update_fields = [];
        $update_values = [];
        
        $update_fields[] = "status = ?";
        $update_values[] = $status;
        
        $update_fields[] = "progress = ?";
        $update_values[] = $progress;
        
        $update_fields[] = "labor_hours = labor_hours + ?";
        $update_values[] = $labor_hours;
        
        if (!empty($notes)) {
            $update_fields[] = "technician_notes = CONCAT(COALESCE(technician_notes, ''), ?)";
            $update_values[] = "\n[" . date('Y-m-d H:i') . "] " . $notes;
        }
        
        if ($status === 'in_progress' && empty($job_card['started_at'])) {
            $update_fields[] = "started_at = NOW()";
        }
        
        if ($status === 'completed' && empty($job_card['completed_at'])) {
            $update_fields[] = "completed_at = NOW()";
            $update_fields[] = "progress = 100";
        }
        
        $update_values[] = $job_card_id;
        
        $sql = "UPDATE job_cards SET " . implode(", ", $update_fields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($update_values);
        
        // Log service update
        $log_stmt = $pdo->prepare("
            INSERT INTO service_updates (job_card_id, technician_id, update_type, description, progress, labor_hours)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->execute([
            $job_card_id,
            $_SESSION['user_id'],
            $update_type,
            $notes,
            $progress,
            $labor_hours
        ]);
        
        // Audit log
        $audit = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)");
        $audit->execute([$_SESSION['user_id'], 'updated_service', 'job_card', $job_card_id]);
        
        $pdo->commit();
        
        header("Location: view_job_card.php?id=$job_card_id&success=Service updated successfully");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to update service: " . $e->getMessage();
    }
}

// Load service update history
$history_stmt = $pdo->prepare("
    SELECT su.*, u.username
    FROM service_updates su
    LEFT JOIN users u ON su.technician_id = u.id
    WHERE su.job_card_id = ?
    ORDER BY su.created_at DESC
");
$history_stmt->execute([$job_card_id]);
$history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

$business = getBusiness();

include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="job_card.php">Job Cards</a>
        <span class="erp-breadcrumb-separator">/</span>
        <a href="view_job_card.php?id=<?= $job_card_id ?>">View Job Card</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Update Service</span>
    </div>
    <h1 class="erp-page-title"><i class="fas fa-wrench"></i> Update Service Progress</h1>
</div>

<!-- Alert Messages -->
<?php if (isset($error)): ?>
    <div class="erp-alert erp-alert-danger erp-mb-4">
        <div class="erp-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="erp-alert-content">
            <div class="erp-alert-title">Error</div>
            <?= htmlspecialchars($error) ?>
        </div>
    </div>
<?php endif; ?>

<!-- Job Card Information -->
<div class="erp-card erp-mb-4">
    <div class="erp-card-header">
        <h2 class="erp-card-title"><i class="fas fa-info-circle"></i> Job Card Information</h2>
    </div>
    <div class="erp-card-body">
        <div class="erp-grid erp-grid-cols-3 erp-gap-4">
            <div class="erp-info-box">
                <div class="erp-info-label"><i class="fas fa-file-alt"></i> Job Card Number</div>
                <div class="erp-info-value"><?= htmlspecialchars($job_card['card_number']) ?></div>
            </div>
            <div class="erp-info-box">
                <div class="erp-info-label"><i class="fas fa-car"></i> Vehicle</div>
                <div class="erp-info-value"><?= htmlspecialchars($job_card['reg_no']) ?> - <?= htmlspecialchars($job_card['model']) ?></div>
            </div>
            <div class="erp-info-box">
                <div class="erp-info-label"><i class="fas fa-user"></i> Client</div>
                <div class="erp-info-value"><?= htmlspecialchars($job_card['client_name']) ?></div>
            </div>
        </div>
        
        <div class="erp-mt-4">
            <div class="erp-info-label erp-mb-2">
                <i class="fas fa-tasks"></i> Current Progress: <strong style="color: var(--primary); font-size: 18px;"><?= $job_card['progress'] ?>%</strong>
            </div>
            <div class="erp-progress">
                <div class="erp-progress-bar" style="width: <?= $job_card['progress'] ?>%">
                    <?= $job_card['progress'] ?>%
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update Form -->
<div class="erp-card erp-mb-4">
    <div class="erp-card-header">
        <h2 class="erp-card-title"><i class="fas fa-edit"></i> Update Service</h2>
    </div>
    <div class="erp-card-body">
        <form method="POST">
            <div class="erp-grid erp-grid-cols-2 erp-gap-4">
                <div class="erp-form-group">
                    <label class="erp-label"><i class="fas fa-flag"></i> Status</label>
                    <select name="status" class="erp-input" required>
                        <option value="new" <?= $job_card['status']==='new'?'selected':'' ?>>New</option>
                        <option value="diagnosed" <?= $job_card['status']==='diagnosed'?'selected':'' ?>>Diagnosed</option>
                        <option value="quoted" <?= $job_card['status']==='quoted'?'selected':'' ?>>Quoted</option>
                        <option value="approved" <?= $job_card['status']==='approved'?'selected':'' ?>>Approved</option>
                        <option value="in_progress" <?= $job_card['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                        <option value="waiting_parts" <?= $job_card['status']==='waiting_parts'?'selected':'' ?>>Waiting Parts</option>
                        <option value="completed" <?= $job_card['status']==='completed'?'selected':'' ?>>Completed</option>
                    </select>
                </div>

                <div class="erp-form-group">
                    <label class="erp-label"><i class="fas fa-percentage"></i> Progress (%)</label>
                    <input type="number" name="progress" class="erp-input" min="0" max="100" value="<?= $job_card['progress'] ?>" required>
                </div>

                <div class="erp-form-group">
                    <label class="erp-label"><i class="fas fa-clock"></i> Labor Hours (Add to total)</label>
                    <input type="number" name="labor_hours" class="erp-input" step="0.5" min="0" value="0">
                    <small class="erp-help-text"><i class="fas fa-info-circle"></i> Current total: <?= $job_card['labor_hours'] ?> hours</small>
                </div>

                <div class="erp-form-group">
                    <label class="erp-label"><i class="fas fa-tag"></i> Update Type</label>
                    <select name="update_type" class="erp-input">
                        <option value="progress">Progress Update</option>
                        <option value="diagnosis">Diagnosis</option>
                        <option value="parts_added">Parts Added</option>
                        <option value="completed">Work Completed</option>
                        <option value="note">General Note</option>
                    </select>
                </div>
            </div>

            <div class="erp-form-group erp-mt-4">
                <label class="erp-label"><i class="fas fa-sticky-note"></i> Notes</label>
                <textarea name="notes" class="erp-input" rows="4" placeholder="Enter work notes, findings, or updates..."></textarea>
            </div>

            <div class="erp-form-actions erp-mt-6">
                <a href="view_job_card.php?id=<?= $job_card_id ?>" class="erp-btn erp-btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="erp-btn erp-btn-primary">
                    <i class="fas fa-save"></i> Save Update
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update History -->
<?php if (!empty($history)): ?>
<div class="erp-card">
    <div class="erp-card-header">
        <h2 class="erp-card-title"><i class="fas fa-history"></i> Update History</h2>
    </div>
    <div class="erp-card-body">
        <?php foreach ($history as $item): ?>
        <div class="erp-timeline-item erp-mb-4">
            <div class="erp-timeline-header">
                <span class="erp-badge erp-badge-primary"><?= strtoupper(str_replace('_', ' ', $item['update_type'])) ?></span>
                <span class="erp-timeline-time">
                    <i class="fas fa-calendar-alt"></i> <?= date('d M Y H:i', strtotime($item['created_at'])) ?> by <?= htmlspecialchars($item['username']) ?>
                </span>
            </div>
            <?php if ($item['description']): ?>
                <div class="erp-timeline-content"><?= nl2br(htmlspecialchars($item['description'])) ?></div>
            <?php endif; ?>
            <div class="erp-timeline-meta">
                <span><i class="fas fa-tasks"></i> Progress: <?= $item['progress'] ?>%</span>
                <span><i class="fas fa-clock"></i> Labor: <?= $item['labor_hours'] ?> hrs</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

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

