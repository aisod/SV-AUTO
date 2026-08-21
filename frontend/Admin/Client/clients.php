<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../../../backend/config/contact_enquiries.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/student_table.inc.php';

require_admin();
erp_ensure_contact_enquiries_table($pdo);

$page_title = 'Clients';
$business = getBusiness();
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (empty($_SESSION['client_admin_csrf'])) {
    $_SESSION['client_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['client_admin_csrf'];

function ca_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ca_redirect(string $extra = ''): void
{
    $base = 'clients.php?tab=enquiries';
    header('Location: ' . $base . $extra);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals($csrf, $token)) {
        $_SESSION['client_admin_flash'] = ['type' => 'error', 'message' => 'Your session expired. Please try again.'];
        ca_redirect();
    }

    $action = (string) ($_POST['action'] ?? '');
    $enquiryId = (int) ($_POST['enquiry_id'] ?? 0);

    try {
        if ($enquiryId <= 0) {
            throw new RuntimeException('Invalid enquiry selected.');
        }

        $stmt = $pdo->prepare('SELECT * FROM contact_enquiries WHERE id = ? LIMIT 1');
        $stmt->execute([$enquiryId]);
        $enquiry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$enquiry) {
            throw new RuntimeException('Enquiry not found.');
        }

        if ($action === 'send_reply') {
            $toEmail = trim((string) ($enquiry['email'] ?? ''));
            $recipientName = trim((string) ($enquiry['first_name'] ?? '') . ' ' . (string) ($enquiry['last_name'] ?? ''));
            $subject = trim((string) ($_POST['reply_subject'] ?? ''));
            $replyMessage = trim((string) ($_POST['reply_message'] ?? ''));
            if ($subject === '' || $replyMessage === '') {
                throw new RuntimeException('Please add a subject and reply message.');
            }

            $sendResult = erp_send_contact_enquiry_reply($toEmail, $recipientName, $subject, $replyMessage, $business);
            if (empty($sendResult['ok'])) {
                throw new RuntimeException((string) ($sendResult['error'] ?? 'Email could not be sent.'));
            }

            $replyStmt = $pdo->prepare(
                'INSERT INTO contact_enquiry_replies (enquiry_id, user_id, to_email, subject, message)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $replyStmt->execute([$enquiryId, $userId ?: null, $toEmail, $subject, $replyMessage]);
            $pdo->prepare("UPDATE contact_enquiries SET status = 'contacted' WHERE id = ? AND status = 'new'")->execute([$enquiryId]);
            $_SESSION['client_admin_flash'] = ['type' => 'success', 'message' => 'Reply sent to the customer.'];
            ca_redirect('&enquiry_id=' . $enquiryId);
        }

        if ($action === 'set_status') {
            $status = (string) ($_POST['status'] ?? 'new');
            if (!in_array($status, ['new', 'contacted', 'converted', 'closed'], true)) {
                throw new RuntimeException('Invalid enquiry status.');
            }
            $upd = $pdo->prepare('UPDATE contact_enquiries SET status = ? WHERE id = ?');
            $upd->execute([$status, $enquiryId]);
            $_SESSION['client_admin_flash'] = ['type' => 'success', 'message' => 'Enquiry updated.'];
            ca_redirect('&enquiry_id=' . $enquiryId);
        }

        if ($action === 'convert_client') {
            $clientId = erp_find_or_create_client_from_enquiry($pdo, $enquiry, $userId);
            $upd = $pdo->prepare(
                "UPDATE contact_enquiries
                 SET status = 'converted', client_id = ?, converted_by = ?, converted_at = NOW()
                 WHERE id = ?"
            );
            $upd->execute([$clientId, $userId ?: null, $enquiryId]);
            $_SESSION['client_admin_flash'] = ['type' => 'success', 'message' => 'Enquiry converted to a client.'];
            ca_redirect('&enquiry_id=' . $enquiryId);
        }
    } catch (Throwable $e) {
        $_SESSION['client_admin_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
        ca_redirect($enquiryId > 0 ? '&enquiry_id=' . $enquiryId : '');
    }
}

$flash = $_SESSION['client_admin_flash'] ?? null;
unset($_SESSION['client_admin_flash']);

$activeTab = (string) ($_GET['tab'] ?? 'clients');
if (!in_array($activeTab, ['clients', 'enquiries'], true)) {
    $activeTab = 'clients';
}
$selectedEnquiryId = (int) ($_GET['enquiry_id'] ?? 0);
$search = trim((string) ($_GET['search'] ?? ''));

$clientParams = [];
$clientWhere = '';
if ($search !== '') {
    $clientWhere = 'WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?';
    $like = '%' . $search . '%';
    $clientParams = [$like, $like, $like];
}
$clientStmt = $pdo->prepare("SELECT id, name, email, phone, address, contact_person FROM clients $clientWhere ORDER BY name ASC, id DESC LIMIT 300");
$clientStmt->execute($clientParams);
$clients = $clientStmt->fetchAll(PDO::FETCH_ASSOC);

$enquiryStmt = $pdo->query(
    "SELECT e.*, c.name AS client_name
     FROM contact_enquiries e
     LEFT JOIN clients c ON c.id = e.client_id
     ORDER BY
        CASE e.status WHEN 'new' THEN 0 WHEN 'contacted' THEN 1 WHEN 'converted' THEN 2 ELSE 3 END,
        e.created_at DESC,
        e.id DESC
     LIMIT 300"
);
$enquiries = $enquiryStmt->fetchAll(PDO::FETCH_ASSOC);

$counts = ['new' => 0, 'contacted' => 0, 'converted' => 0, 'closed' => 0];
foreach ($enquiries as $row) {
    $status = (string) ($row['status'] ?? 'new');
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

$selectedEnquiry = null;
foreach ($enquiries as $row) {
    if ((int) $row['id'] === $selectedEnquiryId) {
        $selectedEnquiry = $row;
        break;
    }
}
if (!$selectedEnquiry && !empty($enquiries)) {
    $selectedEnquiry = $enquiries[0];
}

$selectedReplies = [];
if ($selectedEnquiry) {
    $replyStmt = $pdo->prepare(
        "SELECT r.*, u.username
         FROM contact_enquiry_replies r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.enquiry_id = ?
         ORDER BY r.sent_at DESC, r.id DESC
         LIMIT 20"
    );
    $replyStmt->execute([(int) $selectedEnquiry['id']]);
    $selectedReplies = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .ca-page { display: grid; gap: 18px; }
    .ca-top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .ca-title h1 { margin:0; font-size:28px; line-height:1.15; color:#111827; }
    .ca-title p { margin:6px 0 0; color:#64748b; }
    .ca-tabs { display:flex; gap:8px; flex-wrap:wrap; }
    .ca-tab { display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border:1px solid #e2e8f0; border-radius:8px; background:#fff; color:#334155; text-decoration:none; font-weight:700; }
    .ca-tab.is-active { background:#f97316; color:#fff; border-color:#f97316; }
    .ca-panel { min-width:0; background:#fff; border:1px solid #e5e7eb; border-radius:8px; box-shadow:0 14px 28px rgba(15,23,42,.06); overflow:hidden; }
    .ca-panel-head { padding:16px 18px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .ca-panel-head h2 { margin:0; font-size:18px; color:#111827; }
    .ca-grid { display:grid; grid-template-columns:minmax(360px, 38%) minmax(0, 1fr); gap:18px; align-items:start; }
    .ca-empty { padding:28px; color:#64748b; text-align:center; }
    .ca-search { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:0!important; align-items:center; }
    .ca-search .erp-search-input { min-width:240px; }
    .ca-clients-panel .ca-panel-head { align-items:center; }
    .ca-clients-meta { margin:4px 0 0; font-size:13px; color:#64748b; font-weight:600; }
    .ca-clients-table-wrap { padding:0 18px 18px; }
    #clientsTable.erp-student-table.ca-clients-table {
        width:100%;
        min-width:920px;
        table-layout:fixed;
        border-collapse:collapse;
    }
    #clientsTable.erp-student-table.ca-clients-table tbody td { vertical-align:middle!important; }
    #clientsTable.erp-student-table thead th.ca-col-id .st-th-wrap,
    #clientsTable.erp-student-table thead th.ca-col-id .st-th-main { justify-content:center; align-items:center; }
    #clientsTable.erp-student-table.ca-clients-table tbody tr[data-client-row="1"]:hover td { background:#fff7ed!important; }
    #clientsTable.erp-student-table .st-cell-primary { color:#1e293b; font-weight:700; }
    #clientsTable.erp-student-table th.sort-asc .st-sort-up,
    #clientsTable.erp-student-table th.sort-desc .st-sort-down { color:#c2410c; }
    #clientsTable .ca-client-id {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        min-width:44px;
        padding:4px 10px;
        border-radius:999px;
        background:#eff6ff;
        border:1px solid #dbeafe;
        color:#1d4ed8;
        font-size:12px;
        font-weight:800;
    }
    #clientsTable .ca-client-cell {
        display:flex;
        align-items:center;
        gap:10px;
        min-width:0;
    }
    #clientsTable .ca-client-avatar {
        width:34px;
        height:34px;
        border-radius:10px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        font-size:13px;
        font-weight:800;
        color:#fff;
        background:#2563eb;
    }
    #clientsTable .ca-client-avatar--1 { background:#2563eb; }
    #clientsTable .ca-client-avatar--2 { background:#0891b2; }
    #clientsTable .ca-client-avatar--3 { background:#7c3aed; }
    #clientsTable .ca-client-avatar--4 { background:#db2777; }
    #clientsTable .ca-client-avatar--0 { background:#ea580c; }
    #clientsTable .ca-contact-link {
        color:#1d4ed8;
        text-decoration:none;
        font-weight:600;
        word-break:break-word;
    }
    #clientsTable .ca-contact-link:hover { text-decoration:underline; }
    #clientsTable .ca-contact-sub {
        display:block;
        margin-top:3px;
        color:#64748b;
        font-size:12px;
        font-weight:600;
    }
    #clientsTable .ca-col-address .qt-td-inner {
        display:block;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }
    #clientsTable .ca-col-person .qt-td-inner,
    #clientsTable .ca-col-phone .qt-td-inner { color:#475569; }
    #clientsTable tbody tr.qt-helper-row td { border-bottom:none!important; }
    .ca-btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid #cbd5e1; border-radius:8px; padding:9px 12px; background:#fff; color:#334155; text-decoration:none; font-weight:700; cursor:pointer; }
    .ca-btn-primary { background:#f97316; border-color:#f97316; color:#fff; }
    .ca-btn-green { border-color:#86efac; color:#15803d; background:#f0fdf4; }
    .ca-btn-muted { color:#64748b; }
    .ca-status { display:inline-flex; align-items:center; gap:6px; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:800; }
    .ca-status-new { background:#fff7ed; color:#c2410c; }
    .ca-status-contacted { background:#eff6ff; color:#1d4ed8; }
    .ca-status-converted { background:#ecfdf5; color:#15803d; }
    .ca-status-closed { background:#f1f5f9; color:#475569; }
    .ca-detail { padding:18px; display:grid; gap:16px; }
    .ca-detail-title { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
    .ca-detail-title h3 { margin:0; font-size:20px; color:#111827; }
    .ca-meta { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
    .ca-meta div { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; }
    .ca-meta span { display:block; color:#64748b; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
    .ca-inbox { display:grid; gap:10px; padding:14px; max-height:620px; overflow:auto; background:linear-gradient(180deg,#fff 0%,#fff7ed 100%); }
    .ca-inbox-row { display:grid; gap:10px; padding:14px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; color:#1f2937; text-decoration:none; box-shadow:0 8px 18px rgba(15,23,42,.04); transition:transform .16s ease, border-color .16s ease, box-shadow .16s ease; }
    .ca-inbox-row:hover { transform:translateY(-1px); border-color:#fdba74; box-shadow:0 14px 24px rgba(249,115,22,.12); }
    .ca-inbox-row.is-selected { border-color:#f97316; box-shadow:0 0 0 2px rgba(249,115,22,.14), 0 14px 26px rgba(249,115,22,.14); }
    .ca-inbox-top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
    .ca-inbox-name { font-weight:900; color:#111827; }
    .ca-inbox-email { color:#64748b; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; }
    .ca-inbox-date { color:#64748b; font-size:12px; font-weight:700; white-space:nowrap; }
    .ca-inbox-service { display:flex; align-items:center; justify-content:space-between; gap:8px; }
    .ca-service-pill { display:inline-flex; align-items:center; gap:7px; padding:6px 9px; border-radius:8px; background:#f8fafc; border:1px solid #e2e8f0; color:#334155; font-size:12px; font-weight:800; }
    .ca-inbox-preview { color:#475569; line-height:1.45; font-size:13px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .ca-message { white-space:pre-wrap; background:#111827; color:#f8fafc; border-radius:8px; padding:14px; line-height:1.55; }
    .ca-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .ca-split { display:grid; grid-template-columns:minmax(0, 1fr) 360px; gap:16px; align-items:start; }
    .ca-compose { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; display:grid; gap:10px; position:sticky; top:92px; }
    .ca-compose h4, .ca-thread h4 { margin:0; font-size:15px; color:#111827; }
    .ca-field { display:grid; gap:6px; }
    .ca-field label { font-size:12px; text-transform:uppercase; letter-spacing:.04em; font-weight:800; color:#64748b; }
    .ca-field input, .ca-field textarea { width:100%; border:1px solid #cbd5e1; border-radius:8px; padding:10px 12px; font:inherit; background:#fff; color:#111827; }
    .ca-field textarea { min-height:160px; resize:vertical; line-height:1.5; }
    .ca-thread { display:grid; gap:12px; }
    .ca-thread-title { display:flex; align-items:center; justify-content:space-between; gap:10px; padding-top:2px; }
    .ca-thread-title h4 { display:flex; align-items:center; gap:8px; }
    .ca-thread-count { color:#64748b; font-size:12px; font-weight:800; background:#f1f5f9; border-radius:999px; padding:5px 9px; }
    .ca-reply { border:1px solid #e5e7eb; border-radius:8px; padding:14px; background:#fff; box-shadow:0 8px 20px rgba(15,23,42,.04); }
    .ca-reply-customer { border-color:#fdba74; background:#fff7ed; }
    .ca-reply-admin { border-color:#bfdbfe; background:#eff6ff; }
    .ca-reply-head { display:flex; justify-content:space-between; gap:8px; color:#64748b; font-size:12px; margin-bottom:8px; flex-wrap:wrap; }
    .ca-reply-who { display:inline-flex; align-items:center; gap:7px; font-weight:900; color:#334155; }
    .ca-reply-customer .ca-reply-who { color:#9a3412; }
    .ca-reply-admin .ca-reply-who { color:#1d4ed8; }
    .ca-reply-subject { font-weight:800; color:#111827; margin-bottom:6px; }
    .ca-reply-body { color:#334155; white-space:pre-wrap; line-height:1.6; }
    .ca-engage { border:1px solid #fed7aa; background:#fff7ed; color:#9a3412; border-radius:8px; padding:12px; line-height:1.45; }
    .ca-engage strong { display:block; color:#7c2d12; margin-bottom:4px; }
    .ca-flash { border-radius:8px; padding:12px 14px; font-weight:700; }
    .ca-flash-success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
    .ca-flash-error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
    .ca-modal-backdrop { position:fixed; inset:0; z-index:9998; display:none; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.42); backdrop-filter:blur(6px); }
    .ca-modal-backdrop.is-visible { display:flex; }
    .ca-modal { width:min(460px, 100%); background:#fff; border-radius:10px; box-shadow:0 24px 70px rgba(15,23,42,.28); border:1px solid #e5e7eb; overflow:hidden; transform:translateY(8px) scale(.98); opacity:0; transition:opacity .18s ease, transform .18s ease; }
    .ca-modal-backdrop.is-visible .ca-modal { transform:translateY(0) scale(1); opacity:1; }
    .ca-modal-head { display:flex; align-items:center; gap:12px; padding:18px 20px 0; }
    .ca-modal-icon { width:42px; height:42px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; }
    .ca-modal-icon-success { background:#dcfce7; color:#15803d; }
    .ca-modal-icon-error { background:#fee2e2; color:#b91c1c; }
    .ca-modal-title { margin:0; font-size:18px; color:#111827; }
    .ca-modal-body { padding:12px 20px 20px 74px; color:#475569; line-height:1.5; }
    .ca-modal-actions { display:flex; justify-content:flex-end; gap:10px; padding:14px 20px; background:#f8fafc; border-top:1px solid #e5e7eb; }
    @media (max-width: 1280px) { .ca-split { grid-template-columns:1fr; } }
    @media (max-width: 1024px) { .ca-grid { grid-template-columns:1fr; } }
    @media (max-width: 700px) { .ca-meta { grid-template-columns:1fr; } .ca-search { width:100%; } .ca-search input { min-width:0; width:100%; } }
</style>

<div class="ca-page">
    <div class="ca-top">
        <div class="ca-title">
            <h1>Clients</h1>
            <p>Manage saved clients and turn website quote requests into ERP client records.</p>
        </div>
        <div class="ca-tabs" role="tablist" aria-label="Client admin sections">
            <a class="ca-tab <?php echo $activeTab === 'clients' ? 'is-active' : ''; ?>" href="Client/clients.php?tab=clients">
                <i class="fas fa-users" aria-hidden="true"></i> Clients
            </a>
            <a class="ca-tab <?php echo $activeTab === 'enquiries' ? 'is-active' : ''; ?>" href="Client/clients.php?tab=enquiries">
                <i class="fas fa-inbox" aria-hidden="true"></i> Website enquiries
                <?php if ($counts['new'] > 0): ?><span><?php echo (int) $counts['new']; ?></span><?php endif; ?>
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="ca-modal-backdrop" id="caFlashModal" role="dialog" aria-modal="true" aria-labelledby="caFlashTitle">
            <div class="ca-modal">
                <div class="ca-modal-head">
                    <?php $flashType = (string) ($flash['type'] ?? 'success'); ?>
                    <div class="ca-modal-icon ca-modal-icon-<?php echo $flashType === 'error' ? 'error' : 'success'; ?>" aria-hidden="true">
                        <i class="fas <?php echo $flashType === 'error' ? 'fa-triangle-exclamation' : 'fa-check'; ?>"></i>
                    </div>
                    <h3 class="ca-modal-title" id="caFlashTitle"><?php echo $flashType === 'error' ? 'Action needed' : 'Message sent'; ?></h3>
                </div>
                <div class="ca-modal-body">
                    <?php echo ca_h($flash['message'] ?? ''); ?>
                </div>
                <div class="ca-modal-actions">
                    <button type="button" class="ca-btn ca-btn-primary" id="caFlashClose">Done</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'enquiries'): ?>
        <div class="ca-grid">
            <section class="ca-panel">
                <div class="ca-panel-head">
                    <h2>Website enquiries</h2>
                    <div class="ca-tabs" aria-label="Enquiry counts">
                        <span class="ca-status ca-status-new">New <?php echo (int) $counts['new']; ?></span>
                        <span class="ca-status ca-status-contacted">Contacted <?php echo (int) $counts['contacted']; ?></span>
                        <span class="ca-status ca-status-converted">Converted <?php echo (int) $counts['converted']; ?></span>
                    </div>
                </div>
                <?php if (empty($enquiries)): ?>
                    <div class="ca-empty">No website enquiries have been received yet.</div>
                <?php else: ?>
                    <div class="ca-inbox" aria-label="Website enquiries inbox">
                        <?php foreach ($enquiries as $row): ?>
                            <?php
                            $status = (string) ($row['status'] ?? 'new');
                            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
                            $preview = trim(preg_replace('/\s+/', ' ', (string) ($row['message'] ?? '')));
                            $received = !empty($row['created_at']) ? date('d M Y H:i', strtotime((string) $row['created_at'])) : '-';
                            $isSelected = $selectedEnquiry && (int) $selectedEnquiry['id'] === (int) $row['id'];
                            ?>
                            <a class="ca-inbox-row <?php echo $isSelected ? 'is-selected' : ''; ?>" href="Client/clients.php?tab=enquiries&amp;enquiry_id=<?php echo (int) $row['id']; ?>">
                                <div class="ca-inbox-top">
                                    <div style="min-width:0;">
                                        <div class="ca-inbox-name"><?php echo ca_h($name !== '' ? $name : 'Unknown customer'); ?></div>
                                        <div class="ca-inbox-email"><?php echo ca_h($row['email'] ?? ''); ?></div>
                                    </div>
                                    <div class="ca-inbox-date"><?php echo ca_h($received); ?></div>
                                </div>
                                <div class="ca-inbox-service">
                                    <span class="ca-service-pill"><i class="fas fa-wrench" aria-hidden="true"></i><?php echo ca_h($row['service'] ?: 'General enquiry'); ?></span>
                                    <span class="ca-status ca-status-<?php echo ca_h($status); ?>"><?php echo ca_h(erp_contact_enquiry_status_label($status)); ?></span>
                                </div>
                                <div class="ca-inbox-preview"><?php echo ca_h($preview !== '' ? $preview : 'No message provided.'); ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="ca-panel">
                <div class="ca-panel-head">
                    <h2>Enquiry detail</h2>
                </div>
                <?php if (!$selectedEnquiry): ?>
                    <div class="ca-empty">Select an enquiry to review it.</div>
                <?php else: ?>
                    <?php
                    $status = (string) ($selectedEnquiry['status'] ?? 'new');
                    $name = trim((string) ($selectedEnquiry['first_name'] ?? '') . ' ' . (string) ($selectedEnquiry['last_name'] ?? ''));
                    $clientId = (int) ($selectedEnquiry['client_id'] ?? 0);
                    ?>
                    <div class="ca-detail">
                        <div class="ca-split">
                            <div style="display:grid;gap:16px;min-width:0;">
                                <div class="ca-detail-title">
                                    <div>
                                        <h3><?php echo ca_h($name !== '' ? $name : 'Website enquiry'); ?></h3>
                                        <p style="margin:6px 0 0;color:#64748b"><?php echo ca_h($selectedEnquiry['service'] ?: 'General enquiry'); ?></p>
                                    </div>
                                    <span class="ca-status ca-status-<?php echo ca_h($status); ?>"><?php echo ca_h(erp_contact_enquiry_status_label($status)); ?></span>
                                </div>

                                <div class="ca-meta">
                                    <div><span>Email</span><?php echo ca_h($selectedEnquiry['email'] ?? '-'); ?></div>
                                    <div><span>Phone</span><?php echo $selectedEnquiry['phone'] ? '<a href="tel:' . ca_h($selectedEnquiry['phone']) . '">' . ca_h($selectedEnquiry['phone']) . '</a>' : '-'; ?></div>
                                    <div><span>Received</span><?php echo !empty($selectedEnquiry['created_at']) ? ca_h(date('d M Y H:i', strtotime((string) $selectedEnquiry['created_at']))) : '-'; ?></div>
                                    <div><span>Client record</span><?php echo $clientId > 0 ? '#' . $clientId . ' ' . ca_h($selectedEnquiry['client_name'] ?? '') : 'Not converted yet'; ?></div>
                                </div>

                                <div class="ca-thread">
                                    <div class="ca-thread-title">
                                        <h4><i class="fas fa-comments" aria-hidden="true"></i> Conversation</h4>
                                        <span class="ca-thread-count"><?php echo (int) (count($selectedReplies) + 1); ?> message<?php echo count($selectedReplies) === 0 ? '' : 's'; ?></span>
                                    </div>
                                    <article class="ca-reply ca-reply-customer">
                                        <div class="ca-reply-head">
                                            <span class="ca-reply-who"><i class="fas fa-user" aria-hidden="true"></i> Customer message</span>
                                            <span><?php echo !empty($selectedEnquiry['created_at']) ? ca_h(date('d M Y H:i', strtotime((string) $selectedEnquiry['created_at']))) : ''; ?></span>
                                        </div>
                                        <div class="ca-reply-subject"><?php echo ca_h($selectedEnquiry['service'] ?: 'General enquiry'); ?></div>
                                        <div class="ca-reply-body"><?php echo ca_h($selectedEnquiry['message'] ?? 'No message provided.'); ?></div>
                                    </article>
                                    <?php foreach ($selectedReplies as $reply): ?>
                                        <article class="ca-reply ca-reply-admin">
                                            <div class="ca-reply-head">
                                                <span class="ca-reply-who"><i class="fas fa-paper-plane" aria-hidden="true"></i> <?php echo ca_h($reply['username'] ?: 'Admin'); ?> replied</span>
                                                <span><?php echo !empty($reply['sent_at']) ? ca_h(date('d M Y H:i', strtotime((string) $reply['sent_at']))) : ''; ?></span>
                                            </div>
                                            <div class="ca-reply-subject"><?php echo ca_h($reply['subject'] ?? ''); ?></div>
                                            <div class="ca-reply-body"><?php echo ca_h($reply['message'] ?? ''); ?></div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <aside class="ca-compose">
                                <h4>Reply by email</h4>
                                <form method="post" style="display:grid;gap:10px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo ca_h($csrf); ?>">
                                    <input type="hidden" name="action" value="send_reply">
                                    <input type="hidden" name="enquiry_id" value="<?php echo (int) $selectedEnquiry['id']; ?>">
                                    <div class="ca-field">
                                        <label>To</label>
                                        <input type="email" value="<?php echo ca_h($selectedEnquiry['email'] ?? ''); ?>" readonly>
                                    </div>
                                    <div class="ca-field">
                                        <label for="replySubject">Subject</label>
                                        <input id="replySubject" name="reply_subject" value="Re: Website enquiry - <?php echo ca_h($selectedEnquiry['service'] ?: 'SV Auto Truck Repair'); ?>" required>
                                    </div>
                                    <div class="ca-field">
                                        <label for="replyMessage">Message</label>
                                        <textarea id="replyMessage" name="reply_message" required>Hello <?php echo ca_h($selectedEnquiry['first_name'] ?? ''); ?>,

Thank you for contacting SV Auto Truck Repair. We received your enquiry and can assist you with <?php echo ca_h($selectedEnquiry['service'] ?: 'your request'); ?>.

Please share any additional vehicle details, photos, or preferred booking time so we can guide you properly.

Regards,
SV Auto Truck Repair</textarea>
                                    </div>
                                    <button type="submit" class="ca-btn ca-btn-primary"><i class="fas fa-paper-plane" aria-hidden="true"></i> Send email</button>
                                </form>
                                <div class="ca-engage">
                                    <strong>Client portal follow-up</strong>
                                    Invite the customer to create an account or sign in after the first reply so quotations, approvals, invoices, and job cards can be tracked in one place.
                                </div>
                            </aside>
                        </div>

                        <div class="ca-actions">
                            <?php if ($clientId <= 0): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo ca_h($csrf); ?>">
                                    <input type="hidden" name="action" value="convert_client">
                                    <input type="hidden" name="enquiry_id" value="<?php echo (int) $selectedEnquiry['id']; ?>">
                                    <button type="submit" class="ca-btn ca-btn-green"><i class="fas fa-user-plus" aria-hidden="true"></i> Convert to client</button>
                                </form>
                            <?php endif; ?>
                            <?php foreach (['contacted' => 'Mark contacted', 'closed' => 'Close', 'new' => 'Reopen'] as $nextStatus => $label): ?>
                                <?php if ($status !== $nextStatus && !($nextStatus === 'new' && $status === 'converted')): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?php echo ca_h($csrf); ?>">
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="status" value="<?php echo ca_h($nextStatus); ?>">
                                        <input type="hidden" name="enquiry_id" value="<?php echo (int) $selectedEnquiry['id']; ?>">
                                        <button type="submit" class="ca-btn ca-btn-muted"><?php echo ca_h($label); ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($clientId > 0): ?>
                                <a class="ca-btn ca-btn-primary" href="JobCard/add_job_card.php?client_id=<?php echo $clientId; ?>"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Start job card</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    <?php else: ?>
        <section class="ca-panel ca-clients-panel">
            <div class="ca-panel-head">
                <div>
                    <h2>Saved clients</h2>
                    <p class="ca-clients-meta"><?php echo (int) count($clients); ?> record<?php echo count($clients) === 1 ? '' : 's'; ?><?php echo $search !== '' ? ' matching your search' : ''; ?></p>
                </div>
                <form method="get" class="ca-search erp-search-bar">
                    <input type="hidden" name="tab" value="clients">
                    <input type="search" name="search" class="erp-input erp-search-input" value="<?php echo ca_h($search); ?>" placeholder="Search name, email, or phone" aria-label="Search clients">
                    <button type="submit" class="erp-btn erp-btn-primary erp-btn-sm"><i class="fas fa-search" aria-hidden="true"></i> Search</button>
                    <?php if ($search !== ''): ?>
                        <a class="erp-btn erp-btn-secondary erp-btn-sm" href="Client/clients.php?tab=clients">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="erp-student-table-wrap ca-clients-table-wrap">
                <div class="erp-table-container qt-table-scroll">
                    <table class="erp-table erp-student-table ca-clients-table" id="clientsTable" data-st-row-selector="tr[data-client-row=&quot;1&quot;]">
                        <colgroup>
                            <col class="ca-col-id" style="width:84px">
                            <col class="ca-col-name" style="width:22%">
                            <col class="ca-col-email" style="width:22%">
                            <col class="ca-col-phone" style="width:14%">
                            <col class="ca-col-person" style="width:16%">
                            <col class="ca-col-address" style="width:26%">
                        </colgroup>
                        <thead>
                            <tr>
                                <?php echo st_sortable_th('ID', 'ca-col-id'); ?>
                                <?php echo st_sortable_th('Client', 'ca-col-name'); ?>
                                <?php echo st_sortable_th('Email', 'ca-col-email'); ?>
                                <?php echo st_sortable_th('Phone', 'ca-col-phone'); ?>
                                <?php echo st_sortable_th('Contact person', 'ca-col-person'); ?>
                                <?php echo st_plain_th('Address', 'ca-col-address'); ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clients)): ?>
                                <tr class="qt-helper-row">
                                    <td colspan="6">
                                        <div class="erp-empty-state">
                                            <div class="erp-empty-icon"><i class="fas fa-users" aria-hidden="true"></i></div>
                                            <div class="erp-empty-title"><?php echo $search !== '' ? 'No matching clients' : 'No clients yet'; ?></div>
                                            <div class="erp-empty-text"><?php echo $search !== '' ? 'Try a different name, email, or phone number.' : 'Converted enquiries and manual entries will appear here.'; ?></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $client): ?>
                                    <?php
                                    $clientId = (int) ($client['id'] ?? 0);
                                    $clientName = trim((string) ($client['name'] ?? ''));
                                    $clientEmail = trim((string) ($client['email'] ?? ''));
                                    $clientPhone = trim((string) ($client['phone'] ?? ''));
                                    $clientPerson = trim((string) ($client['contact_person'] ?? ''));
                                    $clientAddress = trim((string) ($client['address'] ?? ''));
                                    $clientInitial = strtoupper(substr($clientName, 0, 1));
                                    if ($clientInitial === '') {
                                        $clientInitial = '?';
                                    }
                                    $clientAvatarTone = abs(crc32($clientName !== '' ? $clientName : (string) $clientId)) % 5;
                                    ?>
                                    <tr data-client-row="1">
                                        <td class="ca-col-id" data-st-sort-value="<?php echo $clientId; ?>">
                                            <div class="qt-td-inner qt-td-inner--center">
                                                <span class="ca-client-id">#<?php echo $clientId; ?></span>
                                            </div>
                                        </td>
                                        <td class="ca-col-name" data-st-sort-value="<?php echo ca_h(strtolower($clientName)); ?>">
                                            <div class="qt-td-inner ca-client-cell">
                                                <span class="ca-client-avatar ca-client-avatar--<?php echo $clientAvatarTone; ?>" aria-hidden="true"><?php echo ca_h($clientInitial); ?></span>
                                                <span class="st-cell-primary"><?php echo ca_h($clientName !== '' ? $clientName : 'Unnamed client'); ?></span>
                                            </div>
                                        </td>
                                        <td class="ca-col-email" data-st-sort-value="<?php echo ca_h(strtolower($clientEmail)); ?>">
                                            <div class="qt-td-inner">
                                                <?php if ($clientEmail !== ''): ?>
                                                    <a class="ca-contact-link" href="mailto:<?php echo ca_h($clientEmail); ?>"><?php echo ca_h($clientEmail); ?></a>
                                                <?php else: ?>
                                                    <span class="ca-contact-sub">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="ca-col-phone" data-st-sort-value="<?php echo ca_h(strtolower($clientPhone)); ?>">
                                            <div class="qt-td-inner">
                                                <?php if ($clientPhone !== ''): ?>
                                                    <a class="ca-contact-link" href="tel:<?php echo ca_h($clientPhone); ?>"><?php echo ca_h($clientPhone); ?></a>
                                                <?php else: ?>
                                                    <span class="ca-contact-sub">-</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="ca-col-person" data-st-sort-value="<?php echo ca_h(strtolower($clientPerson)); ?>">
                                            <div class="qt-td-inner"><?php echo ca_h($clientPerson !== '' ? $clientPerson : '-'); ?></div>
                                        </td>
                                        <td class="ca-col-address" title="<?php echo ca_h($clientAddress); ?>">
                                            <div class="qt-td-inner"><?php echo ca_h($clientAddress !== '' ? $clientAddress : '-'); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($clients)): ?>
                    <?php st_render_table_footer('clientsTable', 25); ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
<script>
(function () {
    var modal = document.getElementById('caFlashModal');
    var close = document.getElementById('caFlashClose');
    if (!modal) return;
    function hideModal() {
        modal.classList.remove('is-visible');
        setTimeout(function () {
            if (modal && modal.parentNode) modal.parentNode.removeChild(modal);
        }, 220);
    }
    requestAnimationFrame(function () {
        modal.classList.add('is-visible');
        if (close) close.focus();
    });
    if (close) close.addEventListener('click', hideModal);
    modal.addEventListener('click', function (event) {
        if (event.target === modal) hideModal();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') hideModal();
    });
    setTimeout(hideModal, 3200);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
