<?php
// Start session at the very beginning of the file
session_start();

// If user is already logged in, redirect to main page
if (isset($_SESSION['user_id'])) {
    header("Location: main.php");
    exit;
}

// Process login form submission before any HTML output
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $id = isset($_POST["id"]) ? $_POST["id"] : "";
    $pswrd = isset($_POST["passw"]) ? $_POST["passw"] : "";
    
    // Check if fields are not empty
    if (empty($id) || empty($pswrd)) {
        $error_message = "Bitte geben Sie Ihre Gurksino-ID und Passwort ein.";
    } else {
        $host = "localhost";
        $dbname = "data_db";
        $username = "root";
        $password = "";
        
        $conn = mysqli_connect($host, $username, $password, $dbname);
        
        if (mysqli_connect_errno()) {
            $error_message = "Connection error! Please contact your local admin! " . mysqli_connect_error();
        } else {
            // Use prepared statement to prevent SQL injection
            $sql = "SELECT id, name_id, password, credit FROM id WHERE name_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row["password"] == $pswrd) {
                    // Store user data in session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['name_id'];
                    $_SESSION['credit'] = $row['credit'];
                    
                    // Redirect to main page
                    header("Location: main.php");
                    exit;
                } else {
                    $error_message = "Falsches Passwort!";
                }
            } else {
                $error_message = "Kein Benutzername gefunden";
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="cas-style.css">
</head>
<body>
    <h1>Anmeldung</h1>

    <section id="input">
        <form action="" method="post">
        <ul>
            <label for="id">Gurksino-ID</label>
        <li><input type="text" id ="id" name="id"></li>
            <label for="passw">Passwort</label>
        <li><input type="password" id="passw" name="passw"></li>
        <br>
        <li><button>Absenden</button></li>
        </ul>
        </form>
    </section>

    <?php if (isset($error_message)): ?>
        <div class="error"><?php echo $error_message; ?></div>
    <?php endif; ?>
</body>
</html>
