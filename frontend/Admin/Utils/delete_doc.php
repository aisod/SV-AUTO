<?php
session_start();

// Simple debug info
echo "<h2>Delete Document Debug</h2>";
echo "Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "<br>";
echo "GET parameters: " . print_r($_GET, true) . "<br>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>User not logged in. Redirecting to login...</p>";
    echo "<script>setTimeout(() => window.location.href='login.php', 2000);</script>";
    exit;
}

// Include config
try {
    require_once __DIR__ . '/../../../backend/config/config.php';
    echo "<p style='color:green;'>Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p style='color:red;'>Invalid or missing document ID</p>";
    echo "<a href='statutory.php'>Back to Statutory Documents</a>";
    exit;
}

$doc_id = (int)$_GET['id'];
echo "<p>Document ID to delete: $doc_id</p>";

// Get document info
try {
    $stmt = $pdo->prepare("SELECT * FROM statutory_docs WHERE id = ?");
    $stmt->execute([$doc_id]);
    $document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$document) {
        echo "<p style='color:red;'>Document not found in database</p>";
        echo "<a href='statutory.php'>Back to Statutory Documents</a>";
        exit;
    }
    
    echo "<h3>Document Found:</h3>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($document['name']) . "</p>";
    echo "<p><strong>Type:</strong> " . htmlspecialchars($document['type']) . "</p>";
    echo "<p><strong>File URL:</strong> " . htmlspecialchars($document['file_url']) . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Database error: " . $e->getMessage() . "</p>";
    exit;
}

// If confirm parameter is set, perform deletion
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    echo "<h3>Performing Deletion...</h3>";
    
    try {
        // Delete the physical file if it exists
        $file_path = __DIR__ . '/../' . $document['file_url'];
        echo "<p>File path: $file_path</p>";
        
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                echo "<p style='color:green;'>Physical file deleted successfully</p>";
            } else {
                echo "<p style='color:orange;'>Failed to delete physical file</p>";
            }
        } else {
            echo "<p style='color:orange;'>Physical file not found (may have been deleted already)</p>";
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM statutory_docs WHERE id = ?");
        $stmt->execute([$doc_id]);
        
        if ($stmt->rowCount() > 0) {
            echo "<p style='color:green;'>Document deleted from database successfully!</p>";
            echo "<script>setTimeout(() => window.location.href='statutory.php?success=" . urlencode('Document deleted successfully') . "', 2000);</script>";
        } else {
            echo "<p style='color:red;'>Failed to delete from database</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color:red;'>Database deletion error: " . $e->getMessage() . "</p>";
    }
    
} else {
    // Show confirmation form
    echo "<h3>Confirm Deletion</h3>";
    echo "<p style='color:red;'><strong>Warning:</strong> This will permanently delete the document!</p>";
    echo "<p><a href='?id=$doc_id&confirm=yes' style='background:red;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>YES, DELETE IT</a></p>";
    echo "<p><a href='statutory.php' style='background:gray;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>Cancel</a></p>";
}
?>