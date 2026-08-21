<?php
// delete_employee.php — PERMANENTLY DELETE EMPLOYEE WITH FULL SAFETY
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

$employee_id = $_GET['id'] ?? 0;
if (!is_numeric($employee_id) || $employee_id <= 0) {
    header('Location: employees.php?error=Invalid employee ID');
    exit;
}

// FETCH EMPLOYEE FIRST (to get name & photo)
$stmt = $pdo->prepare("SELECT name, photo_url FROM employees WHERE id = ? LIMIT 1");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header('Location: employees.php?error=Employee not found');
    exit;
}

try {
    $pdo->beginTransaction();

    // Soft delete employee (move to recycle bin)
    $stmt = $pdo->prepare("UPDATE employees SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$employee_id]);

    // Log deletion in audit trail
    $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, entity_type, entity_id, created_at) 
        VALUES (?, 'deleted employee', 'employee', ?, NOW())
    ")->execute([$_SESSION['user_id'], $employee_id]);

    $pdo->commit();

    // Success redirect
    header('Location: employees.php?success=' . urlencode("Employee \"{$employee['name']}\" has been moved to recycle bin."));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: employees.php?error=Failed to delete employee. Please try again.');
    exit;
}
?>
