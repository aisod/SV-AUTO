<?php
require_once __DIR__ . '/../../../backend/config/config.php';
const Q_JSON_MARKER = "QUOTATION_JSON_V1\n";
function aq_parse_quotation_details(?string $details): ?array {
    if ($details === null || $details === '') return null;
    if (strpos($details, Q_JSON_MARKER) !== 0) return null;
    $json = substr($details, strlen(Q_JSON_MARKER));
    $summaryPos = strpos($json, "\n\n— Line summary —");
    if ($summaryPos !== false) $json = substr($json, 0, $summaryPos);
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}
$editId = 37;
$sqlJc = "SELECT jc.id, jc.card_number, jc.extra_data, jc.created_at AS jc_created_at,
           c.name AS client_name, c.phone AS client_phone,
           c.email AS client_email, c.address AS client_address,
           v.reg_no, v.model, v.vin_no
    FROM job_cards jc
    LEFT JOIN clients c ON jc.client_id = c.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE jc.deleted_at IS NULL AND (
        NOT EXISTS (SELECT 1 FROM quotations q WHERE q.job_card_id = jc.id AND q.deleted_at IS NULL)
        OR EXISTS (SELECT 1 FROM quotations q2 WHERE q2.id = ? AND q2.job_card_id = jc.id)
    ) ORDER BY jc.id DESC";
$jcStmt = $pdo->prepare($sqlJc);
$jcStmt->execute([$editId]);
$job_cards = $jcStmt->fetchAll(PDO::FETCH_ASSOC);
$enc = json_encode($job_cards, JSON_UNESCAPED_UNICODE);
echo "job_cards count=" . count($job_cards) . "\n";
echo "job_cards encode=" . ($enc !== false ? 'ok len=' . strlen($enc) : 'FAIL ' . json_last_error_msg()) . "\n";
if ($enc && strpos($enc, '</script>') !== false) echo "WARN job_cards has </script>\n";
$qStmt = $pdo->prepare('SELECT q.*, jc.card_number FROM quotations q LEFT JOIN job_cards jc ON jc.id = q.job_card_id WHERE q.id = ?');
$qStmt->execute([$editId]);
$qRow = $qStmt->fetch(PDO::FETCH_ASSOC);
$saved = aq_parse_quotation_details($qRow['details'] ?? '');
$enc2 = json_encode($saved, JSON_UNESCAPED_UNICODE);
echo "payload encode=" . ($enc2 !== false ? 'ok' : 'FAIL ' . json_last_error_msg()) . "\n";
if ($enc2 && strpos($enc2, '</script>') !== false) echo "WARN payload has </script>\n";
