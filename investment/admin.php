<?php
session_start();
$admin_page = isset($_SESSION['admin_logged_in']) ? 1 : 0;

// Database connection function to avoid duplication
function getDbConnection() {
    $host = "localhost";
    $dbname = "data_db";
    $username = "root";
    $password = "";
    
    $conn = mysqli_connect($host, $username, $password, $dbname);
    
    if (mysqli_connect_errno()) {
        die("Connection error! Please contact your local admin! " . mysqli_connect_error());
    }
    
    return $conn;
}
?>
<style>
    <?php include 'cas-style.css'; ?>
</style>
<?php if ($admin_page == 0): ?>

<section id="employeefield">
    <div class="contact-container">
        <h1>Mitarbeiter Login</h1>
        <div class="form-wrapper">
            <form method="post" action="">
                <input type="hidden" name="form_type" value="login">
                <label for="employee-id">Employee-Name</label>
                <input type="text" id="employee-id" name="employee-id">
                <label for="employee-password">Passwort</label>
                <input type="password" name="employee-password" id="employee-password">
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </div>
</section>

<?php elseif ($admin_page == 1 ): ?>
        <?php if ($_SESSION['admin_level'] > 1): ?>
        <section id="loginfield">
            <div class="contact-container">
                <h1>Account erstellen</h1>
                <div class="form-wrapper">
                    <form method="post" action="">
                        <input type="hidden" name="form_type" value="create_account">
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
        <?php endif; ?>
    <div class="stats-container">
    <h1>Statistiken</h1>
    <div class="stats-wrapper">
        <h2>Region: <?php echo htmlspecialchars($_SESSION['admin_region']); ?></h2>
          <h2>Deals: <?php echo htmlspecialchars($_SESSION['admin_deals']); ?></h2>
    </div>

    </div>
    <div class="logout-container" style="margin-top: 20px; text-align: center;">
        <form method="post" action="">
            <input type="hidden" name="form_type" value="logout">
            <button type="submit">Logout</button>
        </form>
    </div>
</section>

<?php endif; ?>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $form_type = isset($_POST["form_type"]) ? $_POST["form_type"] : "";
    
    // Handle login form
    if ($form_type == "login") {
        // Get form data
        $emp_id = isset($_POST["employee-id"]) ? $_POST["employee-id"] : "";
        $emp_pswrd = isset($_POST["employee-password"]) ? $_POST["employee-password"] : "";
        
        // Check if fields are not empty
        if (empty($emp_id) || empty($emp_pswrd)) {
            echo "Bitte geben Sie Ihre Gurksino-ID und Passwort ein.";
        } else {
            $conn = getDbConnection();
            
            // Use prepared statement to prevent SQL injection
            $sql = "SELECT id, name_id, password, level, region, deals FROM admin WHERE name_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $emp_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                // In a production environment, you should use password_verify() here
                // This assumes passwords are stored as plain text for now
                if ($row["password"] == $emp_pswrd) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $row['id'];
                    $_SESSION['admin_name'] = $row['name_id'];
                    $_SESSION['admin_level'] = $row['level'];
                    $_SESSION['admin_region'] = $row['region'];
                    $_SESSION['admin_deals'] = $row['deals'];
                    
                    // Redirect to refresh the page with new session state
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    echo "Falsches Passwort!";
                }
            } else {
                echo "Kein Benutzername gefunden";
            }
            
            $stmt->close();
            $conn->close();
        }
    }
    
    // Handle account creation form
    elseif ($form_type == "create_account" && $admin_page == 1) {
        // Get form data with validation
        $credit = isset($_POST["credit"]) ? $_POST["credit"] : "";
        $name_id = isset($_POST["username"]) ? trim($_POST["username"]) : "";
        $passw = isset($_POST["passw"]) ? $_POST["passw"] : "";
        $passwrpt = isset($_POST["passwrpt"]) ? $_POST["passwrpt"] : "";

        // Input validation
        if (empty($name_id)) {
            echo "Bitte Nutzername eingeben!";
        } elseif (empty($passw)) {
            echo "Bitte Passwort eingeben!";
        } elseif ($passw != $passwrpt) {
            echo "Passwörter stimmen nicht überein!";
        } elseif (!is_numeric($credit) || $credit < 0) {
            echo "Bitte gültiges Guthaben eingeben!";
        } else {
            $conn = getDbConnection();

            // Check if username already exists
            $check_sql = "SELECT name_id FROM id WHERE name_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $name_id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                mysqli_stmt_close($check_stmt);
                mysqli_close($conn);
                echo "Dieser Nutzername ist bereits vergeben!";
            } else {
                mysqli_stmt_close($check_stmt);
                
                // Fix for the primary key issue - get the next available ID
                $max_id_query = "SELECT MAX(id) as max_id FROM id";
                $result = mysqli_query($conn, $max_id_query);
                $row = mysqli_fetch_assoc($result);
                $next_id = ($row['max_id'] !== null) ? $row['max_id'] + 1 : 1;
                
                // In a production environment, you should use password_hash() here
                // $hashed_password = password_hash($passw, PASSWORD_DEFAULT);
                
                // Insert new user with explicit ID
                $insert_sql = "INSERT INTO id (id, name_id, password, credit) VALUES (?, ?, ?, ?)";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                
                if (!$insert_stmt) {
                    mysqli_close($conn);
                    echo "Datenbankfehler: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($insert_stmt, "issd", $next_id, $name_id, $passw, $credit);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        echo "Account erfolgreich erstellt!";
                    } else {
                        echo "Fehler beim Erstellen des Accounts: " . mysqli_stmt_error($insert_stmt);
                    }
                    
                    mysqli_stmt_close($insert_stmt);
                }
            }
            mysqli_close($conn);
        }
    }
    
    // Handle logout
    elseif ($form_type == "logout") {
        // Destroy the session
        session_unset();
        session_destroy();
        
        // Redirect to refresh the page
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
