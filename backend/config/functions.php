<?php
// config/functions.php
// REQUIRED ON EVERY PAGE AFTER config.php

if (defined('FUNCTIONS_LOADED')) {
    return; // Prevent double-loading
}
define('FUNCTIONS_LOADED', true);

// GET CURRENT CURRENCY
function getCurrency(): array
{
    static $currency = null;
    if ($currency !== null) return $currency;

    try {
        global $pdo;
        $currencyId = (int)($pdo->query("SELECT value FROM settings WHERE `key` = 'default_currency_id'")->fetchColumn() ?: 1);
        $stmt = $pdo->prepare("SELECT id, code, name, symbol, format FROM currencies WHERE id = ? AND is_active = 1");
        $stmt->execute([$currencyId]);
        $currency = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'id' => 1, 'code' => 'NAD', 'name' => 'Namibian Dollar', 'symbol' => 'N$', 'format' => 'left'
        ];
    } catch (Exception $e) {
        $currency = ['id' => 1, 'code' => 'NAD', 'name' => 'Namibian Dollar', 'symbol' => 'N$', 'format' => 'left'];
    }
    return $currency;
}

// FORMAT MONEY
function formatMoney(float $amount): string
{
    $cur = getCurrency();
    $formatted = number_format(abs($amount), 2, '.', ',');
    $money = $cur['format'] === 'left' ? $cur['symbol'] . $formatted : $formatted . ' ' . $cur['symbol'];
    return $amount < 0 ? '-' . $money : $money;
}

// GET BUSINESS INFO
function getBusiness(): array
{
    static $business = null;
    if ($business !== null) return $business;

    try {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM business ORDER BY id ASC LIMIT 1");
        $business = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'name' => 'SV Auto Services',
            'email' => 'info@svauto.com',
            'phone' => '+264 61 123 4567',
            'address' => '123 Main Street, Windhoek',
            'tax_number' => 'NAM123456789',
            'logo_url' => null,
            'website' => '',
            'slogan' => 'Quality Service You Can Trust'
        ];
    } catch (Exception $e) {
        $business = [
            'name' => 'SV Auto Services',
            'email' => 'info@svauto.com',
            'phone' => '+264 61 123 4567',
            'address' => '123 Main Street, Windhoek',
            'tax_number' => 'NAM123456789',
            'logo_url' => null,
            'website' => '',
            'slogan' => 'Quality Service You Can Trust'
        ];
    }
    return $business;
}

/** Build a cache-busted public asset URL relative to the current script. */
function publicAssetUrl(string $relativePath): string
{
    $frontendRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend';
    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
    $assetFile = $frontendRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? __FILE__);
    $frontendRootNorm = str_replace('\\', '/', realpath($frontendRoot) ?: $frontendRoot);
    $scriptDir = str_replace('\\', '/', dirname($scriptFile));

    $depth = 0;
    $current = rtrim($scriptDir, '/');
    $root = rtrim($frontendRootNorm, '/');
    while ($current !== '' && $current !== $root && $depth < 12) {
        $current = dirname($current);
        $depth++;
    }

    $url = ($depth > 0 ? str_repeat('../', $depth) : '') . $relativePath;
    if (is_file($assetFile)) {
        $url .= '?v=' . filemtime($assetFile);
    }

    return $url;
}

/** Absolute filesystem path to the project root (parent of backend/ and frontend/). */
function erp_project_root(): string
{
    return dirname(__DIR__, 2);
}

/** Light + dark logos for auth pages (jpeg on light UI, png on dark UI). */
function publicAuthLogoUrls(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $lightFile = 'assets/images/companylogo.jpeg';
    $darkFile = 'assets/images/companylogo2.png';
    $frontendRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'frontend';

    if (!is_file($frontendRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $lightFile))) {
        $lightFile = $darkFile;
    }

    $cached = [
        'light' => publicAssetUrl($lightFile),
        'dark'  => publicAssetUrl($darkFile),
    ];

    return $cached;
}

/** Auth page logo link — light jpeg in light mode, companylogo2 in dark mode. */
function renderAuthLogoLink(string $href): void
{
    $logos = publicAuthLogoUrls();
    $hrefEsc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
    $lightEsc = htmlspecialchars($logos['light'], ENT_QUOTES, 'UTF-8');
    $darkEsc = htmlspecialchars($logos['dark'], ENT_QUOTES, 'UTF-8');
    echo '<a href="' . $hrefEsc . '" class="login-logo" aria-label="SV Auto Truck Repair — Home">';
    echo '<img class="login-logo__img login-logo__img--light" src="' . $lightEsc . '" alt="SV Auto Truck Repair">';
    echo '<img class="login-logo__img login-logo__img--dark" src="' . $darkEsc . '" alt="SV Auto Truck Repair">';
    echo '</a>';
}

/** Web path to the primary company logo (assets/images/companylogo2.png), with cache-busting. */
function publicCompanyLogoUrl(): string
{
    return publicAuthLogoUrls()['dark'];
}

// GET ANY SETTING (ONLY ONE VERSION — THIS IS THE MASTER!)
function getSetting(string $key, $default = '')
{
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

// QUICK HELPERS
function getTaxRate(): float     { return (float)getSetting('tax_rate', 15); }
function getInvoicePrefix(): string { return getSetting('invoice_prefix', 'INV'); }
function getDateFormat(): string   { return getSetting('date_format', 'dd/mm/yyyy'); }


// ============================================================================
// LABOR RATES CALCULATION FUNCTIONS
// ============================================================================

/**
 * Get labor rate for a specific date and time
 * Checks: holidays > weekends > after hours > normal hours
 * 
 * @param string $date Date in Y-m-d format
 * @param string $time Time in H:i:s format
 * @param int|null $clientId Optional client ID for custom rates
 * @return array ['rate' => float, 'type' => string, 'description' => string]
 */
function getLaborRate($date, $time, $clientId = null) {
    global $pdo;
    
    try {
        // Check if it's a public holiday
        $holidayStmt = $pdo->prepare("
            SELECT holiday_name FROM public_holidays 
            WHERE holiday_date = ? AND is_active = 1
            LIMIT 1
        ");
        $holidayStmt->execute([$date]);
        $holiday = $holidayStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($holiday) {
            $rate = getClientRate($clientId, 'holiday') ?? getDefaultRate('holiday');
            return [
                'rate' => $rate,
                'type' => 'holiday',
                'description' => 'Public Holiday (' . $holiday['holiday_name'] . ')'
            ];
        }
        
        // Check if it's a weekend
        $dayOfWeek = date('N', strtotime($date)); // 1=Monday, 7=Sunday
        if ($dayOfWeek >= 6) { // Saturday or Sunday
            $rate = getClientRate($clientId, 'weekend') ?? getDefaultRate('weekend');
            return [
                'rate' => $rate,
                'type' => 'weekend',
                'description' => 'Weekend Rate'
            ];
        }
        
        // Check if it's after hours (before 8am or after 5pm)
        $hour = (int)date('H', strtotime($time));
        if ($hour < 8 || $hour >= 17) {
            $rate = getClientRate($clientId, 'after_hours') ?? getDefaultRate('after_hours');
            return [
                'rate' => $rate,
                'type' => 'after_hours',
                'description' => 'After Hours Rate'
            ];
        }
        
        // Normal working hours
        $rate = getClientRate($clientId, 'normal_hours') ?? getDefaultRate('normal_hours');
        return [
            'rate' => $rate,
            'type' => 'normal_hours',
            'description' => 'Normal Hours Rate'
        ];
        
    } catch (Exception $e) {
        // Fallback to default normal rate
        return [
            'rate' => 150.00,
            'type' => 'normal_hours',
            'description' => 'Normal Hours Rate (Default)'
        ];
    }
}

/**
 * Get default labor rate by type
 */
function getDefaultRate($rateType) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT hourly_rate FROM labor_rates WHERE rate_type = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$rateType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['hourly_rate'] : 150.00;
    } catch (Exception $e) {
        return 150.00;
    }
}

/**
 * Get client-specific custom rate (if exists)
 */
function getClientRate($clientId, $rateType) {
    if (!$clientId) return null;
    
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT custom_hourly_rate FROM client_custom_rates 
            WHERE client_id = ? AND rate_type = ? AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$clientId, $rateType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['custom_hourly_rate'] : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get mobile service rates
 */
function getMobileServiceRates() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT * FROM mobile_service_rates WHERE is_active = 1 LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row : [
            'callout_fee' => 500.00,
            'per_km_rate' => 15.00,
            'min_callout_distance' => 5.00
        ];
    } catch (Exception $e) {
        return [
            'callout_fee' => 500.00,
            'per_km_rate' => 15.00,
            'min_callout_distance' => 5.00
        ];
    }
}

/**
 * Calculate total labor cost for a job
 * 
 * @param string $date Work date
 * @param string $startTime Start time
 * @param string $endTime End time
 * @param float $totalHours Total hours worked
 * @param int|null $clientId Client ID for custom rates
 * @return array ['labor_cost' => float, 'rate_applied' => float, 'rate_description' => string]
 */
function calculateLaborCost($date, $startTime, $endTime, $totalHours, $clientId = null) {
    // Use start time to determine rate
    $rateInfo = getLaborRate($date, $startTime, $clientId);
    $laborCost = $totalHours * $rateInfo['rate'];
    
    return [
        'labor_cost' => round($laborCost, 2),
        'rate_applied' => $rateInfo['rate'],
        'rate_description' => $rateInfo['description']
    ];
}

/**
 * Calculate mobile service costs
 * 
 * @param float $distanceKm Distance traveled in kilometers
 * @return array ['callout_fee' => float, 'travel_cost' => float, 'total_mobile_cost' => float]
 */
function calculateMobileCost($distanceKm) {
    $rates = getMobileServiceRates();
    
    $calloutFee = ($distanceKm >= $rates['min_callout_distance']) ? $rates['callout_fee'] : 0;
    $travelCost = $distanceKm * $rates['per_km_rate'];
    
    return [
        'callout_fee' => round($calloutFee, 2),
        'travel_cost' => round($travelCost, 2),
        'total_mobile_cost' => round($calloutFee + $travelCost, 2)
    ];
}

/**
 * Calculate hours between two times
 */
function calculateHours($startTime, $endTime) {
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    
    if ($end < $start) {
        // Crossed midnight
        $end += 86400; // Add 24 hours
    }
    
    $seconds = $end - $start;
    return round($seconds / 3600, 2); // Convert to hours with 2 decimals
}


// -------------------- Invoice helpers --------------------
function create_invoice_from_quotation($quotationId, $invoiceNumber = null)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT q.*, c.id as client_id FROM quotations q LEFT JOIN clients c ON q.client_id = c.id WHERE q.id = ? AND q.deleted_at IS NULL LIMIT 1");
        $stmt->execute([$quotationId]);
        $quotation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$quotation) return null;

        $details = $quotation['details'] ?? '';
        $quoteData = [];
        if (strpos($details, "QUOTATION_JSON_V1\n") === 0) {
            $json = substr($details, strlen("QUOTATION_JSON_V1\n"));
            $summaryPos = strpos($json, "\n\n— Line summary —");
            if ($summaryPos !== false) $json = substr($json, 0, $summaryPos);
            $decoded = json_decode($json, true);
            if (is_array($decoded)) $quoteData = $decoded;
        }

        if (!$invoiceNumber) {
            $prefix = 'INV-';
            $latest = (int)$pdo->query("SELECT MAX(id) FROM invoices")->fetchColumn();
            $invoiceNumber = $prefix . date('Y') . '-' . str_pad($latest + 1, 4, '0', STR_PAD_LEFT);
        }

        $subtotal = (float)($quoteData['totals']['subtotal'] ?? $quotation['amount'] ?? 0);
        $vatAmount = (float)($quoteData['totals']['vat_amount'] ?? 0);
        $total = (float)($quoteData['totals']['grand_total'] ?? $quotation['amount'] ?? 0);
        $issuedDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+30 days'));

        $pdo->beginTransaction();

        $colsStmt = $pdo->query('SHOW COLUMNS FROM invoices');
        $invoiceCols = [];
        while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $invoiceCols[(string) $col['Field']] = true;
        }
        $data = [];
        if (isset($invoiceCols['quotation_id'])) {
            $data['quotation_id'] = $quotationId;
        }
        if (isset($invoiceCols['client_id']) && !empty($quotation['client_id'])) {
            $data['client_id'] = (int) $quotation['client_id'];
        }
        if (isset($invoiceCols['amount'])) {
            $data['amount'] = $total;
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
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (isset($invoiceCols['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        if ($data === []) {
            throw new Exception('Invoices table has no supported columns.');
        }
        $fields = array_keys($data);
        $sql = 'INSERT INTO invoices (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
        $ins = $pdo->prepare($sql);
        $ins->execute(array_values($data));
        $invoiceId = (int) $pdo->lastInsertId();

        $labour = $quoteData['labour_rows'] ?? [];
        foreach ($labour as $row) {
            if (empty($row['include_in_print'])) continue;
            $it = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $it->execute([
                $invoiceId,
                $row['description'] ?? 'Labour',
                (float)($row['hours'] ?? 1),
                (float)($row['rate'] ?? 0),
                (float)($row['total'] ?? 0)
            ]);
        }

        $parts = array_merge($quoteData['parts_rows'] ?? [], $quoteData['cons_rows'] ?? []);
        foreach ($parts as $row) {
            if (empty($row['include_in_print'])) continue;
            $it = $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $it->execute([
                $invoiceId,
                $row['description'] ?? 'Part',
                (float)($row['quantity'] ?? 1),
                (float)($row['unit_price'] ?? $row['price'] ?? 0),
                (float)($row['total'] ?? 0)
            ]);
        }

        $pdo->commit();
        return $invoiceId;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Record the error message for callers to inspect (useful for debugging)
        $msg = $e->getMessage();
        error_log("create_invoice_from_quotation error: " . $msg);
        // Expose the last create-invoice error in a global variable so the
        // controller (create_from_quotation.php) can show a helpful message
        // during development. Do not expose this in production without
        // sanitization. It's intentionally non-fatal here.
        $GLOBALS['LAST_CREATE_INVOICE_ERROR'] = $msg;
        return null;
    }
}

function get_invoice($invoiceId)
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT i.*, q.details as quote_details, c.name as client_name, c.email as client_email, c.phone as client_phone, c.address as client_address, c.contact_person as contact_person_primary, c.contact_person_secondary as contact_person_secondary FROM invoices i LEFT JOIN quotations q ON i.quotation_id = q.id LEFT JOIN clients c ON c.id = q.client_id WHERE i.id = ? LIMIT 1");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$invoice) return null;

        $invoice['items'] = [];
        try {
            $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY created_at ASC");
            $itemStmt->execute([$invoiceId]);
            $invoice['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $itemEx) {
            error_log('get_invoice items load (non-fatal): ' . $itemEx->getMessage());
        }

        return $invoice;
    } catch (Exception $e) {
        error_log("get_invoice error: " . $e->getMessage());
        return null;
    }
}

function list_invoices($limit = 20, $offset = 0, $status = '')
{
    global $pdo;
    try {
        $sql = "SELECT i.id, i.invoice_number, i.amount, i.issued_date, i.due_date, i.status_paid, c.name as client_name FROM invoices i LEFT JOIN quotations q ON i.quotation_id = q.id LEFT JOIN clients c ON c.id = q.client_id WHERE 1=1";
        $params = [];
        if ($status && in_array($status, ['unpaid','partial','paid'])) {
            $sql .= " AND i.status_paid = ?";
            $params[] = $status;
        }
        $sql .= " ORDER BY i.issued_date DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("list_invoices error: " . $e->getMessage());
        return [];
    }
}

function mark_invoice_paid($invoiceId, $paidStatus = 'paid', $amountPaid = null)
{
    global $pdo;
    try {
        if (!in_array($paidStatus, ['paid','partial','unpaid'])) return false;
        $paidAt = ($paidStatus === 'paid' || $paidStatus === 'partial') ? date('Y-m-d H:i:s') : null;
        $stmt = $pdo->prepare("UPDATE invoices SET status_paid = ?, paid_at = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$paidStatus, $paidAt, $invoiceId]);
    } catch (Exception $e) {
        error_log("mark_invoice_paid error: " . $e->getMessage());
        return false;
    }
}

/** Ensure users.avatar_url exists (safe to call repeatedly). */
function erp_ensure_user_avatar_column(): void
{
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_url'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec('ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL DEFAULT NULL AFTER email');
        }
    } catch (Exception $e) {
        error_log('erp_ensure_user_avatar_column: ' . $e->getMessage());
    }
}

/** Web path relative to project root, e.g. uploads/avatars/user_1.jpg */
function erp_get_user_avatar_path(int $userId): ?string
{
    global $pdo;
    if ($userId <= 0) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT avatar_url FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $path = $stmt->fetchColumn();
        return (is_string($path) && $path !== '') ? $path : null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Locate avatar on disk (project uploads/ or legacy frontend/uploads/).
 *
 * @return array{disk:string,url:string}|null
 */
function erp_user_avatar_resolve(?string $avatarWebPath, string $adminRelPrefix = ''): ?array
{
    if (!$avatarWebPath) {
        return null;
    }
    $rel = ltrim(str_replace('\\', '/', (string) $avatarWebPath), '/');
    $root = erp_project_root();
    // URLs are relative to Admin <base href>, not the current script folder.
    $candidates = [
        ['disk' => $root . '/uploads/' . preg_replace('#^uploads/#', '', $rel), 'url' => '../../' . $rel],
        ['disk' => $root . '/frontend/' . $rel, 'url' => '../' . $rel],
        ['disk' => dirname(__DIR__) . '/' . $rel, 'url' => '../../' . $rel],
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate['disk'])) {
            return $candidate;
        }
    }

    return null;
}

/** Full disk path to avatar file, or null if missing. */
function erp_user_avatar_disk_path(?string $avatarWebPath): ?string
{
    $resolved = erp_user_avatar_resolve($avatarWebPath);

    return $resolved ? $resolved['disk'] : null;
}

/** Resolved img src for Admin pages (null if file missing). */
function erp_user_avatar_src(?string $avatarWebPath, string $adminRelPrefix = ''): ?string
{
    $resolved = erp_user_avatar_resolve($avatarWebPath, $adminRelPrefix);

    return $resolved ? $resolved['url'] : null;
}

function erp_user_avatar_cache_version(?string $avatarWebPath): int
{
    $disk = erp_user_avatar_disk_path($avatarWebPath);

    return $disk ? (int) filemtime($disk) : 0;
}

function erp_user_avatar_initial(string $username): string
{
    $username = trim($username);
    return strtoupper(substr($username !== '' ? $username : 'U', 0, 1));
}

/** Morning / afternoon / evening greeting (Namibia — Africa/Windhoek). */
function erp_time_of_day_greeting(?int $hour = null): string
{
    if ($hour === null) {
        $hour = (int) (new DateTimeImmutable('now', new DateTimeZone('Africa/Windhoek')))->format('G');
    }
    if ($hour >= 5 && $hour < 12) {
        return 'Good morning';
    }
    if ($hour >= 12 && $hour < 19) {
        return 'Good afternoon';
    }
    return 'Good evening';
}

/** First name or title-cased username for dashboard greeting. */
function erp_dashboard_display_name(): string
{
    $first = trim((string) ($_SESSION['first_name'] ?? ''));
    if ($first !== '') {
        return $first;
    }
    $username = trim((string) ($_SESSION['username'] ?? ''));
    if ($username === '') {
        return 'there';
    }
    $username = str_replace(['_', '.'], ' ', $username);

    return ucwords(strtolower($username));
}

function erp_dashboard_greeting_line(): string
{
    return erp_time_of_day_greeting() . ', ' . erp_dashboard_display_name();
}

/**
 * Business owner / manager role (full financial & system oversight).
 */
function erp_is_manager_role(?string $roleName = null): bool
{
    $role = strtolower(trim((string) ($roleName ?? ($_SESSION['role_name'] ?? ''))));

    return $role === 'manager';
}

/**
 * Revenue KPIs on the Admin portal dashboard — hidden from admin staff.
 * Managers use the Manager portal for revenue and monitoring.
 */
function erp_can_view_dashboard_revenue(?string $roleName = null): bool
{
    return false;
}

/**
 * Financial summaries (revenue charts, totals, reports) — manager only.
 */
function erp_can_view_financial_summary(?string $roleName = null): bool
{
    return erp_is_manager_role($roleName);
}

/** Overview stat card subtitle — amounts only when financial summary is allowed. */
function erp_overview_card_sub(bool $canViewFinancial, string $withoutMoney, string $withMoney): string
{
    return $canViewFinancial ? $withMoney : $withoutMoney;
}

/**
 * Record an action for manager oversight (non-fatal if audit_logs is missing).
 */
function erp_audit_log(PDO $pdo, int $userId, string $action, string $entityType = '', int $entityId = 0): void
{
    if ($userId <= 0 || trim($action) === '') {
        return;
    }
    try {
        $pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)'
        )->execute([
            $userId,
            trim($action),
            trim($entityType) !== '' ? trim($entityType) : null,
            $entityId > 0 ? $entityId : null,
        ]);
    } catch (Throwable $e) {
        error_log('erp_audit_log: ' . $e->getMessage());
    }
}

/**
 * @return array{ok:bool,message:string,path:?string}
 */
function erp_save_user_avatar_upload(int $userId, array $file): array
{
    global $pdo;
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Invalid user.', 'path' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'No file uploaded or upload failed.', 'path' => null];
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $maxSize = 2 * 1024 * 1024;

    if (!in_array($ext, $allowed, true)) {
        return ['ok' => false, 'message' => 'Use JPG, PNG, GIF, or WebP (max 2MB).', 'path' => null];
    }
    if (($file['size'] ?? 0) > $maxSize) {
        return ['ok' => false, 'message' => 'Image must be 2MB or smaller.', 'path' => null];
    }

    $uploadDir = erp_project_root() . '/uploads/avatars/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'message' => 'Could not create upload folder.', 'path' => null];
    }

    $filename = 'user_' . $userId . '_' . time() . '.' . $ext;
    $fullPath = $uploadDir . $filename;
    $webPath = 'uploads/avatars/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['ok' => false, 'message' => 'Failed to save uploaded image.', 'path' => null];
    }

    erp_ensure_user_avatar_column();
    $oldPath = erp_get_user_avatar_path($userId);

    $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$webPath, $userId]);

    if ($oldPath) {
        $oldDisk = erp_user_avatar_disk_path($oldPath);
        if ($oldDisk) {
            @unlink($oldDisk);
        }
    }

    return ['ok' => true, 'message' => 'Profile photo updated.', 'path' => $webPath];
}

/** @return array{ok:bool,message:string} */
function erp_remove_user_avatar(int $userId): array
{
    global $pdo;
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Invalid user.'];
    }
    $oldPath = erp_get_user_avatar_path($userId);
    $pdo->prepare('UPDATE users SET avatar_url = NULL WHERE id = ?')->execute([$userId]);
    if ($oldPath) {
        $oldDisk = erp_user_avatar_disk_path($oldPath);
        if ($oldDisk) {
            @unlink($oldDisk);
        }
    }
    return ['ok' => true, 'message' => 'Profile photo removed.'];
}

/** @return array<string, mixed>|null */
function erp_get_user_profile(int $userId): ?array
{
    global $pdo;
    if ($userId <= 0) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('
            SELECT u.id, u.username, u.email, u.avatar_url, u.created_at, r.name AS role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        error_log('erp_get_user_profile: ' . $e->getMessage());
        return null;
    }
}

/**
 * @return array{ok:bool,message:string}
 */
function erp_update_user_profile(int $userId, string $username, string $email): array
{
    global $pdo;
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Invalid user.'];
    }

    $username = trim($username);
    $email = trim(strtolower($email));

    if ($username === '') {
        return ['ok' => false, 'message' => 'Display name is required.'];
    }
    if (strlen($username) > 50) {
        return ['ok' => false, 'message' => 'Display name must be 50 characters or less.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Enter a valid email address.'];
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'That display name is already taken.'];
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            return ['ok' => false, 'message' => 'That email is already in use.'];
        }

        $pdo->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?')
            ->execute([$username, $email, $userId]);

        if (session_status() === PHP_SESSION_ACTIVE && (int) ($_SESSION['user_id'] ?? 0) === $userId) {
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
        }

        return ['ok' => true, 'message' => 'Account details saved.'];
    } catch (Exception $e) {
        error_log('erp_update_user_profile: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not save account details.'];
    }
}

/**
 * @return array{ok:bool,message:string}
 */
function erp_change_user_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): array
{
    global $pdo;
    if ($userId <= 0) {
        return ['ok' => false, 'message' => 'Invalid user.'];
    }
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        return ['ok' => false, 'message' => 'Fill in all password fields.'];
    }
    if ($newPassword !== $confirmPassword) {
        return ['ok' => false, 'message' => 'New passwords do not match.'];
    }
    if (strlen($newPassword) < 8) {
        return ['ok' => false, 'message' => 'New password must be at least 8 characters.'];
    }

    try {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        if (!is_string($hash) || !password_verify($currentPassword, $hash)) {
            return ['ok' => false, 'message' => 'Current password is incorrect.'];
        }

        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        return ['ok' => true, 'message' => 'Password updated successfully.'];
    } catch (Exception $e) {
        error_log('erp_change_user_password: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not update password.'];
    }
}

/** Add password-reset columns on users (admin/staff accounts). */
function erp_ensure_user_reset_columns(): void
{
    global $pdo;
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_token'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL DEFAULT NULL');
        }
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reset_expiry'");
        if ($stmt && !$stmt->fetch()) {
            $pdo->exec('ALTER TABLE users ADD COLUMN reset_expiry DATETIME NULL DEFAULT NULL');
        }
    } catch (Exception $e) {
        error_log('erp_ensure_user_reset_columns: ' . $e->getMessage());
    }
}

function erp_auth_is_local_request(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return $host === 'localhost'
        || $host === '127.0.0.1'
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1:');
}

/** Base URL for Admin/Auth pages (no trailing slash). */
function erp_auth_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/Admin/Auth'));
    return $scheme . '://' . $host . rtrim($dir, '/');
}

function erp_mail_config_loaded(): bool
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }
    $mailFile = dirname(__DIR__) . '/Config/mail.php';
    if (is_file($mailFile)) {
        require_once $mailFile;
    }
    $loaded = defined('MAIL_HOST') && defined('MAIL_USERNAME') && defined('MAIL_PASSWORD');
    return $loaded;
}

/**
 * @return array{ok:bool,error:?string}
 */
function erp_send_password_reset_email(string $toEmail, string $recipientName, string $resetUrl): array
{
    if (!erp_mail_config_loaded()) {
        return ['ok' => false, 'error' => 'Email is not configured. Copy Config/mail.example.php to Config/mail.php and add SMTP details.'];
    }

    $phpmailerPath = dirname(__DIR__) . '/Libraries/PHPMailer/src/PHPMailer.php';
    if (!is_file($phpmailerPath)) {
        return ['ok' => false, 'error' => 'PHPMailer is not installed.'];
    }

    try {
        require_once dirname(__DIR__) . '/Libraries/PHPMailer/src/Exception.php';
        require_once dirname(__DIR__) . '/Libraries/PHPMailer/src/PHPMailer.php';
        require_once dirname(__DIR__) . '/Libraries/PHPMailer/src/SMTP.php';

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
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your SV Auto password';
        $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">'
            . '<h2>Password reset</h2>'
            . '<p>Hello ' . $safeName . ',</p>'
            . '<p>Click the link below to set a new password. It expires in 1 hour.</p>'
            . '<p><a href="' . $safeUrl . '" style="background:#F7A100;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;">Reset password</a></p>'
            . '<p style="color:#888;font-size:13px;">If you did not request this, ignore this email.</p>'
            . '<p style="color:#888;font-size:12px;">' . $safeUrl . '</p>'
            . '</div>';
        $mail->AltBody = "Hello {$recipientName},\n\nReset your password (expires in 1 hour):\n{$resetUrl}\n";
        $mail->send();
        return ['ok' => true, 'error' => null];
    } catch (Exception $e) {
        error_log('erp_send_password_reset_email: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Email could not be sent. Check mail settings.'];
    }
}

/**
 * Request reset for admin (users) or client accounts.
 *
 * @return array{sent:bool,message:string,dev_reset_url:?string}
 */
function erp_request_password_reset(string $email): array
{
    global $pdo;
    $email = trim(strtolower($email));
    $generic = 'If this email is registered, you will receive reset instructions shortly.';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'message' => 'Enter a valid email address.', 'dev_reset_url' => null];
    }

    erp_ensure_user_reset_columns();

    $accountType = null;
    $accountId = null;
    $recipientName = '';

    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $accountType = 'user';
        $accountId = (int) $user['id'];
        $recipientName = (string) ($user['username'] ?? 'Admin');
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, first_name, email FROM clients WHERE LOWER(email) = ? LIMIT 1');
            $stmt->execute([$email]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($client) {
                $accountType = 'client';
                $accountId = (int) $client['id'];
                $recipientName = (string) ($client['first_name'] ?? 'Customer');
            }
        } catch (Exception $e) {
            error_log('erp_request_password_reset clients: ' . $e->getMessage());
        }
    }

    if (!$accountType || !$accountId) {
        return ['sent' => true, 'message' => $generic, 'dev_reset_url' => null];
    }

    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $resetUrl = erp_auth_base_url() . '/reset-password.php?token=' . urlencode($token);

    if ($accountType === 'user') {
        $pdo->prepare('UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?')
            ->execute([$token, $expiry, $accountId]);
    } else {
        $pdo->prepare('UPDATE clients SET reset_token = ?, reset_expiry = ? WHERE id = ?')
            ->execute([$token, $expiry, $accountId]);
    }

    $mailResult = erp_send_password_reset_email($email, $recipientName, $resetUrl);
    if ($mailResult['ok']) {
        return ['sent' => true, 'message' => 'Password reset instructions have been sent to your email.', 'dev_reset_url' => null];
    }

    if (erp_auth_is_local_request()) {
        return [
            'sent' => true,
            'message' => 'Email is not configured on this server. Use this reset link (localhost only, expires in 1 hour):',
            'dev_reset_url' => $resetUrl,
        ];
    }

    return ['sent' => false, 'message' => $mailResult['error'] ?? 'Could not send reset email.', 'dev_reset_url' => null];
}

/**
 * @return array{type:string,id:int}|null  type is user|client
 */
function erp_validate_password_reset_token(string $token): ?array
{
    global $pdo;
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    erp_ensure_user_reset_columns();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW() LIMIT 1');
    $stmt->execute([$token]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return ['type' => 'user', 'id' => (int) $id];
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE reset_token = ? AND reset_expiry > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return ['type' => 'client', 'id' => (int) $id];
        }
    } catch (Exception $e) {
        error_log('erp_validate_password_reset_token: ' . $e->getMessage());
    }

    return null;
}

/**
 * @return array{ok:bool,message:string}
 */
function erp_complete_password_reset(string $token, string $password, string $confirmPassword): array
{
    global $pdo;

    if ($password === '' || $confirmPassword === '') {
        return ['ok' => false, 'message' => 'Fill in all fields.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
    }
    if ($password !== $confirmPassword) {
        return ['ok' => false, 'message' => 'Passwords do not match.'];
    }

    $account = erp_validate_password_reset_token($token);
    if (!$account) {
        return ['ok' => false, 'message' => 'Invalid or expired reset link. Request a new one.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($account['type'] === 'user') {
        $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?')
            ->execute([$hash, $account['id']]);
    } else {
        $pdo->prepare('UPDATE clients SET password_hash = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?')
            ->execute([$hash, $account['id']]);
    }

    return ['ok' => true, 'message' => 'Password reset successfully.'];
}
