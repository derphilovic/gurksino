<?php 
//error reporting and session start
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Check if user is logged in and has enough credit
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
            header("Location: ../main.php");
            exit;
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
        error_log("Credit assignment error: " . $e->getMessage());
    }
}

//Define variables
$fields = array(
                "gurke",
                "karotte",
                "tomate",
                "radieschen",
                "zucchini");

// Format credit to have only one decimal place
if (isset($_SESSION['credit'])) {
    $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
}

// Check if user has credit - redirect if credit is 0 or less
if (!isset($_SESSION['credit']) || $_SESSION['credit'] <= 0) {
    header("Location: ../main.php");
    exit;
}

//create session variables for game state and wheel values
if (!isset($_SESSION['wheel_1'])) {
    $_SESSION['wheel_1'] = 0;
    $_SESSION['wheel_2'] = 0;
    $_SESSION['wheel_3'] = 0;
    $_SESSION['game_state'] = 'betting'; // betting, playing, ended
}

?>

<h1>GURKI-SLOTS</h1>