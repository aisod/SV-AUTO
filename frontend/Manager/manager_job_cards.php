<?php
declare(strict_types=1);

$page_title = 'Job Cards';
require_once __DIR__ . '/includes/header_manager.php';
require_once __DIR__ . '/includes/mgr_job_cards_data.inc.php';
require_once __DIR__ . '/../Admin/includes/student_table.inc.php';
require_once __DIR__ . '/includes/mgr_list_layout_styles.inc.php';

ops_docs_render_header([
    'title' => 'Job Cards',
    'search_id' => 'jcSearchInput',
    'search_placeholder' => 'Search job cards…',
    'search_label' => 'Search job cards',
]);

if ($listError !== ''): ?>
<p class="mgr-list-alert" role="alert"><?php echo htmlspecialchars($listError, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<?php ops_docs_layout_open(); ?>
<?php require_once __DIR__ . '/includes/mgr_job_cards_list.inc.php'; ?>
<?php
ops_docs_render_sidebar([
    'overview_title' => 'Workshop load',
    'donut_segments' => $mgr_jc_donut_segments,
    'donut_center' => number_format($mgr_jc_total),
    'donut_sub' => 'job cards',
    'stats_title' => 'Quick stats',
    'bars' => $mgr_jc_sidebar_bars,
    'recent_title' => 'Admin activity',
    'recent' => $mgr_jc_sidebar_recent,
]);
ops_docs_init_script();
?>

<?php
$mgr_jc_js = __DIR__ . '/js/mgr_job_cards.js';
$mgr_jc_js_v = is_file($mgr_jc_js) ? (string) filemtime($mgr_jc_js) : '1';
?>
<script>window.MGR_JC_VIEW_STORAGE_KEY = 'sv_auto_mgr_job_cards_view_v1_u<?php echo (int) ($_SESSION['user_id'] ?? 0); ?>';</script>
<script src="js/mgr_job_cards.js?v=<?php echo htmlspecialchars($mgr_jc_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php require_once __DIR__ . '/includes/footer_manager.php'; ?>
