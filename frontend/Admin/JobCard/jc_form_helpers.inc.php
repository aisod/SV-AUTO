<?php

/** Normalized description column header labels (job card / quotation labour table). */
function jc_general_header_labels_from_extra(array $extra): array
{
    if (!empty($extra['general_header_labels']) && is_array($extra['general_header_labels'])) {
        $labels = array_map(static fn($x) => trim((string) $x), $extra['general_header_labels']);

        return array_values(array_filter($labels, static fn($l) => $l !== ''));
    }

    $labels = [];
    $primary = trim((string) ($extra['attend_to_service_label'] ?? $extra['service_header_label'] ?? ''));
    $secondary = trim((string) ($extra['diagnostic_label'] ?? ''));
    if ($primary !== '') {
        $labels[] = $primary;
    }
    if ($secondary !== '') {
        $labels[] = $secondary;
    }

    return $labels;
}

/** @return array{general_header_labels: array<int, string>, attend_to_service_label: string, diagnostic_label: string} */
function jc_general_header_labels_from_post(array $post): array
{
    $labels = [];
    $raw = $post['general_header_labels'] ?? null;
    if (is_array($raw)) {
        $labels = array_map(static fn($x) => trim((string) $x), $raw);
        $labels = array_values(array_filter($labels, static fn($l) => $l !== ''));
    }
    if ($labels === []) {
        $labels = jc_general_header_labels_from_extra([
            'attend_to_service_label' => $post['attend_to_service_label'] ?? '',
            'diagnostic_label' => $post['diagnostic_label'] ?? '',
        ]);
    }

    return [
        'general_header_labels' => $labels,
        'attend_to_service_label' => $labels[0] ?? trim((string) ($post['attend_to_service_label'] ?? '')),
        'diagnostic_label' => $labels[1] ?? trim((string) ($post['diagnostic_label'] ?? '')),
    ];
}

function jc_build_repersist_payload_from_post(array $post): array
{
    $arr = static function (string $key) use ($post): array {
        $v = $post[$key] ?? [];
        if (!is_array($v)) {
            return $v === '' || $v === null ? [] : [(string) $v];
        }

        return array_map(static fn($x) => (string) $x, $v);
    };
    $str = static fn(string $key): string => (string) ($post[$key] ?? '');
    $bool = static function (string $key) use ($post): bool {
        if (!array_key_exists($key, $post)) {
            return false;
        }
        $value = $post[$key];
        if (is_array($value)) {
            $value = reset($value);
        }

        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    };

    $labelFields = jc_general_header_labels_from_post($post);

    return [
        'card_number' => $str('card_number'),
        'client_id' => $str('client_id'),
        'client_name_display' => $str('client_name_display'),
        'contact_no' => $str('contact_no'),
        'contact_email' => $str('contact_email'),
        'to_line1' => $str('to_line1'),
        'to_line2' => $str('to_line2'),
        'to_line3' => $str('to_line3'),
        'contact_person' => $str('contact_person'),
        'job_date' => $str('job_date'),
        'vehicle_id' => $str('vehicle_id'),
        'vin_no' => $str('vin_no'),
        'kilometre' => $str('kilometre'),
        'fleet_no' => $str('fleet_no'),
        'manual_vehicle_reg' => $str('manual_vehicle_reg'),
        'model_reg' => $str('model_reg'),
        'purchase_order_no' => $str('purchase_order_no'),
        'quotation_no' => $str('quotation_no'),
        'invoice_no' => $str('invoice_no'),
        'callout_km' => $str('callout_km'),
        'callout_fee' => $str('callout_fee'),
        'callout_consumables' => $str('callout_consumables'),
        'normal_time' => $str('normal_time'),
        'overtime' => $str('overtime'),
        'sunday_holiday' => $str('sunday_holiday'),
        'technician_no' => $str('technician_no'),
        'rate_normal' => $str('rate_normal'),
        'rate_after' => $str('rate_after'),
        'rate_holiday' => $str('rate_holiday'),
        'vat_rate' => $str('vat_rate'),
        'general_header_labels' => $labelFields['general_header_labels'],
        'attend_to_service_label' => $labelFields['attend_to_service_label'],
        'diagnostic_label' => $labelFields['diagnostic_label'],
        'blank_normal_lines' => $str('blank_normal_lines'),
        'blank_overtime_lines' => $str('blank_overtime_lines'),
        'blank_holiday_lines' => $str('blank_holiday_lines'),
        'blank_parts_lines' => $str('blank_parts_lines'),
        'blank_cons_lines' => $str('blank_cons_lines'),
        'show_normal_time' => $bool('show_normal_time'),
        'show_overtime' => $bool('show_overtime'),
        'show_public_holiday' => $bool('show_public_holiday'),
        'print_blank_show_labour' => $bool('print_blank_show_labour'),
        'print_blank_show_parts' => $bool('print_blank_show_parts'),
        'blank_col_lab_hours' => $bool('blank_col_lab_hours'),
        'blank_col_lab_rate' => $bool('blank_col_lab_rate'),
        'blank_col_lab_total' => $bool('blank_col_lab_total'),
        'blank_col_part_qty' => $bool('blank_col_part_qty'),
        'blank_col_part_cost' => $bool('blank_col_part_cost'),
        'blank_col_part_total' => $bool('blank_col_part_total'),
        'description_lines' => $arr('description_lines'),
        'parts_left' => $arr('parts_left'),
        'parts_right' => $arr('parts_right'),
        'work_details' => $arr('work_details'),
        'time_allocated' => $arr('time_allocated'),
        'labour_desc' => $arr('labour_desc'),
        'labour_hours' => $arr('labour_hours'),
        'labour_rate' => $arr('labour_rate'),
        'labour_total' => $arr('labour_total'),
        'parts_item_name' => $arr('parts_item_name'),
        'parts_qty2' => $arr('parts_qty2'),
        'parts_unit_cost' => $arr('parts_unit_cost'),
        'parts_total2' => $arr('parts_total2'),
        'cons_item_name' => $arr('cons_item_name'),
        'cons_qty' => $arr('cons_qty'),
        'cons_unit_cost' => $arr('cons_unit_cost'),
        'cons_total' => $arr('cons_total'),
        'customer_sig' => $str('customer_sig'),
        'quotation_labour_sections' => (static function () use ($post): array {
            $raw = $post['quotation_labour_sections'] ?? '[]';
            if (is_array($raw)) {
                return $raw;
            }
            $decoded = json_decode((string) $raw, true);

            return is_array($decoded) ? $decoded : [];
        })(),
        'quotation_parts' => (static function () use ($post): array {
            $raw = $post['quotation_parts'] ?? '[]';
            if (is_array($raw)) {
                return $raw;
            }
            $decoded = json_decode((string) $raw, true);

            return is_array($decoded) ? $decoded : [];
        })(),
        'show_consumables' => $bool('show_consumables'),
        'consumables_label' => $str('consumables_label') !== '' ? $str('consumables_label') : 'Call-out',
    ];
}

/**
 * Resolve display client name for a job card row.
 */
function jc_job_card_row_client_name(array $row): string
{
    $client_name = trim((string) ($row['client_name'] ?? ''));
    if ($client_name === '') {
        $extra = json_decode($row['extra_data'] ?? '{}', true);
        if (is_array($extra)) {
            $client_name = trim((string) ($extra['client_name_display'] ?? ''));
        }
    }

    return $client_name !== '' ? $client_name : 'Unknown client';
}

/**
 * Returns conflict metadata when $card_number cannot be used for $exclude_job_id (0 = new card).
 * Checks other job cards plus linked quotations and invoices.
 *
 * @return array{id:int,client_name:string,has_quotation:bool,has_invoice:bool,is_deleted:bool,quotation_id:int,invoice_id:int}|null
 */
function jc_find_job_card_number_conflict(PDO $pdo, string $card_number, int $exclude_job_id = 0): ?array
{
    $card_number = trim($card_number);
    if ($card_number === '') {
        return null;
    }

    $baseSelect = "
        SELECT jc.id,
               COALESCE(NULLIF(TRIM(jc_client.name), ''), NULLIF(TRIM(v_client.name), ''), '') AS client_name,
               jc.extra_data,
               jc.deleted_at,
               (
                   SELECT q.id
                   FROM quotations q
                   WHERE q.job_card_id = jc.id AND q.deleted_at IS NULL
                   ORDER BY q.id DESC
                   LIMIT 1
               ) AS quotation_id,
               (
                   SELECT i.id
                   FROM invoices i
                   INNER JOIN quotations q ON q.id = i.quotation_id AND q.deleted_at IS NULL
                   WHERE q.job_card_id = jc.id AND i.deleted_at IS NULL
                   ORDER BY i.id DESC
                   LIMIT 1
               ) AS invoice_id
        FROM job_cards jc
        LEFT JOIN clients jc_client ON jc.client_id = jc_client.id
        LEFT JOIN vehicles v ON jc.vehicle_id = v.id
        LEFT JOIN clients v_client ON v.client_id = v_client.id
    ";

    $activeStmt = $pdo->prepare($baseSelect . "
        WHERE jc.card_number = ? AND jc.deleted_at IS NULL
        LIMIT 1
    ");
    $activeStmt->execute([$card_number]);
    $activeRow = $activeStmt->fetch(PDO::FETCH_ASSOC);
    if ($activeRow && (int) $activeRow['id'] !== $exclude_job_id) {
        $quotation_id = (int) ($activeRow['quotation_id'] ?? 0);

        return [
            'id' => (int) $activeRow['id'],
            'client_name' => jc_job_card_row_client_name($activeRow),
            'has_quotation' => $quotation_id > 0,
            'has_invoice' => (int) ($activeRow['invoice_id'] ?? 0) > 0,
            'is_deleted' => false,
            'quotation_id' => $quotation_id,
            'invoice_id' => (int) ($activeRow['invoice_id'] ?? 0),
        ];
    }

    $deletedStmt = $pdo->prepare($baseSelect . "
        WHERE jc.card_number = ?
          AND jc.deleted_at IS NOT NULL
          AND jc.id <> ?
          AND (
              EXISTS (
                  SELECT 1 FROM quotations q
                  WHERE q.job_card_id = jc.id AND q.deleted_at IS NULL
              )
              OR EXISTS (
                  SELECT 1
                  FROM invoices i
                  INNER JOIN quotations q ON q.id = i.quotation_id AND q.deleted_at IS NULL
                  WHERE q.job_card_id = jc.id AND i.deleted_at IS NULL
              )
          )
        LIMIT 1
    ");
    $deletedStmt->execute([$card_number, $exclude_job_id]);
    $deletedRow = $deletedStmt->fetch(PDO::FETCH_ASSOC);
    if ($deletedRow) {
        $quotation_id = (int) ($deletedRow['quotation_id'] ?? 0);

        return [
            'id' => (int) $deletedRow['id'],
            'client_name' => jc_job_card_row_client_name($deletedRow),
            'has_quotation' => $quotation_id > 0,
            'has_invoice' => (int) ($deletedRow['invoice_id'] ?? 0) > 0,
            'is_deleted' => true,
            'quotation_id' => $quotation_id,
            'invoice_id' => (int) ($deletedRow['invoice_id'] ?? 0),
        ];
    }

    return null;
}

/** True when quotation row belongs to the given job card. */
function jc_quotation_belongs_to_job_card(PDO $pdo, int $quote_id, int $job_card_id): bool
{
    if ($quote_id <= 0 || $job_card_id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare(
        'SELECT job_card_id FROM quotations WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$quote_id]);

    return (int) ($stmt->fetchColumn() ?: 0) === $job_card_id;
}

/** Ignore quote ids that are not owned by this job card. */
function jc_sanitize_quote_id_for_job_card(PDO $pdo, int $quote_id, int $job_card_id): int
{
    if ($quote_id <= 0 || $job_card_id <= 0) {
        return 0;
    }

    return jc_quotation_belongs_to_job_card($pdo, $quote_id, $job_card_id) ? $quote_id : 0;
}

function jc_redirect_duplicate_job_card(PDO $pdo, string $card_number, int $edit_id): void
{
    $conflict = jc_find_job_card_number_conflict($pdo, $card_number, $edit_id);
    if ($conflict === null) {
        return;
    }

    jc_redirect_job_card_already_used($pdo, $card_number, $edit_id, $conflict, !empty($_POST) ? $_POST : null);
}

/**
 * @param array<string,mixed>|null $repersist_post
 * @param array<string,mixed> $conflict
 */
function jc_redirect_job_card_already_used(
    PDO $pdo,
    string $card_number,
    int $edit_id,
    array $conflict,
    ?array $repersist_post = null
): void {
    $client_name = trim((string) ($conflict['client_name'] ?? ''));
    $msg = "Job card number {$card_number} is already used";
    if ($client_name !== '') {
        $msg .= " (assigned to {$client_name})";
    }
    $msg .= '.';
    if (!empty($conflict['has_quotation'])) {
        $msg .= ' A quotation already exists for this number.';
    }
    if (!empty($conflict['has_invoice'])) {
        $msg .= ' An invoice already exists for this number.';
    }
    if (!empty($conflict['is_deleted'])) {
        $msg .= ' The previous job card is in the recycle bin — restore it instead of reusing this number.';
    } else {
        $msg .= ' Open the existing job card or quotation — do not create or overwrite another document.';
    }

    if ($repersist_post !== null) {
        $_SESSION['jc_form_repersist'] = $repersist_post;
    }

    $existing_quote_id = (int) ($conflict['quotation_id'] ?? 0);
    if ($existing_quote_id <= 0 && !empty($conflict['has_quotation'])) {
        $existing_quote_id = jc_find_active_quotation_id_for_job_card($pdo, (int) $conflict['id']);
    }

    $_SESSION['jc_dup_error'] = [
        'message' => $msg,
        'existing_job_id' => (int) ($conflict['id'] ?? 0),
        'existing_quote_id' => $existing_quote_id,
        'existing_invoice_id' => (int) ($conflict['invoice_id'] ?? 0),
    ];

    $editQ = $edit_id > 0 ? ('?edit_id=' . $edit_id . '&') : '?';
    header('Location: add_job_card.php' . $editQ . 'dup_err=1');
    exit;
}

/** Latest active quotation id linked to a job card (0 if none). */
function jc_find_active_quotation_id_for_job_card(PDO $pdo, int $job_card_id): int
{
    if ($job_card_id <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare(
        'SELECT id FROM quotations WHERE job_card_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$job_card_id]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

/**
 * Quotation on another job card that shares the same card number.
 *
 * @return array{quotation_id:int,job_card_id:int,is_deleted_jc:bool}|null
 */
function jc_find_quotation_for_card_number(PDO $pdo, string $card_number, int $exclude_job_card_id = 0): ?array
{
    $card_number = trim($card_number);
    if ($card_number === '') {
        return null;
    }

    $sql = "
        SELECT q.id AS quotation_id, jc.id AS job_card_id,
               (jc.deleted_at IS NOT NULL) AS is_deleted_jc
        FROM job_cards jc
        INNER JOIN quotations q ON q.job_card_id = jc.id AND q.deleted_at IS NULL
        WHERE jc.card_number = ?
    ";
    $params = [$card_number];
    if ($exclude_job_card_id > 0) {
        $sql .= ' AND jc.id <> ?';
        $params[] = $exclude_job_card_id;
    }
    $sql .= ' ORDER BY q.id DESC LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'quotation_id' => (int) ($row['quotation_id'] ?? 0),
        'job_card_id' => (int) ($row['job_card_id'] ?? 0),
        'is_deleted_jc' => !empty($row['is_deleted_jc']),
    ];
}

/**
 * After saving a job card, open or create its quotation (admin/manager only).
 */
function jc_handle_open_quotation_after_save(
    PDO $pdo,
    int $job_card_id,
    string $card_number,
    int $post_quote_id = 0
): void {
    $role = strtolower((string) ($_SESSION['role_name'] ?? ''));
    if (!in_array($role, ['admin', 'manager'], true)) {
        header(
            'Location: add_job_card.php?edit_id=' . (int) $job_card_id . '&success='
            . rawurlencode("Job card {$card_number} saved. Only admin or manager can open quotations.")
        );
        exit;
    }

    $conflict = jc_find_job_card_number_conflict($pdo, $card_number, $job_card_id);
    if ($conflict !== null) {
        jc_redirect_job_card_already_used($pdo, $card_number, $job_card_id, $conflict);
    }

    require_once __DIR__ . '/../Quotation/quotation_from_job_card.inc.php';

    $post_quote_id = jc_sanitize_quote_id_for_job_card($pdo, $post_quote_id, $job_card_id);
    $quote_id = $post_quote_id > 0
        ? $post_quote_id
        : jc_find_active_quotation_id_for_job_card($pdo, $job_card_id);

    if ($quote_id <= 0) {
        $other = jc_find_quotation_for_card_number($pdo, $card_number, $job_card_id);
        if ($other !== null && $other['quotation_id'] > 0) {
            jc_redirect_job_card_already_used($pdo, $card_number, $job_card_id, [
                'id' => (int) $other['job_card_id'],
                'client_name' => '',
                'has_quotation' => true,
                'has_invoice' => false,
                'is_deleted' => !empty($other['is_deleted_jc']),
                'quotation_id' => (int) $other['quotation_id'],
                'invoice_id' => 0,
            ]);
        }
    }

    if ($quote_id > 0) {
        if (!jc_quotation_belongs_to_job_card($pdo, $quote_id, $job_card_id)) {
            header(
                'Location: add_job_card.php?edit_id=' . (int) $job_card_id . '&error='
                . rawurlencode('Job card number already used on another quotation. Use a different number.')
            );
            exit;
        }
        header(
            'Location: ../Quotation/add_quotation.php?id=' . (int) $quote_id
            . '&success=' . rawurlencode("Job card {$card_number} saved. Opened the linked quotation.")
        );
        exit;
    }

    try {
        $quote_id = sv_auto_create_quotation_from_job_card($pdo, $job_card_id);
        header(
            'Location: ../Quotation/add_quotation.php?id=' . (int) $quote_id
            . '&success=' . rawurlencode("Job card {$card_number} saved. Your quotation is ready to manage.")
        );
        exit;
    } catch (Throwable $qe) {
        error_log('Auto quotation after job card failed: ' . $qe->getMessage());
        $quote_id = jc_find_active_quotation_id_for_job_card($pdo, $job_card_id);
        if ($quote_id > 0 && jc_quotation_belongs_to_job_card($pdo, $quote_id, $job_card_id)) {
            header(
                'Location: ../Quotation/add_quotation.php?id=' . (int) $quote_id
                . '&success=' . rawurlencode("Job card {$card_number} saved. Opened the linked quotation.")
            );
            exit;
        }
        header(
            'Location: add_job_card.php?edit_id=' . (int) $job_card_id . '&error='
            . rawurlencode('Job card saved, but the quotation could not be created. This job card number may already be used.')
        );
        exit;
    }
}

/**
 * Sync quotation from job card (Update Quotation only). Never call on Open Quotation.
 */
function jc_handle_update_quotation_after_save(
    PDO $pdo,
    int $job_card_id,
    string $card_number,
    int $post_quote_id = 0
): void {
    $role = strtolower((string) ($_SESSION['role_name'] ?? ''));
    if (!in_array($role, ['admin', 'manager'], true)) {
        header(
            'Location: add_job_card.php?edit_id=' . (int) $job_card_id . '&error='
            . rawurlencode('Only admin or manager can update quotations.')
        );
        exit;
    }

    $conflict = jc_find_job_card_number_conflict($pdo, $card_number, $job_card_id);
    if ($conflict !== null) {
        jc_redirect_job_card_already_used($pdo, $card_number, $job_card_id, $conflict);
    }

    require_once __DIR__ . '/../Quotation/quotation_from_job_card.inc.php';

    $post_quote_id = jc_sanitize_quote_id_for_job_card($pdo, $post_quote_id, $job_card_id);
    $quote_id = $post_quote_id > 0
        ? $post_quote_id
        : jc_find_active_quotation_id_for_job_card($pdo, $job_card_id);

    if ($quote_id > 0 && !jc_quotation_belongs_to_job_card($pdo, $quote_id, $job_card_id)) {
        header(
            'Location: add_job_card.php?edit_id=' . (int) $job_card_id . '&error='
            . rawurlencode('Job card number already used. This quotation belongs to another job card.')
        );
        exit;
    }

    try {
        if ($quote_id > 0) {
            sv_auto_update_quotation_from_job_card($pdo, $quote_id, $job_card_id);
            $target_quote_id = $quote_id;
        } else {
            $other = jc_find_quotation_for_card_number($pdo, $card_number, $job_card_id);
            if ($other !== null && $other['quotation_id'] > 0) {
                jc_redirect_job_card_already_used($pdo, $card_number, $job_card_id, [
                    'id' => (int) $other['job_card_id'],
                    'client_name' => '',
                    'has_quotation' => true,
                    'has_invoice' => false,
                    'is_deleted' => !empty($other['is_deleted_jc']),
                    'quotation_id' => (int) $other['quotation_id'],
                    'invoice_id' => 0,
                ]);
            }
            $target_quote_id = sv_auto_create_quotation_from_job_card($pdo, $job_card_id);
        }
        header(
            'Location: ../Quotation/add_quotation.php?id=' . (int) $target_quote_id
            . '&success=' . rawurlencode('Quotation successfully updated.')
        );
        exit;
    } catch (Throwable $qe) {
        error_log('Update quotation from job card failed: ' . $qe->getMessage());
        header(
            'Location: add_job_card.php?edit_id=' . (int) $job_card_id
            . '&quote_id=' . (int) $quote_id
            . '&error=' . rawurlencode('Failed to update quotation. This job card number may already be used on another document.')
        );
        exit;
    }
}

function jc_consume_duplicate_flash(): array
{
    $out = ['message' => '', 'existing_job_id' => 0, 'existing_quote_id' => 0, 'repersist_payload' => null];

    if (empty($_GET['dup_err']) || empty($_SESSION['jc_dup_error']) || !is_array($_SESSION['jc_dup_error'])) {
        return $out;
    }

    $flash = $_SESSION['jc_dup_error'];
    unset($_SESSION['jc_dup_error']);

    $out['message'] = (string) ($flash['message'] ?? '');
    $out['existing_job_id'] = (int) ($flash['existing_job_id'] ?? 0);
    $out['existing_quote_id'] = (int) ($flash['existing_quote_id'] ?? 0);

    if (!empty($_SESSION['jc_form_repersist']) && is_array($_SESSION['jc_form_repersist'])) {
        $out['repersist_payload'] = jc_build_repersist_payload_from_post($_SESSION['jc_form_repersist']);
        unset($_SESSION['jc_form_repersist']);
    }

    return $out;
}
