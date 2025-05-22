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

$values = array(
                "10",
                "20",
                "30",
                "40",
                "50");
$bet = 0;
$message = "";

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
    $_SESSION['win_amount'] = 0;
}

// Handle bet submission
if (isset($_POST['bet_button']) && $_SESSION['game_state'] == 'betting') {
    $bet = floatval($_POST['bet']);
    
    // Validate bet
    if ($bet <= 0) {
        $message = "Please enter a valid bet amount.";
    } elseif ($bet > $_SESSION['credit']) {
        $message = "You don't have enough credit for this bet.";
    } else {
        // Deduct bet from credit
        $_SESSION['credit'] -= $bet;
        $_SESSION['current_bet'] = $bet;
        $_SESSION['game_state'] = 'playing';
    }
}

// Handle spin
if (isset($_POST['spin_button']) && $_SESSION['game_state'] == 'playing') {
    // Generate random results for each wheel
    $_SESSION['wheel_1'] = rand(0, count($fields) - 1);
    $_SESSION['wheel_2'] = rand(0, count($fields) - 1);
    $_SESSION['wheel_3'] = rand(0, count($fields) - 1);
    
    // Check for wins
    if ($_SESSION['wheel_1'] == $_SESSION['wheel_2'] && $_SESSION['wheel_2'] == $_SESSION['wheel_3']) {
        // Jackpot - all three match
        $multiplier = $values[$_SESSION['wheel_1']];
        $_SESSION['win_amount'] = $_SESSION['current_bet'] * ($multiplier / 10);
        $message = "JACKPOT! You won " . $_SESSION['win_amount'] . " credits!";
    } elseif ($_SESSION['wheel_1'] == $_SESSION['wheel_2'] || 
              $_SESSION['wheel_2'] == $_SESSION['wheel_3'] || 
              $_SESSION['wheel_1'] == $_SESSION['wheel_3']) {
        // Two matching symbols
        $_SESSION['win_amount'] = $_SESSION['current_bet'] * 1.5;
        $message = "Nice! You won " . $_SESSION['win_amount'] . " credits!";
    } else {
        // No matches
        $_SESSION['win_amount'] = 0;
        $message = "No luck this time. Try again!";
    }
    
    // Add winnings to credit
    $_SESSION['credit'] += $_SESSION['win_amount'];
    $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10; // Format to one decimal place
    
    $_SESSION['game_state'] = 'ended';
}

// Handle play again
if (isset($_POST['play_again']) && $_SESSION['game_state'] == 'ended') {
    $_SESSION['game_state'] = 'betting';
    $_SESSION['win_amount'] = 0;
}

// Update database with new credit amount
if (isset($_SESSION['user_id']) && $_SESSION['game_state'] == 'ended') {
    try {
        $conn = mysqli_connect($host, $username, $password, $dbname);
        if (!mysqli_connect_errno()) {
            $user_id = $_SESSION['user_id'];
            $credit = $_SESSION['credit'];
            
            $sql = "UPDATE id SET credit = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("di", $credit, $user_id);
            $stmt->execute();
            
            $stmt->close();
            $conn->close();
        }
    } catch (Exception $e) {
        error_log("Error updating credit: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>GURKI-SLOTS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background-color: #f0f0f0;
            padding: 20px;
        }
        h1 {
            color: #2c3e50;
        }
        .slot-machine {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .wheel {
            width: 100px;
            height: 100px;
            margin: 0 10px;
            background-color: #fff;
            border: 2px solid #2c3e50;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .controls {
            margin: 20px 0;
        }
        .message {
            margin: 20px 0;
            font-weight: bold;
            color: #e74c3c;
        }
        .credit {
            font-size: 18px;
            margin-bottom: 20px;
        }
        input[type="number"] {
            padding: 8px;
            width: 100px;
        }
        input[type="submit"] {
            padding: 8px 16px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #27ae60;
        }
    </style>
</head>
<body>
    <h1>GURKI-SLOTS</h1>
    
    <div class="credit">
        Your Credit: <?php echo isset($_SESSION['credit']) ? $_SESSION['credit'] : 0; ?> credits
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="message"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if ($_SESSION['game_state'] == 'betting'): ?>
        <form action="" method="post">
            <label for="bet">Bet Amount:</label>   
            <input type="number" name="bet" placeholder="Bet" step="0.1" min="0.1" max="<?php echo $_SESSION['credit']; ?>">
            <input type="submit" name="bet_button" value="Place Bet">
        </form>
        
    <?php elseif ($_SESSION['game_state'] == 'playing'): ?>
        <div class="slot-machine">
            <div class="wheel">?</div>
            <div class="wheel">?</div>
            <div class="wheel">?</div>
        </div>
        
        <div class="controls">
            <form action="" method="post">
                <input type="submit" name="spin_button" value="SPIN!">
            </form>
        </div>
        
        <div>Current Bet: <?php echo $_SESSION['current_bet']; ?> credits</div>
        
    <?php elseif ($_SESSION['game_state'] == 'ended'): ?>
        <div class="slot-machine">
            <div class="wheel"><?php echo $fields[$_SESSION['wheel_1']]; ?></div>
            <div class="wheel"><?php echo $fields[$_SESSION['wheel_2']]; ?></div>
            <div class="wheel"><?php echo $fields[$_SESSION['wheel_3']]; ?></div>
        </div>
        
        <div class="controls">
            <form action="" method="post">
                <input type="submit" name="play_again" value="Play Again">
            </form>
        </div>
        
        <div>
            <?php if ($_SESSION['win_amount'] > 0): ?>
                You won: <?php echo $_SESSION['win_amount']; ?> credits!
            <?php else: ?>
                Better luck next time!
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div>
        <a href="../main.php">Back to Main</a>
    </div>
</body>
</html>