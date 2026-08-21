<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$quotation_id = (int)($_GET['id'] ?? 0);
if ($quotation_id <= 0) {
    header('Location: quotations.php?error=Invalid quotation ID');
    exit;
}

$success = $error = '';

// Fetch quotation details
$stmt = $pdo->prepare("
    SELECT q.id, q.amount, q.status, q.submitted_at, q.details,
        c.name AS client_name, c.email AS client_email, c.phone AS client_phone,
        v.reg_no, v.model, jc.card_number
    FROM quotations q
    JOIN job_cards jc ON q.job_card_id = jc.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    LEFT JOIN clients c ON q.client_id = c.id
    WHERE q.id = ?
");
$stmt->execute([$quotation_id]);
$quotation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quotation) {
    header('Location: quotations.php?error=Quotation not found');
    exit;
}

if (empty($quotation['client_email'])) {
    header('Location: quotations.php?error=Client email not found. Please update client information.');
    exit;
}

$business = getBusiness();
$quotation_number = 'QTN-' . str_pad($quotation['id'], 6, '0', STR_PAD_LEFT);

// Handle Send
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Parse line items from details field
    $details_lines = explode("\n", $quotation['details'] ?? '');
    $email_items   = [];
    $current_item  = null;
    foreach ($details_lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '<strong>') !== false || strpos($line, '═') !== false) {
            if (strpos($line, 'TOTAL:') !== false && $current_item) {
                $email_items[] = $current_item;
                $current_item  = null;
            }
            continue;
        }
        $first_char = mb_substr($line, 0, 1, 'UTF-8');
        if ($first_char === '•' || ord($first_char) > 127) {
            if ($current_item) $email_items[] = $current_item;
            $current_item = ['desc' => trim(mb_substr($line, 1, null, 'UTF-8')), 'amount' => ''];
        } elseif ($current_item && preg_match('/N\$\s*[0-9,.]+/', $line)) {
            $current_item['amount'] = $line;
        }
    }
    if ($current_item) $email_items[] = $current_item;

    $mail = new PHPMailer(true);

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'karlosbrian02@gmail.com';
        $mail->Password   = 'oqwhovsizixxkhmw'; // App password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & Recipient
        $mail->setFrom('karlosbrian02@gmail.com', $business['name'] ?? 'SV Auto Services');
        $mail->addAddress($quotation['client_email'], $quotation['client_name']);
        $mail->addReplyTo($business['email'] ?? 'karlosbrian02@gmail.com', $business['name'] ?? 'SV Auto Services');

        // Email Content
        $mail->isHTML(true);
        $mail->Subject = "Quotation $quotation_number from " . ($business['name'] ?? 'SV Auto Services');

        $mail->Body = "
        <html>
        <body style='font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto;'>

            <div style='background:#F5A623;padding:30px;text-align:center;border-radius:10px 10px 0 0;'>
                <h1 style='color:white;margin:0;font-size:24px;letter-spacing:2px;'>
                    " . htmlspecialchars($business['name'] ?? 'SV Auto Services') . "
                </h1>
                <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px;'>
                    " . htmlspecialchars($business['address'] ?? '') . "
                </p>
            </div>

            <div style='background:#FFF8F0;padding:30px;border:1px solid #FFF8EC;'>
                <h2 style='color: #4a4a4a;margin:0 0 6px;'>Quotation $quotation_number</h2>
                <p style='color:#666;margin:0 0 25px;font-size:13px;'>Please review your quotation below.</p>

                <p style='font-size:15px;margin:0 0 20px;'>Dear <strong>" . htmlspecialchars($quotation['client_name']) . "</strong>,</p>

                <p style='font-size:14px;line-height:1.7;margin:0 0 25px;'>
                    Thank you for choosing <strong>" . htmlspecialchars($business['name'] ?? 'SV Auto Services') . "</strong>.
                    We have prepared a quotation for your vehicle
                    <strong>" . htmlspecialchars($quotation['reg_no'] ?? 'N/A') . "
                    (" . htmlspecialchars($quotation['model'] ?? 'N/A') . ")</strong>.
                </p>

                <div style='background:white;border:2px solid #FFF8EC;border-radius:10px;padding:20px;margin:0 0 25px;'>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                        <tr style='border-bottom:1px solid #eee;'>
                            <td style='padding:10px 0;color: #4a4a4a;font-weight:700;width:50%;'>Quotation Number</td>
                            <td style='padding:10px 0;'>$quotation_number</td>
                        </tr>
                        <tr style='border-bottom:1px solid #eee;'>
                            <td style='padding:10px 0;color: #4a4a4a;font-weight:700;'>Vehicle</td>
                            <td style='padding:10px 0;'>
                                " . htmlspecialchars($quotation['reg_no'] ?? 'N/A') . " —
                                " . htmlspecialchars($quotation['model'] ?? 'N/A') . "
                            </td>
                        </tr>
                        <tr style='border-bottom:1px solid #eee;'>
                            <td style='padding:10px 0;color: #4a4a4a;font-weight:700;'>Job Card</td>
                            <td style='padding:10px 0;'>" . htmlspecialchars($quotation['card_number']) . "</td>
                        </tr>
                        <tr style='border-bottom:1px solid #eee;'>
                            <td style='padding:10px 0;color: #4a4a4a;font-weight:700;'>Date</td>
                            <td style='padding:10px 0;'>
                                " . ($quotation['submitted_at'] ? date('d M Y', strtotime($quotation['submitted_at'])) : '—') . "
                            </td>
                        </tr>
                        <tr style='border-bottom:1px solid #eee;'>
                            <td style='padding:10px 0;color: #4a4a4a;font-weight:700;'>Valid For</td>
                            <td style='padding:10px 0;'>30 Days</td>
                        </tr>
                    </table>
                </div>

                " . (!empty($email_items) ? "
                <div style='background:#FFF8F0;border:2px solid #FFF8EC;border-radius:10px;padding:20px;margin:0 0 25px;'>
                    <h3 style='margin:0 0 15px;color: #4a4a4a;font-size:16px;'>Items & Services</h3>
                    <table style='width:100%;border-collapse:collapse;'>
                        <thead>
                            <tr style='background:#f0e0cc;'>
                                <th style='padding:10px;text-align:left;border:1px solid #FFF8EC;font-size:13px;color: #4a4a4a;'>No.</th>
                                <th style='padding:10px;text-align:left;border:1px solid #FFF8EC;font-size:13px;color: #4a4a4a;'>Description</th>
                                <th style='padding:10px;text-align:right;border:1px solid #FFF8EC;font-size:13px;color: #4a4a4a;'>Amount</th>
                            </tr>
                        </thead>
                        <tbody>" . 
                        implode('', array_map(function($item, $idx) {
                            return "<tr style='background:" . ($idx % 2 ? '#ffffff' : '#FFF8F0') . ";'>
                                <td style='padding:10px;border:1px solid #eee;text-align:center;font-size:13px;'>" . ($idx + 1) . "</td>
                                <td style='padding:10px;border:1px solid #eee;font-size:13px;'>" . htmlspecialchars($item['desc']) . "</td>
                                <td style='padding:10px;border:1px solid #eee;text-align:right;font-size:13px;font-weight:700;'>" . htmlspecialchars($item['amount']) . "</td>
                            </tr>";
                        }, $email_items, array_keys($email_items))) . "
                        </tbody>
                    </table>
                </div>
                " : "") . "

                <div style='background:white;border:2px solid #FFF8EC;border-radius:10px;padding:20px;margin:0 0 25px;'>
                    <table style='width:100%;border-collapse:collapse;font-size:14px;'>
                        <tr>
                            <td style='padding:10px 0;color: #4a4a4a;font-weight:700;'>Total Amount</td>
                            <td style='padding:10px 0;font-size:18px;font-weight:900;color:#F5A623;text-align:right;'>
                                " . formatMoney($quotation['amount']) . "
                            </td>
                        </tr>
                    </table>
                </div>

                <p style='font-size:14px;line-height:1.7;margin:0 0 25px;'>
                    Please contact us to <strong>approve or discuss</strong> this quotation.
                    This quotation is valid for <strong>30 days</strong> from the date issued.
                </p>

                <div style='background:#F5A623;border-radius:10px;padding:20px;color:white;font-size:13px;line-height:1.8;'>
                    <strong style='font-size:15px;'>Contact Us</strong><br>
                    Phone: " . htmlspecialchars($business['phone'] ?? '') . "<br>
                    Email: " . htmlspecialchars($business['email'] ?? '') . "<br>
                    Address: " . htmlspecialchars($business['address'] ?? '') . "
                </div>
            </div>

            <div style='background: #4a4a4a;padding:15px;text-align:center;border-radius:0 0 10px 10px;'>
                <p style='color:rgba(255,255,255,0.6);font-size:11px;margin:0;'>
                    This email was sent by " . htmlspecialchars($business['name'] ?? 'SV Auto Services') . " •
                    VAT: " . htmlspecialchars($business['tax_number'] ?? '') . "
                </p>
            </div>

        
</body>`n        </html>`n        ";

        // Plain text fallback
        $mail->AltBody = "Quotation $quotation_number\n\n" .
            "Dear " . $quotation['client_name'] . ",\n\n" .
            "Vehicle: " . ($quotation['reg_no'] ?? 'N/A') . " (" . ($quotation['model'] ?? 'N/A') . ")\n" .
            "Amount: " . formatMoney($quotation['amount']) . "\n" .
            "Valid For: 30 Days\n\n" .
            "Contact: " . ($business['phone'] ?? '') . "\n" .
            "From: " . ($business['name'] ?? 'SV Auto Services');

        $mail->send();

        $success = "Quotation sent successfully to " . htmlspecialchars($quotation['client_email']);

        // Log the action
        try {
            $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'sent_quotation', 'quotation', ?, ?)");
            $log->execute([$_SESSION['user_id'], $quotation_id, "Sent to " . $quotation['client_email']]);
        } catch (Exception $e) {
            // audit_logs table may not exist — ignore silently
        }

    } catch (Exception $e) {
        $error = "Failed to send email. Error: " . $mail->ErrorInfo;
    }
}

include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Quotation - <?= htmlspecialchars($business['name'] ?? 'SV Auto') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root { --primary:#F5A623; --secondary:#1a1a1a; --accent:#FFF8EC; }

        .send-container {
            max-width:700px; margin:30px auto; background:white;
            border-radius:16px; padding:40px;
            box-shadow:0 8px 30px rgba(245,166,35,0.15);
        }
        .send-header { text-align:center; margin-bottom:30px; }
        .send-icon {
            width:75px; height:75px;
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            border-radius:50%; display:flex; align-items:center;
            justify-content:center; margin:0 auto 16px;
            color:white; font-size:32px;
        }
        .send-title { font-size:26px; font-weight:700; color:var(--secondary); margin-bottom:6px; }
        .send-subtitle { color:#666; font-size:14px; }

        .quotation-info {
            background:#FFF8F0; border:2px solid var(--accent);
            border-radius:12px; padding:20px; margin-bottom:24px;
        }
        .info-row {
            display:flex; justify-content:space-between;
            padding:8px 0; border-bottom:1px solid #eee;
            font-size:14px;
        }
        .info-row:last-child { border-bottom:none; }
        .info-label { font-weight:700; color:var(--secondary); }
        .info-value { color:#333; }

        .instruction-box {
            background:#e8f5e8; border:2px solid #2e7d32;
            border-radius:12px; padding:18px; margin-bottom:22px;
        }
        .instruction-title {
            font-size:15px; font-weight:700; color:#2e7d32;
            margin-bottom:8px; display:flex; align-items:center; gap:8px;
        }
        .instruction-steps { color:#1b5e20; font-size:13px; line-height:1.7; }

        .alert {
            padding:16px 20px; border-radius:12px; margin-bottom:22px;
            font-size:15px; display:flex; align-items:center; gap:12px;
            font-weight:600;
        }
        .alert-success { background:#e8f5e8; border:2px solid #2e7d32; color:#1b5e20; }
        .alert-error   { background:#ffebee; border:2px solid #c62828; color:#b71c1c; }

        .action-buttons {
            display:flex; gap:14px; justify-content:center; margin-top:24px;
        }
        .btn {
            padding:14px 30px; border:none; border-radius:10px;
            font-weight:700; font-size:15px; cursor:pointer;
            transition:all 0.3s; display:inline-flex;
            align-items:center; gap:10px; text-decoration:none;
        }
        .btn-email {
            background:linear-gradient(135deg,var(--primary),var(--secondary));
            color:white;
        }
        .btn-email:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(210,105,30,0.4); }
        .btn-back { background:#e0e0e0; color:#333; }
        .btn-back:hover { background:#d0d0d0; }
    </style>
</head>
<body>

<div class="content-wrapper">
    <div class="send-container">

        <div class="send-header">
            <div class="send-icon"><i class="fas fa-paper-plane"></i></div>
            <h2 class="send-title">Send Quotation</h2>
            <p class="send-subtitle">Send quotation via email to client</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="quotation-info">
            <div class="info-row">
                <span class="info-label">Quotation Number</span>
                <span class="info-value"><?= $quotation_number ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Client</span>
                <span class="info-value"><?= htmlspecialchars($quotation['client_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value"><?= htmlspecialchars($quotation['client_email']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Amount</span>
                <span class="info-value"><?= formatMoney($quotation['amount']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Vehicle</span>
                <span class="info-value">
                    <?= htmlspecialchars($quotation['reg_no'] ?? 'N/A') ?> —
                    <?= htmlspecialchars($quotation['model'] ?? 'N/A') ?>
                </span>
            </div>
        </div>

        <?php if (!$success): ?>
        <form method="POST">
            <input type="hidden" name="quotation_id" value="<?= $quotation_id ?>">

            <div class="instruction-box">
                <div class="instruction-title">
                    <i class="fas fa-info-circle"></i> Ready to Send
                </div>
                <div class="instruction-steps">
                    Click <strong>Send Email</strong> below to send this quotation directly to
                    <strong><?= htmlspecialchars($quotation['client_email']) ?></strong>.
                    The client will receive a professional HTML email with full quotation details.
                </div>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn btn-email">
                    <i class="fas fa-paper-plane"></i> Send Email
                </button>
            </div>
        </form>
        <?php else: ?>
        <div class="action-buttons">
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        setTimeout(() => {
            successAlert.style.transition = 'opacity 0.5s';
            successAlert.style.opacity = '0';
            setTimeout(() => successAlert.remove(), 500);
        }, 5000);
    }
});
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
            notice.innerHTML = '📝 <strong>Draft restored</strong> — your unsaved changes have been recovered.';
            notice.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#F7A100; color:white; padding:14px 22px; border-radius:12px; font-size:14px; z-index:99999; box-shadow:0 6px 20px rgba(0,0,0,0.2); display:flex; align-items:center; gap:10px;';
            const closeBtn = document.createElement('span');
            closeBtn.textContent = '✕';
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
            indicator.textContent = '✓ Draft saved';
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

</body>
</html>

<?php echo '</div></div>'; ?>
