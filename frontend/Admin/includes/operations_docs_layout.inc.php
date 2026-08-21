<?php
declare(strict_types=1);

/**
 * Shared Documents-style layout helpers for Operations list pages.
 */

function ops_docs_time_ago(?string $raw): string
{
    if ($raw === null || trim($raw) === '') {
        return '';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m === 1 ? '1 min ago' : ($m . ' min ago');
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h === 1 ? '1 hour ago' : ($h . ' hours ago');
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);
        return $d === 1 ? '1 day ago' : ($d . ' days ago');
    }
    return date('d M Y', $ts);
}

function ops_docs_count_label(int $count, string $unit): string
{
    $unit = trim($unit);
    if ($count === 1) {
        $singular = rtrim($unit, 's');
        if ($singular === $unit && str_ends_with($unit, 'es')) {
            $singular = substr($unit, 0, -2);
        }
        return '1 ' . $singular;
    }
    return number_format($count) . ' ' . $unit;
}

/**
 * @param array<string, mixed> $opts
 */
function ops_docs_render_header(array $opts): void
{
    $title = (string) ($opts['title'] ?? '');
    $subtitle = (string) ($opts['subtitle'] ?? '');
    $searchId = (string) ($opts['search_id'] ?? 'searchInput');
    $searchPlaceholder = (string) ($opts['search_placeholder'] ?? 'Search…');
    $searchLabel = (string) ($opts['search_label'] ?? 'Search');
    $actionsHtml = (string) ($opts['actions_html'] ?? '');
    ?>
    <div class="ops-doc-page">
        <header class="ops-doc-header">
            <div class="ops-doc-header-left">
                <h1 class="ops-doc-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                <?php if ($subtitle !== ''): ?>
                <p class="ops-doc-subtitle"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
            <div class="ops-doc-header-right">
                <label class="ops-doc-search">
                    <span class="ops-doc-search-icon" aria-hidden="true"><i class="fas fa-search"></i></span>
                    <input type="text"
                        class="erp-input erp-input-sm"
                        id="<?php echo htmlspecialchars($searchId, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="<?php echo htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="<?php echo htmlspecialchars($searchLabel, ENT_QUOTES, 'UTF-8'); ?>">
                </label>
                <?php echo $actionsHtml; ?>
            </div>
        </header>
    <?php
}

function ops_docs_layout_open(): void
{
    echo '<div class="ops-doc-layout"><div class="ops-doc-main">';
}

/**
 * @param array<int, array<string, mixed>> $folders
 */
function ops_docs_render_folders(string $title, array $folders, string $viewAllId = 'opsFoldersViewAll'): void
{
    ?>
    <section class="ops-doc-folders" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="ops-doc-block-head">
            <h2 class="ops-doc-block-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if ($viewAllId !== ''): ?>
            <button type="button" class="ops-doc-view-all" id="<?php echo htmlspecialchars($viewAllId, ENT_QUOTES, 'UTF-8'); ?>">View All</button>
            <?php endif; ?>
        </div>
        <div class="ops-folder-grid">
            <?php foreach ($folders as $folder):
                $label = (string) ($folder['label'] ?? '');
                $count = (int) ($folder['count'] ?? 0);
                $unit = (string) ($folder['unit'] ?? 'items');
                $countLabel = (string) ($folder['count_label'] ?? ops_docs_count_label($count, $unit));
                $icon = (string) ($folder['icon'] ?? 'fa-folder');
                $tone = preg_replace('/[^a-z]/', '', strtolower((string) ($folder['tone'] ?? 'orange')));
                $extraClass = (string) ($folder['class'] ?? '');
                $href = (string) ($folder['href'] ?? '');
                $attrs = is_array($folder['attrs'] ?? null) ? $folder['attrs'] : [];
                $isClickable = !empty($folder['clickable']) || $href !== '';
                $attrHtml = '';
                foreach ($attrs as $key => $val) {
                    $attrHtml .= ' ' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8')
                        . '="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"';
                }
                $cardClass = 'ops-folder-card ops-folder-card--' . htmlspecialchars($tone, ENT_QUOTES, 'UTF-8')
                    . ($extraClass !== '' ? ' ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') : '');
            ?>
            <?php if ($href !== ''): ?>
            <a class="<?php echo $cardClass; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $attrHtml; ?>>
            <?php else: ?>
            <div class="<?php echo $cardClass; ?>"
                <?php if ($isClickable): ?>role="button" tabindex="0"<?php endif; ?>
                <?php echo $attrHtml; ?>>
            <?php endif; ?>
                <span class="ops-folder-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                <span class="ops-folder-body">
                    <span class="ops-folder-name"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="ops-folder-count"><?php echo htmlspecialchars($countLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
            <?php echo $href !== '' ? '</a>' : '</div>'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
}

function ops_docs_files_section_open(string $title = 'Files', string $viewAllId = 'opsFilesViewAll'): void
{
    ?>
    <section class="ops-doc-files" aria-label="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="ops-doc-block-head">
            <h2 class="ops-doc-block-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if ($viewAllId !== ''): ?>
            <div class="ops-doc-files-head-end">
                <span class="ops-doc-files-meta" id="opsDocListMeta"></span>
                <button type="button" class="ops-doc-view-all" id="<?php echo htmlspecialchars($viewAllId, ENT_QUOTES, 'UTF-8'); ?>">View All</button>
            </div>
            <?php endif; ?>
        </div>
    <?php
}

function ops_docs_table_card_open(string $listId = ''): void
{
    $idAttr = $listId !== '' ? ' id="' . htmlspecialchars($listId, ENT_QUOTES, 'UTF-8') . '"' : '';
    echo '<div class="ops-doc-table-card erp-card qt-list-card"' . $idAttr . '>';
}

function ops_docs_files_section_close(): void
{
    echo '</section>';
}

/**
 * @param array<string, mixed> $config
 */
function ops_docs_render_sidebar(array $config): void
{
    $overviewTitle = (string) ($config['overview_title'] ?? 'Overview');
    $donutSegments = is_array($config['donut_segments'] ?? null) ? $config['donut_segments'] : [];
    $donutCenter = (string) ($config['donut_center'] ?? '');
    $donutSub = (string) ($config['donut_sub'] ?? '');
    $statsTitle = (string) ($config['stats_title'] ?? 'Quick stats');
    $bars = is_array($config['bars'] ?? null) ? $config['bars'] : [];
    $recentTitle = (string) ($config['recent_title'] ?? 'Recent activity');
    $recent = is_array($config['recent'] ?? null) ? $config['recent'] : [];
    ?>
    </div>
    <aside class="ops-doc-aside" aria-label="Page summary">
        <?php if ($donutSegments !== []): ?>
        <div class="ops-doc-widget ops-doc-widget--overview">
            <h3 class="ops-doc-widget-title"><?php echo htmlspecialchars($overviewTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="ops-donut-wrap">
                <div class="ops-donut" role="img" aria-label="<?php echo htmlspecialchars($donutCenter, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                    $gradientParts = [];
                    $cursor = 0.0;
                    $total = 0.0;
                    foreach ($donutSegments as $seg) {
                        $total += (float) ($seg['pct'] ?? 0);
                    }
                    if ($total <= 0) {
                        $gradientParts[] = '#e2e8f0 0% 100%';
                    } else {
                        foreach ($donutSegments as $seg) {
                            $pct = max(0.0, (float) ($seg['pct'] ?? 0));
                            if ($pct <= 0) {
                                continue;
                            }
                            $color = (string) ($seg['color'] ?? '#ea580c');
                            $start = $cursor;
                            $cursor += ($pct / $total) * 100;
                            $gradientParts[] = $color . ' ' . $start . '% ' . $cursor . '%';
                        }
                    }
                    $gradient = 'conic-gradient(' . implode(', ', $gradientParts) . ')';
                    ?>
                    <div class="ops-donut-ring" style="background:<?php echo htmlspecialchars($gradient, ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="ops-donut-hole">
                        <?php if ($donutCenter !== ''): ?>
                        <strong><?php echo htmlspecialchars($donutCenter, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php endif; ?>
                        <?php if ($donutSub !== ''): ?>
                        <span><?php echo htmlspecialchars($donutSub, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="ops-donut-legend">
                    <?php foreach ($donutSegments as $seg): ?>
                    <li>
                        <span class="ops-donut-swatch" style="background:<?php echo htmlspecialchars((string) ($seg['color'] ?? '#ea580c'), ENT_QUOTES, 'UTF-8'); ?>"></span>
                        <span class="ops-donut-legend-label"><?php echo htmlspecialchars((string) ($seg['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="ops-donut-legend-val"><?php echo htmlspecialchars((string) ($seg['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($bars !== []): ?>
        <div class="ops-doc-widget">
            <h3 class="ops-doc-widget-title"><?php echo htmlspecialchars($statsTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
            <div class="ops-doc-stat-row">
                <?php foreach ($bars as $bar):
                    $label = (string) ($bar['label'] ?? '');
                    $value = (string) ($bar['value'] ?? '0');
                    $pct = max(0, min(100, (int) ($bar['pct'] ?? 0)));
                    $tone = (string) ($bar['tone'] ?? '');
                    $icon = (string) ($bar['icon'] ?? 'fa-chart-bar');
                    $fillClass = $tone !== '' ? ' ops-doc-stat-bar-fill--' . preg_replace('/[^a-z]/', '', strtolower($tone)) : '';
                ?>
                <div class="ops-doc-stat-item">
                    <span class="ops-doc-stat-icon" aria-hidden="true"><i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                    <span class="ops-doc-stat-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="ops-doc-stat-value"><?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="ops-doc-stat-bar" role="presentation">
                        <span class="ops-doc-stat-bar-fill<?php echo htmlspecialchars($fillClass, ENT_QUOTES, 'UTF-8'); ?>" style="width:<?php echo $pct; ?>%"></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($recent !== []): ?>
        <div class="ops-doc-widget">
            <h3 class="ops-doc-widget-title"><?php echo htmlspecialchars($recentTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
            <ul class="ops-doc-activity-list">
                <?php foreach ($recent as $i => $item):
                    $href = (string) ($item['href'] ?? '#');
                    $title = (string) ($item['title'] ?? '');
                    $meta = (string) ($item['meta'] ?? '');
                    $time = (string) ($item['time'] ?? '');
                    $initials = (string) ($item['initials'] ?? '');
                    $highlight = !empty($item['highlight']) || $i === 0;
                ?>
                <li>
                    <a class="ops-doc-activity-item<?php echo $highlight ? ' is-highlight' : ''; ?>" href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="ops-doc-activity-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials !== '' ? $initials : '•', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="ops-doc-activity-body">
                            <p class="ops-doc-activity-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if ($meta !== ''): ?>
                            <p class="ops-doc-activity-meta"><?php echo htmlspecialchars($meta, ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <?php if ($time !== ''): ?>
                            <span class="ops-doc-activity-time"><?php echo htmlspecialchars($time, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($highlight): ?><span class="ops-doc-activity-dot" aria-hidden="true"></span><?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </aside>
    </div></div>
    <?php
}

/**
 * @param array<int, array<string, mixed>> $bars
 */
function ops_docs_pct_bars(array $bars, int $total): array
{
    if ($total <= 0) {
        foreach ($bars as &$bar) {
            $bar['pct'] = 0;
        }
        unset($bar);
        return $bars;
    }
    foreach ($bars as &$bar) {
        $count = (int) ($bar['count'] ?? 0);
        $bar['pct'] = (int) round(($count / $total) * 100);
        if (!isset($bar['value'])) {
            $bar['value'] = (string) number_format($count);
        }
    }
    unset($bar);
    return $bars;
}

function ops_docs_init_script(): void
{
    ?>
    <script>
    (function () {
        function wireViewAll(btnId, resetFn) {
            var btn = document.getElementById(btnId);
            if (!btn || typeof resetFn !== 'function') return;
            btn.addEventListener('click', function () { resetFn(); });
        }

        function syncListMeta() {
            var meta = document.getElementById('jcTableMeta') || document.getElementById('qtTableMeta') || document.getElementById('invTableMeta');
            var slotMeta = document.getElementById('opsDocListMeta');
            var footMeta = document.getElementById('opsFilterResult');
            if (!meta) return;
            var text = meta.textContent || '';
            if (slotMeta) slotMeta.textContent = text;
            if (footMeta) footMeta.textContent = text ? ('Showing ' + text) : '';
        }

        document.addEventListener('DOMContentLoaded', function () {
            syncListMeta();
            var meta = document.getElementById('jcTableMeta') || document.getElementById('qtTableMeta') || document.getElementById('invTableMeta');
            if (meta) {
                new MutationObserver(syncListMeta).observe(meta, { childList: true, characterData: true, subtree: true });
            }
        });

        window.opsDocsSyncListMeta = syncListMeta;
        window.opsDocsWireViewAll = wireViewAll;
    })();
    </script>
    <?php
}
