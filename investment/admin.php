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
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gurksino Admin</title>
    <style>
        <?php include 'cas-style.css'; ?>
        
        /* Responsive styles */
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        
        .contact-container, .stats-container {
            width: 90%;
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
        }
        
        input, button {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        button {
            cursor: pointer;
            background-color: #4CAF50;
            color: white;
            border: none;
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        /* Media queries */
        @media screen and (max-width: 768px) {
            .contact-container, .stats-container {
                width: 95%;
                padding: 15px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            h2 {
                font-size: 18px;
            }
        }
        
        @media screen and (max-width: 480px) {
            .contact-container, .stats-container {
                width: 100%;
                padding: 10px;
            }
            
            input, button {
                padding: 8px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            h2 {
                font-size: 16px;
            }
            
            .form-wrapper, .stats-wrapper {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
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
            echo "<div class='error-message'>Bitte geben Sie Ihre Gurksino-ID und Passwort ein.</div>";
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
                    echo "<div class='error-message'>Falsches Passwort!</div>";
                }
            } else {
                echo "<div class='error-message'>Kein Benutzername gefunden</div>";
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
            echo "<div class='error-message'>Bitte Nutzername eingeben!</div>";
        } elseif (empty($passw)) {
            echo "<div class='error-message'>Bitte Passwort eingeben!</div>";
        } elseif ($passw != $passwrpt) {
            echo "<div class='error-message'>Passwörter stimmen nicht überein!</div>";
        } elseif (!is_numeric($credit) || $credit < 0) {
            echo "<div class='error-message'>Bitte gültiges Guthaben eingeben!</div>";
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
                echo "<div class='error-message'>Dieser Nutzername ist bereits vergeben!</div>";
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
                    echo "<div class='error-message'>Datenbankfehler: " . mysqli_error($conn) . "</div>";
                } else {
                    mysqli_stmt_bind_param($insert_stmt, "issd", $next_id, $name_id, $passw, $credit);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        echo "<div class='success-message'>Account erfolgreich erstellt!</div>";
                    } else {
                        echo "<div class='error-message'>Fehler beim Erstellen des Accounts: " . mysqli_stmt_error($insert_stmt) . "</div>";
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
</body>
</html>
