<?php
declare(strict_types=1);

$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: manager_job_cards.php');
    exit;
}

$page_title = 'Job Card';
require_once __DIR__ . '/../includes/header_manager.php';
require_once __DIR__ . '/../includes/mgr_job_card_view_data.inc.php';

$jc = mgr_load_job_card_view($pdo, $id);
if ($jc === null) {
    header('Location: ../manager_job_cards.php?error=' . urlencode('Job card not found'));
    exit;
}

$previewUrl = mgr_href('JobCard/mgr_job_card_preview.php?id=' . $id);

$listError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';
?>
<?php require_once __DIR__ . '/../../Admin/Quotation/quotation_crm_shell_styles.inc.php'; ?>
<?php require_once __DIR__ . '/../includes/mgr_crm_view_extras.inc.php'; ?>

<?php if ($listError !== ''): ?>
<p class="mgr-qt-alert" style="margin:0 0 12px;padding:10px 14px;border-radius:8px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;font-size:14px;" role="alert"><?php echo htmlspecialchars($listError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/mgr_job_card_view_shell.inc.php'; ?>

<?php
$mgr_doc_actions_js = __DIR__ . '/../js/mgr_doc_view_actions.js';
$mgr_doc_actions_js_v = is_file($mgr_doc_actions_js) ? (string) filemtime($mgr_doc_actions_js) : '1';
$mgr_jc_view_js = __DIR__ . '/../js/mgr_job_card_view.js';
$mgr_jc_view_js_v = is_file($mgr_jc_view_js) ? (string) filemtime($mgr_jc_view_js) : '1';
?>
<script src="<?php echo htmlspecialchars(mgr_href('js/mgr_doc_view_actions.js?v=' . $mgr_doc_actions_js_v), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(mgr_href('js/mgr_job_card_view.js?v=' . $mgr_jc_view_js_v), ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer_manager.php'; ?>
