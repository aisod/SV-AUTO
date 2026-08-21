<?php
/**
 * Manager portal breadcrumb trail (matches Admin erp-breadcrumb dropdown pattern).
 */
declare(strict_types=1);

if (!function_exists('mgr_breadcrumb_page_paths')) {
    function mgr_breadcrumb_page_paths(): array
    {
        return [
            'manager_dashboard' => 'manager_dashboard.php',
            'manager_job_cards' => 'manager_job_cards.php',
            'job_card' => 'JobCard/job_card.php',
            'mgr_job_card_preview' => 'JobCard/mgr_job_card_preview.php',
            'manager_quotations' => 'manager_quotations.php',
            'view_quotation' => 'Quotation/view_quotation.php',
            'mgr_quotation_preview' => 'Quotation/mgr_quotation_preview.php',
            'manager_invoices' => 'manager_invoices.php',
            'view_invoice' => 'Invoice/view_invoice.php',
            'mgr_invoice_preview' => 'Invoice/mgr_invoice_preview.php',
        ];
    }
}

if (!function_exists('mgr_breadcrumb_page_labels')) {
    function mgr_breadcrumb_page_labels(): array
    {
        return [
            'manager_dashboard' => 'Dashboard',
            'manager_job_cards' => 'All Job Cards',
            'job_card' => 'Job Card',
            'mgr_job_card_preview' => 'Job Card Preview',
            'manager_quotations' => 'All Quotations',
            'view_quotation' => 'View Quotation',
            'mgr_quotation_preview' => 'Quotation Preview',
            'manager_invoices' => 'All Invoices',
            'view_invoice' => 'View Invoice',
            'mgr_invoice_preview' => 'Invoice Preview',
        ];
    }
}

if (!function_exists('mgr_breadcrumb_page_href')) {
    function mgr_breadcrumb_page_href(string $pageKey): string
    {
        $paths = mgr_breadcrumb_page_paths();
        return mgr_nav_href($paths[$pageKey] ?? ($pageKey . '.php'));
    }
}

if (!function_exists('mgr_breadcrumb_page_label')) {
    function mgr_breadcrumb_page_label(string $pageKey): string
    {
        $labels = mgr_breadcrumb_page_labels();
        return $labels[$pageKey] ?? ucwords(str_replace('_', ' ', $pageKey));
    }
}

if (!function_exists('mgr_build_breadcrumb_trail')) {
    /**
     * @param array<int, array<string, mixed>> $nav
     * @return array<int, array<string, mixed>>|null
     */
    function mgr_build_breadcrumb_trail(array $nav, string $currentPageFile, string $currentPageKey): ?array
    {
        $trail = [
            [
                'label' => 'Home',
                'href' => 'manager_dashboard.php',
                'dropdown' => null,
                'current' => ($currentPageKey === 'manager_dashboard'),
            ],
        ];

        if ($currentPageKey === 'manager_dashboard') {
            return $trail;
        }

        foreach ($nav as $item) {
            if (($item['type'] ?? '') !== 'group') {
                continue;
            }
            if (!in_array($currentPageFile, $item['pages'] ?? [], true)) {
                continue;
            }

            $groupDropdown = [];
            foreach ($item['children'] ?? [] as $child) {
                $groupDropdown[] = [
                    'label' => (string) $child['label'],
                    'href' => mgr_nav_href((string) $child['href']),
                ];
            }

            $trail[] = [
                'label' => (string) $item['label'],
                'href' => null,
                'dropdown' => $groupDropdown,
                'current' => false,
            ];

            foreach ($item['children'] ?? [] as $child) {
                $childPages = $child['pages'] ?? [];
                if (!in_array($currentPageFile, $childPages, true)) {
                    continue;
                }

                $sectionPages = $child['breadcrumb_pages'] ?? $childPages;
                $sectionDropdown = [];
                foreach ($sectionPages as $pageFile) {
                    $pageKey = basename((string) $pageFile, '.php');
                    $sectionDropdown[] = [
                        'label' => mgr_breadcrumb_page_label($pageKey),
                        'href' => mgr_breadcrumb_page_href($pageKey),
                        'active' => ($pageFile === $currentPageFile),
                    ];
                }

                $sectionHref = mgr_nav_href((string) $child['href']);
                $listPageFile = basename((string) $child['href']);
                $isListPage = ($currentPageFile === $listPageFile);

                $trail[] = [
                    'label' => (string) $child['label'],
                    'href' => $sectionHref,
                    'dropdown' => count($sectionDropdown) > 1 ? $sectionDropdown : null,
                    'current' => $isListPage,
                ];

                if (!$isListPage) {
                    $trail[] = [
                        'label' => mgr_breadcrumb_page_label($currentPageKey),
                        'href' => null,
                        'dropdown' => null,
                        'current' => true,
                    ];
                }

                return $trail;
            }
        }

        return null;
    }
}

if (!function_exists('mgr_resolve_breadcrumb_trail')) {
    /**
     * @param array<int, array<string, mixed>> $nav
     * @return array<int, array<string, mixed>>
     */
    function mgr_resolve_breadcrumb_trail(array $nav, string $currentPageFile, string $currentPageKey): array
    {
        $trail = mgr_build_breadcrumb_trail($nav, $currentPageFile, $currentPageKey);
        if ($trail !== null) {
            return $trail;
        }

        $legacy = [
            'manager_job_cards' => [
                ['label' => 'Documents', 'href' => null, 'dropdown' => null, 'current' => false],
                ['label' => 'Job Cards', 'href' => mgr_nav_href('manager_job_cards.php'), 'dropdown' => null, 'current' => true],
            ],
            'job_card' => [
                ['label' => 'Documents', 'href' => null, 'dropdown' => null, 'current' => false],
                ['label' => 'Job Cards', 'href' => mgr_nav_href('manager_job_cards.php'), 'dropdown' => null, 'current' => false],
                ['label' => 'Job Card', 'href' => null, 'dropdown' => null, 'current' => true],
            ],
            'manager_quotations' => [
                ['label' => 'Documents', 'href' => null, 'dropdown' => null, 'current' => false],
                ['label' => 'Quotations', 'href' => mgr_nav_href('manager_quotations.php'), 'dropdown' => null, 'current' => true],
            ],
            'view_quotation' => [
                ['label' => 'Documents', 'href' => null, 'dropdown' => null, 'current' => false],
                ['label' => 'Quotations', 'href' => mgr_nav_href('manager_quotations.php'), 'dropdown' => null, 'current' => false],
                ['label' => 'View Quotation', 'href' => null, 'dropdown' => null, 'current' => true],
            ],
            'manager_invoices' => [
                ['label' => 'Documents', 'href' => null, 'dropdown' => null, 'current' => false],
                ['label' => 'Invoices', 'href' => mgr_nav_href('manager_invoices.php'), 'dropdown' => null, 'current' => true],
            ],
            'view_invoice' => [
                ['label' => 'Documents', 'href' => null, 'dropdown' => null, 'current' => false],
                ['label' => 'Invoices', 'href' => mgr_nav_href('manager_invoices.php'), 'dropdown' => null, 'current' => false],
                ['label' => 'View Invoice', 'href' => null, 'dropdown' => null, 'current' => true],
            ],
        ];

        $trail = [
            ['label' => 'Home', 'href' => 'manager_dashboard.php', 'dropdown' => null, 'current' => false],
        ];

        foreach ($legacy[$currentPageKey] ?? [] as $crumb) {
            $trail[] = $crumb;
        }

        if (!isset($legacy[$currentPageKey])) {
            $trail[] = [
                'label' => mgr_breadcrumb_page_label($currentPageKey),
                'href' => null,
                'dropdown' => null,
                'current' => true,
            ];
        }

        return $trail;
    }
}

if (!function_exists('mgr_render_breadcrumb_nav')) {
    /**
     * @param array<int, array<string, mixed>> $trail
     */
    function mgr_render_breadcrumb_nav(array $trail): void
    {
        ?>
    <nav class="erp-breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo htmlspecialchars(mgr_nav_href('manager_dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="erp-breadcrumb-home" aria-label="Home"><i class="fas fa-home" aria-hidden="true"></i></a>
        <?php foreach ($trail as $i => $crumb):
            if ($i === 0) {
                continue;
            }
            $hasDropdown = !empty($crumb['dropdown']);
            $isCurrent = !empty($crumb['current']);
            ?>
        <span class="erp-breadcrumb-separator" aria-hidden="true">/</span>
        <?php if ($hasDropdown): ?>
        <details class="erp-breadcrumb-item erp-breadcrumb-item--dropdown<?php echo $isCurrent ? ' is-current' : ''; ?>">
            <summary class="erp-breadcrumb-trigger">
                <span><?php echo htmlspecialchars((string) $crumb['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </summary>
            <ul class="erp-breadcrumb-menu" role="menu">
                <?php foreach ($crumb['dropdown'] as $menuItem): ?>
                <li role="none">
                    <a role="menuitem"
                       href="<?php echo htmlspecialchars((string) $menuItem['href'], ENT_QUOTES, 'UTF-8'); ?>"
                       class="<?php echo !empty($menuItem['active']) ? 'is-active' : ''; ?>">
                        <?php echo htmlspecialchars((string) $menuItem['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php elseif (!empty($crumb['href']) && !$isCurrent): ?>
        <a href="<?php echo htmlspecialchars(mgr_nav_href((string) $crumb['href']), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars((string) $crumb['label'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php else: ?>
        <span class="erp-breadcrumb-current"><?php echo htmlspecialchars((string) $crumb['label'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <?php endforeach; ?>
    </nav>
        <?php
    }
}
