<?php

function erp_ensure_contact_enquiries_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_enquiries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            service VARCHAR(150) DEFAULT NULL,
            message TEXT NOT NULL,
            status ENUM('new','contacted','converted','closed') NOT NULL DEFAULT 'new',
            client_id INT DEFAULT NULL,
            source VARCHAR(50) NOT NULL DEFAULT 'website',
            ip_address VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            converted_by INT DEFAULT NULL,
            converted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_contact_enquiries_status (status),
            INDEX idx_contact_enquiries_email (email),
            INDEX idx_contact_enquiries_created_at (created_at),
            INDEX idx_contact_enquiries_client_id (client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_enquiry_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            enquiry_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            to_email VARCHAR(150) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_contact_enquiry_replies_enquiry_id (enquiry_id),
            INDEX idx_contact_enquiry_replies_sent_at (sent_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function erp_contact_enquiry_status_label(string $status): string
{
    $labels = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'converted' => 'Converted',
        'closed' => 'Closed',
    ];
    return $labels[$status] ?? 'New';
}

function erp_fetch_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[(string) $row['Field']] = true;
    }
    return $columns;
}

function erp_find_or_create_client_from_enquiry(PDO $pdo, array $enquiry, int $userId = 0): int
{
    $email = trim((string) ($enquiry['email'] ?? ''));
    if ($email !== '') {
        $find = $pdo->prepare('SELECT id FROM clients WHERE email = ? ORDER BY id ASC LIMIT 1');
        $find->execute([$email]);
        $existingId = (int) $find->fetchColumn();
        if ($existingId > 0) {
            return $existingId;
        }
    }

    $first = trim((string) ($enquiry['first_name'] ?? ''));
    $last = trim((string) ($enquiry['last_name'] ?? ''));
    $name = trim($first . ' ' . $last);
    if ($name === '') {
        $name = $email !== '' ? $email : 'Website enquiry';
    }

    $columns = erp_fetch_table_columns($pdo, 'clients');
    $data = [];
    foreach ([
        'name' => $name,
        'first_name' => $first,
        'last_name' => $last,
        'email' => $email,
        'phone' => trim((string) ($enquiry['phone'] ?? '')),
        'contact_person' => $name,
        'status' => 'approved',
        'role' => 'client',
        'created_at' => date('Y-m-d H:i:s'),
    ] as $column => $value) {
        if (isset($columns[$column])) {
            $data[$column] = $value;
        }
    }

    if (isset($columns['address']) && !isset($data['address'])) {
        $data['address'] = '';
    }
    if (isset($columns['password_hash']) && !isset($data['password_hash'])) {
        $data['password_hash'] = '';
    }
    if (isset($columns['created_by']) && $userId > 0) {
        $data['created_by'] = $userId;
    }

    if (empty($data)) {
        throw new RuntimeException('No writable client columns were found.');
    }

    $fieldList = array_keys($data);
    $placeholders = array_fill(0, count($fieldList), '?');
    $sql = 'INSERT INTO clients (`' . implode('`,`', $fieldList) . '`) VALUES (' . implode(',', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));

    return (int) $pdo->lastInsertId();
}

function erp_send_contact_enquiry_reply(string $toEmail, string $recipientName, string $subject, string $message, array $business = []): array
{
    if (!function_exists('erp_mail_config_loaded') || !erp_mail_config_loaded()) {
        return ['ok' => false, 'error' => 'Email is not configured. Copy Config/mail.example.php to Config/mail.php and add SMTP details.'];
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'The customer email address is not valid.'];
    }

    $phpmailerPath = dirname(__DIR__) . '/Libraries/PHPMailer/src/PHPMailer.php';
    if (!is_file($phpmailerPath)) {
        return ['ok' => false, 'error' => 'PHPMailer is not installed.'];
    }

    try {
        require_once dirname(__DIR__) . '/Libraries/PHPMailer/src/Exception.php';
        require_once dirname(__DIR__) . '/Libraries/PHPMailer/src/PHPMailer.php';
        require_once dirname(__DIR__) . '/Libraries/PHPMailer/src/SMTP.php';

        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : (string) ($business['name'] ?? 'SV Auto Truck Repair');
        $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : MAIL_USERNAME;

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) MAIL_PORT;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $recipientName);
        if (!empty($business['email']) && filter_var($business['email'], FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo((string) $business['email'], (string) ($business['name'] ?? $fromName));
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'there', ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $safeBusiness = htmlspecialchars((string) ($business['name'] ?? $fromName), ENT_QUOTES, 'UTF-8');
        $safePhone = htmlspecialchars((string) ($business['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars((string) ($business['email'] ?? $fromEmail), ENT_QUOTES, 'UTF-8');

        $mail->Body = '<div style="font-family:Arial,Helvetica,sans-serif;background:#f6f7f9;padding:28px;">'
            . '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
            . '<div style="background:#f97316;color:#fff;padding:22px 26px;">'
            . '<h2 style="margin:0;font-size:20px;">' . $safeBusiness . '</h2>'
            . '<p style="margin:6px 0 0;opacity:.9;">Reply to your website enquiry</p>'
            . '</div>'
            . '<div style="padding:26px;color:#1f2937;line-height:1.6;">'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<div>' . $safeMessage . '</div>'
            . '<hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">'
            . '<p style="margin:0;color:#64748b;font-size:13px;">You can reply to this email or contact us directly.</p>'
            . ($safePhone !== '' ? '<p style="margin:8px 0 0;color:#64748b;font-size:13px;">Phone: ' . $safePhone . '</p>' : '')
            . '<p style="margin:8px 0 0;color:#64748b;font-size:13px;">Email: ' . $safeEmail . '</p>'
            . '</div></div></div>';
        $mail->AltBody = "Hello " . ($recipientName !== '' ? $recipientName : 'there') . ",\n\n" . $message . "\n\n" . $safeBusiness;

        $mail->send();
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        error_log('erp_send_contact_enquiry_reply: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Email could not be sent. Check mail settings.'];
    }
}
