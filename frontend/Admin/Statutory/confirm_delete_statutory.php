<?php
session_start();
require_once __DIR__ . '/../../../backend/config/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: statutory.php?error=' . urlencode('Invalid document ID'));
    exit;
}

$doc_id = (int)$_GET['id'];

// Get document details
try {
    $stmt = $pdo->prepare("SELECT * FROM statutory_docs WHERE id = ?");
    $stmt->execute([$doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        header('Location: statutory.php?error=' . urlencode('Document not found'));
        exit;
    }
} catch (PDOException $e) {
    header('Location: statutory.php?error=' . urlencode('Database error'));
    exit;
}

$file_path = __DIR__ . '/../' . $document['file_url'];
$file_size = file_exists($file_path) ? round(filesize($file_path)/1024, 1) . ' KB' : 'File not found';
?>

<?php include __DIR__ . '/../sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Delete - SV Auto</title>
    <style>
        .confirm-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .confirm-header {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .confirm-header h2 {
            margin: 0;
            font-size: 24px;
        }
        .confirm-body {
            padding: 40px;
        }
        .doc-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 5px solid #dc3545;
        }
        .doc-info h3 {
            margin: 0 0 15px 0;
            color: #333;
        }
        .doc-detail {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .doc-detail:last-child {
            border-bottom: none;
        }
        .doc-detail strong {
            color: #555;
        }
        .warning-text {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #ffeaa7;
        }
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="content-wrapper">
    <div class="confirm-container">
        <div class="confirm-header">
            <h2>⚠️ Confirm Document Deletion</h2>
        </div>
        
        <div class="confirm-body">
            <div class="doc-info">
                <h3>Document Details</h3>
                <div class="doc-detail">
                    <span><strong>Name:</strong></span>
                    <span><?= htmlspecialchars($document['name']) ?></span>
                </div>
                <div class="doc-detail">
                    <span><strong>Type:</strong></span>
                    <span><?= ucwords(str_replace('_', ' ', $document['type'])) ?></span>
                </div>
                <div class="doc-detail">
                    <span><strong>File Size:</strong></span>
                    <span><?= $file_size ?></span>
                </div>
                <div class="doc-detail">
                    <span><strong>File Path:</strong></span>
                    <span><?= htmlspecialchars($document['file_url']) ?></span>
                </div>
            </div>
            
            <div class="warning-text">
                <strong>⚠️ Warning:</strong> This action cannot be undone. The document will be permanently deleted from both the database and the server.
            </div>
            
            <div class="button-group">
                <?php if (file_exists($file_path)): ?>
                <a href="../<?= htmlspecialchars($document['file_url']) ?>" target="_blank" class="btn btn-info">
                    👁️ View Document
                </a>
                <?php endif; ?>
                
                <a href="statutory.php" class="btn btn-secondary">
                    ❌ Cancel
                </a>
                
                <a href="delete_statutory.php?id=<?= $document['id'] ?>&confirm=yes" class="btn btn-danger">
                    🗑️ Delete Permanently
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
