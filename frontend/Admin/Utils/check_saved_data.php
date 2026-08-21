<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Saved Data • SV Auto</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 30px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        h1 {
            color: #F5A623;
            border-bottom: 3px solid #F5A623;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        h2 {
            color: #4a4a4a;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .data-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #F5A623;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #F5A623;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background: #F7A100;
            color: white;
        }
        .count {
            font-size: 24px;
            font-weight: bold;
            color: #F5A623;
            margin: 10px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background: #F5A623;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .back-link:hover {
            background: #4a4a4a;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="container">
    <h1>📊 Check Saved Data in Database</h1>

    <?php
    try {
        // Job Cards
        echo '<div class="data-section">';
        echo '<h2>Job Cards</h2>';
        $job_cards = $pdo->query("SELECT COUNT(*) FROM job_cards")->fetchColumn();
        echo '<div class="count">' . $job_cards . ' Job Cards Saved</div>';
        
        if ($job_cards > 0) {
            echo '<table>';
            echo '<tr><th>Card Number</th><th>Client</th><th>Vehicle</th><th>Status</th><th>Created</th></tr>';
            $results = $pdo->query("
                SELECT 
                    jc.card_number,
                    c.name AS client_name,
                    v.reg_no,
                    jc.status,
                    jc.created_at
                FROM job_cards jc
                LEFT JOIN vehicles v ON jc.vehicle_id = v.id
                LEFT JOIN clients c ON v.client_id = c.id
                ORDER BY jc.created_at DESC
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as $row) {
                echo '<tr>';
                echo '<td><strong>' . htmlspecialchars($row['card_number']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($row['client_name'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($row['reg_no'] ?? 'N/A') . '</td>';
                echo '<td><span style="background: #fff3cd; padding: 5px 10px; border-radius: 5px;">' . ucfirst($row['status']) . '</span></td>';
                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<div class="error">❌ No job cards found. Create one first!</div>';
        }
        echo '</div>';

        // Clients
        echo '<div class="data-section">';
        echo '<h2>Clients</h2>';
        $clients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
        echo '<div class="count">' . $clients . ' Clients</div>';
        echo '</div>';

        // Vehicles
        echo '<div class="data-section">';
        echo '<h2>Vehicles</h2>';
        $vehicles = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
        echo '<div class="count">' . $vehicles . ' Vehicles</div>';
        echo '</div>';

        // Employees
        echo '<div class="data-section">';
        echo '<h2>Employees</h2>';
        $employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
        echo '<div class="count">' . $employees . ' Employees</div>';
        
        $technicians = $pdo->query("SELECT COUNT(*) FROM employees WHERE position = 'Technician'")->fetchColumn();
        echo '<div style="margin-top: 10px; color: #666;">Technicians: ' . $technicians . '</div>';
        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>

    <a href="view_all_job_cards.php" class="back-link">← View All Job Cards</a>
    <a href="add_job_card.php" class="back-link">+ Create New Job Card</a>
</div>

</body>
</html>