<?php
/**
 * In-content page header (title + actions). Top breadcrumb lives in header_manager.php.
 */
declare(strict_types=1);

if (!function_exists('mgr_render_page_header')) {
    function mgr_render_page_header(string $title, string $actionsHtml = ''): void
    {
        ?>
<div class="erp-page-header">
    <div class="erp-breadcrumb" aria-hidden="true">
        <a href="<?php echo htmlspecialchars(mgr_nav_href('manager_dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-home" aria-hidden="true"></i> Home</a>
    </div>
    <h1 class="erp-page-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (trim($actionsHtml) !== ''): ?>
    <div class="erp-page-actions"><?php echo $actionsHtml; ?></div>
    <?php endif; ?>
</div>
        <?php
    }
}
