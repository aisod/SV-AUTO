<?php
/**
 * Manager — job card printable preview (no Admin chrome).
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

$role = strtolower((string) ($_SESSION['role_name'] ?? ''));
if (!isset($_SESSION['user_id']) || !in_array($role, ['admin', 'manager'], true)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid job card';
    exit;
}

$stmt = $pdo->prepare("
    SELECT jc.*,
           COALESCE(jc_client.name, v_client.name, 'Walk-in Client') AS client_name,
           COALESCE(jc_client.phone, v_client.phone, '') AS client_phone,
           COALESCE(jc_client.email, v_client.email, '') AS client_email,
           COALESCE(v.reg_no, '') AS reg_no,
           COALESCE(v.model, '') AS model,
           e.name AS technician_name
    FROM job_cards jc
    LEFT JOIN clients jc_client ON jc.client_id = jc_client.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    LEFT JOIN clients v_client ON v.client_id = v_client.id
    LEFT JOIN employees e ON jc.technician_id = e.id
    WHERE jc.id = :id AND jc.deleted_at IS NULL
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    echo 'Job card not found';
    exit;
}

$extra = json_decode($job['extra_data'] ?? '{}', true);
if (!is_array($extra)) {
    $extra = [];
}

$toArray = static function ($value): array {
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    foreach ($value as $v) {
        $s = trim((string) $v);
        if ($s !== '') {
            $out[] = $s;
        }
    }
    return $out;
};

$descriptionLines = $toArray($extra['description_lines'] ?? []);
$partsLeft = $toArray($extra['parts_left'] ?? []);
$partsRight = $toArray($extra['parts_right'] ?? []);
$workDetails = $toArray($extra['work_details'] ?? []);
$timeAllocated = $toArray($extra['time_allocated'] ?? []);

$cardNo = trim((string) ($job['card_number'] ?? ''));
$dateVal = !empty($job['created_at']) ? date('d/m/Y', strtotime((string) $job['created_at'])) : '';

$scriptParts = array_values(array_filter(explode('/', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')))));
$mgrIdx = array_search('Manager', $scriptParts, true);
$publicBase = $mgrIdx !== false
    ? '/' . implode('/', array_slice($scriptParts, 0, $mgrIdx)) . '/assets/images/companylogo2.png'
    : '/assets/images/companylogo2.png';
$logoUrl = $publicBase;

$h = static function ($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};

$fixedRows = static function (array $left, array $right, int $count) use ($h): string {
    $html = '';
    for ($i = 0; $i < $count; $i++) {
        $l = $left[$i] ?? '';
        $r = $right[$i] ?? '';
        $html .= '<tr><td><span class="val-inline">' . $h($l) . '</span></td><td><span class="val-inline">' . $h($r) . '</span></td></tr>';
    }
    return $html;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Card <?php echo $h($cardNo); ?></title>
  <style>
    *{box-sizing:border-box}
    body{margin:0;font-family:Arial,sans-serif;background:#fff9c4;color:#111}
    .wrap{padding:10px}
    .page{width:100%;max-width:860px;margin:0 auto;background:#fff9c4;border:2px solid #111;padding:6px}
    .header{display:grid;grid-template-columns:335px 1fr;align-items:center;gap:12px;margin-top:6px}
    .header img{max-width:320px;height:auto}
    .hdr-txt{text-align:center;font-size:16px;font-weight:700;line-height:1.3;padding-right:6px;position:relative;top:-6px}
    .title{position:relative;text-align:center;margin:14px 0 8px;min-height:56px}
    .title-main{font-size:42px;font-weight:900;letter-spacing:1.5px;line-height:1;display:inline-block}
    .title-no{position:absolute;right:14px;top:56%;transform:translateY(-50%);font-size:18px;font-weight:800}
    .title-no-val{color:#b91c1c;font-weight:900}
    table{width:100%;border-collapse:collapse;table-layout:fixed}
    td,th{border:1px solid #111;padding:2px 4px;font-size:12px;height:22px;vertical-align:middle}
    .tight td,.tight th{height:20px;font-size:11px}
    .main-info td{height:24px;font-size:12px}
    .attr{font-weight:400;padding-left:6px}
    .val{padding-left:8px;font-weight:800}
    .sec{font-weight:900;text-align:center;text-transform:uppercase;letter-spacing:1px}
    .cond{font-size:9px;line-height:1.25;border:1px solid #111;border-top:none;padding:4px}
    .page-break{page-break-before:always}
    .val-inline{font-weight:800 !important}
  </style>
</head>
<body>
<div class="wrap">
  <div class="page">
    <div class="header">
      <img src="<?php echo $h($logoUrl); ?>" alt="SV Auto logo"/>
      <div class="hdr-txt">
        Lafrenz Industrial · Rensburger Street · Erf 174LL · Unit 18<br>
        Cell: +264 81 44 9962 · svautotruckrepairs@gmail.com<br>
        PO Box 21292 · Windhoek · Namibia<br>
        Reg No. cc/2015/1378
      </div>
    </div>
    <div class="title">
      <span class="title-main">JOB CARD</span>
      <span class="title-no">No.&nbsp;<span class="title-no-val"><?php echo $h($cardNo); ?></span></span>
    </div>
    <table class="tight main-info">
      <colgroup><col style="width:12%"><col style="width:38%"><col style="width:18%"><col style="width:32%"></colgroup>
      <tr><td class="attr" colspan="2">To</td><td class="attr">Date</td><td class="val"><?php echo $h($dateVal); ?></td></tr>
      <tr><td class="val" colspan="2"><?php echo $h($job['client_name'] ?? ''); ?></td><td class="attr">VIN No.</td><td class="val"><?php echo $h($extra['vin_no'] ?? ''); ?></td></tr>
      <tr><td colspan="2"></td><td class="attr">Kilometres</td><td class="val"><?php echo $h($extra['kilometre'] ?? ''); ?></td></tr>
      <tr><td colspan="2"></td><td class="attr">Fleet No.</td><td class="val"><?php echo $h($extra['fleet_no'] ?? ''); ?></td></tr>
      <tr><td class="attr">Contact No.</td><td class="val"><?php echo $h($extra['contact_no'] ?? $job['client_phone'] ?? ''); ?></td><td class="attr">Vehicle Reg. No.</td><td class="val"><?php echo $h($job['reg_no'] ?? ''); ?></td></tr>
      <tr><td></td><td></td><td class="attr">Model</td><td class="val"><?php echo $h($extra['model_reg'] ?? $job['model'] ?? ''); ?></td></tr>
      <tr><td class="attr">Email Address</td><td class="val"><?php echo $h($extra['contact_email'] ?? $job['client_email'] ?? ''); ?></td><td class="attr">Purchase Order No.</td><td class="val"><?php echo $h($extra['purchase_order_no'] ?? ''); ?></td></tr>
      <tr><td></td><td></td><td class="attr">Quotation No.</td><td class="val"><?php echo $h($extra['quotation_no'] ?? ''); ?></td></tr>
      <tr><td class="attr">Contact person</td><td class="val"><?php echo $h($extra['contact_person'] ?? ''); ?></td><td class="attr">Invoice No.</td><td class="val"><?php echo $h($extra['invoice_no'] ?? ''); ?></td></tr>
      <tr><td></td><td></td><td></td><td></td></tr>
    </table>
    <div style="height:14px;"></div>
    <table class="tight"><tr><th class="sec">Description</th></tr></table>
    <table class="tight"><?php echo $fixedRows($descriptionLines, array_fill(0, 20, ''), 20); ?></table>
    <div style="height:14px;"></div>
    <table class="tight"><tr><th class="sec" colspan="2">Parts Supply</th></tr></table>
    <table class="tight"><?php echo $fixedRows($partsLeft, $partsRight, 18); ?></table>
    <div class="cond"><strong>Conditions of Service:</strong> By signing this Job Card, the customer gives our mechanic authorization to inspect, test drive and diagnosis on vehicle.</div>
  </div>
  <div class="page page-break">
    <table class="tight">
      <tr><th class="sec" style="width:80%">Work Details</th><th class="sec" style="width:20%">Time Allocated</th></tr>
      <?php echo $fixedRows($workDetails, $timeAllocated, 28); ?>
    </table>
    <table class="tight" style="margin-top:8px;">
      <tr><th class="sec" colspan="2">Call Out of Town</th></tr>
      <tr><td style="width:40%">Kilometres</td><td><span class="val-inline"><?php echo $h($extra['callout_km'] ?? ''); ?></span></td></tr>
      <tr><td>Call out fee</td><td><span class="val-inline"><?php echo $h($extra['callout_fee'] ?? ''); ?></span></td></tr>
      <tr><td>Consumables</td><td><span class="val-inline"><?php echo $h($extra['callout_consumables'] ?? ''); ?></span></td></tr>
    </table>
    <table class="tight" style="margin-top:8px;">
      <tr>
        <td>Normal Time: <span class="val-inline"><?php echo $h($extra['normal_time'] ?? ''); ?></span></td>
        <td>Overtime: <span class="val-inline"><?php echo $h($extra['overtime'] ?? ''); ?></span></td>
        <td>Sunday / Public Holiday: <span class="val-inline"><?php echo $h($extra['sunday_holiday'] ?? ''); ?></span></td>
      </tr>
      <tr>
        <td>Technician Name: <span class="val-inline"><?php echo $h($extra['technician_no'] ?? $job['technician_name'] ?? ''); ?></span></td>
        <td colspan="2">Customer signature: <span class="val-inline"><?php echo $h($extra['customer_sig'] ?? ''); ?></span></td>
      </tr>
    </table>
  </div>
</div>
</body>
</html>
