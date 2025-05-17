<section id="loginfield">
    <div class="contact-container">
        <h1>Account erstellen</h1>
        <div class="form-wrapper">
            <form method="post">
                <label for="username">Username</label>
                <input type="text" id="username" name="username">
                <label for="credit">Guthaben</label>
                <input type="number" name="credit" id="credit">
                <label for="passw">Passwort</label>
                <input type="password" name="passw" id="passw">
                <label for="passwrpt">Passwort wiederholen</label>
                <input type="password" name="passwrpt" id="passwrpt">
                <button type="submit">Account erstellen</button>
            </form>
        </div>
    </div>
</section>

<?php
// Only process if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data with validation
    $credit = isset($_POST["credit"]) ? $_POST["credit"] : "";
    $name_id = isset($_POST["username"]) ? trim($_POST["username"]) : "";
    $passw = isset($_POST["passw"]) ? $_POST["passw"] : "";
    $passwrpt = isset($_POST["passwrpt"]) ? $_POST["passwrpt"] : "";

    // Input validation
    if (empty($name_id)) {
        die("Bitte Nutzername eingeben!");
    }
    
    if (empty($passw)) {
        die("Bitte Passwort eingeben!");
    }
    
    if ($passw != $passwrpt) {
        die("Passwörter stimmen nicht überein!");
    }
    
    if (!is_numeric($credit) || $credit < 0) {
        die("Bitte gültiges Guthaben eingeben!");
    }
    
    $host = "localhost";
    $dbname = "data_db";
    $usernme = "root";
    $password = "";
    
    $conn = mysqli_connect($host, $usernme, $password, $dbname);
    
    if (mysqli_connect_errno()) {
        die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
    }

    // Check if username already exists
    $check_sql = "SELECT name_id FROM id WHERE name_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "s", $name_id);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    
    if (mysqli_stmt_num_rows($check_stmt) > 0) {
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
        die("Dieser Nutzername ist bereits vergeben!");
    }
    
    mysqli_stmt_close($check_stmt);
    
    // Fix for the primary key issue - get the next available ID
    $max_id_query = "SELECT MAX(id) as max_id FROM id";
    $result = mysqli_query($conn, $max_id_query);
    $row = mysqli_fetch_assoc($result);
    $next_id = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;
    
    // Insert new user with explicit ID
    $insert_sql = "INSERT INTO id (id, name_id, password, credit) VALUES (?, ?, ?, ?)";
    $insert_stmt = mysqli_prepare($conn, $insert_sql);
    
    if (!$insert_stmt) {
        mysqli_close($conn);
        die("Datenbankfehler: " . mysqli_error($conn));
    }
    
    mysqli_stmt_bind_param($insert_stmt, "issd", $next_id, $name_id, $passw, $credit);
    
    if (mysqli_stmt_execute($insert_stmt)) {
        echo "Account erfolgreich erstellt!";
    } else {
        echo "Fehler beim Erstellen des Accounts: " . mysqli_stmt_error($insert_stmt);
    }
    
    mysqli_stmt_close($insert_stmt);
    mysqli_close($conn);
}
?>
