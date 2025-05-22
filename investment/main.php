<?php
// Start session
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gurksino</title>
    <style>
    <?php include 'cas-style.css'; ?>
    </style>
</head>
<body>
    <section id="header">
        <div>
            <ul id="navbar">
            <?php if ($is_logged_in): ?>
                <li><h2>GURKSINO-ID: <?php echo htmlspecialchars($_SESSION['username']); ?></h2></li>
                <li><h2>GURKRONE: <?php echo htmlspecialchars($_SESSION['credit']); ?></h2></li>
                <li><h1>GURKSINO!</h1></li>
                <li><a href="logout.php"><h2>ABMELDEN</h2></a></li>
            <?php else: ?>
                <li><h2>GURKSINO-ID: Gast</h2></li>
                <li><h2>GURKRONE: 0</h2></li>
                <li><h1>GURKSINO!</h1></li>
                <li><a href="login.php"><h2>ANMELDEN</h2></a></li>
            <?php endif; ?>
            </ul>
        </div>
    </section>

    <section id="main">
        <div class="main-container">
        <ul id="activities">
            <li><a href="activities/gurken-jack.php"><img src="images/gurken-black.jpg" alt=""></a></li>
            <li><a href="activities/gurken-slots.php"><img src="images/gurken-slot.jpg" alt=""></a></li>
        </ul>
        </div>

        <a href="../index.html"><h1>NOTFALL</h1></a>

    </section>
</body>
</html>