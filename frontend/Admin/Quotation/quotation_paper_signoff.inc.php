<?php
declare(strict_types=1);

if (!defined('Q_JSON_MARKER')) {
    define('Q_JSON_MARKER', "QUOTATION_JSON_V1\n");
}

/** @return array<string,mixed>|null */
function qt_parse_quotation_details(?string $details): ?array
{
    if ($details === null || $details === '') {
        return null;
    }
    if (strpos($details, Q_JSON_MARKER) !== 0) {
        return null;
    }
    $json = substr($details, strlen(Q_JSON_MARKER));
    $summaryPos = strpos($json, "\n\n— Line summary —");
    if ($summaryPos !== false) {
        $json = substr($json, 0, $summaryPos);
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/** @param array<string,mixed> $form */
function qt_paper_signoff_info(array $form): array
{
    $at = trim((string) ($form['paper_signed_at'] ?? $form['approved_at'] ?? ''));
    $by = trim((string) ($form['paper_signed_by'] ?? $form['approved_by'] ?? ''));
    return [
        'signed' => $at !== '',
        'at' => $at,
        'by' => $by,
    ];
}

/** @param array<string,mixed> $form */
function qt_paper_rejection_info(array $form): array
{
    $at = trim((string) ($form['rejected_at'] ?? ''));
    $by = trim((string) ($form['rejected_by'] ?? ''));
    $reason = trim((string) ($form['rejection_reason'] ?? ''));
    return [
        'rejected' => $at !== '',
        'at' => $at,
        'by' => $by,
        'reason' => $reason,
    ];
}

function qt_format_paper_signoff_date(string $at): string
{
    if ($at === '') {
        return '';
    }
    $ts = strtotime($at);
    return $ts ? date('d M Y', $ts) : $at;
}

/**
 * @param array<string,string> $formUpdates
 */
function qt_persist_details_form_fields(string $details, array $formUpdates): ?string
{
    $payload = qt_parse_quotation_details($details);
    if (!$payload || !isset($payload['form']) || !is_array($payload['form'])) {
        return null;
    }
    foreach ($formUpdates as $key => $value) {
        $payload['form'][$key] = $value;
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return null;
    }
    $summaryPos = strpos($details, "\n\n— Line summary —");
    $suffix = $summaryPos !== false ? substr($details, $summaryPos) : '';
    return Q_JSON_MARKER . $json . $suffix;
}

function qt_update_paper_signoff(PDO $pdo, int $quoteId, string $signedAt, string $signedBy): bool
{
    $stmt = $pdo->prepare('SELECT details, status, client_status FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$quoteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $details = (string) ($row['details'] ?? '');
    $dbStatus = strtolower(trim((string) ($row['status'] ?? '')));
    $clientStatus = trim((string) ($row['client_status'] ?? ''));
    $formPayload = qt_parse_quotation_details($details);
    $formStatus = '';
    if (is_array($formPayload) && isset($formPayload['form']) && is_array($formPayload['form'])) {
        $formStatus = strtolower(trim((string) ($formPayload['form']['status'] ?? '')));
    }

    $isSigning = trim($signedAt) !== '';
    $updates = [
        'approved_at' => $signedAt,
        'approved_by' => $signedBy,
        'paper_signed_at' => $signedAt,
        'paper_signed_by' => $signedBy,
    ];

    if ($isSigning) {
        $updates['status'] = 'approved';
        $updates['rejected_at'] = '';
        $updates['rejected_by'] = '';
        $updates['rejection_reason'] = '';
        $newDbStatus = 'approved';
    } else {
        $newDbStatus = $dbStatus;
        if ($dbStatus === 'approved' && $clientStatus === '' && $formStatus !== 'sent_to_client') {
            $updates['status'] = 'pending_manager';
            $newDbStatus = 'pending_manager';
        }
    }

    $newDetails = qt_persist_details_form_fields($details, $updates);
    if ($newDetails === null) {
        return false;
    }
    $upd = $pdo->prepare('UPDATE quotations SET details = ?, status = ? WHERE id = ?');
    $upd->execute([$newDetails, $newDbStatus, $quoteId]);
    return true;
}

function qt_clear_paper_signoff(PDO $pdo, int $quoteId): bool
{
    return qt_update_paper_signoff($pdo, $quoteId, '', '');
}

function qt_update_paper_rejection(PDO $pdo, int $quoteId, string $rejectedAt, string $rejectedBy, string $reason): bool
{
    $stmt = $pdo->prepare('SELECT details, status, client_status FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$quoteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return false;
    }
    $details = (string) ($row['details'] ?? '');
    $dbStatus = strtolower(trim((string) ($row['status'] ?? '')));
    $clientStatus = trim((string) ($row['client_status'] ?? ''));
    $isRejecting = trim($rejectedAt) !== '';

    $updates = [
        'rejected_at' => $rejectedAt,
        'rejected_by' => $rejectedBy,
        'rejection_reason' => $reason,
        'approved_at' => '',
        'approved_by' => '',
        'paper_signed_at' => '',
        'paper_signed_by' => '',
    ];

    if ($isRejecting) {
        $updates['status'] = 'rejected';
        $newDbStatus = 'rejected';
    } else {
        $newDbStatus = $dbStatus === 'rejected' ? 'pending_manager' : $dbStatus;
        if ($newDbStatus === 'rejected') {
            $newDbStatus = 'pending_manager';
        }
        $updates['status'] = $newDbStatus;
        if ($clientStatus !== '' && $dbStatus === 'approved') {
            $newDbStatus = 'approved';
            $updates['status'] = 'approved';
        }
    }

    $newDetails = qt_persist_details_form_fields($details, $updates);
    if ($newDetails === null) {
        return false;
    }
    $upd = $pdo->prepare('UPDATE quotations SET details = ?, status = ? WHERE id = ?');
    $upd->execute([$newDetails, $newDbStatus, $quoteId]);
    return true;
}

function qt_clear_paper_rejection(PDO $pdo, int $quoteId): bool
{
    return qt_update_paper_rejection($pdo, $quoteId, '', '', '');
}

/**
 * Paper sign-off recorded in details but system status not yet approved (legacy rows).
 */
function qt_sync_paper_signoff_system_status(PDO $pdo): int
{
    $stmt = $pdo->query('SELECT id, status, client_status, details FROM quotations WHERE deleted_at IS NULL');
    $fixed = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dbStatus = strtolower(trim((string) ($row['status'] ?? '')));
        if (in_array($dbStatus, ['approved', 'rejected'], true)) {
            continue;
        }
        $payload = qt_parse_quotation_details((string) ($row['details'] ?? ''));
        $form = (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) ? $payload['form'] : [];
        $paper = qt_paper_signoff_info($form);
        if (!$paper['signed']) {
            continue;
        }
        $formStatus = strtolower(trim((string) ($form['status'] ?? '')));
        if ($formStatus === 'sent_to_client') {
            continue;
        }
        if (qt_update_paper_signoff($pdo, (int) $row['id'], $paper['at'], $paper['by'])) {
            $fixed++;
        }
    }
    return $fixed;
}

/**
 * Admin workflow steps for one quotation (print → paper sign → record → send).
 *
 * @param array<string,mixed> $form Quotation form payload
 * @return array{steps: list<array{id:string,label:string,short:string,done:bool,current:bool}>, next_label: string, next_hint: string}
 */
function qt_workflow_steps(string $status, array $form, ?string $clientStatus = null, bool $printedForSignoff = false): array
{
    $paper = qt_paper_signoff_info($form);
    $rejection = qt_paper_rejection_info($form);
    $mgrReviewSent = trim((string) ($form['manager_review_sent_at'] ?? '')) !== '';
    $paperDone = $paper['signed'];
    $status = strtolower(trim($status));
    $clientStatus = $clientStatus !== null ? trim($clientStatus) : '';

    if ($status === 'rejected' || $rejection['rejected']) {
        return [
            'steps' => [
                ['id' => 'print', 'label' => 'Print quotation', 'short' => 'Print', 'done' => true, 'current' => false],
                ['id' => 'manager', 'label' => 'Manager declined on paper', 'short' => 'Declined', 'done' => true, 'current' => false],
                ['id' => 'record', 'label' => 'Rejection recorded', 'short' => 'Recorded', 'done' => true, 'current' => false],
                ['id' => 'send', 'label' => 'Send to client / invoice', 'short' => 'Send', 'done' => false, 'current' => false],
            ],
            'next_label' => 'Manager rejected this quotation',
            'next_hint' => 'Revise the quotation or start a new one if needed. Do not send to client.',
        ];
    }

    $printDone = $printedForSignoff || $paperDone;
    $managerDone = $paperDone;
    $recordDone = $paperDone;
    $sentDone = $status === 'sent_to_client'
        || $clientStatus === 'client_accepted'
        || $clientStatus === 'client_rejected';

    $steps = [
        [
            'id' => 'print',
            'label' => 'Print quotation',
            'short' => 'Print',
            'done' => $printDone,
            'current' => false,
        ],
        [
            'id' => 'manager',
            'label' => 'Manager signs paper',
            'short' => 'Signed',
            'done' => $managerDone,
            'current' => false,
        ],
        [
            'id' => 'record',
            'label' => 'Record sign-off here',
            'short' => 'Record',
            'done' => $recordDone,
            'current' => false,
        ],
        [
            'id' => 'send',
            'label' => 'Send to client / invoice',
            'short' => 'Send',
            'done' => $sentDone,
            'current' => false,
        ],
    ];

    $nextLabel = 'All done for this quotation';
    $nextHint = 'You can still edit details or create an invoice if needed.';

    if (!$mgrReviewSent) {
        $nextLabel = 'Send to manager for review';
        $nextHint = 'Click Send to manager for review so they can preview the PDF before you print for paper sign-off.';
    } elseif (!$printDone) {
        $steps[0]['current'] = true;
        $nextLabel = 'Print the quotation';
        $nextHint = 'Click Preview or Print Blank, give it to the manager to sign.';
    } elseif (!$recordDone) {
        $steps[1]['current'] = true;
        $steps[2]['current'] = true;
        $nextLabel = 'Record paper sign-off';
        $nextHint = 'Enter the date and manager name from the signed printout, then click Save sign-off.';
    } elseif (!$sentDone) {
        $steps[3]['current'] = true;
        $nextLabel = 'Send to client or create invoice';
        $nextHint = 'Use Send to Client or Create Invoice when you are ready.';
    } else {
        foreach ($steps as $i => $step) {
            $steps[$i]['done'] = true;
            $steps[$i]['current'] = false;
        }
    }

    return [
        'steps' => $steps,
        'next_label' => $nextLabel,
        'next_hint' => $nextHint,
    ];
}

/** @return array{steps: list<array<string,mixed>>, next_label: string, next_hint: string} */
function qt_workflow_from_quotation_row(array $row): array
{
    $payload = qt_parse_quotation_details((string) ($row['details'] ?? ''));
    $form = (is_array($payload) && isset($payload['form']) && is_array($payload['form'])) ? $payload['form'] : [];
    $status = (string) ($row['status'] ?? ($form['status'] ?? 'draft'));
    $clientStatus = isset($row['client_status']) ? (string) $row['client_status'] : null;
    return qt_workflow_steps($status, $form, $clientStatus, false);
}

/** @return array{status:string,client_status:string,paper_signed:bool,form_status:string} */
function qt_quotation_list_context(array $qRow): array
{
    return [
        'status' => strtolower(trim((string) ($qRow['status'] ?? ''))),
        'client_status' => trim((string) ($qRow['client_status'] ?? '')),
        'paper_signed' => !empty($qRow['paper_signed']),
        'form_status' => strtolower(trim((string) ($qRow['form_status'] ?? ''))),
    ];
}

function qt_is_pending_manager_status(string $status): bool
{
    return in_array($status, ['pending', 'pending_manager', 'sent_back_admin'], true);
}

/** @param array{status:string,client_status:string,paper_signed:bool,form_status:string} $ctx */
function qt_matches_paper_filter(array $ctx, string $filter): bool
{
    $status = $ctx['status'];
    if ($filter === 'all') {
        return true;
    }
    if ($status === 'rejected') {
        return false;
    }
    if ($filter === 'paper_pending') {
        return !$ctx['paper_signed'] && qt_is_pending_manager_status($status);
    }
    if ($filter === 'paper_signed') {
        return $ctx['paper_signed'] && $status === 'approved';
    }
    if ($filter === 'awaiting_client') {
        return $status === 'approved'
            && $ctx['paper_signed']
            && $ctx['client_status'] === ''
            && $ctx['form_status'] !== 'sent_to_client';
    }
    return false;
}

/** @param array{status:string,client_status:string,paper_signed:bool,form_status:string} $ctx */
function qt_matches_system_filter(array $ctx, string $filter): bool
{
    $status = $ctx['status'];
    if ($filter === 'all') {
        return true;
    }
    if ($filter === 'pending_group') {
        return qt_is_pending_manager_status($status);
    }
    if ($filter === 'approved') {
        return $status === 'approved';
    }
    if ($filter === 'rejected') {
        return $status === 'rejected';
    }
    return false;
}

/**
 * Same rules as overview cards and list filters (paper + system + optional period).
 *
 * @param array{status:string,client_status:string,paper_signed:bool,form_status:string} $ctx
 * @param array<string,mixed> $card
 */
function qt_matches_dashboard_card(array $ctx, array $card, ?string $submittedAt, callable $periodFn): bool
{
    $tab = array_key_exists('tab', $card) && $card['tab'] !== null ? (string) $card['tab'] : '';
    $systemTabs = isset($card['system_tabs']) ? (string) $card['system_tabs'] : '';
    $period = isset($card['period']) ? (string) $card['period'] : '';

    if ($period !== '' && !$periodFn($submittedAt, $period)) {
        return false;
    }

    if ($tab === 'all') {
        return true;
    }

    if ($tab === '') {
        if ($systemTabs === '' || strtolower($systemTabs) === 'all') {
            return $period !== '';
        }
        foreach (array_map('trim', explode(',', $systemTabs)) as $systemFilter) {
            if ($systemFilter !== '' && qt_matches_system_filter($ctx, $systemFilter)) {
                return true;
            }
        }
        return false;
    }

    if (!qt_matches_paper_filter($ctx, $tab)) {
        return false;
    }

    if ($systemTabs === '' || strtolower($systemTabs) === 'all') {
        return true;
    }

    foreach (array_map('trim', explode(',', $systemTabs)) as $systemFilter) {
        if ($systemFilter !== '' && qt_matches_system_filter($ctx, $systemFilter)) {
            return true;
        }
    }

    return false;
}

/** @param list<array<string,mixed>> $quotations */
function qt_count_dashboard_card(array $quotations, array $card, callable $periodFn): int
{
    $count = 0;
    foreach ($quotations as $qRow) {
        $ctx = qt_quotation_list_context($qRow);
        $submittedAt = isset($qRow['date']) ? (string) $qRow['date'] : null;
        if (qt_matches_dashboard_card($ctx, $card, $submittedAt, $periodFn)) {
            $count++;
        }
    }
    return $count;
}
