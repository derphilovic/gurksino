<?php
session_start();
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
                $_SESSION['credit'] -= $bet;
            }
        }
    }
    else if ($_POST['action'] == 'stand' && $_SESSION['game_state'] == 'playing') {
        // Dealer's turn
        dealCard('dealer'); // Reveal second card
        
        // Dealer hits until 17 or higher
        while ($_SESSION['dealerScore'] < 17) {
            // Rig the game: Higher chance of getting low cards for dealer
            dealRiggedCard('dealer', false); // false = rig for low cards (less likely to bust)
            calculateScores();
        }
        
        $_SESSION['game_state'] = 'ended';
        
        // Determine winner with rigged odds
        if ($_SESSION['dealerScore'] > 21) {
            // Dealer busts - player wins
            // But let's add a 30% chance that dealer magically doesn't bust
            if (mt_rand(1, 100) <= 30) {
                $_SESSION['dealerScore'] = mt_rand(17, 21);
                // Now check if dealer wins
                if ($_SESSION['dealerScore'] > $_SESSION['playerScore']) {
                    if (isset($_SESSION['credit'])) {
                        $_SESSION['credit'] -= $bet;
                    }
                } else if ($_SESSION['dealerScore'] < $_SESSION['playerScore']) {
                    if (isset($_SESSION['credit'])) {
                        $_SESSION['credit'] += $bet;
                    }
                }
                // Else it's a push (tie)
            } else {
                // Player wins normally
                if (isset($_SESSION['credit'])) {
                    $_SESSION['credit'] += $bet;
                }
            }
        } 
        else if ($_SESSION['playerScore'] > $_SESSION['dealerScore']) {
            // Player would normally win, but let's add a 40% chance that dealer gets a better hand
            if (mt_rand(1, 100) <= 40) {
                $_SESSION['dealerScore'] = $_SESSION['playerScore'] + mt_rand(1, 3);
                // Make sure dealer doesn't bust
                if ($_SESSION['dealerScore'] > 21) {
                    $_SESSION['dealerScore'] = 21;
                }
                // Dealer wins
                if (isset($_SESSION['credit'])) {
                    $_SESSION['credit'] -= $bet;
                }
            } else {
                // Player wins normally
                if (isset($_SESSION['credit'])) {
                    $_SESSION['credit'] += $bet;
                }
            }
        } 
        else if ($_SESSION['playerScore'] < $_SESSION['dealerScore']) {
            // Dealer wins
            if (isset($_SESSION['credit'])) {
                $_SESSION['credit'] -= $bet;
            }
        }
        // Else it's a push (tie), no money changes hands
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

function dealCard($to) {
    global $deck; // This is the key fix - declare $deck as global
    $card = array_rand($deck);
    $_SESSION[$to.'_hand'][] = $card;
    calculateScores();
}

// New function to deal rigged cards
function dealRiggedCard($to, $highCards) {
    global $deck;
    
    // Define high and low cards
    $highCardSet = array("10", "J", "Q", "K");
    $lowCardSet = array("2", "3", "4", "5", "6");
    
    // Determine which set to favor based on who's getting the card and what we want to rig
    if (($to == 'player' && $highCards) || ($to == 'dealer' && !$highCards)) {
        // Player getting high cards (more likely to bust) or dealer getting low cards (less likely to bust)
        $favoredSet = $highCardSet;
        $chance = 70; // 70% chance of getting a high card for player, or low card for dealer
    } else {
        // Player getting low cards or dealer getting high cards
        $favoredSet = $lowCardSet;
        $chance = 70; // 70% chance of getting a low card for player, or high card for dealer
    }
    
    // Determine if we're using the favored set
    if (mt_rand(1, 100) <= $chance) {
        // Use the favored set
        $card = $favoredSet[array_rand($favoredSet)];
    } else {
        // Use a random card from the deck
        $card = array_rand($deck);
    }
    
    $_SESSION[$to.'_hand'][] = $card;
    calculateScores();
}

function calculateScores() {
    global $deck; // Also need global here
    
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
?>

<style>
<?php include '../cas-style.css'; ?>
</style>

<section id="header">
    <ul id="navbar">
    <li><h2>Guthaben: <?php echo htmlspecialchars($_SESSION['credit']); ?></h2></li>
    <li><h1>GURKEN-JACK</h1></li>
    <li><a href="../../index.html"><h2>NOTFALL</h2></a></li>
    </ul>
</section>

<?php if ($_SESSION['game_state'] == 'betting'): ?>
    <form action="" method="post">
        <label for="bet">BET:</label>  
        <input type="text" id="bet" name="bet" required>
        <button type="submit">GURK-JACK</button>
    </form>
<?php else: ?>
    <div class="game-area">
        <h2>Dealer's Hand: <?php echo $_SESSION['game_state'] == 'ended' ? $_SESSION['dealerScore'] : '?'; ?></h2>
        <div class="cards">
            <?php 
            if (!empty($_SESSION['dealer_hand'])) {
                foreach ($_SESSION['dealer_hand'] as $index => $card) {
                    // Hide dealer's hole card until game ends
                    if ($_SESSION['game_state'] != 'ended' && $index > 0) {
                        echo '<div class="card">?</div>';
                    } else {
                        echo '<div class="card">' . $card . '</div>';
                    }
                }
            }
            ?>
        </div>
        
        <h2>Your Hand: <?php echo $_SESSION['playerScore']; ?></h2>
        <div class="cards">
            <?php 
            if (!empty($_SESSION['player_hand'])) {
                foreach ($_SESSION['player_hand'] as $card) {
                    echo '<div class="card">' . $card . '</div>';
                }
            }
            ?>
        </div>
        
        <p>Your current bet: <?php echo $bet; ?></p>
        
        <?php if ($_SESSION['game_state'] == 'playing'): ?>
            <form action="" method="post">
                <input type="hidden" name="bet" value="<?php echo $bet; ?>">
                <button type="submit" name="action" value="hit">Hit</button>
                <button type="submit" name="action" value="stand">Stand</button>
            </form>
        <?php else: ?>
            <div class="result">
                <?php
                if ($_SESSION['playerScore'] > 21) {
                    echo "<p>You bust! Dealer wins.</p>";
                } elseif ($_SESSION['dealerScore'] > 21) {
                    echo "<p>Dealer busts! You win!</p>";
                } elseif ($_SESSION['playerScore'] > $_SESSION['dealerScore']) {
                    echo "<p>You win!</p>";
                } elseif ($_SESSION['playerScore'] < $_SESSION['dealerScore']) {
                    echo "<p>Dealer wins.</p>";
                } else {
                    echo "<p>Push! It's a tie.</p>";
                }
                ?>
                <form action="" method="post">
                    <button type="submit" name="action" value="new_game">Play Again</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
