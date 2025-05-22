<?php
// Error reporting and session start
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database connection and credit update
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
        error_log("Credit assignment error: " . $e->getMessage());
    }
}

// Define game variables - using only emojis
$fields = array(
    "🥒",
    "🥕",
    "🍅",
    "🌱",
    "🥬"
);

$values = array(
    "10",
    "20",
    "30",
    "40",
    "50"
);

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

// Initialize session variables if they don't exist
if (!isset($_SESSION['game_state'])) {
    $_SESSION['wheel_1'] = 0;
    $_SESSION['wheel_2'] = 0;
    $_SESSION['wheel_3'] = 0;
    $_SESSION['game_state'] = 'betting'; // betting, playing, ended
    $_SESSION['win_amount'] = 0;
}

// Handle play again - THIS IS THE KEY FIX
if (isset($_POST['play_again'])) {
    // Reset game state to betting
    $_SESSION['game_state'] = 'betting';
    $_SESSION['win_amount'] = 0;
    unset($_SESSION['result_message']);
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
    
    // Initialize win amount
    $_SESSION['win_amount'] = 0;
    
    // Check for wins
    if ($_SESSION['wheel_1'] == $_SESSION['wheel_2'] && $_SESSION['wheel_2'] == $_SESSION['wheel_3']) {
        // Jackpot - all three match
        $symbol_index = $_SESSION['wheel_1'];
        $multiplier = intval($values[$symbol_index]);
        $_SESSION['win_amount'] = $_SESSION['current_bet'] * ($multiplier / 10);
        $message = "🎉 JACKPOT! You won " . $_SESSION['win_amount'] . " G$ with three " . $fields[$symbol_index] . "!";
    } elseif ($_SESSION['wheel_1'] == $_SESSION['wheel_2'] || 
              $_SESSION['wheel_2'] == $_SESSION['wheel_3'] || 
              $_SESSION['wheel_1'] == $_SESSION['wheel_3']) {
        // Two matching symbols
        $_SESSION['win_amount'] = $_SESSION['current_bet'] * 1.5;
        $message = "✨ Nice! You won " . $_SESSION['win_amount'] . " G$!";
    } else {
        // No matches
        $_SESSION['win_amount'] = 0;
        $message = "😢 No luck this time. Try again!";
    }
    
    // Add winnings to credit
    $_SESSION['credit'] += $_SESSION['win_amount'];
    $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10; // Format to one decimal place
    
    // Update database with new credit amount
    updateDatabaseCredit();
    
    // Set the game state to 'ended'
    $_SESSION['game_state'] = 'ended';
    
    // Store message in session to preserve it after redirect
    $_SESSION['result_message'] = $message;
}

// Retrieve stored message if it exists
if (isset($_SESSION['result_message'])) {
    $message = $_SESSION['result_message'];
}

// Function to update database with credit
function updateDatabaseCredit() {
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    try {
        $host = "localhost";
        $dbname = "data_db";
        $username = "root";
        $password = "";
        
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gurken Slots - Gurksino</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: rgb(0, 189, 16);
            color: white;
            margin: 0;
            padding: 20px;
            text-align: center;
        }

        h1 {
            text-align: center;
            font-family: Impact, sans-serif;
            font-size: 72px;
            margin: 20px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        h2 {
            font-family: Impact, sans-serif;
            font-size: 24px;
            margin: 15px 0;
        }

        .game-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: rgb(0, 82, 7);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        .credit-display {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
            font-family: Impact, sans-serif;
        }

        .slot-machine {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }

        .wheel {
            width: 100px;
            height: 120px;
            margin: 0 10px;
            background-color: white;
            color: black;
            border-radius: 5px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 48px; /* Larger font size for emojis */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, background-color 0.3s ease;
        }

        /* Add animation for spinning effect */
        @keyframes spin {
            0% { transform: translateY(-20px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .spinning .wheel {
            animation: spin 0.5s ease-out;
        }

        .status {
            font-size: 18px;
            margin: 15px 0;
            min-height: 50px; /* Ensure consistent height */
        }

        .betting-area, .actions {
            margin: 20px 0;
        }

        button, 
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s, transform 0.2s;
        }

        button:hover,
        input[type="submit"]:hover {
            background-color: #45a049;
            transform: scale(1.05);
        }

        button:active,
        input[type="submit"]:active {
            transform: scale(0.98);
        }

        input[type="number"] {
            padding: 10px;
            width: 100px;
            border-radius: 4px;
            border: none;
            margin-bottom: 15px;
            font-size: 16px;
            transition: box-shadow 0.3s;
        }

        input[type="number"]:focus {
            box-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
            outline: none;
        }

        .paytable {
            margin: 20px auto;
            padding: 15px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            max-width: 500px;
            text-align: left;
        }

        .paytable h3 {
            text-align: center;
            margin-bottom: 10px;
        }

        .emergency-button {
            display: inline-block;
            background-color: red;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            margin: 20px 0;
            transition: background-color 0.3s;
        }

        .emergency-button:hover {
            background-color: darkred;
        }

        .emergency-button h2 {
            color: white;
            margin: 0;
        }

        .back-link {
            background-color: white;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .back-link:hover {
            background-color: lightgray;
        }

        .back-link h2 {
            color: rgb(42, 42, 42);
            margin: 0;
        }

        /* Emoji descriptions for paytable */
        .emoji-desc {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
        }

        .emoji-desc span:first-child {
            font-size: 24px;
            margin-right: 10px;
            min-width: 30px;
            text-align: center;
        }

        /* Win animation */
        @keyframes win-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .win-animation {
            animation: win-pulse 0.5s ease-in-out infinite;
        }

        /* Responsive Design */
        @media screen and (max-width: 1200px) {
            h1 {
                font-size: 60px;
            }
        }

        @media screen and (max-width: 992px) {
            h1 {
                font-size: 48px;
            }
            
            .game-container {
                max-width: 700px;
            }
        }

        @media screen and (max-width: 768px) {
            h1 {
                font-size: 36px;
            }
            
            .game-container {
                max-width: 90%;
                padding: 15px;
            }
            
            .wheel {
                width: 80px;
                height: 100px;
                font-size: 36px;
            }
        }

        @media screen and (max-width: 576px) {
            h1 {
                font-size: 30px;
            }
            
            .game-container {
                padding: 10px;
            }
            
            .wheel {
                width: 60px;
                height: 80px;
                font-size: 28px;
            }
            
            button, input[type="submit"] {
                padding: 8px 16px;
            }
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>Gurken Slots</h1>
        
        <div class="credit-display">
            Your Credit: <?php echo isset($_SESSION['credit']) ? $_SESSION['credit'] : 0; ?> G$
        </div>
        
        <!-- Slot Machine Display -->
        <div class="slot-machine <?php echo $_SESSION['game_state'] == 'playing' ? 'spinning' : ''; ?>">
            <div class="wheel <?php echo ($_SESSION['game_state'] == 'ended' && $_SESSION['win_amount'] > 0) ? 'win-animation' : ''; ?>">
                <?php 
                if ($_SESSION['game_state'] == 'playing' || $_SESSION['game_state'] == 'betting') {
                    echo "❓";
                } else {
                    echo $fields[$_SESSION['wheel_1']];
                }
                ?>
            </div>
            <div class="wheel <?php echo ($_SESSION['game_state'] == 'ended' && $_SESSION['win_amount'] > 0) ? 'win-animation' : ''; ?>">
                <?php 
                if ($_SESSION['game_state'] == 'playing' || $_SESSION['game_state'] == 'betting') {
                    echo "❓";
                } else {
                    echo $fields[$_SESSION['wheel_2']];
                }
                ?>
            </div>
            <div class="wheel <?php echo ($_SESSION['game_state'] == 'ended' && $_SESSION['win_amount'] > 0) ? 'win-animation' : ''; ?>">
                <?php 
                if ($_SESSION['game_state'] == 'playing' || $_SESSION['game_state'] == 'betting') {
                    echo "❓";
                } else {
                    echo $fields[$_SESSION['wheel_3']];
                }
                ?>
            </div>
        </div>
        
        <div class="status">
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
        </div>
        
        <!-- Betting Screen -->
        <?php if ($_SESSION['game_state'] == 'betting'): ?>
            <div class="betting-area">
                <h2>Place Your Bet</h2>
                <form method="post">
                    <input type="number" name="bet" min="0.1" max="<?php echo $_SESSION['credit']; ?>" step="0.1" value="1.0" required>
                    <button type="submit" name="bet_button">Place Bet</button>
                </form>
                
                <div class="paytable">
                    <h3>Paytable</h3>
                    <p>Three matching symbols: Win up to 5x your bet!</p>
                    <p>Two matching symbols: Win 1.5x your bet</p>
                    <p>No matches: No win</p>
                    <p>Symbol Values:</p>
                    <div class="emoji-values">
                        <?php for ($i = 0; $i < count($fields); $i++): ?>
                            <div class="emoji-desc">
                                <span><?php echo $fields[$i]; ?></span>
                                <span><?php echo $values[$i]/10; ?>x multiplier</span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Playing Screen -->
        <?php if ($_SESSION['game_state'] == 'playing'): ?>
            <div class="actions">
                <h2>Current Bet: <?php echo $_SESSION['current_bet']; ?> G$</h2>
                <form method="post">
                    <button type="submit" name="spin_button">🎰 SPIN!</button>
                </form>
            </div>
        <?php endif; ?>
        
        <!-- Win/Result Screen -->
        <?php if ($_SESSION['game_state'] == 'ended'): ?>
            <div class="actions">
                <?php if ($_SESSION['win_amount'] > 0): ?>
                    <h2>🎉 You Won: <?php echo $_SESSION['win_amount']; ?> G$! 🎉</h2>
                <?php else: ?>
                    <h2>😢 Better luck next time!</h2>
                <?php endif; ?>
                
                <form method="post">
                    <button type="submit" name="play_again">🔄 Play Again</button>
                </form>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="../main.php" style="text-decoration: none;"><h2 style="color: rgb(42, 42, 42)">🏠 Main-Menu</h2></a>
        </div>
        
        <br>
        
        <div class="emergency-button">
            <a href="../../index.html" style="text-decoration: none;"><h2 style="color: #FFFFFF">🚨 NOT</h2></a>
        </div>
    </div>

    <!-- Add JavaScript for animation effects -->
    <script>
        // Add spinning animation when spin button is clicked
        document.addEventListener('DOMContentLoaded', function() {
            const spinButton = document.querySelector('button[name="spin_button"]');
            const slotMachine = document.querySelector('.slot-machine');
            
            if (spinButton) {
                spinButton.addEventListener('click', function() {
                    slotMachine.classList.add('spinning');
                });
            }
        });
    </script>
</body>
</html>