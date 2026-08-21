<?php
session_start();
require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/contact_enquiries.php';
require_once __DIR__ . '/../backend/config/notifications.php';

$redirectBase = 'index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectBase . '#contact');
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
$sessionToken = (string) ($_SESSION['contact_csrf'] ?? '');

if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
    $_SESSION['contact_flash'] = [
        'type' => 'error',
        'message' => 'Your session expired. Please try again.',
    ];
    header('Location: ' . $redirectBase . '#contact');
    exit;
}

// Honeypot
if (trim((string) ($_POST['company'] ?? '')) !== '') {
    header('Location: ' . $redirectBase . '#contact');
    exit;
}

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$service = trim((string) ($_POST['service'] ?? ''));
$serviceSelect = trim((string) ($_POST['service_select'] ?? ''));

if ($service === '' && $serviceSelect !== '') {
    $service = $serviceSelect;
}

if ($firstName === '' || $lastName === '' || $message === '') {
    $_SESSION['contact_flash'] = [
        'type' => 'error',
        'message' => 'Please fill in your name and message.',
    ];
    header('Location: ' . $redirectBase . '#contact');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['contact_flash'] = [
        'type' => 'error',
        'message' => 'Please enter a valid email address.',
    ];
    header('Location: ' . $redirectBase . '#contact');
    exit;
}

$to = 'svautotruckrepair@gmail.com';
$subject = 'Website enquiry — ' . $firstName . ' ' . $lastName;
if ($service !== '') {
    $subject .= ' (' . $service . ')';
}

$bodyLines = [
    'New message from the SV Auto website',
    '',
    'Name: ' . $firstName . ' ' . $lastName,
    'Email: ' . $email,
];
if ($phone !== '') {
    $bodyLines[] = 'Phone: ' . $phone;
}
if ($service !== '') {
    $bodyLines[] = 'Service: ' . $service;
}
$bodyLines[] = '';
$bodyLines[] = 'Message:';
$bodyLines[] = $message;
$bodyLines[] = '';
$bodyLines[] = 'Sent: ' . date('Y-m-d H:i:s');

$body = implode("\n", $bodyLines);
$headers = [
    'From: SV Auto Website <noreply@svauto.local>',
    'Reply-To: ' . $email,
    'Content-Type: text/plain; charset=UTF-8',
];

$enquiryId = 0;
try {
    erp_ensure_contact_enquiries_table($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO contact_enquiries
            (first_name, last_name, email, phone, service, message, source, ip_address, user_agent)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $firstName,
        $lastName,
        $email,
        $phone !== '' ? $phone : null,
        $service !== '' ? $service : null,
        $message,
        'website',
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);
    $enquiryId = (int) $pdo->lastInsertId();
    notifyAdmins(
        $pdo,
        'New website enquiry',
        trim($firstName . ' ' . $lastName) . ($service !== '' ? ' asked about ' . $service : ' sent a message'),
        'Client/clients.php?tab=enquiries&enquiry_id=' . $enquiryId
    );
} catch (Throwable $e) {
    error_log('Website enquiry save failed: ' . $e->getMessage());
}

$sent = @mail($to, $subject, $body, implode("\r\n", $headers));

$logDir = __DIR__ . '/storage';
$logFile = $logDir . '/contact_inquiries.log';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logEntry = date('c') . "\t" . $email . "\t" . str_replace(["\r", "\n", "\t"], ' ', $subject) . "\n";
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

@file_put_contents(
    $logDir . '/contact_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.txt',
    $body
);

if ($sent) {
    $_SESSION['contact_flash'] = [
        'type' => 'success',
        'message' => 'Thank you - your message was sent. For deeper tracking, create an account or sign in to view quotations, approvals, job cards, and invoices.',
    ];
} else {
    $_SESSION['contact_flash'] = [
        'type' => 'success',
        'message' => 'Thank you - we received your message. For deeper tracking, create an account or sign in to view quotations, approvals, job cards, and invoices.',
    ];
}

header('Location: ' . $redirectBase . '?contact=sent#contact');
exit;
