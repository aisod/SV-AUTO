<?php
declare(strict_types=1);

$page_title = 'Quotations';
require_once __DIR__ . '/includes/header_manager.php';
require_once __DIR__ . '/includes/mgr_quotations_data.inc.php';
require_once __DIR__ . '/../Admin/includes/student_table.inc.php';
require_once __DIR__ . '/includes/mgr_quotations_styles.inc.php';

ops_docs_render_header([
    'title' => 'Quotations',
    'search_id' => 'searchInput',
    'search_placeholder' => 'Search quotations…',
    'search_label' => 'Search quotations',
]);

if ($listError !== ''): ?>
<p class="mgr-qt-alert" role="alert"><?php echo htmlspecialchars($listError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php ops_docs_layout_open(); ?>
<?php require_once __DIR__ . '/includes/mgr_quotations_list.inc.php'; ?>
<?php
ops_docs_render_sidebar([
    'overview_title' => 'Quotation pipeline',
    'donut_segments' => $mgr_qt_donut_segments,
    'donut_center' => number_format($mgr_qt_total),
    'donut_sub' => 'quotes',
    'stats_title' => 'Quick stats',
    'bars' => $mgr_qt_sidebar_bars,
    'recent_title' => 'Admin activity',
    'recent' => $mgr_qt_sidebar_recent,
]);
ops_docs_init_script();
?>

<?php
$mgr_qt_js = __DIR__ . '/js/mgr_quotations.js';
$mgr_qt_js_v = is_file($mgr_qt_js) ? (string) filemtime($mgr_qt_js) : '1';
?>
<script>window.MGR_QT_VIEW_STORAGE_KEY = 'sv_auto_mgr_quotations_view_v1_u<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>';</script>
<script src="js/mgr_quotations.js?v=<?php echo htmlspecialchars($mgr_qt_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php require_once __DIR__ . '/includes/footer_manager.php'; ?>
