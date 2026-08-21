<?php
declare(strict_types=1);

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: manager_quotations.php');
    exit;
}

$page_title = 'View Quotation';
require_once __DIR__ . '/../includes/header_manager.php';
require_once __DIR__ . '/../../../backend/config/notifications.php';
require_once __DIR__ . '/../includes/mgr_quotation_view_data.inc.php';

if (isset($_SESSION['user_id'])) {
    erp_mark_notifications_read_for_quotation($pdo, (int) $_SESSION['user_id'], $id);
}

$qv = mgr_load_quotation_view($pdo, $id);
if ($qv === null) {
    header('Location: ../manager_quotations.php?error=' . urlencode('Quotation not found'));
    exit;
}

$mgrCanPreview = true;
$previewUrl = mgr_app_url('Manager/Quotation/mgr_quotation_preview.php?id=' . $id);
$pdfUrl = mgr_app_url('Manager/Quotation/mgr_quotation_pdf.php?id=' . $id);
$markViewedUrl = mgr_href('Utils/mark_quotation_viewed.php');
$currency = getCurrency();
$sym = (string) ($currency['symbol'] ?? 'N$');

$listError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
?>
<?php require_once __DIR__ . '/../../Admin/Quotation/quotation_crm_shell_styles.inc.php'; ?>

<?php
if ($listError !== ''): ?>
<p class="mgr-qt-alert" style="margin:0 0 12px;padding:10px 14px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:14px;" role="alert"><?php echo htmlspecialchars($listError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/mgr_quotation_view_shell.inc.php'; ?>

<?php
$mgr_doc_actions_js = __DIR__ . '/../js/mgr_doc_view_actions.js';
$mgr_doc_actions_js_v = is_file($mgr_doc_actions_js) ? (string) filemtime($mgr_doc_actions_js) : '1';
$mgr_qt_view_js = __DIR__ . '/../js/mgr_quotation_view.js';
$mgr_qt_view_js_v = is_file($mgr_qt_view_js) ? (string) filemtime($mgr_qt_view_js) : '1';
?>
<script>
window.MGR_QT_MARK_VIEWED_URL = <?php echo json_encode($markViewedUrl); ?>;
window.MGR_QT_QUOTE_ID = <?php echo (int) $id; ?>;
window.MGR_QT_ALREADY_VIEWED = <?php echo !empty($qv['review']['viewed']) ? 'true' : 'false'; ?>;
</script>
<script src="<?php echo htmlspecialchars(mgr_href('js/mgr_doc_view_actions.js?v=' . $mgr_doc_actions_js_v), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(mgr_href('js/mgr_quotation_view.js?v=' . $mgr_qt_view_js_v), ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer_manager.php'; ?>
