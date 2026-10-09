<?php
declare(strict_types=1);

/**
 * Shared renderer for the simple Admin list pages (Inventory, Employees, Expenses, ...).
 *
 * Links are written relative to the Admin base (header.php emits <base href=".../Admin/">),
 * e.g. 'Inventory/edit_inventory.php?id=3'.
 */

require_once __DIR__ . '/student_table.inc.php';

function al_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Small coloured status pill. $tone: green | amber | red | blue | grey */
function al_badge(string $text, string $tone = 'grey'): string
{
    $tones = [
        'green' => ['#dcfce7', '#166534'],
        'amber' => ['#fef3c7', '#92400e'],
        'red'   => ['#fee2e2', '#991b1b'],
        'blue'  => ['#dbeafe', '#1e40af'],
        'grey'  => ['#f1f5f9', '#475569'],
    ];
    [$bg, $fg] = $tones[$tone] ?? $tones['grey'];
    return '<span style="display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;'
        . 'background:' . $bg . ';color:' . $fg . ';">' . al_h($text) . '</span>';
}

function al_date($raw, string $format = 'd M Y'): string
{
    if ($raw === null || trim((string) $raw) === '') {
        return '-';
    }
    $ts = strtotime((string) $raw);
    return $ts === false ? '-' : date($format, $ts);
}

/**
 * Render a full list page (call after including header.php, close with footer.php yourself).
 *
 * @param array{
 *   title:string, subtitle?:string, icon?:string, table_id:string,
 *   add_url?:string, add_label?:string, search?:string, search_placeholder?:string,
 *   empty?:string,
 *   columns:list<array{label:string, value:callable(array):string, html?:bool, class?:string, sort?:bool}>,
 *   actions?:callable(array):array<string,mixed>,
 *   header_html?:string
 * } $cfg
 * @param list<array<string,mixed>> $rows
 */
function al_render_list(array $cfg, array $rows): void
{
    $title    = (string) $cfg['title'];
    $tableId  = (string) $cfg['table_id'];
    $search   = (string) ($cfg['search'] ?? '');
    $icon     = (string) ($cfg['icon'] ?? 'fa-list');
    $columns  = $cfg['columns'];
    $actions  = $cfg['actions'] ?? null;
    $success  = isset($_GET['success']) ? (string) $_GET['success'] : '';
    $error    = isset($_GET['error']) ? (string) $_GET['error'] : '';
    ?>
<div class="erp-flex erp-justify-between erp-align-center erp-mt-2" style="flex-wrap:wrap;gap:12px;">
    <div>
        <h1 class="erp-page-title"><i class="fas <?php echo al_h($icon); ?>" style="color:#F5A623;margin-right:8px;"></i><?php echo al_h($title); ?></h1>
        <?php if (!empty($cfg['subtitle'])): ?>
            <p style="margin:4px 0 0;color:#64748b;font-size:14px;"><?php echo al_h($cfg['subtitle']); ?></p>
        <?php endif; ?>
    </div>
    <div class="erp-page-actions" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <?php if (empty($cfg['no_search'])): ?>
        <form method="get" style="display:flex;gap:8px;align-items:center;margin:0;">
            <?php foreach (($cfg['keep_params'] ?? []) as $k => $v): ?>
                <input type="hidden" name="<?php echo al_h($k); ?>" value="<?php echo al_h($v); ?>">
            <?php endforeach; ?>
            <input type="text" name="q" value="<?php echo al_h($search); ?>" class="erp-input erp-input-sm"
                   placeholder="<?php echo al_h($cfg['search_placeholder'] ?? 'Search...'); ?>" aria-label="Search">
            <button type="submit" class="erp-btn erp-btn-secondary erp-btn-sm"><i class="fas fa-search"></i></button>
            <?php if ($search !== ''): ?>
                <a href="<?php echo al_h(strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?')); ?>" class="erp-btn erp-btn-secondary erp-btn-sm">Clear</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>
        <?php echo (string) ($cfg['header_html'] ?? ''); ?>
        <?php if (!empty($cfg['add_url'])): ?>
            <a href="<?php echo al_h($cfg['add_url']); ?>" class="erp-btn erp-btn-primary">
                <i class="fas fa-plus"></i> <?php echo al_h($cfg['add_label'] ?? 'Add'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($success !== ''): ?>
    <div class="erp-card erp-mt-2" style="border-left:4px solid #16a34a;background:#f0fdf4;"><div class="erp-card-body" style="color:#166534;"><i class="fas fa-check-circle"></i> <?php echo al_h($success); ?></div></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="erp-card erp-mt-2" style="border-left:4px solid #dc2626;background:#fef2f2;"><div class="erp-card-body" style="color:#991b1b;"><i class="fas fa-exclamation-circle"></i> <?php echo al_h($error); ?></div></div>
<?php endif; ?>

<div class="erp-card erp-mt-2">
    <div class="erp-card-body" style="padding:0;">
        <div class="erp-table-container qt-table-scroll">
            <table class="erp-table erp-student-table" id="<?php echo al_h($tableId); ?>" data-st-row-selector="tr[data-al-row=&quot;1&quot;]">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <?php echo ($col['sort'] ?? true)
                                ? st_sortable_th((string) $col['label'], (string) ($col['class'] ?? ''))
                                : st_plain_th((string) $col['label'], (string) ($col['class'] ?? '')); ?>
                        <?php endforeach; ?>
                        <?php if ($actions): ?>
                            <?php echo st_plain_th('Actions', 'st-col-actions st-no-row-nav'); ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr><td colspan="<?php echo count($columns) + ($actions ? 1 : 0); ?>" style="text-align:center;padding:36px;color:#64748b;">
                        <i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:.5;"></i>
                        <?php echo al_h($cfg['empty'] ?? 'Nothing here yet.'); ?>
                    </td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr data-al-row="1">
                        <?php foreach ($columns as $col):
                            $cell = (string) ($col['value'])($row);
                            if (empty($col['html'])) {
                                $cell = al_h($cell);
                            }
                            ?>
                            <td<?php echo !empty($col['class']) ? ' class="' . al_h($col['class']) . '"' : ''; ?>><div class="qt-td-inner"><?php echo $cell; ?></div></td>
                        <?php endforeach; ?>
                        <?php if ($actions): echo al_actions_cell($actions($row)); endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($rows !== []) { st_render_table_footer($tableId); } ?>
    </div>
</div>
    <?php
}

/**
 * Icon-button action cell. Keys: view, edit, print (urls); delete (url) with delete_confirm;
 * approve (url); extra: list of ['url','icon','title','confirm'?].
 *
 * @param array<string,mixed> $a
 */
function al_actions_cell(array $a): string
{
    $btn = static function (string $url, string $icon, string $title, string $cls, ?string $confirm = null, bool $blank = false): string {
        $onclick = $confirm !== null
            ? ' onclick="return confirm(' . al_h(json_encode($confirm, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) . ');"'
            : '';
        return '<a href="' . al_h($url) . '" class="st-act-btn ' . $cls . '" title="' . al_h($title) . '" aria-label="' . al_h($title) . '"'
            . ($blank ? ' target="_blank" rel="noopener"' : '') . $onclick . '><i class="fas ' . $icon . '" aria-hidden="true"></i></a>';
    };

    $html = '<td class="st-col-actions st-no-row-nav"><div class="qt-td-inner qt-td-inner--center"><div class="st-actions">';
    if (!empty($a['view']))    { $html .= $btn((string) $a['view'], 'fa-eye', 'View', 'st-act-btn--view'); }
    if (!empty($a['edit']))    { $html .= $btn((string) $a['edit'], 'fa-pen', 'Edit', 'st-act-btn--edit'); }
    if (!empty($a['print']))   { $html .= $btn((string) $a['print'], 'fa-print', 'Print', 'st-act-btn--print', null, true); }
    if (!empty($a['approve'])) { $html .= $btn((string) $a['approve'], 'fa-check', 'Approve', 'st-act-btn--view', (string) ($a['approve_confirm'] ?? 'Approve this item?')); }
    foreach (($a['extra'] ?? []) as $x) {
        $html .= $btn((string) $x['url'], (string) ($x['icon'] ?? 'fa-link'), (string) ($x['title'] ?? ''), 'st-act-btn--view', $x['confirm'] ?? null, !empty($x['blank']));
    }
    if (!empty($a['delete']))  { $html .= $btn((string) $a['delete'], 'fa-trash-alt', 'Delete', 'st-act-btn--delete', (string) ($a['delete_confirm'] ?? 'Move this item to the recycle bin?')); }
    return $html . '</div></div></td>';
}
