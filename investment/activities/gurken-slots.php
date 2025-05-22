<?php
// Error reporting and session start
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// CONFIGURATION SETTINGS
// Set to false to disable rigging and make the game fair
$enableRigging = true;
// Win rate percentage (1-100) - only applies when rigging is enabled
$winChance = 45; // 35% chance of winning

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

// Rigging parameters
$jackpotRate = 10; // 10% chance of jackpot when winning

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
    $_SESSION['total_spins'] = 0;
    $_SESSION['total_wins'] = 0;
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
    // Increment total spins counter
    if (!isset($_SESSION['total_spins'])) {
        $_SESSION['total_spins'] = 0;
    }
    $_SESSION['total_spins']++;
    
    // Initialize win amount
    $_SESSION['win_amount'] = 0;
    
    // Determine if this spin should be rigged
    $shouldRig = $enableRigging && (mt_rand(1, 100) > $winChance);
    
    if ($enableRigging) {
        if ($shouldRig) {
            // Rig for a loss - ensure no matches or only one match
            rigForLoss();
        } else {
            // Allow a fair win chance
            // Increment win counter
            if (!isset($_SESSION['total_wins'])) {
                $_SESSION['total_wins'] = 0;
            }
            $_SESSION['total_wins']++;
            
            // Determine if this should be a jackpot or just a partial match
            $isJackpot = (mt_rand(1, 100) <= $jackpotRate);
            
            if ($isJackpot) {
                // Rig for jackpot - all three symbols match
                rigForJackpot();
            } else {
                // Rig for partial win - two symbols match
                rigForPartialWin();
            }
        }
    } else {
        // No rigging - completely random results
        $_SESSION['wheel_1'] = mt_rand(0, count($fields) - 1);
        $_SESSION['wheel_2'] = mt_rand(0, count($fields) - 1);
        $_SESSION['wheel_3'] = mt_rand(0, count($fields) - 1);
        
        // Check if we won with fair play
        if ($_SESSION['wheel_1'] == $_SESSION['wheel_2'] && $_SESSION['wheel_2'] == $_SESSION['wheel_3'] ||
            $_SESSION['wheel_1'] == $_SESSION['wheel_2'] || 
            $_SESSION['wheel_2'] == $_SESSION['wheel_3'] || 
            $_SESSION['wheel_1'] == $_SESSION['wheel_3']) {
            // Increment win counter for statistics
            if (!isset($_SESSION['total_wins'])) {
                $_SESSION['total_wins'] = 0;
            }
            $_SESSION['total_wins']++;
        }
    }
    
    // Check for wins based on the rigged or fair outcome
    checkForWins();
    
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

// Function to rig for a loss
function rigForLoss() {
    global $fields;
    
    // Ensure all three wheels show different symbols
    $_SESSION['wheel_1'] = mt_rand(0, count($fields) - 1);
    
    // Make sure wheel 2 is different from wheel 1
    do {
        $_SESSION['wheel_2'] = mt_rand(0, count($fields) - 1);
    } while ($_SESSION['wheel_2'] == $_SESSION['wheel_1']);
    
    // Make sure wheel 3 is different from both wheel 1 and wheel 2
    do {
        $_SESSION['wheel_3'] = mt_rand(0, count($fields) - 1);
    } while ($_SESSION['wheel_3'] == $_SESSION['wheel_1'] || $_SESSION['wheel_3'] == $_SESSION['wheel_2']);
    
    // Sometimes (20% chance) allow two symbols to match for a small win
    // This makes the rigging less obvious
    if (mt_rand(1, 100) <= 20) {
        // Randomly decide which two wheels will match
        $matchCase = mt_rand(1, 3);
        switch ($matchCase) {
            case 1:
                $_SESSION['wheel_2'] = $_SESSION['wheel_1']; // Wheels 1 and 2 match
                break;
            case 2:
                $_SESSION['wheel_3'] = $_SESSION['wheel_1']; // Wheels 1 and 3 match
                break;
            case 3:
                $_SESSION['wheel_3'] = $_SESSION['wheel_2']; // Wheels 2 and 3 match
                break;
        }
    }
}

// Function to rig for a jackpot
function rigForJackpot() {
    global $fields, $values;
    
    // For jackpot, prefer lower value symbols to limit payouts
    // Higher chance of getting the lowest value symbols
    $weights = [50, 25, 15, 7, 3]; // Weights for each symbol (adds up to 100)
    
    // Select a symbol based on weights
    $rand = mt_rand(1, 100);
    $cumulativeWeight = 0;
    $selectedSymbol = 0;
    
    for ($i = 0; $i < count($weights); $i++) {
        $cumulativeWeight += $weights[$i];
        if ($rand <= $cumulativeWeight) {
            $selectedSymbol = $i;
            break;
        }
    }
    
    // Set all wheels to the same symbol
    $_SESSION['wheel_1'] = $selectedSymbol;
    $_SESSION['wheel_2'] = $selectedSymbol;
    $_SESSION['wheel_3'] = $selectedSymbol;
}

// Function to rig for a partial win (two matching symbols)
function rigForPartialWin() {
    global $fields;
    
    // Select a random symbol for the matching pair
    $matchingSymbol = mt_rand(0, count($fields) - 1);
    
    // Randomly decide which two wheels will match
    $matchCase = mt_rand(1, 3);
    
    // Set the matching wheels
    switch ($matchCase) {
        case 1:
            $_SESSION['wheel_1'] = $matchingSymbol;
            $_SESSION['wheel_2'] = $matchingSymbol;
            // Make sure wheel 3 is different
            do {
                $_SESSION['wheel_3'] = mt_rand(0, count($fields) - 1);
            } while ($_SESSION['wheel_3'] == $matchingSymbol);
            break;
        case 2:
            $_SESSION['wheel_1'] = $matchingSymbol;
            $_SESSION['wheel_3'] = $matchingSymbol;
            // Make sure wheel 2 is different
            do {
                $_SESSION['wheel_2'] = mt_rand(0, count($fields) - 1);
            } while ($_SESSION['wheel_2'] == $matchingSymbol);
            break;
        case 3:
            $_SESSION['wheel_2'] = $matchingSymbol;
            $_SESSION['wheel_3'] = $matchingSymbol;
            // Make sure wheel 1 is different
            do {
                $_SESSION['wheel_1'] = mt_rand(0, count($fields) - 1);
            } while ($_SESSION['wheel_1'] == $matchingSymbol);
            break;
    }
}

// Function to check for wins and set appropriate messages
function checkForWins() {
    global $fields, $values, $message;
    
    // Check for jackpot (all three match)
    if ($_SESSION['wheel_1'] == $_SESSION['wheel_2'] && $_SESSION['wheel_2'] == $_SESSION['wheel_3']) {
        // Jackpot - all three match
        $symbol_index = $_SESSION['wheel_1'];
        $multiplier = intval($values[$symbol_index]);
        $_SESSION['win_amount'] = $_SESSION['current_bet'] * ($multiplier / 10);
        $message = "🎉 JACKPOT! You won " . $_SESSION['win_amount'] . " G$ with three " . $fields[$symbol_index] . "!";
    } 
    // Check for two matching symbols
    else if ($_SESSION['wheel_1'] == $_SESSION['wheel_2'] || 
             $_SESSION['wheel_2'] == $_SESSION['wheel_3'] || 
             $_SESSION['wheel_1'] == $_SESSION['wheel_3']) {
        // Two matching symbols
        $_SESSION['win_amount'] = $_SESSION['current_bet'] * 1.5;
        $message = "✨ Nice! You won " . $_SESSION['win_amount'] . " G$!";
    } 
    // No matches
    else {
        // No matches
        $_SESSION['win_amount'] = 0;
        $message = "😢 No luck this time. Try again!";
    }
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

// Function to get win rate statistics
function getWinRateStats() {
    $totalSpins = isset($_SESSION['total_spins']) ? $_SESSION['total_spins'] : 0;
    $totalWins = isset($_SESSION['total_wins']) ? $_SESSION['total_wins'] : 0;
    
    if ($totalSpins > 0) {
        $winRate = round(($totalWins / $totalSpins) * 100);
        return "Win rate: $winRate% ($totalWins/$totalSpins)";
    }
    
    return "";
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
            perspective: 1000px;
        }

        .wheel-container {
            position: relative;
            width: 100px;
            height: 120px;
            margin: 0 10px;
            perspective: 1000px;
            transform-style: preserve-3d;
        }

        .wheel {
            width: 100%;
            height: 100%;
            background-color: white;
            color: black;
            border-radius: 5px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 48px; /* Larger font size for emojis */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            position: relative;
            transform-style: preserve-3d;
            backface-visibility: hidden;
        }

        /* Continuous spinning animation for idle state */
        @keyframes idle-spin {
            0% { transform: translateY(0); }
            25% { transform: translateY(-10px); }
            50% { transform: translateY(0); }
            75% { transform: translateY(10px); }
            100% { transform: translateY(0); }
        }

        /* Different speeds for each wheel in idle state */
        .idle .wheel-container:nth-child(1) .wheel {
            animation: idle-spin 1.5s infinite ease-in-out;
        }

        .idle .wheel-container:nth-child(2) .wheel {
            animation: idle-spin 2s infinite ease-in-out;
            animation-delay: 0.2s;
        }

        .idle .wheel-container:nth-child(3) .wheel {
            animation: idle-spin 1.8s infinite ease-in-out;
            animation-delay: 0.4s;
        }

        /* Spinning animations for each wheel with different durations */
        @keyframes spin-wheel-1 {
            0% { transform: rotateX(0deg); }
            100% { transform: rotateX(3600deg); }
        }

        @keyframes spin-wheel-2 {
            0% { transform: rotateX(0deg); }
            100% { transform: rotateX(3960deg); }
        }

        @keyframes spin-wheel-3 {
            0% { transform: rotateX(0deg); }
            100% { transform: rotateX(4320deg); }
        }

        .spinning .wheel-container:nth-child(1) .wheel {
            animation: spin-wheel-1 2s ease-out forwards;
        }

        .spinning .wheel-container:nth-child(2) .wheel {
            animation: spin-wheel-2 2.5s ease-out forwards;
        }

        .spinning .wheel-container:nth-child(3) .wheel {
            animation: spin-wheel-3 3s ease-out forwards;
        }

        /* Win animation */
        @keyframes win-pulse {
            0% { transform: scale(1); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3); }
            50% { transform: scale(1.1); box-shadow: 0 0 20px rgba(255, 215, 0, 0.7); }
            100% { transform: scale(1); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3); }
        }

        .win-animation {
            animation: win-pulse 0.8s ease-in-out infinite;
            background-color: #ffeb3b;
        }

        .status {
            font-size: 18px;
            margin: 15px 0;
            min-height: 50px; /* Ensure consistent height */
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 5px;
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

        /* Slot machine frame styling */
        .slot-frame {
            background-color: #8B4513;
            padding: 20px;
            border-radius: 10px;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.5);
            margin-bottom: 20px;
        }

        .slot-display {
            background-color: #333;
            padding: 15px;
            border-radius: 5px;
            display: inline-block;
        }

        /* Spin button special styling */
        .spin-button {
            background-color: #ff5722;
            font-size: 20px;
            padding: 12px 30px;
            transition: all 0.3s;
        }

        .spin-button:hover {
            background-color: #e64a19;
            transform: scale(1.1);
        }

        /* Stats display (hidden by default) */
        .stats-display {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 10px;
            display: none;
        }

        /* Show stats when debug mode is active */
        .debug-mode .stats-display {
            display: block;
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
            
            .wheel-container {
                width: 80px;
                height: 100px;
            }
            
            .wheel {
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
            
            .wheel-container {
                width: 60px;
                height: 80px;
            }
            
            .wheel {
                font-size: 28px;
            }
            
            button, input[type="submit"] {
                padding: 8px 16px;
            }
        }

        /* Lever animation */
        .lever {
            width: 20px;
            height: 100px;
            background-color: #333;
            border-radius: 10px;
            position: relative;
            margin: 0 auto 20px;
            cursor: pointer;
            transform-origin: bottom center;
            transition: transform 0.3s;
        }

        .lever:before {
            content: '';
            position: absolute;
            width: 30px;
            height: 30px;
            background-color: red;
            border-radius: 50%;
            top: -15px;
            left: -5px;
        }

        .lever.pulled {
            transform: rotate(30deg);
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>Gurken Slots</h1>
        
        <div class="credit-display">
            Your Credit: <?php echo isset($_SESSION['credit']) ? $_SESSION['credit'] : 0; ?> G$
        </div>
        
        <!-- Slot Machine Display with Frame -->
        <div class="slot-frame">
            <div class="slot-display">
                <div class="slot-machine <?php echo $_SESSION['game_state'] == 'playing' ? 'spinning' : ($_SESSION['game_state'] == 'betting' ? 'idle' : ''); ?>">
                    <div class="wheel-container">
                        <div class="wheel <?php echo ($_SESSION['game_state'] == 'ended' && $_SESSION['win_amount'] > 0 && $_SESSION['wheel_1'] == $_SESSION['wheel_2'] && $_SESSION['wheel_1'] == $_SESSION['wheel_3']) ? 'win-animation' : ''; ?>">
                            <?php 
                            if ($_SESSION['game_state'] == 'playing') {
                                echo "❓";
                            } else if ($_SESSION['game_state'] == 'betting') {
                                echo "❓";
                            } else {
                                echo $fields[$_SESSION['wheel_1']];
                            }
                            ?>
                        </div>
                    </div>
                    <div class="wheel-container">
                        <div class="wheel <?php echo ($_SESSION['game_state'] == 'ended' && $_SESSION['win_amount'] > 0 && $_SESSION['wheel_1'] == $_SESSION['wheel_2'] && $_SESSION['wheel_1'] == $_SESSION['wheel_3']) ? 'win-animation' : ''; ?>">
                            <?php 
                            if ($_SESSION['game_state'] == 'playing') {
                                echo "❓";
                            } else if ($_SESSION['game_state'] == 'betting') {
                                echo "❓";
                            } else {
                                echo $fields[$_SESSION['wheel_2']];
                            }
                            ?>
                        </div>
                    </div>
                    <div class="wheel-container">
                        <div class="wheel <?php echo ($_SESSION['game_state'] == 'ended' && $_SESSION['win_amount'] > 0 && $_SESSION['wheel_1'] == $_SESSION['wheel_2'] && $_SESSION['wheel_1'] == $_SESSION['wheel_3']) ? 'win-animation' : ''; ?>">
                            <?php 
                            if ($_SESSION['game_state'] == 'playing') {
                                echo "❓";
                            } else if ($_SESSION['game_state'] == 'betting') {
                                echo "❓";
                            } else {
                                echo $fields[$_SESSION['wheel_3']];
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="status">
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
        </div>
        
        <!-- Stats display (hidden unless in debug mode) -->
        <div class="stats-display">
            <?php echo getWinRateStats(); ?>
        </div>
        
        <!-- Betting Screen -->
        <?php if ($_SESSION['game_state'] == 'betting'): ?>
            <div class="betting-area">
                <h2>Place Your Bet</h2>
                <form method="post">
                    <input type="number" name="bet" min="0.1" max="<?php echo $_SESSION['credit']; ?>" step="0.1" value="1.0" required>
                    <button type="submit" name="bet_button">💰 Place Bet</button>
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
                <form method="post" id="spin-form">
                    <input type="hidden" name="spin_button" value="1">
                    <button type="submit" class="spin-button">🎰 SPIN!</button>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const slotMachine = document.querySelector('.slot-machine');
            const wheels = document.querySelectorAll('.wheel');
            const symbols = ['🥒', '🥕', '🍅', '🌱', '🥬'];
            
            // Function to update symbols in idle state
            function updateIdleSymbols() {
                if (slotMachine && slotMachine.classList.contains('idle')) {
                    wheels.forEach(wheel => {
                        // Only change symbols during idle animation
                        if (wheel.textContent === '❓') {
                            const randomSymbol = symbols[Math.floor(Math.random() * symbols.length)];
                            wheel.textContent = randomSymbol;
                            
                            // Reset to question mark after a brief delay
                            setTimeout(() => {
                                if (wheel.parentNode.parentNode.classList.contains('idle')) {
                                    wheel.textContent = '❓';
                                }
                            }, 100);
                        }
                    });
                }
            }
            
            // Start continuous idle animation if in betting state
            if (slotMachine && slotMachine.classList.contains('idle')) {
                // Update symbols periodically
                setInterval(updateIdleSymbols, 200);
            }
            
            // Handle spinning animation
            if (slotMachine && slotMachine.classList.contains('spinning')) {
                // Create random spinning symbols during animation
                let spinInterval = setInterval(function() {
                    wheels.forEach(wheel => {
                        // Only change symbols during spinning animation
                        if (wheel.textContent === '❓') {
                            const randomSymbol = symbols[Math.floor(Math.random() * symbols.length)];
                            wheel.textContent = randomSymbol;
                        }
                    });
                }, 100);
                
                // Clear interval after animations complete
                setTimeout(function() {
                    clearInterval(spinInterval);
                }, 3000);
            }
            
            // Secret debug mode - press D key 3 times quickly
            let keyPresses = [];
            let keyTimeout;
            
            document.addEventListener('keydown', function(e) {
                if (e.key.toLowerCase() === 'd') {
                    keyPresses.push(Date.now());
                    
                    // Only keep the last 3 presses
                    if (keyPresses.length > 3) {
                        keyPresses.shift();
                    }
                    
                    // Check if we have 3 presses within 1 second
                    if (keyPresses.length === 3 && 
                        (keyPresses[2] - keyPresses[0]) < 1000) {
                        
                        // Toggle debug mode
                        document.querySelector('.game-container').classList.toggle('debug-mode');
                        
                        // Clear the key presses
                        keyPresses = [];
                    }
                    
                    // Clear timeout if it exists
                    if (keyTimeout) {
                        clearTimeout(keyTimeout);
                    }
                    
                    // Set timeout to clear key presses after 1 second
                    keyTimeout = setTimeout(function() {
                        keyPresses = [];
                    }, 1000);
                }
            });
        });
    </script>
</body>
</html>