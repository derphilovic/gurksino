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
$pswrd = $_POST["passw"];
$id = $_POST["id"];

$host = "localhost";
$dbname = "data_db";
$username = "root";
$password ="";

$conn = mysqli_connect($host, $username, $password, $dbname);

if (mysqli_connect_errno()) {
    die("Connection error! Please contact your local admin!" . mysqli_connect_error());
 };

$sql = "SELECT name_id, password FROM id";

 if ($result->num_rows > 0) {
    $valid = 0;
    $validpswrd = 0;
     while($row = $result->fetch_assoc()) {
       if ($row["name_id"] == $username){
        $valid = 1;
       }
       if ($row["password"] == $pswrd){
        $validpswrd = 1;
       }
     }
     if (! $valid) { 
        die("Kein Benutzername gefunden");
      }
    if (! $validpswrd) {
        die("Falsches Passwort!");
    }
   } 
$result = $conn->query($sql);

echo("Logged in!")
?>