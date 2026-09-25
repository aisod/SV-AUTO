<?php
/**
 * One-time Google OAuth setup check. Open in browser, then delete or restrict access on production.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=utf-8');

$ok = GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
$redirects = [
    'Login callback'    => GOOGLE_REDIRECT_URI,
    'Register callback' => GOOGLE_REGISTER_REDIRECT_URI,
];

$dbOk = false;
$dbMsg = '';
try {
    $col = $pdo->query("SHOW COLUMNS FROM clients LIKE 'google_id'")->fetch(PDO::FETCH_ASSOC);
    $dbOk = (bool) $col;
    $dbMsg = $dbOk ? 'google_id column exists on clients table.' : 'google_id column missing.';
} catch (Exception $e) {
    $dbMsg = 'Database check failed: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google OAuth setup check</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; line-height: 1.5; }
        .ok { color: #059669; }
        .bad { color: #dc2626; }
        code, pre { background: #f1f5f9; padding: 0.2rem 0.4rem; border-radius: 4px; word-break: break-all; }
        pre { padding: 1rem; overflow-x: auto; }
        h1 { font-size: 1.35rem; }
    </style>
</head>
<body>
    <h1>Google OAuth setup check</h1>

    <p class="<?= $ok ? 'ok' : 'bad' ?>">
        <?= $ok ? '✓ Credentials loaded from google.local.php' : '✗ Missing GOOGLE_CLIENT_ID or GOOGLE_CLIENT_SECRET in google.local.php' ?>
    </p>

    <p class="<?= $dbOk ? 'ok' : 'bad' ?>">
        <?= $dbOk ? '✓' : '✗' ?> <?= htmlspecialchars($dbMsg) ?>
    </p>

    <h2>Redirect URIs for this request</h2>
    <p>Add these <strong>exactly</strong> in Google Cloud Console → Credentials → your OAuth client → Authorized redirect URIs:</p>
    <pre><?php
foreach ($redirects as $label => $uri) {
    echo htmlspecialchars($label) . "\n" . htmlspecialchars($uri) . "\n\n";
}
echo "Also add the other environment (local vs production) if you use both.\n";
?></pre>

    <h2>Test links</h2>
    <ul>
        <li><a href="<?= htmlspecialchars(app_url('Admin/Auth/google-auth.php')) ?>">Sign in with Google</a></li>
        <li><a href="<?= htmlspecialchars(app_url('Admin/Auth/register.php?action=google_register')) ?>">Register with Google</a></li>
    </ul>

    <p><small>Delete <code>backend/config/google-oauth-check.php</code> on production after verifying.</small></p>
</body>
</html>
