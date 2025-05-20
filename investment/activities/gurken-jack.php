<?php
// Add this near the top of the file, after session_start()
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Add this variable to control rigging
$enableRigging = false; // Set to false to disable rigged gameplay

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

// Define the deck of cards
$deck = array(
    "A" => 11,
    "2" => 2,
    "3" => 3,
    "4" => 4,
    "5" => 5,
    "6" => 6,
    "7" => 7,
    "8" => 8,
    "9" => 9,
    "10" => 10,
    "J" => 10,
    "Q" => 10,
    "K" => 10
);

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
if (!isset($_SESSION['player_hand'])) {
    $_SESSION['player_hand'] = array();
    $_SESSION['dealer_hand'] = array();
    $_SESSION['playerScore'] = 0;
    $_SESSION['dealerScore'] = 0;
    $_SESSION['game_state'] = 'betting'; // betting, playing, ended
}

$bet = 0; // Default value
if (isset($_POST['bet']) && is_numeric($_POST['bet'])) {
    $bet = (float)$_POST['bet'];
    // Round bet to one decimal place
    $bet = round($bet * 10) / 10;
    
    // Start a new game when bet is placed
    if ($bet > 0 && $_SESSION['game_state'] == 'betting') {
        // Check if bet is not greater than available credit
        if ($bet > $_SESSION['credit']) {
            $bet = $_SESSION['credit']; // Limit bet to available credit
        }
        
        $_SESSION['bet'] = $bet;
        $_SESSION['game_state'] = 'playing';
        $_SESSION['player_hand'] = array();
        $_SESSION['dealer_hand'] = array();
        
        // Initial deal - 2 cards each
        dealCard('player');
        dealCard('dealer');
        dealCard('player');
        dealCard('dealer'); // Add second card for dealer
        
        calculateScores();
    }
} else if (isset($_SESSION['bet'])) {
    $bet = $_SESSION['bet'];
}

// Handle actions
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'hit' && $_SESSION['game_state'] == 'playing') {
        // Rig the game: Higher chance of getting high cards when hitting
        dealRiggedCard('player', true); // true = rig for high cards (more likely to bust)
        calculateScores();
        
        // Check if player busts
        if ($_SESSION['playerScore'] > 21) {
            $_SESSION['game_state'] = 'ended';
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] = max(0, $_SESSION['credit'] - $bet);
                // Round to one decimal place
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        }
    }
    else if ($_POST['action'] == 'stand' && $_SESSION['game_state'] == 'playing') {
        // Dealer's turn
    
        // Dealer hits until 17 or higher
        while ($_SESSION['dealerScore'] < 17) {
            dealRiggedCard('dealer', false);
            calculateScores();
        }
    
        $_SESSION['game_state'] = 'ended';
    
        // Use the determineWinner function to handle the outcome
        determineWinner($bet);
    }
    else if ($_POST['action'] == 'new_game') {
        // Check if user still has credit
        if ($_SESSION['credit'] <= 0) {
            header("Location: ../main.php");
            exit;
        }
        
        // Reset for a new game
        $_SESSION['game_state'] = 'betting';
        $_SESSION['player_hand'] = array();
        $_SESSION['dealer_hand'] = array();
        $_SESSION['playerScore'] = 0;
        $_SESSION['dealerScore'] = 0;
        unset($_SESSION['bet']);
    }
}

// Function to deal initial cards with a preference for lower cards
function dealCard($to) {
    global $deck;
    
    // For initial dealing, favor lower cards to make the game more realistic
    if (count($_SESSION[$to.'_hand']) < 2) {
        // Define card sets for initial deal
        $lowCardSet = array("2", "3", "4", "5", "6");
        $mediumCardSet = array("7", "8", "9");
        $highCardSet = array("10", "J", "Q", "K", "A");
        
        // Weighted distribution for initial cards
        $rand = mt_rand(1, 100);
        if ($rand <= 50) {
            // 50% chance of low card
            $card = $lowCardSet[array_rand($lowCardSet)];
        } elseif ($rand <= 80) {
            // 30% chance of medium card
            $card = $mediumCardSet[array_rand($mediumCardSet)];
        } else {
            // 20% chance of high card
            $card = $highCardSet[array_rand($highCardSet)];
        }
    } else {
        // For subsequent cards, use normal distribution
        $card = array_rand($deck);
    }
    
    $_SESSION[$to.'_hand'][] = $card;
    calculateScores();
}

// Function to deal rigged cards more subtly
function dealRiggedCard($to, $highCards) {
    global $deck, $enableRigging;
    
    // If rigging is disabled, just deal a random card
    if (!$enableRigging) {
        $card = array_rand($deck);
        $_SESSION[$to.'_hand'][] = $card;
        calculateScores();
        return;
    }
    
    // Define card sets
    $highCardSet = array("10", "J", "Q", "K");
    $mediumCardSet = array("7", "8", "9");
    $lowCardSet = array("2", "3", "4", "5", "6");
    $aceSet = array("A");
    
    // Get current scores
    $playerScore = $_SESSION['playerScore'];
    $dealerScore = $_SESSION['dealerScore'];
    
    // 65% chance to rig (still subtle but allows for 35% natural play)
    $shouldRig = mt_rand(1, 100) <= 65;
    
    if ($shouldRig) {
        if ($to == 'player') {
            // Player has ~35% chance to get favorable cards
            $playerLuck = mt_rand(1, 100);
            
            if ($playerScore >= 16) {
                // Player is at risk of busting
                if ($playerLuck <= 35) {
                    // 35% chance to get a helpful low card
                    $card = $lowCardSet[array_rand($lowCardSet)];
                } else {
                    // 65% chance to get a risky high card
                    $card = $highCardSet[array_rand($highCardSet)];
                }
            } else if ($playerScore >= 12 && $playerScore <= 15) {
                // Medium risk zone
                if ($playerLuck <= 35) {
                    // 35% chance to get a medium card
                    $card = $mediumCardSet[array_rand($mediumCardSet)];
                } else {
                    // 65% chance to get a high card
                    $card = $highCardSet[array_rand($highCardSet)];
                }
            } else {
                // Low score, less rigging needed
                // But still favor medium cards to make it more realistic
                $randCard = mt_rand(1, 100);
                if ($randCard <= 60) {
                    $card = $mediumCardSet[array_rand($mediumCardSet)];
                } else {
                    $card = array_rand($deck);
                }
            }
        } else { // dealer
            // Dealer has ~65% chance to get favorable cards
            $dealerLuck = mt_rand(1, 100);
            
            if ($dealerScore < 17) {
                $neededValue = 17 - $dealerScore;
                
                if ($dealerLuck <= 65) {
                    // 65% chance dealer gets what they need, but more realistically
                    if ($neededValue >= 8) {
                        // Need high cards
                        $card = $highCardSet[array_rand($highCardSet)];
                    } else if ($neededValue >= 4) {
                        // Need medium cards
                        $card = $mediumCardSet[array_rand($mediumCardSet)];
                    } else {
                        // Need low cards
                        $card = $lowCardSet[array_rand($lowCardSet)];
                    }
                } else {
                    // 35% chance dealer gets a suboptimal card
                    // But make it more realistic - not always busting
                    if ($dealerScore <= 11) {
                        // If dealer has low score, give medium cards instead of high
                        $card = $mediumCardSet[array_rand($mediumCardSet)];
                    } else if ($dealerScore >= 12 && $dealerScore <= 16) {
                        // If dealer is in danger zone, higher chance of bust
                        $bustChance = mt_rand(1, 100);
                        if ($bustChance <= 40) {
                            $card = $highCardSet[array_rand($highCardSet)];
                        } else {
                            $card = $lowCardSet[array_rand($lowCardSet)];
                        }
                    } else {
                        // Random card for other scenarios
                        $card = array_rand($deck);
                    }
                }
            } else if ($dealerScore >= 17 && $dealerScore <= 21) {
                // Dealer already has a good hand
                if ($dealerLuck <= 80) {
                    // 80% chance to stand pat (by getting a low card)
                    $card = $lowCardSet[array_rand($lowCardSet)];
                } else {
                    // 20% chance to get a risky card
                    $card = $highCardSet[array_rand($highCardSet)];
                }
            } else {
                // Random card if dealer busted already
                $card = array_rand($deck);
            }
        }
    } else {
        // Sometimes deal truly random cards to maintain appearance of fairness
        $card = array_rand($deck);
    }
    
    $_SESSION[$to.'_hand'][] = $card;
    calculateScores();
}

// Function to calculate scores
function calculateScores() {
    global $deck;
    
    // Calculate player score
    $score = 0;
    $aces = 0;
    foreach ($_SESSION['player_hand'] as $card) {
        $score += $deck[$card];
        if ($card == 'A') $aces++;
    }
    
    // Adjust for aces if needed
    while ($score > 21 && $aces > 0) {
        $score -= 10; // Convert an Ace from 11 to 1
        $aces--;
    }
    
    $_SESSION['playerScore'] = $score;
    
    // Calculate dealer score
    $score = 0;
    $aces = 0;
    foreach ($_SESSION['dealer_hand'] as $card) {
        $score += $deck[$card];
        if ($card == 'A') $aces++;
    }
    
    // Adjust for aces if needed
    while ($score > 21 && $aces > 0) {
        $score -= 10; // Convert an Ace from 11 to 1
        $aces--;
    }
    
    $_SESSION['dealerScore'] = $score;
}

// Function to determine winner with rigging
function determineWinner($bet) {
    global $enableRigging;
    
    // If rigging is disabled, determine winner fairly
    if (!$enableRigging) {
        if ($_SESSION['dealerScore'] > 21) {
            // Dealer busts - player wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] += $bet;
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        } 
        else if ($_SESSION['playerScore'] > $_SESSION['dealerScore']) {
            // Player wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] += $bet;
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        } 
        else if ($_SESSION['playerScore'] < $_SESSION['dealerScore']) {
            // Dealer wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] = max(0, $_SESSION['credit'] - $bet);
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        }
        // It's a tie - no money changes hands
        return;
    }
    
    // Determine winner with subtle rigging for 35% player win rate
    if ($_SESSION['dealerScore'] > 21) {
        // Dealer busts - player would win
        // But let's add a subtle chance that dealer gets a better hand
        if (mt_rand(1, 100) <= 15) { // 15% chance to rig even when dealer busts
            // Instead of just changing the score, actually modify the dealer's hand
            $targetScore = mt_rand(17, 21);
            adjustDealerHand($targetScore);
            
            // Dealer wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] = max(0, $_SESSION['credit'] - $bet);
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        } else {
            // Player wins normally (85% chance when dealer busts)
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] += $bet;
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        }
    } 
    else if ($_SESSION['playerScore'] > $_SESSION['dealerScore']) {
        // Player would win, but let's add a chance that dealer gets a better hand
        if (mt_rand(1, 100) <= 50) { // 50% chance to rig when player is ahead
            // Make it look natural - dealer gets exactly what they need
            $targetScore = $_SESSION['playerScore'] + mt_rand(1, 2);
            if ($targetScore > 21) $targetScore = 21;
            
            adjustDealerHand($targetScore);
            
            // Dealer wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] = max(0, $_SESSION['credit'] - $bet);
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        } else {
            // Player wins (50% chance when player is ahead)
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] += $bet;
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        }
    } 
    else if ($_SESSION['playerScore'] < $_SESSION['dealerScore']) {
        // Dealer is ahead, give player a small chance to win anyway
        if (mt_rand(1, 100) <= 10) { // 10% chance for player to win when behind
            // Make it look natural - dealer busts
            $targetScore = 22 + mt_rand(0, 3); // More realistic bust (22-25)
            adjustDealerHand($targetScore);
            
            // Player wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] += $bet;
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        } else {
            // Dealer wins (90% chance when dealer is ahead)
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] = max(0, $_SESSION['credit'] - $bet);
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        }
    }
    else { // It's a tie
        // In ties, slightly favor the house
        if (mt_rand(1, 100) <= 70) { // 70% chance dealer wins on tie
            // Dealer gets one more point
            $targetScore = $_SESSION['dealerScore'] + 1;
            adjustDealerHand($targetScore);
            
            // Dealer wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] = max(0, $_SESSION['credit'] - $bet);
                $_SESSION['credit'] = round($_SESSION['credit'] * 10) / 10;
            }
        }
        // 30% chance it remains a tie, no money changes hands
    }
}

// Add this function after the calculateScores function
function adjustDealerHand($targetScore) {
    global $deck;
    
    // Start fresh with a new hand that adds up to the target score
    $_SESSION['dealer_hand'] = [];
    
    // Add cards to reach the target score
    $remainingScore = $targetScore;
    
    // First card is often a 10-value card
    if ($remainingScore >= 10) {
        $tenCards = ["10", "J", "Q", "K"];
        $_SESSION['dealer_hand'][] = $tenCards[array_rand($tenCards)];
        $remainingScore -= 10;
    }
    
    // Add more cards as needed
    while ($remainingScore > 0) {
        if ($remainingScore >= 10) {
            $tenCards = ["10", "J", "Q", "K"];
            $_SESSION['dealer_hand'][] = $tenCards[array_rand($tenCards)];
            $remainingScore -= 10;
        } else if ($remainingScore == 1) {
            // Special case for Ace (can be 1 or 11)
            $_SESSION['dealer_hand'][] = "A";
            $remainingScore = 0;
        } else {
            // Add a card with the exact remaining value
            $_SESSION['dealer_hand'][] = (string)$remainingScore;
            $remainingScore = 0;
        }
    }
    
    // Recalculate scores with the new hand
    calculateScores();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gurken Jack - Blackjack Game</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #2c3e50;
            color: white;
            margin: 0;
            padding: 20px;
            text-align: center;
        }
        .game-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #34495e;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }
        .card-area {
            margin: 20px 0;
            min-height: 120px;
            background-color: #2c3e50;
            border-radius: 5px;
            padding: 10px;
        }
        .card {
            display: inline-block;
            width: 80px;
            height: 120px;
            background-color: white;
            color: black;
            margin: 5px;
            border-radius: 5px;
            line-height: 120px;
            font-size: 24px;
            font-weight: bold;
        }
        .actions {
            margin: 20px 0;
        }
        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        input[type="number"] {
            padding: 10px;
            width: 100px;
            border-radius: 4px;
            border: none;
        }
        .status {
            font-size: 18px;
            margin: 15px 0;
        }
        .credit-display {
            font-size: 20px;
            font-weight: bold;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <h1>Gurken Jack</h1>
        
        <div class="credit-display">
            Your Credit: <?php echo isset($_SESSION['credit']) ? $_SESSION['credit'] : 0; ?> G$
        </div>
        
        <?php if ($_SESSION['game_state'] == 'betting'): ?>
            <div class="betting-area">
                <h2>Place Your Bet</h2>
                <form method="post">
                    <input type="number" name="bet" min="0.1" max="<?php echo $_SESSION['credit']; ?>" step="0.1" required>
                    <button type="submit">Place Bet</button>
                </form>
            </div>
        <?php else: ?>
            <div class="game-area">
                <h2>Dealer's Hand (Score: <?php echo $_SESSION['game_state'] == 'ended' ? $_SESSION['dealerScore'] : '?'; ?>)</h2>
                <div class="card-area">
                    <?php 
                    if (!empty($_SESSION['dealer_hand'])) {
                        // Show first card
                        echo '<div class="card">' . $_SESSION['dealer_hand'][0] . '</div>';
                        
                        // Show second card only if game is ended
                        if ($_SESSION['game_state'] == 'ended') {
                            for ($i = 1; $i < count($_SESSION['dealer_hand']); $i++) {
                                echo '<div class="card">' . $_SESSION['dealer_hand'][$i] . '</div>';
                            }
                        } else {
                            // Show hidden card
                            echo '<div class="card">?</div>';
                        }
                    }
                    ?>
                </div>
                
                <h2>Your Hand (Score: <?php echo $_SESSION['playerScore']; ?>)</h2>
                <div class="card-area">
                    <?php 
                    if (!empty($_SESSION['player_hand'])) {
                        foreach ($_SESSION['player_hand'] as $card) {
                            echo '<div class="card">' . $card . '</div>';
                        }
                    }
                    ?>
                </div>
                
                <div class="status">
                    <?php 
                    if ($_SESSION['game_state'] == 'ended') {
                        if ($_SESSION['playerScore'] > 21) {
                            echo "Bust! You lose.";
                        } else if ($_SESSION['dealerScore'] > 21) {
                            echo "Dealer busts! You win!";
                        } else if ($_SESSION['playerScore'] > $_SESSION['dealerScore']) {
                            echo "You win!";
                        } else if ($_SESSION['playerScore'] < $_SESSION['dealerScore']) {
                            echo "Dealer wins.";
                        } else {
                            echo "It's a tie!";
                        }
                    } else {
                        echo "Current bet: " . $_SESSION['bet'] . " G$";
                    }
                    ?>
                </div>
                
                <div class="actions">
                    <?php if ($_SESSION['game_state'] == 'playing'): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="hit">
                            <button type="submit">Hit</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="stand">
                            <button type="submit">Stand</button>
                        </form>
                    <?php elseif ($_SESSION['game_state'] == 'ended'): ?>
                        <form method="post">
                            <input type="hidden" name="action" value="new_game">
                            <button type="submit">New Game</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="../main.php" style="color: white;">Back to Main</a>
        </div>
    </div>
</body>
</html>
