<?php
/**
 * seed_test_data.php - Deterministic test accounts and records for QA runs.
 *
 * Run from the CLI against a LOCAL database only:
 *     php backend/setup/seed_test_data.php
 *
 * Every account below uses the same password so the e2e suite can share one
 * constant. Idempotent: re-running updates the existing rows in place.
 *
 * Staff live in `users` (+ `roles`); clients live in `clients` with their own
 * password_hash and status. login.php checks `users` first, so a client's email
 * must never also exist in `users` or the client branch is unreachable.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("seed_test_data.php is CLI-only.\n");
}

require_once __DIR__ . '/../config/config.php';

const TEST_PASSWORD = 'Test@1234';

$hash = password_hash(TEST_PASSWORD, PASSWORD_DEFAULT);
$out = [];

/** Resolve a role id by name, creating the role if absent. */
function roleId(PDO $pdo, string $name, string $permissions): int {
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $pdo->prepare("INSERT INTO roles (name, permissions) VALUES (?, ?)")->execute([$name, $permissions]);
    return (int) $pdo->lastInsertId();
}

/** Insert or update a staff user, returning its id. */
function upsertUser(PDO $pdo, string $username, string $email, string $hash, int $roleId): int {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$email, $username]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role_id = ? WHERE id = ?")
            ->execute([$username, $email, $hash, $roleId, $id]);
        return (int) $id;
    }
    $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)")
        ->execute([$username, $email, $hash, $roleId]);
    return (int) $pdo->lastInsertId();
}

/** Insert or update a client, returning its id. */
function upsertClient(PDO $pdo, array $c, string $hash): int {
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
    $stmt->execute([$c['email']]);
    $id = $stmt->fetchColumn();

    $cols = [
        'name'           => $c['first_name'] . ' ' . $c['last_name'],
        'email'          => $c['email'],
        'phone'          => $c['phone'],
        'address'        => $c['address'],
        'contact_person' => $c['first_name'] . ' ' . $c['last_name'],
        'title'          => $c['title'],
        'first_name'     => $c['first_name'],
        'last_name'      => $c['last_name'],
        'password_hash'  => $hash,
        'role'           => 'client',
        'status'         => $c['status'],
    ];

    if ($id) {
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($cols)));
        $params = array_values($cols);
        $params[] = $id;
        $pdo->prepare("UPDATE clients SET $set WHERE id = ?")->execute($params);
        return (int) $id;
    }
    $names = implode(', ', array_map(fn($k) => "`$k`", array_keys($cols)));
    $marks = implode(', ', array_fill(0, count($cols), '?'));
    $pdo->prepare("INSERT INTO clients ($names) VALUES ($marks)")->execute(array_values($cols));
    return (int) $pdo->lastInsertId();
}

// --- Roles -------------------------------------------------------------------
$roles = [
    'admin'      => roleId($pdo, 'Admin', 'full_access'),
    'manager'    => roleId($pdo, 'Manager', 'authorize,reports'),
    'technician' => roleId($pdo, 'Technician', 'job_cards,requests'),
    'finance'    => roleId($pdo, 'Finance User', 'invoices,payments,reports'),
    'hr'         => roleId($pdo, 'HR User', 'employees,forms'),
];
$out[] = 'roles: ' . json_encode($roles);

// --- Staff accounts ----------------------------------------------------------
$staff = [
    ['qa_admin',      'qa.admin@test.local',      $roles['admin']],
    ['qa_manager',    'qa.manager@test.local',    $roles['manager']],
    ['qa_technician', 'qa.technician@test.local', $roles['technician']],
    ['qa_finance',    'qa.finance@test.local',    $roles['finance']],
    ['qa_hr',         'qa.hr@test.local',         $roles['hr']],
];
foreach ($staff as $s) {
    $id = upsertUser($pdo, $s[0], $s[1], $hash, $s[2]);
    $out[] = "user  {$s[1]} (role_id={$s[2]}) -> id $id";
}

// A role name the redirect table does not recognise, for the ROLE-07 fallback case.
$cleanerRole = roleId($pdo, 'Cleaner', 'none');
$out[] = 'user  qa.cleaner@test.local -> id '
    . upsertUser($pdo, 'qa_cleaner', 'qa.cleaner@test.local', $hash, $cleanerRole);

// Repair the placeholder hashes shipped in database.sql so those rows are usable.
$fixed = $pdo->prepare("UPDATE users SET password = ? WHERE password LIKE 'hashed_password%'");
$fixed->execute([$hash]);
$out[] = 'repaired placeholder password hashes: ' . $fixed->rowCount();

// --- Clients -----------------------------------------------------------------
$clients = [
    ['title' => 'Mr', 'first_name' => 'Alpha', 'last_name' => 'Client',
     'email' => 'qa.client.approved@test.local', 'phone' => '0811000001',
     'address' => '1 Alpha Street, Windhoek', 'status' => 'approved'],
    ['title' => 'Ms', 'first_name' => 'Bravo', 'last_name' => 'Client',
     'email' => 'qa.client.other@test.local', 'phone' => '0811000002',
     'address' => '2 Bravo Street, Windhoek', 'status' => 'approved'],
    ['title' => 'Mr', 'first_name' => 'Charlie', 'last_name' => 'Pending',
     'email' => 'qa.client.pending@test.local', 'phone' => '0811000003',
     'address' => '3 Charlie Street, Windhoek', 'status' => 'pending'],
    ['title' => 'Ms', 'first_name' => 'Delta', 'last_name' => 'Blocked',
     'email' => 'qa.client.blocked@test.local', 'phone' => '0811000004',
     'address' => '4 Delta Street, Windhoek', 'status' => 'blocked'],
];
$clientIds = [];
foreach ($clients as $c) {
    $id = upsertClient($pdo, $c, $hash);
    $clientIds[$c['email']] = $id;
    $out[] = "client {$c['email']} ({$c['status']}) -> id $id";
}

// --- Vehicles, job cards, quotations, invoices for the two approved clients ---
// Two tenants whose records can be cross-requested, for the IDOR cases.
$fixtureIds = [];
$pairs = [
    ['qa.client.approved@test.local', 'QA-ALPHA-001', 'N 1111 W', 'Scania R450'],
    ['qa.client.other@test.local',    'QA-BRAVO-001', 'N 2222 W', 'Volvo FH16'],
];

foreach ($pairs as $p) {
    list($email, $cardNumber, $reg, $model) = $p;
    $cid = $clientIds[$email];

    $v = $pdo->prepare("SELECT id FROM vehicles WHERE reg_no = ? LIMIT 1");
    $v->execute([$reg]);
    $vehicleId = $v->fetchColumn();
    if (!$vehicleId) {
        $pdo->prepare("INSERT INTO vehicles (client_id, vin_no, fiscal_no, reg_no, model, last_service_date)
                       VALUES (?, ?, ?, ?, ?, CURDATE())")
            ->execute([$cid, 'VIN' . $cardNumber, 'FSC' . $cardNumber, $reg, $model]);
        $vehicleId = (int) $pdo->lastInsertId();
    }

    $j = $pdo->prepare("SELECT id FROM job_cards WHERE card_number = ? LIMIT 1");
    $j->execute([$cardNumber]);
    $jobCardId = $j->fetchColumn();
    if (!$jobCardId) {
        $pdo->prepare("INSERT INTO job_cards (card_number, vehicle_id, client_id, description, parts_supply, requester_for_parts, status)
                       VALUES (?, ?, ?, ?, 'workshop', 'QA seed', 'new')")
            ->execute([$cardNumber, $vehicleId, $cid, "Seeded job card for $model"]);
        $jobCardId = (int) $pdo->lastInsertId();
    }

    $q = $pdo->prepare("SELECT id FROM quotations WHERE job_card_id = ? AND client_id = ? LIMIT 1");
    $q->execute([$jobCardId, $cid]);
    $quotationId = $q->fetchColumn();
    if (!$quotationId) {
        $pdo->prepare("INSERT INTO quotations (job_card_id, client_id, amount, details, status, submitted_at)
                       VALUES (?, ?, ?, ?, 'pending', NOW())")
            ->execute([$jobCardId, $cid, 15000.00, "Labour and parts for $model"]);
        $quotationId = (int) $pdo->lastInsertId();
    }

    $i = $pdo->prepare("SELECT id FROM invoices WHERE quotation_id = ? LIMIT 1");
    $i->execute([$quotationId]);
    $invoiceId = $i->fetchColumn();
    if (!$invoiceId) {
        $pdo->prepare("INSERT INTO invoices (quotation_id, amount, status, details)
                       VALUES (?, ?, 'unpaid', ?)")
            ->execute([$quotationId, 15000.00, "Invoice for $cardNumber"]);
        $invoiceId = (int) $pdo->lastInsertId();
    }

    $fixtureIds[$email] = [
        'clientId'    => (int) $cid,
        'vehicleId'   => (int) $vehicleId,
        'jobCardId'   => (int) $jobCardId,
        'quotationId' => (int) $quotationId,
        'invoiceId'   => (int) $invoiceId,
    ];
    $out[] = "fixture $email -> client=$cid vehicle=$vehicleId jobcard=$jobCardId quotation=$quotationId invoice=$invoiceId";
}

// Hand the ids to the e2e suite so specs never guess primary keys.
$manifest = [
    'password' => TEST_PASSWORD,
    'clients'  => $clientIds,
    'fixtures' => $fixtureIds,
];
$manifestPath = __DIR__ . '/../../tests/e2e/seed-manifest.json';
if (is_dir(dirname($manifestPath))) {
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));
    $out[] = 'wrote ' . realpath($manifestPath);
}

echo implode("\n", $out), "\n\nAll seeded accounts use password: " . TEST_PASSWORD . "\n";
