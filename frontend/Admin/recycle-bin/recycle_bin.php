<?php
/**
 * Recycle Bin — restore or permanently delete soft-deleted records.
 */
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['restore', 'purge'], true)) {
    $rb_bulk_handler = __DIR__ . '/rb_bulk_action.php';
    if (is_file($rb_bulk_handler)) {
        require $rb_bulk_handler;
    }
    require __DIR__ . '/rb_bulk_action.inc.php';
}

$success = trim((string) ($_GET['success'] ?? ''));
$error = trim((string) ($_GET['error'] ?? ''));
$tab = strtolower(trim((string) ($_GET['tab'] ?? 'all')));
$validTabs = ['all', 'job_cards', 'quotations', 'expenses', 'employees', 'inventory', 'statutory'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'all';
}

$rb_type_meta = [
    'job_cards' => ['label' => 'Job Card', 'label_plural' => 'Job Cards', 'icon' => 'fa-clipboard-list', 'badge' => 'rb-badge--blue'],
    'quotations' => ['label' => 'Quotation', 'label_plural' => 'Quotations', 'icon' => 'fa-file-invoice-dollar', 'badge' => 'rb-badge--purple'],
    'expenses' => ['label' => 'Expense', 'label_plural' => 'Expenses', 'icon' => 'fa-receipt', 'badge' => 'rb-badge--amber'],
    'employees' => ['label' => 'Employee', 'label_plural' => 'Employees', 'icon' => 'fa-user-tie', 'badge' => 'rb-badge--slate'],
    'inventory' => ['label' => 'Inventory', 'label_plural' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'badge' => 'rb-badge--cyan'],
    'statutory' => ['label' => 'Statutory', 'label_plural' => 'Statutory Docs', 'icon' => 'fa-file-contract', 'badge' => 'rb-badge--green'],
];

$rb_safe_query = static function (PDO $pdo, string $sql): array {
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('Recycle bin query failed: ' . $e->getMessage());
        return [];
    }
};

$rb_rows = [];

$pushRows = static function (string $type, array $items) use (&$rb_rows, $rb_type_meta): void {
    if ($items === [] || !isset($rb_type_meta[$type])) {
        return;
    }
    $meta = $rb_type_meta[$type];
    foreach ($items as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $primary = '';
        $secondary = '';
        if ($type === 'job_cards') {
            $primary = (string) ($row['card_number'] ?? ('#' . $id));
            $secondary = (string) ($row['client_name'] ?? '');
        } elseif ($type === 'quotations') {
            $primary = 'QTN #' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
            $jc = trim((string) ($row['job_card'] ?? ''));
            $amt = isset($row['amount']) ? formatMoney((float) $row['amount']) : '';
            $secondary = trim($jc . ($amt !== '' ? ' · ' . $amt : ''));
            if ($secondary === '') {
                $secondary = (string) ($row['client_name'] ?? '');
            }
        } elseif ($type === 'expenses') {
            $primary = 'EXP-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
            $po = (int) ($row['purchase_order_id'] ?? 0);
            $secondary = $po > 0 ? 'Purchase order #' . $po : '';
        } elseif ($type === 'employees') {
            $primary = (string) ($row['name'] ?? ('Employee #' . $id));
            $secondary = (string) ($row['position'] ?? '');
        } elseif ($type === 'inventory') {
            $primary = (string) ($row['part_name'] ?? ('Part #' . $id));
            $secondary = 'Stock: ' . (int) ($row['stock'] ?? 0);
        } elseif ($type === 'statutory') {
            $primary = (string) ($row['name'] ?? ('Document #' . $id));
            $secondary = (string) ($row['type'] ?? '');
        }
        $deletedAt = $row['deleted_at'] ?? '';
        $rb_rows[] = [
            'type' => $type,
            'type_label' => $meta['label'],
            'type_plural' => $meta['label_plural'],
            'icon' => $meta['icon'],
            'badge' => $meta['badge'],
            'id' => $id,
            'token' => $type . ':' . $id,
            'primary' => $primary,
            'secondary' => $secondary,
            'deleted_at' => $deletedAt,
            'deleted_display' => $deletedAt ? date('d M Y · H:i', strtotime((string) $deletedAt)) : '—',
        ];
    }
};

if ($tab === 'all' || $tab === 'job_cards') {
    $pushRows('job_cards', $rb_safe_query($pdo, "SELECT jc.id, jc.card_number, jc.deleted_at,
        COALESCE(c.name, 'Walk-in') AS client_name
        FROM job_cards jc
        LEFT JOIN clients c ON jc.client_id = c.id
        WHERE jc.deleted_at IS NOT NULL
        ORDER BY jc.deleted_at DESC"));
}
if ($tab === 'all' || $tab === 'quotations') {
    $pushRows('quotations', $rb_safe_query($pdo, "SELECT q.id, q.amount, q.deleted_at,
        jc.card_number AS job_card, COALESCE(c.name, 'Walk-in') AS client_name
        FROM quotations q
        LEFT JOIN job_cards jc ON q.job_card_id = jc.id
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE q.deleted_at IS NOT NULL
        ORDER BY q.deleted_at DESC"));
}
if ($tab === 'all' || $tab === 'expenses') {
    $pushRows('expenses', $rb_safe_query($pdo, "SELECT id, deleted_at, purchase_order_id
        FROM expenses WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC"));
}
if ($tab === 'all' || $tab === 'employees') {
    $pushRows('employees', $rb_safe_query($pdo, "SELECT id, name, position, deleted_at
        FROM employees WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC"));
}
if ($tab === 'all' || $tab === 'inventory') {
    $pushRows('inventory', $rb_safe_query($pdo, "SELECT id, part_name, stock, deleted_at
        FROM inventory WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC"));
}
if ($tab === 'all' || $tab === 'statutory') {
    $pushRows('statutory', $rb_safe_query($pdo, "SELECT id, name, type, deleted_at
        FROM statutory_docs WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC"));
}

$rb_total = count($rb_rows);
$tabLabels = [
    'all' => 'All items',
    'job_cards' => 'Job Cards',
    'quotations' => 'Quotations',
    'expenses' => 'Expenses',
    'employees' => 'Employees',
    'inventory' => 'Inventory',
    'statutory' => 'Statutory',
];
$tabHref = static function (string $key) use ($tab): string {
    $base = 'recycle-bin/recycle_bin.php';
    if ($key === 'all') {
        return $base;
    }
    return $base . '?tab=' . rawurlencode($key);
};

include __DIR__ . '/../includes/header.php';
?>

<style>
    .rb-page-intro{margin:0;color:#64748b;font-size:14px;line-height:1.5;max-width:720px;}
    .rb-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:18px;}
    .rb-stat{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 16px;box-shadow:0 1px 3px rgba(15,23,42,.05);}
    .rb-stat-value{font-size:26px;font-weight:800;color:#0f172a;line-height:1;}
    .rb-stat-label{font-size:12px;font-weight:600;color:#64748b;margin-top:4px;}
    .rb-tabs{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}
    .rb-tab{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;color:#475569;background:#f1f5f9;border:1px solid #e2e8f0;}
    .rb-tab:hover{background:#e2e8f0;color:#0f172a;}
    .rb-tab.is-active{background:#fff7ed;border-color:#fdba74;color:#c2410c;}
    .rb-find-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;margin-bottom:16px;box-shadow:0 2px 8px rgba(15,23,42,.06);}
    .rb-find-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
    .rb-search-wrap{display:flex;align-items:center;gap:8px;flex:1 1 280px;min-width:200px;padding:6px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;}
    .rb-search-wrap i{color:#94a3b8;}
    .rb-search-wrap input{border:none;background:transparent;flex:1;min-width:0;font-size:14px;outline:none;}
    .erp-card.rb-list-card{border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(15,23,42,.07);border-radius:14px;}
    .qt-table-toolbar{display:flex;align-items:center;flex-wrap:wrap;gap:12px 16px;padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
    .qt-select-all{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#334155;cursor:pointer;user-select:none;margin:0;}
    .qt-select-all input{width:16px;height:16px;margin:0;cursor:pointer;accent-color:#F7A100;}
    .qt-selection-meta{font-size:13px;font-weight:600;color:#64748b;}
    .rb-toolbar-actions{display:flex;flex-wrap:wrap;gap:8px;margin-left:auto;}
  .rb-table .erp-table thead th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#1e3a8a;background:#eaf1ff;}
    .rb-table .erp-table tbody td{font-size:13px;font-weight:600;color:#1f2937;vertical-align:middle;}
    .rb-table .erp-table tbody tr:hover td{background:#fffbeb;}
    .rb-table .erp-table tbody tr.is-selected td{background:#fff7ed;}
    .qt-col-check{width:44px;text-align:center;}
    .qt-col-check input{width:16px;height:16px;accent-color:#F7A100;cursor:pointer;}
    .rb-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;white-space:nowrap;}
    .rb-badge--blue{background:#eff6ff;color:#1d4ed8;}
    .rb-badge--purple{background:#f5f3ff;color:#6d28d9;}
    .rb-badge--amber{background:#fff7ed;color:#b45309;}
    .rb-badge--slate{background:#f1f5f9;color:#475569;}
    .rb-badge--cyan{background:#ecfeff;color:#0e7490;}
    .rb-badge--green{background:#ecfdf5;color:#047857;}
    .rb-item-primary{font-weight:800;color:#0f172a;}
    .rb-item-secondary{font-size:12px;color:#64748b;margin-top:2px;}
    .rb-deleted{font-size:12px;color:#64748b;white-space:nowrap;}
    .rb-row-actions{display:flex;gap:6px;justify-content:flex-end;}
    .rb-icon-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid transparent;background:transparent;cursor:pointer;font-size:14px;transition:background .12s ease,border-color .12s ease,color .12s ease;}
    .rb-icon-btn--restore{color:#047857;border-color:#bbf7d0;background:#ecfdf5;}
    .rb-icon-btn--restore:hover{background:#d1fae5;}
    .rb-icon-btn--purge{color:#b91c1c;border-color:#fecaca;background:#fef2f2;}
    .rb-icon-btn--purge:hover{background:#fee2e2;}
    .rb-empty{padding:48px 24px;text-align:center;color:#64748b;}
    .rb-empty-icon{font-size:40px;color:#cbd5e1;margin-bottom:12px;}
    .rb-empty-title{font-size:16px;font-weight:700;color:#334155;margin-bottom:6px;}
    .rb-flash-wrap{position:fixed;inset:0;z-index:2200;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.35);backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .2s ease;}
    .rb-flash-wrap.show{opacity:1;pointer-events:auto;}
    .rb-flash-card{width:min(92vw,400px);background:#fff;border-radius:14px;padding:22px 20px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.2);transform:translateY(8px);transition:transform .2s ease;}
    .rb-flash-wrap.show .rb-flash-card{transform:translateY(0);}
    .rb-flash-icon{width:48px;height:48px;border-radius:999px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;font-size:20px;}
    .rb-flash-card--success .rb-flash-icon{background:#ecfdf3;color:#16a34a;}
    .rb-flash-card--error .rb-flash-icon{background:#fef2f2;color:#dc2626;}
    .aq-s1-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;border:1px solid transparent;background:#fff;line-height:1.2;}
    .aq-s1-btn--gray{border-color:#cbd5e1;color:#334155;}
    .aq-s1-btn--gray:hover{background:#f8fafc;}
    .aq-s1-btn--success{background:#059669;color:#fff;border:none;}
    .aq-s1-btn--success:hover{background:#047857;}
    .aq-name-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:2300;}
    .aq-name-modal.show{display:flex;}
    .aq-name-modal-card{width:min(92vw,460px);background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.25);padding:18px;}
    .aq-name-modal-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px;}
    .aq-name-modal-sub{font-size:.875rem;color:#475569;margin-bottom:10px;line-height:1.5;}
    .aq-name-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px;}
    .aq-name-modal-actions form{margin:0;}
    #rbPurgeModal .erp-modal-body ul{margin:0;padding:0;list-style:none;max-height:140px;overflow-y:auto;text-align:left;}
    #rbPurgeModal .erp-modal-body li{font-size:13px;color:#334155;padding:6px 0;border-bottom:1px solid #f1f5f9;}
    #rbPurgeModal .erp-modal-body li:last-child{border-bottom:none;}
    .rb-search-hidden{display:none !important;}
</style>

<div class="erp-page-header">
    <div>
        <h1 class="erp-page-title"><i class="fas fa-trash-restore" style="margin-right:8px;color:#F7A100;"></i> Recycle Bin</h1>
        <p class="rb-page-intro">Review deleted records, restore them to active lists, or permanently remove them. Permanent deletion cannot be undone.</p>
    </div>
</div>

<?php if ($success !== ''): ?>
<div class="rb-flash-wrap" id="rbFlashWrap">
    <div class="rb-flash-card rb-flash-card--success">
        <div class="rb-flash-icon"><i class="fas fa-check"></i></div>
        <div class="rb-flash-title" style="font-weight:700;color:#0f172a;margin-bottom:6px;">Success</div>
        <div><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</div>
<?php endif; ?>
<?php if ($error !== ''): ?>
<div class="rb-flash-wrap" id="rbFlashWrap">
    <div class="rb-flash-card rb-flash-card--error">
        <div class="rb-flash-icon"><i class="fas fa-exclamation"></i></div>
        <div style="font-weight:700;color:#0f172a;margin-bottom:6px;">Something went wrong</div>
        <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
</div>
<?php endif; ?>

<div class="rb-stats">
    <div class="rb-stat">
        <div class="rb-stat-value"><?php echo (int) $rb_total; ?></div>
        <div class="rb-stat-label">Items in bin<?php echo $tab !== 'all' ? ' (this view)' : ''; ?></div>
    </div>
    <div class="rb-stat">
        <div class="rb-stat-value" style="font-size:18px;padding-top:4px;"><i class="fas fa-undo" style="color:#059669;"></i></div>
        <div class="rb-stat-label">Restore returns items to active lists</div>
    </div>
    <div class="rb-stat">
        <div class="rb-stat-value" style="font-size:18px;padding-top:4px;"><i class="fas fa-skull-crossbones" style="color:#dc2626;"></i></div>
        <div class="rb-stat-label">Permanent delete is irreversible</div>
    </div>
</div>

<nav class="rb-tabs" aria-label="Recycle bin categories">
    <?php foreach ($tabLabels as $key => $label): ?>
    <a href="<?php echo htmlspecialchars($tabHref($key), ENT_QUOTES, 'UTF-8'); ?>" class="rb-tab<?php echo $tab === $key ? ' is-active' : ''; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($rb_total === 0): ?>
<div class="erp-card rb-list-card">
    <div class="erp-card-body">
        <div class="rb-empty">
            <div class="rb-empty-icon"><i class="fas fa-trash-restore"></i></div>
            <div class="rb-empty-title">Recycle bin is empty</div>
            <p style="margin:0;font-size:14px;">Deleted <?php echo $tab === 'all' ? 'records' : htmlspecialchars(strtolower($tabLabels[$tab] ?? 'items'), ENT_QUOTES, 'UTF-8'); ?> will appear here for recovery or permanent removal.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div class="rb-find-panel">
    <div class="rb-find-row">
        <label class="rb-search-wrap">
            <i class="fas fa-search" aria-hidden="true"></i>
            <input type="search" id="rbSearchInput" placeholder="Search by name, reference, client…" aria-label="Search recycle bin">
        </label>
    </div>
</div>

<div class="erp-card rb-list-card" id="rbListCard">
    <div class="erp-card-header">
        <h2 class="erp-card-title"><?php echo htmlspecialchars($tab === 'all' ? 'All deleted items' : ($tabLabels[$tab] ?? 'Items'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <span class="qt-list-meta" id="rbListMeta"><?php echo (int) $rb_total; ?> item<?php echo $rb_total === 1 ? '' : 's'; ?></span>
    </div>
    <div class="erp-card-body erp-p-0">
        <div class="qt-table-toolbar">
            <label class="qt-select-all">
                <input type="checkbox" id="rbSelectAll" aria-label="Select all visible items">
                <span>Select all</span>
            </label>
            <span class="qt-selection-meta" id="rbSelectionMeta">0 selected</span>
            <div class="rb-toolbar-actions">
                <button type="button" class="erp-btn erp-btn-sm" id="rbRestoreSelected" disabled style="background:#ecfdf5;color:#047857;border:1px solid #bbf7d0;">
                    <i class="fas fa-undo"></i> Restore selected
                </button>
                <button type="button" class="erp-btn erp-btn-danger erp-btn-sm" id="rbPurgeSelected" disabled>
                    <i class="fas fa-trash-alt"></i> Delete permanently
                </button>
            </div>
        </div>
        <div class="erp-table-container qt-table-scroll rb-table">
            <table class="erp-table" id="rbTable">
                <thead>
                    <tr>
                        <th class="qt-col-check" scope="col">
                            <input type="checkbox" id="rbSelectAllHead" aria-label="Select all visible items">
                        </th>
                        <th>Type</th>
                        <th>Item</th>
                        <th>Details</th>
                        <th>Deleted</th>
                        <th style="text-align:right;width:96px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rb_rows as $rbRow): ?>
                    <tr class="rb-data-row" data-rb-token="<?php echo htmlspecialchars($rbRow['token'], ENT_QUOTES, 'UTF-8'); ?>" data-rb-label="<?php echo htmlspecialchars($rbRow['type_label'] . ': ' . $rbRow['primary'], ENT_QUOTES, 'UTF-8'); ?>">
                        <td class="qt-col-check">
                            <input type="checkbox" class="rb-row-check" value="<?php echo htmlspecialchars($rbRow['token'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select <?php echo htmlspecialchars($rbRow['primary'], ENT_QUOTES, 'UTF-8'); ?>">
                        </td>
                        <td>
                            <span class="rb-badge <?php echo htmlspecialchars($rbRow['badge'], ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas <?php echo htmlspecialchars($rbRow['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                <?php echo htmlspecialchars($rbRow['type_label'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>
                        <td><span class="rb-item-primary"><?php echo htmlspecialchars($rbRow['primary'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><span class="rb-item-secondary"><?php echo htmlspecialchars($rbRow['secondary'] !== '' ? $rbRow['secondary'] : '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td class="rb-deleted"><?php echo htmlspecialchars($rbRow['deleted_display'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <div class="rb-row-actions">
                                <button type="button" class="rb-icon-btn rb-icon-btn--restore rb-one-restore" title="Restore" aria-label="Restore <?php echo htmlspecialchars($rbRow['primary'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button type="button" class="rb-icon-btn rb-icon-btn--purge rb-one-purge" title="Delete permanently" aria-label="Permanently delete <?php echo htmlspecialchars($rbRow['primary'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr id="rbNoMatchRow" class="rb-search-hidden">
                        <td colspan="6" style="text-align:center;padding:24px;color:#64748b;"><i class="fas fa-search"></i> No matching items</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Restore modal -->
<div id="rbRestoreModal" class="aq-name-modal" role="dialog" aria-modal="true" aria-labelledby="rbRestoreTitle">
    <div class="aq-name-modal-card">
        <div class="aq-name-modal-title" id="rbRestoreTitle">Restore items?</div>
        <div class="aq-name-modal-sub" id="rbRestoreMessage">Selected items will be returned to their active lists and can be used again in Job Cards, Quotations, and other modules.</div>
        <ul id="rbRestoreList" style="margin:0 0 8px;padding:0;list-style:none;max-height:120px;overflow-y:auto;font-size:13px;color:#334155;"></ul>
        <div class="aq-name-modal-actions">
            <button type="button" class="aq-s1-btn aq-s1-btn--gray" data-rb-restore-close>Cancel</button>
            <form method="POST" action="recycle-bin/recycle_bin.php" id="rbRestoreForm">
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
                <div id="rbRestoreItemsWrap"></div>
                <button type="submit" class="aq-s1-btn aq-s1-btn--success"><i class="fas fa-undo"></i> Restore</button>
            </form>
        </div>
    </div>
</div>

<!-- Permanent delete modal — quotations.php #deleteModal blueprint -->
<div class="erp-modal-overlay" id="rbPurgeModal" role="dialog" aria-modal="true" aria-labelledby="rbPurgeTitle">
    <div class="erp-modal">
        <div class="erp-modal-header">
            <h3 class="erp-modal-title" id="rbPurgeTitle">Permanently Delete</h3>
            <button type="button" class="erp-modal-close" data-rb-purge-close aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="erp-modal-body" style="text-align:center;">
            <div style="width:80px;height:80px;background:var(--status-danger-bg,#fef2f2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <i class="fas fa-trash-alt" style="font-size:36px;color:var(--status-danger,#dc2626);"></i>
            </div>
            <p style="color:var(--gray-600,#475569);margin-bottom:16px;" id="rbPurgePrompt">Are you sure you want to permanently delete:</p>
            <h4 id="rbPurgeName" style="color:var(--gray-800,#0f172a);font-size:18px;margin:0 0 12px;font-weight:700;line-height:1.4;"></h4>
            <ul id="rbPurgeList" style="display:none;"></ul>
            <p style="color:var(--status-danger,#dc2626);font-size:14px;font-weight:600;margin:0;">This cannot be undone. Data will be permanently removed from the system.</p>
        </div>
        <div class="erp-modal-footer">
            <button type="button" class="erp-btn erp-btn-secondary" data-rb-purge-close>Cancel</button>
            <form method="POST" action="recycle-bin/recycle_bin.php" id="rbPurgeForm" style="display:inline-block;margin:0;">
                <input type="hidden" name="action" value="purge">
                <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES, 'UTF-8'); ?>">
                <div id="rbPurgeItemsWrap"></div>
                <button type="submit" class="erp-btn erp-btn-danger" id="rbPurgeSubmitBtn">Delete permanently</button>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const table = document.getElementById('rbTable');
    if (!table) return;

    const selectAll = document.getElementById('rbSelectAll');
    const selectAllHead = document.getElementById('rbSelectAllHead');
    const selectionMeta = document.getElementById('rbSelectionMeta');
    const listMeta = document.getElementById('rbListMeta');
    const restoreBtn = document.getElementById('rbRestoreSelected');
    const purgeBtn = document.getElementById('rbPurgeSelected');
    const searchInput = document.getElementById('rbSearchInput');
    const noMatchRow = document.getElementById('rbNoMatchRow');
    const restoreModal = document.getElementById('rbRestoreModal');
    const purgeModal = document.getElementById('rbPurgeModal');

    function visibleRows() {
        return Array.from(table.querySelectorAll('tbody tr.rb-data-row')).filter(function (row) {
            return !row.classList.contains('rb-search-hidden');
        });
    }

    function selectedChecks() {
        return visibleRows().map(function (row) {
            return row.querySelector('.rb-row-check');
        }).filter(function (cb) { return cb && cb.checked; });
    }

    function syncSelection() {
        const checks = selectedChecks();
        const n = checks.length;
        const visible = visibleRows();
        if (selectionMeta) selectionMeta.textContent = n + ' selected';
        if (restoreBtn) restoreBtn.disabled = n === 0;
        if (purgeBtn) purgeBtn.disabled = n === 0;
        visible.forEach(function (row) {
            const cb = row.querySelector('.rb-row-check');
            row.classList.toggle('is-selected', !!(cb && cb.checked));
        });
        const allVisible = visible.length > 0 && visible.every(function (row) {
            const cb = row.querySelector('.rb-row-check');
            return cb && cb.checked;
        });
        if (selectAll) selectAll.checked = allVisible;
        if (selectAllHead) selectAllHead.checked = allVisible;
    }

    function setAllVisible(checked) {
        visibleRows().forEach(function (row) {
            const cb = row.querySelector('.rb-row-check');
            if (cb) cb.checked = checked;
        });
        syncSelection();
    }

    function applySearch() {
        const q = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase().trim();
        let visibleCount = 0;
        table.querySelectorAll('tbody tr.rb-data-row').forEach(function (row) {
            const text = row.textContent.toLowerCase();
            const show = q === '' || text.indexOf(q) !== -1;
            row.classList.toggle('rb-search-hidden', !show);
            if (show) visibleCount++;
        });
        if (noMatchRow) noMatchRow.classList.toggle('rb-search-hidden', visibleCount > 0);
        if (listMeta) {
            listMeta.textContent = visibleCount + ' item' + (visibleCount === 1 ? '' : 's') + (q ? ' shown' : '');
        }
        syncSelection();
    }

    function tokensFromChecks(checks) {
        return checks.map(function (cb) { return cb.value; }).filter(Boolean);
    }

    function labelsFromChecks(checks) {
        return checks.map(function (cb) {
            const row = cb.closest('tr');
            return row ? (row.getAttribute('data-rb-label') || cb.value) : cb.value;
        });
    }

    function fillItemsWrap(wrap, tokens) {
        if (!wrap) return;
        wrap.innerHTML = '';
        tokens.forEach(function (token) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[]';
            input.value = token;
            wrap.appendChild(input);
        });
    }

    function fillList(listEl, labels, max) {
        if (!listEl) return;
        listEl.innerHTML = '';
        const show = labels.slice(0, max);
        show.forEach(function (label) {
            const li = document.createElement('li');
            li.textContent = label;
            listEl.appendChild(li);
        });
        if (labels.length > max) {
            const li = document.createElement('li');
            li.textContent = '…and ' + (labels.length - max) + ' more';
            li.style.color = '#64748b';
            listEl.appendChild(li);
        }
        listEl.style.display = labels.length ? 'block' : 'none';
    }

    function openRestoreModal(checks) {
        const labels = labelsFromChecks(checks);
        const tokens = tokensFromChecks(checks);
        const titleEl = document.getElementById('rbRestoreTitle');
        const messageEl = document.getElementById('rbRestoreMessage');
        if (titleEl) {
            titleEl.textContent = labels.length === 1 ? 'Restore item?' : ('Restore ' + labels.length + ' items?');
        }
        if (messageEl) {
            messageEl.textContent = labels.length === 1
                ? ('Restore “' + (labels[0] || 'this item') + '” to its active list?')
                : 'Selected items will be returned to their active lists and can be used again.';
        }
        fillList(document.getElementById('rbRestoreList'), labels, 6);
        fillItemsWrap(document.getElementById('rbRestoreItemsWrap'), tokens);
        if (restoreModal) restoreModal.classList.add('show');
    }

    function openPurgeModal(checks) {
        const labels = labelsFromChecks(checks);
        const tokens = tokensFromChecks(checks);
        const titleEl = document.getElementById('rbPurgeTitle');
        const promptEl = document.getElementById('rbPurgePrompt');
        const nameEl = document.getElementById('rbPurgeName');
        const submitBtn = document.getElementById('rbPurgeSubmitBtn');
        fillItemsWrap(document.getElementById('rbPurgeItemsWrap'), tokens);
        if (labels.length === 1) {
            if (titleEl) titleEl.textContent = 'Permanently Delete';
            if (promptEl) promptEl.textContent = 'Are you sure you want to permanently delete:';
            if (nameEl) nameEl.textContent = labels[0] || '';
            if (submitBtn) submitBtn.textContent = 'Delete permanently';
        } else {
            if (titleEl) titleEl.textContent = 'Permanently Delete Items';
            if (promptEl) promptEl.textContent = 'Are you sure you want to permanently delete these items?';
            if (nameEl) {
                const preview = labels.slice(0, 4).join(', ');
                nameEl.textContent = preview + (labels.length > 4 ? (' …and ' + (labels.length - 4) + ' more') : '');
            }
            if (submitBtn) submitBtn.textContent = 'Delete ' + labels.length + ' permanently';
        }
        if (purgeModal) purgeModal.classList.add('show');
    }

    function closeRestoreModal() {
        if (restoreModal) restoreModal.classList.remove('show');
    }

    function closePurgeModal() {
        if (purgeModal) purgeModal.classList.remove('show');
    }

    if (selectAll) selectAll.addEventListener('change', function () { setAllVisible(selectAll.checked); });
    if (selectAllHead) selectAllHead.addEventListener('change', function () { setAllVisible(selectAllHead.checked); });

    table.addEventListener('change', function (e) {
        if (e.target.classList.contains('rb-row-check')) syncSelection();
    });

    if (searchInput) searchInput.addEventListener('input', applySearch);

    if (restoreBtn) {
        restoreBtn.addEventListener('click', function () {
            const checks = selectedChecks();
            if (!checks.length) return;
            openRestoreModal(checks);
        });
    }

    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            const checks = selectedChecks();
            if (!checks.length) return;
            openPurgeModal(checks);
        });
    }

    table.addEventListener('click', function (e) {
        const restoreOne = e.target.closest('.rb-one-restore');
        const purgeOne = e.target.closest('.rb-one-purge');
        if (restoreOne) {
            const row = restoreOne.closest('tr');
            const cb = row && row.querySelector('.rb-row-check');
            if (cb) openRestoreModal([cb]);
            return;
        }
        if (purgeOne) {
            const row = purgeOne.closest('tr');
            const cb = row && row.querySelector('.rb-row-check');
            if (cb) openPurgeModal([cb]);
        }
    });

    document.querySelectorAll('[data-rb-restore-close]').forEach(function (btn) {
        btn.addEventListener('click', closeRestoreModal);
    });
    document.querySelectorAll('[data-rb-purge-close]').forEach(function (btn) {
        btn.addEventListener('click', closePurgeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeRestoreModal();
            closePurgeModal();
        }
    });
    if (restoreModal) {
        restoreModal.addEventListener('click', function (e) {
            if (e.target === restoreModal) closeRestoreModal();
        });
    }
    if (purgeModal) {
        purgeModal.addEventListener('click', function (e) {
            if (e.target === purgeModal) closePurgeModal();
        });
    }

    syncSelection();
})();

(function () {
    const wrap = document.getElementById('rbFlashWrap');
    if (!wrap) return;
    function dismiss() {
        wrap.classList.remove('show');
        setTimeout(function () { wrap.remove(); }, 220);
    }
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    wrap.addEventListener('click', dismiss);
    setTimeout(dismiss, 3200);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
