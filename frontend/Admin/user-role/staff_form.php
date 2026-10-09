<?php
// Add a staff login (no id) or edit one (?id=N): email, role, optional new password, active/deactivated.
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_once __DIR__ . '/../includes/admin_list.inc.php';

require_role(['admin']);

const STAFF_MIN_PASSWORD = 10;

$me = (int) ($_SESSION['user_id'] ?? 0);
$editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$isEdit = $editId > 0;

// is_active comes from backend/migrations/live_database_upgrade.sql; the form still works without it.
$hasActive = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'")->rowCount() > 0;

// Only roles that have a working area can be assigned. Technician / Finance / HR are hidden (code and rows kept).
$assignable = ['admin', 'manager'];
$allRoles = $pdo->query("SELECT id, name FROM roles WHERE LOWER(name) <> 'client' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$roles = [];
$roleIds = [];
$adminRoleId = (int) $pdo->query("SELECT id FROM roles WHERE LOWER(name) = 'admin' LIMIT 1")->fetchColumn();

if (empty($_SESSION['staff_csrf'])) {
    $_SESSION['staff_csrf'] = bin2hex(random_bytes(32));
}

$form = ['username' => '', 'email' => '', 'role_id' => 0, 'is_active' => 1];
$existing = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND LOWER(r.name) <> 'client'");
    $stmt->execute([$editId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        header('Location: staff.php?error=' . urlencode('Staff account not found.'));
        exit;
    }
    $form = [
        'username' => (string) $existing['username'],
        'email' => (string) ($existing['email'] ?? ''),
        'role_id' => (int) $existing['role_id'],
        'is_active' => (int) ($existing['is_active'] ?? 1),
    ];
}
$isSelf = $isEdit && $editId === $me;
foreach ($allRoles as $r) {
    if (in_array(strtolower($r['name']), $assignable, true) || (int) $r['id'] === (int) $form['role_id']) {
        $roles[] = $r;
        $roleIds[] = (int) $r['id'];
    }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['staff_csrf'], (string) ($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $username = $isEdit ? $form['username'] : trim((string) ($_POST['username'] ?? ''));
    $email    = trim((string) ($_POST['email'] ?? ''));
    $roleId   = (int) ($_POST['role_id'] ?? 0);
    $active   = $hasActive ? (isset($_POST['is_active']) ? 1 : 0) : 1;
    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    // You cannot demote or deactivate yourself (that could lock everyone out).
    if ($isSelf) {
        $roleId = $form['role_id'];
        $active = 1;
    }

    if (!$isEdit && !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3-50 characters: letters, numbers, dot, dash or underscore.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!in_array($roleId, $roleIds, true)) {
        $errors[] = 'Choose a role.';
    }
    if (!$isEdit || $password !== '' || $confirm !== '') {
        if (strlen($password) < STAFF_MIN_PASSWORD) {
            $errors[] = 'Password must be at least ' . STAFF_MIN_PASSWORD . ' characters.';
        } elseif ($password !== $confirm) {
            $errors[] = 'The two passwords do not match.';
        }
    }

    if (!$errors) {
        $dup = $pdo->prepare('SELECT username, email FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
        $dup->execute([$username, $email, $editId]);
        if ($row = $dup->fetch(PDO::FETCH_ASSOC)) {
            $errors[] = strcasecmp((string) $row['username'], $username) === 0
                ? 'That username is already taken.'
                : 'That email address is already used by another account.';
        }
        // Clients sign in with the same email, so keep staff and customer emails separate.
        $clientDup = $pdo->prepare('SELECT 1 FROM clients WHERE email = ? LIMIT 1');
        $clientDup->execute([$email]);
        if (!$errors && $clientDup->fetchColumn()) {
            $errors[] = 'That email already belongs to a customer account. Use a different email for the staff login.';
        }
    }

    // Keep at least one active admin.
    if (!$errors && $isEdit && $existing && (int) $existing['role_id'] === $adminRoleId
        && ($roleId !== $adminRoleId || $active === 0)) {
        $othersSql = 'SELECT COUNT(*) FROM users WHERE role_id = ? AND id <> ?' . ($hasActive ? ' AND is_active = 1' : '');
        $others = $pdo->prepare($othersSql);
        $others->execute([$adminRoleId, $editId]);
        if ((int) $others->fetchColumn() < 1) {
            $errors[] = 'This is the only active administrator. Make another person an administrator first.';
        }
    }

    if (!$errors) {
        try {
            if ($isEdit) {
                $set = ['email = ?', 'role_id = ?'];
                $vals = [$email, $roleId];
                if ($hasActive) {
                    $set[] = 'is_active = ?';
                    $vals[] = $active;
                }
                if ($password !== '') {
                    $set[] = 'password = ?';
                    $vals[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $vals[] = $editId;
                $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($vals);
                $action = $password !== '' ? 'updated staff account and reset password' : 'updated staff account';
                $entityId = $editId;
            } else {
                $pdo->prepare('INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)')
                    ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $roleId]);
                $entityId = (int) $pdo->lastInsertId();
                $action = 'created staff account';
            }
            try {
                $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)')
                    ->execute([$me, $action, 'user', $entityId]);
            } catch (Throwable $e) {
                error_log('Staff audit log failed: ' . $e->getMessage());
            }
            $_SESSION['staff_csrf'] = bin2hex(random_bytes(32));
            header('Location: staff.php?success=' . urlencode($isEdit ? 'Staff account updated.' : 'Staff account created. They can sign in now with their username or email.'));
            exit;
        } catch (Throwable $e) {
            error_log('Staff save failed: ' . $e->getMessage());
            $errors[] = 'Could not save the account. Please try again.';
        }
    }

    // Re-show what was typed (never the passwords).
    $form = ['username' => $username, 'email' => $email, 'role_id' => $roleId, 'is_active' => $active];
}

$GLOBALS['erp_resume_title'] = $isEdit ? 'Edit Staff' : 'Add Staff';
include __DIR__ . '/../includes/header.php';
?>

<div class="erp-flex erp-justify-between erp-align-center erp-mt-2">
    <h1 class="erp-page-title"><i class="fas fa-id-badge" style="color:#F5A623;margin-right:8px;"></i><?php echo $isEdit ? 'Edit Staff Account' : 'Add Staff Account'; ?></h1>
    <a href="user-role/staff.php" class="erp-btn erp-btn-secondary"><i class="fas fa-arrow-left"></i> Back to staff</a>
</div>

<?php if ($errors): ?>
    <div class="erp-card erp-mt-2" style="border-left:4px solid #dc2626;background:#fef2f2;"><div class="erp-card-body" style="color:#991b1b;">
        <?php foreach ($errors as $e): ?><div><i class="fas fa-exclamation-circle"></i> <?php echo al_h($e); ?></div><?php endforeach; ?>
    </div></div>
<?php endif; ?>

<?php if (!$hasActive): ?>
    <div class="erp-card erp-mt-2" style="border-left:4px solid #f59e0b;background:#fffbeb;"><div class="erp-card-body" style="color:#92400e;">
        <i class="fas fa-info-circle"></i> The activate/deactivate option appears after the database upgrade script (live_database_upgrade.sql) has been run.
    </div></div>
<?php endif; ?>

<div class="erp-card erp-mt-2 erp-mb-4">
    <div class="erp-card-body">
        <form method="post" autocomplete="off" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;max-width:900px;">
            <input type="hidden" name="csrf_token" value="<?php echo al_h($_SESSION['staff_csrf']); ?>">

            <div>
                <label class="erp-label" for="sf-username">Username</label>
                <input type="text" id="sf-username" name="username" class="erp-input" value="<?php echo al_h($form['username']); ?>"
                       <?php echo $isEdit ? 'readonly style="background:#f1f5f9;"' : 'required maxlength="50"'; ?>>
                <?php if ($isEdit): ?><small style="color:#64748b;">The username cannot be changed.</small><?php endif; ?>
            </div>

            <div>
                <label class="erp-label" for="sf-email">Email</label>
                <input type="email" id="sf-email" name="email" class="erp-input" required maxlength="100" value="<?php echo al_h($form['email']); ?>">
            </div>

            <div>
                <label class="erp-label" for="sf-role">Role</label>
                <select id="sf-role" name="role_id" class="erp-input" required<?php echo $isSelf ? ' disabled' : ''; ?>>
                    <option value="">Select a role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int) $r['id']; ?>"<?php echo (int) $r['id'] === (int) $form['role_id'] ? ' selected' : ''; ?>><?php echo al_h($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($isSelf): ?><small style="color:#64748b;">You cannot change your own role.</small><?php endif; ?>
            </div>

            <?php if ($hasActive): ?>
            <div style="display:flex;align-items:end;">
                <label style="display:flex;gap:10px;align-items:center;font-weight:600;">
                    <input type="checkbox" name="is_active" value="1"<?php echo (int) $form['is_active'] === 1 ? ' checked' : ''; ?><?php echo $isSelf ? ' disabled' : ''; ?>>
                    Account is active (can sign in)
                </label>
            </div>
            <?php endif; ?>

            <div>
                <label class="erp-label" for="sf-pass"><?php echo $isEdit ? 'New password (leave empty to keep the current one)' : 'Password'; ?></label>
                <input type="password" id="sf-pass" name="password" class="erp-input" minlength="<?php echo STAFF_MIN_PASSWORD; ?>" autocomplete="new-password"<?php echo $isEdit ? '' : ' required'; ?>>
                <small style="color:#64748b;">At least <?php echo STAFF_MIN_PASSWORD; ?> characters. Share it with the person privately and ask them to keep it safe.</small>
            </div>

            <div>
                <label class="erp-label" for="sf-pass2">Repeat password</label>
                <input type="password" id="sf-pass2" name="password_confirm" class="erp-input" autocomplete="new-password"<?php echo $isEdit ? '' : ' required'; ?>>
            </div>

            <div style="grid-column:1/-1;display:flex;gap:10px;">
                <button type="submit" class="erp-btn erp-btn-primary"><i class="fas fa-save"></i> <?php echo $isEdit ? 'Save changes' : 'Create account'; ?></button>
                <a href="user-role/staff.php" class="erp-btn erp-btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
