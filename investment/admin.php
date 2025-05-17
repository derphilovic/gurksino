<section id="loginfield">
    <div class="contact-container">
        <h1>Account erstellen</h1>
        <div class="form-wrapper">
            <form  method="post">
                <label for="username">Username</label>
                <input type="text" id="username" name="username">
                <label for="credit">Guthaben</label>
                <input type="number" name="credit" id="credit">
                <label for="passw">Passwort</label>
                <input type="password" name="passw" id="passw">
                <label for="passwrpt">Passwort wiederholen</label>
                <input type="password" name="passwrpt" id="passwrpt">
                <button>Account erstellen</button>
            </form>
        </div>
    </div>
    
</section>

<?php
$credit = $_POST["credit"];
$name_id = $_POST["username"];
$passw = $_POST["passw"];
$passwrpt = $_POST["passwrpt"];

$host = "localhost";
$dbname = "data_db";
$usernme = "root";
$password = "";

$conn = mysqli_connect($host, $usernme, $password, $dbname);

 if (mysqli_connect_errno()) {
    die("Connection error! Fuck you!" . mysqli_connect_error());
 };

 $sql = "SELECT name_id, password, credit FROM id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
      if ($row["name_id"] == $name_id) {
          die("Name schon in benutzung");
      }
    }
  } 
  
if (! $name_id) {
    die("Nutzername eingeben!");
}
if (! $passw) {
    die("Passwort eingeben!");
}
if ($passw  != $passwrpt) {
die("Passwort falsch wiederholt");
}
if ($credit < 0) {
die("Falsches Guthaben!");
}


$sqql = "INSERT INTO id (name_id, password, credit)
        VALUES (?, ?, ?)";

$stmt = mysqli_stmt_init($conn);

if ( !mysqli_stmt_prepare($stmt, $sqql)) {
    die(mysqli_error($conn));
};

mysqli_stmt_bind_param($stmt, "sss", $name_id, $passw, $credit);
mysqli_stmt_execute($stmt);
echo "Account erstellt!";

?>