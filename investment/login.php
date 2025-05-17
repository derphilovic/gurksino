<style>
<?php include 'cas-style.css'; ?>
</style>
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

<?php
// Only process login if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $id = isset($_POST["id"]) ? $_POST["id"] : "";
    $pswrd = isset($_POST["passw"]) ? $_POST["passw"] : "";
    
    // Check if fields are not empty
    if (empty($id) || empty($pswrd)) {
        echo "Bitte geben Sie Ihre Gurksino-ID und Passwort ein.";
    } else {
        $host = "localhost";
        $dbname = "data_db";
        $username = "root";
        $password = "";
        
        $conn = mysqli_connect($host, $username, $password, $dbname);
        
        if (mysqli_connect_errno()) {
            die("Connection error! Please contact your local admin! " . mysqli_connect_error());
        }
        
        // Use prepared statement to prevent SQL injection
        $sql = "SELECT name_id, password FROM id WHERE name_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row["password"] == $pswrd) {
                echo "Logged in!";
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
?>
