<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Admin/includes/operations_docs_layout.inc.php';
require_once __DIR__ . '/mgr_admin_oversight.inc.php';

function mgr_ops_folder_count(mixed $value): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return (int) round($value);
    }
    $raw = trim((string) $value);
    if ($raw === '' || !preg_match('/\d/', $raw)) {
        return 0;
    }
    if (ctype_digit(str_replace([',', ' '], '', $raw))) {
        return (int) str_replace([',', ' '], '', $raw);
    }
    return (int) preg_replace('/[^\d]/', '', $raw);
}

/**
 * @param array<int, array<string, mixed>> $cards
 * @return array<int, array<string, mixed>>
 */
function mgr_dashboard_cards_to_folders(array $cards, string $clickableClass, string $unit = 'items'): array
{
    $folders = [];
    foreach ($cards as $card) {
        $attrs = [];
        $listTitle = trim((string) ($card['list_title'] ?? ''));
        if ($listTitle !== '') {
            $attrs['data-mgr-list-title'] = $listTitle;
            $attrs['data-qt-list-title'] = $listTitle;
        }
        $map = [
            'filter' => 'data-mgr-filter',
            'period' => 'data-mgr-period',
            'digital' => 'data-mgr-digital',
            'payment' => 'data-mgr-payment',
            'tab' => 'data-qt-tab',
            'system_tabs' => 'data-qt-system-tabs',
        ];
        foreach ($map as $key => $attr) {
            if (!empty($card[$key])) {
                $attrs[$attr] = (string) $card[$key];
            }
        }
        if (!empty($card['period'])) {
            $attrs['data-qt-period'] = (string) $card['period'];
        }

        $folders[] = [
            'label' => (string) ($card['label'] ?? ''),
            'count' => mgr_ops_folder_count($card['value'] ?? 0),
            'unit' => $unit,
            'icon' => (string) ($card['icon'] ?? 'fa-folder'),
            'tone' => (string) ($card['tone'] ?? 'orange'),
            'clickable' => true,
            'class' => trim($clickableClass),
            'attrs' => $attrs,
        ];
    }
    return $folders;
}

/**
 * @return array<int, array<string, mixed>>
 */
function mgr_build_ops_sidebar_recent(PDO $pdo, int $limit = 6): array
{
    $recent = [];
    foreach (array_slice(mgr_fetch_admin_activity_feed($pdo, $limit), 0, $limit) as $i => $act) {
        $who = trim((string) ($act['username'] ?? 'User'));
        $href = trim((string) ($act['href'] ?? ''));
        if ($href !== '' && function_exists('mgr_nav_href')) {
            $href = mgr_nav_href($href);
        }
        $recent[] = [
            'href' => $href !== '' ? $href : '#',
            'title' => $who,
            'meta' => trim((string) ($act['label'] ?? 'Activity')),
            'time' => trim((string) ($act['time_label'] ?? '')) !== ''
                ? (string) $act['time_label']
                : ops_docs_time_ago((string) ($act['created_at'] ?? '')),
            'initials' => strtoupper(substr($who, 0, 1)) ?: '•',
            'highlight' => $i === 0,
        ];
    }
    return $recent;
}
