<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_admin();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    die('Invalid job card id.');
}
$blankMode = isset($_GET['blank']) && $_GET['blank'] === '1';
$autoPrint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';
$successMsg = trim((string)($_GET['success'] ?? ''));
$errorMsg = trim((string)($_GET['error'] ?? ''));

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
    die('Job card not found.');
}

$extra = json_decode($job['extra_data'] ?? '{}', true);
if (!is_array($extra)) {
    $extra = [];
}

$toArray = static function ($value): array {
    if (!is_array($value)) return [];
    $out = [];
    foreach ($value as $v) {
        $s = trim((string) $v);
        if ($s !== '') $out[] = $s;
    }
    return $out;
};

$descriptionLines = $toArray($extra['description_lines'] ?? []);
$partsLeft = $toArray($extra['parts_left'] ?? []);
$partsRight = $toArray($extra['parts_right'] ?? []);
$workDetails = $toArray($extra['work_details'] ?? []);
$timeAllocated = $toArray($extra['time_allocated'] ?? []);

if ($blankMode) {
    $descriptionLines = [];
    $partsLeft = [];
    $partsRight = [];
    $workDetails = [];
    $timeAllocated = [];
}

$cardNo = $blankMode ? '' : trim((string)($job['card_number'] ?? ''));
$dateVal = '';
if (!empty($job['created_at'])) {
    $dateVal = date('d/m/Y', strtotime($job['created_at']));
}
$dateVal = $blankMode ? '' : $dateVal;

$logoUrl = '' . app_url('assets/') . 'companylogo2.png';
$pageTitle = $blankMode ? 'Job Card Blank' : 'Job Card Preview';
$business = getBusiness();

$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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

include __DIR__ . '/../includes/header.php';
?>
<style>
*{box-sizing:border-box}
html,body,.content-wrapper,.erp-main,.erp-content,.erp-page-header{background:#fff9c4!important}
.jc-preview-shell{font-family:Arial,sans-serif;background:#fff9c4;color:#111;padding:10px;border-radius:8px}
.wrap{padding:8px}.page{width:100%;max-width:860px;margin:0 auto 10px;background:#fff9c4;border:2px solid #111;padding:6px}
.header{display:grid;grid-template-columns:335px 1fr;align-items:center;gap:12px;margin-top:6px}
.header img{max-width:320px;height:auto}.hdr-txt{text-align:center;font-size:16px;font-weight:700;line-height:1.3;padding-right:6px;position:relative;top:-6px}
.title{position:relative;text-align:center;margin:14px 0 8px;min-height:56px}
.title-main{font-size:42px;font-weight:900;letter-spacing:1.5px;line-height:1;display:inline-block}
.title-no{position:absolute;right:14px;top:56%;transform:translateY(-50%);font-size:18px;font-weight:800}
.title-no-val{color:#b91c1c;font-weight:900}
table{width:100%;border-collapse:collapse;table-layout:fixed} td,th{border:1px solid #111;padding:2px 4px;font-size:12px;height:22px;vertical-align:middle}
.tight td,.tight th{height:20px;font-size:11px}
.tight td:not(.val){font-weight:400 !important;}
.main-info td{height:24px;font-size:12px}
.attr{font-weight:400;padding-left:6px}.val{padding-left:8px;font-weight:800}.sec{font-weight:900;text-align:center;text-transform:uppercase;letter-spacing:1px}
.cond{font-size:9px;line-height:1.25;border:1px solid #111;border-top:none;padding:4px}
.page-break{page-break-before:always}
.val-inline{font-weight:800 !important;}
.tools{max-width:860px;margin:14px auto 8px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap}
.tool-group{display:flex;gap:8px;flex-wrap:wrap}
.jc-flash-wrap{
  position:fixed;inset:0;z-index:2100;display:flex;align-items:center;justify-content:center;
  background:rgba(15,23,42,.35);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
  opacity:0;pointer-events:none;transition:opacity .2s ease;
}
.jc-flash-wrap.show{opacity:1;pointer-events:auto}
.jc-flash-card{
  width:min(92vw,420px);background:#fff;border:1px solid #e5e7eb;border-radius:16px;
  box-shadow:0 24px 55px rgba(0,0,0,.25);padding:18px 18px 14px;text-align:center;
  transform:translateY(8px);transition:transform .2s ease;
}
.jc-flash-wrap.show .jc-flash-card{transform:translateY(0)}
.jc-flash-icon{
  width:44px;height:44px;border-radius:999px;margin:0 auto 10px;
  display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;
}
.jc-flash-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
.jc-flash-body{font-size:.9rem;line-height:1.45}
.jc-flash-card--success .jc-flash-icon{background:#ecfdf3;color:#16a34a;border:1px solid #bbf7d0}
.jc-flash-card--success .jc-flash-body{color:#166534}
.jc-flash-card--error .jc-flash-icon{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.jc-flash-card--error .jc-flash-body{color:#991b1b}
@media print{
    .erp-sidebar,.erp-topbar,.erp-page-header,.erp-breadcrumb,.tools{display:none!important}
    .erp-main,.erp-content,.content-wrapper{margin:0!important;padding:0!important;background:#fff!important}
    .jc-preview-shell{padding:0!important;background:#fff!important}
    .page{margin:0 auto 0}
}
</style>
<div class="erp-page-header">
    <div class="erp-breadcrumb">
        <a href="/SV Auto Truck Repair/Admin/dashboard.php"><i class="fas fa-home"></i> Home</a>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Job Card</span>
        <span class="erp-breadcrumb-separator">/</span>
        <span class="erp-breadcrumb-current">Job Card Preview</span>
    </div>
</div>
<div class="jc-preview-shell">
<?php if ($successMsg !== ''): ?>
  <div class="jc-flash-wrap" id="jcFlashWrap">
    <div class="jc-flash-card jc-flash-card--success" id="jcFlashCard">
      <div class="jc-flash-icon"><i class="fas fa-check"></i></div>
      <div class="jc-flash-title">Success</div>
      <div class="jc-flash-body"><?php echo $h($successMsg); ?></div>
    </div>
  </div>
<?php elseif ($errorMsg !== ''): ?>
  <div class="jc-flash-wrap" id="jcFlashWrap">
    <div class="jc-flash-card jc-flash-card--error" id="jcFlashCard">
      <div class="jc-flash-icon"><i class="fas fa-exclamation"></i></div>
      <div class="jc-flash-title">Error</div>
      <div class="jc-flash-body"><?php echo $h($errorMsg); ?></div>
    </div>
  </div>
<?php endif; ?>
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
      <colgroup>
        <col style="width:12%"><col style="width:38%"><col style="width:18%"><col style="width:32%">
      </colgroup>
      <tr><td class="attr" colspan="2">To</td><td class="attr">Date</td><td class="val"><?php echo $h($dateVal); ?></td></tr>
      <tr><td class="val" colspan="2"><?php echo $h($blankMode ? '' : ($job['client_name'] ?? '')); ?></td><td class="attr">VIN No.</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['vin_no'] ?? '')); ?></td></tr>
      <tr><td colspan="2"></td><td class="attr">Kilometres</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['kilometre'] ?? '')); ?></td></tr>
      <tr><td colspan="2"></td><td class="attr">Fleet No.</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['fleet_no'] ?? '')); ?></td></tr>
      <tr><td class="attr">Contact No.</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['contact_no'] ?? $job['client_phone'] ?? '')); ?></td><td class="attr">Vehicle Reg. No.</td><td class="val"><?php echo $h($blankMode ? '' : ($job['reg_no'] ?? '')); ?></td></tr>
      <tr><td></td><td></td><td class="attr">Model</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['model_reg'] ?? $job['model'] ?? '')); ?></td></tr>
      <tr><td class="attr">Email Address</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['contact_email'] ?? $job['client_email'] ?? '')); ?></td><td class="attr">Purchase Order No.</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['purchase_order_no'] ?? '')); ?></td></tr>
      <tr><td></td><td></td><td class="attr">Quotation No.</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['quotation_no'] ?? '')); ?></td></tr>
      <tr><td class="attr">Contact person</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['contact_person'] ?? '')); ?></td><td class="attr">Invoice No.</td><td class="val"><?php echo $h($blankMode ? '' : ($extra['invoice_no'] ?? '')); ?></td></tr>
      <tr><td></td><td></td><td></td><td></td></tr>
    </table>

    <div style="height:14px;"></div>
    <table class="tight"><tr><th class="sec">Description</th></tr></table>
    <table class="tight"><?php echo $fixedRows($descriptionLines, array_fill(0, 20, ''), 20); ?></table>

    <div style="height:14px;"></div>
    <table class="tight"><tr><th class="sec" colspan="2">Parts Supply</th></tr></table>
    <table class="tight"><?php echo $fixedRows($partsLeft, $partsRight, 18); ?></table>

    <div class="cond"><strong>Conditions of Service:</strong> By signing this Job Card, the customer gives our mechanic authorization to inspect, test drive and diagnosis on vehicle. Our mechanics will not be held liable for any valuable goods left in the vehicle by customer or existing faults after vehicle is checked into workshop or at roadside assistance. The problem of the vehicle should be clearly stipulated on the Job Card by client. Customer should be prepared to pay 100% of the invoiced amount when collecting vehicles unless prior arrangements has been made with our finance department.</div>
  </div>

  <div class="page page-break">
    <table class="tight">
      <tr><th class="sec" style="width:80%">Work Details</th><th class="sec" style="width:20%">Time Allocated</th></tr>
      <?php echo $fixedRows($workDetails, $timeAllocated, 28); ?>
    </table>

    <table class="tight" style="margin-top:8px;">
      <tr><th class="sec" colspan="2">Call Out of Town</th></tr>
      <tr><td style="width:40%">Kilometres</td><td><span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['callout_km'] ?? '')); ?></span></td></tr>
      <tr><td>Call out fee</td><td><span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['callout_fee'] ?? '')); ?></span></td></tr>
      <tr><td>Consumables</td><td><span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['callout_consumables'] ?? '')); ?></span></td></tr>
    </table>

    <table class="tight" style="margin-top:8px;">
      <tr>
        <td>Normal Time: <span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['normal_time'] ?? '')); ?></span></td>
        <td>Overtime: <span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['overtime'] ?? '')); ?></span></td>
        <td>Sunday / Public Holiday: <span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['sunday_holiday'] ?? '')); ?></span></td>
      </tr>
      <tr>
        <td>Technician Name: <span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['technician_no'] ?? $job['technician_name'] ?? '')); ?></span></td>
        <td colspan="2">Customer signature: <span class="val-inline"><?php echo $h($blankMode ? '' : ($extra['customer_sig'] ?? '')); ?></span></td>
      </tr>
    </table>
  </div>
</div>
<div class="tools">
    <div class="tool-group">
        <a class="aq-s1-btn aq-s1-btn--gray" href="/SV Auto Truck Repair/Admin/JobCard/job_card_preview.php?id=<?php echo (int)$id; ?>&blank=1&autoprint=1"><i class="fas fa-print"></i> Print Blank</a>
    </div>
    <div class="tool-group">
        <a class="aq-s1-btn aq-s1-btn--blue" href="/SV Auto Truck Repair/Admin/JobCard/add_job_card.php?edit_id=<?php echo (int)$id; ?>"><i class="fas fa-pen"></i> Edit Job Card</a>
    </div>
</div>
</div>
<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 250);
});
</script>
<?php endif; ?>
<script>
window.addEventListener('load', function () {
    const wrap = document.getElementById('jcFlashWrap');
    if (!wrap) return;
    requestAnimationFrame(function () { wrap.classList.add('show'); });
    setTimeout(function () {
        wrap.classList.remove('show');
        setTimeout(function () { wrap.remove(); }, 220);
    }, 3200);
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
