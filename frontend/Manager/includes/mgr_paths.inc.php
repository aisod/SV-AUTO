<?php
/**
 * Web paths for Manager portal pages (works from Manager/ and subfolders).
 */
declare(strict_types=1);

if (!function_exists('mgr_portal_paths')) {
    function mgr_portal_paths(): array
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $parts = array_values(array_filter(explode('/', $script)));
        $mgrIdx = array_search('Manager', $parts, true);
        if ($mgrIdx === false) {
            $cached = ['rel' => '', 'base' => '/Manager/'];
            return $cached;
        }

        $after = array_slice($parts, $mgrIdx + 1);
        $depth = max(0, count($after) - 1);
        $rel = $depth > 0 ? str_repeat('../', $depth) : '';
        $base = '/' . implode('/', array_slice($parts, 0, $mgrIdx + 1)) . '/';
        $cached = ['rel' => $rel, 'base' => $base];

        return $cached;
    }
}

if (!function_exists('mgr_href')) {
    /**
     * Href for Manager portal assets and routes.
     * Layout uses <base href=".../Manager/"> — in-portal paths are relative to that root,
     * not the current script folder (do not prefix ../js/ from Quotation/, etc.).
     */
    function mgr_href(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '#') {
            return '#';
        }
        if (preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'mailto:')) {
            return $path;
        }
        if (str_starts_with($path, '../') || str_starts_with($path, '/')) {
            return $path;
        }

        return ltrim($path, '/');
    }
}

/** Sidebar/menu links — always under /Manager/, never /Admin/. */
if (!function_exists('mgr_nav_href')) {
    function mgr_nav_href(string $path): string
    {
        return mgr_href($path);
    }
}

/** Absolute app path (ignores &lt;base&gt; in Manager layout). */
if (!function_exists('mgr_app_url')) {
    function mgr_app_url(string $path = ''): string
    {
        return app_url($path);
    }
}

/** Notification / external links for Manager header dropdown. */
if (!function_exists('mgr_notif_link')) {
    function mgr_notif_link(string $link): string
    {
        $link = trim($link);
        if ($link === '' || $link === '#') {
            return '#';
        }
        if (preg_match('#^https?://#i', $link) || str_starts_with($link, '/')) {
            return $link;
        }
        if (str_starts_with($link, 'Manager/')) {
            return mgr_app_url($link);
        }

        return mgr_href($link);
    }
}
