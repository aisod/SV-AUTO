<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

// Get all job cards
$job_cards = $pdo->query("
    SELECT 
        jc.id,
        jc.card_number,
        c.name AS client_name,
        v.reg_no,
        v.model,
        e.name AS technician_name,
        jc.description,
        jc.status,
        jc.created_at,
        jc.requester_for_parts
    FROM job_cards jc
    LEFT JOIN vehicles v ON jc.vehicle_id = v.id
    LEFT JOIN clients c ON v.client_id = c.id
    LEFT JOIN employees e ON jc.technician_id = e.id
    ORDER BY jc.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/../sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Job Cards • SV Auto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <style>
        :root {
            --p: #F5A623;
            --s: #1a1a1a;
            --a: #FFF8EC;
            --t: #1a1a1a;
            --g: #2e7d32;
            --r: #c62828;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #fdfcfb 0%, #f9f0e6 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--t);
            min-height: 100vh;
        }
        .content-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        .page-title {
            font-size: 48px;
            color: var(--s);
            margin: 40px 0 30px;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .page-title i {
            color: var(--p);
            font-size: 50px;
        }
        .table-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(139, 69, 19, 0.2);
            overflow: hidden;
            border-top: 8px solid var(--p);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: linear-gradient(135deg, var(--p), var(--s));
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 900;
            font-size: 16px;
        }
        td {
            padding: 18px 20px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 15px;
        }
        tr:hover {
            background: #F7A100;
            color: white;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-in_progress {
            background: #cfe2ff;
            color: #084298;
        }
        .status-complete {
            background: #d1e7dd;
            color: #0f5132;
        }
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-view {
            background: var(--p);
            color: white;
        }
        .btn-view:hover {
            background: var(--s);
            transform: translateY(-2px);
        }
        .btn-delete {
            background: var(--r);
            color: white;
        }
        .btn-delete:hover {
            background: #a01f1f;
            transform: translateY(-2px);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(139, 69, 19, 0.1);
            border-left: 6px solid var(--p);
        }
        .stat-number {
            font-size: 36px;
            font-weight: 900;
            color: var(--p);
        }
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 8px;
        }
    </style>
</head>
<body>

<div class="content-wrapper">
    <h2 class="page-title">
        <i class="fas fa-list"></i> ALL JOB CARDS
    </h2>

    <!-- Statistics -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo count($job_cards); ?></div>
            <div class="stat-label">Total Job Cards</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($job_cards, fn($j) => $j['status'] === 'pending')); ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($job_cards, fn($j) => $j['status'] === 'in_progress')); ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?php echo count(array_filter($job_cards, fn($j) => $j['status'] === 'complete')); ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-container">
        <?php if (empty($job_cards)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Job Cards Yet</h3>
                <p>Create your first job card to get started</p>
                <a href="add_job_card.php" class="action-btn btn-view" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i> Create Job Card
                </a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Card Number</th>
                        <th>Client</th>
                        <th>Vehicle</th>
                        <th>Technician</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($job_cards as $card): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($card['card_number']); ?></strong></td>
                            <td><?php echo htmlspecialchars($card['client_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php echo htmlspecialchars(($card['reg_no'] ?? 'N/A') . ' • ' . ($card['model'] ?? '')); ?>
                            </td>
                            <td><?php echo htmlspecialchars($card['technician_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $card['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $card['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($card['created_at'])); ?></td>
                            <td>
                                <a href="view_job_card.php?id=<?php echo $card['id']; ?>" class="action-btn btn-view">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
