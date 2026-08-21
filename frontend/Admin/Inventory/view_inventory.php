<?php
// view_inventory.php — Professional Inventory Item Detail View
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$business = getBusiness();
$currency = getCurrency();

// Get item ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: inventory.php?error=' . urlencode('Invalid part ID.'));
    exit;
}

$item_id = (int)$_GET['id'];

// Fetch inventory item
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: inventory.php?error=' . urlencode('Part not found.'));
    exit;
}

// Calculate stock status
$stock = (int)$item['stock'];
if ($stock == 0) {
    $status = 'Out of Stock';
    $status_color = '#c62828';
} elseif ($stock < 10) {
    $status = 'Critical';
    $status_color = '#f57c00';
} elseif ($stock < 25) {
    $status = 'Low Stock';
    $status_color = '#f57c00';
} else {
    $status = 'In Stock';
    $status_color = '#2e7d32';
}

$stock_value = $stock * (float)$item['price'];
include __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
    <h1 class="erp-page-title">Inventory Item Details</h1>
    <div class="erp-page-actions">
        <a href="edit_inventory.php?id=<?php echo $item_id; ?>" class="erp-btn erp-btn-primary">
            <i class="fas fa-edit"></i> Edit
        </a>
    </div>
</div>

<!-- Inventory Item Card -->
<div class="erp-card erp-mb-4">
    <div class="erp-card-header erp-flex erp-justify-between erp-align-center">
        <h2 class="erp-card-title">
            <i class="fas fa-box"></i> <?php echo htmlspecialchars($item['part_name']); ?>
        </h2>
        <span class="erp-badge" style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>;">
            <?php echo $status; ?>
        </span>
    </div>
    <div class="erp-card-body">
        <!-- Main Info Grid -->
        <div class="erp-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <!-- Part Name -->
            <div class="erp-card" style="background: var(--gray-50);">
                <div class="erp-card-body">
                    <div class="erp-label" style="font-size: 12px; margin-bottom: 8px;">Part Name</div>
                    <div style="font-size: 18px; font-weight: 600; color: var(--gray-800);"><?php echo htmlspecialchars($item['part_name']); ?></div>
                </div>
            </div>

            <!-- Category -->
            <div class="erp-card" style="background: var(--gray-50);">
                <div class="erp-card-body">
                    <div class="erp-label" style="font-size: 12px; margin-bottom: 8px;">Category</div>
                    <div style="font-size: 18px; font-weight: 600; color: var(--gray-800);"><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></div>
                </div>
            </div>

            <!-- Unit Price -->
            <div class="erp-card" style="background: var(--gray-50);">
                <div class="erp-card-body">
                    <div class="erp-label" style="font-size: 12px; margin-bottom: 8px;">Unit Price</div>
                    <div style="font-size: 18px; font-weight: 600; color: var(--brand-primary);"><?php echo formatMoney($item['price']); ?></div>
                </div>
            </div>
        </div>

        <!-- Stock Information -->
        <div class="erp-card" style="background: var(--gray-50); margin-bottom: 20px;">
            <div class="erp-card-header">
                <h3 class="erp-card-title" style="font-size: 16px;"><i class="fas fa-warehouse"></i> Stock Information</h3>
            </div>
            <div class="erp-card-body">
                <div class="erp-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                        <div class="erp-label" style="font-size: 12px; margin-bottom: 10px;">Quantity in Stock</div>
                        <div style="font-size: 32px; font-weight: 700; color: var(--gray-800);"><?php echo $stock; ?></div>
                        <div style="font-size: 12px; color: var(--gray-500);">units</div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                        <div class="erp-label" style="font-size: 12px; margin-bottom: 10px;">Status</div>
                        <span class="erp-badge" style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>; padding: 8px 16px;">
                            <?php echo $status; ?>
                        </span>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                        <div class="erp-label" style="font-size: 12px; margin-bottom: 10px;">Stock Value</div>
                        <div style="font-size: 24px; font-weight: 700; color: var(--brand-primary);"><?php echo formatMoney($stock_value); ?></div>
                    </div>
                    <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                        <div class="erp-label" style="font-size: 12px; margin-bottom: 10px;">Total Items</div>
                        <div style="font-size: 24px; font-weight: 700; color: var(--gray-800);"><?php echo number_format($stock); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Details -->
        <div class="erp-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
            <div class="erp-card" style="background: var(--gray-50);">
                <div class="erp-card-body">
                    <div class="erp-label" style="font-size: 12px; margin-bottom: 8px;">Part Number</div>
                    <div style="font-size: 14px; color: var(--gray-700);"><?php echo htmlspecialchars($item['part_number'] ?? 'N/A'); ?></div>
                </div>
            </div>

            <div class="erp-card" style="background: var(--gray-50);">
                <div class="erp-card-body">
                    <div class="erp-label" style="font-size: 12px; margin-bottom: 8px;">Supplier</div>
                    <div style="font-size: 14px; color: var(--gray-700);"><?php echo htmlspecialchars($item['supplier'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="erp-card" style="background: var(--gray-50);">
            <div class="erp-card-header">
                <h3 class="erp-card-title" style="font-size: 16px;"><i class="fas fa-align-left"></i> Description</h3>
            </div>
            <div class="erp-card-body">
                <div style="color: var(--gray-700); line-height: 1.8;">
                    <?php 
                    echo !empty($item['description']) 
                        ? nl2br(htmlspecialchars($item['description'])) 
                        : '<span style="color: var(--gray-400); font-style: italic;">No description provided</span>'; 
                    ?>
                </div>
            </div>
        </div>

        <!-- Bottom Actions -->
        <div class="erp-flex erp-justify-center erp-gap-3 erp-mt-4 erp-mb-4">
            <a href="edit_inventory.php?id=<?php echo $item_id; ?>" class="erp-btn erp-btn-primary">
                <i class="fas fa-edit"></i> Edit Item
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

