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
$id = $_POST["id"];
$passw = $_POST["passw"];

if (! $id or ! $passw){
echo("Passwort und ID eingebne!");
}

$host = "localhost";
$dbname = "message_db";
$username = "root";
$password ="";

$conn = mysqli_connect($host, $username, $password, $dbname);


?>