<?php
declare(strict_types=1);

/**
 * Manager quotation preview/PDF — paper sign-off Date line always shows today.
 */
function mgr_quotation_inject_paper_signoff_date(string $html): string
{
    $today = htmlspecialchars(date('d M Y'), ENT_QUOTES, 'UTF-8');

    $pattern = '/(<span style="min-width:60px;">Date<\/span>\s*<span style="flex:1;border-bottom:1px solid #000;padding-left:6px;font-weight:bold;color:#111;">)(\s*)(<\/span>)/i';
    $out = preg_replace($pattern, '$1' . $today . '$3', $html, 1, $count);
    if ($count > 0 && is_string($out)) {
        return $out;
    }

    $fallback = '/(<span[^>]*>Date<\/span>\s*<span[^>]*border-bottom[^>]*>)(\s*)(<\/span>)/i';
    $out = preg_replace($fallback, '$1' . $today . '$3', $html, 1, $count);

    return is_string($out) ? $out : $html;
}
