<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../Auth/auth.php';

require_role(['admin', 'manager']);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: quotations.php?error=Invalid Quotation');
    exit;
}

$quote_id = (int)$_GET['id'];
$business = getBusiness();
$currency = getCurrency();
$is_manager = (($_SESSION['role'] ?? '') === 'manager');
$is_admin_user = function_exists('is_admin') ? is_admin() : (($_SESSION['role'] ?? '') === 'admin');

// Absolute URL for creating invoice (reliable regardless of <base> tag)
$createInvoiceUrl = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/Invoice/create_from_quotation.php';

$header_path = __DIR__ . '/../../assets/images/header.png';
$header_base64 = '';
if (file_exists($header_path)) {
    $header_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($header_path));
}

$action_message = '';
$action_error   = '';

// Check for success/error messages from URL parameters
if (isset($_GET['success'])) {
    $action_message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $action_error = $_GET['error'];
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// POST HANDLER
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['post_action'] ?? '';

    // â”€â”€ SEND EMAIL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    if ($post_action === 'send_email') {

        // Full fetch for PDF + email
        $stmt = $pdo->prepare("
            SELECT q.*, jc.card_number, jc.description AS job_description, jc.extra_data,
                c.name AS client_name, c.email AS client_email,
                c.phone AS client_phone, c.address AS client_address,
                v.reg_no, v.model, v.vin_no
            FROM quotations q
            LEFT JOIN job_cards jc ON q.job_card_id = jc.id
            LEFT JOIN vehicles v ON jc.vehicle_id = v.id
            LEFT JOIN clients c ON q.client_id = c.id
            WHERE q.id = ?
        ");
        $stmt->execute([$quote_id]);
        $qdata = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$qdata) {
            $action_error = "Quotation not found.";
        } elseif (empty(trim($qdata['client_email'] ?? ''))) {
            $action_error = "Cannot send â€” this client has no email address on record.";
        } else {
            // Allow override email from modal input, fallback to client email
            $override_email  = trim($_POST['send_to_email'] ?? '');
            $recipient_email = (filter_var($override_email, FILTER_VALIDATE_EMAIL)) ? $override_email : trim($qdata['client_email']);
            $quotation_number = 'QTN-' . str_pad($qdata['id'], 6, '0', STR_PAD_LEFT);
            $currency_symbol  = $currency['symbol'] ?? 'N$';
            $status_colors    = ['pending' => '#d97706', 'approved' => '#2e7d32', 'rejected' => '#c62828'];
            $status_color     = $status_colors[$qdata['status']] ?? '#d97706';
            $clean_details    = strip_tags($qdata['details'] ?? '');

            // Logo base64
            $logo_base64 = '';
            $logo_path   = __DIR__ . '/../assets/images/companylogo.jpeg';
            if (file_exists($logo_path)) {
                $logo_base64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logo_path));
            }

            $pdf_notes = json_decode($qdata['notes'] ?? '{}', true) ?: [];
            $pdf_labour_items = json_decode($qdata['labour_items'] ?? '[]', true);
            $pdf_parts_items = json_decode($qdata['parts_items'] ?? '[]', true);
            if (!is_array($pdf_labour_items)) $pdf_labour_items = [];
            if (!is_array($pdf_parts_items)) $pdf_parts_items = [];
            $pdf_consumables = (float)($qdata['consumables'] ?? 0);
            $pdf_subtotal = (float)($qdata['subtotal'] ?? 0);
            $pdf_vat = (float)($qdata['vat_amount'] ?? ($pdf_subtotal * 0.15));
            $pdf_total = (float)($qdata['total_amount'] ?? $qdata['amount'] ?? 0);

            $pdf_grouped_labour = [
                'normal_hours' => [],
                'holiday' => [],
                'after_hours' => []
            ];
            foreach ($pdf_labour_items as $item) {
                $type = $item['type'] ?? 'normal_hours';
                $desc = trim((string)($item['desc'] ?? ''));
                $hours = (float)($item['hours'] ?? 0);
                $rate = (float)($item['rate'] ?? 0);
                $total = (float)($item['total'] ?? ($hours * $rate));
                if ($desc !== '' && $hours > 0 && $rate > 0) {
                    if (!isset($pdf_grouped_labour[$type])) $pdf_grouped_labour[$type] = [];
                    $pdf_grouped_labour[$type][] = compact('desc', 'hours', 'rate', 'total');
                }
            }

            // â”€â”€ Build PDF HTML using physical SV Auto template â”€â”€
            ob_start();
            ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size:10px; }
    .shell { border:2px solid #000; }
    table { width:100%; border-collapse:collapse; }
    td, th { border:1px solid #000; padding:4px; }
    .noborder { border:none !important; }
    .section-left { width:80px; font-weight:700; text-align:center; }
    .gray { background:#d0d0d0; font-weight:700; }
    .sub-gray { background:#e0e0e0; font-weight:700; }
    .text-right { text-align:right; }
    .text-center { text-align:center; }
    .small { font-size:9px; }
</style>
</head>
<body>
<div class="shell">
    <div style="position:relative; border-bottom:2px solid #000;">
        <?php if (!empty($header_base64)): ?>
            <img src="<?= $header_base64 ?>" style="width:100%; display:block;" alt="Header">
        <?php endif; ?>
        <div style="position:absolute; top:8px; right:14px; font-size:26px; font-weight:900; letter-spacing:3px;">QUOTATION</div>
    </div>
    <table>
        <tr>
            <th colspan="2" class="gray text-center">Customer Details</th>
            <th colspan="2" class="gray text-center">Vehicle Details</th>
        </tr>
        <tr><td><strong>Name</strong></td><td><?= htmlspecialchars($qdata['client_name'] ?? 'Walk-in Client') ?></td><td class="text-right"><strong>Date</strong></td><td><?= date('Y-m-d', strtotime($qdata['submitted_at'])) ?></td></tr>
        <tr><td><strong>Address</strong></td><td><?= htmlspecialchars($qdata['client_address'] ?? '') ?></td><td class="text-right"><strong>Quote Number</strong></td><td><?= htmlspecialchars($quotation_number) ?></td></tr>
        <tr><td class="noborder"></td><td class="noborder"></td><td class="text-right"><strong>Kilometers</strong></td><td><?= htmlspecialchars($pdf_notes['km'] ?? '') ?></td></tr>
        <tr><td class="noborder"></td><td class="noborder"></td><td class="text-right"><strong>Vin No.</strong></td><td><?= htmlspecialchars($pdf_notes['vin'] ?? $qdata['vin_no'] ?? '') ?></td></tr>
        <tr><td class="noborder"></td><td class="noborder"></td><td class="text-right"><strong>Fleet No.</strong></td><td><?= htmlspecialchars($pdf_notes['fleet'] ?? '') ?></td></tr>
        <tr><td class="noborder"></td><td class="noborder"></td><td class="text-right"><strong>Vehicle Reg No.</strong></td><td><?= htmlspecialchars($qdata['reg_no'] ?? '') ?></td></tr>
        <tr><td class="noborder"></td><td class="noborder"></td><td class="text-right"><strong>Model</strong></td><td><?= htmlspecialchars($qdata['model'] ?? '') ?></td></tr>
        <tr><td class="noborder"></td><td class="noborder"></td><td class="text-right"><strong>Job No.</strong></td><td><?= htmlspecialchars($qdata['card_number'] ?? '') ?></td></tr>
        <tr><td class="noborder"></td><td class="noborder"></td><td class="text-right"><strong>Purchase Order</strong></td><td><?= htmlspecialchars($pdf_notes['po'] ?? '') ?></td></tr>
    </table>
    <?php
    $labour_labels = ['normal_hours' => 'Normal Time', 'holiday' => 'Public Holiday', 'after_hours' => 'Overtime'];
    foreach (['normal_hours', 'holiday', 'after_hours'] as $labour_type):
        $rows = $pdf_grouped_labour[$labour_type] ?? [];
        if (empty($rows)) continue;
        $type_total = array_sum(array_column($rows, 'total'));
    ?>
    <table style="margin-top:6px;">
        <tr><td class="section-left" rowspan="<?= count($rows) + 3 ?>"><?= htmlspecialchars($labour_labels[$labour_type]) ?></td><th>Description</th><th style="width:80px;">Hour</th><th style="width:90px;">Rate</th><th style="width:100px;">Total</th></tr>
        <tr class="sub-gray"><td colspan="4">Diagnostic</td></tr>
        <?php foreach ($rows as $row): ?>
            <tr><td><?= htmlspecialchars($row['desc']) ?></td><td class="text-center"><?= number_format($row['hours'], 2) ?></td><td class="text-right"><?= $currency_symbol . number_format($row['rate'], 2) ?></td><td class="text-right"><?= $currency_symbol . number_format($row['total'], 2) ?></td></tr>
        <?php endforeach; ?>
        <tr class="gray"><td colspan="3" class="text-right">Total</td><td class="text-right"><?= $currency_symbol . number_format($type_total, 2) ?></td></tr>
    </table>
    <?php endforeach; ?>

    <table style="margin-top:6px;">
        <tr><td class="section-left" rowspan="<?= max(4, count($pdf_parts_items) + 3) ?>">Parts Supply</td><th>Item Name</th><th style="width:80px;">Qty</th><th style="width:90px;">Unit Cost</th><th style="width:100px;">Total</th></tr>
        <?php foreach ($pdf_parts_items as $part): ?>
            <tr><td><?= htmlspecialchars($part['name'] ?? '') ?></td><td class="text-center"><?= number_format((float)($part['qty'] ?? 0), 2) ?></td><td class="text-right"><?= $currency_symbol . number_format((float)($part['unit_cost'] ?? 0), 2) ?></td><td class="text-right"><?= $currency_symbol . number_format((float)($part['total'] ?? 0), 2) ?></td></tr>
        <?php endforeach; ?>
        <tr><td><strong>Consumables</strong></td><td></td><td></td><td class="text-right"><?= $currency_symbol . number_format($pdf_consumables, 2) ?></td></tr>
        <tr class="gray"><td colspan="3" class="text-right">Total Parts</td><td class="text-right"><?= $currency_symbol . number_format(array_sum(array_map(static function ($i) { return (float)($i['total'] ?? 0); }, $pdf_parts_items)) + $pdf_consumables, 2) ?></td></tr>
    </table>

    <table style="margin-top:6px;">
        <tr>
            <td style="width:35%; vertical-align:top;">
                <div style="border:2px solid red; padding:6px;">
                    <div style="color:red; font-weight:700;">New Banking Details:</div>
                    <div class="small">S.V Auto Truck Repair cc<br>Bank Windhoek Limited<br>Acc: CHK: 8040770120<br>Branch code: 486-372<br>Business Cheque Account</div>
                </div>
            </td>
            <td style="width:30%; vertical-align:top;" class="text-center">
                <div>Approved_______________________</div>
                <div style="margin-top:14px;">Date___________________________</div>
            </td>
            <td style="width:35%; vertical-align:top;">
                <table>
                    <tr><td class="text-right"><strong>Subtotal</strong></td><td class="text-right"><?= $currency_symbol . number_format($pdf_subtotal, 2) ?></td></tr>
                    <tr><td class="text-right"><strong>VAT</strong></td><td class="text-right"><?= $currency_symbol . number_format($pdf_vat, 2) ?></td></tr>
                    <tr class="gray"><td class="text-right"><strong>Total</strong></td><td class="text-right"><strong><?= $currency_symbol . number_format($pdf_total, 2) ?></strong></td></tr>
                </table>
            </td>
        </tr>
    </table>
    <div style="padding:10px; font-size:10px;">
        <div><strong>Note: Unforeseen is not quoted for.</strong></div>
        <div style="text-align:center; margin-top:8px;">Essence of perfection - Thank you for doing business with us</div>
    </div>
</div>
</body>
</html>
            <?php
            $pdf_html = ob_get_clean();

            // â”€â”€ Generate PDF â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            try {
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true);
                $options->set('defaultFont', 'DejaVu Sans');
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($pdf_html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdf_output = $dompdf->output();
            } catch (\Exception $e) {
                $action_error = "Failed to generate PDF: " . $e->getMessage();
                goto skip_send;
            }

            // â”€â”€ Send Email via PHPMailer â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            $mail = new PHPMailer(true);
            try {
                // â”€â”€ SMTP Configuration â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                // IMPORTANT: Replace the values below with your actual SMTP credentials.
                // For Gmail: use an App Password (not your account password).
                //   Gmail App Passwords: https://myaccount.google.com/apppasswords
                // For other providers: update Host, Port, SMTPSecure accordingly.
                // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';           // Your SMTP host
                $mail->SMTPAuth   = true;
                $mail->Username   = 'karlosbrian02@gmail.com';  // Your SMTP username
                $mail->Password   = 'oqwhovsiziqxkhmw';        // Your Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->SMTPDebug  = SMTP::DEBUG_OFF;            // Set to SMTP::DEBUG_SERVER for troubleshooting
                $mail->Timeout    = 30;

                // â”€â”€ Sender & Recipient â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                $from_email = !empty($business['email']) ? $business['email'] : $mail->Username;
                $from_name  = $business['name'] ?? 'SV Auto Services';

                $mail->setFrom($mail->Username, $from_name); // Must use authenticated address as From
                $mail->addAddress($recipient_email, $qdata['client_name'] ?? '');
                if (!empty($business['email']) && $business['email'] !== $mail->Username) {
                    $mail->addReplyTo($business['email'], $from_name);
                }

                // â”€â”€ PDF Attachment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                $mail->addStringAttachment(
                    $pdf_output,
                    $quotation_number . '-Quotation.pdf',
                    'base64',
                    'application/pdf'
                );

                // â”€â”€ Email Content â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
                $mail->isHTML(true);
                $mail->CharSet  = 'UTF-8';
                $expiry_date    = date('d F Y', strtotime(($qdata['submitted_at'] ?? 'now') . ' +30 days'));
                $formatted_amt  = $currency_symbol . number_format((float)$qdata['amount'], 2);
                $client_name_esc = htmlspecialchars($qdata['client_name'] ?? 'Valued Client');
                $from_name       = $business['name'] ?? 'SV Auto Services';
                $biz_name_esc    = htmlspecialchars($from_name);
                $reg_no_esc      = htmlspecialchars($qdata['reg_no'] ?? 'N/A');
                $model_esc       = htmlspecialchars($qdata['model'] ?? 'N/A');
                $biz_phone_esc   = htmlspecialchars($business['phone'] ?? '');
                $biz_email_esc   = htmlspecialchars($business['email'] ?? '');
                $biz_addr_esc    = htmlspecialchars($business['address'] ?? '');
                $biz_tax_esc     = htmlspecialchars($business['tax_number'] ?? '');
                $biz_phone_esc_line = !empty($business['phone'])
                    ? '<p style="margin:4px 0;font-size:13px;opacity:0.9;">&#128222; ' . $biz_phone_esc . '</p>'
                    : '';
                $biz_email_esc_line = !empty($business['email'])
                    ? '<p style="margin:4px 0;font-size:13px;opacity:0.9;">&#9993; ' . $biz_email_esc . '</p>'
                    : '';
                $tax_line = !empty($business['tax_number'])
                    ? ' &bull; Tax No: ' . $biz_tax_esc
                    : '';

                $mail->Subject = "Your Quotation " . $quotation_number . " from " . $from_name;

                $mail->Body = '
<html>
<body style="margin:0;padding:0;background:#f4ede4;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4ede4;padding:30px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);">

      <tr>
        <td style="background:#F5A623;padding:32px 36px;text-align:center;">
          <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:900;letter-spacing:2px;">' . $biz_name_esc . '</h1>
          <p style="margin:8px 0 0;color:rgba(255,255,255,0.8);font-size:12px;">' . $biz_addr_esc . '</p>
        </td>
      </tr>

      <tr>
        <td style="padding:36px 36px 24px;">
          <h2 style="margin:0 0 6px;color: #4a4a4a;font-size:20px;">Quotation ' . $quotation_number . '</h2>
          <p style="margin:0 0 24px;color:#999;font-size:13px;">Please find your quotation attached as a PDF document.</p>
          <p style="margin:0 0 20px;font-size:15px;color:#333;">Dear <strong>' . $client_name_esc . '</strong>,</p>
          <p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#555;">
            Thank you for choosing <strong>' . $biz_name_esc . '</strong>. We have prepared a quotation
            for your vehicle <strong>' . $reg_no_esc . ' (' . $model_esc . ')</strong>.
            Please review the attached PDF and contact us to proceed.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0" style="border:2px solid #FFF8EC;border-radius:10px;overflow:hidden;margin-bottom:24px;">
            <tr style="background:#FFF8F0;">
              <td style="padding:14px 18px;border-bottom:1px solid #f0e0cc;font-size:13px;">
                <span style="color: #4a4a4a;font-weight:700;display:inline-block;width:160px;">Quotation Number</span>
                <span style="color:#333;font-weight:600;">' . $quotation_number . '</span>
              </td>
            </tr>
            <tr style="background:#ffffff;">
              <td style="padding:14px 18px;border-bottom:1px solid #f0e0cc;font-size:13px;">
                <span style="color: #4a4a4a;font-weight:700;display:inline-block;width:160px;">Vehicle</span>
                <span style="color:#333;font-weight:600;">' . $reg_no_esc . ' &mdash; ' . $model_esc . '</span>
              </td>
            </tr>
            <tr style="background:#FFF8F0;">
              <td style="padding:14px 18px;border-bottom:1px solid #f0e0cc;font-size:13px;">
                <span style="color: #4a4a4a;font-weight:700;display:inline-block;width:160px;">Valid Until</span>
                <span style="color:#333;font-weight:600;">' . $expiry_date . '</span>
              </td>
            </tr>
            <tr style="background:#ffffff;">
              <td style="padding:14px 18px;font-size:15px;">
                <span style="color: #4a4a4a;font-weight:700;display:inline-block;width:160px;">Total Amount</span>
                <span style="color:#F5A623;font-size:22px;font-weight:900;">' . $formatted_amt . '</span>
              </td>
            </tr>
          </table>

          <p style="margin:0 0 24px;font-size:13px;color:#666;line-height:1.6;background:#FFF8F0;border-left:4px solid #F5A623;padding:12px 16px;border-radius:4px;">
            &#128206; The full quotation is attached as a PDF. Please open it to view the complete breakdown
            of parts, labour, and services. To proceed, simply reply to this email or call us directly.
          </p>
        </td>
      </tr>

      <tr>
        <td style="padding:0 36px 36px;">
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#F5A623;border-radius:10px;">
            <tr>
              <td style="padding:20px 24px;color:white;">
                <p style="margin:0 0 10px;font-size:15px;font-weight:900;">&#128222; Contact Us</p>
                ' . $biz_phone_esc_line . '
                ' . $biz_email_esc_line . '
                <p style="margin:4px 0;font-size:13px;opacity:0.9;">&#128205; ' . $biz_addr_esc . '</p>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
        <td style="background: #4a4a4a;padding:16px 36px;text-align:center;">
          <p style="margin:0;color:rgba(255,255,255,0.5);font-size:11px;">
            ' . $biz_name_esc . $tax_line . '
          </p>
          <p style="margin:6px 0 0;color:rgba(255,255,255,0.3);font-size:10px;">
            This is an automated email. Please do not reply directly &mdash; use the contact details above.
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';

                // Plain text fallback
                $mail->AltBody =
                    "Quotation " . $quotation_number . "\n\n" .
                    "Dear " . ($qdata['client_name'] ?? 'Client') . ",\n\n" .
                    "Please find your quotation attached.\n\n" .
                    "Quotation No : " . $quotation_number . "\n" .
                    "Vehicle      : " . ($qdata['reg_no'] ?? '') . " â€” " . ($qdata['model'] ?? '') . "\n" .
                    "Total Amount : " . $formatted_amt . "\n" .
                    "Valid Until  : " . $expiry_date . "\n\n" .
                    "Contact us:\n" .
                    ($biz_phone_esc ? "Phone: " . ($business['phone'] ?? '') . "\n" : '') .
                    ($biz_email_esc ? "Email: " . ($business['email'] ?? '') . "\n" : '') .
                    "Address: " . ($business['address'] ?? '') . "\n\n" .
                    $from_name;

                $mail->send();

                $action_message = "âœ… Quotation PDF sent successfully to " . htmlspecialchars($recipient_email);

                // Audit log
                try {
                    $log = $pdo->prepare(
                        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details)
                         VALUES (?, 'sent_quotation', 'quotation', ?, ?)"
                    );
                    $log->execute([
                        $_SESSION['user_id'],
                        $quote_id,
                        "PDF emailed to " . $recipient_email
                    ]);
                } catch (\Exception $logEx) {
                    // Non-critical â€” audit log failure should not block success message
                    error_log("Audit log error: " . $logEx->getMessage());
                }
                
                // Redirect to show success message
                header("Location: view_quotation.php?id={$quote_id}&success=" . urlencode($action_message));
                exit;

            } catch (Exception $e) {
                $action_error = "Failed to send email: " . $mail->ErrorInfo;
                error_log("PHPMailer error for quotation #{$quote_id}: " . $mail->ErrorInfo);
                
                // Redirect to show error message
                header("Location: view_quotation.php?id={$quote_id}&error=" . urlencode($action_error));
                exit;
            }
        }

        skip_send:; // goto target if PDF generation fails

    // â”€â”€ SEND TO PORTAL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    } elseif ($post_action === 'send_to_portal') {
        try {
            // Auto-add column if not exists
            $pdo->exec("ALTER TABLE quotations ADD COLUMN IF NOT EXISTS sent_to_portal TINYINT DEFAULT 0");
            
            // Mark as sent to portal
            $stmt = $pdo->prepare("UPDATE quotations SET sent_to_portal = 1 WHERE id = ?");
            $stmt->execute([$quote_id]);
            $action_message = "âœ… Quotation has been sent to Client Portal.";
            
            // Notify the client
            require_once __DIR__ . '/../../../backend/config/notifications.php';
            $clientUserId = getUserIdByClientId($pdo, $quote['client_id']);
            if ($clientUserId) {
                $quotationNumber = 'QTN-' . str_pad($quote_id, 6, '0', STR_PAD_LEFT);
                createNotification($pdo, $clientUserId, 
                    'New Quotation Available', 
                    "A new quotation {$quotationNumber} has been sent to your portal.", 
                    '../Client/view_quotation.php?id=' . $quote_id
                );
            }
        } catch (\Exception $e) {
            $action_error = "Failed to send to portal: " . $e->getMessage();
        }

    // â”€â”€ APPROVE / REJECT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    } elseif ($is_admin_user || $is_manager) {
        $action        = $_POST['action'] ?? '';
        $valid_actions = ['approved', 'rejected', 'client_accepted', 'client_rejected'];

        if (in_array($action, $valid_actions)) {
            try {
                if ($action === 'client_accepted' || $action === 'client_rejected') {
                    $stmt = $pdo->prepare(
                        "UPDATE quotations SET client_status = ?, client_response_date = NOW() WHERE id = ?"
                    );
                    $stmt->execute([$action, $quote_id]);
                    $log_action     = $action === 'client_accepted' ? 'client_accepted_quotation' : 'client_rejected_quotation';
                    $action_message = $action === 'client_accepted'
                        ? "âœ… Marked as Client Accepted."
                        : "âœ… Marked as Client Rejected.";
                } else {
                    $stmt = $pdo->prepare("UPDATE quotations SET status = ? WHERE id = ?");
                    $stmt->execute([$action, $quote_id]);
                    $log_action     = $action === 'approved' ? 'approved_quotation' : 'rejected_quotation';
                    $action_message = "âœ… Quotation has been " . ucfirst($action) . " successfully.";
                }
                $log = $pdo->prepare(
                    "INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, 'quotation', ?)"
                );
                $log->execute([$_SESSION['user_id'], $log_action, $quote_id]);
            } catch (\Exception $e) {
                $action_error = "Failed to update quotation status.";
                error_log("Status update error quotation #{$quote_id}: " . $e->getMessage());
            }
        }
    }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// LOAD QUOTATION FOR DISPLAY
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$stmt = $pdo->prepare("
    SELECT q.*, jc.card_number, jc.description AS job_description, jc.extra_data,
        c.name AS client_name, c.phone AS client_phone,
        c.email AS client_email, c.address AS client_address,
        v.reg_no, v.model, v.vin_no
    FROM quotations q
    LEFT JOIN job_cards jc ON q.job_card_id = jc.id
    LEFT JOIN clients c ON q.client_id = c.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE q.id = ?
    LIMIT 1
");
$stmt->execute([$quote_id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    header('Location: quotations.php?error=Quotation not found');
    exit;
}

require_once __DIR__ . '/quotation_paper_signoff.inc.php';
require_once __DIR__ . '/../includes/trade_document_helpers.inc.php';
$notes = json_decode($quote['notes'] ?? '{}', true) ?: [];
$labour_items = json_decode($quote['labour_items'] ?? '[]', true);
$parts_items = json_decode($quote['parts_items'] ?? '[]', true);
if (!is_array($labour_items)) {
    $labour_items = [];
}
if (!is_array($parts_items)) {
    $parts_items = [];
}

$quoteData = qt_parse_quotation_details((string) ($quote['details'] ?? ''));
if (!$quoteData) {
    $quoteData = sv_doc_legacy_quote_payload($quote, $notes, $labour_items, $parts_items, (float) ($quote['consumables'] ?? 0));
}
$formData = isset($quoteData['form']) && is_array($quoteData['form']) ? $quoteData['form'] : [];
$totals = isset($quoteData['totals']) && is_array($quoteData['totals']) ? $quoteData['totals'] : [];

$customerName = trim((string) ($formData['customer_name'] ?? $quote['client_name'] ?? ''));
$customerAddress = trim((string) ($formData['customer_address'] ?? $quote['client_address'] ?? ''));
$customerPhone = trim((string) ($formData['customer_phone'] ?? $quote['client_phone'] ?? ''));
$customerEmail = trim((string) ($formData['customer_email'] ?? $quote['client_email'] ?? ''));
$contactPerson = trim((string) ($formData['contact_person'] ?? ''));

$vehicleRegNo = trim((string) ($formData['vehicle_reg_no'] ?? $quote['reg_no'] ?? ''));
$vehicleModel = trim((string) ($formData['model'] ?? $quote['model'] ?? ''));
$vehicleVinNo = trim((string) ($formData['vin_no'] ?? $notes['vin'] ?? $quote['vin_no'] ?? ''));
$kilometers = trim((string) ($formData['kilometers'] ?? $notes['km'] ?? ''));
$fleetNo = trim((string) ($formData['fleet_no'] ?? $notes['fleet'] ?? ''));
$jobNo = trim((string) ($formData['job_no'] ?? $quote['card_number'] ?? ''));
$purchaseOrder = trim((string) ($formData['purchase_order'] ?? $notes['po'] ?? ''));
$quotationNumber = trim((string) ($formData['quote_number'] ?? ('QTN-' . str_pad((string) $quote['id'], 6, '0', STR_PAD_LEFT))));
$invoiceDate = trim((string) ($formData['date'] ?? substr((string) ($quote['submitted_at'] ?? ''), 0, 10)));

$subtotal_amount = (float) ($totals['subtotal'] ?? $quote['subtotal'] ?? 0);
$vat_amount = (float) ($totals['vat_amount'] ?? $quote['vat_amount'] ?? 0);
$total_amount = (float) ($totals['grand_total'] ?? $quote['total_amount'] ?? $quote['amount'] ?? 0);
if ($subtotal_amount <= 0 && $total_amount > 0) {
    $subtotal_amount = $total_amount - $vat_amount;
}

$docAssets = trade_doc_assets(__DIR__ . '/..');
$header_base64 = $docAssets['header'];
$footer_base64 = $docAssets['footer'];

// Client email for display â€” trim and validate
$display_client_email = trim($quote['client_email'] ?? '');
$can_send_email       = !empty($display_client_email) && filter_var($display_client_email, FILTER_VALIDATE_EMAIL);

include __DIR__ . '/../includes/header.php';
?>

<style>
    <?php echo inv_doc_trade_info_styles(); ?>
    .quotation-view-container { max-width: 1200px; margin: 0 auto; }
    .page-title { font-size:22px; color:var(--gray-800); margin-bottom:16px; font-weight:700; display:flex; align-items:center; gap:10px; }
    .invoice-container { display:flex; flex-direction:column; align-items:center; padding:20px 10px 40px; width:100%; }
    .invoice-wrapper { width:210mm; min-height:297mm; background:#fff; font-family:Arial,sans-serif; font-size:9pt; color:#000; padding:10mm 12mm; box-shadow:0 4px 32px rgba(0,0,0,.18); margin-bottom:30px; }
    .btn-bar { max-width:900px; margin:20px auto 40px; display:flex; gap:12px; flex-wrap:wrap; }
        .btn { padding:12px 24px; border:none; border-radius:8px; color:white; font-weight:700; font-size:15px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:8px; transition:opacity 0.2s,transform 0.1s; }
        .btn:hover { opacity:0.9; transform:translateY(-1px); }
        .btn-back    { background:#666; }
        .btn-print   { background:#2e7d32; }
        .btn-email   { background:#1a73e8; }
        .btn-approve { background:#27ae60; }
        .btn-reject  { background:#c62828; }
        .btn-edit    { background:#F5A623; }
        .approval-box { max-width:900px; margin:20px auto; background:#fff4e5; border:3px solid #d97706; border-radius:12px; padding:24px; text-align:center; }
        .approval-box h3 { color:var(--s); font-size:20px; margin-bottom:10px; }
        .approval-actions { display:flex; gap:12px; justify-content:center; margin-top:20px; flex-wrap:wrap; }
        .alert { max-width:900px; margin:20px auto; padding:20px; border-radius:12px; font-weight:700; display:flex; align-items:center; gap:12px; }
        .alert-success { background:#e8f5e8; color:#2e7d32; border:3px solid #4caf50; }
        .alert-error   { background:#ffebee; color:#c62828; border:3px solid #f44336; }

        /* MODAL */
        .modal-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-content { background:white; border-radius:16px; box-shadow:0 10px 40px rgba(0,0,0,0.3); max-width:520px; width:90%; animation:slideIn 0.25s ease-out; }
        @keyframes slideIn { from { transform:translateY(-40px); opacity:0; } to { transform:translateY(0); opacity:1; } }
        .modal-header  { padding:30px 30px 20px; text-align:center; }
        .modal-icon    { width:80px; height:80px; border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; font-size:40px; }
        .modal-title   { font-size:24px; font-weight:900; color:#333; margin-bottom:10px; }
        .modal-body    { padding:0 30px 30px; text-align:center; font-size:15px; line-height:1.7; color:#555; }
        .modal-body .email-highlight { display:inline-block; margin-top:10px; padding:8px 20px; background:#e8f0fe; color:#1a73e8; border-radius:8px; font-size:16px; font-weight:700; border:2px solid #c5d8fd; word-break:break-all; }
        .modal-actions { display:flex; gap:12px; padding:0 30px 30px; justify-content:flex-end; flex-wrap:wrap; }
        .modal-btn { padding:12px 24px; border:none; border-radius:8px; font-weight:700; font-size:15px; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.2s; }
        .modal-btn-cancel  { background:#e0e0e0; color:#666; }
        .modal-btn-cancel:hover  { background:#d0d0d0; }
        .modal-btn-confirm { background:#1a73e8; color:white; }
        .modal-btn-confirm:hover { background:#1557b0; }
        .modal-btn-danger  { background:#c62828; color:white; }
        .modal-btn-danger:hover  { background:#b71c1c; }
        .modal-btn-success { background:#27ae60; color:white; }
        .modal-btn-success:hover { background:#229954; }

        /* Sending spinner */
        .spinner { display:inline-block; width:18px; height:18px; border:3px solid rgba(255,255,255,0.4); border-top-color:white; border-radius:50%; animation:spin 0.7s linear infinite; vertical-align:middle; }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* PRINT STYLES */
        @media print {
            .header, .sidebar, .overlay, .menu-toggle, .erp-page-header { display:none !important; }
            body { background:white; margin:0 !important; padding:0 !important; }
            .content-wrapper { margin:0 !important; padding:0 !important; margin-left:0 !important; padding-top:0 !important; }
            .erp-alert, .erp-page-title, .erp-btn { display:none !important; }
            .page-title, .btn-bar, .alert, .approval-box { display:none !important; }
            .invoice-wrapper { box-shadow:none !important; margin:0 !important; width:100%; }
            @page { size:A4; margin:10mm; }
        }
    </style>

    <!-- ERP Page Header -->
    <div class="erp-page-header">
        <div class="erp-breadcrumb">
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <span class="erp-breadcrumb-separator">/</span>
            <a href="Quotation/quotations.php">Quotations</a>
            <span class="erp-breadcrumb-separator">/</span>
            <span class="erp-breadcrumb-current">View Quotation</span>
        </div>
        <h1 class="erp-page-title">View Quotation</h1>
    </div>

<div class="quotation-view-container">

    <h2 class="page-title">
        <i class="fas fa-file-invoice-dollar"></i>
        View Quotation: QTN-<?= str_pad($quote['id'], 6, '0', STR_PAD_LEFT) ?>
    </h2>

    <?php if ($action_message): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="font-size:20px;"></i>
            <?= htmlspecialchars($action_message) ?>
        </div>
    <?php endif; ?>
    <?php if ($action_error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="font-size:20px;"></i>
            <?= htmlspecialchars($action_error) ?>
        </div>
    <?php endif; ?>

    <div class="invoice-container">
    <?php
    $doc_title = 'Quotation';
    $show_invoice_number = false;
    $show_contact_box = false;
    $doc_quote_number = $quotationNumber;
    $show_quotation_ref_on_doc = false;
    $show_purchase_order_on_doc = false;
    $doc_wrapper_id = 'quotation-print-inner';
    $subTotal = $subtotal_amount;
    $vatTotal = $vat_amount;
    $grandTotal = $total_amount;
    include __DIR__ . '/../includes/trade_document_body.inc.php';
    ?>
    </div>

    <!-- â”€â”€ ADMIN APPROVAL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
        <?php $quote_ready = ($quote['status'] !== 'rejected'); ?>



    <!-- â”€â”€ CLIENT ACCEPTANCE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <?php if ($is_admin_user && $quote_ready && empty($quote['client_status'])): ?>
    <div class="approval-box" style="border-color:#2e7d32;">
        <h3><i class="fas fa-user-check"></i> Client Response Tracking</h3>
        <p style="color:#8B7355;margin:10px 0;">Mark the client's response after sending this quotation.</p>
        <div class="approval-actions">
            <button type="button" class="btn btn-approve" onclick="showClientModal('accept')">
                <i class="fas fa-thumbs-up"></i> Client Accepted
            </button>
            <button type="button" class="btn btn-reject" onclick="showClientModal('reject')">
                <i class="fas fa-thumbs-down"></i> Client Rejected
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- â”€â”€ CLIENT RESPONSE STATUS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <?php if (!empty($quote['client_status'])): ?>
    <?php
        $cs_accepted = $quote['client_status'] === 'client_accepted';
        $cs_border   = $cs_accepted ? '#2e7d32' : '#c62828';
        $cs_bg       = $cs_accepted ? '#e8f5e8'  : '#ffebee';
        $cs_icon     = $cs_accepted ? 'check-circle' : 'times-circle';
        $cs_label    = $cs_accepted ? 'ACCEPTED' : 'REJECTED';
    ?>
    <div class="approval-box" style="border-color:<?= $cs_border ?>;background:<?= $cs_bg ?>;">
        <h3>
            <i class="fas fa-<?= $cs_icon ?>"></i>
            Client Response: <?= $cs_label ?>
        </h3>
        <p style="margin:10px 0;"><strong>Response Date:</strong>
            <?= date('d M Y H:i', strtotime($quote['client_response_date'])) ?>
        </p>
        <?php if (!empty($quote['client_response_notes'])): ?>
        <p style="margin:10px 0;"><strong>Notes:</strong> <?= htmlspecialchars($quote['client_response_notes']) ?></p>
        <?php endif; ?>
        
        <?php if ($cs_accepted): ?>
            <?php
            // Check if invoice already exists for this quotation
            $inv_check = $pdo->prepare("SELECT id FROM invoices WHERE quotation_id = ? LIMIT 1");
            $inv_check->execute([$quote_id]);
            $existing_invoice = $inv_check->fetchColumn();
            ?>
            
            <?php if ($existing_invoice): ?>
                <div style="margin-top:20px;display:flex;gap:12px;">
                    <a href="../view_invoice.php?id=<?= $existing_invoice ?>" class="btn" style="background:#2e7d32;text-decoration:none;">
                        <i class="fas fa-file-invoice-dollar"></i> View Invoice
                    </a>
                    <a href="../Invoice/add_invoice.php?id=<?= $existing_invoice ?>" class="btn" style="background:#f59e0b;text-decoration:none;">
                        <i class="fas fa-edit"></i> Edit Invoice
                    </a>
                </div>
            <?php else: ?>
                <div style="margin-top:20px;">
                    <a href="../Invoice/add_invoice.php?quotation_id=<?= $quote_id ?>" class="btn" style="background:#2e7d32;text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fas fa-file-invoice"></i> Generate Tax Invoice
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- â”€â”€ BUTTON BAR â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
    <div class="btn-bar">
        <a href="Quotation/edit_quotation.php?id=<?= (int)$quote['id'] ?>" class="btn btn-edit">
            <i class="fas fa-edit"></i> Edit Quotation
        </a>
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print
        </button>

        <?php if ($quote_ready && $can_send_email): ?>
        <button type="button" class="btn btn-email" id="sendEmailBtn" onclick="showEmailModal()">
            <i class="fas fa-paper-plane"></i> Send via Email
        </button>
        <?php elseif ($quote_ready): ?>
        <button type="button" class="btn" style="background:#999;cursor:not-allowed;" title="No valid email address on record for this client" disabled>
            <i class="fas fa-paper-plane"></i> Send via Email
            <span style="font-size:11px;opacity:0.8;">(No Email)</span>
        </button>
        <?php endif; ?>

        <!-- Send to Portal Button -->
        <?php if ($quote_ready): ?>
            <button type="button" class="btn btn-email" style="background:#28a745;" onclick="sendToPortal()">
                <i class="fas fa-globe"></i> Send to Portal
            </button>
        <?php endif; ?>
        
        <!-- Create Invoice Button -->
        <?php if ($quote_ready): ?>
            <?php
            // Check if invoice already exists for this quotation
            $checkInvoice = $pdo->prepare("SELECT id FROM invoices WHERE quotation_id = ? LIMIT 1");
            $checkInvoice->execute([$quote['id']]);
            $existingInvoice = $checkInvoice->fetch(PDO::FETCH_ASSOC);
            ?>
            <?php if ($existingInvoice): ?>
                <a href="../view_invoice.php?id=<?php echo $existingInvoice['id']; ?>" class="btn" style="background:#9C27B0;">
                    <i class="fas fa-file-invoice"></i> View Invoice
                </a>
            <?php else: ?>
                <form method="POST" action="<?php echo htmlspecialchars($createInvoiceUrl, ENT_QUOTES); ?>" style="display:inline;">
                    <input type="hidden" name="quotation_id" value="<?php echo (int)$quote['id']; ?>">
                    <button type="submit" class="btn" style="background:#9C27B0;" onclick="return confirm('Create invoice from this quotation?')">
                        <i class="fas fa-file-invoice"></i> Create Invoice
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div><!-- /.content-wrapper -->

<!-- â”€â”€ APPROVAL MODAL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal-overlay" id="approvalModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon" id="modalIcon"></div>
            <div class="modal-title" id="modalTitle"></div>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeApprovalModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" id="approvalForm" style="margin:0;">
                <input type="hidden" name="action" id="approvalAction">
                <button type="submit" class="modal-btn" id="confirmBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </form>
        </div>
    </div>
</div>

<!-- â”€â”€ EMAIL CONFIRM MODAL â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
<div class="modal-overlay" id="emailModal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon" style="background:#e8f0fe;color:#1a73e8;width:80px;height:80px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:36px;">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <div class="modal-title">Send Quotation to Client</div>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:10px;font-size:14px;">Send PDF to this email address:</p>
            <input
                type="email"
                id="sendToEmailInput"
                name="send_to_email"
                value="<?= htmlspecialchars($display_client_email) ?>"
                placeholder="Enter email address"
                style="width:100%;padding:10px 14px;font-size:15px;font-weight:600;color:#1a73e8;border:2px solid #c5d8fd;border-radius:8px;background:#e8f0fe;text-align:center;outline:none;box-sizing:border-box;"
                onfocus="this.style.borderColor='#1a73e8'"
                onblur="this.style.borderColor='#c5d8fd'"
            >
            <p style="margin-top:12px;font-size:12px;color:#aaa;">
                The full quotation PDF will be attached to the email.
            </p>
        </div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeEmailModal()" id="emailCancelBtn">
                <i class="fas fa-times"></i> Cancel
            </button>
            <form method="POST" style="margin:0;" id="emailForm" onsubmit="handleEmailSubmit(event)">
                <input type="hidden" name="post_action" value="send_email">
                <input type="hidden" name="send_to_email" id="emailFormAddress">
                <button type="submit" class="modal-btn modal-btn-confirm" id="emailSendBtn">
                    <i class="fas fa-paper-plane"></i> Send Email
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// â”€â”€ Email Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function showEmailModal() {
    document.getElementById('emailModal').classList.add('active');
}
function closeEmailModal() {
    const modal = document.getElementById('emailModal');
    modal.classList.remove('active');
    const btn = document.getElementById('emailSendBtn');
    const emailInput = document.getElementById('sendToEmailInput');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
    emailInput.style.borderColor = '#c5d8fd';
}
function handleEmailSubmit(e) {
    const emailInput = document.getElementById('sendToEmailInput');
    const hiddenField = document.getElementById('emailFormAddress');
    const val = emailInput.value.trim();
    if (!val || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        emailInput.style.borderColor = '#c62828';
        emailInput.focus();
        e.preventDefault();
        return;
    }
    hiddenField.value = val;
    const btn    = document.getElementById('emailSendBtn');
    const cancel = document.getElementById('emailCancelBtn');
    btn.disabled    = true;
    cancel.disabled = true;
    btn.innerHTML   = '<span class="spinner"></span> Sendingâ€¦';
    // Form submits naturally â€” page will reload with result
}

// â”€â”€ Approval Modal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function showApprovalModal(type) {
    const modal      = document.getElementById('approvalModal');
    const icon       = document.getElementById('modalIcon');
    const title      = document.getElementById('modalTitle');
    const body       = document.getElementById('modalBody');
    const action     = document.getElementById('approvalAction');
    const confirmBtn = document.getElementById('confirmBtn');

    if (type === 'approve') {
        icon.innerHTML   = '<i class="fas fa-check-circle"></i>';
        icon.style.background = '#e8f5e8';
        icon.style.color      = '#2e7d32';
        title.textContent     = 'Approve Quotation';
        body.innerHTML        = 'Are you sure you want to approve this quotation?<br><span style="color:#2e7d32;font-weight:700;margin-top:8px;display:block;">This will allow it to be sent to the client.</span>';
        action.value          = 'approved';
        confirmBtn.className  = 'modal-btn modal-btn-success';
        confirmBtn.innerHTML  = '<i class="fas fa-check"></i> Yes, Approve';
    } else {
        icon.innerHTML   = '<i class="fas fa-times-circle"></i>';
        icon.style.background = '#ffebee';
        icon.style.color      = '#c62828';
        title.textContent     = 'Reject Quotation';
        body.innerHTML        = 'Are you sure you want to reject this quotation?<br><span style="color:#c62828;font-weight:700;margin-top:8px;display:block;">This action cannot be undone.</span>';
        action.value          = 'rejected';
        confirmBtn.className  = 'modal-btn modal-btn-danger';
        confirmBtn.innerHTML  = '<i class="fas fa-times"></i> Yes, Reject';
    }
    modal.classList.add('active');
}
function showClientModal(type) {
    const modal      = document.getElementById('approvalModal');
    const icon       = document.getElementById('modalIcon');
    const title      = document.getElementById('modalTitle');
    const body       = document.getElementById('modalBody');
    const action     = document.getElementById('approvalAction');
    const confirmBtn = document.getElementById('confirmBtn');

    if (type === 'accept') {
        icon.innerHTML        = '<i class="fas fa-thumbs-up"></i>';
        icon.style.background = '#e8f5e8';
        icon.style.color      = '#2e7d32';
        title.textContent     = 'Mark as Client Accepted';
        body.innerHTML        = 'Confirm that the client has <strong>accepted</strong> this quotation?';
        action.value          = 'client_accepted';
        confirmBtn.className  = 'modal-btn modal-btn-success';
        confirmBtn.innerHTML  = '<i class="fas fa-check"></i> Yes, Client Accepted';
    } else {
        icon.innerHTML        = '<i class="fas fa-thumbs-down"></i>';
        icon.style.background = '#ffebee';
        icon.style.color      = '#c62828';
        title.textContent     = 'Mark as Client Rejected';
        body.innerHTML        = 'Confirm that the client has <strong>rejected</strong> this quotation?';
        action.value          = 'client_rejected';
        confirmBtn.className  = 'modal-btn modal-btn-danger';
        confirmBtn.innerHTML  = '<i class="fas fa-times"></i> Yes, Client Rejected';
    }
    modal.classList.add('active');
}
function closeApprovalModal() {
    document.getElementById('approvalModal').classList.remove('active');
}

// Close modals on backdrop click
document.getElementById('approvalModal').addEventListener('click', function(e) {
    if (e.target === this) closeApprovalModal();
});
document.getElementById('emailModal').addEventListener('click', function(e) {
    if (e.target === this) closeEmailModal();
});

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeApprovalModal();
        closeEmailModal();
    }
});

// â”€â”€ Send to Portal â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
function sendToPortal() {
    if (!confirm('Send this quotation to the Client Portal?\n\nThe client will be able to view and respond to this quotation in their portal.')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'post_action';
    actionInput.value = 'send_to_portal';
    form.appendChild(actionInput);
    
    document.body.appendChild(form);
    form.submit();
}
</script>


<script>
(function() {
    const PAGE_KEY = 'draft_' + window.location.pathname + window.location.search;
    const form = document.querySelector('form');
    if (!form) return;

    // Restore draft on page load
    const saved = localStorage.getItem(PAGE_KEY);
    if (saved) {
        try {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(name => {
                const fields = form.querySelectorAll(`[name="${name}"]`);
                fields.forEach(field => {
                    if (!field) return;
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = data[name] === true || data[name] === field.value;
                    } else if (field.tagName === 'SELECT') {
                        field.value = data[name];
                        field.dispatchEvent(new Event('change'));
                    } else {
                        field.value = data[name];
                        field.dispatchEvent(new Event('input'));
                    }
                });
            });
            // Show restored notice
            const notice = document.createElement('div');
            notice.innerHTML = 'ðŸ“ <strong>Draft restored</strong> â€” your unsaved changes have been recovered.';
            notice.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#F7A100; color:white; padding:14px 22px; border-radius:12px; font-size:14px; z-index:99999; box-shadow:0 6px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;';
            const closeBtn = document.createElement('span');
            closeBtn.textContent = 'âœ•';
            closeBtn.style.cssText = 'cursor:pointer; margin-left:10px; font-size:16px; opacity:0.8;';
            closeBtn.onclick = () => notice.remove();
            notice.appendChild(closeBtn);
            document.body.appendChild(notice);
            setTimeout(() => notice.remove(), 5000);
        } catch(e) {}
    }

    // Auto-save on any input change
    let saveTimeout;
    function doSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const data = {};
            form.querySelectorAll('input, select, textarea').forEach(field => {
                if (!field.name) return;
                if (field.type === 'password' || field.type === 'file' || field.type === 'hidden') return;
                if (field.type === 'checkbox' || field.type === 'radio') {
                    data[field.name] = field.checked;
                } else {
                    data[field.name] = field.value;
                }
            });
            localStorage.setItem(PAGE_KEY, JSON.stringify(data));

            // Show saved indicator
            let indicator = document.getElementById('draft-save-indicator');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'draft-save-indicator';
                indicator.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#22C55E; color:white; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; z-index:99999; box-shadow:0 4px 12px rgba(0,0,0,0.15); opacity:0; transition:opacity 0.3s;';
                document.body.appendChild(indicator);
            }
            indicator.textContent = 'âœ“ Draft saved';
            indicator.style.opacity = '1';
            clearTimeout(indicator._hideTimer);
            indicator._hideTimer = setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
        }, 800);
    }

    form.addEventListener('input', doSave);
    form.addEventListener('change', doSave);

    // Clear draft on successful form submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(PAGE_KEY);
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

