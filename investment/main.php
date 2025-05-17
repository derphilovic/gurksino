<?php
// Start session
session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
?>

<style>
<?php include 'cas-style.css'; ?>
</style>
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
