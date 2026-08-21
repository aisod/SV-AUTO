<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Admin/JobCard/jc_status.php';

if (!function_exists('mgr_jc_list_date_cell')) {
    function mgr_jc_list_date_cell(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '-';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '-';
        }
        $date = date('d M Y', $ts);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($raw))) {
            return htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
        }
        return htmlspecialchars($date, ENT_QUOTES, 'UTF-8')
            . ' <span class="qt-list-time">' . htmlspecialchars(date('H:i', $ts), ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('mgr_jc_in_period')) {
    function mgr_jc_in_period(?string $raw, string $period): bool
    {
        if ($raw === null || trim($raw) === '') {
            return false;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return false;
        }
        $ymd = date('Y-m-d', $ts);
        switch ($period) {
            case 'today':
                return $ymd === date('Y-m-d');
            case 'week':
                $dt = new DateTime($ymd);
                $now = new DateTime(date('Y-m-d'));
                return $dt->format('oW') === $now->format('oW');
            case 'month':
                return date('Y-m', $ts) === date('Y-m');
            default:
                return true;
        }
    }
}

if (!function_exists('mgr_jc_progress_dots')) {
    /** @return list<array{done: bool, current: bool}> */
    function mgr_jc_progress_dots(string $status): array
    {
        $s = jc_status_normalize($status);
        $step = 0;
        if (in_array($s, ['diagnosed', 'quoted', 'approved'], true)) {
            $step = 1;
        } elseif (in_array($s, ['in_progress', 'waiting_parts'], true)) {
            $step = 2;
        } elseif (in_array($s, ['completed', 'invoiced', 'paid'], true)) {
            $step = 3;
        }
        $labels = ['Logged', 'Quoted', 'Work', 'Done'];
        $dots = [];
        foreach ($labels as $i => $_) {
            $dots[] = [
                'done' => $step > $i,
                'current' => $step === $i && $step < 3,
            ];
        }
        return $dots;
    }
}

if (!function_exists('mgr_jc_matches_card')) {
    function mgr_jc_matches_card(array $row, array $card, ?string $createdRaw): bool
    {
        $status = jc_status_normalize((string) ($row['status'] ?? ''));
        $filter = (string) ($card['filter'] ?? '');
        if ($filter === 'open') {
            return !in_array($status, ['completed', 'invoiced', 'paid'], true);
        }
        if ($filter === 'in_progress') {
            return in_array($status, ['in_progress', 'waiting_parts'], true);
        }
        if ($filter === 'complete') {
            return in_array($status, ['completed', 'invoiced', 'paid'], true);
        }
        $period = $card['period'] ?? null;
        if ($period !== null && $period !== '') {
            return mgr_jc_in_period($createdRaw, (string) $period);
        }
        return $filter === '' || $filter === 'all';
    }
}

$listError = isset($_GET['error']) ? trim((string) $_GET['error']) : '';

$summary = ['total_count' => 0, 'today_count' => 0, 'week_count' => 0, 'month_count' => 0];
try {
    $summary = $pdo->query("
        SELECT COUNT(*) AS total_count,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_count,
            COUNT(CASE WHEN YEARWEEK(DATE(created_at), 1) = YEARWEEK(CURDATE(), 1) THEN 1 END) AS week_count,
            COUNT(CASE WHEN YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) THEN 1 END) AS month_count
        FROM job_cards WHERE deleted_at IS NULL
    ")->fetch(PDO::FETCH_ASSOC) ?: $summary;
} catch (Throwable $e) {
    error_log('mgr_job_cards summary: ' . $e->getMessage());
}

$jobCards = [];
try {
    $stmt = $pdo->query("
        SELECT jc.id, jc.card_number, jc.status, jc.created_at, jc.description,
               COALESCE(jc_client.name, v_client.name, 'Walk-in') AS client_name,
               COALESCE(v.reg_no, '') AS reg_no,
               COALESCE(v.model, '') AS model
        FROM job_cards jc
        LEFT JOIN clients jc_client ON jc.client_id = jc_client.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        LEFT JOIN clients v_client ON v.client_id = v_client.id
        WHERE jc.deleted_at IS NULL
        ORDER BY jc.id DESC
        LIMIT 500
    ");
    $jobCards = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('mgr_job_cards list: ' . $e->getMessage());
}

$mgr_jc_open = 0;
$mgr_jc_in_progress = 0;
$mgr_jc_complete = 0;
foreach ($jobCards as $r) {
    $st = jc_status_normalize((string) ($r['status'] ?? ''));
    if (in_array($st, ['completed', 'invoiced', 'paid'], true)) {
        $mgr_jc_complete++;
    } elseif (in_array($st, ['in_progress', 'waiting_parts'], true)) {
        $mgr_jc_in_progress++;
    } else {
        $mgr_jc_open++;
    }
}

$mgr_jc_dashboard_cards = [
    ['tone' => 'amber', 'label' => 'Open jobs', 'value' => (string) $mgr_jc_open, 'sub' => 'Not yet completed', 'icon' => 'fa-folder-open', 'filter' => 'open', 'period' => null, 'list_title' => 'Open job cards'],
    ['tone' => 'blue', 'label' => 'In progress', 'value' => (string) $mgr_jc_in_progress, 'sub' => 'Work underway or waiting parts', 'icon' => 'fa-wrench', 'filter' => 'in_progress', 'period' => null, 'list_title' => 'In progress'],
    ['tone' => 'green', 'label' => 'Completed', 'value' => (string) $mgr_jc_complete, 'sub' => 'Finished · invoiced or paid', 'icon' => 'fa-check-circle', 'filter' => 'complete', 'period' => null, 'list_title' => 'Completed job cards'],
    ['tone' => 'slate', 'label' => 'Today', 'value' => (string) ((int) ($summary['today_count'] ?? 0)), 'sub' => date('d M Y') . ' · new cards', 'icon' => 'fa-calendar-day', 'filter' => null, 'period' => 'today', 'list_title' => 'Created today'],
    ['tone' => 'purple', 'label' => 'This week', 'value' => (string) ((int) ($summary['week_count'] ?? 0)), 'sub' => 'Mon – Sun', 'icon' => 'fa-calendar-week', 'filter' => null, 'period' => 'week', 'list_title' => 'Created this week'],
    ['tone' => 'cyan', 'label' => 'This month', 'value' => (string) ((int) ($summary['month_count'] ?? 0)), 'sub' => date('F Y'), 'icon' => 'fa-calendar-alt', 'filter' => null, 'period' => 'month', 'list_title' => 'Created this month'],
    ['tone' => 'orange', 'label' => 'All job cards', 'value' => (string) ((int) ($summary['total_count'] ?? 0)), 'sub' => 'Full workshop list', 'icon' => 'fa-clipboard-list', 'filter' => 'all', 'period' => null, 'list_title' => 'All job cards'],
];

$mgr_jc_view_base = mgr_nav_href('JobCard/job_card.php?id=');
$mgr_jc_table_colspan = 7;

require_once __DIR__ . '/mgr_ops_docs_helpers.inc.php';
$mgr_jc_folders = mgr_dashboard_cards_to_folders($mgr_jc_dashboard_cards, 'mgr-stat-clickable', 'cards');
$mgr_jc_total = (int) ($summary['total_count'] ?? 0);
$mgr_jc_donut_segments = [
    ['label' => 'Open', 'value' => (string) number_format($mgr_jc_open), 'pct' => $mgr_jc_open, 'color' => '#2563eb'],
    ['label' => 'In progress', 'value' => (string) number_format($mgr_jc_in_progress), 'pct' => $mgr_jc_in_progress, 'color' => '#ea580c'],
    ['label' => 'Completed', 'value' => (string) number_format($mgr_jc_complete), 'pct' => $mgr_jc_complete, 'color' => '#059669'],
];
$mgr_jc_sidebar_bars = ops_docs_pct_bars([
    ['label' => 'Open', 'count' => $mgr_jc_open, 'tone' => 'blue', 'icon' => 'fa-folder-open'],
    ['label' => 'In progress', 'count' => $mgr_jc_in_progress, 'tone' => 'amber', 'icon' => 'fa-wrench'],
    ['label' => 'Completed', 'count' => $mgr_jc_complete, 'tone' => 'green', 'icon' => 'fa-check'],
], max(1, $mgr_jc_total));
$mgr_jc_sidebar_recent = mgr_build_ops_sidebar_recent($pdo, 6);
