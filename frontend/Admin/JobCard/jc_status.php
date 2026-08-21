<?php
/**
 * Job card status helpers — matches DB ENUM (migrations/add_job_card_progress.sql).
 */
function jc_status_open_sql(string $alias = 'jc'): string
{
    $p = preg_replace('/[^a-z_]/', '', $alias) ?: 'jc';
    return "({$p}.status IS NULL OR {$p}.status = '' OR {$p}.status NOT IN ('completed', 'invoiced', 'paid'))";
}

function jc_status_pending_sql(string $alias = 'jc'): string
{
    $p = preg_replace('/[^a-z_]/', '', $alias) ?: 'jc';
    return "({$p}.status = 'new' OR {$p}.status IS NULL OR {$p}.status = '')";
}

function jc_status_in_progress_sql(string $alias = 'jc'): string
{
    $p = preg_replace('/[^a-z_]/', '', $alias) ?: 'jc';
    return "{$p}.status IN ('in_progress', 'waiting_parts')";
}

function jc_status_complete_sql(string $alias = 'jc'): string
{
    $p = preg_replace('/[^a-z_]/', '', $alias) ?: 'jc';
    return "{$p}.status IN ('completed', 'invoiced', 'paid')";
}

function jc_status_normalize(?string $raw): string
{
    $s = strtolower(trim((string) $raw));
    if ($s === '' || $s === 'pending') {
        return 'new';
    }
    if ($s === 'complete') {
        return 'completed';
    }
    return $s;
}

function jc_status_label(string $status): string
{
    $labels = [
        'new' => 'New',
        'diagnosed' => 'Diagnosed',
        'quoted' => 'Quoted',
        'approved' => 'Approved',
        'in_progress' => 'In progress',
        'waiting_parts' => 'Waiting parts',
        'completed' => 'Completed',
        'invoiced' => 'Invoiced',
        'paid' => 'Paid',
        'pending' => 'New',
        'complete' => 'Completed',
    ];
    $s = jc_status_normalize($status);
    return $labels[$s] ?? ucfirst(str_replace('_', ' ', $s));
}

function jc_status_css_class(string $status): string
{
    $s = jc_status_normalize($status);
    $map = [
        'new' => 'jc-status-new',
        'diagnosed' => 'jc-status-diagnosed',
        'quoted' => 'jc-status-quoted',
        'approved' => 'jc-status-approved',
        'in_progress' => 'jc-status-in_progress',
        'waiting_parts' => 'jc-status-waiting_parts',
        'completed' => 'jc-status-complete',
        'invoiced' => 'jc-status-invoiced',
        'paid' => 'jc-status-paid',
    ];
    return $map[$s] ?? 'jc-status-default';
}

/** MySQL YEARWEEK(date, 1) — Monday week, week 1 has 4+ days (matches overview SQL). */
function jc_yearweek_mode1(string $ymd): int
{
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$dt) {
        return 0;
    }
    return (int) ($dt->format('o') . $dt->format('W'));
}

function jc_status_row_matches_filter(string $rowStatus, string $filter): bool
{
    $s = jc_status_normalize($rowStatus);
    if ($filter === '' || $filter === 'all') {
        return true;
    }
    if ($filter === 'open') {
        return !in_array($s, ['completed', 'invoiced', 'paid'], true);
    }
    if ($filter === 'pending') {
        return $s === 'new';
    }
    if ($filter === 'in_progress') {
        return in_array($s, ['in_progress', 'waiting_parts'], true);
    }
    if ($filter === 'complete') {
        return in_array($s, ['completed', 'invoiced', 'paid'], true);
    }
    return $s === $filter;
}
