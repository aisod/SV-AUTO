<?php
declare(strict_types=1);

/**
 * Quotations list page — LAYOUT BLUEPRINT (locked).
 *
 * Do not change section order, wrapper hierarchy, or spacing tokens in quotations.php
 * without updating this file and QT_LAYOUT_VERSION.
 *
 * STRUCTURE (fixed):
 *   erp-page-header
 *   [flash messages]
 *   .qt-quotations-page
 *     └── .qt-page-stack                    ← vertical rhythm (gap token only)
 *           ├── section.qt-overview-section  ← KPI strip (no outer card border)
 *           │     ├── .qt-dashboard-label
 *           │     ├── .qt-dashboard-hint
 *           │     └── .mod-hero-grid--overview
 *           ├── section.qt-find-panel        ← filter card
 *           │     ├── .qt-find-header
 *           │     └── .qt-tab-groups
 *           └── section.qt-list-card         ← table card (#qtQuotationsList)
 *                 ├── .erp-card-header
 *                 ├── .qt-table-toolbar
 *                 └── #quotationTable
 *
 * @see quotations.php
 */
define('QT_LAYOUT_VERSION', '1.1.0');

/**
 * Locked spacing / shell tokens — single source of truth for page structure size.
 *
 * @return array<string, string>
 */
function qt_layout_tokens(): array
{
    return [
        'stack_gap' => '48px',
        'stack_margin_top' => '8px',
        'stack_margin_bottom' => '24px',
        'overview_label_mb' => '6px',
        'overview_hint_mb' => '14px',
        'overview_grid_gap' => '12px',
        'find_header_mb' => '14px',
        'tab_groups_gap' => '12px',
        'list_scroll_margin' => '88px',
    ];
}

function qt_render_layout_blueprint_css(): string
{
    $lines = ['/* Quotations layout blueprint v' . QT_LAYOUT_VERSION . ' — do not edit spacing in quotations.php */'];
    $lines[] = '.qt-quotations-page {';
    foreach (qt_layout_tokens() as $key => $value) {
        $lines[] = '    --qt-' . str_replace('_', '-', $key) . ': ' . $value . ';';
    }
    $lines[] = '}';
    $lines[] = '.qt-page-stack {';
    $lines[] = '    display: flex;';
    $lines[] = '    flex-direction: column;';
    $lines[] = '    gap: var(--qt-stack-gap);';
    $lines[] = '    margin-top: var(--qt-stack-margin-top);';
    $lines[] = '    margin-bottom: var(--qt-stack-margin-bottom);';
    $lines[] = '}';
    $lines[] = '.qt-page-section { margin: 0; }';
    $lines[] = '.qt-dashboard-label { margin: 0 0 var(--qt-overview-label-mb); }';
    $lines[] = '.qt-dashboard-hint { margin: 0 0 var(--qt-overview-hint-mb); }';
    $lines[] = '.mod-hero-grid--overview { gap: var(--qt-overview-grid-gap); margin-bottom: 0; }';
    $lines[] = '.qt-find-header { margin-bottom: var(--qt-find-header-mb); }';
    $lines[] = '.qt-tab-groups { gap: var(--qt-tab-groups-gap); }';
    $lines[] = '#qtQuotationsList { scroll-margin-top: var(--qt-list-scroll-margin); }';

    return implode("\n", $lines) . "\n";
}
