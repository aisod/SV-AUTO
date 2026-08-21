<?php
declare(strict_types=1);

/**
 * Manager document preview — shell CSS (iframe HTML) and CRM summary card headers.
 * Keeps QUOTATION / Tax Invoice title inside the letterhead (matches Admin live preview top:4px).
 */

if (!function_exists('mgr_doc_preview_shell_css')) {
    function mgr_doc_preview_shell_css(): string
    {
        return <<<'CSS'
html,body{min-height:100%;height:auto}
body{display:flex;justify-content:center;align-items:flex-start;padding:1.25rem 1rem 2rem;background:#e2e8f0;box-sizing:border-box}
#invoice-print-inner,#aq-print-inner{width:210mm;max-width:calc(100vw - 2rem);min-height:0;margin:0 auto;padding:10mm 12mm;background:#fff;color:#000;font-family:Arial,Helvetica,sans-serif;font-size:9pt;line-height:1.35;box-sizing:border-box;box-shadow:0 4px 28px rgba(15,23,42,.14);border:1px solid #94a3b8}
#invoice-print-inner img,#aq-print-inner img{max-width:100%;height:auto;display:block}
#invoice-print-inner .aq-doc-header-wrap,#aq-print-inner .aq-doc-header-wrap{position:relative;overflow:visible}
#invoice-print-inner .aq-doc-title-badge,#aq-print-inner .aq-doc-title-badge{top:5px!important;bottom:auto!important;right:10px!important;background:transparent!important;padding:0!important;letter-spacing:3px!important;line-height:1!important}
@media print{
  @page{size:A4 portrait;margin:8mm}
  html,body{margin:0!important;padding:0!important;background:#fff!important;min-height:0!important;height:auto!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{display:block!important}
  #invoice-print-inner,#aq-print-inner{
    width:100%!important;max-width:none!important;min-height:auto!important;
    margin:0!important;padding:0!important;
    background:#fff!important;color:#000!important;
    box-shadow:none!important;border:none!important;
  }
  #invoice-print-inner .aq-doc-parts-section{page-break-before:auto}
  #invoice-print-inner .aq-doc-footer-block,#aq-print-inner .aq-doc-footer-block{page-break-inside:avoid}
}
CSS;
    }
}

if (!function_exists('mgr_crm_summary_card_css')) {
    function mgr_crm_summary_card_css(): string
    {
        return <<<'CSS'
.mgr-crm-doc-view .mgr-crm-summary-card.crm-inv-summary{padding:0;overflow:hidden}
.mgr-crm-doc-view .mgr-crm-summary-card .crm-inv-summary-hd{margin-bottom:0;padding:14px 16px;border-bottom:1px solid #f1f5f9}
.mgr-crm-doc-view .mgr-crm-summary-card .crm-inv-summary-hd h1{margin:0;font-size:15px;font-weight:700;color:#111827;display:flex;align-items:center;gap:8px;line-height:1.3}
.mgr-crm-doc-view .mgr-crm-summary-card .crm-inv-summary-hd h1 i{color:#64748b;font-size:14px}
.mgr-crm-doc-view .mgr-crm-summary-bd{padding:18px 20px}
.mgr-crm-doc-view .crm-cust-meta-item dt{color:#334155;font-weight:700}
.mgr-crm-doc-view .crm-cust-meta-item dd{color:#0f172a;font-weight:600}
.mgr-crm-doc-view .crm-cust-addr-line--muted{color:#475569}
CSS;
    }
}

if (!function_exists('mgr_stream_doc_pdf_from_html')) {
    function mgr_stream_doc_pdf_from_html(string $html, string $filename): void
    {
        if ($html === '' || (stripos($html, 'aq-print-inner') === false && stripos($html, 'invoice-print-inner') === false)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Invalid document content';
            exit;
        }

        if (!class_exists(\Dompdf\Dompdf::class)) {
            $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
        }
        if (!class_exists(\Dompdf\Dompdf::class)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'PDF library not available';
            exit;
        }

        $filename = preg_replace('/[^\w\-]+/', '_', $filename);
        if ($filename === '') {
            $filename = 'document';
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 120);
        $options->set('defaultMediaType', 'print');
        $chroot = realpath(dirname(__DIR__, 2));
        if ($chroot !== false) {
            $options->setChroot($chroot);
        }

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
    }
}
