<!DOCTYPE html>
<html>
<head>
    <title>Simple Delete Test</title>
</head>
<body>
    <h2>Simple Delete Test</h2>
    
    <?php
    session_start();
    
    echo "<p>Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</p>";
    echo "<p>User logged in: " . (isset($_SESSION['user_id']) ? 'YES (ID: ' . $_SESSION['user_id'] . ')' : 'NO') . "</p>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "<p style='color:red;'>You need to log in first!</p>";
        echo "<p><a href='login.php'>Go to Login</a></p>";
        exit;
    }
    
    // Test database connection
    try {
        require_once __DIR__ . '/../../../backend/config/config.php';
        echo "<p style='color:green;'>Database connected successfully</p>";
        
        // Show all documents
        $stmt = $pdo->query("SELECT * FROM statutory_docs ORDER BY id");
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Available Documents:</h3>";
        if (empty($docs)) {
            echo "<p>No documents found in database</p>";
        } else {
            echo "<table border='1' style='border-collapse:collapse;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Type</th><th>Action</th></tr>";
            foreach ($docs as $doc) {
                echo "<tr>";
                echo "<td>" . $doc['id'] . "</td>";
                echo "<td>" . htmlspecialchars($doc['name']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['type']) . "</td>";
                echo "<td><a href='?delete_id=" . $doc['id'] . "' onclick='return confirm(\"Delete this document?\")'>DELETE</a></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // Handle deletion
        if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
            $delete_id = (int)$_GET['delete_id'];
            
            try {
                // Get document info first
                $stmt = $pdo->prepare("SELECT * FROM statutory_docs WHERE id = ?");
                $stmt->execute([$delete_id]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($doc) {
                    // Delete file if exists
                    $file_path = __DIR__ . '/../' . $doc['file_url'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                        echo "<p style='color:green;'>File deleted: " . $file_path . "</p>";
                    }
                    
                    // Delete from database
                    $stmt = $pdo->prepare("DELETE FROM statutory_docs WHERE id = ?");
                    $stmt->execute([$delete_id]);
                    
                    echo "<p style='color:green;'>Document '" . htmlspecialchars($doc['name']) . "' deleted successfully!</p>";
                    echo "<script>setTimeout(() => window.location.href='simple_delete.php', 2000);</script>";
                } else {
                    echo "<p style='color:red;'>Document not found</p>";
                }
                
            } catch (Exception $e) {
                echo "<p style='color:red;'>Error deleting: " . $e->getMessage() . "</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color:red;'>Database error: " . $e->getMessage() . "</p>";
    }
    ?>
    
    <p><a href="statutory.php">Back to Statutory Documents</a></p>
</body>
</html>