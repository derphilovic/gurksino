<?php
// Start session
session_start();

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        // Database connection parameters
        $host = "localhost";
        $dbname = "data_db";
        $username = "root";
        $password = "";
        
        // Connect to database with error handling
        $conn = mysqli_connect($host, $username, $password, $dbname);
        
        // Check connection
        if (mysqli_connect_errno()) {
            // Log error but continue with logout process
            error_log("Database connection failed: " . mysqli_connect_error());
        } else {
            // Get user data from session
            $user_id = $_SESSION['user_id'];
            $credit = $_SESSION['credit'];
            
            // Update user credit in database
            $sql = "UPDATE id SET credit = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $credit, $user_id);
            $stmt->execute();
            
            // Close database connection
            $stmt->close();
            $conn->close();
        }
    } catch (Exception $e) {
        // Log error but continue with logout process
        error_log("Error in logout process: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to main page
header("Location: main.php");
exit;
?>
