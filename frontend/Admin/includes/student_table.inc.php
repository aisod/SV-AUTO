<?php
declare(strict_types=1);

/**
 * Student-style table footer (pagination + rows per page).
 *
 * @param string $tableId DOM id of the <table>
 * @param int $defaultPerPage Default rows per page
 */
function st_render_table_footer(string $tableId, int $defaultPerPage = 10): void
{
    $tableId = htmlspecialchars($tableId, ENT_QUOTES, 'UTF-8');
    $defaultPerPage = max(5, min(100, $defaultPerPage));
    ?>
<div class="st-table-footer" data-st-footer-for="<?php echo $tableId; ?>" data-st-per-page="<?php echo (int) $defaultPerPage; ?>">
    <div class="st-table-footer__range" data-st-range>0 of 0</div>
    <div class="st-table-footer__controls">
        <label class="st-table-footer__rpp">
            Rows per page
            <select data-st-rows-per-page aria-label="Rows per page">
                <option value="10"<?php echo $defaultPerPage === 10 ? ' selected' : ''; ?>>10</option>
                <option value="25"<?php echo $defaultPerPage === 25 ? ' selected' : ''; ?>>25</option>
                <option value="50"<?php echo $defaultPerPage === 50 ? ' selected' : ''; ?>>50</option>
                <option value="100"<?php echo $defaultPerPage === 100 ? ' selected' : ''; ?>>100</option>
            </select>
        </label>
        <nav class="st-table-pagination" data-st-pagination aria-label="Table pagination"></nav>
    </div>
</div>
<?php
}

/**
 * Sortable column header — label + sort icons on one line (student-table layout).
 */
function st_sortable_th(string $label, string $extraClass = '', bool $withHint = false, string $hint = ''): string
{
    return st_th_cell($label, trim('sortable ' . $extraClass), true, $withHint, $hint);
}

/**
 * Non-sortable column header (Progress, Action, etc.).
 */
function st_plain_th(string $label, string $extraClass = '', bool $withHint = false, string $hint = ''): string
{
    return st_th_cell($label, $extraClass, false, $withHint, $hint);
}

/**
 * @internal Shared header cell markup.
 */
function st_th_cell(string $label, string $extraClass, bool $sortable, bool $withHint, string $hint): string
{
    $hasSub = $withHint && $hint !== '';
    $class = trim($extraClass . ($hasSub ? ' st-th-has-sub' : ' st-th-single'));
    $hintHtml = '';
    if ($hasSub) {
        $hintHtml = '<span class="st-th-sub">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $icons = '';
    if ($sortable) {
        $icons = '<span class="st-sort-icons" aria-hidden="true">'
            . '<i class="fas fa-caret-up st-sort-up"></i>'
            . '<i class="fas fa-caret-down st-sort-down"></i>'
            . '</span>';
    }
    $labelHtml = '<span class="st-th-text">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';

    $titleAttr = $sortable ? ' title="Click to sort"' : '';

    return '<th class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" scope="col"' . $titleAttr . '>'
        . '<div class="st-th-wrap">'
        . '<span class="st-th-main">' . $labelHtml . $icons . '</span>'
        . $hintHtml
        . '</div></th>';
}

/**
 * View / edit / delete action cell (icon buttons).
 *
 * @param array{view?:string,edit?:string,print?:string,print_blank?:bool,delete_attrs?:array<string,string>,delete_label?:string,delete_class?:string} $urls
 */
function st_actions_cell(array $urls): string
{
    $html = '<td class="st-col-actions st-no-row-nav" onclick="event.stopPropagation();"><div class="qt-td-inner qt-td-inner--center"><div class="st-actions">';

    if (!empty($urls['view'])) {
        $view = htmlspecialchars((string) $urls['view'], ENT_QUOTES, 'UTF-8');
        $html .= '<a href="' . $view . '" class="st-act-btn st-act-btn--view" title="View" aria-label="View"><i class="fas fa-eye" aria-hidden="true"></i></a>';
    }

    if (!empty($urls['edit'])) {
        $edit = htmlspecialchars((string) $urls['edit'], ENT_QUOTES, 'UTF-8');
        $html .= '<a href="' . $edit . '" class="st-act-btn st-act-btn--edit" title="Edit" aria-label="Edit"><i class="fas fa-pen" aria-hidden="true"></i></a>';
    }

    if (!empty($urls['print'])) {
        $print = htmlspecialchars((string) $urls['print'], ENT_QUOTES, 'UTF-8');
        $target = !empty($urls['print_blank']) ? ' target="_blank" rel="noopener"' : '';
        $html .= '<a href="' . $print . '" class="st-act-btn st-act-btn--print" title="Print" aria-label="Print"' . $target . '><i class="fas fa-print" aria-hidden="true"></i></a>';
    }

    if (!empty($urls['delete_attrs'])) {
        $attrs = $urls['delete_attrs'];
        $label = htmlspecialchars((string) ($urls['delete_label'] ?? 'Delete'), ENT_QUOTES, 'UTF-8');
        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8')
                . '="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"';
        }
        $btnClass = htmlspecialchars((string) ($urls['delete_class'] ?? 'st-act-btn st-act-btn--delete'), ENT_QUOTES, 'UTF-8');
        $html .= '<button type="button" class="' . $btnClass . '" title="Delete" aria-label="' . $label . '"' . $attrStr . '>'
            . '<i class="fas fa-trash-alt" aria-hidden="true"></i></button>';
    }

    $html .= '</div></div></td>';
    return $html;
}
