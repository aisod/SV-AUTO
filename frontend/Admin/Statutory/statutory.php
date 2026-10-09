<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin', 'manager']);

$typeLabels = [
    'founding' => 'Founding document',
    'good_standing' => 'Good standing',
    'recommendation' => 'Recommendation',
    'afs' => 'Annual financial statements',
    'tender' => 'Tender',
];

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT id, name, file_url, type, uploaded_at FROM statutory_docs WHERE deleted_at IS NULL';
$params = [];
if ($q !== '') {
    $sql .= ' AND name LIKE ?';
    $params[] = '%' . $q . '%';
}
$stmt = $pdo->prepare($sql . ' ORDER BY id DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$GLOBALS['erp_resume_title'] = 'Statutory Docs';
include __DIR__ . '/../includes/header.php';

al_render_list([
    'title' => 'Statutory Documents',
    'subtitle' => count($rows) . ' document' . (count($rows) === 1 ? '' : 's') . ' on file',
    'icon' => 'fa-file-contract',
    'table_id' => 'statutoryTable',
    'search' => $q,
    'search_placeholder' => 'Search documents...',
    'empty' => 'No documents uploaded yet.',
    'columns' => [
        ['label' => 'Document', 'value' => fn ($r) => $r['name']],
        ['label' => 'Type', 'value' => fn ($r) => $typeLabels[$r['type']] ?? (string) $r['type']],
        ['label' => 'Uploaded', 'value' => fn ($r) => al_date($r['uploaded_at'])],
    ],
    'actions' => fn ($r) => [
        'view' => 'Statutory/view_statutory_doc.php?file=' . rawurlencode((string) $r['file_url']),
        'delete' => 'Statutory/delete_statutory.php?id=' . (int) $r['id'],
        'delete_confirm' => 'Delete "' . $r['name'] . '"? You will be asked to confirm on the next page.',
    ],
], $rows);
?>

<div class="erp-card erp-mt-2 erp-mb-4">
    <div class="erp-card-header"><h2 class="erp-card-title"><i class="fas fa-upload"></i> Upload a document</h2></div>
    <div class="erp-card-body">
        <form action="Statutory/upload_statutory.php" method="post" enctype="multipart/form-data"
              style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;align-items:end;">
            <div>
                <label class="erp-label" for="stat-name">Document name</label>
                <input type="text" id="stat-name" name="name" class="erp-input" required maxlength="100">
            </div>
            <div>
                <label class="erp-label" for="stat-type">Type</label>
                <select id="stat-type" name="type" class="erp-input" required>
                    <option value="">Select type</option>
                    <?php foreach ($typeLabels as $key => $label): ?>
                        <option value="<?php echo al_h($key); ?>"><?php echo al_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="erp-label" for="stat-file">File (PDF, Word, JPG, PNG · max 15 MB)</label>
                <input type="file" id="stat-file" name="file" class="erp-input" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            </div>
            <div><button type="submit" class="erp-btn erp-btn-primary"><i class="fas fa-upload"></i> Upload</button></div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
