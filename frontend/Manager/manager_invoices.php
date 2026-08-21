<?php
declare(strict_types=1);

$page_title = 'Invoices';
require_once __DIR__ . '/includes/header_manager.php';
require_once __DIR__ . '/includes/mgr_invoices_data.inc.php';
require_once __DIR__ . '/../Admin/includes/student_table.inc.php';
require_once __DIR__ . '/includes/mgr_list_layout_styles.inc.php';

ops_docs_render_header([
    'title' => 'Invoices',
    'search_id' => 'invSearchInput',
    'search_placeholder' => 'Search invoices…',
    'search_label' => 'Search invoices',
]);

if ($listError !== ''): ?>
<p class="mgr-list-alert" role="alert"><?php echo htmlspecialchars($listError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php ops_docs_layout_open(); ?>
<?php require_once __DIR__ . '/includes/mgr_invoices_list.inc.php'; ?>
<?php
ops_docs_render_sidebar([
    'overview_title' => 'Collections',
    'donut_segments' => $mgr_inv_donut_segments,
    'donut_center' => number_format($mgr_inv_total),
    'donut_sub' => 'invoices',
    'stats_title' => 'Quick stats',
    'bars' => $mgr_inv_sidebar_bars,
    'recent_title' => 'Admin activity',
    'recent' => $mgr_inv_sidebar_recent,
]);
ops_docs_init_script();
?>

<?php
$mgr_inv_js = __DIR__ . '/js/mgr_invoices.js';
$mgr_inv_js_v = is_file($mgr_inv_js) ? (string) filemtime($mgr_inv_js) : '1';
?>
<script>window.MGR_INV_VIEW_STORAGE_KEY = 'sv_auto_mgr_invoices_view_v1_u<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>';</script>
<script src="js/mgr_invoices.js?v=<?php echo htmlspecialchars($mgr_inv_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php require_once __DIR__ . '/includes/footer_manager.php'; ?>
