<?php
/**
 * Temporary preview step for add_quotation.php — receives JSON `draft` POST.
 * Does not persist to the database until wired to create_quotation / your flow.
 */
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';
require_once __DIR__ . '/../../../backend/config/functions.php';
require_once __DIR__ . '/../Auth/auth.php';
require_admin();

$draftRaw = $_POST['draft'] ?? '';
if (!is_string($draftRaw) || trim($draftRaw) === '') {
    header('Location: add_quotation.php?error=' . urlencode('Nothing to confirm.'));
    exit;
}

$page_title = 'Confirm quotation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: 'Roboto', sans-serif; max-width: 720px; margin: 40px auto; padding: 0 16px; color: #1e293b; }
        a { color: #f7a100; font-weight: 600; text-decoration: none; }
        a:hover { text-decoration: underline; }
        pre { background: #f4f4f5; padding: 12px; border-radius: 8px; overflow: auto; font-size: 12px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <p><strong>Preview</strong> — Data below is not saved yet. Use <strong>Quotations</strong> list + the standard create flow to persist.</p>
    <p>
        <a href="add_quotation.php">← Back to quotation form</a>
        &nbsp;·&nbsp;
        <a href="quotations.php">Quotations list</a>
    </p>
    <pre><?php echo htmlspecialchars($draftRaw, ENT_QUOTES, 'UTF-8'); ?></pre>
</body>
</html>
