<?php
/**
 * SV Auto — Create / edit quotation (PHP port of AddQuotation + QuotationPrint React components).
 * Persists structured data in quotations.details prefixed with QUOTATION_JSON_V1 + JSON payload.
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_role(['admin', 'manager']);
require_once __DIR__ . '/quotation_paper_signoff.inc.php';
require_once __DIR__ . '/quotation_manager_review.inc.php';
require_once __DIR__ . '/quotation_sync_invoice.inc.php';
require_once __DIR__ . '/../../../backend/config/notifications.php';
require_once __DIR__ . '/../JobCard/jc_form_helpers.inc.php';
require_once __DIR__ . '/../includes/trade_document_helpers.inc.php';

$aq_doc_hdr_bg = inv_doc_table_hdr_bg();
$aq_doc_bd_col = inv_doc_table_border();
$aq_doc_body_bg = inv_doc_table_body_bg();

$business = getBusiness();
$currency = getCurrency();
$sym = htmlspecialchars($currency['symbol'] ?? 'N$', ENT_QUOTES, 'UTF-8');

$editId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int) $_GET['id'] : null;
$aq_open_chat = $editId && isset($_GET['open_chat']) && (string) $_GET['open_chat'] === '1';
if ($editId && isset($_SESSION['user_id'])) {
    erp_mark_notifications_read_for_quotation($pdo, (int) $_SESSION['user_id'], $editId);
}
// Legacy preview=1 hid the CRM manage panel; strip it so old job-card links show full layout.
if ($editId && isset($_GET['preview']) && (string) $_GET['preview'] === '1') {
    $params = $_GET;
    unset($params['preview']);
    header('Location: add_quotation.php?' . http_build_query($params));
    exit;
}
$previewOnlyMode = false;
$pageError = $_GET['error'] ?? '';
$pageSuccess = $_GET['success'] ?? '';
$aq_current_role = strtolower((string) ($_SESSION['role_name'] ?? ''));
$aq_current_user = (string) ($_SESSION['username'] ?? 'User');

$header_img_url = '';
$footer_img_url = '';
$header_candidates = [
    __DIR__ . '/../../assets/images/header.png',
    __DIR__ . '/../assets/images/header.png',
];
$footer_candidates = [
    __DIR__ . '/../../assets/images/footer.png',
    __DIR__ . '/../assets/images/footer.png',
];
$aq_script_path_parts = array_values(array_filter(explode('/', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')))));
$aq_admin_idx = array_search('Admin', $aq_script_path_parts, true);
$aq_public_web_path = '/assets/';
if ($aq_admin_idx !== false) {
    $aq_public_web_path = '/' . implode('/', array_slice($aq_script_path_parts, 0, $aq_admin_idx)) . '/assets/';
}
$aq_asset_origin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
foreach ($header_candidates as $header_path) {
    if (is_file($header_path)) {
        $header_img_url = $aq_asset_origin . $aq_public_web_path . 'images/header.png';
        break;
    }
}
foreach ($footer_candidates as $footer_path) {
    if (is_file($footer_path)) {
        $footer_img_url = $aq_asset_origin . $aq_public_web_path . 'images/footer.png';
        break;
    }
}

/** @return array<string,mixed>|null */
function aq_parse_quotation_details(?string $details): ?array
{
    if ($details === null || $details === '') {
        return null;
    }
    if (strpos($details, Q_JSON_MARKER) !== 0) {
        return null;
    }
    $json = substr($details, strlen(Q_JSON_MARKER));
    // Some records append a human-readable summary after the JSON payload.
    $summaryPos = strpos($json, "\n\n— Line summary —");
    if ($summaryPos !== false) {
        $json = substr($json, 0, $summaryPos);
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function aq_js_json($value): string
{
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? 'null' : $json;
}

function aq_next_quote_number(PDO $pdo): string
{
    $maxNum = 0;
    $stmt = $pdo->query('SELECT details FROM quotations WHERE deleted_at IS NULL');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $details = (string)($row['details'] ?? '');
        if ($details === '') {
            continue;
        }
        $payload = aq_parse_quotation_details($details);
        $quoteNumber = '';
        if (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) {
            $quoteNumber = trim((string)($payload['form']['quote_number'] ?? ''));
        }
        if ($quoteNumber === '' && preg_match('/Quot\.\s*([^\r\n]+)/i', $details, $m)) {
            $quoteNumber = trim((string)$m[1]);
        }
        if ($quoteNumber !== '' && preg_match('/(\d+)(?!.*\d)/', $quoteNumber, $m)) {
            $n = (int)$m[1];
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }
    }
    return 'QT-' . str_pad((string)($maxNum + 1), 5, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_paper_signoff') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!is_admin()) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $quoteId = isset($_POST['quote_id']) ? (int) $_POST['quote_id'] : 0;
    $signedAt = trim((string) ($_POST['paper_signed_at'] ?? ''));
    $signedBy = trim((string) ($_POST['paper_signed_by'] ?? ''));
    if ($quoteId <= 0 || $signedAt === '') {
        echo json_encode(['ok' => false, 'error' => 'Quotation and sign-off date are required.']);
        exit;
    }
    if (!qt_update_paper_signoff($pdo, $quoteId, $signedAt, $signedBy)) {
        echo json_encode(['ok' => false, 'error' => 'Could not save paper sign-off.']);
        exit;
    }
    echo json_encode(['ok' => true, 'id' => $quoteId]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_paper_rejection') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!is_admin()) {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $quoteId = isset($_POST['quote_id']) ? (int) $_POST['quote_id'] : 0;
    $rejectedAt = trim((string) ($_POST['rejected_at'] ?? ''));
    $rejectedBy = trim((string) ($_POST['rejected_by'] ?? ''));
    $reason = trim((string) ($_POST['rejection_reason'] ?? ''));
    if ($quoteId <= 0 || $rejectedAt === '') {
        echo json_encode(['ok' => false, 'error' => 'Quotation and rejection date are required.']);
        exit;
    }
    if ($reason === '') {
        echo json_encode(['ok' => false, 'error' => 'Enter the reason the manager declined.']);
        exit;
    }
    if (!qt_update_paper_rejection($pdo, $quoteId, $rejectedAt, $rejectedBy, $reason)) {
        echo json_encode(['ok' => false, 'error' => 'Could not save manager rejection.']);
        exit;
    }
    echo json_encode(['ok' => true, 'id' => $quoteId]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'send_to_manager_review') {
    header('Content-Type: application/json; charset=UTF-8');
    if (!is_admin()) {
        echo json_encode(['ok' => false, 'error' => 'Only admin can send quotations for manager review.']);
        exit;
    }
    $quoteId = isset($_POST['quote_id']) ? (int) $_POST['quote_id'] : 0;
    if ($quoteId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Save the quotation first.']);
        exit;
    }
    $result = qt_send_to_manager_review(
        $pdo,
        $quoteId,
        (int) ($_SESSION['user_id'] ?? 0),
        (string) ($_SESSION['username'] ?? 'Admin')
    );
    if (!$result['ok']) {
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Could not send to manager.']);
        exit;
    }
    echo json_encode([
        'ok' => true,
        'id' => $result['id'],
        'sent_at' => $result['sent_at'] ?? '',
        'sent_by' => $result['sent_by'] ?? '',
        'notified' => (int) ($result['notified'] ?? 0),
        'manager_link' => app_url('Manager/Quotation/view_quotation.php?id=' . $quoteId),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'create_invoice_from_quotation_local') {
    header('Content-Type: application/json; charset=UTF-8');
    $quoteId = isset($_POST['quotation_id']) ? (int) $_POST['quotation_id'] : 0;
    if ($quoteId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Missing quotation ID']);
        exit;
    }

    try {
        $qStmt = $pdo->prepare('SELECT * FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $qStmt->execute([$quoteId]);
        $quote = $qStmt->fetch(PDO::FETCH_ASSOC);
        if (!$quote) {
            throw new RuntimeException('Quotation not found.');
        }
        if ((string)($quote['status'] ?? '') === 'rejected') {
            throw new RuntimeException('Rejected quotations cannot be invoiced.');
        }

        $existingStmt = $pdo->prepare('SELECT id, deleted_at FROM invoices WHERE quotation_id = ? ORDER BY id DESC LIMIT 1');
        $existingStmt->execute([$quoteId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            if (!empty($existing['deleted_at'])) {
                $pdo->prepare('UPDATE invoices SET deleted_at = NULL, updated_at = NOW() WHERE id = ?')->execute([(int) $existing['id']]);
                echo json_encode(['ok' => true, 'id' => (int) $existing['id'], 'restored' => true]);
                exit;
            }
            echo json_encode(['ok' => true, 'id' => (int) $existing['id'], 'existing' => true]);
            exit;
        }

        $colsStmt = $pdo->query('SHOW COLUMNS FROM invoices');
        $invoiceCols = [];
        while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $invoiceCols[(string) $col['Field']] = true;
        }

        $payload = aq_parse_quotation_details((string) ($quote['details'] ?? ''));
        $totals = is_array($payload) && isset($payload['totals']) && is_array($payload['totals']) ? $payload['totals'] : [];
        $amount = (float) ($totals['grand_total'] ?? $quote['amount'] ?? 0);
        $vatAmount = (float) ($totals['vat_amount'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $issuedDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+30 days'));
        $latest = (int) $pdo->query('SELECT MAX(id) FROM invoices')->fetchColumn();
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad((string) ($latest + 1), 4, '0', STR_PAD_LEFT);

        $data = [];
        if (isset($invoiceCols['quotation_id'])) {
            $data['quotation_id'] = $quoteId;
        }
        if (isset($invoiceCols['client_id']) && !empty($quote['client_id'])) {
            $data['client_id'] = (int) $quote['client_id'];
        }
        if (isset($invoiceCols['amount'])) {
            $data['amount'] = $amount;
        }
        if (isset($invoiceCols['invoice_number'])) {
            $data['invoice_number'] = $invoiceNumber;
        }
        if (isset($invoiceCols['vat_amount'])) {
            $data['vat_amount'] = $vatAmount;
        }
        if (isset($invoiceCols['issued_date'])) {
            $data['issued_date'] = $issuedDate;
        }
        if (isset($invoiceCols['due_date'])) {
            $data['due_date'] = $dueDate;
        }
        if (isset($invoiceCols['status_paid'])) {
            $data['status_paid'] = 'unpaid';
        }
        if (isset($invoiceCols['status'])) {
            $data['status'] = 'unpaid';
        }
        if (isset($invoiceCols['created_at'])) {
            $data['created_at'] = $now;
        }
        if (isset($invoiceCols['updated_at'])) {
            $data['updated_at'] = $now;
        }

        if ($data === []) {
            throw new RuntimeException('Invoices table has no supported columns.');
        }

        $fields = array_keys($data);
        $sql = 'INSERT INTO invoices (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $ins = $pdo->prepare($sql);
        $ins->execute(array_values($data));
        echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'existing' => false]);
        exit;
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'Failed to create invoice: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'download_quotation_pdf') {
    require_once __DIR__ . '/quotation_pdf.inc.php';

    $quoteId = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    if ($quoteId <= 0) {
        $quoteId = isset($_POST['quote_id']) ? (int) $_POST['quote_id'] : 0;
    }
    if ($quoteId <= 0) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Invalid quotation';
        exit;
    }

    $filename = preg_replace('/[^\w\-]+/', '_', (string) ($_POST['filename'] ?? ''));
    try {
        aq_stream_quotation_pdf($pdo, $quoteId, $filename !== '' ? $filename : null);
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'PDF generation failed: ' . $e->getMessage();
    }
    exit;
}

/**
 * AJAX / form save
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'save_quotation_json') {
    header('Content-Type: application/json; charset=UTF-8');
    $raw = $_POST['q_json'] ?? '';
    $payload = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($payload)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
        exit;
    }

    $form = $payload['form'] ?? [];
    $jobCardId = (int) ($payload['job_card_id'] ?? 0);
    $saveId = isset($payload['saved_id']) && is_numeric($payload['saved_id']) ? (int) $payload['saved_id'] : null;
    $quoteNum = trim((string) ($form['quote_number'] ?? ''));

    if ($quoteNum === '') {
        echo json_encode(['ok' => false, 'error' => 'Quote number is required.']);
        exit;
    }
    if ($jobCardId <= 0 && !$saveId) {
        echo json_encode(['ok' => false, 'error' => 'Select a job card before saving.']);
        exit;
    }

    if ($jobCardId > 0) {
        $jcStmt = $pdo->prepare('SELECT id, card_number FROM job_cards WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $jcStmt->execute([$jobCardId]);
        $jcRow = $jcStmt->fetch(PDO::FETCH_ASSOC);
        if (!$jcRow) {
            echo json_encode(['ok' => false, 'error' => 'Linked job card was not found or is no longer active.']);
            exit;
        }
        $linkedJobNo = trim((string) ($jcRow['card_number'] ?? ''));
        if ($linkedJobNo === '') {
            echo json_encode(['ok' => false, 'error' => 'Linked job card has no job card number.']);
            exit;
        }
        $form['job_no'] = $linkedJobNo;
        $dupCountStmt = $pdo->prepare('SELECT COUNT(*) FROM job_cards WHERE card_number = ? AND deleted_at IS NULL');
        $dupCountStmt->execute([$linkedJobNo]);
        if ((int) $dupCountStmt->fetchColumn() > 1) {
            echo json_encode([
                'ok' => false,
                'error' => 'Job card number ' . $linkedJobNo . ' is already used on multiple active job cards. Fix duplicates before saving.',
            ]);
            exit;
        }
        $jcUsage = jc_find_job_card_number_conflict($pdo, $linkedJobNo, $jobCardId);
        if ($jcUsage !== null) {
            echo json_encode([
                'ok' => false,
                'error' => 'Job card number ' . $linkedJobNo . ' is already used on another job card, quotation, or invoice.',
            ]);
            exit;
        }
    }

    try {
        // Ensure quote number is unique across active quotations.
        $dupeSql = 'SELECT id, details FROM quotations WHERE deleted_at IS NULL';
        $dupeParams = [];
        if ($saveId) {
            $dupeSql .= ' AND id <> ?';
            $dupeParams[] = $saveId;
        }
        $dupeQnStmt = $pdo->prepare($dupeSql);
        $dupeQnStmt->execute($dupeParams);
        $needleQn = strtolower($quoteNum);
        while ($dupeRow = $dupeQnStmt->fetch(PDO::FETCH_ASSOC)) {
            $dupePayload = aq_parse_quotation_details($dupeRow['details'] ?? '');
            $dupeNum = '';
            if (is_array($dupePayload) && isset($dupePayload['form']) && is_array($dupePayload['form'])) {
                $dupeNum = trim((string)($dupePayload['form']['quote_number'] ?? ''));
            }
            if ($dupeNum !== '' && strtolower($dupeNum) === $needleQn) {
                echo json_encode(['ok' => false, 'error' => 'Quote number already exists. Use a different number.']);
                exit;
            }
        }

        $stmt = $pdo->prepare(
            'SELECT jc.*, c.id AS client_id
        FROM job_cards jc
             LEFT JOIN clients c ON jc.client_id = c.id
             WHERE jc.id = ? AND jc.deleted_at IS NULL'
        );
        $stmt->execute([$jobCardId]);
        $jc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$jc) {
            if ($saveId) {
                $qf = $pdo->prepare('SELECT job_card_id, client_id FROM quotations WHERE id = ? AND deleted_at IS NULL');
                $qf->execute([$saveId]);
                $qfr = $qf->fetch(PDO::FETCH_ASSOC);
                if ($qfr) {
                    $jobCardId = (int) ($qfr['job_card_id'] ?? 0);
                    $jc = ['client_id' => $qfr['client_id'] ?? null];
                }
            }
            if (!$jc) {
                throw new RuntimeException('Job card not found.');
            }
        }

        $clientId = isset($jc['client_id']) && $jc['client_id'] ? (int) $jc['client_id'] : null;

        // Totals from payload (recalculate server-side minimally)
        $labourRows = isset($payload['labour_rows']) && is_array($payload['labour_rows']) ? $payload['labour_rows'] : [];
        $partsRows = isset($payload['parts_rows']) && is_array($payload['parts_rows']) ? $payload['parts_rows'] : [];
        $consRows = isset($payload['cons_rows']) && is_array($payload['cons_rows']) ? $payload['cons_rows'] : [];

        $filterPartRows = static function (array $rows): array {
            return array_values(array_filter($rows, static function ($r) {
                if (!is_array($r)) {
                    return false;
                }
                $name = trim((string) ($r['item_name'] ?? ''));
                $callout = trim((string) ($r['callout_type'] ?? ''));
                $qty = (float) ($r['qty'] ?? 0);
                $unitCost = (float) ($r['unit_cost'] ?? 0);
                $total = (float) ($r['total'] ?? 0);

                return $name !== '' || $callout !== '' || $qty > 0 || $unitCost > 0 || $total > 0;
            }));
        };
        $partsRows = $filterPartRows($partsRows);
        $consRows = $filterPartRows($consRows);

        $partsPrintCount = 0;
        foreach ($partsRows as $r) {
            if (is_array($r) && !empty($r['include_in_print'])) {
                $partsPrintCount++;
            }
        }
        $consPrintCount = 0;
        foreach ($consRows as $r) {
            if (is_array($r) && !empty($r['include_in_print'])) {
                $consPrintCount++;
            }
        }
        if (!is_array($form)) {
            $form = [];
        }
        $form['blank_parts_lines'] = $partsPrintCount > 0 ? $partsPrintCount : count($partsRows);
        $form['blank_cons_lines'] = $consPrintCount > 0 ? $consPrintCount : count($consRows);

        $labSum = 0.0;
        foreach ($labourRows as $r) {
            if (!is_array($r) || empty($r['include_in_print'])) {
                continue;
            }
            $labSum += (float) ($r['total'] ?? 0);
        }
        $partSum = 0.0;
        foreach ($partsRows as $r) {
            if (!is_array($r) || empty($r['include_in_print'])) {
                continue;
            }
            $partSum += (float) ($r['total'] ?? 0);
        }
        foreach ($consRows as $r) {
            if (!is_array($r) || empty($r['include_in_print'])) {
                continue;
            }
            $partSum += (float) ($r['total'] ?? 0);
        }
        $vatRate = (float) ($form['vat_rate'] ?? 15);
        $subtotal = $labSum + $partSum;
        $vatAmount = round($subtotal * ($vatRate / 100), 2);
        $grandTotal = round($subtotal + $vatAmount, 2);

        $persist = [
            'v' => 1,
            'form' => $form,
            'labour_rows' => $labourRows,
            'parts_rows' => $partsRows,
            'cons_rows' => $consRows,
            'totals' => [
                'labour_total' => $labSum,
                'parts_total' => $partSum,
                'subtotal' => $subtotal,
                'vat_amount' => $vatAmount,
                'grand_total' => $grandTotal,
            ],
        ];

        $details = Q_JSON_MARKER . json_encode($persist, JSON_UNESCAPED_UNICODE)
            . "\n\n— Line summary —\nQuot. " . $quoteNum . "\nTotal " . (($currency['symbol'] ?? 'N$') . number_format($grandTotal, 2));

        $submittedAt = trim((string) ($form['date'] ?? '')) ?: date('Y-m-d');
        $mapStatus = (string) ($form['status'] ?? 'draft');
        $dbStatus = 'pending_manager';
        if ($mapStatus === 'pending' || $mapStatus === 'pending_manager') {
            $dbStatus = 'pending_manager';
        }
        if ($mapStatus === 'sent_back_admin') {
            $dbStatus = 'sent_back_admin';
        }
        if ($mapStatus === 'approved' || $mapStatus === 'sent_to_client') {
            $dbStatus = 'approved';
        }
        if ($mapStatus === 'rejected') {
            $dbStatus = 'rejected';
        }

        if ($saveId) {
            // Ensure belongs to editable card / exists
            $chk = $pdo->prepare('SELECT id, job_card_id FROM quotations WHERE id = ? AND deleted_at IS NULL');
            $chk->execute([$saveId]);
            $row = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new RuntimeException('Quotation not found.');
            }

            // Optional: forbid changing job_card on edit to keep FK sane
            $pdo->prepare('UPDATE quotations SET job_card_id = ?, client_id = ?, amount = ?, details = ?, status = ?, submitted_at = ? WHERE id = ?')->execute([
                $jobCardId,
                $clientId,
                $grandTotal,
                $details,
                $dbStatus,
                $submittedAt,
                $saveId,
            ]);

            $invoiceSynced = aq_sync_linked_invoice_totals($pdo, $saveId, $grandTotal, $vatAmount);
            erp_audit_log($pdo, (int) ($_SESSION['user_id'] ?? 0), 'updated quotation', 'quotation', $saveId);

            echo json_encode(['ok' => true, 'id' => $saveId, 'invoice_synced' => $invoiceSynced]);
            exit;
        }

        // New: duplicate job_card guard (same rule as legacy create_quotation)
        $dupe = $pdo->prepare('SELECT id FROM quotations WHERE job_card_id = ? AND deleted_at IS NULL LIMIT 1');
        $dupe->execute([$jobCardId]);
        if ($dupe->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'A quotation already exists for this job card. Open it to edit instead.']);
            exit;
        }

        try {
            $pdo->prepare(
                'INSERT INTO quotations (job_card_id, client_id, amount, details, status, submitted_at, created_by)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                $jobCardId,
                $clientId,
                $grandTotal,
                $details,
                $dbStatus,
                $submittedAt,
                $_SESSION['user_id'] ?? null,
            ]);
        } catch (PDOException $e) {
            // Backward-compatible fallback for schemas without created_by.
            $isUnknownColumn = ((string) $e->getCode() === '42S22')
                || (strpos((string) $e->getMessage(), '1054') !== false)
                || (stripos((string) $e->getMessage(), 'Unknown column') !== false);
            if (!$isUnknownColumn) {
                throw $e;
            }
            $pdo->prepare(
                'INSERT INTO quotations (job_card_id, client_id, amount, details, status, submitted_at)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $jobCardId,
                $clientId,
                $grandTotal,
                $details,
                $dbStatus,
                $submittedAt,
            ]);
        }

        $newQuoteId = (int) $pdo->lastInsertId();
        erp_audit_log($pdo, (int) ($_SESSION['user_id'] ?? 0), 'created quotation', 'quotation', $newQuoteId);

        echo json_encode(['ok' => true, 'id' => $newQuoteId]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// ── Job cards dropdown (eligible for NEW = no quotation; EDIT includes current JC) ──
$sqlJc = "
    SELECT jc.id, jc.card_number, jc.extra_data, jc.created_at AS jc_created_at,
           c.name AS client_name, c.phone AS client_phone,
           c.email AS client_email, c.address AS client_address,
           c.contact_person AS client_contact_person,
           v.reg_no, v.model, v.vin_no
    FROM job_cards jc
    LEFT JOIN clients c ON jc.client_id = c.id
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    WHERE jc.deleted_at IS NULL ";
$paramsJc = [];
if ($editId) {
    $sqlJc .= " AND (
        NOT EXISTS (
            SELECT 1 FROM quotations q
            WHERE q.job_card_id = jc.id AND q.deleted_at IS NULL
        )
        OR EXISTS (
            SELECT 1 FROM quotations q2 WHERE q2.id = ? AND q2.job_card_id = jc.id
        )
    ) ORDER BY jc.id DESC";
    $paramsJc[] = $editId;
    } else {
    $sqlJc .= ' AND NOT EXISTS (
        SELECT 1 FROM quotations q WHERE q.job_card_id = jc.id AND q.deleted_at IS NULL
    ) ORDER BY jc.id DESC';
}
$jcStmt = $pdo->prepare($sqlJc);
$jcStmt->execute($paramsJc);
$job_cards = $jcStmt->fetchAll(PDO::FETCH_ASSOC);

$inventory = [];
try {
    $inventory = $pdo->query('SELECT part_name, price, stock FROM inventory WHERE stock > 0 ORDER BY part_name')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
    $inventory = [];
}

$saved_payload = null;
$loadedSavedId = null;
$forcedJobCard = null;
$linkedJobCardId = 0;
$aq_quote_status = 'draft';
$aq_client_status = '';
$aq_workflow_initial = null;

if ($editId) {
    $qStmt = $pdo->prepare(
        'SELECT q.*, jc.card_number FROM quotations q LEFT JOIN job_cards jc ON jc.id = q.job_card_id WHERE q.id = ? AND q.deleted_at IS NULL'
    );
    $qStmt->execute([$editId]);
    $qRow = $qStmt->fetch(PDO::FETCH_ASSOC);
    if ($qRow) {
        $loadedSavedId = (int) $qRow['id'];
        $forcedJobCard = (int) ($qRow['job_card_id'] ?? 0);
        $linkedJobCardId = (int) ($qRow['job_card_id'] ?? 0);
        $saved_payload = aq_parse_quotation_details($qRow['details'] ?? '');
        $aq_quote_status = (string) ($qRow['status'] ?? 'draft');
        $aq_client_status = (string) ($qRow['client_status'] ?? '');
        $formWf = (is_array($saved_payload) && isset($saved_payload['form']) && is_array($saved_payload['form']))
            ? $saved_payload['form'] : [];
        $aq_workflow_initial = qt_workflow_steps($aq_quote_status, $formWf, $aq_client_status, false);
        $aq_mgr_review_initial = qt_manager_review_info($formWf);
        $aq_manager_comments = qt_quotation_messages_list(is_array($saved_payload) ? $saved_payload : null);

        if ($linkedJobCardId > 0 && is_array($saved_payload) && is_array($saved_payload['form'] ?? null)) {
            $jcLabelStmt = $pdo->prepare('SELECT extra_data FROM job_cards WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $jcLabelStmt->execute([$linkedJobCardId]);
            $jcExtraRaw = $jcLabelStmt->fetchColumn();
            $jcExtra = is_string($jcExtraRaw) ? json_decode($jcExtraRaw, true) : null;
            if (is_array($jcExtra)) {
                $jcHeaderLabels = jc_general_header_labels_from_extra($jcExtra);
                $saved_payload['form']['general_header_labels'] = $jcHeaderLabels;
                $saved_payload['form']['attend_to_service_label'] = $jcHeaderLabels[0] ?? '';
                $saved_payload['form']['diagnostic_label'] = $jcHeaderLabels[1] ?? '';
            }
        }
    }
}
$aq_mgr_review_initial = $aq_mgr_review_initial ?? ['sent' => false, 'sent_at' => '', 'sent_by' => '', 'viewed_at' => ''];
$aq_manager_comments = $aq_manager_comments ?? [];
$aq_mgr_badge_text = 'Not sent to manager';
$aq_mgr_badge_class = 'pending';
if (!empty($aq_mgr_review_initial['sent'])) {
    if (!empty($aq_mgr_review_initial['viewed_at'])) {
        $aq_mgr_badge_text = 'Manager viewed';
        $aq_mgr_badge_class = 'signed';
    } else {
        $aq_mgr_badge_text = 'Sent for review';
        $aq_mgr_badge_class = 'sent';
    }
}

// Next quote label for NEW
$next_quote_num = '';
if (!$saved_payload) {
    $next_quote_num = aq_next_quote_number($pdo);
}

$aq_toolbar_title = 'Create Quotation';
$aq_display_quote = '';
if (is_array($saved_payload) && isset($saved_payload['form']['quote_number'])) {
    $aq_display_quote = trim((string) $saved_payload['form']['quote_number']);
}
if ($editId) {
    $aq_toolbar_title = 'Manage Quotation';
}

$pageTitle = $editId ? 'Manage Quotation' : 'Create Quotation';
$aq_quote_ref_label = '';
if ($editId) {
    $aq_quote_ref_label = 'QTN-' . str_pad((string) $editId, 5, '0', STR_PAD_LEFT);
} elseif ($aq_display_quote !== '') {
    $aq_quote_ref_label = $aq_display_quote;
}

$aq_paper_signoff_date = date('Y-m-d');
if ($editId && is_array($saved_payload) && isset($saved_payload['form']) && is_array($saved_payload['form'])) {
    $aq_form_signed_at = trim((string) ($saved_payload['form']['approved_at'] ?? $saved_payload['form']['paper_signed_at'] ?? ''));
    if ($aq_form_signed_at !== '') {
        $aq_paper_signoff_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $aq_form_signed_at)
            ? substr($aq_form_signed_at, 0, 10)
            : date('Y-m-d', strtotime($aq_form_signed_at) ?: time());
    }
} elseif ($next_quote_num !== '') {
    $aq_quote_ref_label = $next_quote_num;
}

$aq_paper_rejection_date = '';
if ($editId && is_array($saved_payload) && isset($saved_payload['form']) && is_array($saved_payload['form'])) {
    $aq_form_rejected_at = trim((string) ($saved_payload['form']['rejected_at'] ?? ''));
    if ($aq_form_rejected_at !== '') {
        $aq_paper_rejection_date = preg_match('/^\d{4}-\d{2}-\d{2}/', $aq_form_rejected_at)
            ? substr($aq_form_rejected_at, 0, 10)
            : date('Y-m-d', strtotime($aq_form_rejected_at) ?: time());
    }
}
// Absolute URL for creating invoice from quotation (reliable regardless of current URL)
$invoiceListUrl = dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/Invoice/invoices.php';
$erp_resume_title = 'New quotation';
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<style>
  body.print-open { overflow: hidden; }
  body.print-open .erp-sidebar,
  body.print-open .erp-topbar,
  body.print-open #aq-app,
  body.print-open .aq-quote-actions-panel,
  body.print-open .aq-manage-layout,
  body.print-open .aq-manage-sidebar { display: none !important; }
  #aq-print-shell {
    z-index: 99990 !important;
  }
  #aq-print-shell:not(.hidden) {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
  }
  #aq-print-shell:not(.hidden) .aq-print-shell-toolbar,
  #aq-print-shell:not(.hidden) .bottom-actions,
  #aq-print-shell:not(.hidden) .button-group {
    display: flex !important;
  }
  .aq-print-shell-toolbar {
    position: sticky;
    top: 0;
    z-index: 60;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    justify-content: center;
  }
  .aq-print-shell-toolbar #aq-print-titlebar {
    text-align: center;
    width: 100%;
  }
  /* preview-only: hide duplicate top edit blocks only; CRM sidebar + manage panel stay visible */
  .aq-preview-only .aq-edit-screen-only,
  .aq-preview-only .aq-hidden-source-block { display: none !important; }

  /* Print / download confirmation — compact (job-card delete modal scale) */
  #aqExportModal .aq-export-modal {
    width: min(380px, calc(100vw - 28px));
    max-width: 380px;
  }
  #aqExportModal .erp-modal-header {
    padding: 14px 18px;
  }
  #aqExportModal .erp-modal-title {
    font-size: 1rem;
  }
  #aqExportModal .aq-export-modal__body {
    text-align: center;
    padding: 18px 20px 20px;
  }
  #aqExportModal .aq-export-modal__icon-wrap {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 14px;
    background: #eff6ff;
  }
  #aqExportModal .aq-export-modal__icon-wrap--print {
    background: #fff7ed;
  }
  #aqExportModal .aq-export-modal__icon {
    font-size: 24px;
    color: #2563eb;
  }
  #aqExportModal .aq-export-modal__icon-wrap--print .aq-export-modal__icon {
    color: #ea580c;
  }
  #aqExportModal .aq-export-modal__prompt {
    color: #475569;
    font-size: 13px;
    margin: 0 0 6px;
    line-height: 1.4;
  }
  #aqExportModal .aq-export-modal__quote-name {
    color: #0f172a;
    font-size: 1.05rem;
    margin: 0 0 10px;
    font-weight: 700;
    line-height: 1.3;
    word-break: break-word;
  }
  #aqExportModal .aq-export-modal__hint {
    color: #64748b;
    font-size: 12px;
    margin: 0 auto;
    max-width: 30ch;
    line-height: 1.45;
  }
  #aqExportModal .erp-modal-footer {
    padding: 12px 18px 16px;
    gap: 8px;
  }
  #aqExportModal .erp-modal-footer .erp-btn {
    min-height: 36px;
    min-width: 5.5rem;
    padding: 8px 14px;
    font-size: 13px;
  }
  /* Safe-hide duplicated top capture blocks while preserving bound fields for print/data integrity. */
  .aq-hidden-source-block { display: none !important; }
  .aq-ic{width:100%;border:1px solid #cbd5e1;border-radius:10px;padding:.5rem .75rem;font-size:.875rem;outline:none;background:#fff;color:#0f172a}
  .aq-ic:focus{box-shadow:0 0 0 3px rgba(100,116,139,.15);border-color:#94a3b8}

  @media (max-width: 640px) {
    .aq-section1__toolbar-title { font-size: 1.5rem; }
    .aq-section1__status-row { font-size: 0.9375rem; }
    .aq-section1__badge { font-size: 0.875rem; padding: 5px 10px; }
  }

  .aq-section1 {
    background: transparent;
    border-radius: 0;
    padding: 0;
    margin-bottom: 24px;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  }
  .aq-section1-toolbar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
    border-radius: 0;
    box-shadow: none;
  }
  .aq-section1-toolbar-head {
    text-align: center;
    width: 100%;
    min-height: 0;
  }
  .aq-section1-toolbar-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    width: 100%;
  }
  .aq-section1-meta-left {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    min-width: 0;
  }
  .aq-section1__toolbar-title {
    font-size: 2rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1.25;
    letter-spacing: -0.02em;
    word-break: break-word;
  }
  .aq-section1__status-row {
    margin-top: 0;
    font-size: 1rem;
    font-weight: 500;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 8px;
    flex-wrap: wrap;
  }
  .aq-section1__badge {
    display: inline-block;
    font-size: 0.9375rem;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 9999px;
    line-height: 1.35;
    vertical-align: middle;
  }
  .aq-section1__badge--draft { background: #f3f4f6; color: #4b5563; }
  .aq-section1__badge--pending { background: #fef9c3; color: #854d0e; }
  .aq-section1__badge--approved { background: #dcfce7; color: #166534; }
  .aq-section1__badge--rejected { background: #fee2e2; color: #b91c1c; }
  .aq-section1__badge--sent { background: #dbeafe; color: #1e40af; }

  .aq-paper-signoff-panel{
    width:100%;
    margin:0;
    padding:16px 18px;
    border:2px solid #fdba74;
    border-left:5px solid #ea580c;
    border-radius:14px;
    background:linear-gradient(135deg,#fffbeb 0%,#fff7ed 55%,#fff 100%);
    box-shadow:0 2px 8px rgba(234,88,12,.1);
  }
  .aq-paper-signoff-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    flex-wrap:wrap;
    margin-bottom:8px;
  }
  .aq-paper-signoff-head h3{
    margin:0;
    font-size:14px;
    font-weight:700;
    color:#9a3412;
  }
  .aq-paper-signoff-hint{
    margin:0 0 12px;
    font-size:12px;
    color:#9a3412;
    line-height:1.45;
  }
  .aq-paper-signoff-fields{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:10px 14px;
  }
  .aq-paper-field{display:flex;flex-direction:column;gap:4px;}
  .aq-paper-field span{font-size:11px;font-weight:700;color:#7c2d12;text-transform:uppercase;letter-spacing:.04em;}
  .aq-paper-field input{
    border:1px solid #fdba74;border-radius:8px;padding:8px 10px;font-size:14px;background:#fff;
  }
  .aq-paper-status-badge{
    font-size:11px;font-weight:700;padding:5px 10px;border-radius:999px;white-space:nowrap;
  }
  .aq-paper-status-badge--pending{background:#ffedd5;color:#c2410c;}
  .aq-paper-status-badge--signed{background:#dcfce7;color:#166534;}
  .aq-paper-status-badge--rejected{background:#fee2e2;color:#b91c1c;}
  .aq-paper-rejection-panel{margin-top:0;border:1px solid #fecaca;border-radius:10px;padding:14px;background:#fffafb;}
  .aq-paper-rejection-panel .aq-paper-field textarea{min-height:72px;resize:vertical;}
  .aq-workflow-panel{
    width:100%;
    margin:0;
    padding:0;
    border:none;
    border-radius:0;
    background:transparent;
    box-shadow:none;
  }
  .aq-workflow-next{
    margin-bottom:12px;
    padding:12px 14px;
    border-radius:12px;
    background:linear-gradient(135deg,#fff7ed 0%,#ffedd5 100%);
    border:2px solid #fdba74;
    box-shadow:0 1px 3px rgba(234,88,12,.12);
  }
  .aq-workflow-next strong{display:block;font-size:13px;color:#9a3412;margin-bottom:4px;}
  .aq-workflow-next span{font-size:12px;color:#c2410c;line-height:1.4;}
  .aq-workflow-steps{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:6px;
    padding:5px;
    background:#e2e8f0;
    border-radius:12px;
  }
  .aq-workflow-step{
    flex:1 1 calc(25% - 6px);
    min-width:72px;
    padding:9px 10px;
    border-radius:9px;
    border:none;
    background:transparent;
    text-align:center;
    font-size:11px;
    font-weight:700;
    color:#475569;
    line-height:1.3;
    transition:background .15s ease,box-shadow .15s ease,color .15s ease;
  }
  @media (max-width:720px){
    .aq-workflow-step{flex:1 1 calc(50% - 6px);}
  }
  .aq-workflow-step.is-done{
    background:#fff;
    color:#047857;
    box-shadow:0 1px 3px rgba(15,23,42,.08);
  }
  .aq-workflow-step.is-current{
    background:#fff;
    color:#c2410c;
    box-shadow:0 1px 4px rgba(15,23,42,.1);
  }
  .aq-workflow-step-num{
    display:block;
    width:22px;height:22px;
    margin:0 auto 6px;
    border-radius:50%;
    line-height:22px;
    font-size:11px;
    background:#e2e8f0;
    color:#475569;
  }
  .aq-workflow-step.is-done .aq-workflow-step-num{background:#16a34a;color:#fff;}
  .aq-workflow-step.is-current .aq-workflow-step-num{background:#ea580c;color:#fff;}
  .aq-paper-signoff-actions{
    margin-top:12px;
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    align-items:center;
  }

  .aq-section1-actions {
    margin-top: 0;
    margin-bottom: 0;
  }
  .aq-section1-actions-bar {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
    gap: 10px 12px;
    padding-top: 1.25rem;
    margin-top: 0.5rem;
    border-top: 1px solid #e5e7eb;
  }
  #aq-status-msg.aq-s1-flash-hidden { display: none !important; }
  #aq-status-msg:not(.aq-s1-flash-hidden) {
    display: block;
    margin-top: 0.375rem;
    font-size: 0.9375rem;
    font-weight: 600;
    color: #16a34a;
  }
  .aq-section1-btn-grid {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 10px 12px;
  }
  .aq-s1-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    border: 1px solid transparent;
    background: #fff;
    line-height: 1.2;
    white-space: nowrap;
  }
  .aq-s1-btn--gray { border-color: #cbd5e1; color: #334155; }
  .aq-s1-btn--gray:hover { border-color: #94a3b8; background: #fafafa; }
  .aq-s1-btn--blue { border-color: #93c5fd; color: #1d4ed8; }
  .aq-s1-btn--blue:hover { border-color: #60a5fa; background: #eff6ff; }
  .aq-s1-btn--orange-outline { border-color: #fdba74; color: #c2410c; }
  .aq-s1-btn--orange-outline:hover { border-color: #fb923c; background: #fff7ed; }
  .aq-s1-btn--green-outline { border-color: #bbf7d0; color: #16a34a; }
  .aq-s1-btn--green-outline:disabled {
    border-color: #e2e8f0;
    color: #94a3b8;
    background: #fafafa;
    opacity: 0.55;
    cursor: not-allowed;
  }
  .aq-s1-btn--save {
    background: #f97316;
    color: #fff;
    border: none;
  }
  .aq-s1-btn--save:hover { background: #ea580c; }
  .aq-s1-btn--danger-outline { border-color: #fecaca; color: #b91c1c; background: #fff; }
  .aq-s1-btn--danger-outline:hover { border-color: #f87171; background: #fef2f2; }
  .aq-s1-btn--danger {
    background: #dc2626;
    color: #fff;
    border: none;
  }
  .aq-s1-btn--danger:hover { background: #b91c1c; }
  .aq-s1-btn {
    justify-content: center;
    text-align: center;
    min-height: 2.75rem;
  }
  .aq-name-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:1200}
  .aq-name-modal.show{display:flex}
  .aq-name-modal-card{width:min(92vw,460px);background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 20px 50px rgba(0,0,0,.25);padding:18px}
  .aq-name-modal-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
  .aq-name-modal-sub{font-size:.875rem;color:#475569;margin-bottom:10px}
  .aq-name-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}
  .jc-flash-wrap{
    position:fixed;inset:0;z-index:2300;display:flex;align-items:center;justify-content:center;
    background:rgba(15,23,42,.35);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);
    opacity:0;pointer-events:none;transition:opacity .2s ease;
  }
  .jc-flash-wrap.show{opacity:1;pointer-events:auto}
  .jc-flash-card{
    position:relative;
    width:min(92vw,420px);background:#fff;border:1px solid #e5e7eb;border-radius:16px;
    box-shadow:0 24px 55px rgba(0,0,0,.25);padding:18px 18px 14px;text-align:center;
    transform:translateY(8px);transition:transform .2s ease;
  }
  .jc-flash-close{
    position:absolute;top:10px;right:10px;
    width:32px;height:32px;border:none;border-radius:8px;
    background:#f1f5f9;color:#64748b;font-size:20px;line-height:1;
    cursor:pointer;display:inline-flex;align-items:center;justify-content:center;
  }
  .jc-flash-close:hover{background:#e2e8f0;color:#334155}
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
  .jc-flash-card--info .jc-flash-icon{background:#fff7ed;color:#ea580c;border:1px solid #fdba74}
  .jc-flash-card--info .jc-flash-body{color:#9a3412}
  .jc-flash-card--mgr{
    width:min(92vw,440px);
    padding:22px 22px 18px;
    text-align:left;
    position:relative;
  }
  .jc-flash-card--mgr .jc-flash-icon{margin:0 0 14px;width:48px;height:48px;font-size:20px}
  .jc-flash-card--mgr.jc-flash-card--success .jc-flash-icon{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
  .jc-flash-card--mgr.jc-flash-card--success .jc-flash-title{color:#1e3a8a}
  .jc-flash-card--mgr.jc-flash-card--success .jc-flash-body{color:#334155}
  .jc-flash-card--mgr.jc-flash-card--signoff.jc-flash-card--success .jc-flash-icon{background:#ecfdf3;color:#16a34a;border:1px solid #bbf7d0}
  .jc-flash-card--mgr.jc-flash-card--signoff.jc-flash-card--success .jc-flash-title{color:#14532d}
  .jc-flash-card--mgr.jc-flash-card--rejection.jc-flash-card--success .jc-flash-icon{background:#fff7ed;color:#c2410c;border:1px solid #fdba74}
  .jc-flash-card--mgr.jc-flash-card--rejection.jc-flash-card--success .jc-flash-title{color:#9a3412}
  .jc-flash-card--mgr.jc-flash-card--error .jc-flash-icon{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
  .jc-flash-card--mgr .jc-flash-title{font-size:1.125rem;margin-bottom:8px;text-align:left}
  .jc-flash-card--mgr .jc-flash-body{font-size:.9375rem;text-align:left;margin-bottom:10px}
  .jc-flash-card--mgr .jc-flash-hint{
    margin:0 0 16px;padding:10px 12px;border-radius:10px;
    background:#f8fafc;border:1px solid #e2e8f0;
    font-size:12px;line-height:1.5;color:#64748b;text-align:left;
  }
  .jc-flash-card--mgr .jc-flash-hint strong{color:#475569;font-weight:600}
  .jc-flash-btn{
    display:inline-flex;align-items:center;justify-content:center;
    min-width:120px;padding:10px 20px;border:none;border-radius:10px;
    font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;
    transition:background .15s ease,box-shadow .15s ease;
  }
  .jc-flash-card--success .jc-flash-btn{background:#2563eb;color:#fff;box-shadow:0 1px 2px rgba(37,99,235,.25)}
  .jc-flash-card--success .jc-flash-btn:hover{background:#1d4ed8}
  .jc-flash-card--error .jc-flash-btn{background:#dc2626;color:#fff}
  .jc-flash-card--error .jc-flash-btn:hover{background:#b91c1c}
  .jc-flash-wrap[hidden]{display:none!important}
  .jc-del-modal{
    position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:center;justify-content:center;z-index:2200;
  }
  .jc-del-modal.show{display:flex}
  .jc-del-card{
    width:min(92vw,460px);background:#fff;border:1px solid #e5e7eb;border-radius:14px;
    box-shadow:0 20px 50px rgba(0,0,0,.25);padding:18px;
  }
  .jc-del-title{font-size:1rem;font-weight:700;color:#0f172a;margin-bottom:6px}
  .jc-del-body{font-size:.9375rem;color:#334155;line-height:1.45}
  .jc-del-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}
  .jc-del-cancel{border-color:#cbd5e1;color:#334155}
  .jc-del-confirm{background:#dc2626;border-color:#b91c1c;color:#fff}
  .jc-del-confirm:hover{background:#b91c1c;border-color:#991b1b}
  .aq-cust-fields .aq-cust-field > label,
  .aq-vehicle-fields .aq-veh-field > label {
    display: block;
    text-align: left;
  }
  .aq-detail-cards {
    align-items: start;
    gap: 1rem !important;
  }
  .aq-detail-card {
    padding: 0.85rem 1.1rem !important;
    border-radius: 14px !important;
    border: 2px solid #cbd5e1 !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06) !important;
  }
  .aq-detail-card > h2 {
    margin-bottom: 0.5rem !important;
    font-size: 0.875rem !important;
    letter-spacing: 0.06em !important;
    color: #0f172a !important;
  }
  .aq-detail-card .aq-detail-stack > * + * {
    margin-top: 0.5rem !important;
  }
  .aq-detail-card .aq-ic {
    padding: 0.5625rem 0.875rem;
    font-size: 0.9375rem;
    border-radius: 0.5rem;
    min-height: 2.35rem;
    box-sizing: border-box;
    color: #0f172a;
    border-color: #cfd8e3;
    background: #fff;
  }
  .aq-detail-card .aq-ic::placeholder {
    color: #9ca3af;
  }
  .aq-detail-card label.block.text-xs,
  #aq-app .bg-white > label.block.text-xs {
    font-size: 0.8125rem !important;
    font-weight: 600 !important;
    letter-spacing: 0.02em;
    color: #374151 !important;
  }
  #aq-app .bg-white.rounded-xl.border.border-gray-200.p-5.mb-5 label {
    font-size: 0.8125rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.04em;
    color: #1f2937 !important;
  }
  #aq-job-card {
    font-size: 0.95rem !important;
    color: #0f172a !important;
  }
  /* Clear, bold section headings only */
  #aq-app h2,
  #aq-app h3,
  #aq-app .font-semibold.text-gray-700.text-sm.uppercase.tracking-wide,
  #aq-app .text-xs.font-semibold.text-gray-700.uppercase.tracking-wide {
    font-weight: 800 !important;
    color: #0f172a !important;
    letter-spacing: 0.06em !important;
  }
  #aq-toggle-rates,
  #aq-toggle-rates span {
    font-weight: 800 !important;
    color: #0f172a !important;
    letter-spacing: 0.04em !important;
  }
  /* Keep labour/parts/consumables dark-bar headings white */
  #aq-labour-card .bg-black h2,
  #aq-labour-card .bg-black button,
  #aq-app .bg-white.rounded-xl.border.border-gray-200.mb-5.overflow-hidden > .bg-black h2,
  #aq-app .bg-white.rounded-xl.border.border-gray-200.mb-5.overflow-hidden > .bg-black button,
  #aq-app .bg-black.text-white h2,
  #aq-app .bg-black.text-white span,
  #aq-app .bg-black.text-white button {
    color: #ffffff !important;
  }
  /* Keep delete icons consistent across labour/parts/consumables */
  #aq-app .lab-del,
  #aq-app .pp-del {
    color: #cbd5e1 !important;
    font-size: 0.95rem;
    line-height: 1;
    transition: color 0.15s ease, transform 0.15s ease;
  }
  #aq-app .lab-del:hover,
  #aq-app .pp-del:hover {
    color: #ef4444 !important;
    transform: translateY(-1px);
  }
  #aq-tbody-labour td:last-child,
  #aq-tbody-parts td:last-child,
  #aq-tbody-cons td:last-child {
    width: 2rem !important;
    text-align: center !important;
    padding-left: 0.25rem !important;
    padding-right: 0.25rem !important;
  }
  /* Clear row/column grid for parts + consumables */
  #aq-tbody-parts tr td,
  #aq-tbody-cons tr td {
    border-bottom: 1px solid #d1d5db !important;
    font-size: 0.9375rem;
    color: #111827;
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
  }
  /* Extra spacing for Qty, Unit Cost, Total columns */
  #aq-tbody-parts tr td:nth-child(3),
  #aq-tbody-parts tr td:nth-child(4),
  #aq-tbody-parts tr td:nth-child(5),
  #aq-tbody-cons tr td:nth-child(3),
  #aq-tbody-cons tr td:nth-child(4),
  #aq-tbody-cons tr td:nth-child(5) {
    padding-left: 0.95rem !important;
    padding-right: 0.95rem !important;
    min-width: 6.5rem;
  }
  /* Make parts/consumables editable columns visibly boxed (not only on focus) */
  #aq-tbody-parts .pp-name,
  #aq-tbody-parts .pp-q,
  #aq-tbody-parts .pp-u,
  #aq-tbody-cons .pp-name,
  #aq-tbody-cons .pp-q,
  #aq-tbody-cons .pp-u,
  #aq-tbody-parts .pp-sel {
    border: 1px solid #cfd8e3 !important;
    background: #ffffff !important;
    border-radius: 0.375rem;
    padding: 0.35rem 0.55rem !important;
  }
  #aq-tbody-parts .pp-q,
  #aq-tbody-parts .pp-u,
  #aq-tbody-cons .pp-q,
  #aq-tbody-cons .pp-u {
    text-align: center;
    min-height: 2rem;
  }
  /* Match Labour inputs to formal boxed style */
  #aq-tbody-labour .lab-desc,
  #aq-tbody-labour .lab-h,
  #aq-tbody-labour .lab-r {
    border: 1px solid #cfd8e3 !important;
    background: #ffffff !important;
    border-radius: 0.375rem;
    padding: 0.35rem 0.55rem !important;
    min-height: 2rem;
    color: #111827 !important;
  }
  #aq-tbody-labour .lab-h,
  #aq-tbody-labour .lab-r {
    text-align: center;
  }
  .aq-quote-page-shell #aq-labour-card thead tr,
  .aq-quote-page-shell #aq-app > .bg-white.overflow-hidden thead tr {
    background: #eaf1ff !important;
    border-bottom: 1px solid #dbe8ff !important;
  }
  .aq-quote-page-shell #aq-labour-card thead th,
  .aq-quote-page-shell #aq-app > .bg-white.overflow-hidden thead th {
    color: #172554 !important;
    font-weight: 700 !important;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    font-size: 11px !important;
  }
  .aq-quote-page-shell #aq-labour-card .overflow-x-auto,
  .aq-quote-page-shell #aq-app > .bg-white.overflow-hidden .overflow-x-auto {
    max-height: min(52vh, 440px);
    overflow: auto;
    -webkit-overflow-scrolling: touch;
  }
  .aq-quote-page-shell #aq-labour-card thead th,
  .aq-quote-page-shell #aq-app > .bg-white.overflow-hidden thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    box-shadow: 0 1px 0 #dbe8ff;
  }
  .aq-quote-page-shell .aq-s1-btn--orange-outline {
    border-color: #ea580c;
    color: #c2410c;
    background: #fff;
    box-shadow: 0 0 0 1px rgba(234, 88, 12, 0.15);
  }
  .aq-quote-page-shell .aq-s1-btn--orange-outline:hover {
    background: #fff7ed;
    border-color: #c2410c;
  }
  .aq-quote-page-shell .aq-s1-btn--danger {
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.35);
  }

  /* —— Page layout: quotations.php blueprint (enhanced) —— */
  .erp-content:has(.aq-quote-page-shell):not(:has(.crm-inv-page)) {
    background: var(--gray-50, #fafafa);
    padding: var(--space-6, 1.5rem);
  }
  .aq-quote-page-shell.crm-inv-page {
    background: transparent;
    min-height: calc(100vh - var(--header-height, 64px));
    padding-bottom: 0;
  }
  .aq-quote-page-shell:not(.crm-inv-page) {
    background: var(--gray-50, #fafafa);
    min-height: calc(100vh - var(--header-height, 64px));
    padding-bottom: 0;
  }
  .aq-quote-page-inner {
    max-width: 88rem;
    margin-left: auto;
    margin-right: auto;
    padding: 0.75rem 1.25rem 0;
  }
  .aq-manage-layout {
    width: 100%;
    margin-top: 1rem;
  }
  .crm-inv-layout.aq-manage-layout--split {
    display: grid;
  }
  .crm-inv-layout.aq-manage-layout--split .aq-crm-manage-panel {
    margin-top: 0;
  }
  .crm-inv-sidebar .aq-manage-sidebar.aq-manage-under-cust {
    width: 100%;
    max-width: none;
    margin-top: 0;
    flex-shrink: 0;
  }
  .aq-crm-sidebar-tabs-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    min-width: 0;
  }
  .aq-crm-sidebar-tabs {
    display: flex;
    gap: 0;
    padding: 4px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    position: sticky;
    top: 0;
    z-index: 30;
  }
  .aq-crm-sidebar-tab {
    flex: 1;
    min-height: 40px;
    padding: 8px 12px;
    border: none;
    border-radius: 8px;
    background: transparent;
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
    font-family: inherit;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
  }
  .aq-crm-sidebar-tab:hover {
    background: #f8fafc;
    color: #334155;
  }
  .aq-crm-sidebar-tab.is-active {
    background: #eff6ff;
    color: #1d4ed8;
    box-shadow: 0 0 0 1px #bfdbfe;
  }
  .aq-crm-sidebar-tab i { margin-right: 6px; font-size: 12px; }
  .aq-crm-sidebar-panel { display: none; min-width: 0; }
  .aq-crm-sidebar-panel.is-active { display: block; }
  .aq-crm-sidebar-panel .aq-manage-sidebar {
    border-radius: 12px;
  }
  .aq-live-doc-host {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }
  /* Manage Quotation sidebar — trade-lane card UI (preview untouched) */
  .aq-manage-sidebar {
    width: 400px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 0;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    overflow: hidden;
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  }
  .aq-manage-sidebar.aq-crm-manage-panel {
    width: 100%;
    max-width: none;
  }
  .aq-manage-sidebar .aq-bolt-section {
    margin: 0;
    padding: 0;
    border: 1px solid #d1e9ff;
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 86, 179, 0.06);
  }
  .aq-manage-sidebar .aq-bolt-section--last {
    border-bottom: 1px solid #d1e9ff;
  }
  /* Manage Quotation — ordered tab workflow (sidebar only) */
  .aq-manage-sidebar .aq-mq-next-banner {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 0;
    padding: 12px 16px;
    border-radius: 0;
    background: #eef7ff;
    border: none;
    border-bottom: 1px solid #d1e9ff;
    box-shadow: none;
  }
  .aq-manage-sidebar .aq-mq-next-banner i {
    color: #2563eb;
    margin-top: 2px;
    flex-shrink: 0;
  }
  .aq-manage-sidebar .aq-mq-next-banner.is-rejected {
    background: #fef2f2;
    border-color: #fecaca;
  }
  .aq-manage-sidebar .aq-mq-next-banner.is-rejected i {
    color: #dc2626;
  }
  .aq-manage-sidebar .aq-mq-next-text {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: #1e3a8a;
    font-weight: 500;
  }
  .aq-manage-sidebar .aq-mq-next-banner.is-rejected .aq-mq-next-text {
    color: #991b1b;
  }
  .aq-manage-sidebar .aq-mq-next-text strong {
    font-weight: 700;
  }
  .aq-manage-sidebar .aq-mq-tabs-card {
    display: flex;
    flex-direction: column;
    min-height: 0;
    flex: 1;
    background: #fff;
    border: none;
    border-radius: 0;
    overflow: hidden;
    box-shadow: none;
  }
  .aq-manage-sidebar .aq-mq-tabs-wrap {
    display: flex;
    align-items: stretch;
    gap: 0;
    padding: 0 10px;
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
  }
  .aq-manage-sidebar .aq-mq-tab {
    flex: 1 1 0;
    min-width: 0;
    margin: 0;
    padding: 12px 6px 10px;
    border: none;
    border-bottom: 3px solid transparent;
    border-radius: 0;
    background: transparent;
    font-size: 0.75rem;
    font-weight: 600;
    color: #94a3b8;
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
  }
  .aq-manage-sidebar .aq-mq-tab:hover {
    color: #475569;
  }
  .aq-manage-sidebar .aq-mq-tab.is-active {
    color: #0f172a;
    border-bottom-color: #0ea5e9;
  }
  .aq-manage-sidebar .aq-mq-tab.is-done:not(.is-active) {
    color: #475569;
  }
  .aq-manage-sidebar .aq-mq-tab.is-done:not(.is-active)::after {
    content: ' ✓';
    font-size: 0.6875rem;
    color: #16a34a;
  }
  .aq-manage-sidebar .aq-mq-tab-panels {
    flex: 1;
    min-height: 0;
  }
  .aq-manage-sidebar .aq-mq-tab-panel {
    display: none;
    padding: 14px;
    box-sizing: border-box;
  }
  .aq-manage-sidebar .aq-mq-tab-panel.is-active {
    display: block;
  }
  .aq-manage-sidebar .aq-mq-approval-switch {
    display: flex;
    gap: 8px;
    margin: 0 0 12px;
    padding: 4px;
    background: #f4f6f8;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
  }
  .aq-manage-sidebar .aq-mq-approval-opt {
    flex: 1 1 0;
    min-width: 0;
    padding: 8px 10px;
    border: 1px solid transparent;
    border-radius: 6px;
    background: transparent;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
  }
  .aq-manage-sidebar .aq-mq-approval-opt.is-active {
    background: #fff;
    border-color: #d1e9ff;
    color: #1e3a8a;
    box-shadow: 0 1px 2px rgba(0, 86, 179, 0.08);
  }
  .aq-manage-sidebar .aq-mq-approval-opt[data-approval-mode="declined"].is-active {
    border-color: #fecaca;
    color: #b91c1c;
  }
  .aq-manage-sidebar .aq-mq-approval-pane {
    display: none;
  }
  .aq-manage-sidebar .aq-mq-approval-pane.is-active {
    display: block;
  }
  .aq-manage-sidebar .aq-mq-finish-notice {
    margin: 0 0 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: #991b1b;
    font-weight: 500;
  }
  .aq-manage-sidebar .aq-mq-finish-danger {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid #fee2e2;
  }
  .aq-manage-sidebar .aq-mq-panel-head {
    margin: 0;
    padding: 14px 16px 12px;
    background: #f8fafc;
    border: none;
    border-bottom: 1px solid #e5e7eb;
    border-radius: 0;
    box-shadow: none;
    text-align: center;
  }
  .aq-manage-sidebar .aq-mq-panel-title {
    margin: 0;
    padding: 0;
    font-size: 1.0625rem;
    font-weight: 700;
    letter-spacing: -0.02em;
    color: #0f172a;
    text-transform: none;
    line-height: 1.3;
    text-align: center;
  }
  .aq-manage-sidebar .aq-mq-panel-head .aq-manage-sidebar-flash {
    margin-top: 10px;
    border-radius: 8px;
    border: 1px solid #bbf7d0;
  }
  .aq-manage-sidebar .aq-bolt-head-meta {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0;
    margin: 0;
    font-size: 0.8125rem;
    color: #64748b;
    border: none;
    background: transparent;
  }
  .aq-manage-sidebar .aq-bolt-ref {
    font-weight: 700;
    color: #0056b3;
    font-size: 0.875rem;
  }
  .aq-manage-sidebar .aq-section1__badge {
    font-size: 0.6875rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    letter-spacing: 0.03em;
  }
  .aq-manage-sidebar .aq-section1__badge--draft {
    background: #e8f0fe;
    color: #1e4a8c;
  }
  .aq-manage-sidebar .aq-section1__badge--pending {
    background: #fff7ed;
    color: #c2410c;
  }
  .aq-manage-sidebar .aq-section1__badge--approved {
    background: #e6f7ed;
    color: #15803d;
  }
  .aq-manage-sidebar .aq-section1__badge--rejected {
    background: #fef2f2;
    color: #b91c1c;
  }
  .aq-manage-sidebar .aq-section1__badge--sent {
    background: #e8f0fe;
    color: #1d4ed8;
  }
  .aq-manage-sidebar-flash {
    display: block;
    margin: 0;
    padding: 10px 16px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #15803d;
    background: #ecfdf5;
    border-top: 1px solid #bbf7d0;
    border-radius: 0;
  }
  .aq-manage-sidebar .aq-mq-card-body {
    padding: 12px 14px 14px;
    box-sizing: border-box;
  }
  .aq-manage-sidebar .aq-bolt-section-label {
    margin: 0;
    padding: 10px 14px;
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #1e4a8c;
    background: #eef7ff;
    border-bottom: 1px solid #d1e9ff;
  }
  .aq-manage-sidebar .aq-bolt-section--paper .aq-mq-card-body {
    padding-top: 12px;
  }
  .aq-manage-sidebar .aq-bolt-section--paper .aq-paper-signoff-panel,
  .aq-manage-sidebar .aq-bolt-section--paper .aq-paper-rejection-panel {
    width: 100%;
    box-sizing: border-box;
  }
  .aq-manage-sidebar .aq-bolt-section--doc-tools {
    background: #fff;
  }
  .aq-manage-sidebar .aq-bolt-section--doc-tools .aq-bolt-section-label {
    color: #0056b3;
  }
  .aq-manage-sidebar #aq-workflow-panel {
    padding: 0;
  }
  .aq-manage-sidebar .aq-workflow-next {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 0 0 12px;
    padding: 10px 12px;
    border-radius: 8px;
    background: #eef7ff;
    border: 1px solid #d1e9ff;
    box-shadow: none;
  }
  .aq-manage-sidebar .aq-workflow-next i {
    color: #2563eb;
    margin-top: 2px;
    flex-shrink: 0;
  }
  .aq-manage-sidebar .aq-workflow-next-text {
    margin: 0;
    font-size: 0.8125rem;
    line-height: 1.45;
    color: #1e3a8a;
    font-weight: 500;
  }
  .aq-manage-sidebar .aq-workflow-next-text strong {
    font-weight: 700;
    color: #1e3a8a;
  }
  .aq-manage-sidebar .aq-workflow-next .aq-workflow-next-hint {
    display: none;
  }
  .aq-manage-sidebar .aq-workflow-steps {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 0;
    background: transparent;
    border: none;
    border-radius: 0;
  }
  .aq-manage-sidebar .aq-workflow-step {
    flex: 1 1 calc(25% - 8px);
    min-width: 72px;
    padding: 8px 10px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    background: #fff;
    color: #2563eb;
    border: 1px solid #d1e9ff;
    box-shadow: 0 1px 2px rgba(0, 86, 179, 0.06);
  }
  .aq-manage-sidebar .aq-workflow-step.is-done,
  .aq-manage-sidebar .aq-workflow-step.is-current {
    background: #eef7ff;
    color: #0056b3;
    border-color: #93c5fd;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.12);
  }
  .aq-manage-sidebar .aq-workflow-step-num {
    display: none;
  }
  /* Manager paper sign-off & rejection — Manage Quotation sidebar only */
  .aq-manage-sidebar .aq-paper-signoff-panel,
  .aq-manage-sidebar .aq-paper-rejection-panel {
    margin: 0;
    padding: 14px 14px 12px;
    border-radius: 8px;
    background: #fff;
    box-shadow: none;
  }
  .aq-manage-sidebar .aq-paper-signoff-panel {
    border: 1px solid #f97316;
  }
  .aq-manage-sidebar .aq-paper-rejection-panel {
    border: 1px solid #ef4444;
    background: #fff;
  }
  .aq-manage-sidebar .aq-paper-signoff-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 10px;
    flex-wrap: wrap;
  }
  .aq-manage-sidebar .aq-paper-signoff-head h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    flex: 1 1 auto;
    min-width: 0;
    font-size: 0.875rem;
    font-weight: 700;
    color: #1e3a8a;
    line-height: 1.3;
  }
  .aq-manage-sidebar .aq-paper-signoff-head h3 i {
    color: #2563eb;
    font-size: 0.875rem;
    flex-shrink: 0;
  }
  .aq-manage-sidebar .aq-paper-signoff-hint {
    display: block;
    margin: 0 0 12px;
    font-size: 0.75rem;
    color: #64748b;
    line-height: 1.5;
  }
  .aq-manage-sidebar .aq-paper-signoff-hint strong {
    color: #475569;
    font-weight: 700;
  }
  .aq-manage-sidebar .aq-paper-signoff-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 0;
    border: none;
    background: transparent;
    border-radius: 0;
  }
  .aq-manage-sidebar .aq-paper-field--full {
    grid-column: 1 / -1;
  }
  .aq-manage-sidebar .aq-paper-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }
  .aq-manage-sidebar .aq-paper-field span {
    font-size: 0.625rem;
    font-weight: 700;
    color: #9ca3af;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }
  .aq-manage-sidebar .aq-paper-field input,
  .aq-manage-sidebar .aq-paper-field textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 0.875rem;
    background: #fff;
    color: #1e293b;
    font-family: inherit;
  }
  .aq-manage-sidebar .aq-paper-field textarea {
    min-height: 72px;
    resize: vertical;
    line-height: 1.45;
  }
  .aq-manage-sidebar .aq-paper-signoff-panel .aq-paper-field input:focus,
  .aq-manage-sidebar .aq-paper-signoff-panel .aq-paper-field textarea:focus {
    border-color: #2563eb;
    outline: none;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
  }
  .aq-manage-sidebar .aq-paper-rejection-panel .aq-paper-field input:focus,
  .aq-manage-sidebar .aq-paper-rejection-panel .aq-paper-field textarea:focus {
    border-color: #ef4444;
    outline: none;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.12);
  }
  .aq-manage-sidebar .aq-paper-status-badge {
    flex-shrink: 0;
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 5px 10px;
    border-radius: 999px;
    white-space: nowrap;
    line-height: 1.2;
  }
  .aq-manage-sidebar #aq-paper-status-badge.aq-paper-status-badge--pending {
    background: #fff7ed;
    color: #c2410c;
  }
  .aq-manage-sidebar #aq-paper-status-badge.aq-paper-status-badge--signed {
    background: #e6f4ea;
    color: #137333;
  }
  .aq-manage-sidebar #aq-rejection-status-badge.aq-paper-status-badge--pending {
    background: #e8f0fe;
    color: #1e40af;
  }
  .aq-manage-sidebar #aq-rejection-status-badge.aq-paper-status-badge--rejected {
    background: #fce8e6;
    color: #c5221f;
  }
  .aq-manage-sidebar .aq-mgr-review-panel {
    margin-bottom: 14px;
    padding-bottom: 14px;
    border-bottom: 1px dashed #d1d5db;
  }
  .aq-manage-sidebar #aq-mgr-review-status-badge.aq-paper-status-badge--sent {
    background: #e0f2fe;
    color: #0369a1;
  }
  .aq-manage-sidebar #aq-mgr-review-status-badge.aq-paper-status-badge--pending {
    background: #fff7ed;
    color: #c2410c;
  }
  .aq-manage-sidebar .aq-mgr-comments-panel {
    margin-top: 0;
    padding: 14px 16px;
    border-top: 1px solid #e5e7eb;
    background: #fffbeb;
  }
  .aq-manage-sidebar .aq-mgr-comments-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
  }
  .aq-manage-sidebar .aq-mgr-comment-item {
    padding: 10px 12px;
    border: 1px solid #fde68a;
    border-radius: 8px;
    background: #fff;
  }
  .aq-manage-sidebar .aq-mgr-comment-meta {
    margin: 0 0 6px;
    font-size: 11px;
    color: #92400e;
  }
  .aq-manage-sidebar .aq-mgr-comment-meta strong { color: #78350f; }
  .aq-manage-sidebar .aq-mgr-comment-body {
    margin: 0;
    font-size: 13px;
    color: #334155;
    line-height: 1.45;
  }
  .aq-manage-sidebar .aq-mgr-review-actions {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    margin-top: 12px;
  }
  .aq-manage-sidebar .aq-mq-paper-actions,
  .aq-manage-sidebar .aq-mq-signoff-actions {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px dashed #d1d5db;
  }
  .aq-manage-sidebar .aq-mq-signoff-hint {
    font-size: 0.6875rem;
    font-weight: 500;
    color: #9ca3af;
    line-height: 1.4;
    text-align: center;
  }
  .aq-manage-sidebar .aq-paper-signoff-panel #aq-save-paper-signoff,
  .aq-manage-sidebar .aq-paper-signoff-panel .aq-mq-btn-primary {
    justify-content: center;
    width: 100%;
    min-height: 2.5rem;
    background: #2563eb !important;
    color: #fff !important;
    border: none !important;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
  }
  .aq-manage-sidebar .aq-paper-signoff-panel #aq-save-paper-signoff:hover,
  .aq-manage-sidebar .aq-paper-signoff-panel .aq-mq-btn-primary:hover {
    background: #1d4ed8 !important;
    border: none !important;
  }
  .aq-manage-sidebar .aq-paper-signoff-panel #aq-save-paper-signoff i,
  .aq-manage-sidebar .aq-paper-signoff-panel .aq-mq-btn-primary i {
    color: #fff !important;
  }
  .aq-manage-sidebar .aq-paper-rejection-panel #aq-save-paper-rejection {
    justify-content: center;
    width: 100%;
    min-height: 2.5rem;
    background: #fff !important;
    color: #dc2626 !important;
    border: 1px solid #ef4444 !important;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: none;
  }
  .aq-manage-sidebar .aq-paper-rejection-panel #aq-save-paper-rejection:hover {
    background: #fef2f2 !important;
    border-color: #dc2626 !important;
    color: #b91c1c !important;
  }
  .aq-manage-sidebar .aq-paper-rejection-panel #aq-save-paper-rejection i {
    color: #6b7280 !important;
  }
  .aq-manage-sidebar .aq-paper-signoff-panel .aq-s1-btn:hover,
  .aq-manage-sidebar .aq-paper-rejection-panel .aq-s1-btn:hover {
    transform: none;
  }
  .aq-manage-sidebar .aq-quote-actions-row[aria-label="Document actions"] {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
    padding: 0;
    background: transparent;
    border: none;
  }
  .aq-manage-sidebar .aq-manage-workflow-row::before {
    display: none;
  }
  /* Next Steps — Manage Quotation sidebar only */
  .aq-manage-sidebar #aq-mq-tab-finish .aq-mq-next-steps-well {
    padding: 12px;
    background: #f4f6f8;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-sizing: border-box;
  }
  .aq-manage-sidebar #aq-mq-tab-finish .aq-manage-workflow-row {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
  }
  .aq-manage-sidebar #aq-mq-tab-finish .aq-manage-workflow-row .aq-s1-btn,
  .aq-manage-sidebar #aq-mq-tab-finish .aq-manage-workflow-row a.aq-s1-btn {
    justify-content: flex-start;
    width: 100%;
    min-height: 2.65rem;
    padding: 10px 14px;
    border-radius: 8px;
    background: #fff !important;
    box-shadow: none;
    font-size: 0.8125rem;
    font-weight: 600;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-add-po {
    border: 1px solid #cbd5e1 !important;
    color: #1e3a8a !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-add-po i {
    color: #475569 !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-add-po:hover:not(:disabled) {
    background: #f8fafc !important;
    border-color: #94a3b8 !important;
    color: #1e3a8a !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-send-client,
  .aq-manage-sidebar #aq-mq-tab-finish #aq-create-invoice {
    border: 1px solid #86efac !important;
    color: #16a34a !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-send-client i,
  .aq-manage-sidebar #aq-mq-tab-finish #aq-create-invoice i {
    color: #16a34a !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-send-client:hover:not(:disabled),
  .aq-manage-sidebar #aq-mq-tab-finish #aq-create-invoice:hover:not(:disabled) {
    background: #f0fdf4 !important;
    border-color: #4ade80 !important;
    color: #15803d !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-delete-quotation {
    border: 1px solid #fecaca !important;
    background: #fef2f2 !important;
    color: #dc2626 !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-delete-quotation i {
    color: #ef4444 !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish #aq-delete-quotation:hover:not(:disabled) {
    background: #fee2e2 !important;
    border-color: #fca5a5 !important;
    color: #b91c1c !important;
  }
  .aq-manage-sidebar #aq-mq-tab-finish .aq-s1-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }
  .aq-manage-sidebar #aq-mq-tab-finish .aq-s1-btn:disabled:hover {
    background: #fff !important;
    border-color: #e5e7eb !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn,
  .aq-manage-sidebar .aq-doc-tools-grid a.aq-s1-btn {
    justify-content: center !important;
    background: #fff !important;
  }
  /* Document Tools — button colors (Manage Quotation sidebar only) */
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--blue,
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--blue[data-doc-tool="preview"] {
    border-color: #1e40af !important;
    color: #1e40af !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--blue i {
    color: #1e40af !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--blue:hover {
    background: #eff6ff !important;
    border-color: #1d4ed8 !important;
    color: #1e3a8a !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--blue:hover i {
    color: #1d4ed8 !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--blue.is-active,
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--blue[data-doc-tool="preview"].is-active {
    background: #eef2ff !important;
    border-color: #1e40af !important;
    color: #1e40af !important;
    box-shadow: 0 0 0 2px rgba(30, 64, 175, 0.14) !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--gray,
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--outline-slate {
    border-color: #9ca3af !important;
    color: #4b5563 !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--gray i,
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--outline-slate i {
    color: #6b7280 !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--gray:hover,
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--outline-slate:hover {
    background: #f9fafb !important;
    border-color: #6b7280 !important;
    color: #374151 !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--gray:hover i,
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--outline-slate:hover i {
    color: #4b5563 !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--orange-outline,
  .aq-manage-sidebar .aq-doc-tools-grid a.aq-s1-btn--orange-outline {
    border-color: #ea580c !important;
    color: #ea580c !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--orange-outline i {
    color: #ea580c !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn--orange-outline:hover,
  .aq-manage-sidebar .aq-doc-tools-grid a.aq-s1-btn--orange-outline:hover {
    background: #fff7ed !important;
    border-color: #c2410c !important;
    color: #c2410c !important;
  }
  .aq-manage-sidebar .aq-s1-btn,
  .aq-manage-sidebar a.aq-s1-btn {
    justify-content: flex-start;
    width: 100%;
    min-height: 2.5rem;
    padding: 8px 12px;
    border: 1px solid #d1e9ff;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font-size: 0.8125rem;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(0, 86, 179, 0.04);
    transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
  }
  .aq-manage-sidebar .aq-s1-btn:hover,
  .aq-manage-sidebar a.aq-s1-btn:hover {
    border-color: #93c5fd;
    background: #f8fbff;
  }
  .aq-manage-sidebar .aq-s1-btn i {
    color: #64748b;
    width: 1rem;
    text-align: center;
  }
  .aq-manage-sidebar .aq-manage-workflow-row .aq-s1-btn--orange-outline {
    border-color: #ea580c !important;
    color: #ea580c !important;
  }
  .aq-manage-sidebar .aq-manage-workflow-row .aq-s1-btn--gray,
  .aq-manage-sidebar .aq-manage-workflow-row .aq-s1-btn--outline-slate {
    border-color: #d1e9ff !important;
    color: #475569 !important;
  }
  @media (max-width: 420px) {
    .aq-manage-sidebar .aq-workflow-step {
      flex: 1 1 calc(50% - 8px);
    }
  }
  .aq-manage-sidebar .aq-s1-btn--green-outline {
    border-color: #86efac !important;
    color: #15803d !important;
  }
  .aq-manage-sidebar .aq-s1-btn--green-outline:hover:not(:disabled) {
    background: #f0fdf4 !important;
  }
  .aq-manage-sidebar .aq-s1-btn--danger {
    border-color: #fecaca !important;
    background: #fef2f2 !important;
    color: #dc2626 !important;
  }
  .aq-manage-sidebar .aq-s1-btn--danger:hover {
    background: #fee2e2 !important;
    border-color: #fca5a5 !important;
  }
  .aq-manage-sidebar .aq-s1-btn--danger i {
    color: #ef4444;
  }
  .aq-manage-sidebar .aq-s1-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
  }
  .aq-manage-preview-main,
  .crm-inv-main.aq-manage-preview-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 7rem);
    gap: 1rem;
  }
  .aq-manage-preview-main.is-highlight #aq-live-doc-mount {
    box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.35);
  }
  .aq-manage-preview-top {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 0 0.5rem 0.85rem;
    gap: 0.25rem;
    min-height: 3.85rem;
    box-sizing: border-box;
  }
  .aq-manage-preview-heading {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -0.02em;
  }
  .aq-manage-preview-meta {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #64748b;
  }
  .aq-manage-preview-body {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }
  .aq-manage-preview-scroll-wrap {
    flex: 1;
    overflow-y: auto;
    border-radius: 12px;
    background: rgba(226, 232, 240, 0.6);
    border: 2px solid #cbd5e1;
    padding: 1.25rem;
    min-height: 360px;
  }
  #aq-live-doc-mount {
    width: 100%;
    box-sizing: border-box;
  }
  .aq-manage-preview-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding-top: 0.85rem;
    margin-top: 0.85rem;
    border-top: 2px solid #cbd5e1;
  }
  .aq-manage-preview-actions .aq-s1-btn {
    min-width: 8.5rem;
    min-height: 2.65rem;
    border-width: 2px;
    border-radius: 0.5rem;
    font-weight: 600;
  }
  .aq-manage-sidebar .aq-doc-tools-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    gap: 8px !important;
    width: 100%;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-quote-actions-slot {
    display: none !important;
  }
  .aq-manage-sidebar .aq-doc-tools-grid .aq-s1-btn,
  .aq-manage-sidebar .aq-doc-tools-grid a.aq-s1-btn {
    width: 100% !important;
    justify-content: center !important;
    gap: 0.35rem !important;
  }
  #aq-live-doc-mount .aq-print-inner,
  #aq-live-doc-mount #aq-print-inner {
    margin-left: auto;
    margin-right: auto;
    max-width: 780px;
    background: #fff;
    box-shadow: 0 10px 15px -3px rgba(15, 23, 42, 0.1);
    border-radius: 0.375rem;
    border: 2px solid #cbd5e1;
  }
  #aq-print-inner .aq-doc-block {
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    margin-bottom: 3mm;
    border-collapse: collapse;
    width: 100%;
    table-layout: fixed;
    font-size: 8pt;
  }
  #aq-print-inner .aq-doc-info-section {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: none;
    margin-bottom: 2mm;
  }
  #aq-print-inner .aq-doc-info-slot {
    width: 46%;
    vertical-align: top;
    padding: 0;
    border: none;
  }
  #aq-print-inner .aq-doc-info-gap {
    width: 8%;
    padding: 0;
    border: none;
    font-size: 0;
    line-height: 0;
  }
  #aq-print-inner .aq-doc-info-slot-fit {
    display: inline-block;
    width: 100%;
    vertical-align: top;
  }
  #aq-print-inner .aq-doc-info-box {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    background: #fff;
  }
  #aq-print-inner .aq-doc-info-box:not(.aq-doc-info-box--vehicle) {
    table-layout: auto;
  }
  #aq-print-inner .aq-doc-info-inner {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
  }
  #aq-print-inner .aq-doc-info-hd {
    background: <?php echo $aq_doc_hdr_bg; ?>;
    color: #000;
    font-weight: bold;
    text-align: center;
    padding: 3px 5px;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    font-size: 7.5pt;
  }
  #aq-print-inner .aq-doc-info-lbl {
    font-weight: bold;
    color: #000;
    white-space: nowrap;
    text-align: left;
    padding: 2px 6px 2px 8px;
    vertical-align: top;
    font-size: 7.5pt;
    line-height: 1.2;
    width: 1%;
  }
  #aq-print-inner .aq-doc-info-val {
    color: #444;
    font-weight: normal;
    text-align: left;
    padding: 2px 8px 2px 0;
    vertical-align: top;
    font-size: 7.5pt;
    line-height: 1.2;
    width: auto;
  }
  #aq-print-inner .aq-doc-info-box--vehicle .aq-doc-info-lbl,
  #aq-print-inner .aq-doc-info-box--vehicle .aq-doc-info-val {
    padding-top: 1px;
    padding-bottom: 1px;
    line-height: 1.15;
  }
  #aq-print-inner .aq-doc-block-hd {
    background: <?php echo $aq_doc_hdr_bg; ?>;
    color: #000;
    font-weight: bold;
    text-align: center;
    padding: 5px 6px;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    font-size: 8.5pt;
    letter-spacing: 0.02em;
  }
  #aq-print-inner .aq-doc-data-table {
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    border-spacing: 0;
    table-layout: fixed;
    box-sizing: border-box;
    margin-bottom: 0;
    font-size: 7.5pt;
  }
  #aq-print-inner .aq-doc-data-table th,
  #aq-print-inner .aq-doc-data-table td {
    box-sizing: border-box;
  }
  #aq-print-inner .aq-doc-data-table.aq-labour-table {
    margin-bottom: 1mm;
    margin-top: 0;
  }
  #aq-print-inner .aq-doc-data-table.aq-doc-parts-section {
    margin-bottom: 2mm;
    margin-top: 0;
  }
  #aq-print-inner .aq-doc-data-table th,
  #aq-print-inner .aq-doc-data-table td {
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    padding: 3px 5px;
    vertical-align: middle;
    background: <?php echo $aq_doc_body_bg; ?>;
  }
  #aq-print-inner .aq-doc-data-table tr.aq-doc-last-body-row td {
    border-bottom: none !important;
  }
  #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td {
    padding: 1px 3px !important;
    line-height: 1.1 !important;
    font-size: 7pt !important;
    height: auto;
    vertical-align: middle;
  }
  #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-spacer {
    background: #fff !important;
    color: #000;
    border: 1px solid <?php echo $aq_doc_bd_col; ?> !important;
    border-top: 1px solid <?php echo $aq_doc_bd_col; ?> !important;
    font-weight: normal;
    padding: 0 3px !important;
    min-height: 0;
    font-size: 0;
    line-height: 0;
  }
  #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-metric {
    background: <?php echo $aq_doc_body_bg; ?> !important;
    color: #000;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    border-top: 1px solid <?php echo $aq_doc_bd_col; ?>;
    font-weight: normal;
    text-align: center;
  }
  #aq-print-inner .aq-doc-sum-table {
    width: 32%;
    margin-left: auto;
    margin-top: 0;
    border-collapse: collapse;
    font-size: 7pt;
  }
  #aq-print-inner .aq-doc-sum-table td {
    padding: 1px 4px;
    line-height: 1.1;
    vertical-align: middle;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
  }
  #aq-print-inner .aq-doc-data-table tr.aq-parts-thick-divider td {
    height: 5px;
    padding: 0 !important;
    background: <?php echo $aq_doc_bd_col; ?> !important;
    border: 1px solid <?php echo $aq_doc_bd_col; ?> !important;
    border-top: 1px solid <?php echo $aq_doc_bd_col; ?> !important;
    border-bottom: 3px solid <?php echo $aq_doc_bd_col; ?> !important;
    font-size: 0;
    line-height: 0;
  }
  #aq-print-inner .aq-doc-data-table tr.aq-doc-total-gap td {
    height: 4px;
    padding: 0 !important;
    border: none !important;
    background: #fff !important;
    font-size: 0;
    line-height: 0;
  }
  #aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-hdr {
    background: <?php echo $aq_doc_hdr_bg; ?>;
    color: #000;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    border-top: 1px solid <?php echo $aq_doc_bd_col; ?>;
    font-weight: bold;
    text-align: center;
  }
  #aq-print-inner .aq-doc-data-table thead th {
    background: <?php echo $aq_doc_hdr_bg; ?> !important;
    color: #000;
    font-weight: bold;
    text-align: center;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
  }
  #aq-print-inner .aq-doc-data-table.aq-labour-table,
  #aq-print-inner .aq-doc-data-table.aq-doc-parts-section {
    border-collapse: collapse;
    border-spacing: 0;
  }
  #aq-print-inner .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-sub {
    background: #d9d9d9 !important;
    color: #000;
    font-weight: bold;
    text-align: center;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
  }
  #aq-print-inner .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-side,
  #aq-print-inner .aq-doc-data-table.aq-doc-parts-section thead th {
    vertical-align: middle;
    text-align: center;
  }
  #aq-print-inner .aq-doc-data-table.aq-labour-table thead tr.aq-labour-hdr-label th {
    border-top: 1px solid <?php echo $aq_doc_bd_col; ?>;
  }
  #aq-print-inner .aq-labour-title-cell,
  #aq-print-inner .aq-part-type-cell {
    background: #ffffff;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
    text-align: center;
    vertical-align: middle;
  }
  #aq-print-inner .aq-labour-desc-cell,
  #aq-print-inner .aq-parts-name-cell {
    background: #ffffff;
    vertical-align: top;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
  }
  #aq-print-inner .aq-labour-metric-cell {
    background: #ffffff;
    text-align: center;
    vertical-align: middle;
    border: 1px solid <?php echo $aq_doc_bd_col; ?>;
  }
  #aq-print-inner .aq-doc-parts-section {
    margin-top: 0;
    margin-bottom: 2mm;
    table-layout: fixed;
    width: 100%;
    max-width: 100%;
  }
  #aq-print-inner .aq-doc-parts-section td.aq-part-type-cell {
    white-space: nowrap;
    line-height: 1.2;
    word-break: keep-all;
  }
  #aq-print-inner .aq-doc-footer-block {
    margin-top: 3mm;
  }
  @media print {
    #aq-print-inner .aq-doc-parts-section {
      page-break-before: auto;
    }
    #aq-print-inner .aq-doc-footer-block {
      page-break-inside: avoid;
    }
  }
  #aq-print-inner .aq-doc-muted {
    color: #000;
  }
  .aq-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 11px 16px;
    margin: -1rem -1.15rem 1rem;
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    border-radius: 12px 12px 0 0;
  }
  .aq-panel-head-title {
    margin: 0;
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #475569;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .aq-panel-head-title i {
    color: #ea580c;
    font-size: 13px;
  }
  .aq-actions-zone {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
  }
  .aq-actions-zone-label {
    margin: 0 0 2px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
  }
  @media (min-width: 1024px) {
    .aq-quote-page-inner {
      padding-left: 1.75rem;
      padding-right: 1.75rem;
    }
  }
  .aq-quote-page-shell .aq-erp-card {
    background: #fff;
    border: 2px solid #cbd5e1;
    border-radius: 14px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06);
  }
  .aq-quote-page-shell #aq-app > .bg-white.rounded-xl.border,
  .aq-quote-page-shell #aq-app > .grid .aq-detail-card,
  .aq-quote-page-shell #aq-labour-card,
  .aq-quote-page-shell #aq-app > .bg-white.rounded-xl.border.overflow-hidden.mb-5 {
    background: #fff !important;
    border: 2px solid #cbd5e1 !important;
    border-radius: 14px !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06) !important;
  }
  .aq-quote-page-shell .aq-detail-card {
    border-color: #cbd5e1 !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.06) !important;
  }

  .aq-quote-edit-panel {
    margin-bottom: 1.5rem;
  }

  #aq-app {
    padding: 0;
    margin: 0;
  }
  .aq-quote-document-panel {
    margin: 0;
    background: #fff;
    border: 2px solid #cbd5e1;
    border-radius: 14px;
    box-shadow: 0 4px 16px rgba(15, 23, 42, 0.07);
    padding: 1rem 1.15rem 1.15rem;
  }
  .aq-quote-document-panel .aq-panel-head {
    margin: -1rem -1.15rem 0.85rem;
  }
  .aq-quote-doc-frame {
    display: flex;
    justify-content: center;
    padding: 0.35rem 0 0.5rem;
    width: 100%;
  }
  .aq-quote-doc-frame #aq-live-doc-mount {
    width: 100%;
    display: flex;
    justify-content: center;
  }

  .aq-quote-actions-panel {
    margin: 0;
    padding: 0;
    background: #fff;
    border: 2px solid #cbd5e1;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, 0.08);
    max-width: 100%;
    margin-left: auto;
    margin-right: auto;
    overflow: hidden;
  }
  .aq-quote-actions-panel > .aq-panel-head {
    margin: 0;
    border-radius: 0;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 16px;
  }
  .aq-quote-actions-wrap {
    display: flex;
    flex-direction: column;
    gap: 14px;
    width: 100%;
    margin-left: auto;
    margin-right: auto;
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 1rem 1.15rem 1.15rem;
  }
  .aq-quote-actions-row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 8px;
    width: 100%;
    align-items: center;
    margin: 0;
  }
  .aq-quote-actions-row[aria-label="Document actions"] {
    padding: 0;
    background: transparent;
    border: none;
  }
  .aq-quote-actions-row[aria-label="Document actions"] > .aq-s1-btn,
  .aq-quote-actions-row[aria-label="Document actions"] > a.aq-s1-btn {
    min-height: 2.65rem;
    height: auto;
    border-width: 2px;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
  }
  .aq-quote-actions-row[aria-label="Workflow actions"] {
    padding: 12px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    margin-top: 2px;
  }
  .aq-quote-actions-wrap > .aq-quote-actions-row[aria-label="Workflow actions"]::before {
    content: "Next steps";
    display: block;
    width: 100%;
    grid-column: 1 / -1;
    margin: 0 0 8px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
  }
  .aq-quote-actions-wrap > .aq-quote-actions-row[aria-label="Workflow actions"] {
    display: grid;
  }
  .aq-quote-actions-row[aria-label="Workflow actions"] > .aq-s1-btn,
  .aq-quote-actions-row[aria-label="Workflow actions"] > a.aq-s1-btn {
    min-height: 2.85rem;
    height: auto;
    font-size: 0.8125rem;
    border-radius: 10px;
    border-width: 2px;
  }
  .aq-quote-actions-row > .aq-s1-btn,
  .aq-quote-actions-row > a.aq-s1-btn {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    width: 100% !important;
    max-width: none;
    min-width: 0;
    min-height: 2.5rem;
    height: 2.5rem;
    box-sizing: border-box;
    padding: 0.35rem 0.4rem;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1.2;
    gap: 0.35rem;
    text-align: center;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex-shrink: 1;
  }
  .aq-quote-actions-row > .aq-s1-btn i,
  .aq-quote-actions-row > a.aq-s1-btn i {
    flex-shrink: 0;
  }
  .aq-quote-actions-row--solo {
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .aq-quote-actions-row--solo > .aq-s1-btn,
  .aq-quote-actions-row--solo > a.aq-s1-btn {
    width: min(14rem, 100%) !important;
    max-width: 14rem;
  }
  .aq-quote-actions-row--manager {
    display: none;
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .aq-quote-actions-row--manager.is-visible {
    display: grid;
  }
  .aq-quote-actions-slot {
    display: block;
    min-height: 0;
    visibility: hidden;
    pointer-events: none;
  }
  @media (max-width: 900px) {
    .aq-quote-actions-row {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (max-width: 640px) {
    .aq-quote-actions-row {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
  @media (max-width: 420px) {
    .aq-quote-actions-row {
      grid-template-columns: 1fr;
    }
  }

  .aq-s1-btn--outline-slate {
    border-color: #cbd5e1;
    color: #334155;
    background: #fff;
  }
  .aq-s1-btn--outline-slate:hover {
    border-color: #94a3b8;
    background: #f8fafc;
  }

  /* —— Quotation print preview: match Admin/Invoice/view_invoice.php —— */
  .aq-print-shell {
    background: rgba(15, 23, 42, 0.45);
  }
  .invoice-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
  }
  #aq-print-shell .invoice-container {
    padding: 20px 10px 60px;
  }
  .invoice-wrapper {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    font-family: Arial, sans-serif;
    font-size: 9pt;
    color: #000;
    padding: 10mm 12mm;
    box-shadow: 0 4px 32px rgba(0, 0, 0, 0.18);
    margin-bottom: 16px;
  }
  .bottom-actions {
    width: 100%;
    max-width: 900px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    align-items: center;
    padding: 0 20px 40px;
    margin: 0 auto;
  }
  .button-group {
    display: flex;
    flex-direction: row;
    gap: 12px;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    width: auto;
  }
  .button-group .aq-s1-btn {
    flex-shrink: 0;
  }
  .aq-s1-btn--green {
    background: #16a34a;
    color: #fff;
    border: none;
  }
  .aq-s1-btn--green:hover {
    background: #15803d;
  }
  .aq-s1-btn--orange {
    background: #f97316;
    color: #fff;
    border: none;
  }
  .aq-s1-btn--orange:hover {
    background: #ea580c;
  }
  .aq-doc-icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 9999px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
  }
  .aq-doc-icon-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
  }
  .aq-doc-icon-btn--green {
    background: #16a34a;
    border-color: #16a34a;
    color: #fff;
  }
  .aq-doc-icon-btn--green:hover {
    background: #15803d;
    border-color: #15803d;
    color: #fff;
  }
  .aq-doc-icon-btn--muted {
    color: #64748b;
  }
  @media print {
    body {
      margin: 0;
      padding: 0;
    }
    .invoice-wrapper {
      box-shadow: none !important;
      margin: 0 !important;
    }
  }
  @media (max-width: 768px) {
    .invoice-wrapper {
      width: 100%;
      padding: 5mm;
    }
    .button-group {
      flex-direction: column;
      width: 100%;
    }
    .button-group .aq-s1-btn {
      width: 100%;
    }
  }

</style>
<?php include __DIR__ . '/quotation_crm_shell_styles.inc.php'; ?>
<style>
  /* Customer avatar: show readable initials (e.g. CD), not the decorative wave graphic */
  #aq-crm-customer-card .crm-cust-logo svg { display: none !important; }
  #aq-crm-customer-card .crm-cust-logo-initials {
    display: flex !important;
    position: relative !important;
    inset: auto !important;
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    letter-spacing: 0.08em !important;
    line-height: 1 !important;
    text-transform: uppercase;
    color: #fff !important;
  }
</style>

<div class="crm-inv-page aq-quote-page-shell">

<?php if ($pageSuccess !== ''): ?>
    <div class="jc-flash-wrap" id="jcFlashWrap">
        <div class="jc-flash-card jc-flash-card--success">
            <div class="jc-flash-icon"><i class="fas fa-check"></i></div>
            <div class="jc-flash-title">Success</div>
            <div class="jc-flash-body"><?php echo htmlspecialchars($pageSuccess, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>
<?php elseif ($pageError !== ''): ?>
    <div class="jc-flash-wrap" id="jcFlashWrap">
        <div class="jc-flash-card jc-flash-card--error">
            <div class="jc-flash-icon"><i class="fas fa-exclamation"></i></div>
            <div class="jc-flash-title">Error</div>
            <div class="jc-flash-body"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
    </div>
<?php endif; ?>

<div class="aq-quote-page-inner">
<div id="aq-app" class="w-full max-w-7xl mx-auto no-print <?php echo $previewOnlyMode ? 'aq-preview-only' : ''; ?>">
  <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5 aq-edit-screen-only aq-hidden-source-block">
    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">Link to Job Card (required to save)</label>
    <select id="aq-job-card" class="aq-ic appearance-none cursor-pointer"></select>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 mb-5 aq-detail-cards aq-edit-screen-only aq-hidden-source-block">
    <div class="bg-white border border-gray-200 aq-detail-card md:col-span-1" style="max-width: 400px">
      <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Customer Details</h2>
      <div class="aq-detail-stack aq-cust-fields">
        <div class="aq-cust-field"><label class="block text-xs text-gray-600 mb-1">Name</label><input id="f-customer_name" class="aq-ic"/></div>
        <div class="aq-cust-field"><label class="block text-xs text-gray-600 mb-1">Address 1</label><input id="f-customer_address" class="aq-ic" autocomplete="street-address"/></div>
        <div class="aq-cust-field"><label class="block text-xs text-gray-600 mb-1">Address 2</label><input id="f-customer_address2" class="aq-ic" autocomplete="address-line2"/></div>
        <div class="aq-cust-field"><label class="block text-xs text-gray-600 mb-1">Address 3</label><input id="f-customer_address3" class="aq-ic" autocomplete="address-line3"/></div>
      </div>
      <input type="hidden" id="f-customer_email" value=""/>
      <input type="hidden" id="f-customer_phone" value=""/>
      <input type="hidden" id="f-contact_person" value=""/>
    </div>
    <div class="bg-white border border-gray-200 aq-detail-card">
      <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Vehicle Details</h2>
      <div class="aq-detail-stack aq-vehicle-fields">
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Date</label><input type="date" id="f-date" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Quote Number <span class="text-red-500">*</span></label><input id="f-quote_number" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Kilometers</label><input id="f-kilometers" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Vin No.</label><input id="f-vin_no" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Fleet No.</label><input id="f-fleet_no" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Vehicle Reg No.</label><input id="f-vehicle_reg_no" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Model</label><input id="f-model" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Job No.</label><input id="f-job_no" class="aq-ic"/></div>
        <div class="aq-veh-field"><label class="block text-xs text-gray-600 mb-1">Purchase Order</label><input id="f-purchase_order" class="aq-ic"/></div>
      </div>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 mb-5 aq-edit-screen-only aq-hidden-source-block">
    <button type="button" id="aq-toggle-rates" class="w-full flex items-center justify-between px-5 py-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-xl">
      <span class="flex items-center gap-2"><i class="fas fa-sliders-h text-orange-500"></i> Rates & Section Configuration</span>
      <i id="aq-chevron" class="fas fa-chevron-down text-gray-400 transition-transform"></i>
    </button>
    <div id="aq-rates-panel" class="hidden px-5 pb-5 border-t border-gray-100 pt-4">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <div><label class="block text-xs text-gray-600 mb-1">Normal Time Rate</label><input type="number" step="any" id="f-normal_time_rate" class="aq-ic"/></div>
        <div><label class="block text-xs text-gray-600 mb-1">Overtime Rate</label><input type="number" step="any" id="f-overtime_rate" class="aq-ic"/></div>
        <div><label class="block text-xs text-gray-600 mb-1">Public Holiday Rate</label><input type="number" step="any" id="f-public_holiday_rate" class="aq-ic"/></div>
        <div><label class="block text-xs text-gray-600 mb-1">VAT Rate (%)</label><input type="number" step="any" id="f-vat_rate" class="aq-ic"/></div>
      </div>
      <div class="mb-4">
        <p class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Description column labels</p>
        <p class="text-xs text-gray-500 mb-2">From the linked job card — each label is a header row on the labour table below.</p>
        <div id="aqHeaderLabelsDisplay" class="flex flex-col gap-1.5 text-sm text-gray-800 mb-2"></div>
        <input type="hidden" id="f-general_header_labels_json" value="">
        <input type="hidden" id="f-attend_to_service_label" value="">
        <input type="hidden" id="f-diagnostic_label" value="">
      </div>
      <div class="flex flex-wrap gap-6 mb-6">
        <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" id="f-show_normal_time" class="accent-orange-500 w-4 h-4"/> Show Normal Time</label>
        <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" id="f-show_overtime" class="accent-orange-500 w-4 h-4"/> Show Overtime</label>
        <label class="flex items-center gap-2 text-sm cursor-pointer"><input type="checkbox" id="f-show_public_holiday" class="accent-orange-500 w-4 h-4"/> Show Public Holiday</label>
      </div>
      <div class="border-t border-gray-200 pt-4">
        <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Print blank PDF layout</h3>
        <p class="text-sm font-bold text-gray-800 mb-3">Print Blank only: these row counts apply when you click <strong>Print Blank</strong>.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
          <div><label class="block text-xs text-gray-600 mb-1">Normal time rows</label><input type="number" step="1" min="2" max="40" id="f-blank_normal_lines" class="aq-ic" value="5"/></div>
          <div><label class="block text-xs text-gray-600 mb-1">Overtime rows</label><input type="number" step="1" min="2" max="40" id="f-blank_overtime_lines" class="aq-ic" value="5"/></div>
          <div><label class="block text-xs text-gray-600 mb-1">Public holiday rows</label><input type="number" step="1" min="2" max="40" id="f-blank_holiday_lines" class="aq-ic" value="5"/></div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-2 gap-3 mb-4">
          <div><label class="block text-xs text-gray-600 mb-1">Parts top rows (above divider)</label><input type="number" step="1" min="0" max="10" id="f-blank_parts_top_lines" class="aq-ic" value="2"/></div>
          <div><label class="block text-xs text-gray-600 mb-1">Parts Supply rows</label><input type="number" step="1" min="1" max="40" id="f-blank_parts_lines" class="aq-ic" value="7"/></div>
          <div><label class="block text-xs text-gray-600 mb-1">Consumables rows</label><input type="number" step="1" min="0" max="40" id="f-blank_cons_lines" class="aq-ic" value="1"/></div>
        </div>
        <h3 class="text-xs font-semibold text-gray-700 uppercase tracking-wide mb-1">Preview + Print table layout</h3>
        <p class="text-sm font-bold text-gray-800 mb-3">These options apply to both <strong>Preview</strong> and <strong>Print Blank</strong>.</p>
        <div class="flex flex-wrap gap-x-8 gap-y-2 mb-3 text-sm">
          <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-print_blank_show_labour" class="accent-orange-500 w-4 h-4" checked/> Include labour table</label>
          <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-print_blank_show_parts" class="accent-orange-500 w-4 h-4" checked/> Include parts &amp; consumables table</label>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm mb-4">
          <fieldset class="min-w-0 border border-gray-200 rounded-lg p-3">
            <legend class="text-xs font-semibold text-gray-700 px-1">Labour columns</legend>
            <div class="flex flex-wrap gap-4 mt-2">
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-blank_col_lab_hours" class="accent-orange-500 w-4 h-4" checked/> Hours</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-blank_col_lab_rate" class="accent-orange-500 w-4 h-4" checked/> Rate</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-blank_col_lab_total" class="accent-orange-500 w-4 h-4" checked/> Line total</label>
            </div>
          </fieldset>
          <fieldset class="min-w-0 border border-gray-200 rounded-lg p-3">
            <legend class="text-xs font-semibold text-gray-700 px-1">Parts columns</legend>
            <div class="flex flex-wrap gap-4 mt-2">
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-blank_col_part_qty" class="accent-orange-500 w-4 h-4" checked/> Qty</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-blank_col_part_cost" class="accent-orange-500 w-4 h-4" checked/> Unit cost</label>
              <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" id="f-blank_col_part_total" class="accent-orange-500 w-4 h-4" checked/> Line total</label>
            </div>
          </fieldset>
        </div>
      </div>
      <input type="hidden" id="f-status" value="draft"/>
      <input type="hidden" id="f-manager_review_sent_at" value=""/>
      <input type="hidden" id="f-manager_review_sent_by" value=""/>
      <input type="hidden" id="f-manager_review_viewed_at" value=""/>
      <input type="hidden" id="f-sent_back_at" value=""/>
      <input type="hidden" id="f-sent_back_by" value=""/>
      <input type="hidden" id="f-sent_back_reason" value=""/>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 mb-5 overflow-hidden aq-edit-screen-only aq-hidden-source-block" id="aq-labour-card">
    <div class="px-5 py-3 bg-black text-white flex items-center justify-between border-b border-gray-900">
      <h2 class="font-semibold text-white text-xs uppercase tracking-wider">Labour — Tasks / Activities</h2>
      <button type="button" id="aq-add-labour" class="text-sm font-semibold hover:underline text-amber-400"><i class="fas fa-plus"></i> Add Row</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b bg-gray-100 border-gray-200">
            <th class="text-center px-3 py-2 w-8 text-xs text-gray-500">Print</th>
            <th class="text-left px-3 py-2 text-xs text-gray-600">Description</th>
            <th class="text-center px-3 py-2 w-20 text-xs text-gray-600">Hours</th>
            <th class="text-center px-3 py-2 w-24 text-xs text-gray-600">Rate (<?php echo $sym; ?>)</th>
            <th class="text-right px-3 py-2 w-24 text-xs text-gray-600">Total (<?php echo $sym; ?>)</th>
            <th class="w-8"></th>
  </tr>
        </thead>
        <tbody id="aq-tbody-labour"></tbody>
        <tfoot>
          <tr class="bg-gray-50 border-t border-gray-200">
            <td colspan="2" class="px-3 py-2 text-right font-semibold text-gray-700 text-xs" style="width: 60%">Labour Total</td>
            <td class="px-3 py-2 text-right font-bold text-gray-900" id="aq-foot-labour" style="width: 20%"> <?php echo $sym; ?>0.00 </td><td style="width: 20%"></td>
          </tr>
        </tfoot>
</table>
    </div>
  </div>

  <div class="bg-white rounded-xl border border-gray-200 mb-5 overflow-hidden aq-edit-screen-only aq-hidden-source-block">
    <div class="px-5 py-3 bg-black text-white flex items-center justify-between border-b border-gray-900">
      <h2 class="font-semibold text-white text-xs uppercase tracking-wider">Parts Supply</h2>
      <button type="button" id="aq-add-parts" class="text-sm font-semibold hover:underline text-amber-400"><i class="fas fa-plus"></i> Add Row</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b bg-gray-100 border-gray-200">
            <th class="text-center px-3 py-2 w-8 text-xs text-gray-500">Print</th>
            <th class="text-left px-3 py-2 text-xs text-gray-600">Item Name</th>
            <th class="text-center px-3 py-2 w-20 text-xs text-gray-600">Qty</th>
            <th class="text-center px-3 py-2 w-28 text-xs text-gray-600">Unit Cost (<?php echo $sym; ?>)</th>
            <th class="text-right px-3 py-2 w-24 text-xs text-gray-600">Total</th><th class="w-8"></th>
    </tr>
        </thead>
        <tbody id="aq-tbody-parts"></tbody>
</table>
    </div>
    <div class="border-t border-gray-200">
      <div class="px-5 py-2 bg-black text-white flex items-center justify-between border-b border-gray-900">
        <span class="text-xs font-bold uppercase tracking-wide text-gray-100">Consumables</span>
        <button type="button" id="aq-add-cons" class="text-sm font-semibold text-amber-400 hover:underline"><i class="fas fa-plus"></i> Add</button>
      </div>
      <table class="w-full text-sm">
        <tbody id="aq-tbody-cons"></tbody>
      </table>
    </div>
    <div class="border-t bg-gray-100 border-gray-200 flex justify-end px-5 py-2">
      <span class="text-xs font-semibold text-gray-600 mr-6">Total Parts</span>
      <span class="font-bold text-gray-900 text-sm" id="aq-foot-parts"><?php echo $sym; ?>0.00</span>
    </div>
  </div>

  <div class="crm-inv-layout aq-manage-layout no-print<?php echo $editId ? ' aq-manage-layout--split' : ''; ?>">
    <aside class="crm-inv-sidebar no-print<?php echo $editId ? ' aq-crm-sidebar-tabs-wrap' : ''; ?>" aria-label="Quotation sidebar">
<?php
ob_start();
include __DIR__ . '/quotation_crm_customer_sidebar.inc.php';
$aq_crm_sidebar_html = ob_get_clean();
$aq_crm_doc_actions = '<div class="crm-cust-action-block aq-doc-actions-block">'
    . '<span class="crm-cust-action-label"><i class="fas fa-file-alt"></i> Document</span>'
    . '<button type="button" class="crm-cust-action-btn crm-cust-action-btn--preview is-active" id="aq-btn-preview" data-doc-tool="preview" role="menuitem"><i class="fas fa-eye"></i> Preview</button>'
    . '<button type="button" class="crm-cust-action-btn crm-cust-action-btn--print" id="aq-crm-action-print" data-doc-tool="print" role="menuitem"><i class="fas fa-print"></i> Print</button>'
    . '<button type="button" class="crm-cust-action-btn crm-cust-action-btn--print-blank" id="aq-btn-print-blank" data-doc-tool="print-blank" role="menuitem"><i class="fas fa-file"></i> Print blank</button>';
if ($linkedJobCardId > 0 && $editId) {
    $aq_crm_doc_actions .= '<a href="' . htmlspecialchars(($erp_admin_base_path ?? '') . 'JobCard/add_job_card.php?edit_id=' . (int) $linkedJobCardId . '&quote_id=' . (int) $editId, ENT_QUOTES, 'UTF-8') . '" class="crm-cust-action-btn crm-cust-action-btn--edit" data-doc-tool="edit" role="menuitem"><i class="fas fa-pen"></i> Edit quotation</a>';
}
$aq_crm_doc_actions .= '<button type="button" class="crm-cust-action-btn crm-cust-action-btn--quote-no" id="aq-set-quote-number" data-doc-tool="quote-no" role="menuitem"><i class="fas fa-hashtag"></i> Set quote no.</button>';
if ($editId) {
    $aq_crm_doc_actions .= '<button type="button" class="crm-cust-action-btn crm-cust-action-btn--download" id="aq-crm-action-download" role="menuitem"><i class="fas fa-download"></i> Download PDF</button>';
}
$aq_crm_doc_actions .= '</div>';
$aq_crm_sidebar_html = preg_replace(
    '/<div class="crm-cust-action-block aq-doc-actions-block">.*?<\/div>\s*/s',
    $aq_crm_doc_actions,
    $aq_crm_sidebar_html,
    1
) ?? $aq_crm_sidebar_html;
if ($editId):
?>
      <div class="aq-crm-sidebar-tabs" role="tablist" aria-label="Quotation sidebar">
        <button type="button" class="aq-crm-sidebar-tab" role="tab" id="aq-crm-tab-btn-customer" aria-selected="false" aria-controls="aq-crm-sidebar-customer" data-aq-sidebar-tab="customer"><i class="fas fa-user" aria-hidden="true"></i>Customer</button>
        <button type="button" class="aq-crm-sidebar-tab is-active" role="tab" id="aq-crm-tab-btn-workflow" aria-selected="true" aria-controls="aq-crm-sidebar-workflow" data-aq-sidebar-tab="workflow"><i class="fas fa-tasks" aria-hidden="true"></i>Workflow</button>
      </div>
      <div class="aq-crm-sidebar-panel is-active" id="aq-crm-sidebar-workflow" role="tabpanel" aria-labelledby="aq-crm-tab-btn-workflow" data-aq-sidebar-panel="workflow">
      <div class="aq-manage-sidebar aq-crm-manage-panel aq-manage-under-cust aq-section1-actions" aria-label="Manage quotation">
<?php else:
    echo $aq_crm_sidebar_html;
endif;
?>
<?php if ($editId): ?>
    <header class="aq-mq-panel-head">
      <h2 class="aq-mq-panel-title" id="aq-title-heading">Manage Quotation</h2>
      <span id="aq-status-msg" class="aq-s1-flash-hidden aq-manage-sidebar-flash" aria-live="polite"></span>
    </header>
    <?php
    $aq_mq_show_approval = ($aq_current_role !== 'manager');
    $aq_mq_next_label = '';
    if ($editId && $aq_workflow_initial && $aq_mq_show_approval) {
        $aq_mq_next_label = (string) ($aq_workflow_initial['next_label'] ?? '');
    }
    $aq_mq_finish_tab = $aq_mq_show_approval ? '2. Finish' : '1. Finish';
    $aq_mq_default_tab = $aq_mq_show_approval ? 'approval' : 'finish';
    ?>
    <?php if ($aq_mq_next_label !== ''): ?>
    <div class="aq-mq-next-banner" id="aq-workflow-next" role="status">
      <i class="fas fa-arrow-circle-right" aria-hidden="true"></i>
      <p class="aq-mq-next-text"><strong>Next:</strong> <?php echo htmlspecialchars($aq_mq_next_label, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <?php endif; ?>
    <div class="aq-mq-tabs-card" id="aq-mq-tabs-card">
      <div class="aq-mq-tabs-wrap" role="tablist" aria-label="Quotation workflow">
        <?php if ($aq_mq_show_approval): ?>
        <button type="button" class="aq-mq-tab is-active" role="tab" id="aq-mq-tab-btn-approval" aria-selected="true" aria-controls="aq-mq-tab-approval" data-mq-tab="approval">1. Approval</button>
        <?php endif; ?>
        <button type="button" class="aq-mq-tab<?php echo $aq_mq_show_approval ? '' : ' is-active'; ?>" role="tab" id="aq-mq-tab-btn-finish" aria-selected="<?php echo $aq_mq_show_approval ? 'false' : 'true'; ?>" aria-controls="aq-mq-tab-finish" data-mq-tab="finish"><?php echo htmlspecialchars($aq_mq_finish_tab, ENT_QUOTES, 'UTF-8'); ?></button>
      </div>
      <div class="aq-mq-tab-panels">
        <?php if ($aq_mq_show_approval): ?>
        <section class="aq-mq-tab-panel is-active" id="aq-mq-tab-approval" role="tabpanel" aria-labelledby="aq-mq-tab-btn-approval" data-mq-panel="approval">
          <?php if ($editId && is_admin()): ?>
          <div class="aq-mgr-review-panel" id="aq-mgr-review-panel">
            <div class="aq-paper-signoff-head">
              <h3><i class="fas fa-eye"></i> Manager preview (before paper sign-off)</h3>
              <span id="aq-mgr-review-status-badge" class="aq-paper-status-badge aq-paper-status-badge--<?php echo htmlspecialchars($aq_mgr_badge_class, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($aq_mgr_badge_text, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <p class="aq-paper-signoff-hint">Click <strong>Send to manager for review</strong> when the quotation is ready. That saves the document, adds it to the manager <strong>Sent for review</strong> inbox, and sends a notification. Until you send, managers cannot open the preview in their portal. This is <strong>not</strong> system approval — only a heads-up before paper sign-off.</p>
            <p id="aq-mgr-review-sent-meta" class="aq-mq-signoff-hint"<?php echo empty($aq_mgr_review_initial['sent']) ? ' hidden' : ''; ?>>
              <?php if (!empty($aq_mgr_review_initial['sent'])): ?>
              Last sent <?php echo htmlspecialchars($aq_mgr_review_initial['sent_at'], ENT_QUOTES, 'UTF-8'); ?>
              <?php if (!empty($aq_mgr_review_initial['sent_by'])): ?>
              by <?php echo htmlspecialchars($aq_mgr_review_initial['sent_by'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
              <?php if (!empty($aq_mgr_review_initial['viewed_at'])): ?>
              · Manager viewed <?php echo htmlspecialchars($aq_mgr_review_initial['viewed_at'], ENT_QUOTES, 'UTF-8'); ?>
              <?php endif; ?>
              <?php endif; ?>
            </p>
            <div class="aq-mgr-review-actions">
              <button type="button" id="aq-send-manager-review" class="aq-s1-btn aq-mq-btn-primary"><i class="fas fa-paper-plane"></i> Send to manager for review</button>
              <span class="aq-mq-signoff-hint">Saves the quotation, then notifies manager users in their portal.</span>
            </div>
          </div>
          <?php endif; ?>
          <div class="aq-mq-approval-switch" role="group" aria-label="Manager decision on paper">
            <button type="button" class="aq-mq-approval-opt is-active" data-approval-mode="signed">Manager signed</button>
            <button type="button" class="aq-mq-approval-opt" data-approval-mode="declined">Manager declined</button>
          </div>
          <div class="aq-mq-approval-pane is-active" id="aq-mq-pane-signed" data-approval-pane="signed">
            <div class="aq-paper-signoff-panel" id="aq-paper-signoff-panel">
              <div class="aq-paper-signoff-head">
                <h3><i class="fas fa-file-signature"></i> Manager paper sign-off</h3>
                <span id="aq-paper-status-badge" class="aq-paper-status-badge aq-paper-status-badge--pending">Not signed yet</span>
              </div>
              <p class="aq-paper-signoff-hint">Print the quotation for the manager to sign on paper. After they sign, enter the <strong>date</strong> and <strong>name</strong> from the signed copy here for system records only — the printed quotation and invoice are not updated.</p>
              <div class="aq-paper-signoff-fields">
                <label class="aq-paper-field">
                  <span>Date</span>
                  <input type="date" id="f-approved_at" value="<?php echo htmlspecialchars($aq_paper_signoff_date, ENT_QUOTES, 'UTF-8'); ?>"/>
                </label>
                <label class="aq-paper-field">
                  <span>Manager Name</span>
                  <input type="text" id="f-approved_by" value="" placeholder="e.g. J. Martinez"/>
                </label>
              </div>
              <?php if ($editId): ?>
              <div class="aq-paper-signoff-actions aq-mq-signoff-actions aq-mq-paper-actions">
                <button type="button" id="aq-save-paper-signoff" class="aq-s1-btn aq-mq-btn-primary"><i class="fas fa-save"></i> Save Sign-off</button>
                <span class="aq-mq-signoff-hint">Saves date &amp; name without leaving this page</span>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="aq-mq-approval-pane" id="aq-mq-pane-declined" data-approval-pane="declined" hidden>
            <div class="aq-paper-rejection-panel" id="aq-paper-rejection-panel">
              <div class="aq-paper-signoff-head">
                <h3><i class="fas fa-ban"></i> Manager declined on paper</h3>
                <span id="aq-rejection-status-badge" class="aq-paper-status-badge aq-paper-status-badge--pending">Not declined</span>
              </div>
              <p class="aq-paper-signoff-hint">If the manager <strong>refused to sign</strong> the printed quotation, record the <strong>date</strong>, <strong>manager name</strong>, and <strong>reason</strong>. Do not use Save sign-off for declined documents.</p>
              <div class="aq-paper-signoff-fields">
                <label class="aq-paper-field">
                  <span>Date</span>
                  <input type="date" id="f-rejected_at" value="<?php echo htmlspecialchars($aq_paper_rejection_date, ENT_QUOTES, 'UTF-8'); ?>"/>
                </label>
                <label class="aq-paper-field">
                  <span>Manager Name</span>
                  <input type="text" id="f-rejected_by" value="" placeholder="e.g. J. Martinez"/>
                </label>
                <label class="aq-paper-field aq-paper-field--full">
                  <span>Reason</span>
                  <textarea id="f-rejection_reason" rows="3" placeholder="Why the manager declined"></textarea>
                </label>
              </div>
              <?php if ($editId): ?>
              <div class="aq-paper-signoff-actions aq-mq-paper-actions">
                <button type="button" id="aq-save-paper-rejection" class="aq-s1-btn aq-s1-btn--danger-outline"><i class="fas fa-save"></i> Save Rejection</button>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>
        <section class="aq-mq-tab-panel<?php echo $aq_mq_show_approval ? '' : ' is-active'; ?>" id="aq-mq-tab-finish" role="tabpanel" aria-labelledby="aq-mq-tab-btn-finish" data-mq-panel="finish"<?php echo $aq_mq_show_approval ? ' hidden' : ''; ?>>
          <div class="aq-mq-finish-notice" id="aq-mq-finish-notice" hidden role="status">This quotation was declined on paper. Send and invoice actions are not available until you revise and re-approve.</div>
          <div class="aq-mq-next-steps-well">
            <div class="aq-quote-actions-row aq-manage-workflow-row" aria-label="Workflow actions">
              <?php if ($editId): ?>
              <button type="button" id="aq-add-po" class="aq-s1-btn aq-s1-btn--outline-slate"><i class="fas fa-file-invoice"></i> Add PO</button>
              <?php else: ?>
              <span class="aq-quote-actions-slot" aria-hidden="true"></span>
              <?php endif; ?>
              <button type="button" id="aq-send-client" class="aq-s1-btn aq-s1-btn--green-outline" disabled title="Save the quotation first"><i class="fas fa-paper-plane"></i> Send to Client</button>
              <?php if ($editId): ?>
              <button type="button" id="aq-create-invoice" class="aq-s1-btn aq-s1-btn--green-outline" disabled title="Save the quotation first"><i class="fas fa-file-invoice-dollar"></i> Create Invoice</button>
              <?php else: ?>
              <span class="aq-quote-actions-slot" aria-hidden="true"></span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($editId && is_admin()): ?>
          <div class="aq-mq-finish-danger">
            <button type="button" id="aq-delete-quotation" class="aq-s1-btn aq-s1-btn--danger"><i class="fas fa-trash"></i> Delete quotation</button>
          </div>
          <?php endif; ?>
        </section>
      </div>
    </div>
    <?php if ($aq_current_role === 'manager'): ?>
    <input type="hidden" id="f-approved_at" value=""/>
    <input type="hidden" id="f-approved_by" value=""/>
    <input type="hidden" id="f-rejected_at" value=""/>
    <input type="hidden" id="f-rejected_by" value=""/>
    <input type="hidden" id="f-rejection_reason" value=""/>
    <?php endif; ?>
      </div>
      </div>
      <div class="aq-crm-sidebar-panel" id="aq-crm-sidebar-customer" role="tabpanel" aria-labelledby="aq-crm-tab-btn-customer" data-aq-sidebar-panel="customer" hidden>
        <?php echo $aq_crm_sidebar_html; ?>
      </div>
<?php endif; ?>
    </aside>
<?php if ($editId): ?>
    <div class="crm-inv-main aq-manage-preview-main" id="aq-live-quotation-block" aria-label="Quotation preview">
<?php include __DIR__ . '/quotation_crm_summary.inc.php'; ?>
      <section class="crm-inv-panel">
        <div class="crm-inv-panel-body aq-manage-preview-body" id="aq-side-preview-actions">
          <div class="inv-doc-scroll aq-manage-preview-scroll-wrap" id="aq-live-doc-scroll">
            <div id="aq-live-doc-mount"></div>
          </div>
        </div>
      </section>
      <p class="aq-manage-preview-meta" id="aq-live-preview-label" hidden data-ref="<?php echo htmlspecialchars($aq_quote_ref_label !== '' ? $aq_quote_ref_label : '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($aq_quote_ref_label !== '' ? $aq_quote_ref_label : '', ENT_QUOTES, 'UTF-8'); ?></p>
      <h2 class="aq-manage-preview-heading" id="aq-live-preview-title" hidden>Quotation Preview</h2>
    </div>
<?php else: ?>
    <div id="aq-live-quotation-block" class="aq-live-doc-host" aria-hidden="true">
      <div id="aq-live-doc-mount"></div>
    </div>
<?php endif; ?>
  </div>

</div>

</div>



</div>

<div id="aq-quote-no-modal" class="aq-name-modal">
  <div class="aq-name-modal-card">
    <div class="aq-name-modal-title">Set Quote Number</div>
    <div class="aq-name-modal-sub">Enter the manual quotation number (example: QT-0031).</div>
    <input type="text" id="aq-quote-no-input" class="aq-ic" placeholder="QT-0031"/>
    <div class="aq-name-modal-actions">
      <button type="button" id="aq-quote-no-cancel" class="aq-s1-btn aq-s1-btn--gray">Cancel</button>
      <button type="button" id="aq-quote-no-ok" class="aq-s1-btn aq-s1-btn--save">Save</button>
    </div>
  </div>
</div>

<div id="aq-po-modal" class="aq-name-modal">
  <div class="aq-name-modal-card">
    <div class="aq-name-modal-title">Purchase Order Number</div>
    <div class="aq-name-modal-sub">Enter the purchase order number for this quotation.</div>
    <input type="text" id="aq-po-input" class="aq-ic" placeholder="Enter PO number"/>
    <div class="aq-name-modal-actions">
      <button type="button" id="aq-po-cancel" class="aq-s1-btn aq-s1-btn--gray">Cancel</button>
      <button type="button" id="aq-po-ok" class="aq-s1-btn aq-s1-btn--save">Save</button>
    </div>
  </div>
</div>

<?php if ($editId && is_admin()): ?>
<div id="aq-delete-modal" class="aq-name-modal">
  <div class="aq-name-modal-card">
    <div class="aq-name-modal-title">Delete Quotation</div>
    <div class="aq-name-modal-sub">Move this quotation to the recycle bin? It will be removed from the active quotation list and can be restored later if needed.</div>
    <div class="aq-name-modal-actions">
      <button type="button" id="aq-delete-cancel" class="aq-s1-btn aq-s1-btn--gray">Cancel</button>
      <form method="POST" action="Quotation/quotations.php" style="margin:0;">
        <input type="hidden" name="delete_id" value="<?php echo (int)$editId; ?>">
        <button type="submit" class="aq-s1-btn aq-s1-btn--danger"><i class="fas fa-trash"></i> Move to Recycle Bin</button>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($editId): ?>
<!-- Download / Print confirmation — quotations.php #deleteModal blueprint -->
<div class="erp-modal-overlay" id="aqExportModal" role="dialog" aria-modal="true" aria-labelledby="aqExportModalTitle">
  <div class="erp-modal aq-export-modal">
    <div class="erp-modal-header">
      <h3 class="erp-modal-title" id="aqExportModalTitle">Download Quotation</h3>
      <button type="button" class="erp-modal-close" id="aqExportModalClose" aria-label="Close">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="erp-modal-body aq-export-modal__body">
      <div id="aqExportModalIconWrap" class="aq-export-modal__icon-wrap aq-export-modal__icon-wrap--download">
        <i id="aqExportModalIcon" class="fas fa-download aq-export-modal__icon"></i>
      </div>
      <p class="aq-export-modal__prompt" id="aqExportModalPrompt">Download a copy of this quotation:</p>
      <h4 class="aq-export-modal__quote-name" id="aqExportModalQuoteName"></h4>
      <p class="aq-export-modal__hint" id="aqExportModalHint">The file will include full quotation styling and can be opened in any browser.</p>
    </div>
    <div class="erp-modal-footer">
      <button type="button" class="erp-btn erp-btn-secondary" id="aqExportModalCancel">Cancel</button>
      <button type="button" class="erp-btn erp-btn-primary" id="aqExportModalConfirm"><i class="fas fa-download"></i> Download</button>
    </div>
  </div>
</div>

<!-- Send to manager / paper sign-off feedback -->
<div class="jc-flash-wrap no-print" id="aqMgrSendModal" hidden aria-hidden="true">
  <div class="jc-flash-card jc-flash-card--mgr jc-flash-card--success" role="dialog" aria-modal="true" aria-labelledby="aqMgrSendModalTitle">
    <button type="button" class="jc-flash-close" id="aqMgrSendModalClose" aria-label="Close">&times;</button>
    <div class="jc-flash-icon" id="aqMgrSendModalIcon" aria-hidden="true"><i class="fas fa-paper-plane"></i></div>
    <div class="jc-flash-title" id="aqMgrSendModalTitle">Sent for manager preview</div>
    <div class="jc-flash-body" id="aqMgrSendModalBody"></div>
    <p class="jc-flash-hint" id="aqMgrSendModalHint"><strong>Manager preview (before paper sign-off)</strong> — The quotation is in the manager <em>Sent for review</em> inbox. They can open the document preview in their portal before you print for paper sign-off.</p>
    <button type="button" class="jc-flash-btn" id="aqMgrSendModalOk">OK</button>
  </div>
</div>
<div class="jc-flash-wrap no-print" id="aqSignoffModal" hidden aria-hidden="true">
  <div class="jc-flash-card jc-flash-card--mgr jc-flash-card--signoff jc-flash-card--success" role="dialog" aria-modal="true" aria-labelledby="aqSignoffModalTitle">
    <button type="button" class="jc-flash-close" id="aqSignoffModalClose" aria-label="Close">&times;</button>
    <div class="jc-flash-icon" id="aqSignoffModalIcon" aria-hidden="true"><i class="fas fa-file-signature"></i></div>
    <div class="jc-flash-title" id="aqSignoffModalTitle">Sign-off recorded</div>
    <div class="jc-flash-body" id="aqSignoffModalBody"></div>
    <p class="jc-flash-hint" id="aqSignoffModalHint"><strong>System record only</strong> — Status is marked approved for workflow. The printed quotation and invoice preview are unchanged. Use the <strong>Finish</strong> tab to send to the client or create an invoice.</p>
    <button type="button" class="jc-flash-btn" id="aqSignoffModalOk">OK</button>
  </div>
</div>
<?php endif; ?>

<div id="aq-print-shell" class="aq-print-shell fixed inset-0 z-50 overflow-auto hidden">
  <div class="invoice-container">
    <div class="no-print aq-print-shell-toolbar" style="width:210mm;max-width:calc(100% - 20px);margin:0 auto;padding:12px 0 10px;display:flex;align-items:center;justify-content:center;">
      <h2 id="aq-print-titlebar" style="margin:0;font-size:1rem;font-weight:600;color:#334155;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">Quotation Preview</h2>
    </div>
    <div id="aq-print-mount" style="display:flex;justify-content:center;padding:0 10px 10px;width:100%;"></div>
    <div class="no-print bottom-actions">
      <div class="button-group">
        <button type="button" id="aq-close-print" class="aq-s1-btn aq-s1-btn--gray">
          <i class="fas fa-times"></i> Cancel
        </button>
        <button type="button" id="aq-do-print" class="aq-s1-btn aq-s1-btn--green">
          <i class="fas fa-print"></i> Print Quotation
        </button>
        <button type="button" id="aq-save-pdf-bottom" class="aq-s1-btn aq-s1-btn--gray">
          <i class="fas fa-download"></i> Save / PDF
        </button>
      </div>
    </div>
  </div>
</div>

<script>
window.aqDoPreview = function(blank){
  function runPreview(){
    if(window.AQ && window.AQ._openPrintReady){
      if(window.AQ._useSidePreview && typeof window.AQ._realOpenSidePreview==='function'){
        window.AQ._realOpenSidePreview(!!blank);
        return true;
      }
      if(typeof window.AQ._realOpenPrint==='function'){
        window.AQ._realOpenPrint(!!blank);
        return true;
      }
    }
    return false;
  }
  if(runPreview()) return;
  window.AQ_PENDING_PREVIEW = !!blank;
  var tries = 0;
  (function wait(){
    if(runPreview()) return;
    if(++tries < 80){
      setTimeout(wait, 150);
      return;
    }
    alert('Preview is still loading. Please refresh the page and try again.');
  })();
};
(function(){
  var previewBtn = document.getElementById('aq-btn-preview');
  var printBlankBtn = document.getElementById('aq-btn-print-blank');
  if(previewBtn){
    previewBtn.addEventListener('click', function(e){
      e.preventDefault();
      window.aqDoPreview(false);
    });
  }
  if(printBlankBtn){
    printBlankBtn.addEventListener('click', function(e){
      e.preventDefault();
      window.aqDoPreview(true);
    });
  }
})();
</script>
<?php if ($editId): ?>
<script>window.AQ_CRM_OPEN_CUSTOMER_TAB = <?php echo $aq_open_chat ? 'true' : 'false'; ?>;</script>
<?php endif; ?>

<script>
(function(){
  const __aqNativeAlert = window.alert.bind(window);
  let _aqPendingExportAction = null;
  let _aqLiveDocRaf = null;
  const AQ_DOC_HDR = <?php echo json_encode($aq_doc_hdr_bg, JSON_THROW_ON_ERROR); ?>;
  const AQ_DOC_BD = <?php echo json_encode($aq_doc_bd_col, JSON_THROW_ON_ERROR); ?>;
  const AQ_DOC_BODY = <?php echo json_encode($aq_doc_body_bg, JSON_THROW_ON_ERROR); ?>;

  let _aqFlashWrap = null;
  function hideAqFlash(){
    const wrap = _aqFlashWrap || document.getElementById('aqFlashWrap') || document.getElementById('jcFlashWrap');
    if(!wrap) return;
    wrap.classList.remove('show');
    setTimeout(function(){ if(wrap.parentNode) wrap.parentNode.removeChild(wrap); }, 220);
    _aqFlashWrap = null;
  }
  function showAqFlash(opts){
    opts = opts || {};
    if(typeof window.closeAqExportModal==='function') window.closeAqExportModal();
    const kind = opts.kind === 'success' || opts.kind === 'error' ? opts.kind : 'info';
    const title = (opts.title || 'Notice').toString();
    const body = (opts.body || '').toString();
    const iconClass = kind === 'error' ? 'fa-exclamation' : (kind === 'success' ? 'fa-check' : 'fa-file-lines');
    const cardClass = kind === 'error' ? 'jc-flash-card--error' : (kind === 'success' ? 'jc-flash-card--success' : 'jc-flash-card--info');
    hideAqFlash();
    const wrap = document.createElement('div');
    wrap.id = 'aqFlashWrap';
    wrap.className = 'jc-flash-wrap';
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-modal', 'true');
    const card = document.createElement('div');
    card.className = 'jc-flash-card ' + cardClass;
    const icon = document.createElement('div');
    icon.className = 'jc-flash-icon';
    icon.innerHTML = '<i class="fas ' + iconClass + '" aria-hidden="true"></i>';
    const titleEl = document.createElement('div');
    titleEl.className = 'jc-flash-title';
    titleEl.textContent = title;
    const bodyEl = document.createElement('div');
    bodyEl.className = 'jc-flash-body';
    bodyEl.textContent = body;
    card.appendChild(icon);
    card.appendChild(titleEl);
    card.appendChild(bodyEl);
    wrap.appendChild(card);
    wrap.addEventListener('click', function(e){ if(e.target === wrap) hideAqFlash(); });
    document.body.appendChild(wrap);
    _aqFlashWrap = wrap;
    requestAnimationFrame(function(){ wrap.classList.add('show'); });
    setTimeout(hideAqFlash, typeof opts.duration === 'number' ? opts.duration : 3200);
  }
  function showAqNotice(message, title){
    const t = String(title || 'Notice');
    const m = String(message || '');
    let kind = 'info';
    if(/success|saved|updated|sent|complete/i.test(t) || /successfully/i.test(m)) kind = 'success';
    else if(/error|fail|invalid|denied|unable|required|warning|caution|sign-off/i.test(t)) kind = /error|fail|invalid|denied|unable/i.test(t) ? 'error' : 'info';
    showAqFlash({ kind: kind, title: t, body: m });
  }
  window.showAqNotice = showAqNotice;
  window.showAqFlash = showAqFlash;

  function closeAqWorkflowModal(wrapId){
    const wrap=document.getElementById(wrapId);
    if(!wrap) return;
    wrap.classList.remove('show');
    wrap.setAttribute('aria-hidden','true');
    setTimeout(function(){
      wrap.hidden=true;
      wrap.setAttribute('hidden','');
    },200);
  }
  function openAqWorkflowModal(wrapId, cardSelector, opts){
    const wrap=document.getElementById(wrapId);
    if(!wrap) return false;
    const card=wrap.querySelector(cardSelector);
    if(!card) return false;
    if(typeof window.closeAqExportModal==='function') window.closeAqExportModal();
    hideAqFlash();
    const ok=!opts||opts.ok!==false;
    const titleEl=wrap.querySelector('[id$="ModalTitle"]');
    const bodyEl=wrap.querySelector('[id$="ModalBody"]');
    const hintEl=wrap.querySelector('[id$="ModalHint"]');
    const iconEl=wrap.querySelector('[id$="ModalIcon"]');
    if(titleEl) titleEl.textContent=(opts&&opts.title)||'';
    if(bodyEl) bodyEl.textContent=(opts&&opts.body)||'';
    if(hintEl){
      if(opts&&opts.hintHtml){
        hintEl.innerHTML=opts.hintHtml;
        hintEl.style.display='';
      }else if(opts&&opts.hint===false){
        hintEl.style.display='none';
      }else{
        hintEl.style.display=ok?'':'none';
      }
    }
    card.classList.remove('jc-flash-card--success','jc-flash-card--error');
    card.classList.add(ok?'jc-flash-card--success':'jc-flash-card--error');
    if(iconEl&&opts&&opts.iconHtml){
      iconEl.innerHTML=opts.iconHtml;
    }
    wrap.hidden=false;
    wrap.removeAttribute('hidden');
    wrap.setAttribute('aria-hidden','false');
    requestAnimationFrame(function(){ wrap.classList.add('show'); });
    function closeModal(){ closeAqWorkflowModal(wrapId); }
    const okBtn=wrap.querySelector('[id$="ModalOk"]');
    const closeBtn=wrap.querySelector('[id$="ModalClose"]');
    if(okBtn) okBtn.onclick=closeModal;
    if(closeBtn) closeBtn.onclick=closeModal;
    wrap.onclick=function(e){ if(e.target===wrap) closeModal(); };
    document.addEventListener('keydown',function onKey(e){
      if(e.key==='Escape') closeModal();
    },{once:true});
    return true;
  }
  function showAqMgrSendModal(opts){
    const ok=!opts||opts.ok!==false;
    if(!openAqWorkflowModal('aqMgrSendModal','.jc-flash-card',{
      ok:ok,
      title:(opts&&opts.title)||(ok?'Sent for manager preview':'Could not send'),
      body:(opts&&opts.body)||'',
      hint:ok?true:false,
      iconHtml:ok
        ?'<i class="fas fa-paper-plane"></i>'
        :'<i class="fas fa-exclamation-circle"></i>'
    })){
      alert((opts&&opts.body)||'Done');
    }
  }
  function showAqSignoffModal(opts){
    const variant=(opts&&opts.variant)==='rejection'?'rejection':'signoff';
    const wrap=document.getElementById('aqSignoffModal');
    const card=wrap?wrap.querySelector('.jc-flash-card'):null;
    if(card){
      card.classList.remove('jc-flash-card--signoff','jc-flash-card--rejection');
      card.classList.add(variant==='rejection'?'jc-flash-card--rejection':'jc-flash-card--signoff');
    }
    const ok=opts&&opts.ok!==false;
    const defaultTitle=variant==='rejection'
      ? (ok?'Rejection recorded':'Could not save rejection')
      : (ok?'Sign-off recorded':'Sign-off failed');
    const defaultHint=variant==='rejection'
      ? '<strong>Declined on paper</strong> — Status is marked rejected. Send to client and create invoice are disabled until you revise the quotation.'
      : '<strong>System record only</strong> — Status is marked approved for workflow. The printed quotation and invoice preview are unchanged. Use the <strong>Finish</strong> tab to send to the client or create an invoice.';
    const hintOff=opts&&opts.hint===false;
    if(!openAqWorkflowModal('aqSignoffModal','.jc-flash-card',{
      ok:ok,
      title:(opts&&opts.title)||defaultTitle,
      body:(opts&&opts.body)||'',
      hint:hintOff?false:true,
      hintHtml:hintOff?undefined:((opts&&opts.hintHtml)||defaultHint),
      iconHtml:ok
        ? (variant==='rejection'?'<i class="fas fa-ban"></i>':'<i class="fas fa-file-signature"></i>')
        :'<i class="fas fa-exclamation-circle"></i>'
    })){
      showAqFlash({
        kind:ok?'success':'error',
        title:(opts&&opts.title)||defaultTitle,
        body:(opts&&opts.body)||'',
        duration:4500
      });
    }
  }

  function getAqExportQuoteLabel(){
    const metaEl=document.getElementById('aq-live-preview-label');
    if(metaEl && metaEl.textContent.trim()) return metaEl.textContent.trim();
    const qnEl=document.getElementById('f-quote_number');
    if(qnEl && qnEl.value && qnEl.value.trim()) return qnEl.value.trim();
    if(metaEl && metaEl.dataset.ref) return metaEl.dataset.ref;
    return 'this quotation';
  }

  function closeAqExportModal(){
    const modal=document.getElementById('aqExportModal');
    if(modal) modal.classList.remove('show');
    _aqPendingExportAction=null;
  }
  window.closeAqExportModal=closeAqExportModal;

  function openAqExportModal(action){
    const modal=document.getElementById('aqExportModal');
    const titleEl=document.getElementById('aqExportModalTitle');
    const promptEl=document.getElementById('aqExportModalPrompt');
    const nameEl=document.getElementById('aqExportModalQuoteName');
    const hintEl=document.getElementById('aqExportModalHint');
    const iconWrap=document.getElementById('aqExportModalIconWrap');
    const iconEl=document.getElementById('aqExportModalIcon');
    const confirmBtn=document.getElementById('aqExportModalConfirm');
    if(!modal || !confirmBtn){
      showAqNotice('The export dialog is not available on this page.','Unavailable');
      return;
    }
    _aqPendingExportAction=action;
    const label=getAqExportQuoteLabel();
    if(action==='print'){
      if(titleEl) titleEl.textContent='Print Quotation';
      if(promptEl) promptEl.textContent='Print this quotation:';
      if(hintEl) hintEl.textContent='Your browser print dialog will open with the formatted document.';
      if(iconWrap){
        iconWrap.classList.remove('aq-export-modal__icon-wrap--download');
        iconWrap.classList.add('aq-export-modal__icon-wrap--print');
      }
      if(iconEl){ iconEl.className='fas fa-print aq-export-modal__icon'; }
      confirmBtn.innerHTML='<i class="fas fa-print"></i> Print';
    }else{
      if(titleEl) titleEl.textContent='Download Quotation';
      if(promptEl) promptEl.textContent='Download a copy of this quotation:';
      if(hintEl) hintEl.textContent='A PDF copy of this quotation preview will be saved to your device.';
      if(iconWrap){
        iconWrap.classList.remove('aq-export-modal__icon-wrap--print');
        iconWrap.classList.add('aq-export-modal__icon-wrap--download');
      }
      if(iconEl){ iconEl.className='fas fa-download aq-export-modal__icon'; }
      confirmBtn.innerHTML='<i class="fas fa-download"></i> Download';
    }
    if(nameEl) nameEl.textContent=label;
    modal.classList.add('show');
  }

  function confirmAqExportModal(){
    const action=_aqPendingExportAction;
    closeAqExportModal();
    try{
      if(typeof syncLiveDocPreview==='function') syncLiveDocPreview(!!window._aqSidePreviewBlank);
    }catch(err){ console.error('Preview sync failed', err); }
    try{
      if(action==='print') sidePreviewPrint();
      else if(action==='download') sidePreviewDownload();
    }catch(err){
      console.error('Export failed', err);
      showAqNotice('Could not export the quotation. Please try again.','Export failed');
    }
  }

  function aqRequestSideDownload(e){
    if(e){ e.preventDefault(); e.stopPropagation(); }
    sidePreviewDownload();
  }

  function aqRequestSidePrint(e){
    if(e){ e.preventDefault(); e.stopPropagation(); }
    openAqExportModal('print');
  }

  function aqWireExportModal(){
    const modal=document.getElementById('aqExportModal');
    if(!modal || modal.dataset.aqWired) return;
    modal.dataset.aqWired='1';
    modal.addEventListener('click', function(e){
      if(e.target===modal) closeAqExportModal();
    });
    const closeBtn=document.getElementById('aqExportModalClose');
    const cancelBtn=document.getElementById('aqExportModalCancel');
    const confirmBtn=document.getElementById('aqExportModalConfirm');
    if(closeBtn) closeBtn.addEventListener('click', closeAqExportModal);
    if(cancelBtn) cancelBtn.addEventListener('click', closeAqExportModal);
    if(confirmBtn) confirmBtn.addEventListener('click', confirmAqExportModal);
  }

  window._aqRunSideDownload=aqRequestSideDownload;
  window._aqRunSidePrint=aqRequestSidePrint;
  aqWireExportModal();

  const SYM = <?php echo json_encode($currency['symbol'] ?? 'N$'); ?>;
  const HEADER_IMG = <?php echo aq_js_json($header_img_url); ?>;
  const FOOTER_IMG = <?php echo aq_js_json($footer_img_url); ?>;
  const JOB_OPTIONS = <?php echo aq_js_json($job_cards); ?>;
  let savedId = <?php echo aq_js_json($loadedSavedId); ?>;
  const EDIT_PAYLOAD = <?php echo aq_js_json($saved_payload); ?>;
  const NEXT_QUOTE = <?php echo aq_js_json($next_quote_num); ?>;
  const FORCED_JOB_CARD = <?php echo aq_js_json($forcedJobCard); ?>;
  const INITIAL_CLIENT_STATUS = <?php echo aq_js_json($aq_client_status); ?>;
  const INITIAL_MGR_REVIEW = <?php echo aq_js_json($aq_mgr_review_initial); ?>;
  const CSRF_NOP = ''; // rely on admin session / same-site POST
  const CURRENT_ROLE = <?php echo json_encode($aq_current_role); ?>;
  const CURRENT_USER = <?php echo json_encode($aq_current_user); ?>;
  const INVOICE_LIST_URL = <?php echo json_encode($invoiceListUrl); ?>;
  const PAGE_SUCCESS = <?php echo json_encode($pageSuccess); ?>;
  const PAGE_ERROR = <?php echo json_encode($pageError); ?>;
  const PREVIEW_ONLY_MODE = <?php echo $previewOnlyMode ? 'true' : 'false'; ?>;
  const PAGE_IS_EDIT = <?php echo $editId ? 'true' : 'false'; ?>;
  const AQ_EDIT_ID = <?php echo (int) ($editId ?? 0); ?>;
  const AQ_REF_LABEL = <?php echo json_encode($aq_quote_ref_label); ?>;
  const USE_SIDE_PREVIEW = !!document.querySelector('.aq-manage-preview-main');
  window.aqSideDownload = function(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (typeof window._aqRunSideDownload === 'function') return window._aqRunSideDownload(e);
    showAqNotice('Quotation preview is still loading. Please try again in a moment.', 'Please wait');
  };
  window.aqSidePrint = function(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (typeof window._aqRunSidePrint === 'function') return window._aqRunSidePrint(e);
    showAqNotice('Quotation preview is still loading. Please try again in a moment.', 'Please wait');
  };

  const DEFAULT_LABOUR = 2, DEFAULT_PARTS = 2, DEFAULT_CONSUMABLES = 2;
  window.AQ = window.AQ || {};
  window.AQ._openPrintReady = false;
  window.AQ.openPrint = function(blank){
    if(window.AQ._openPrintReady && window.AQ._realOpenPrint){
      return window.AQ._realOpenPrint(!!blank);
    }
    window.AQ_PENDING_PREVIEW = !!blank;
  };
  window.__aqNativeAlert = __aqNativeAlert;

  function uid(prefix){ return prefix + '-'+Date.now()+'-'+Math.random().toString(36).slice(2); }

  function makeLab(order){
    const id = uid('lr');
    return { id, category:order===0?'diagnostic':'normal_time', description:'', hours:0, rate:0, total:0, include_in_print:true, sort_order:order };
  }
  function makePart(cat, order){
    return { id: uid('pr'), category:cat, item_name:'', qty:0, unit_cost:0, total:0, include_in_print:true, sort_order:order };
  }

  let labourRows=[], partsRows=[], consRows=[];

  function rowLab(r){
    const cat=r.category||'normal_time';
    const catOpts=[['diagnostic','Diagnostic'],['normal_time','Normal Time'],['overtime','Overtime'],['public_holiday','Public Holiday']].map(function(o){return '<option value="'+o[0]+'"'+(cat===o[0]?' selected':'')+'>'+o[1]+'</option>';}).join('');
    return '<tr data-id="'+escapeAttr(r.id)+'" class="border-b border-gray-50 hover:bg-gray-50">'+
      '<td class="px-3 py-1.5 text-center"><input type="checkbox" class="lab-print accent-orange-500" '+(r.include_in_print?'checked':'')+'/></td>'+
      '<td class="px-3 py-1.5"><select class="lab-cat w-full border-0 bg-transparent text-xs text-gray-600 mb-1">'+catOpts+'</select><input type="text" class="lab-desc w-full border-0 bg-transparent text-sm"/></td>'+
      '<td class="px-3 py-1.5"><input type="number" class="lab-h w-full border-0 bg-transparent text-sm text-center" step="any"/></td>'+
      '<td class="px-3 py-1.5"><input type="number" class="lab-r w-full border-0 bg-transparent text-sm text-center" step="any"/></td>'+
      '<td class="px-3 py-1.5 text-right font-medium lab-t">'+(r.total>0?SYM+r.total.toFixed(2):'')+'</td>'+
      '<td class="px-2 py-1.5"><button type="button" class="lab-del text-gray-300 hover:text-red-500"><i class="fas fa-trash"></i></button></td>'+
      '</tr>';
  }
  function rowPart(tbl, r){
    return '<tr data-id="'+escapeAttr(r.id)+'" class="border-b border-gray-50">'+
      '<td class="px-3 py-1.5 text-center"><input type="checkbox" class="pp-print accent-orange-500" '+(r.include_in_print?'checked':'')+'/></td>'+
      '<td class="px-3 py-1.5">'+(tbl==='parts'? invSelect(): '')+'<input type="text" class="pp-name w-full border-0 bg-transparent text-sm"/></td>'+
      '<td class="px-3 py-1.5"><input type="number" class="pp-q w-full border-0 bg-transparent text-sm text-center" step="any"/></td>'+
      '<td class="px-3 py-1.5"><input type="number" class="pp-u w-full border-0 bg-transparent text-sm text-center" step="any"/></td>'+
      '<td class="px-3 py-1.5 text-right font-medium pp-t">'+(r.total>0?SYM+r.total.toFixed(2):'')+'</td>'+
      '<td class="px-2 py-1.5"><button type="button" class="pp-del text-gray-300 hover:text-red-500"><i class="fas fa-trash"></i></button></td>'+
      '</tr>';
  }
  const INV=<?php echo aq_js_json($inventory); ?>;

  function invSelect(){
    if(!INV.length) return '';
    let o='<select class="w-full text-xs text-gray-500 border-b border-gray-200 mb-1 pp-sel"><option value="">— Inventory —</option>';
    INV.forEach(x=>{ o+='<option value="'+Number(x.price)+'" data-n="'+escapeAttr(x.part_name)+'">'+escapeHtml(x.part_name)+' (stock '+x.stock+')</option>'; });
    return o+'</select>';
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g,c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }
  function escapeAttr(s){ return escapeHtml(s).replace(/"/g,'&quot;'); }

  function syncLabourDom(){
    const tb=document.getElementById('aq-tbody-labour');
    if(!tb) return;
    tb.innerHTML=''; labourRows.forEach((r,i)=>{ tb.insertAdjacentHTML('beforeend', rowLab(r)); const tr=tb.lastElementChild;
      tr.querySelector('.lab-desc').value=r.description; tr.querySelector('.lab-h').value=r.hours||''; tr.querySelector('.lab-r').value=r.rate||'';
    });
    bindLabour();
  }
  function syncPartsDom(){
    const tb=document.getElementById('aq-tbody-parts');
    const tc=document.getElementById('aq-tbody-cons');
    if(!tb || !tc) return;
    tb.innerHTML='';
    partsRows.forEach(r=>{ tb.insertAdjacentHTML('beforeend', rowPart('parts',r)); fillPartRow(tb.lastElementChild,r); });
    bindParts(tb,'parts');
    tc.innerHTML='';
    consRows.forEach(r=>{ tc.insertAdjacentHTML('beforeend', rowPart('cons',r)); fillPartRow(tc.lastElementChild,r); });
    bindParts(tc,'cons');
  }
  function fillPartRow(tr,r){
    tr.querySelector('.pp-name').value=r.item_name;
    tr.querySelector('.pp-q').value=r.qty||'';
    tr.querySelector('.pp-u').value=r.unit_cost||'';
  }

  function bindLabour(){
    document.querySelectorAll('#aq-tbody-labour tr').forEach(tr=>{
      const id=tr.dataset.id;
      tr.querySelector('.lab-print').onchange=e=>{ const r=labourRows.find(x=>x.id===id); if(r) r.include_in_print=e.target.checked; calc(); };
      tr.querySelector('.lab-desc').oninput=e=>{ const r=labourRows.find(x=>x.id===id); if(r) r.description=e.target.value; scheduleLiveDocPreview(); };
      tr.querySelector('.lab-h').oninput=e=>{ const r=labourRows.find(x=>x.id===id); if(r){ r.hours=parseFloat(e.target.value)||0; r.total=r.hours*r.rate; } calc(); };
      tr.querySelector('.lab-r').oninput=e=>{ const r=labourRows.find(x=>x.id===id); if(r){ r.rate=parseFloat(e.target.value)||0; r.total=r.hours*r.rate; } calc(); };
      tr.querySelector('.lab-del').onclick=()=>{ labourRows=labourRows.filter(x=>x.id!==id); syncLabourDom(); calc(); };
    });
  }
  function bindParts(root, kind){
    const arr = kind==='parts'?partsRows:consRows;
    root.querySelectorAll('tr').forEach(tr=>{
      const id=tr.dataset.id;
      const sel=tr.querySelector('.pp-sel');
      if(sel){ sel.onchange=()=>{ const opt=sel.selectedOptions[0]; const r=arr.find(x=>x.id===id); if(!r) return; const n=opt.getAttribute('data-n')||''; const p=parseFloat(opt.value)||0; r.item_name=n; r.unit_cost=p; tr.querySelector('.pp-name').value=n; calc(); syncPartsDom(); }; }
      tr.querySelector('.pp-print').onchange=e=>{ const r=arr.find(x=>x.id===id); if(r) r.include_in_print=e.target.checked; calc(); };
      tr.querySelector('.pp-name').oninput=e=>{ const r=arr.find(x=>x.id===id); if(r) r.item_name=e.target.value; scheduleLiveDocPreview(); };
      tr.querySelector('.pp-q').oninput=e=>{ const r=arr.find(x=>x.id===id); if(r){ r.qty=parseFloat(e.target.value)||0; r.total=r.qty*r.unit_cost; } calc(); };
      tr.querySelector('.pp-u').oninput=e=>{ const r=arr.find(x=>x.id===id); if(r){ r.unit_cost=parseFloat(e.target.value)||0; r.total=r.qty*r.unit_cost; } calc(); };
      tr.querySelector('.pp-del').onclick=()=>{
        if(kind==='parts') partsRows=partsRows.filter(x=>x.id!==id); else consRows=consRows.filter(x=>x.id!==id);
        syncPartsDom(); calc();
      };
    });
  }

  function v(id){ const el=document.getElementById(id); return el? String(el.type==='checkbox'?(el.checked?1:''): el.value):''; }
  function vn(id){ const x=parseFloat(v(id)); return isFinite(x)?x:0; }
  function vb(id){ const el=document.getElementById(id); return !!(el&&el.checked); }

  function customerAddressLines(){
    return [v('f-customer_address'), v('f-customer_address2'), v('f-customer_address3')]
      .map(function(s){ return String(s||'').trim(); })
      .filter(Boolean);
  }
  function combineCustomerAddress(){
    return customerAddressLines().join('\n');
  }
  function splitCustomerAddressIntoFields(addr){
    const parts=String(addr==null?'':addr).split(/\r?\n/).map(function(s){ return s.trim(); });
    const l1=document.getElementById('f-customer_address');
    const l2=document.getElementById('f-customer_address2');
    const l3=document.getElementById('f-customer_address3');
    if(l1) l1.value=parts[0]||'';
    if(l2) l2.value=parts[1]||'';
    if(l3) l3.value=parts.length>2?parts.slice(2).join('\n'):'';
  }

  function aqHeaderLabelsFromFormObject(f){
    if(f&&Array.isArray(f.general_header_labels)&&f.general_header_labels.length){
      return f.general_header_labels.map(function(x){return String(x||'').trim();}).filter(Boolean);
    }
    const out=[];
    const a=String((f&&f.attend_to_service_label)||'').trim();
    const d=String((f&&f.diagnostic_label)||'').trim();
    if(a) out.push(a);
    if(d) out.push(d);
    return out;
  }
  function aqCollectHeaderLabels(){
    const el=document.getElementById('f-general_header_labels_json');
    if(el&&el.value){
      try{
        const parsed=JSON.parse(el.value);
        if(Array.isArray(parsed)){
          const labels=parsed.map(function(x){return String(x||'').trim();}).filter(Boolean);
          if(labels.length) return labels;
        }
      }catch(_){}
    }
    const labels=[];
    const a=v('f-attend_to_service_label').trim();
    const d=v('f-diagnostic_label').trim();
    if(a) labels.push(a);
    if(d) labels.push(d);
    return labels;
  }
  function aqSyncLegacyHeaderFields(labels){
    const a=document.getElementById('f-attend_to_service_label');
    const d=document.getElementById('f-diagnostic_label');
    const json=document.getElementById('f-general_header_labels_json');
    if(json) json.value=JSON.stringify(labels||[]);
    if(a) a.value=(labels&&labels[0])?labels[0]:'';
    if(d) d.value=(labels&&labels[1])?labels[1]:'';
  }
  function aqRenderHeaderLabelsDisplay(labels){
    const box=document.getElementById('aqHeaderLabelsDisplay');
    if(!box) return;
    const rows=Array.isArray(labels)?labels:[];
    if(!rows.length){
      box.innerHTML='<span class="text-gray-500">No description labels — set them on the job card.</span>';
      return;
    }
    box.innerHTML=rows.map(function(text,i){
      return '<div class="px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg"><span class="text-xs text-gray-500 mr-2">Row '+(i+1)+'</span>'+escapeHtml(text)+'</div>';
    }).join('');
  }
  function aqBuildLabourHeaderRowsHtml(labels, labHdrStyle, labHdrDual, blankOnly){
    const rows=Array.isArray(labels)&&labels.length?labels:[''];
    const rowSpan=rows.length;
    const subHdrStyle='background:#d9d9d9;color:#000;border:1px solid #000;font-weight:bold;text-align:center;padding:3px 5px;font-size:7.5pt;white-space:normal;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;';
    let html='';
    rows.forEach(function(label, idx){
      const cellStyle=(blankOnly&&idx>0)?subHdrStyle:labHdrStyle;
      const cellCls=(blankOnly&&idx>0)?' aq-lab-hdr-sub':'';
      if(idx===0){
        html+='<tr class="aq-labour-hdr-cols'+labHdrDual+'">';
        html+='<th rowspan="'+rowSpan+'" class="aq-lab-hdr-side" style="'+labHdrStyle+'">Description</th>';
        html+='<th class="'+cellCls.trim()+'" style="'+cellStyle+'">'+escapeHtml(label)+'</th>';
        html+='<th rowspan="'+rowSpan+'" class="aq-lab-hdr-side" style="'+labHdrStyle+'">Hour</th>';
        html+='<th rowspan="'+rowSpan+'" class="aq-lab-hdr-side" style="'+labHdrStyle+'">Rate</th>';
        html+='<th rowspan="'+rowSpan+'" class="aq-lab-hdr-side" style="'+labHdrStyle+'">Total</th></tr>';
      }else{
        html+='<tr class="aq-labour-hdr-label'+labHdrDual+'">';
        if(blankOnly){
          html+='<th colspan="4" class="'+cellCls.trim()+'" style="'+cellStyle+'">'+escapeHtml(label)+'</th>';
        }else{
          html+='<th class="'+cellCls.trim()+'" style="'+cellStyle+'">'+escapeHtml(label)+'</th>';
        }
        html+='</tr>';
      }
    });
    return html;
  }
  function aqApplyHeaderLabelsFromForm(f){
    const labels=aqHeaderLabelsFromFormObject(f||{});
    aqSyncLegacyHeaderFields(labels);
    aqRenderHeaderLabelsDisplay(labels);
  }

  function readForm(){
    const general_header_labels=aqCollectHeaderLabels();
    aqSyncLegacyHeaderFields(general_header_labels);
    return {
      quote_number: v('f-quote_number'), customer_name: v('f-customer_name'), customer_address: combineCustomerAddress(),
      customer_email: v('f-customer_email'), customer_phone: v('f-customer_phone'), contact_person: v('f-contact_person'),
      date: v('f-date'), vehicle_reg_no: v('f-vehicle_reg_no'), vin_no: v('f-vin_no'), fleet_no: v('f-fleet_no'),
      model: v('f-model'), kilometers: v('f-kilometers'), job_no: v('f-job_no'), purchase_order: v('f-purchase_order'),
      normal_time_rate: vn('f-normal_time_rate'), overtime_rate: vn('f-overtime_rate'), public_holiday_rate: vn('f-public_holiday_rate'),
      general_header_labels: general_header_labels,
      attend_to_service_label: v('f-attend_to_service_label'), diagnostic_label: v('f-diagnostic_label'),
      show_normal_time: vb('f-show_normal_time'), show_overtime: vb('f-show_overtime'), show_public_holiday: vb('f-show_public_holiday'),
      approved_at: v('f-approved_at'), approved_by: v('f-approved_by'),
      paper_signed_at: v('f-approved_at'), paper_signed_by: v('f-approved_by'),
      rejected_at: v('f-rejected_at'), rejected_by: v('f-rejected_by'), rejection_reason: v('f-rejection_reason'),
      sent_back_at: v('f-sent_back_at'), sent_back_by: v('f-sent_back_by'), sent_back_reason: v('f-sent_back_reason'),
      manager_review_sent_at: v('f-manager_review_sent_at'), manager_review_sent_by: v('f-manager_review_sent_by'),
      manager_review_viewed_at: v('f-manager_review_viewed_at'),
      vat_rate: vn('f-vat_rate'), status: (document.getElementById('f-status')||{value:'draft'}).value || 'draft',
      blank_normal_lines: vn('f-blank_normal_lines'), blank_overtime_lines: vn('f-blank_overtime_lines'), blank_holiday_lines: vn('f-blank_holiday_lines'),
      blank_parts_top_lines: vn('f-blank_parts_top_lines'), blank_parts_lines: vn('f-blank_parts_lines'), blank_cons_lines: vn('f-blank_cons_lines'),
      print_blank_show_labour: vb('f-print_blank_show_labour'), print_blank_show_parts: vb('f-print_blank_show_parts'),
      blank_col_lab_hours: vb('f-blank_col_lab_hours'), blank_col_lab_rate: vb('f-blank_col_lab_rate'), blank_col_lab_total: vb('f-blank_col_lab_total'),
      blank_col_part_qty: vb('f-blank_col_part_qty'), blank_col_part_cost: vb('f-blank_col_part_cost'), blank_col_part_total: vb('f-blank_col_part_total'),
    };
  }

  function setDocToolActive(btn){
    const grid=document.querySelector('.aq-doc-tools-grid');
    if(!grid) return;
    grid.querySelectorAll('[data-doc-tool]').forEach(function(el){
      el.classList.remove('is-active');
    });
    if(btn) btn.classList.add('is-active');
  }
  window.aqSetDocToolActive=setDocToolActive;

  function labourTotalCalc(){
    return labourRows.filter(r=>r.include_in_print).reduce((s,r)=>s+r.total,0);
  }
  function partsTotalCalc(){
    return partsRows.filter(r=>r.include_in_print).reduce((s,r)=>s+r.total,0)+consRows.filter(r=>r.include_in_print).reduce((s,r)=>s+r.total,0);
  }

  function calcTotalsOnly(){
    const lt=labourTotalCalc(), pt=partsTotalCalc();
    const frm=readForm();
    const sub=lt+pt;
    const vat=sub*((frm.vat_rate||0)/100);
    const g=sub+vat;
    return { lab:lt, part:pt, sub:sub, vat:vat, grand:g };
  }
  function calc(){
    labourRows.forEach(r=>{ trRefreshLab(r.id); });
    partsRows.concat(consRows).forEach(r=>{ trRefreshPart(r.id); });
    const totals=calcTotalsOnly();
    const lt=totals.lab, pt=totals.part;
    const frm=readForm();
    const sub=totals.sub, vat=totals.vat, g=totals.grand;
    const footLab=document.getElementById('aq-foot-labour');
    const footParts=document.getElementById('aq-foot-parts');
    if(footLab) footLab.textContent=SYM+lt.toFixed(2);
    if(footParts) footParts.textContent=SYM+pt.toFixed(2);
    const tSub = document.getElementById('t-sub');
    const tVlabel = document.getElementById('t-vlabel');
    const tVat = document.getElementById('t-vat');
    const tGrand = document.getElementById('t-grand');
    if (tSub) tSub.textContent = SYM + sub.toFixed(2);
    if (tVlabel) tVlabel.textContent = 'VAT (' + (frm.vat_rate || 0) + '%)';
    if (tVat) tVat.textContent = SYM + vat.toFixed(2);
    if (tGrand) tGrand.textContent = SYM + g.toFixed(2);
    updateBadge();
    updateClientBtn();
    updatePaperSignoffBadge();
  updatePaperRejectionBadge();
    syncQuoteHeading();
    syncCrmCustomerPanel(frm, totals);
    scheduleLiveDocPreview();
    return totals;
  }

  function trRefreshLab(id){
    const tr=[...document.querySelectorAll('#aq-tbody-labour tr')].find(t=>t.dataset.id===id);
    const r=labourRows.find(x=>x.id===id); if(!tr||!r) return; tr.querySelector('.lab-t').textContent=r.total>0?SYM+r.total.toFixed(2):'';
  }
  function trRefreshPart(id){
    const tr=[...document.querySelectorAll('#aq-tbody-parts tr, #aq-tbody-cons tr')].find(t=>t.dataset.id===id);
    const all=[...partsRows,...consRows]; const r=all.find(x=>x.id===id);
    if(!tr||!r) return; tr.querySelector('.pp-t').textContent=r.total>0?SYM+r.total.toFixed(2):'';
  }

  function crmDash(val){
    const s=String(val==null?'':val).trim();
    return s===''?'—':s;
  }
  function crmInitials(name){
    const parts=String(name||'').trim().split(/\s+/).filter(Boolean);
    if(!parts.length) return '?';
    if(parts.length===1) return parts[0].slice(0,2).toUpperCase();
    return (parts[0][0]+parts[parts.length-1][0]).toUpperCase();
  }
  function crmSetMetaLink(id, val, type){
    const el=document.getElementById(id);
    if(!el) return;
    const s=String(val||'').trim();
    if(s===''){ el.textContent='—'; return; }
    if(type==='email'){ el.innerHTML='<a href="mailto:'+escapeHtml(s)+'">'+escapeHtml(s)+'</a>'; return; }
    if(type==='phone'){ el.innerHTML='<a href="tel:'+escapeHtml(s.replace(/\s+/g,''))+'">'+escapeHtml(s)+'</a>'; return; }
    el.textContent=s;
  }
  function syncCrmCustomerPanel(frm, totals){
    const f=frm||readForm();
    const t=totals||calcTotalsOnly();
    const name=f.customer_name||'';
    const addr=(f.customer_address||'').trim();
    const phone=String(f.customer_phone||'').trim();
    const setText=function(id,val){ const el=document.getElementById(id); if(el) el.textContent=crmDash(val); };
    setText('aq-crm-customer-name',name);
    setText('aq-crm-meta-name',name);
    setText('aq-crm-meta-address', addr);
    crmSetMetaLink('aq-crm-meta-phone', phone, 'phone');
    setText('aq-crm-meta-contact', f.contact_person || '');
    crmSetMetaLink('aq-crm-meta-email', f.customer_email, 'email');
    const addrEl=document.getElementById('aq-crm-address');
    if(addrEl) addrEl.innerHTML=addr?escapeHtml(addr).replace(/\n/g,'<br>'):'—';
    const phoneLine=document.getElementById('aq-crm-addr-phone');
    if(phoneLine){
      if(phone){ phoneLine.textContent=phone; phoneLine.hidden=false; }
      else { phoneLine.textContent=''; phoneLine.hidden=true; }
    }
    const vLines=[];
    const regModel=[f.vehicle_reg_no,f.model].filter(x=>String(x||'').trim()!=='').join(' · ');
    if(regModel) vLines.push(regModel);
    if(f.vin_no) vLines.push('VIN '+f.vin_no);
    if(f.fleet_no) vLines.push('Fleet '+f.fleet_no);
    if(f.kilometers) vLines.push(String(f.kilometers)+(String(f.kilometers).toUpperCase().indexOf('KM')>=0?'':' KM'));
    if(f.job_no) vLines.push('Job '+f.job_no);
    const qn=String(f.quote_number||'').trim();
    if(qn) vLines.push('Quote '+qn);
    const vWrap=document.getElementById('aq-crm-vehicle-lines');
    const vCard=document.getElementById('aq-crm-vehicle-card');
    if(vWrap){
      if(vLines.length){
        vWrap.innerHTML='<p class="crm-cust-addr-title">Vehicle / Job</p>'+vLines.map(function(line){
          return '<p class="crm-cust-addr-line">'+escapeHtml(line)+'</p>';
        }).join('');
        if(vCard) vCard.hidden=false;
      } else {
        vWrap.innerHTML='<p class="crm-cust-addr-title">Vehicle / Job</p><p class="crm-cust-addr-line crm-cust-addr-line--muted">—</p>';
      }
    }
    const ini=document.getElementById('aq-crm-initials');
    const logoEl=document.getElementById('aq-crm-logo');
    const initials=crmInitials(name);
    if(ini){
      ini.textContent=initials;
      ini.hidden=false;
      ini.removeAttribute('hidden');
    }
    if(logoEl){
      logoEl.setAttribute('aria-label', String(name||'').trim()!=='' ? (String(name).trim()+' ('+initials+')') : 'Customer initials');
      logoEl.removeAttribute('aria-hidden');
    }
    const refEl=document.getElementById('aq-crm-quote-ref');
    if(refEl) refEl.textContent=qn||(PAGE_IS_EDIT&&AQ_REF_LABEL?AQ_REF_LABEL:'New quotation');
    const fileNameEl=document.getElementById('aq-crm-file-name');
    if(fileNameEl && qn) fileNameEl.textContent=qn.replace(/[^\w\-]+/g,'_')+'.pdf';
    const refSum=document.getElementById('aq-crm-summary-ref');
    if(refSum){
      const lbl=qn||(PAGE_IS_EDIT&&AQ_REF_LABEL?AQ_REF_LABEL:'Quotation');
      refSum.textContent=lbl+' · Quotation total';
    }
    const money=function(n){ return SYM+Number(n||0).toFixed(2); };
    ['aq-crm-summary-labour','aq-crm-summary-parts','aq-crm-summary-sub','aq-crm-summary-vat','aq-crm-summary-grand'].forEach(function(id, i){
      const el=document.getElementById(id);
      if(!el) return;
      const vals=[t.lab,t.part,t.sub,t.vat,t.grand];
      el.textContent=money(vals[i]);
    });
    const vatLbl=document.getElementById('aq-crm-summary-vat-label');
    if(vatLbl) vatLbl.textContent='VAT ('+(f.vat_rate||0)+'%):';
    const sub=t.sub||0;
    const labPct=sub>0?Math.round((t.lab/sub)*100):50;
    const partPct=sub>0?100-labPct:50;
    const labBar=document.getElementById('aq-crm-progress-labour');
    const partBar=document.getElementById('aq-crm-progress-parts');
    if(labBar) labBar.style.width=labPct+'%';
    if(partBar) partBar.style.width=partPct+'%';
  }
  function updateBadge(){
    const statusEl=document.getElementById('f-status');
    const st=(statusEl&&statusEl.value)?statusEl.value:'draft';
    const crmBadge=document.getElementById('aq-crm-status-badge');
    const crmMap={draft:['Draft','crm-cust-badge crm-cust-badge--draft'],pending_manager:['Pending Approval','crm-cust-badge crm-cust-badge--pending'],approved:['Approved','crm-cust-badge crm-cust-badge--approved'],rejected:['Rejected','crm-cust-badge crm-cust-badge--rejected'],sent_to_client:['Sent','crm-cust-badge crm-cust-badge--sent'],sent_back_admin:['Needs Review','crm-cust-badge crm-cust-badge--pending']};
    const cL=crmMap[st]||crmMap.draft;
    if(crmBadge){
      crmBadge.textContent=cL[0];
      crmBadge.className=cL[1];
    }
  }
  function updateClientBtn(){
    const btn=document.getElementById('aq-send-client');
    if(!btn) return;
    if(typeof aqIsQuotationRejected==='function'&&aqIsQuotationRejected()){
      btn.disabled=true;
      btn.title='Not available for declined quotations';
      return;
    }
    btn.disabled=!savedId;
    btn.title=!savedId?'Save the quotation first':'Send quotation to client';
  }
  function aqWfPrintKey(){
    return savedId ? ('aq_wf_print_' + savedId) : '';
  }
  function aqMarkPrinted(){
    const k=aqWfPrintKey();
    if(k){ try{ localStorage.setItem(k,'1'); }catch(_){} }
    updateWorkflowUI();
  }
  function aqHasPrintedFlag(){
    const k=aqWfPrintKey();
    if(!k) return false;
    try{ return localStorage.getItem(k)==='1'; }catch(_){ return false; }
  }
  let mqUserPickedTab=false;
  function aqIsQuotationRejected(){
    const statusEl=document.getElementById('f-status');
    const status=(statusEl&&statusEl.value)?String(statusEl.value).toLowerCase():'draft';
    const form=readForm();
    const rejAt=String((form&&form.rejected_at)||'').trim();
    return status==='rejected'||rejAt!=='';
  }
  function aqMqSwitchTab(tabId, fromUser){
    if(fromUser) mqUserPickedTab=true;
    const card=document.getElementById('aq-mq-tabs-card');
    if(!card||!tabId) return;
    if(!card.querySelector('.aq-mq-tab[data-mq-tab="'+tabId+'"]')) tabId=CURRENT_ROLE==='manager'?'finish':'approval';
    card.querySelectorAll('.aq-mq-tab').forEach(function(btn){
      const on=btn.getAttribute('data-mq-tab')===tabId;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on?'true':'false');
    });
    card.querySelectorAll('.aq-mq-tab-panel').forEach(function(panel){
      const on=panel.getAttribute('data-mq-panel')===tabId;
      panel.classList.toggle('is-active', on);
      panel.hidden=!on;
    });
  }
  function aqMqSetApprovalMode(mode){
    const signed=(mode==='signed');
    document.querySelectorAll('.aq-mq-approval-opt').forEach(function(btn){
      const on=btn.getAttribute('data-approval-mode')===(signed?'signed':'declined');
      btn.classList.toggle('is-active', on);
    });
    const paneSigned=document.getElementById('aq-mq-pane-signed');
    const paneDeclined=document.getElementById('aq-mq-pane-declined');
    if(paneSigned){
      paneSigned.classList.toggle('is-active', signed);
      paneSigned.hidden=!signed;
    }
    if(paneDeclined){
      paneDeclined.classList.toggle('is-active', !signed);
      paneDeclined.hidden=signed;
    }
  }
  function aqMqSyncApprovalFromForm(){
    if(aqIsQuotationRejected()) aqMqSetApprovalMode('declined');
    else aqMqSetApprovalMode('signed');
  }
  function aqMqInitTabs(){
    const card=document.getElementById('aq-mq-tabs-card');
    if(!card) return;
    card.querySelectorAll('.aq-mq-tab').forEach(function(btn){
      btn.addEventListener('click', function(){
        aqMqSwitchTab(btn.getAttribute('data-mq-tab'), true);
      });
    });
    document.querySelectorAll('.aq-mq-approval-opt').forEach(function(btn){
      btn.addEventListener('click', function(){
        aqMqSetApprovalMode(btn.getAttribute('data-approval-mode'));
      });
    });
  }
  function aqWorkflowSteps(status, form, clientStatus, printedFlag){
    const at=String((form&&form.approved_at)||'').trim();
    const rejAt=String((form&&form.rejected_at)||'').trim();
    const mgrSent=String((form&&form.manager_review_sent_at)||'').trim()!=='';
    const paperDone=at!=='';
    status=String(status||'draft').toLowerCase();
    clientStatus=String(clientStatus||'').trim();
    const rejected=status==='rejected'||rejAt!=='';
    const printDone=!!printedFlag||paperDone||rejected;
    const approvalDone=paperDone||rejected;
    const sentDone=status==='sent_to_client'||clientStatus==='client_accepted'||clientStatus==='client_rejected';
    const tab_done={approval:approvalDone, finish:sentDone};
    if(rejected){
      return {
        tab_done:tab_done,
        next_label:'Quotation was declined on paper',
        next_hint:'Open Approval to review, or use Edit Quotation to revise.',
        suggested_tab:'approval',
        rejected:true
      };
    }
    let nextLabel='All done for this quotation';
    let nextHint='You can still edit details or create an invoice if needed.';
    let suggested_tab='finish';
    if(!mgrSent){
      suggested_tab='approval';
      nextLabel='Send to manager for review';
      nextHint='Click Send to manager for review so they can preview the PDF before you print for paper sign-off.';
    }else if(!printDone){
      suggested_tab='approval';
      nextLabel='Print the quotation';
      nextHint='Open Actions → Document, use Preview or Print Blank, then take the copy to the manager for paper signature.';
    }else if(!paperDone){
      suggested_tab='finish';
      nextLabel='Create invoice or record sign-off';
      nextHint='You can create an invoice from Finish now, or open Approval to record manager paper sign-off when available.';
    }else if(!sentDone){
      suggested_tab='finish';
      nextLabel='Send to client or create invoice';
      nextHint='Use Finish when you are ready to send or invoice.';
    }
    return {tab_done:tab_done,next_label:nextLabel,next_hint:nextHint,suggested_tab:suggested_tab,rejected:false};
  }
  function updateWorkflowUI(){
    const card=document.getElementById('aq-mq-tabs-card');
    if(!card) return;
    const statusEl=document.getElementById('f-status');
    const status=(statusEl&&statusEl.value)?statusEl.value:'draft';
    const wf=aqWorkflowSteps(status, readForm(), INITIAL_CLIENT_STATUS, aqHasPrintedFlag());
    const nextEl=document.getElementById('aq-workflow-next');
    if(nextEl){
      nextEl.classList.toggle('is-rejected', !!wf.rejected);
      nextEl.innerHTML='<i class="fas fa-arrow-circle-right" aria-hidden="true"></i><p class="aq-mq-next-text"><strong>Next:</strong> '+escapeHtml(wf.next_label)+'</p>';
      nextEl.hidden=false;
    }
    card.querySelectorAll('.aq-mq-tab').forEach(function(btn){
      const key=btn.getAttribute('data-mq-tab');
      if(key&&wf.tab_done) btn.classList.toggle('is-done', !!wf.tab_done[key]);
    });
    const notice=document.getElementById('aq-mq-finish-notice');
    if(notice) notice.hidden=!wf.rejected;
    if(wf.rejected){
      const sendBtn=document.getElementById('aq-send-client');
      const invBtn=document.getElementById('aq-create-invoice');
      const poBtn=document.getElementById('aq-add-po');
      if(sendBtn){ sendBtn.disabled=true; sendBtn.title='Not available for declined quotations'; }
      if(invBtn){ invBtn.disabled=true; invBtn.title='Not available for declined quotations'; }
      if(poBtn) poBtn.disabled=true;
    }else{
      const poBtn=document.getElementById('aq-add-po');
      if(poBtn) poBtn.disabled=!savedId;
      updateClientBtn();
      updateInvoiceBtn();
    }
    if(!mqUserPickedTab&&wf.suggested_tab) aqMqSwitchTab(wf.suggested_tab, false);
    aqMqSyncApprovalFromForm();
  }
  function aqTodayDateStr(){
    const d=new Date();
    const p=function(n){ return String(n).padStart(2,'0'); };
    return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate());
  }
  function updateManagerReviewBadge(){
    const badge=document.getElementById('aq-mgr-review-status-badge');
    const meta=document.getElementById('aq-mgr-review-sent-meta');
    const btn=document.getElementById('aq-send-manager-review');
    const sentAt=v('f-manager_review_sent_at');
    const sentBy=v('f-manager_review_sent_by');
    if(!badge) return;
    const viewedAt=v('f-manager_review_viewed_at');
    if(sentAt){
      badge.textContent=viewedAt ? 'Manager viewed' : 'Sent for review';
      badge.className='aq-paper-status-badge aq-paper-status-badge--'+(viewedAt?'signed':'sent');
      if(meta){
        meta.hidden=false;
        var viewed=v('f-manager_review_viewed_at');
        meta.textContent='Last sent '+sentAt+(sentBy ? ' by '+sentBy : '')+(viewed ? ' · Manager viewed '+viewed : '')+'. You can send again after changes.';
      }
      if(btn) btn.innerHTML='<i class="fas fa-paper-plane"></i> Send again to manager';
    }else{
      badge.textContent='Not sent to manager';
      badge.className='aq-paper-status-badge aq-paper-status-badge--pending';
      if(meta) meta.hidden=true;
      if(btn) btn.innerHTML='<i class="fas fa-paper-plane"></i> Send to manager for review';
    }
  }
  function sendToManagerReview(){
    const btn=document.getElementById('aq-send-manager-review');
    if(btn) btn.disabled=true;
    return saveQuotation({
      redirectAfterSave:false,
      successMessage:'',
      showSaveButtonState:false,
      showErrorAlert:true
    }).then(function(){
      if(!savedId) throw new Error('Save the quotation first.');
      const fd=new FormData();
      fd.append('_action','send_to_manager_review');
      fd.append('quote_id', String(savedId));
      return fetch(location.pathname, { method:'POST', body:fd });
    }).then(async function(r){
      const raw=await r.text();
      let j=null;
      try{ j=JSON.parse(raw); }catch(_){ j=null; }
      if(!j||!j.ok) throw new Error((j&&j.error)||raw||'Send failed');
      const sentAt=String(j.sent_at||'');
      const sentBy=String(j.sent_by||'');
      const atEl=document.getElementById('f-manager_review_sent_at');
      const byEl=document.getElementById('f-manager_review_sent_by');
      const stEl=document.getElementById('f-status');
      if(atEl) atEl.value=sentAt;
      if(byEl) byEl.value=sentBy;
      if(stEl) stEl.value='pending_manager';
      updateManagerReviewBadge();
      updateWorkflowUI();
      updateBadge();
      const body=j.notified>0
        ? 'The quotation was sent for manager review. '+j.notified+' notification(s) were delivered to manager accounts.'
        : 'The quotation was sent for manager review. No manager user accounts were found to notify — managers can still open it from their quotation list once they have access.';
      showAqMgrSendModal({ ok:true, title:'Sent for manager preview', body:body });
      return j;
    }).catch(function(err){
      const msg=err&&err.message?err.message:'Send failed';
      showAqMgrSendModal({ ok:false, title:'Could not send', body:msg });
      throw err;
    }).finally(function(){
      if(btn) btn.disabled=!savedId;
    });
  }
  function updatePaperSignoffBadge(){
    const badge=document.getElementById('aq-paper-status-badge');
    const atEl=document.getElementById('f-approved_at');
    if(!badge||!atEl) return;
    const at=(atEl.value||'').trim();
    if(at){
      badge.textContent='Paper signed · '+at;
      badge.className='aq-paper-status-badge aq-paper-status-badge--signed';
    }else{
      badge.textContent='Not signed yet';
      badge.className='aq-paper-status-badge aq-paper-status-badge--pending';
    }
    updateWorkflowUI();
    updateInvoiceBtn();
    if(typeof scheduleLiveDocPreview==='function'){
      try{ scheduleLiveDocPreview(); }catch(err){ console.error('Preview sync failed', err); }
    }
  }
  function aqRejectDateOnly(raw){
    const s=String(raw||'').trim();
    if(!s) return '';
    if(/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0,10);
    const d=new Date(s);
    if(isNaN(d.getTime())) return s;
    const p=function(n){ return String(n).padStart(2,'0'); };
    return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate());
  }
  function updatePaperRejectionBadge(){
    const badge=document.getElementById('aq-rejection-status-badge');
    const atEl=document.getElementById('f-rejected_at');
    if(!badge||!atEl) return;
    const at=(atEl.value||'').trim();
    if(at){
      badge.textContent='Declined · '+at;
      badge.className='aq-paper-status-badge aq-paper-status-badge--rejected';
    }else{
      badge.textContent='Not declined';
      badge.className='aq-paper-status-badge aq-paper-status-badge--pending';
    }
    updateWorkflowUI();
    updateInvoiceBtn();
  }
  function savePaperSignoff(){
    if(!savedId){
      showAqSignoffModal({ ok:false, title:'Save quotation first', body:'Save this quotation before recording paper sign-off.' });
      return Promise.reject();
    }
    const atEl=document.getElementById('f-approved_at');
    const byEl=document.getElementById('f-approved_by');
    const at=atEl?(atEl.value||'').trim():'';
    const by=byEl?(byEl.value||'').trim():'';
    if(!at){
      showAqSignoffModal({
        ok:false,
        title:'Date required',
        body:'Enter the sign-off date from the manager\'s signed printout.',
        hintHtml:'<strong>Before saving</strong> — Print the quotation, have the manager sign on paper, then enter the date and name from that signed copy.'
      });
      return Promise.reject();
    }
    const fd=new FormData();
    fd.append('_action','save_paper_signoff');
    fd.append('quote_id', String(savedId));
    fd.append('paper_signed_at', at);
    fd.append('paper_signed_by', by);
    const btn=document.getElementById('aq-save-paper-signoff');
    if(btn) btn.disabled=true;
    return fetch(location.pathname, { method:'POST', body:fd }).then(async function(r){
      const raw=await r.text();
      let j=null;
      try{ j=JSON.parse(raw); }catch(_){ j=null; }
      if(!j||!j.ok) throw new Error((j&&j.error)||'Could not save sign-off');
      const stEl=document.getElementById('f-status');
      if(stEl) stEl.value='approved';
      document.getElementById('f-rejected_at').value='';
      document.getElementById('f-rejected_by').value='';
      document.getElementById('f-rejection_reason').value='';
      updateBadge();
      updatePaperSignoffBadge();
      updatePaperRejectionBadge();
      var detail='Paper sign-off saved for '+at+(by ? ' ('+by+')' : '')+'.';
      showAqSignoffModal({
        ok:true,
        variant:'signoff',
        body:detail+' This quotation is marked approved in the system.'
      });
      return j;
    }).catch(function(err){
      const msg=err&&err.message?err.message:'Save failed';
      showAqSignoffModal({ ok:false, variant:'signoff', body:msg });
      throw err;
    }).finally(function(){
      if(btn) btn.disabled=false;
    });
  }
  function savePaperRejection(){
    if(!savedId){
      showAqSignoffModal({ ok:false, variant:'rejection', title:'Save quotation first', body:'Save this quotation before recording a paper rejection.' });
      return Promise.reject();
    }
    const atEl=document.getElementById('f-rejected_at');
    const byEl=document.getElementById('f-rejected_by');
    const reasonEl=document.getElementById('f-rejection_reason');
    const at=atEl?(atEl.value||'').trim():'';
    const by=byEl?(byEl.value||'').trim():'';
    const reason=reasonEl?(reasonEl.value||'').trim():'';
    if(!at){
      showAqSignoffModal({
        ok:false,
        variant:'rejection',
        title:'Date required',
        body:'Enter the date the manager declined on paper.',
        hint:false
      });
      return Promise.reject();
    }
    if(!reason){
      showAqSignoffModal({
        ok:false,
        variant:'rejection',
        title:'Reason required',
        body:'Enter why the manager declined the quotation on paper.',
        hint:false
      });
      return Promise.reject();
    }
    const fd=new FormData();
    fd.append('_action','save_paper_rejection');
    fd.append('quote_id', String(savedId));
    fd.append('rejected_at', at);
    fd.append('rejected_by', by);
    fd.append('rejection_reason', reason);
    const btn=document.getElementById('aq-save-paper-rejection');
    if(btn) btn.disabled=true;
    return fetch(location.pathname, { method:'POST', body:fd }).then(async function(r){
      const raw=await r.text();
      let j=null;
      try{ j=JSON.parse(raw); }catch(_){ j=null; }
      if(!j||!j.ok) throw new Error((j&&j.error)||'Could not save rejection');
      const stEl=document.getElementById('f-status');
      if(stEl) stEl.value='rejected';
      document.getElementById('f-approved_at').value='';
      document.getElementById('f-approved_by').value='';
      mqUserPickedTab=false;
      updateBadge();
      updatePaperSignoffBadge();
      updatePaperRejectionBadge();
      showAqSignoffModal({
        ok:true,
        variant:'rejection',
        body:'Decline recorded for '+at+(by ? ' ('+by+')' : '')+'. Status is marked rejected.'
      });
      return j;
    }).catch(function(err){
      const msg=err&&err.message?err.message:'Save failed';
      showAqSignoffModal({ ok:false, variant:'rejection', body:msg });
      throw err;
    }).finally(function(){
      if(btn) btn.disabled=false;
    });
  }

  function syncQuoteHeading(){
    const th=document.getElementById('aq-title-heading');
    if(!th) return;
    th.textContent=PAGE_IS_EDIT?'Manage Quotation':'Create Quotation';
  }

  function fillJobSelect(){
    const sel=document.getElementById('aq-job-card');
    if(!sel) return;
    sel.innerHTML='<option value="">— Select Job Card —</option>';
    JOB_OPTIONS.forEach(j=>{
      const t=(j.card_number||'')+' | '+(j.client_name||'No customer')+' | '+(j.reg_no||'')+' | ';
      sel.insertAdjacentHTML('beforeend','<option value="'+j.id+'">'+escapeHtml(t)+'</option>');
    });
  }

  function populateFromJC(id){
    const j=JOB_OPTIONS.find(x=>String(x.id)===String(id)); if(!j) return;
    let xd={}; try{ xd=(typeof j.extra_data==='object'&&j.extra_data!==null)?j.extra_data:JSON.parse((j.extra_data&&String(j.extra_data))||'{}'); }catch(_){ xd={}; }
    if(j.client_name!==undefined){ document.getElementById('f-customer_name').value=j.client_name||''; }
    if(j.client_address!==undefined){ splitCustomerAddressIntoFields(j.client_address||''); }
    if(j.client_email!==undefined){ document.getElementById('f-customer_email').value=j.client_email||''; }
    if(j.client_phone!==undefined){ document.getElementById('f-customer_phone').value=j.client_phone||''; }
    if(j.client_contact_person!==undefined){ document.getElementById('f-contact_person').value=j.client_contact_person||''; }
    if(j.jc_created_at){ document.getElementById('f-date').value=String(j.jc_created_at).slice(0,10).replace(/T.*/,''); }
    document.getElementById('f-vehicle_reg_no').value=j.reg_no||'';
    document.getElementById('f-model').value=j.model||'';
    document.getElementById('f-vin_no').value=j.vin_no||'';
    document.getElementById('f-job_no').value=j.card_number||'';
    if(xd){
      const kmVal = (xd.kilometre !== undefined && xd.kilometre !== null && xd.kilometre !== '')
        ? xd.kilometre
        : xd.kilometers;
      if(kmVal !== undefined && kmVal !== null && String(kmVal) !== ''){
        document.getElementById('f-kilometers').value=kmVal;
      }
      if(xd.contact_no !== undefined && xd.contact_no !== null && String(xd.contact_no) !== ''){
        document.getElementById('f-customer_phone').value=xd.contact_no;
      }
      if(xd.contact_person !== undefined && xd.contact_person !== null && String(xd.contact_person) !== ''){
        document.getElementById('f-contact_person').value=xd.contact_person;
      }
    }
    if(xd&&xd.fleet_no) document.getElementById('f-fleet_no').value=xd.fleet_no;
    if(xd&&xd.purchase_order) document.getElementById('f-purchase_order').value=xd.purchase_order;
    if(xd && Array.isArray(xd.description_lines)) {
      xd.description_lines.forEach(function(line, i) {
        if(line && line.trim() !== '' && labourRows[i]) {
          labourRows[i].description = line.trim();
        }
      });
      syncLabourDom();
    }
    scheduleLiveDocPreview();
  }

  function resetRows(ntRate){
    const r=+(ntRate||560);
    labourRows=Array.from({length:DEFAULT_LABOUR},(_,i)=>makeLab(i)); labourRows[0].rate=r;
    syncLabourDom();
    partsRows=Array.from({length:DEFAULT_PARTS},(_,i)=>makePart('parts_supply',i));
    consRows=Array.from({length:DEFAULT_CONSUMABLES},(_,i)=>makePart('consumables',i));
    syncPartsDom();
  }

  function initDefaults(){
    const today=new Date().toISOString().slice(0,10);
    document.getElementById('f-date').value=today;
    document.getElementById('f-quote_number').value=NEXT_QUOTE||('QT-TEMP'); 
    document.getElementById('f-normal_time_rate').value=560;
    document.getElementById('f-overtime_rate').value=0;
    document.getElementById('f-public_holiday_rate').value=1500;
    document.getElementById('f-vat_rate').value=15;
    aqApplyHeaderLabelsFromForm({general_header_labels:['Attend to service','Diagnostic']});
    document.getElementById('f-show_normal_time').checked=true;
    ['f-print_blank_show_labour','f-print_blank_show_parts','f-blank_col_lab_hours','f-blank_col_lab_rate','f-blank_col_lab_total','f-blank_col_part_qty','f-blank_col_part_cost','f-blank_col_part_total'].forEach(function(id){ const z=document.getElementById(id); if(z&&z.type==='checkbox') z.checked=true; });
    document.getElementById('f-blank_normal_lines').value='5';
    document.getElementById('f-blank_overtime_lines').value='5';
    document.getElementById('f-blank_holiday_lines').value='5';
    const elPartsTop=document.getElementById('f-blank_parts_top_lines');
    if(elPartsTop) elPartsTop.value='2';
    document.getElementById('f-blank_parts_lines').value='7';
    document.getElementById('f-blank_cons_lines').value='1';
    resetRows(560);
    document.getElementById('f-status').value='draft';
    document.getElementById('f-approved_at').value=today;
    document.getElementById('f-approved_by').value='';
    document.getElementById('f-rejected_at').value=today;
    document.getElementById('f-rejected_by').value='';
    document.getElementById('f-rejection_reason').value='';
    document.getElementById('f-sent_back_at').value='';
    document.getElementById('f-sent_back_by').value='';
    document.getElementById('f-sent_back_reason').value='';
    syncQuoteHeading();
  }

  try {
  if(EDIT_PAYLOAD && EDIT_PAYLOAD.form){
    const f=EDIT_PAYLOAD.form;
    ['quote_number','customer_name','customer_email','customer_phone','contact_person','date','vehicle_reg_no','vin_no','fleet_no','model','kilometers','job_no','purchase_order','approved_at','approved_by','rejected_at','rejected_by','rejection_reason','sent_back_at','sent_back_by','sent_back_reason','manager_review_sent_at','manager_review_sent_by','manager_review_viewed_at'].forEach(k=>{
      const el=document.getElementById('f-'+k); if(el && f[k]!==undefined) el.value=f[k]; });
    (function(){
      const ap=document.getElementById('f-approved_at');
      if(ap&&ap.type==='date'&&!(String(ap.value||'').trim())) ap.value=new Date().toISOString().slice(0,10);
    })();
    if(f.customer_address!==undefined){ splitCustomerAddressIntoFields(f.customer_address); }
    ['normal_time_rate','overtime_rate','public_holiday_rate','vat_rate','blank_normal_lines','blank_overtime_lines','blank_holiday_lines','blank_parts_top_lines','blank_parts_lines','blank_cons_lines'].forEach(k=>{const el=document.getElementById('f-'+k); if(el && f[k]!==undefined) el.value=String(f[k]);});
    function _aqSetChecked(fid, val){
      const el=document.getElementById(fid);
      if(el && el.type==='checkbox') el.checked=!!val;
    }
    _aqSetChecked('f-show_normal_time', f.show_normal_time);
    _aqSetChecked('f-show_overtime', f.show_overtime);
    _aqSetChecked('f-show_public_holiday', f.show_public_holiday);
    function _aqCk(fid, oval, def){ const el=document.getElementById(fid); if(el&&el.type==='checkbox') el.checked=(oval===undefined||oval===null)?def:(!!oval||oval===1||oval==='1'); }
    _aqCk('f-print_blank_show_labour', f.print_blank_show_labour, true);
    _aqCk('f-print_blank_show_parts', f.print_blank_show_parts, true);
    _aqCk('f-blank_col_lab_hours', f.blank_col_lab_hours, true);
    _aqCk('f-blank_col_lab_rate', f.blank_col_lab_rate, true);
    _aqCk('f-blank_col_lab_total', f.blank_col_lab_total, true);
    _aqCk('f-blank_col_part_qty', f.blank_col_part_qty, true);
    _aqCk('f-blank_col_part_cost', f.blank_col_part_cost, true);
    _aqCk('f-blank_col_part_total', f.blank_col_part_total, true);
    const statusEl=document.getElementById('f-status');
    if(statusEl) statusEl.value=f.status||'draft';
    const rejAtEl=document.getElementById('f-rejected_at');
    if(rejAtEl&&f.rejected_at) rejAtEl.value=aqRejectDateOnly(f.rejected_at);
    labourRows=(EDIT_PAYLOAD.labour_rows&&EDIT_PAYLOAD.labour_rows.length)?EDIT_PAYLOAD.labour_rows:[];
    partsRows=(EDIT_PAYLOAD.parts_rows&&EDIT_PAYLOAD.parts_rows.length)?EDIT_PAYLOAD.parts_rows:[];
    consRows=(EDIT_PAYLOAD.cons_rows&&EDIT_PAYLOAD.cons_rows.length)?EDIT_PAYLOAD.cons_rows:[];
    if(!labourRows.length){
      labourRows=Array.from({length:DEFAULT_LABOUR},(_,i)=>makeLab(i));
      labourRows[0].rate=vn('f-normal_time_rate')||560;
    }
    if(!partsRows.length) partsRows=Array.from({length:DEFAULT_PARTS},(_,i)=>makePart('parts_supply',i));
    if(!consRows.length) consRows=Array.from({length:DEFAULT_CONSUMABLES},(_,i)=>makePart('consumables',i));
    syncLabourDom(); syncPartsDom();
    aqApplyHeaderLabelsFromForm(f);
    syncQuoteHeading();
  } else initDefaults();
  } catch(initErr) {
    console.error('Quotation form init failed', initErr);
    if(PAGE_IS_EDIT && EDIT_PAYLOAD && EDIT_PAYLOAD.form){
      try{
        const f2=EDIT_PAYLOAD.form;
        ['quote_number','customer_name','date','job_no'].forEach(function(k){
          const el=document.getElementById('f-'+k);
          if(el && f2[k]!==undefined) el.value=f2[k];
        });
        if(f2.customer_address!==undefined) splitCustomerAddressIntoFields(f2.customer_address);
        labourRows=(EDIT_PAYLOAD.labour_rows&&EDIT_PAYLOAD.labour_rows.length)?EDIT_PAYLOAD.labour_rows:[];
        partsRows=(EDIT_PAYLOAD.parts_rows&&EDIT_PAYLOAD.parts_rows.length)?EDIT_PAYLOAD.parts_rows:[];
        consRows=(EDIT_PAYLOAD.cons_rows&&EDIT_PAYLOAD.cons_rows.length)?EDIT_PAYLOAD.cons_rows:[];
        syncLabourDom(); syncPartsDom();
      }catch(recoverErr){ console.error('Quotation recovery init failed', recoverErr); }
    }
  }
  function fillMissingContactFromJC(id){
    const j=JOB_OPTIONS.find(x=>String(x.id)===String(id)); if(!j) return;
    let xd={}; try{ xd=(typeof j.extra_data==='object'&&j.extra_data!==null)?j.extra_data:JSON.parse((j.extra_data&&String(j.extra_data))||'{}'); }catch(_){ xd={}; }
    const phoneEl=document.getElementById('f-customer_phone');
    const emailEl=document.getElementById('f-customer_email');
    const contactEl=document.getElementById('f-contact_person');
    if(phoneEl && String(phoneEl.value||'').trim()===''){
      phoneEl.value=String(xd.contact_no||j.client_phone||'');
    }
    if(emailEl && String(emailEl.value||'').trim()===''){
      emailEl.value=String(j.client_email||'');
    }
    if(contactEl && String(contactEl.value||'').trim()===''){
      contactEl.value=String(xd.contact_person||j.client_contact_person||'');
    }
  }

  function aqRegisterOpenPrint(){
    window.AQ._realOpenPrint=openPrint;
    window.AQ._realOpenSidePreview=function(blank){
      const isBlank=!!blank;
      const toolBtn=document.getElementById(isBlank?'aq-btn-print-blank':'aq-btn-preview');
      setDocToolActive(toolBtn);
      return openSidePreview(isBlank, isBlank?'Print Blank':'Preview');
    };
    window.AQ._useSidePreview=USE_SIDE_PREVIEW;
    window.AQ._openPrintReady=true;
    window.AQ.openPrint=function(blank){
      if(USE_SIDE_PREVIEW) return openSidePreview(!!blank);
      return openPrint(!!blank);
    };
    window.aqDoPreview=window.AQ.openPrint;
    if(!window.AQ._printShellWired){
      window.AQ._printShellWired=true;
      const closePrintBtn=document.getElementById('aq-close-print');
      const doPrintBtn=document.getElementById('aq-do-print');
      const savePdfBottom=document.getElementById('aq-save-pdf-bottom');
      if(closePrintBtn){
        closePrintBtn.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          closePrint();
        });
      }
      if(doPrintBtn){
        doPrintBtn.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          runQuotationPrintPreview();
        });
      }
      if(savePdfBottom){
        savePdfBottom.addEventListener('click', function(e){
          e.preventDefault();
          e.stopPropagation();
          runQuotationPrintPreview();
        });
      }
    }
    if(window.AQ_PENDING_PREVIEW!==undefined){
      if(USE_SIDE_PREVIEW) openSidePreview(!!window.AQ_PENDING_PREVIEW, window.AQ_PENDING_PREVIEW ? 'Print Blank' : 'Preview');
      else openPrint(!!window.AQ_PENDING_PREVIEW);
      delete window.AQ_PENDING_PREVIEW;
    }
  }
  aqRegisterOpenPrint();

  fillJobSelect();
  <?php if ($forcedJobCard): ?>
    (function(){ const jc=document.getElementById('aq-job-card'); if(jc) jc.value=<?php echo (int)$forcedJobCard; ?>; })();
          <?php endif; ?>
  const _hadEdit=!!(EDIT_PAYLOAD&&EDIT_PAYLOAD.form);
  (function(){
    const jc=document.getElementById('aq-job-card');
    if(!_hadEdit && jc && jc.value) populateFromJC(jc.value);
    if(_hadEdit && jc && jc.value) fillMissingContactFromJC(jc.value);
  })();
  const saveBtn=document.getElementById('aq-save');
  const addLabBtn=document.getElementById('aq-add-labour');
  const addPartsBtn=document.getElementById('aq-add-parts');
  const addConsBtn=document.getElementById('aq-add-cons');
  function setManagerReadOnly(){
    document.querySelectorAll('input,select,textarea,button').forEach(function(el){
      if(!el) return;
      const keep=['aq-btn-preview','aq-btn-print-blank','aq-set-quote-number','aq-do-print','aq-close-print','aq-save-pdf-bottom','aq-side-download','aq-side-print'];
      if(el.id && keep.indexOf(el.id)>=0) return;
      if(el.id==='aq-save' || el.id==='aq-send-client' || el.id==='aq-create-invoice' || el.id==='aq-add-labour' || el.id==='aq-add-parts' || el.id==='aq-add-cons'){
        el.style.display='none';
        return;
      }
      if(el.classList.contains('lab-del') || el.classList.contains('pp-del')){
        el.style.display='none';
        return;
      }
      if(el.type==='checkbox' || el.type==='radio' || el.tagName==='SELECT' || el.tagName==='TEXTAREA' || el.type==='number' || el.type==='text' || el.type==='date'){
        el.disabled=true;
        el.readOnly=true;
      }
    });
  }
  if(CURRENT_ROLE==='manager'){
    if(saveBtn) saveBtn.style.display='none';
    if(addLabBtn) addLabBtn.style.display='none';
    if(addPartsBtn) addPartsBtn.style.display='none';
    if(addConsBtn) addConsBtn.style.display='none';
    setManagerReadOnly();
  }

  const jobCardSel=document.getElementById('aq-job-card');
  if(jobCardSel){
    jobCardSel.onchange=function(e){
      if(e.target.value) populateFromJC(e.target.value);
    };
  }

  if(addLabBtn) { addLabBtn.onclick = () => { labourRows.push(makeLab(labourRows.length)); syncLabourDom(); calc(); }; }
  if(addPartsBtn) { addPartsBtn.onclick = () => { partsRows.push(makePart('parts_supply',partsRows.length)); syncPartsDom(); calc(); }; }
  if(addConsBtn) { addConsBtn.onclick = () => { consRows.push(makePart('consumables',consRows.length)); syncPartsDom(); calc(); }; }

  ['f-vat_rate','f-show_normal_time','f-show_overtime','f-show_public_holiday'].forEach(id=>{
    const el=document.getElementById(id); if(!el)return; el.addEventListener(el.type==='checkbox'?'change':'input',calc);
  });

  const toggleRatesBtn=document.getElementById('aq-toggle-rates');
  if(toggleRatesBtn){
    toggleRatesBtn.onclick=function(){
      const panel=document.getElementById('aq-rates-panel');
      const chev=document.getElementById('aq-chevron');
      if(panel) panel.classList.toggle('hidden');
      if(chev) chev.classList.toggle('rotate-180');
    };
  }

  function collectPayload(){
    const selectedJc=parseInt(document.getElementById('aq-job-card').value||0,10)||0;
    const fallbackJc=parseInt(FORCED_JOB_CARD||0,10)||0;
    return { saved_id:savedId, job_card_id:(selectedJc||fallbackJc), form:readForm(),
      labour_rows:labourRows, parts_rows:partsRows, cons_rows:consRows };
  }

  function saveQuotation(opts){
    const cfg=Object.assign({
      successMessage:'Quotation saved successfully!',
      redirectAfterSave:true,
      showSaveButtonState:true,
      showErrorAlert:true
    }, opts||{});
    const fd=new FormData();
    fd.append('_action','save_quotation_json');
    fd.append('q_json', JSON.stringify(collectPayload()));
    return fetch(location.pathname, { method:'POST', body:fd }).then(async function(r){
      const raw = await r.text();
      let j = null;
      try { j = JSON.parse(raw); } catch(_){ j = null; }
      if(!j || typeof j !== 'object'){
        throw new Error(raw && raw.trim() ? raw : 'Unexpected server response.');
      }
      if(!j.ok) throw new Error(j.error || 'Save failed');
      savedId=j.id;
      updateClientBtn();
      if(typeof updateInvoiceBtn==='function') updateInvoiceBtn();
      if(cfg.successMessage){
        flash(cfg.successMessage);
        showToast(cfg.successMessage, false);
      }
      if(cfg.showSaveButtonState){
        const saveLabel=document.getElementById('aq-save-label');
        if(saveLabel){
          saveLabel.textContent='Quotation Saved';
          setTimeout(function(){ saveLabel.textContent='Save'; }, 1500);
        }
      }
      if(cfg.redirectAfterSave){
        const u=new URL(location.href); u.searchParams.delete('error');
        setTimeout(function(){
          location.replace(u.pathname+'?id='+j.id+(u.hash||''));
        }, 1800);
      }
      return j;
    }).catch(function(err){
      showToast(err && err.message ? err.message : 'Save failed', true);
      if(cfg.showErrorAlert){
        alert(err && err.message ? err.message : 'Save failed');
      }
      throw err;
    });
  }

  if(saveBtn) { saveBtn.onclick = function(){ saveQuotation(); }; }
  const savePaperBtn=document.getElementById('aq-save-paper-signoff');
  if(savePaperBtn){ savePaperBtn.onclick=function(){ savePaperSignoff(); }; }
  const saveRejectBtn=document.getElementById('aq-save-paper-rejection');
  if(saveRejectBtn){ saveRejectBtn.onclick=function(){ savePaperRejection(); }; }
  const sendMgrReviewBtn=document.getElementById('aq-send-manager-review');
  if(sendMgrReviewBtn){ sendMgrReviewBtn.onclick=function(){ sendToManagerReview(); }; }
  ['f-approved_at','f-approved_by'].forEach(function(id){
    const el=document.getElementById(id);
    if(el) el.addEventListener('change', updatePaperSignoffBadge);
  });

  function askQuoteNumber(initialValue){
    return new Promise(function(resolve){
      const modal=document.getElementById('aq-quote-no-modal');
      const input=document.getElementById('aq-quote-no-input');
      const ok=document.getElementById('aq-quote-no-ok');
      const cancel=document.getElementById('aq-quote-no-cancel');
      if(!modal||!input||!ok||!cancel){ resolve(null); return; }
      let done=false;
      function close(val){
        if(done) return;
        done=true;
        modal.classList.remove('show');
        ok.onclick=null; cancel.onclick=null; modal.onclick=null; input.onkeydown=null;
        resolve(val);
      }
      input.value=initialValue||'';
      modal.classList.add('show');
      setTimeout(function(){ input.focus(); input.select(); }, 10);
      ok.onclick=function(){ close(String(input.value||'').trim()); };
      cancel.onclick=function(){ close(null); };
      modal.onclick=function(e){ if(e.target===modal) close(null); };
      input.onkeydown=function(e){
        if(e.key==='Enter'){ e.preventDefault(); close(String(input.value||'').trim()); }
        if(e.key==='Escape'){ e.preventDefault(); close(null); }
      };
    });
  }

  async function openQuoteNumberModal(){
    const qnEl=document.getElementById('f-quote_number');
    const current=String((qnEl&&qnEl.value)||'').trim();
    const next=await askQuoteNumber(current);
    if(next===null) return;
    const normalized=String(next||'').trim();
    if(!normalized) return alert('Quote number is required.');
    if(!/^[A-Za-z0-9][A-Za-z0-9\-\/]*$/.test(normalized)){
      return alert('Use letters, numbers, dash or slash only.');
    }
    if(qnEl) qnEl.value=normalized;
    const heading=document.getElementById('aq-title-heading');
    if(heading) heading.textContent=(PAGE_IS_EDIT?'Manage Quotation':'Create Quotation')+' — '+normalized;
    saveQuotation({
      successMessage:'Quote number updated successfully!',
      redirectAfterSave:false,
      showSaveButtonState:false
    }).catch(function(){});
  }
  window.AQ.openQuoteNumberModal=openQuoteNumberModal;
  const setQuoteBtn=document.getElementById('aq-set-quote-number');
  if(setQuoteBtn) setQuoteBtn.onclick=function(){ setDocToolActive(setQuoteBtn); openQuoteNumberModal(); };

  function flash(t){ const m=document.getElementById('aq-status-msg'); m.textContent=t; m.classList.remove('aq-s1-flash-hidden'); setTimeout(()=>m.classList.add('aq-s1-flash-hidden'),3000); }
  function showToast(message, isError){
    const old=document.getElementById('aq-toast-msg');
    if(old) old.remove();
    const t=document.createElement('div');
    t.id='aq-toast-msg';
    t.textContent=message;
    t.style.position='fixed';
    t.style.right='18px';
    t.style.bottom='18px';
    t.style.zIndex='99999';
    t.style.padding='10px 14px';
    t.style.borderRadius='10px';
    t.style.fontSize='14px';
    t.style.fontWeight='700';
    t.style.color='#fff';
    t.style.background=isError?'#dc2626':'#16a34a';
    t.style.boxShadow='0 8px 24px rgba(0,0,0,.2)';
    document.body.appendChild(t);
    setTimeout(function(){ if(t&&t.parentNode) t.parentNode.removeChild(t); }, 2200);
  }
  function showMessageModal(message, title){
    showAqNotice(message, title);
  }
  window.alert=function(message){ showMessageModal(message,'Notice'); };

  const deleteQuotationBtn=document.getElementById('aq-delete-quotation');
  const deleteQuotationModal=document.getElementById('aq-delete-modal');
  const deleteQuotationCancel=document.getElementById('aq-delete-cancel');
  if(deleteQuotationBtn && deleteQuotationModal){
    deleteQuotationBtn.onclick=function(){ deleteQuotationModal.classList.add('show'); };
    deleteQuotationModal.onclick=function(e){
      if(e.target===deleteQuotationModal) deleteQuotationModal.classList.remove('show');
    };
    console.log('✓ Delete Quotation button handler attached');
  } else {
    console.warn('Delete Quotation button not found (may be hidden for non-admins)');
  }
  if(deleteQuotationCancel && deleteQuotationModal){
    deleteQuotationCancel.onclick=function(){ deleteQuotationModal.classList.remove('show'); };
  }

  const addPOBtn=document.getElementById('aq-add-po');
  if(addPOBtn){
    console.log('✓ Add Purchase Order button found');
    addPOBtn.onclick=function(){
      const fd=readForm();
      const modal=document.getElementById('aq-po-modal');
      const input=document.getElementById('aq-po-input');
      const ok=document.getElementById('aq-po-ok');
      const cancel=document.getElementById('aq-po-cancel');
      
      if(!modal || !input || !ok || !cancel) return;
      
      input.value=fd.purchase_order || '';
      modal.classList.add('show');
      setTimeout(function(){ input.focus(); },100);
      
      ok.onclick=function(){
        const po=input.value.trim();
        if(po!==''){
          document.getElementById('f-purchase_order').value=po;
          modal.classList.remove('show');
          setTimeout(function(){
            showMessageModal('Purchase Order number has been saved successfully.','Purchase Order');
          },200);
        } else {
          modal.classList.remove('show');
        }
        ok.onclick=null;
        cancel.onclick=null;
        modal.onclick=null;
        input.onkeydown=null;
      };
      
      cancel.onclick=function(){
        modal.classList.remove('show');
        ok.onclick=null;
        cancel.onclick=null;
        modal.onclick=null;
        input.onkeydown=null;
      };
      
      modal.onclick=function(e){
        if(e.target===modal){
          cancel.onclick();
        }
      };
      
      input.onkeydown=function(e){
        if(e.key==='Enter'){
          e.preventDefault();
          ok.onclick();
        } else if(e.key==='Escape'){
          e.preventDefault();
          cancel.onclick();
        }
      };
    };
  }

  function updateInvoiceBtn(){
    const btn=document.getElementById('aq-create-invoice');
    if(!btn) return;
    if(typeof aqIsQuotationRejected==='function'&&aqIsQuotationRejected()){
      btn.disabled=true;
      btn.title='Not available for declined quotations';
      return;
    }
    btn.disabled=!savedId;
    btn.title=!savedId?'Save the quotation first':'Create invoice from this quotation';
  }
  // Invoice creation button handler
  const createInvoiceBtn=document.getElementById('aq-create-invoice');
  if(createInvoiceBtn){
    updateInvoiceBtn();
    
    // New: use a nicer modal confirmation and submit to server-generated absolute URL
    createInvoiceBtn.onclick=async function(){
      if(!savedId){
        alert('Please save the quotation first.');
        return;
      }
      const confirmed = await askConfirm({
        title: 'Create Invoice',
        message: 'Are you sure you want to create an invoice from this quotation?\n\nThis will generate a new Tax Invoice linked to the quotation. You will be redirected to the invoice view after it is created.'
      });
      if(!confirmed) return;

      const fd=new FormData();
      fd.append('_action','create_invoice_from_quotation_local');
      fd.append('quotation_id', String(savedId));
      createInvoiceBtn.disabled=true;
      fetch(location.pathname, { method:'POST', body:fd }).then(async function(r){
        const raw=await r.text();
        let j=null;
        try{ j=JSON.parse(raw); }catch(_){ j=null; }
        if(!j||!j.ok) throw new Error((j&&j.error)||'Failed to create invoice');
        let msg='Invoice created successfully';
        if(j.restored) msg='Invoice restored to the active list';
        else if(j.existing) msg='Invoice already exists for this quotation';
        location.href=INVOICE_LIST_URL+'?success='+encodeURIComponent(msg);
      }).catch(function(err){
        alert(err&&err.message?err.message:'Failed to create invoice');
        createInvoiceBtn.disabled=false;
      });
    };
    
    // Update invoice button when status changes
    const originalUpdateBadge=updateBadge;
    updateBadge=function(){
      originalUpdateBadge();
      if(createInvoiceBtn) updateInvoiceBtn();
    };
  }

  // Helper: confirmation modal helper
  function askConfirm(opts){
    opts = Object.assign({ title: 'Confirm', message: '', okText: 'OK', cancelText: 'Cancel' }, opts||{});
    return new Promise(function(resolve){
      // create modal elements (reusable if present)
      let modal = document.getElementById('aq-confirm-modal');
      if(!modal){
        modal = document.createElement('div'); modal.id='aq-confirm-modal'; modal.className='jc-del-modal';
        modal.innerHTML = '<div class="jc-del-card" role="dialog" aria-modal="true">'
          +'<div class="jc-del-title" id="aq-confirm-title"></div>'
          +'<div class="jc-del-body" id="aq-confirm-body"></div>'
          +'<div class="jc-del-actions">'
          +'<button type="button" id="aq-confirm-cancel" class="aq-s1-btn aq-s1-btn--gray jc-del-cancel">Cancel</button>'
          +'<button type="button" id="aq-confirm-ok" class="aq-s1-btn jc-del-confirm">OK</button>'
          +'</div></div>';
        document.body.appendChild(modal);
      }
      const titleEl = modal.querySelector('#aq-confirm-title');
      const bodyEl = modal.querySelector('#aq-confirm-body');
      const ok = modal.querySelector('#aq-confirm-ok');
      const cancel = modal.querySelector('#aq-confirm-cancel');
      titleEl.textContent = opts.title || 'Confirm';
      bodyEl.textContent = opts.message || '';
      modal.classList.add('show');
      let done=false;
      function close(val){ if(done) return; done=true; modal.classList.remove('show'); ok.onclick=null; cancel.onclick=null; modal.onclick=null; resolve(Boolean(val)); }
      ok.onclick = function(){ close(true); };
      cancel.onclick = function(){ close(false); };
      modal.onclick = function(e){ if(e.target===modal) close(false); };
    });
  }

  ['f-approved_at','f-approved_by'].forEach(function(id){
    const el=document.getElementById(id);
    if(el) el.addEventListener('input',updatePaperSignoffBadge);
    if(el) el.addEventListener('change',updatePaperSignoffBadge);
  });
  ['f-rejected_at','f-rejected_by','f-rejection_reason'].forEach(function(id){
    const el=document.getElementById(id);
    if(el) el.addEventListener('input',updatePaperRejectionBadge);
    if(el) el.addEventListener('change',updatePaperRejectionBadge);
  });
  updatePaperSignoffBadge();
  updatePaperRejectionBadge();
  updateManagerReviewBadge();

  const sendClientBtn=document.getElementById('aq-send-client');
  if(sendClientBtn){
    sendClientBtn.onclick=async function(){
      const atEl=document.getElementById('f-approved_at');
      const signed=(atEl&&atEl.value)?String(atEl.value).trim():'';
      if(!signed){
        const ok=await askConfirm({
          title:'Paper sign-off not recorded',
          message:'Manager paper sign-off is not recorded yet. You can still continue.\n\nContinue marking as sent to client?',
          okText:'Continue anyway',
          cancelText:'Go back'
        });
        if(!ok) return;
      }
      const st=document.getElementById('f-status');
      if(st) st.value='sent_to_client';
      flash('Marked Sent to Client — click Save.');
      updateBadge();
      updateClientBtn();
    };
  }
  function getQuotationExportCss(){
    return '@page{size:A4;margin:8mm 10mm}'+
      'html,body{margin:0;padding:0;background:#fff}'+
      'body{font-family:DejaVu Sans,Arial,Helvetica,sans-serif;font-size:8pt;color:#000;-webkit-print-color-adjust:exact;print-color-adjust:exact}'+
      'table{border-collapse:collapse;width:100%}'+
      '#aq-print-inner{max-width:100%;background:#fff;box-sizing:border-box}'+
      '#aq-print-inner img{max-width:100%;height:auto}'+
      '#aq-print-inner .aq-doc-info-section{width:100%;border-collapse:collapse;table-layout:fixed;border:none;margin-bottom:2mm}'+
      '#aq-print-inner .aq-doc-info-slot{width:46%;vertical-align:top;padding:0;border:none}'+
      '#aq-print-inner .aq-doc-info-gap{width:8%;padding:0;border:none;font-size:0;line-height:0}'+
      '#aq-print-inner .aq-doc-info-slot-fit{display:inline-block;width:100%;vertical-align:top}'+
      '#aq-print-inner .aq-doc-info-box{width:100%;border-collapse:collapse;table-layout:fixed;border:1px solid '+AQ_DOC_BD+';background:#fff}'+
      '#aq-print-inner .aq-doc-info-box:not(.aq-doc-info-box--vehicle){table-layout:auto}'+
      '#aq-print-inner .aq-doc-info-hd{background:'+AQ_DOC_HDR+';color:#000;font-weight:bold;text-align:center;padding:3px 5px;border:1px solid '+AQ_DOC_BD+';font-size:7.5pt}'+
      '#aq-print-inner .aq-doc-info-lbl{font-weight:bold;color:#000;white-space:nowrap;text-align:left;padding:2px 6px 2px 8px;vertical-align:top;font-size:7.5pt;line-height:1.2;width:1%}'+
      '#aq-print-inner .aq-doc-info-val{color:#444;font-weight:normal;text-align:left;padding:2px 8px 2px 0;vertical-align:top;font-size:7.5pt;line-height:1.2;width:auto}'+
      '#aq-print-inner .aq-doc-info-box--vehicle .aq-doc-info-lbl,#aq-print-inner .aq-doc-info-box--vehicle .aq-doc-info-val{padding-top:1px;padding-bottom:1px;line-height:1.15}'+
      '#aq-print-inner .aq-doc-block-hd{background:'+AQ_DOC_HDR+';color:#000;font-weight:bold;text-align:center;padding:3px 5px;border:1px solid '+AQ_DOC_BD+';font-size:8pt}'+
      '#aq-print-inner .aq-doc-data-table{width:100%;max-width:100%;border-collapse:collapse;border-spacing:0;table-layout:fixed;box-sizing:border-box;margin-bottom:0;font-size:7.5pt;border:1px solid '+AQ_DOC_BD+'}'+
      '#aq-print-inner .aq-doc-data-table th,#aq-print-inner .aq-doc-data-table td{box-sizing:border-box}'+
      '#aq-print-inner .aq-doc-data-table.aq-labour-table,#aq-print-inner .aq-doc-data-table.aq-doc-parts-section{margin-bottom:2mm;margin-top:0}'+
      '#aq-print-inner .aq-doc-data-table th,#aq-print-inner .aq-doc-data-table td{border:1px solid '+AQ_DOC_BD+';padding:3px 5px;vertical-align:middle;background:'+AQ_DOC_BODY+'}'+
      '#aq-print-inner .aq-doc-data-table thead th{background:'+AQ_DOC_HDR+'!important;color:#000;font-weight:bold;text-align:center;border:1px solid '+AQ_DOC_BD+'}'+
      '#aq-print-inner .aq-doc-data-table.aq-labour-table,#aq-print-inner .aq-doc-data-table.aq-doc-parts-section{border-collapse:collapse;border-spacing:0}'+
      '#aq-print-inner .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-sub{background:#d9d9d9!important;color:#000;font-weight:bold;text-align:center;border:1px solid '+AQ_DOC_BD+'}'+
      '#aq-print-inner .aq-doc-data-table tr.aq-doc-last-body-row td{border-bottom:none!important}'+
      '#aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td{padding:1px 3px!important;line-height:1.1!important;font-size:7pt!important;height:auto;vertical-align:middle}'+
      '#aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-spacer{background:#fff!important;color:#000;border:1px solid '+AQ_DOC_BD+'!important;border-top:1px solid '+AQ_DOC_BD+'!important;font-weight:normal;padding:0 3px!important;min-height:0;font-size:0;line-height:0}'+
      '#aq-print-inner .aq-doc-data-table tr.aq-doc-total-gap td{height:4px;padding:0!important;border:none!important;background:#fff!important;font-size:0;line-height:0}'+
      '#aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-hdr{background:'+AQ_DOC_HDR+';color:#000;border:1px solid '+AQ_DOC_BD+';border-top:1px solid '+AQ_DOC_BD+';font-weight:bold;text-align:center}'+
      '#aq-print-inner .aq-doc-data-table tr.aq-doc-total-row td.aq-total-strip-metric{background:'+AQ_DOC_BODY+';color:#000;border:1px solid '+AQ_DOC_BD+';border-top:1px solid '+AQ_DOC_BD+';font-weight:normal;text-align:center}'+
      '#aq-print-inner .aq-doc-sum-table{width:42%;margin-left:auto;margin-top:0;border-collapse:collapse;font-size:7pt}'+
      '#aq-print-inner .aq-doc-sum-table td{padding:1px 4px;line-height:1.1;vertical-align:middle;border:1px solid '+AQ_DOC_BD+'}'+
      '#aq-print-inner .aq-doc-data-table tr.aq-parts-thick-divider td{height:5px;padding:0!important;background:'+AQ_DOC_BD+'!important;border:1px solid '+AQ_DOC_BD+'!important;border-top:1px solid '+AQ_DOC_BD+'!important;border-bottom:3px solid '+AQ_DOC_BD+'!important;font-size:0;line-height:0}'+
      '#aq-print-inner .aq-doc-data-table.aq-labour-table thead th.aq-lab-hdr-side,#aq-print-inner .aq-doc-data-table.aq-doc-parts-section thead th{vertical-align:middle;text-align:center}'+
      '#aq-print-inner .aq-doc-data-table.aq-labour-table thead tr.aq-labour-hdr-label th{border-top:1px solid '+AQ_DOC_BD+'}'+
      '#aq-print-inner .aq-labour-title-cell,#aq-print-inner .aq-part-type-cell{background:'+AQ_DOC_BODY+';border:1px solid '+AQ_DOC_BD+';text-align:center;vertical-align:middle}'+
      '#aq-print-inner .aq-labour-desc-cell,#aq-print-inner .aq-parts-name-cell{background:'+AQ_DOC_BODY+';border:1px solid '+AQ_DOC_BD+';vertical-align:top}'+
      '#aq-print-inner .aq-labour-metric-cell{background:'+AQ_DOC_BODY+';text-align:center;vertical-align:middle;border:1px solid '+AQ_DOC_BD+'}'+
      '#aq-print-inner .aq-doc-parts-section{margin-top:0;margin-bottom:2mm;table-layout:fixed;width:100%;max-width:100%;page-break-before:auto}'+
      '#aq-print-inner .aq-doc-footer-block{margin-top:3mm}'+
      '#aq-print-inner .aq-doc-parts-section td.aq-part-type-cell{white-space:nowrap!important;line-height:1.15;word-break:keep-all}'+
      '#aq-print-inner .aq-doc-footer-block{page-break-inside:avoid}'+
      '#aq-print-inner .aq-doc-muted{color:#000}';
  }
  function buildQuotationExportDoc(inner, title){
    const safeTitle=String(title||'Quotation').replace(/[<>&"]/g,'');
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>'+safeTitle+'</title><style>'+
      getQuotationExportCss()+'</style></head><body>'+inner.outerHTML+'</body></html>';
  }
  function getQuotationExportTitle(){
    let title='quotation';
    try{ title=(readForm().quote_number||'quotation').replace(/[^\w\-]+/g,'_'); }catch(_){}
    return title;
  }
  function runQuotationPrintPreview(){
    const inner=document.querySelector('#aq-print-mount #aq-print-inner')
      ||document.querySelector('#aq-live-doc-mount #aq-print-inner');
    if(!inner){
      try{ syncLiveDocPreview(!!window._aqSidePreviewBlank); }catch(err){ console.error('Preview sync failed', err); }
      const retry=document.querySelector('#aq-print-mount #aq-print-inner')
        ||document.querySelector('#aq-live-doc-mount #aq-print-inner');
      if(!retry) return;
      return runQuotationPrintFromNode(retry);
    }
    return runQuotationPrintFromNode(inner);
  }
  function runQuotationPrintFromNode(inner){
    if(!inner) return;
    const docHtml=buildQuotationExportDoc(inner, getQuotationExportTitle());
    const iframe=document.createElement('iframe');
    iframe.setAttribute('title','Quotation print');
    iframe.style.cssText='position:fixed;width:0;height:0;border:0;left:0;top:0;opacity:0;pointer-events:none';
    document.body.appendChild(iframe);
    const idoc=iframe.contentWindow.document;
    idoc.open();
    idoc.write(docHtml);
    idoc.close();
    function cleanup(){ if(iframe.parentNode) iframe.parentNode.removeChild(iframe); }
    function openPrintWindow(){
      const w=window.open('','_blank','noopener,noreferrer');
      if(!w){
        __aqNativeAlert('Print was blocked. Allow pop-ups for this site, or use Download and print from the saved file.');
        cleanup();
        return;
      }
      w.document.open();
      w.document.write(docHtml);
      w.document.close();
      setTimeout(function(){
        try{ w.focus(); w.print(); }catch(_){}
        setTimeout(function(){ try{ w.close(); }catch(_){} }, 800);
      }, 450);
      cleanup();
    }
    setTimeout(function(){
      try{
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
        setTimeout(cleanup, 1200);
      }catch(_){
        cleanup();
        openPrintWindow();
      }
    }, 500);
  }

  function buildPrintHtml(blankOnly){
    const fd=Object.assign({},readForm());
    if(blankOnly){
      fd.date='';
      fd.quote_number='';
      fd.customer_name='';
      fd.customer_address='';
      fd.customer_email='';
      fd.customer_phone='';
      fd.contact_person='';
      fd.vehicle_reg_no='';
      fd.vin_no='';
      fd.fleet_no='';
      fd.model='';
      fd.kilometers='';
      fd.job_no='';
      fd.purchase_order='';
    }

    function formatDate(dateStr){
      if(!dateStr||String(dateStr).trim()==='') return '';
      try{
        if(/^\d{4}-\d{2}-\d{2}$/.test(String(dateStr))){
          const p=String(dateStr).split('-');
          const dt=new Date(parseInt(p[0],10),parseInt(p[1],10)-1,parseInt(p[2],10));
          if(!isNaN(dt.getTime())){
            return String(dt.getDate()).padStart(2,'0')+'-'+String(dt.getMonth()+1).padStart(2,'0')+'-'+dt.getFullYear();
          }
        }
        const d=new Date(dateStr);
        if(isNaN(d.getTime())) return String(dateStr);
        return String(d.getDate()).padStart(2,'0')+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+d.getFullYear();
      }catch(e){
        return String(dateStr);
      }
    }

    function esc(s){
      return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
    }

    function aqDocQty(v){
      const n=Number(v);
      if(!isFinite(n)||n<=0) return '';
      return Math.abs(n-Math.round(n))<0.001?String(Math.round(n)):String(n);
    }

    function bShow(key,def){
      const x=fd[key];
      if(x===undefined||x===null||x==='') return def;
      return !!(x===true||x===1||x==='1');
    }

    function bInt(val,lo,hi,fb){
      const n=Math.floor(Number(val));
      if(!isFinite(n)) return fb;
      return Math.min(hi,Math.max(lo,n));
    }

    const formattedDate=formatDate(fd.date);
    const addrParts=blankOnly?[]:customerAddressLines();
    const totals=blankOnly?{lab:0,part:0,sub:0,vat:0,grand:0}:calcTotalsOnly();

    const DOC_HDR=AQ_DOC_HDR;
    const DOC_BD=AQ_DOC_BD;
    const DOC_BODY=AQ_DOC_BODY;
    const B='1px solid '+DOC_BD;
    const B_IN='1px solid '+DOC_BD;
    const INFO_HD_STYLE='background:'+DOC_HDR+';color:#000;border:1px solid '+DOC_BD+';font-weight:bold;text-align:center;padding:3px 5px;font-size:7.5pt';
    const INFO_COLGROUP='<colgroup><col style="width:1%"/><col style="width:auto"/>';
    const INFO_COLGROUP_CUSTOMER=INFO_COLGROUP;
    const INFO_COLGROUP_VEHICLE='<colgroup><col style="width:34%"/><col style="width:66%"/>';
    const INFO_LBL_STYLE='font-weight:bold;color:#000;text-align:left;padding:2px 6px 2px 8px;vertical-align:top;font-size:7.5pt;line-height:1.2;white-space:nowrap';
    const INFO_VAL_STYLE='color:#444;text-align:left;padding:2px 8px 2px 0;vertical-align:top;font-size:7.5pt;line-height:1.2';
    const INFO_LBL_STYLE_VEH='font-weight:bold;color:#000;text-align:left;padding:1px 6px 1px 8px;vertical-align:top;font-size:7.5pt;line-height:1.15;white-space:nowrap';
    const INFO_VAL_STYLE_VEH='color:#444;text-align:left;padding:1px 8px 1px 6px;vertical-align:top;font-size:7.5pt;line-height:1.15';
    const COL_DESC='12%';
    const COL_NAME='56%';
    const COL_QTY='10%';
    const COL_RATE='11%';
    const COL_TOT='11%';
    const TOTAL_STRIP_CELL='background:'+DOC_HDR+';color:#000;border:1px solid '+DOC_BD+';font-weight:bold;font-size:7pt;padding:3px 3px;line-height:1.25;height:14px;vertical-align:middle;text-align:center;';
    const TOTAL_METRIC_CELL='background:'+DOC_BODY+';color:#000;border:1px solid '+DOC_BD+';font-size:7pt;padding:1px 3px;line-height:1.1;vertical-align:middle;text-align:center;';
    const SUM_CELL='background:'+DOC_HDR+';color:#000;border:1px solid '+DOC_BD+';font-weight:bold;font-size:7pt;padding:1px 4px;line-height:1.1;vertical-align:middle;';
    const SUM_VAL_CELL=SUM_CELL+'text-align:right;';

    const headerBlock=HEADER_IMG
      ?'<img style="width:100%;display:block;" src="'+HEADER_IMG+'" alt="Header"/>'
      :'<div style="text-align:right;font-weight:bold;padding:20px 0;">SV Auto Truck Repair CC</div>';

    let html='';
    html+='<div id="aq-print-inner" class="invoice-wrapper">';
    html+='<div style="position:relative;margin-bottom:2mm;line-height:0;">';
    html+=headerBlock;
    html+='<div style="position:absolute;top:4px;right:8px;font-family:Arial Black,Arial,sans-serif;font-size:16pt;font-weight:900;letter-spacing:3px;color:#000;line-height:1;">QUOTATION</div>';
    html+='</div>';

    function aqInfoLine(label, value, lblStyle, valStyle) {
      return '<tr><td class="aq-doc-info-lbl" style="'+(lblStyle||INFO_LBL_STYLE)+'">'+esc(label)+'</td><td class="aq-doc-info-val" style="'+(valStyle||INFO_VAL_STYLE)+'">'+esc(value)+'</td></tr>';
    }
    function aqInfoBoxTable(title, rows, compactVehicle, tableExtraStyle) {
      let body = '';
      const lblStyle=compactVehicle?INFO_LBL_STYLE_VEH:INFO_LBL_STYLE;
      const valStyle=compactVehicle?INFO_VAL_STYLE_VEH:INFO_VAL_STYLE;
      const boxCls=compactVehicle?'aq-doc-info-box aq-doc-info-box--vehicle':'aq-doc-info-box';
      const colgroup=compactVehicle?INFO_COLGROUP_VEHICLE:INFO_COLGROUP_CUSTOMER;
      const header=String(title||'').trim()!==''?'<thead><tr><th colspan="2" class="aq-doc-info-hd" style="'+INFO_HD_STYLE+'">'+esc(title)+'</th></tr></thead>':'';
      rows.forEach(function (row) {
        body += aqInfoLine(row[0], row[1], lblStyle, valStyle);
      });
      return '<table class="'+boxCls+'" style="width:100%;border-collapse:collapse;table-layout:'+(compactVehicle?'fixed':'auto')+';border:1px solid '+DOC_BD+';background:#fff;'+(tableExtraStyle||'')+'">'
        +colgroup
        +header
        +'<tbody>'+body+'</tbody></table>';
    }
    const customerRows=[['Name', fd.customer_name]];
    if(addrParts.length){
      addrParts.forEach(function(part, idx){
        customerRows.push([idx===0?'Address':'', part]);
      });
    }else{
      customerRows.push(['Address', '']);
    }
    const contactRows=[
      ['Contact No', fd.customer_phone],
      ['Contact Person', fd.contact_person],
      ['Email Address', fd.customer_email]
    ];
    const vehicleRowsAll=[
      ['Date', formattedDate],
      ['Quote Number', fd.quote_number],
      ['Kilometers', fd.kilometers],
      ['Vin No.', fd.vin_no],
      ['Fleet No.', fd.fleet_no],
      ['Vehicle Reg No.', fd.vehicle_reg_no],
      ['Model', fd.model],
      ['Job No.', fd.job_no],
      ['Purchase Order', fd.purchase_order]
    ];
    const vehicleRows=blankOnly
      ? vehicleRowsAll
      : vehicleRowsAll.filter(function(row){ return String(row[1]||'').trim()!==''; });
    html+='<table class="aq-doc-info-section" style="width:100%;border-collapse:collapse;table-layout:fixed;border:none;margin-bottom:2mm"><tr>';
    html+='<td class="aq-doc-info-slot" style="width:46%;vertical-align:top;padding:0;border:none;height:1px"><div class="aq-doc-info-slot-fit" style="display:flex;width:100%;height:100%;vertical-align:top;flex-direction:column">'
      +aqInfoBoxTable('Customer Details', customerRows)
      +'<div style="height:2mm;font-size:0;line-height:0;"></div>'
      +aqInfoBoxTable('', contactRows, false, 'flex:1;height:100%;')
      +'</div></td>';
    html+='<td class="aq-doc-info-gap" style="width:8%;padding:0;border:none">&nbsp;</td>';
    html+='<td class="aq-doc-info-slot" style="width:46%;vertical-align:top;padding:0;border:none;height:1px">'+aqInfoBoxTable('Vehicle Details', vehicleRows, true, 'height:100%;')+'</td>';
    html+='</tr></table>';

    const inclLab=bShow('print_blank_show_labour',true);
    const labourSource=blankOnly?[]:labourRows.slice();
    const diagnosticRows=labourSource.filter(function(r){return r.include_in_print&&r.category==='diagnostic';});
    const normalTimeRows=labourSource.filter(function(r){return r.include_in_print&&(!r.category||r.category==='normal_time');});
    const overtimeRows=labourSource.filter(function(r){return r.include_in_print&&r.category==='overtime';});
    const publicHolidayRows=labourSource.filter(function(r){return r.include_in_print&&r.category==='public_holiday';});

    const normalLinesCfg=Math.max(2,bInt(fd.blank_normal_lines,2,40,5));
    const overtimeLinesCfg=bInt(fd.blank_overtime_lines,0,40,5);
    const holidayLinesCfg=bInt(fd.blank_holiday_lines,0,40,5);
    let showNT=!!fd.show_normal_time;
    let showOT=!!fd.show_overtime;
    let showPH=!!fd.show_public_holiday;
    if(!blankOnly){
      if(normalTimeRows.length>0) showNT=true;
      if(overtimeRows.length>0) showOT=true;
      if(publicHolidayRows.length>0) showPH=true;
    }

    let normalRowsToShow=0;
    let overtimeRowsToShow=0;
    let holidayRowsToShow=0;
    if(inclLab){
      if(blankOnly){
        if(showNT) normalRowsToShow=normalLinesCfg;
        if(showOT) overtimeRowsToShow=overtimeLinesCfg;
        if(showPH) holidayRowsToShow=holidayLinesCfg;
      }else{
        if(showNT) normalRowsToShow=Math.max(normalTimeRows.length,normalLinesCfg);
        if(showOT) overtimeRowsToShow=Math.max(overtimeRows.length,overtimeLinesCfg);
        if(showPH) holidayRowsToShow=Math.max(publicHolidayRows.length,holidayLinesCfg);
      }
    }

    function aqLabourBlockTitle(r){
      const label=String(r&&r.section_label||'').trim();
      if(label) return label;
      const map={diagnostic:'Diagnostic',normal_time:'Normal Time',overtime:'Overtime',public_holiday:'Public Holiday'};
      return map[(r&&r.category)||'normal_time']||'Labour';
    }
    function aqBuildLabourPrintBlocks(rows){
      const blocks=[];
      const sorted=rows.slice().sort(function(a,b){return (a.sort_order||0)-(b.sort_order||0);});
      sorted.forEach(function(r){
        if(!r||!r.include_in_print) return;
        const cat=r.category||'normal_time';
        const sk=r.section_key!=null&&r.section_key!==''?String(r.section_key):'';
        const key=cat+'\0'+sk;
        const last=blocks.length?blocks[blocks.length-1]:null;
        if(!last||last.blockKey!==key){
          blocks.push({blockKey:key,title:aqLabourBlockTitle(r),rows:[r]});
        }else{
          last.rows.push(r);
        }
      });
      return blocks;
    }
    const labourRowsForBlocks=labourSource.filter(function(r){return r.include_in_print&&r.category!=='diagnostic';});
    const labourPrintBlocks=blankOnly?[]:aqBuildLabourPrintBlocks(labourRowsForBlocks);
    const headerLabelsForSection=aqHeaderLabelsFromFormObject(fd);
    const hasLabourSection=blankOnly
      ?(normalRowsToShow>0||overtimeRowsToShow>0||holidayRowsToShow>0)
      :(inclLab&&(labourPrintBlocks.length>0||headerLabelsForSection.length>0));
    if(hasLabourSection){
      const headerLabels=aqHeaderLabelsFromFormObject(fd);
      const labHdrDual=headerLabels.length>=2?' aq-labour-hdr-dual':'';
      const LAB_HDR_STYLE='background:'+DOC_HDR+';color:#000;border:1px solid '+DOC_BD+';white-space:normal;word-wrap:break-word;overflow-wrap:anywhere;word-break:break-word;';
      html+='<table class="aq-doc-data-table aq-labour-table" style="width:100%;max-width:100%;table-layout:fixed;box-sizing:border-box;margin-bottom:2mm;border:1px solid '+DOC_BD+';border-collapse:collapse;border-spacing:0;"><colgroup><col style="width:'+COL_DESC+'"/><col style="width:'+COL_NAME+'"/><col style="width:'+COL_QTY+'"/><col style="width:'+COL_RATE+'"/><col style="width:'+COL_TOT+'"/></colgroup><thead>';
      html+=aqBuildLabourHeaderRowsHtml(headerLabels, LAB_HDR_STYLE, labHdrDual, blankOnly);
      html+='</thead><tbody>';
      function aqFormatHours(val){
        const n=Number(val);
        if(!isFinite(n)||n<=0) return '';
        if(Math.abs(n-Math.round(n))<0.001) return String(Math.round(n));
        return String(parseFloat(n.toFixed(1)));
      }
      let labourTotal=0;
      let labourHours=0;
      let labourRateSum=0;

      const labDataCell='padding:4px 6px;vertical-align:top;background:'+DOC_BODY+';border:1px solid '+DOC_BD+';font-size:7.5pt;line-height:1.45;';
      const labTitleCell='background:'+DOC_BODY+';border:1px solid '+DOC_BD+';text-align:center;vertical-align:middle;padding:4px 6px;font-size:7.5pt;line-height:1.45;';
      const labMetricCell='padding:3px 5px;text-align:center;vertical-align:middle;background:'+DOC_BODY+';border:1px solid '+DOC_BD+';font-size:7.5pt;';
      function labDescBorder(i,isFirstBlock,sep){
        if(i>0) return 'border-top:1px solid '+DOC_BD+';';
        if(!isFirstBlock) return sep;
        return '';
      }

      function aqBlockMetrics(rows){
        let h=0,r=0,t=0;
        if(!blankOnly){
          for(let j=0;j<rows.length;j++){
            const row=rows[j];
            if(!row) continue;
            const nh=Number(row.hours||0), nr=Number(row.rate||0), nt=Number(row.total||0);
            if(nh>0||nr>0||nt>0){ h=nh; r=nr; t=nt; break; }
          }
        }
        return {
          h:h>0?aqFormatHours(h):'',
          rate:r>0?r.toFixed(2):'',
          tot:t>0?t.toFixed(2):'',
          hoursNum:h, rateNum:r, totalNum:t
        };
      }
      function emitLabourBlock(title,rows,isFirstBlock,isLastBlock){
        const rowCount=rows.length;
        if(!rowCount) return;
        const sep=!isFirstBlock?'border-top:1px solid '+DOC_BD+';':'';
        const blockEnd='';
        const metrics=aqBlockMetrics(rows);
        if(!blankOnly){
          labourTotal+=metrics.totalNum;
          labourHours+=metrics.hoursNum;
          labourRateSum+=metrics.rateNum;
        }
        const metricSpan=(isFirstBlock?'border-top:1px solid '+DOC_BD+';':sep)+blockEnd;
        const titleStyle=labTitleCell+metricSpan+(title?'font-weight:bold;font-size:8pt;':'');
        const metricStyle=labMetricCell+metricSpan;
        for(let i=0;i<rowCount;i++){
          const row=rows[i];
          let desc=row?esc(row.description||''):'&nbsp;';
          if(desc==='') desc='&nbsp;';
          const descBorder=labDescBorder(i,isFirstBlock,sep);
          const lastBodyRow=isLastBlock&&i===rowCount-1;
          html+='<tr'+(lastBodyRow?' class="aq-doc-last-body-row"':'')+'>';
          if(i===0){
            html+='<td rowspan="'+rowCount+'" class="aq-labour-title-cell" style="'+titleStyle+'">'+(title?esc(title):'&nbsp;')+'</td>';
            html+='<td class="aq-labour-desc-cell" style="'+labDataCell+descBorder+'">'+desc+'</td>';
            html+='<td rowspan="'+rowCount+'" class="aq-labour-metric-cell" style="'+metricStyle+'">'+metrics.h+'</td>';
            html+='<td rowspan="'+rowCount+'" class="aq-labour-metric-cell" style="'+metricStyle+'">'+metrics.rate+'</td>';
            html+='<td rowspan="'+rowCount+'" class="aq-labour-metric-cell" style="'+metricStyle+'">'+metrics.tot+'</td>';
          }else{
            html+='<td class="aq-labour-desc-cell" style="'+labDataCell+descBorder+'">'+desc+'</td>';
          }
          html+='</tr>';
        }
      }

      if(blankOnly){
        const blankBlocks=[];
        if(normalRowsToShow>0) blankBlocks.push({title:'Normal Time',rows:Array(normalRowsToShow).fill(null)});
        if(overtimeRowsToShow>0) blankBlocks.push({title:'Overtime',rows:Array(overtimeRowsToShow).fill(null)});
        if(holidayRowsToShow>0) blankBlocks.push({title:'Public Holiday',rows:Array(holidayRowsToShow).fill(null)});
        blankBlocks.forEach(function(blk,b){
          emitLabourBlock(blk.title,blk.rows,b===0,b===blankBlocks.length-1);
        });
      }else{
        labourPrintBlocks.forEach(function(blk,b){
          emitLabourBlock(blk.title,blk.rows,b===0,b===labourPrintBlocks.length-1);
        });
      }

      html+='</tbody></table>'
        +'<div style="height:4px;font-size:0;line-height:0;background:#fff;"></div>'
        +'<table class="aq-total-strip-table" style="width:42%;margin-left:auto;border-collapse:collapse;table-layout:fixed;font-size:7pt;line-height:1.1;margin-bottom:2mm;"><colgroup><col style="width:23.8095%"><col style="width:23.8095%"><col style="width:26.1905%"><col style="width:26.1905%"></colgroup><tbody><tr>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">Total</td>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">'+(!blankOnly&&labourHours>0?aqFormatHours(labourHours):'')+'</td>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">'+(!blankOnly&&labourRateSum>0?labourRateSum.toFixed(2):'')+'</td>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">'+(!blankOnly&&labourTotal>0?labourTotal.toFixed(2):'')+'</td>'
        +'</tr></tbody></table>';
    }

    const inclParts=bShow('print_blank_show_parts',true);
    const partTypeLabelsForFilter=['consumables','call out fee','kilometres','consumable','call-out','call out'];
    function partRowHasContent(row){
      if(!row) return false;
      let name=String(row.item_name||'').trim();
      let callout=String(row.callout_type||'').trim();
      if(callout===''&&name!==''&&partTypeLabelsForFilter.indexOf(name.toLowerCase())>=0){
        callout=name;
        name='';
      }
      return name!==''||callout!==''||Number(row.qty)>0||Number(row.unit_cost)>0||Number(row.total)>0;
    }
    const partsFiltered=blankOnly?[]:partsRows.filter(function(r){return r.include_in_print&&partRowHasContent(r);});
    const consFiltered=blankOnly?[]:consRows.filter(function(r){return r.include_in_print&&partRowHasContent(r);});
    const partsTopLinesCfg=Math.max(0,bInt(fd.blank_parts_top_lines,0,10,2));
    const partsLinesCfg=Math.max(1,bInt(fd.blank_parts_lines,1,40,7));
    const consLinesCfg=Math.max(0,bInt(fd.blank_cons_lines,0,40,1));

    let partsRowsToShow=0;
    let consRowsToShow=0;
    if(inclParts){
      if(blankOnly){
        partsRowsToShow=partsLinesCfg;
        consRowsToShow=consLinesCfg;
      } else {
        partsRowsToShow=partsFiltered.length;
        consRowsToShow=consFiltered.length;
      }
    }

    const hasPartsSection=inclParts&&(blankOnly
      ?(partsTopLinesCfg>0||partsRowsToShow>0||consRowsToShow>0)
      :(partsRowsToShow>0||consRowsToShow>0));

    if(hasPartsSection){
      const totalPR=partsRowsToShow+consRowsToShow;
      let partsBody='';
      let partsTotal=0;
      let partIdx=0;

      const partTitleCell='background:'+DOC_BODY+';border:1px solid '+DOC_BD+';text-align:center;vertical-align:middle;padding:4px 6px;font-size:7.5pt;line-height:1.45;white-space:nowrap!important;word-break:keep-all;';
      const partNameCell='padding:4px 6px;vertical-align:top;background:'+DOC_BODY+';border:1px solid '+DOC_BD+';font-size:7.5pt;line-height:1.45;';
      const partMetricCell='padding:3px 5px;text-align:center;vertical-align:middle;background:'+DOC_BODY+';border:1px solid '+DOC_BD+';font-size:7.5pt;';
      function partTypeLabel(text){
        return esc(String(text||'').trim()).replace(/ /g,'\u00a0');
      }
      function partDescBorder(i,isFirstBlock,sep){
        if(i>0) return 'border-top:1px solid '+DOC_BD+';';
        if(!isFirstBlock) return sep;
        return '';
      }
      const partTypeLabels=['consumables','call out fee','kilometres','consumable','call-out','call out'];

      function resolveCalloutType(row){
        if(!row) return '';
        let type=String(row.callout_type||'').trim();
        let name=String(row.item_name||'').trim();
        if(type===''&&name!==''&&partTypeLabels.indexOf(name.toLowerCase())>=0){
          return name;
        }
        return type;
      }

      function partItemDisplay(row){
        if(!row) return '&nbsp;';
        let name=String(row.item_name||'').trim();
        if(name!==''&&partTypeLabels.indexOf(name.toLowerCase())>=0){
          name='';
        }
        if(name.toLowerCase()==='parts supply'){
          name='';
        }
        return name!==''?esc(name):'&nbsp;';
      }

      function partRowMetrics(row){
        if(blankOnly||!row) return {q:'',uc:'',t:''};
        return {
          q:Number(row.qty)>0?aqDocQty(row.qty):'',
          uc:Number(row.unit_cost)>0?Number(row.unit_cost).toFixed(2):'',
          t:Number(row.total)>0?Number(row.total).toFixed(2):''
        };
      }

      function appendPartSection(label,rowCount,getter,isFirstBlock,isLastBlock){
        const sep=!isFirstBlock?'border-top:1px solid '+DOC_BD+';':'';
        const blockEnd='';
        const metricSpan=(isFirstBlock?'border-top:1px solid '+DOC_BD+';':sep)+blockEnd;
        const rowLabel=String(label||'').trim();
        const titleStyle=partTitleCell+metricSpan+(rowLabel?'font-weight:bold;font-size:8pt;':'');
        const metricStyle=partMetricCell+metricSpan;
        for(let i=0;i<rowCount;i++){
          const row=getter(i);
          if(!blankOnly&&row){
            partsTotal+=Number(row.total||0);
          }
          const metrics=partRowMetrics(row);
          const name=partItemDisplay(row);
          const descBorder=i>0?'border-top:1px solid '+DOC_BD+';':(isFirstBlock?'':sep);
          const typeLabel=i===0&&rowLabel!==''?partTypeLabel(rowLabel):'&nbsp;';
          const lastPartBodyRow=isLastBlock&&i===rowCount-1;
          partsBody+='<tr'+(lastPartBodyRow?' class="aq-doc-last-body-row"':'')+'>';
          if(i===0){
            partsBody+='<td rowspan="'+rowCount+'" class="aq-part-type-cell" style="'+titleStyle+'">'+typeLabel+'</td>';
            partsBody+='<td class="aq-parts-name-cell" style="'+partNameCell+descBorder+'">'+name+'</td>';
            partsBody+='<td rowspan="'+rowCount+'" class="aq-labour-metric-cell" style="'+metricStyle+'">'+metrics.q+'</td>';
            partsBody+='<td rowspan="'+rowCount+'" class="aq-labour-metric-cell" style="'+metricStyle+'">'+metrics.uc+'</td>';
            partsBody+='<td rowspan="'+rowCount+'" class="aq-labour-metric-cell" style="'+metricStyle+'">'+metrics.t+'</td>';
          }else{
            partsBody+='<td class="aq-parts-name-cell" style="'+partNameCell+descBorder+'">'+name+'</td>';
          }
          partsBody+='</tr>';
          partIdx++;
        }
      }

      const blankPartCell='background:'+DOC_BODY+';border:1px solid '+DOC_BD+';font-size:7.5pt;padding:4px 6px;vertical-align:middle;';
      if(blankOnly){
        for(let ti=0;ti<partsTopLinesCfg;ti++){
          partsBody+='<tr>';
          if(ti===0){
            partsBody+='<td rowspan="'+partsTopLinesCfg+'" class="aq-part-type-cell" style="'+blankPartCell+'">&nbsp;</td>';
          }
          partsBody+='<td class="aq-parts-name-cell" style="'+blankPartCell+'">&nbsp;</td>';
          partsBody+='<td class="aq-labour-metric-cell" style="'+blankPartCell+'">&nbsp;</td>';
          partsBody+='<td class="aq-labour-metric-cell" style="'+blankPartCell+'">&nbsp;</td>';
          partsBody+='<td class="aq-labour-metric-cell" style="'+blankPartCell+'">&nbsp;</td>';
          partsBody+='</tr>';
        }
        if(partsTopLinesCfg>0&&(partsRowsToShow>0||consRowsToShow>0)){
          partsBody+='<tr class="aq-parts-thick-divider"><td colspan="5" style="height:5px;padding:0;background:'+DOC_BD+';border:1px solid '+DOC_BD+';border-top:1px solid '+DOC_BD+';border-bottom:3px solid '+DOC_BD+';font-size:0;line-height:0;"></td></tr>';
        }
        let blankPartIdx=0;
        if(partsRowsToShow>0){
          appendPartSection('Parts Supply',partsRowsToShow,function(){return null;},blankPartIdx===0,consRowsToShow<=0);
          blankPartIdx++;
        }
        if(consRowsToShow>0){
          const consLabel=String(fd.consumables_label||'').trim()||'Consumables';
          appendPartSection(consLabel,consRowsToShow,function(){return null;},blankPartIdx===0,true);
        }
      }else{
        const partBlocks=[];
        partsFiltered.forEach(function(row,idx){
          partBlocks.push({label:idx===0?'Parts Supply':'',count:1,getter:function(){return row;}});
        });
        for(let ci=0;ci<consFiltered.length;ci++){
          const crow=consFiltered[ci];
          const ctype=resolveCalloutType(crow);
          partBlocks.push({
            label:ctype!==''?ctype:'Call-out',
            count:1,
            getter:function(){return crow;}
          });
        }
        for(let b=0;b<partBlocks.length;b++){
          const blk=partBlocks[b];
          appendPartSection(blk.label,blk.count,blk.getter,b===0,b===partBlocks.length-1);
        }
      }
      const PART_HDR_STYLE='background:'+DOC_HDR+';color:#000;border:1px solid '+DOC_BD+';';
      const partsColgroup='<colgroup><col style="width:'+COL_DESC+'"/><col style="width:'+COL_NAME+'"/><col style="width:'+COL_QTY+'"/><col style="width:'+COL_RATE+'"/><col style="width:'+COL_TOT+'"/></colgroup>';
      html+='<table class="aq-doc-data-table aq-doc-parts-section" style="width:100%;max-width:100%;table-layout:fixed;box-sizing:border-box;margin-top:0;margin-bottom:2mm;border:1px solid '+DOC_BD+';border-collapse:collapse;border-spacing:0;">'+partsColgroup+'<thead><tr>';
      html+='<th style="'+PART_HDR_STYLE+'"></th>';
      html+='<th style="'+PART_HDR_STYLE+'">Item Name</th>';
      html+='<th style="'+PART_HDR_STYLE+'">Qty</th>';
      html+='<th style="'+PART_HDR_STYLE+'">Unit Cost</th>';
      html+='<th style="'+PART_HDR_STYLE+'">Total</th></tr></thead><tbody>'+partsBody;
      let partsQtySum=0;
      let partsCostSum=0;
      if(!blankOnly){
        partsFiltered.forEach(function(r){ partsQtySum+=Number(r.qty||0); partsCostSum+=Number(r.unit_cost||0); });
        consFiltered.forEach(function(r){ partsQtySum+=Number(r.qty||0); partsCostSum+=Number(r.unit_cost||0); });
      }
      html+='</tbody></table>'
        +'<div style="height:4px;font-size:0;line-height:0;background:#fff;"></div>'
        +'<table class="aq-total-strip-table" style="width:42%;margin-left:auto;border-collapse:collapse;table-layout:fixed;font-size:7pt;line-height:1.1;margin-bottom:2mm;"><colgroup><col style="width:23.8095%"><col style="width:23.8095%"><col style="width:26.1905%"><col style="width:26.1905%"></colgroup><tbody><tr>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">Total Parts</td>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">'+(!blankOnly&&partsQtySum>0?aqDocQty(partsQtySum):'')+'</td>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">'+(!blankOnly&&partsCostSum>0?partsCostSum.toFixed(2):'')+'</td>'
        +'<td class="aq-total-strip-hdr" style="'+TOTAL_STRIP_CELL+'">'+(!blankOnly&&partsTotal>0?partsTotal.toFixed(2):'')+'</td>'
        +'</tr></tbody></table>';
    }

    html+='<div class="aq-doc-footer-block"><table style="width:100%;border-collapse:collapse;font-size:7.5pt;margin-bottom:1.5mm;"><tbody><tr>';
    html+='<td style="vertical-align:top;width:40%;padding:0 6px 0 0;border:none;">';
    html+='<div style="font-style:italic;font-weight:bold;margin-bottom:3px;font-size:8pt">Note: Unforseen is not quoted for.</div>';
    html+='<table style="border-collapse:collapse;font-size:8pt;width:auto;max-width:90%"><tbody>';
    html+='<tr>';
    html+='<td style="border:'+B_IN+';background:#fff;color:#dc2626;font-weight:bold;padding:3px 5px;font-size:8pt;width:50%">New Banking Details:</td>';
    html+='<td style="border:'+B_IN+';background:#fff;width:50%"></td>';
    html+='</tr>';
    html+='<tr><td colspan="2" style="border:'+B_IN+';border-top:none;padding:5px 8px;background:#fff;font-size:8pt;line-height:1.6">';
    html+='<div style="font-weight:bold;">S.V Auto Truck Repair cc</div>';
    html+='<div style="font-weight:bold;">Bank Windhoek Limited</div>';
    html+='<div style="font-weight:bold;">Acc: CHK: 8040770120</div>';
    html+='<div style="font-weight:bold;">Branch code: 486-372</div>';
    html+='<div style="font-weight:bold;">Business Cheque Account</div>';
    html+='</td></tr></tbody></table></td>';
    html+='<td style="vertical-align:top;width:60%;padding:0 0 0 8mm;border:none;">';

    const subLbl=blankOnly?'':(totals.sub>0?totals.sub.toFixed(2):'');
    const vatLbl=blankOnly?'':(totals.vat>0?totals.vat.toFixed(2):'');
    const grLbl=blankOnly?'':(totals.grand>0?totals.grand.toFixed(2):'');
    html+='<table class="aq-doc-sum-table" style="width:42%;border-collapse:collapse;font-size:7pt;margin-left:auto;margin-top:0;margin-bottom:8mm;border:'+B_IN+'"><colgroup><col style="width:58%"><col style="width:42%"></colgroup><tbody>';
    html+='<tr><td style="'+SUM_CELL+'">Subtotal</td><td style="'+SUM_VAL_CELL+'">'+esc(subLbl)+'</td></tr>';
    html+='<tr><td style="'+SUM_CELL+'">VAT</td><td style="'+SUM_VAL_CELL+'">'+esc(vatLbl)+'</td></tr>';
    html+='<tr><td style="'+SUM_CELL+'">Total</td><td style="'+SUM_VAL_CELL+'">'+esc(grLbl)+'</td></tr>';
    html+='</tbody></table>';

    html+='<div style="margin-bottom:7px;font-size:8pt;display:flex;align-items:center;gap:6px;line-height:1.1;">';
    html+='<span style="min-width:60px;">Approved</span>';
    html+='<span style="flex:1;border-bottom:1px solid '+DOC_BD+';padding-left:6px;font-weight:bold;color:#111;"></span></div>';
    html+='<div style="font-size:8pt;display:flex;align-items:center;gap:6px;line-height:1.1;">';
    html+='<span style="min-width:60px;">Date</span>';
    html+='<span style="flex:1;border-bottom:1px solid '+DOC_BD+';padding-left:6px;font-weight:bold;color:#111;"></span></div>';
    html+='</td></tr></tbody></table>';
    html+='<div style="text-align:center;font-style:italic;font-size:8.5pt;border-top:.5px solid #ccc;padding-top:3px"><em>Essence of perfection - Thank you for doing business with us</em></div>';
    html+='</div></div>';
    return html;
  }

  function scheduleLiveDocPreview(){
    if(_aqLiveDocRaf) return;
    _aqLiveDocRaf=requestAnimationFrame(function(){
      _aqLiveDocRaf=null;
      syncLiveDocPreview();
    });
  }
  window._aqSidePreviewBlank=false;
  window._aqSidePreviewTitle='Quotation Preview';

  function updateSidePreviewMeta(){
    const metaEl=document.getElementById('aq-live-preview-label');
    if(!metaEl) return;
    let qn='';
    try{ qn=(readForm().quote_number||'').trim(); }catch(_){}
    if(qn) metaEl.textContent=qn;
    else if(metaEl.dataset.ref) metaEl.textContent=metaEl.dataset.ref;
  }
  function setSidePreviewHeading(title){
    window._aqSidePreviewTitle=String(title||'Quotation Preview');
    const titleEl=document.getElementById('aq-live-preview-title');
    if(titleEl) titleEl.textContent=window._aqSidePreviewTitle;
  }
  function syncLiveDocPreview(blank){
    const useBlank=(blank!==undefined)?!!blank:!!window._aqSidePreviewBlank;
    window._aqSidePreviewBlank=useBlank;
    const m=document.getElementById('aq-live-doc-mount');
    if(!m) return;
    m.innerHTML=buildPrintHtml(useBlank);
    updateSidePreviewMeta();
  }
  function getSidePreviewInner(){
    return document.querySelector('#aq-live-doc-mount #aq-print-inner');
  }
  function sidePreviewDownload(){
    const title=getQuotationExportTitle();
    const qid=String(savedId || AQ_EDIT_ID || '');
    if(!qid){
      showAqNotice('Save the quotation first before downloading a PDF.','Save required');
      return;
    }
    const dlBtn=document.getElementById('aq-side-download')
      || document.getElementById('aq-crm-action-download');
    if(dlBtn){
      dlBtn.disabled=true;
      dlBtn.dataset.prevLabel=dlBtn.innerHTML;
      dlBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Preparing PDF…';
    }
    const fd=new FormData();
    fd.append('_action','download_quotation_pdf');
    fd.append('filename', title);
    fd.append('quote_id', qid);
    const pdfUrl=location.pathname+'?id='+encodeURIComponent(qid);
    fetch(pdfUrl, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){
        if(!r.ok) return r.text().then(function(t){ throw new Error(t || 'PDF generation failed'); });
        const ct=(r.headers.get('content-type')||'').toLowerCase();
        if(ct.indexOf('pdf')===-1){
          return r.text().then(function(t){ throw new Error(t || 'Server did not return a PDF file'); });
        }
        return r.blob();
      })
      .then(function(blob){
        if(!blob || blob.size < 2048){
          throw new Error('PDF file looks empty. Please try again or use Print → Save as PDF.');
        }
        const url=URL.createObjectURL(blob);
        const link=document.createElement('a');
        link.href=url;
        link.download=title+'.pdf';
        link.rel='noopener';
        link.style.display='none';
        document.body.appendChild(link);
        link.click();
        setTimeout(function(){
          URL.revokeObjectURL(url);
          if(link.parentNode) link.parentNode.removeChild(link);
        }, 400);
      })
      .catch(function(err){
        console.error('PDF download failed', err);
        showAqNotice(err && err.message ? err.message : 'Could not generate PDF. Try Print and choose Save as PDF.','Download failed');
      })
      .finally(function(){
        if(dlBtn){
          dlBtn.disabled=false;
          if(dlBtn.dataset.prevLabel) dlBtn.innerHTML=dlBtn.dataset.prevLabel;
        }
      });
  }
  function sidePreviewPrint(){
    let inner=getSidePreviewInner();
    if(!inner){
      try{ syncLiveDocPreview(!!window._aqSidePreviewBlank); }catch(err){ console.error('Preview sync failed', err); }
      inner=getSidePreviewInner();
    }
    if(!inner){
      showAqNotice('Nothing to print yet. Wait for the preview to load, or click Preview.','Preview not ready');
      return;
    }
    runQuotationPrintFromNode(inner);
  }
  function openSidePreview(blank, headingTitle){
    const isBlank=!!blank;
    const toolBtn=document.getElementById(isBlank?'aq-btn-print-blank':'aq-btn-preview');
    setDocToolActive(toolBtn);
    if(headingTitle) setSidePreviewHeading(headingTitle);
    else setSidePreviewHeading(isBlank ? 'Print Blank' : 'Preview');
    syncLiveDocPreview(isBlank);
    const panel=document.querySelector('.aq-manage-preview-main');
    const scrollWrap=document.getElementById('aq-live-doc-scroll');
    if(scrollWrap) scrollWrap.scrollTop=0;
    if(panel){
      panel.classList.add('is-highlight');
      setTimeout(function(){ panel.classList.remove('is-highlight'); }, 1400);
      if(window.matchMedia('(max-width: 1024px)').matches){
        panel.scrollIntoView({ behavior:'smooth', block:'start' });
      }
    }
  }

  function openPrint(blank){
    const shell=document.getElementById('aq-print-shell');
    const mount=document.getElementById('aq-print-mount');
    const titlebar=document.getElementById('aq-print-titlebar');
    if(!shell||!mount){
      __aqNativeAlert('Print preview is not available on this page.');
      return;
    }
    if(shell.parentNode!==document.body){
      document.body.appendChild(shell);
    }
    let html='';
    try{
      html=buildPrintHtml(!!blank);
    }catch(err){
      console.error('Preview build failed', err);
      __aqNativeAlert('Could not build quotation preview: '+(err&&err.message?err.message:'unknown error'));
      return;
    }
    mount.innerHTML=html;
    shell.classList.remove('hidden');
    shell.style.cssText='display:block!important;visibility:visible!important;position:fixed;inset:0;z-index:999999!important;overflow:auto;background:rgba(15,23,42,0.45)';
    document.body.classList.add('print-open');
    if(titlebar){
      titlebar.textContent=blank ? 'Blank Quotation Preview' : ('Quotation Preview — '+(readForm().quote_number||''));
    }
    shell.scrollTop=0;
    window.scrollTo(0, 0);
    if(typeof aqMarkPrinted==='function') aqMarkPrinted();
  }
  aqRegisterOpenPrint();
  function openBrowserPrint(blank){
    const html=buildPrintHtml(blank);
    const wrap=document.createElement('div');
    wrap.innerHTML=html;
    const inner=wrap.querySelector('#aq-print-inner');
    if(inner){
      runQuotationPrintFromNode(inner);
      return;
    }
    const w=window.open('','_blank','noopener,noreferrer');
    if(!w) return;
    w.document.open();
    w.document.write(buildQuotationExportDoc({outerHTML:html}, getQuotationExportTitle()));
    w.document.close();
    setTimeout(function(){ try{ w.focus(); w.print(); }catch(_){} }, 450);
  }
  function closePrint(){
    const shell=document.getElementById('aq-print-shell');
    if(shell){
      shell.classList.add('hidden');
      shell.style.cssText='';
    }
    document.body.classList.remove('print-open');
  }

  const printShellEl=document.getElementById('aq-print-shell');
  if(printShellEl){
    printShellEl.addEventListener('click', function(e){
      if(e.target===printShellEl) closePrint();
    });
  }
  document.addEventListener('keydown', function(e){
    if(e.key!=='Escape') return;
    if(document.getElementById('aqFlashWrap')){
      hideAqFlash();
      return;
    }
    const exportModal=document.getElementById('aqExportModal');
    if(exportModal && exportModal.classList.contains('show')){
      if(typeof window.closeAqExportModal==='function') window.closeAqExportModal();
      return;
    }
    const shell=document.getElementById('aq-print-shell');
    if(shell && !shell.classList.contains('hidden')) closePrint();
  });

  function aqCrmRunPrint(blank){
    const isBlank=!!blank;
    const toolBtn=document.getElementById(isBlank?'aq-btn-print-blank':'aq-crm-action-print');
    if(toolBtn) setDocToolActive(toolBtn);
    window._aqSidePreviewBlank=isBlank;
    if(USE_SIDE_PREVIEW){
      openSidePreview(isBlank, isBlank?'Print Blank':'Preview');
      setTimeout(function(){
        try{ sidePreviewPrint(); }catch(err){
          console.error('Print failed', err);
          runQuotationPrintPreview();
        }
      }, 450);
      return;
    }
    openPrint(isBlank);
    setTimeout(function(){ runQuotationPrintPreview(); }, 650);
  }

  const previewBtn = document.getElementById('aq-btn-preview');
  const printBtn = document.getElementById('aq-crm-action-print');
  const printBlankBtn = document.getElementById('aq-btn-print-blank');
  const closePrintBtn = document.getElementById('aq-close-print');

  if(previewBtn) {
    previewBtn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      setDocToolActive(previewBtn);
      if(USE_SIDE_PREVIEW) openSidePreview(false, 'Preview');
      else openPrint(false);
    });
  }
  if(printBtn) {
    printBtn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      const wrap=document.getElementById('aq-crm-actions-wrap');
      if(wrap) wrap.classList.remove('is-open');
      const menu=document.getElementById('aq-crm-actions-menu');
      const actBtn=document.getElementById('aq-crm-actions-btn');
      if(menu){ menu.hidden=true; menu.setAttribute('hidden',''); }
      if(actBtn) actBtn.setAttribute('aria-expanded','false');
      aqCrmRunPrint(false);
    });
  }
  if(printBlankBtn) {
    printBlankBtn.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      setDocToolActive(printBlankBtn);
      if(USE_SIDE_PREVIEW) openSidePreview(true, 'Print Blank');
      else openPrint(true);
    });
  }
  const docToolsGrid=document.querySelector('.aq-doc-tools-grid');
  if(docToolsGrid){
    docToolsGrid.addEventListener('click', function(e){
      const editLink=e.target.closest('[data-doc-tool="edit"]');
      if(editLink) setDocToolActive(editLink);
    });
  }
  function aqRegisterSidePreviewActions(){
    const sideActions=document.getElementById('aq-side-preview-actions');
    if(sideActions && !sideActions.dataset.aqWired){
      sideActions.dataset.aqWired='1';
      sideActions.addEventListener('click', function(e){
        const dl=e.target.closest('#aq-side-download');
        const pr=e.target.closest('#aq-side-print');
        if(dl){
          e.preventDefault();
          e.stopPropagation();
          aqRequestSideDownload(e);
        }else if(pr){
          e.preventDefault();
          e.stopPropagation();
          aqRequestSidePrint(e);
        }
      });
    }
    const sideDownloadBtn=document.getElementById('aq-side-download');
    const sidePrintBtn=document.getElementById('aq-side-print');
    if(sideDownloadBtn) sideDownloadBtn.onclick=window.aqSideDownload;
    if(sidePrintBtn) sidePrintBtn.onclick=window.aqSidePrint;
  }
  aqRegisterSidePreviewActions();
  if(!window.AQ._printShellWired){
    if(closePrintBtn) closePrintBtn.onclick=closePrint;
    const savePdfBottom=document.getElementById('aq-save-pdf-bottom');
    const doPrintBtn=document.getElementById('aq-do-print');
    if(doPrintBtn) doPrintBtn.onclick=function(){runQuotationPrintPreview();};
    if(savePdfBottom) savePdfBottom.onclick=function(){runQuotationPrintPreview();};
  }

  const aqRootEl=document.getElementById('aq-app');
  if(aqRootEl){
    aqRootEl.addEventListener('input', function(){ scheduleLiveDocPreview(); });
    aqRootEl.addEventListener('change', function(){ scheduleLiveDocPreview(); });
  }

  function aqCrmSidebarTabsInit(){
    const tablist=document.querySelector('.aq-crm-sidebar-tabs');
    if(!tablist) return;
    const tabs=tablist.querySelectorAll('.aq-crm-sidebar-tab');
    const panels=document.querySelectorAll('[data-aq-sidebar-panel]');
    function activate(name){
      tabs.forEach(function(tab){
        const on=tab.getAttribute('data-aq-sidebar-tab')===name;
        tab.classList.toggle('is-active', on);
        tab.setAttribute('aria-selected', on?'true':'false');
      });
      panels.forEach(function(panel){
        const on=panel.getAttribute('data-aq-sidebar-panel')===name;
        panel.classList.toggle('is-active', on);
        if(on) panel.removeAttribute('hidden'); else panel.hidden=true;
      });
    }
    tabs.forEach(function(tab){
      tab.addEventListener('click', function(){
        activate(tab.getAttribute('data-aq-sidebar-tab')||'workflow');
      });
    });
    if(window.AQ_CRM_OPEN_CUSTOMER_TAB==='true' || window.AQ_CRM_OPEN_CUSTOMER_TAB===true){
      activate('customer');
    }
  }
  window.aqCrmSidebarTab = function(name){
    const tablist=document.querySelector('.aq-crm-sidebar-tabs');
    if(!tablist) return;
    const tab=tablist.querySelector('[data-aq-sidebar-tab="'+name+'"]');
    if(tab) tab.click();
  };

  function aqCrmInitActions(){
    const btn=document.getElementById('aq-crm-actions-btn');
    const menu=document.getElementById('aq-crm-actions-menu');
    const wrap=btn?btn.closest('.crm-cust-actions-wrap'):null;
    function closeMenu(){ if(menu){ menu.hidden=true; menu.setAttribute('hidden',''); } if(btn){ btn.setAttribute('aria-expanded','false'); } if(wrap){ wrap.classList.remove('is-open'); } }
    function toggleMenu(e){ if(e){ e.preventDefault(); e.stopPropagation(); } if(!menu||!btn) return; const open=menu.hidden; closeMenu(); if(open){ menu.hidden=false; menu.removeAttribute('hidden'); btn.setAttribute('aria-expanded','true'); if(wrap) wrap.classList.add('is-open'); } }
    if(btn&&menu){ btn.addEventListener('click', toggleMenu); }
    const cardMenuBtn=document.getElementById('aq-crm-card-menu');
    const cardPopover=document.getElementById('aq-crm-card-popover');
    const cardMenuWrap=cardMenuBtn?cardMenuBtn.closest('.crm-cust-hd-menu'):null;
    function closeCardPopover(){ if(cardPopover){ cardPopover.hidden=true; cardPopover.setAttribute('hidden',''); } if(cardMenuBtn){ cardMenuBtn.setAttribute('aria-expanded','false'); } }
    function toggleCardPopover(e){ if(e){ e.preventDefault(); e.stopPropagation(); } if(!cardPopover||!cardMenuBtn) return; const open=cardPopover.hidden; closeCardPopover(); closeMenu(); if(open){ cardPopover.hidden=false; cardPopover.removeAttribute('hidden'); cardMenuBtn.setAttribute('aria-expanded','true'); } }
    if(cardMenuBtn&&cardPopover){ cardMenuBtn.addEventListener('click', toggleCardPopover); }
    document.addEventListener('click', function(e){
      if(wrap&&wrap.contains(e.target)) return;
      if(cardMenuWrap&&cardMenuWrap.contains(e.target)) return;
      closeMenu();
      closeCardPopover();
    });
    const map=[
      ['aq-crm-action-download', function(){ if(window.aqSideDownload) window.aqSideDownload(); closeMenu(); }],
      ['aq-crm-file-download', function(){ if(window.aqSideDownload) window.aqSideDownload(); }],
      ['aq-btn-preview', function(){ closeMenu(); }],
      ['aq-btn-print-blank', function(){ closeMenu(); }],
      ['aq-set-quote-number', function(){ closeMenu(); }]
    ];
    map.forEach(function(pair){ const el=document.getElementById(pair[0]); if(el) el.addEventListener('click', pair[1]); });
  }
  try{ aqMqInitTabs(); aqCrmSidebarTabsInit(); aqCrmInitActions(); calc(); updateWorkflowUI(); }catch(err){ console.error('Quotation calc init failed', err); }
  if(USE_SIDE_PREVIEW){
    try{
      setSidePreviewHeading('Quotation Preview');
      scheduleLiveDocPreview(false);
    }catch(_){}
  }
  (function initAqPageFlash(){
    const wrap = document.getElementById('jcFlashWrap');
    if(!wrap) return;
    requestAnimationFrame(function(){ wrap.classList.add('show'); });
    setTimeout(function(){
      wrap.classList.remove('show');
      setTimeout(function(){ if(wrap.parentNode) wrap.parentNode.removeChild(wrap); }, 220);
    }, 3200);
  })();
  try{
    const qs=new URLSearchParams(window.location.search||'');
    if(qs.get('preview')==='1'){
      if(USE_SIDE_PREVIEW) openSidePreview(false, 'Preview');
      else openPrint(false);
    }
  }catch(_){}
})();
</script>

<?php if ($editId): ?>
<?php
$qt_chat_modal_js = __DIR__ . '/qt_chat_modal.js';
$qt_chat_modal_js_v = is_file($qt_chat_modal_js) ? (string) filemtime($qt_chat_modal_js) : '1';
$qt_chat_customer_name = '';
if (!empty($saved_payload['form']['client_name'])) {
    $qt_chat_customer_name = trim((string) $saved_payload['form']['client_name']);
}
?>
<?php
$qt_chat_quote_id = (int) $editId;
$qt_chat_quote_label = $aq_quote_ref_label ?? ('QTN-' . str_pad((string) $editId, 5, '0', STR_PAD_LEFT));
$qt_chat_messages = $aq_manager_comments;
$qt_chat_viewer_role = 'admin';
require __DIR__ . '/quotation_chat_modal.inc.php';
?>
<script>
window.QT_CHAT_API_URL = <?php echo json_encode('Quotation/qt_message_api.php'); ?>;
window.QT_CHAT_QUOTE_ID = <?php echo (int) $editId; ?>;
window.QT_CHAT_VIEWER_ROLE = 'admin';
window.QT_CHAT_INITIAL_MESSAGES = <?php echo json_encode($aq_manager_comments, JSON_UNESCAPED_UNICODE); ?>;
window.QT_CHAT_OPEN_ON_LOAD = <?php echo $aq_open_chat ? 'true' : 'false'; ?>;
</script>
<script src="Quotation/qt_chat_modal.js?v=<?php echo htmlspecialchars($qt_chat_modal_js_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
