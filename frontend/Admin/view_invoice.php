<?php
/**
 * Legacy URL — redirect to invoice view in Invoice module.
 */
$invoiceId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'Invoice/view_invoice.php' . ($query !== '' ? '?' . $query : ($invoiceId > 0 ? '?id=' . $invoiceId : ''));
header('Location: ' . $target, true, 302);
exit;
